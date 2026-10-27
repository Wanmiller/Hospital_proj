<?php
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/schema.php';
if (strpos(DB_NAME, 'hospital_regression_') !== 0) exit('Use an isolated hospital_regression_* database.');
$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec(str_replace('hospital_db', DB_NAME, file_get_contents(__DIR__ . '/../database/hospital.sql')));
$checks = 0;
function ensure($ok, $name) {
    global $checks;
    if (!$ok) throw new RuntimeException($name);
    $checks++;
    echo "PASS: $name\n";
}
function rejectsSql($pdo, $sql, $name) {
    try { $pdo->exec($sql); } catch (PDOException $e) { ensure($e->getCode() === '23000', $name); return; }
    throw new RuntimeException($name);
}
try {
    // Reproduce the original nullable profile schema and three corrupt profiles.
    $pdo->exec('ALTER TABLE doctors DROP FOREIGN KEY fk_doctor_user_role, DROP FOREIGN KEY fk_doctor_specialization');
    $pdo->exec('ALTER TABLE doctors DROP COLUMN user_role, MODIFY user_id INT NULL, ADD COLUMN specialization VARCHAR(100) NULL');
    $pdo->exec('UPDATE doctors d JOIN specializations s ON s.id=d.specialization_id SET d.specialization=s.name');
    $pdo->exec('ALTER TABLE doctors DROP COLUMN specialization_id');
    $pdo->exec('DROP TABLE specializations');
    $pdo->exec('ALTER TABLE doctors ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
    $pdo->exec("INSERT INTO users (id,username,password_hash,role,first_name,last_name,is_active) VALUES (5,'former_doctor','unchanged','Receptionist','Existing','User',0)");
    $pdo->exec("INSERT INTO doctors (id,user_id,specialization) VALUES (3,5,'  General   practitioner  '),(4,NULL,'Intern'),(5,NULL,'Therapist')");
    $pdo->exec("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,status) VALUES (1,3,'2026-10-01','11:00','Completed')");
    $pdo->exec("INSERT INTO medical_records (appointment_id,diagnosis) VALUES (3,'Existing record')");
    $pdo->exec("INSERT INTO prescriptions (medical_record_id,medicine_name,dosage,frequency,duration) VALUES (2,'Existing','test','test','test')");
    $snapshots = [];
    foreach (['users','appointments','medical_records','prescriptions'] as $table) $snapshots[$table] = $pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll();
    $fixed = repairDoctorAccounts($pdo);
    ensure(count($fixed) === 3, 'Three invalid profiles repaired');
    upgradeHospitalSchema($pdo);
    foreach (['appointments','medical_records','prescriptions'] as $table) ensure($pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll() === $snapshots[$table], "$table history unchanged");
    ensure(array_slice($pdo->query('SELECT * FROM users ORDER BY id')->fetchAll(), 0, 5) === $snapshots['users'], 'Existing identities, roles and passwords unchanged');
    ensure($pdo->query("SELECT COUNT(*) FROM doctors d JOIN users u ON u.id=d.user_id WHERE d.id IN (3,4,5) AND u.role='Doctor' AND u.is_active=0")->fetchColumn() == 3, 'Recovered profiles use inactive doctor accounts');
    ensure(count(repairDoctorAccounts($pdo)) === 0, 'Repeated repair creates no accounts');
    upgradeHospitalSchema($pdo);
    ensure(true, 'Schema upgrade is repeatable');
    rejectsSql($pdo, 'INSERT INTO doctors (user_id) VALUES (NULL)', 'Null doctor account rejected');
    rejectsSql($pdo, 'INSERT INTO doctors (user_id) VALUES (2)', 'Receptionist cannot own doctor profile');
    rejectsSql($pdo, "UPDATE users SET role='Receptionist' WHERE id=3", 'Direct role change rejected');
    rejectsSql($pdo, 'DELETE FROM users WHERE id=3', 'Doctor account deletion rejected');
    rejectsSql($pdo, 'DELETE FROM doctors WHERE id=3', 'Doctor with history cannot be deleted');
    rejectsSql($pdo, 'UPDATE doctors SET specialization_id=99999 WHERE id=3', 'Unknown specialization rejected');
    $first = specializationId($pdo, 'General practitioner');
    ensure($first == specializationId($pdo, '  GENERAL   practitioner '), 'Specialization case and spaces do not create duplicates');
    ensure($pdo->query('SELECT specialization_id FROM doctors WHERE id=3')->fetchColumn() == $first, 'Legacy specialization migrated');
    $pdo->beginTransaction();
    try { prepareUserRoleChange($pdo, 3, 'Receptionist'); throw new RuntimeException('History role change accepted'); }
    catch (DomainException $expected) { $pdo->rollBack(); ensure(true, 'UI role change with history refused'); }
    $userId = $pdo->query('SELECT user_id FROM doctors WHERE id=4')->fetchColumn();
    $pdo->beginTransaction();
    prepareUserRoleChange($pdo, $userId, 'Receptionist');
    $pdo->exec("UPDATE users SET role='Receptionist' WHERE id=" . (int)$userId);
    $pdo->commit();
    ensure($pdo->query('SELECT COUNT(*) FROM doctors WHERE id=4')->fetchColumn() == 0, 'Role change without history removes unused profile');
    echo "$checks checks passed.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
