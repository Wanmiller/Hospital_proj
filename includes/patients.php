<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/appointments.php';

function patientInput(array $input, $key) {
    return isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
}

function registerPatient(PDO $pdo, array $input) {
    $username = patientInput($input, 'username');
    $first = patientInput($input, 'first_name');
    $last = patientInput($input, 'last_name');
    $dob = patientInput($input, 'dob');
    $gender = patientInput($input, 'gender');
    $phone = patientInput($input, 'phone');
    $password = isset($input['password']) && is_string($input['password']) ? $input['password'] : '';
    $confirm = isset($input['password_confirm']) && is_string($input['password_confirm']) ? $input['password_confirm'] : '';
    if (!preg_match('/\A[a-zA-Z0-9_.-]{3,50}\z/', $username)) throw new DomainException('Patient username rule');
    if ($first === '' || $last === '' || mb_strlen($first) > 50 || mb_strlen($last) > 50) throw new DomainException('Patient name rule');
    if (!validClinicDate($dob) || $dob < '1900-01-01' || $dob > clinicToday()) throw new DomainException('Patient birth date rule');
    if (!in_array($gender, ['Male','Female','Other'], true)) throw new DomainException('Invalid field value.');
    if (!preg_match('/\A[+0-9() .-]{7,20}\z/', $phone) || strlen(preg_replace('/\D/', '', $phone)) < 7) throw new DomainException('Patient phone rule');
    if (mb_strlen($password, 'UTF-8') < 8 || strlen($password) > 72 || strpos($password, "\0") !== false) throw new DomainException('Patient password rule');
    if ($password !== $confirm) throw new DomainException('Passwords do not match.');
    $pdo->beginTransaction();
    try {
        // Role and patient identity are assigned on the server. No existing card
        // is claimed by matching a name/phone supplied by an anonymous visitor.
        $stmt = $pdo->prepare("INSERT INTO users (username,password_hash,role,first_name,last_name) VALUES (?,?,'Patient',?,?)");
        $stmt->execute([$username,password_hash($password, PASSWORD_DEFAULT),$first,$last]);
        $userId = $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO patients (first_name,last_name,dob,gender,phone) VALUES (?,?,?,?,?)');
        $stmt->execute([$first,$last,$dob,$gender,$phone]);
        $stmt = $pdo->prepare('INSERT INTO patient_accounts (user_id,patient_id) VALUES (?,?)');
        $stmt->execute([$userId,$pdo->lastInsertId()]);
        $pdo->commit();
        return $userId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function patientAccount(PDO $pdo, $userId, $lock = false) {
    $stmt = $pdo->prepare("SELECT p.* FROM patient_accounts pa JOIN patients p ON p.id=pa.patient_id JOIN users u ON u.id=pa.user_id WHERE pa.user_id=? AND u.role='Patient' AND u.is_active=1" . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$userId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) throw new DomainException('Patient account unavailable.');
    return $patient;
}

function patientBookingDate($date) {
    $today = clinicToday();
    $limit = (new DateTimeImmutable($today))->modify('+90 days')->format('Y-m-d');
    return validClinicDate($date) && $date >= $today && $date <= $limit;
}

function patientSlotTimes($date) {
    if (!patientBookingDate($date)) return [];
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Qyzylorda'));
    $times = [];
    for ($minutes = 9 * 60; $minutes < 17 * 60; $minutes += 30) {
        $time = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        if (new DateTimeImmutable("$date $time", new DateTimeZone('Asia/Qyzylorda')) > $now) $times[] = $time;
    }
    return $times;
}

function patientAvailableSlots(PDO $pdo, $doctorId, $date) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id=(SELECT user_id FROM doctors WHERE id=?) AND role='Doctor' AND is_active=1");
    $stmt->execute([$doctorId]);
    if (!$stmt->fetchColumn()) return [];
    $stmt = $pdo->prepare("SELECT appointment_time FROM appointments WHERE doctor_id=? AND appointment_date=? AND status<>'Cancelled'");
    $stmt->execute([$doctorId,$date]);
    $occupied = array_map('displayTime', $stmt->fetchAll(PDO::FETCH_COLUMN));
    return array_values(array_diff(patientSlotTimes($date), $occupied));
}

function bookPatientAppointment(PDO $pdo, $userId, array $input) {
    $date = patientInput($input, 'date');
    $time = patientInput($input, 'time');
    $doctorId = patientInput($input, 'doctor_id');
    $reason = patientInput($input, 'reason');
    if (!ctype_digit($doctorId) || (int)$doctorId < 1) throw new DomainException('Please select an active doctor.');
    if (!patientBookingDate($date)) throw new DomainException('Patient booking date rule');
    if (!in_array($time, patientSlotTimes($date), true)) throw new DomainException('Patient slot rule');
    if (mb_strlen($reason) > 2000) throw new DomainException('Patient reason rule');
    $pdo->beginTransaction();
    try {
        $patient = patientAccount($pdo, $userId, true);
        // Serialize with activation/role changes and competing self bookings.
        $stmt = $pdo->prepare("SELECT u.id FROM doctors d JOIN users u ON u.id=d.user_id WHERE d.id=? AND u.role='Doctor' AND u.is_active=1 FOR UPDATE");
        $stmt->execute([$doctorId]);
        if (!$stmt->fetchColumn()) throw new DomainException('Please select an active doctor.');
        saveAppointment($pdo, 'add', ['patient_id'=>$patient['id'], 'doctor_id'=>$doctorId, 'date'=>$date, 'time'=>$time, 'reason'=>$reason]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function cancelPatientAppointment(PDO $pdo, $userId, $appointmentId) {
    $pdo->beginTransaction();
    try {
        $patient = patientAccount($pdo, $userId, true);
        $stmt = $pdo->prepare('SELECT id FROM appointments WHERE id=? AND patient_id=? FOR UPDATE');
        $stmt->execute([$appointmentId,$patient['id']]);
        if (!$stmt->fetchColumn()) throw new DomainException('Appointment unavailable.');
        cancelAppointment($pdo, $appointmentId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
