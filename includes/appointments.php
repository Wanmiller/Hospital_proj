<?php
// The caller owns the transaction. Lock the appointment before checking its state.
function lockScheduledAppointment(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT status FROM appointments WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() !== 'Scheduled') {
        throw new DomainException('Only scheduled appointments can be changed or cancelled.');
    }
    $record = $pdo->prepare('SELECT id FROM medical_records WHERE appointment_id = ?');
    $record->execute([$id]);
    if ($record->fetchColumn() !== false) {
        throw new DomainException('An appointment with a medical record cannot be changed or cancelled.');
    }
}

function saveAppointment(PDO $pdo, $action, array $data) {
    if (!in_array($action, ['add', 'edit'], true)) {
        throw new DomainException('Invalid appointment action.');
    }
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        if ($action === 'edit') {
            lockScheduledAppointment($pdo, $data['id']);
        }
        $doctor = $pdo->prepare("SELECT d.id FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ? AND u.is_active = 1 AND u.role = 'Doctor'");
        $doctor->execute([$data['doctor_id']]);
        if ($doctor->fetchColumn() === false) {
            throw new DomainException('Please select an active doctor.');
        }
        $params = [$data['patient_id'], $data['doctor_id'], $data['date'], $data['time'], $data['reason']];
        if ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE appointments SET patient_id=?, doctor_id=?, appointment_date=?, appointment_time=?, reason=? WHERE id=? AND status='Scheduled'");
            $params[] = $data['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status) VALUES (?, ?, ?, ?, ?, 'Scheduled')");
        }
        $stmt->execute($params);
        if ($ownsTransaction) $pdo->commit();
    } catch (Exception $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function cancelAppointment(PDO $pdo, $id) {
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        lockScheduledAppointment($pdo, $id);
        $stmt = $pdo->prepare("UPDATE appointments SET status='Cancelled' WHERE id=? AND status='Scheduled'");
        $stmt->execute([$id]);
        if ($ownsTransaction) $pdo->commit();
    } catch (Exception $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
