<?php
require_once __DIR__ . '/doctor_integrity.php';
// Fictional fixtures for project topic 4. Existing records are never overwritten.
function seedHospitalDemoData(PDO $pdo) {
    $lockName = 'hospital_demo_' . $pdo->query('SELECT DATABASE()')->fetchColumn();
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    if ($lock->fetchColumn() != 1) {
        throw new RuntimeException('Another demo-data import is running. Try again later.');
    }
    $added = array_fill_keys(['users', 'departments', 'doctors', 'patients', 'appointments', 'medical_records', 'prescriptions', 'specializations'], 0);
    try {
        $pdo->beginTransaction();
        $specialtiesBefore = (int)$pdo->query('SELECT COUNT(*) FROM specializations')->fetchColumn();
        $find = function ($table, $key, $value) use ($pdo) {
            $stmt = $pdo->prepare("SELECT id FROM `$table` WHERE `$key` = ?");
            $stmt->execute([$value]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($ids) > 1) throw new RuntimeException("Ambiguous demo identifier in $table: $key");
            return $ids[0] ?? false;
        };
        $insert = function ($table, array $data) use ($pdo, &$added) {
            $columns = '`' . implode('`, `', array_keys($data)) . '`';
            $marks = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $pdo->prepare("INSERT INTO `$table` ($columns) VALUES ($marks)");
            $stmt->execute(array_values($data));
            $added[$table]++;
            return $pdo->lastInsertId();
        };
        $departmentIds = [];
        foreach (['Demo: General Practice', 'Demo: Diagnostics'] as $name) {
            $departmentIds[] = $find('departments', 'name', $name) ?: $insert('departments', ['name' => $name, 'description' => 'Fictional department for the topic 4 demonstration.']);
        }
        $doctorIds = [];
        foreach ([['Әлия', 'Үлгіқызы', 'General practitioner'], ['Ержан', 'Үлгіұлы', 'Diagnostic specialist'], ['Мәдина', 'Тестова', 'General practitioner']] as $i => [$first, $last, $specialty]) {
            $username = 'demo_topic4_doctor_' . ($i + 1);
            $userId = $find('users', 'username', $username);
            if (!$userId) {
                $userId = $insert('users', ['username' => $username, 'password_hash' => password_hash('password123', PASSWORD_DEFAULT), 'role' => 'Doctor', 'first_name' => $first, 'last_name' => $last, 'is_active' => 1]);
            } else {
                $user = $pdo->prepare('SELECT role, is_active FROM users WHERE id = ?');
                $user->execute([$userId]);
                $row = $user->fetch(PDO::FETCH_ASSOC);
                if ($row['role'] !== 'Doctor' || !$row['is_active']) throw new RuntimeException("Demo account $username is not an active doctor; existing account left unchanged.");
            }
            $doctorIds[] = $find('doctors', 'user_id', $userId) ?: $insert('doctors', ['user_id' => $userId, 'department_id' => $departmentIds[$i % 2], 'specialization_id' => specializationId($pdo, $specialty), 'phone' => 'DEMO-DOC-' . ($i + 1)]);
        }
        $patients = [
            ['Әли', 'Үлгіұлы', '1997-02-12', 'Male'],
            ['Айша', 'Үлгіқызы', '1999-05-21', 'Female'],
            ['Нұрлан', 'Тестов', '1986-08-03', 'Male'],
            ['Дана', 'Тестова', '1992-11-18', 'Female'],
            ['Ғалым', 'Үлгіұлы', '1981-03-04', 'Male'],
            ['Әсел', 'Үлгіқызы', '1995-07-15', 'Female'],
            ['Өмір', 'Тестов', '1978-09-23', 'Male'],
            ['Іңкәр', 'Тестова', '2001-12-06', 'Female'],
            ['Қанат', 'Үлгіұлы', '1990-04-10', 'Male'],
            ['Үміт', 'Үлгіқызы', '1988-06-29', 'Female'],
            ['Шыңғыс', 'Тестов', '1993-10-16', 'Male'],
            ['Гүлназ', 'Тестова', '2000-01-08', 'Female'],
        ];
        $patientIds = [];
        foreach ($patients as $i => [$first, $last, $dob, $gender]) {
            $email = 'topic4.patient.' . ($i + 1) . '@example.test';
            $patientIds[] = $find('patients', 'email', $email) ?: $insert('patients', ['first_name' => $first, 'last_name' => $last, 'dob' => $dob, 'gender' => $gender, 'phone' => sprintf('DEMO-PAT-%02d', $i + 1), 'email' => $email, 'address' => 'Оқу мысалы — ойдан шығарылған мекенжай']);
        }
        for ($i = 0; $i < 18; $i++) {
            $reason = sprintf('[DEMO-TOPIC4-%02d] Demonstration appointment', $i + 1);
            $status = $i < 9 ? 'Completed' : ($i < 15 ? 'Scheduled' : 'Cancelled');
            $date = $i < 9 ? sprintf('2026-10-%02d', $i + 1) : sprintf('2026-10-%02d', $i + 5);
            $appointmentId = $find('appointments', 'reason', $reason);
            if (!$appointmentId) {
                $appointmentId = $insert('appointments', ['patient_id' => $patientIds[$i % 12], 'doctor_id' => $doctorIds[$i % 3], 'appointment_date' => $date, 'appointment_time' => '09:00:00', 'reason' => $reason, 'status' => $status]);
            }
            // Do not add clinical records to a demo appointment changed by a user.
            $state = $pdo->prepare('SELECT status FROM appointments WHERE id = ?');
            $state->execute([$appointmentId]);
            if ($status !== 'Completed' || $state->fetchColumn() !== 'Completed') continue;
            $recordId = $find('medical_records', 'appointment_id', $appointmentId) ?: $insert('medical_records', ['appointment_id' => $appointmentId, 'diagnosis' => 'DEMO ONLY: fictional consultation record', 'treatment' => 'Educational example; not a treatment recommendation.', 'notes' => 'Оқу деректері. Нақты пациенттің медициналық жазбасы емес.']);
            $rx = $pdo->prepare("SELECT id FROM prescriptions WHERE medical_record_id = ? AND medicine_name = 'DEMO ONLY: sample medicine'");
            $rx->execute([$recordId]);
            if (!$rx->fetchColumn()) {
                $insert('prescriptions', ['medical_record_id' => $recordId, 'medicine_name' => 'DEMO ONLY: sample medicine', 'dosage' => 'Not applicable (demo)', 'frequency' => 'Not applicable (demo)', 'duration' => 'Not applicable (demo)']);
            }
        }
        $added['specializations'] = (int)$pdo->query('SELECT COUNT(*) FROM specializations')->fetchColumn() - $specialtiesBefore;
        $pdo->commit();
        return $added;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}
