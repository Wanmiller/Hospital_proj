<?php
require_once __DIR__ . '/doctor_integrity.php';
require_once __DIR__ . '/patient_schema.php';
// Upgrade existing installations without replacing their data.
function upgradeHospitalSchema(PDO $pdo) {
    $hasColumn = function ($table, $column) use ($pdo) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    };
    if (!$hasColumn('users', 'is_active')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE');
    }
    foreach (['users', 'departments', 'doctors', 'patients', 'appointments', 'medical_records', 'prescriptions'] as $table) {
        if (!$hasColumn($table, 'updated_at')) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }
    // Refuse to discard or silently cancel existing conflicting appointments.
    $duplicate = $pdo->query("SELECT doctor_id FROM appointments WHERE status != 'Cancelled' GROUP BY doctor_id, appointment_date, appointment_time HAVING COUNT(*) > 1 LIMIT 1")->fetchColumn();
    if ($duplicate !== false) {
        throw new RuntimeException('Conflicting appointments exist. Resolve duplicate doctor/date/time slots before running the upgrade again.');
    }
    if (!$hasColumn('appointments', 'occupied_slot')) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN occupied_slot TINYINT GENERATED ALWAYS AS (CASE WHEN status = 'Cancelled' THEN NULL ELSE 1 END) STORED");
    }
    $index = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND INDEX_NAME = 'unique_doctor_slot'")->fetchColumn();
    if (!$index) {
        $pdo->exec('ALTER TABLE appointments ADD UNIQUE KEY unique_doctor_slot (doctor_id, appointment_date, appointment_time, occupied_slot)');
    }
    $uniqueDepartment = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'departments' AND COLUMN_NAME = 'name' AND NON_UNIQUE = 0")->fetchColumn();
    if (!$uniqueDepartment) {
        $pdo->exec('ALTER TABLE departments ADD UNIQUE KEY unique_department_name (name)');
    }
    upgradeDoctorIntegrity($pdo);
    upgradePatientSchema($pdo);
}
