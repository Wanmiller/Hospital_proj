<?php
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/demo_data.php';
if (strpos(DB_NAME, 'hospital_regression_') !== 0) {
    fwrite(STDERR, "Use a separate hospital_regression_* database.\n");
    exit(1);
}
$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$sql = str_replace('hospital_db', DB_NAME, file_get_contents(__DIR__ . '/../database/hospital.sql'));
$pdo->exec($sql);
$tables = ['users', 'departments', 'doctors', 'patients', 'appointments', 'medical_records', 'prescriptions'];
$snapshot = function () use ($pdo, $tables) {
    $rows = [];
    foreach ($tables as $table) $rows[$table] = $pdo->query("SELECT * FROM `$table` ORDER BY id")->fetchAll();
    return $rows;
};
$checks = 0;
$check = function ($ok, $name) use (&$checks) {
    if (!$ok) throw new RuntimeException($name);
    $checks++;
    echo "PASS: $name\n";
};
try {
    $before = $snapshot();
    $added = seedHospitalDemoData($pdo);
    $check(array_sum($added) === 58, '58 related demo records including specializations added');
    $after = $snapshot();
    foreach ($tables as $table) {
        $check(array_slice($after[$table], 0, count($before[$table])) === $before[$table], "$table original records unchanged");
    }
    $check(array_sum(seedHospitalDemoData($pdo)) === 0, 'Repeated import adds no duplicates');
    $check($snapshot() === $after, 'Repeated import changes no records');
    $check($pdo->query("SELECT first_name FROM patients WHERE email='topic4.patient.1@example.test'")->fetchColumn() === 'Әли', 'Kazakh text round-trips correctly');
    $check($pdo->query("SELECT COUNT(*) FROM appointments a LEFT JOIN patients p ON p.id=a.patient_id LEFT JOIN doctors d ON d.id=a.doctor_id WHERE p.id IS NULL OR d.id IS NULL")->fetchColumn() == 0, 'Appointment relationships valid');
    $check($pdo->query("SELECT COUNT(*) FROM medical_records m JOIN appointments a ON a.id=m.appointment_id WHERE a.status<>'Completed'")->fetchColumn() == 0, 'Medical records belong to completed visits');
    // Force a slot conflict midway through a fresh import: everything must roll back.
    $pdo->exec($sql);
    $pdo->exec("INSERT INTO users (id,username,password_hash,role,first_name,last_name) VALUES (5,'demo_topic4_doctor_1','unused','Doctor','Test','Test')");
    $pdo->exec("INSERT INTO doctors (id,user_id) VALUES (3,5)");
    $pdo->exec("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time) VALUES (1,3,'2026-10-01','09:00')");
    $beforeConflict = $snapshot();
    try {
        seedHospitalDemoData($pdo);
        throw new RuntimeException('Conflict was not rejected');
    } catch (PDOException $expected) {
        $check(($expected->errorInfo[1] ?? null) == 1062, 'Conflicting slot rejected');
    }
    $check($snapshot() === $beforeConflict, 'Failed import rolls back all added data');
    echo "$checks checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
