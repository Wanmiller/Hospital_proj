<?php
require_once __DIR__ . '/../includes/functions.php';
// Requires a separate PHP server with the same HOSPITAL_DB_* environment.
// Never run these fixtures against the application's working database.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run regression checks from the command line.');
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/schema.php';
require_once __DIR__ . '/../includes/appointments.php';
if (strpos(DB_NAME, 'hospital_regression_') !== 0) {
    fwrite(STDERR, "Set HOSPITAL_DB_NAME to an isolated hospital_regression_* database.\n");
    exit(1);
}
$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if (($argv[1] ?? '') === '--seed') {
    $sql = file_get_contents(__DIR__ . '/../database/hospital.sql');
    $pdo->exec(str_replace('hospital_db', DB_NAME, $sql));
    echo "Isolated sample database initialized.\n";
    exit();
}

if (($argv[1] ?? '') === '--slot-worker') {
    $pdo->exec('USE `' . DB_NAME . '`');
    echo "ready\n";
    flush();
    try {
        $pdo->exec("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time) VALUES (1, 1, '2030-01-03', '09:00')");
        echo "inserted\n";
    } catch (PDOException $e) {
        echo ($e->errorInfo[1] ?? null) == 1062 ? "duplicate\n" : "error\n";
    }
    exit();
}

$base = getenv('HOSPITAL_TEST_URL') ?: 'http://127.0.0.1:18000';
$cookies = [];
$checks = 0;
function check($condition, $name) {
    global $checks;
    if (!$condition) throw new RuntimeException($name);
    $checks++;
    echo "PASS: $name\n";
}
function request($path, $cookie, $post = null) {
    global $base;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie, CURLOPT_TIMEOUT => 15]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $response = curl_exec($ch);
    if ($response === false) throw new RuntimeException(curl_error($ch));
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $result = ['status' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'headers' => substr($response, 0, $size), 'body' => substr($response, $size)];
    curl_close($ch);
    return $result;
}
function token($body) {
    preg_match('/name="csrf_token" value="([^"]+)"/', $body, $match);
    return $match[1] ?? '';
}
function loginAs($username, $password) {
    global $cookies;
    $cookie = tempnam(sys_get_temp_dir(), 'hospital_cookie_');
    $cookies[] = $cookie;
    $page = request('/login.php', $cookie);
    $response = request('/login.php', $cookie, ['username' => $username, 'password' => $password, 'csrf_token' => token($page['body'])]);
    check($response['status'] === 302, "$username login");
    return $cookie;
}
function rejects($callback, $name) {
    try {
        $callback();
    } catch (DomainException $e) {
        check(true, $name);
        return;
    }
    check(false, $name);
}

try {
    // The name guard above ensures these resets only affect the test fixture.
    $pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
    $adminCookie = tempnam(sys_get_temp_dir(), 'hospital_cookie_');
    $cookies[] = $adminCookie;
    $setup = request('/setup_db.php', $adminCookie);
    check(strpos($setup['body'], 'Database setup complete!') !== false, 'Fresh setup creates usable schema');
    $pdo->exec('USE `' . DB_NAME . '`');
    upgradeHospitalSchema($pdo);
    upgradeHospitalSchema($pdo);
    check(true, 'Schema upgrade is repeatable');
    $adminCookie = loginAs('admin', 'admin123');
    $hash = password_hash('test123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, first_name, last_name) VALUES ('regression_doctor', ?, 'Doctor', 'Test', 'Doctor')");
    $stmt->execute([$hash]);
    $doctorUser = $pdo->lastInsertId();
    $pdo->exec("INSERT INTO doctors (user_id) VALUES ($doctorUser)");
    $pdo->exec("INSERT INTO patients (first_name, last_name, dob, phone) VALUES ('Test', 'Patient', '2000-01-01', '123')");
    $data = ['id' => null, 'patient_id' => 1, 'doctor_id' => 1, 'date' => '2030-01-01', 'time' => '09:00', 'reason' => 'Visit'];
    saveAppointment($pdo, 'add', $data);
    $data['id'] = $pdo->query('SELECT MAX(id) FROM appointments')->fetchColumn();
    $doctorCookie = loginAs('regression_doctor', 'test123');
    $page = request('/doctor/patient_history.php', $doctorCookie);
    check(strpos($page['body'], 'Test Patient') !== false && strpos($page['body'], 'patient_id=1') !== false, 'History navigation offers doctor patients');
    check(strpos($page['body'], 'data-bs-target="#navbarNav"') !== false, 'Mobile menu has collapse target');

    $page = request('/doctor/appointment_details.php?id=' . $data['id'], $doctorCookie);
    $response = request('/doctor/appointment_details.php?id=' . $data['id'], $doctorCookie, ['csrf_token' => token($page['body']), 'action' => 'save_medical_record', 'diagnosis' => 'Regression diagnosis']);
    check(strpos($response['body'], t('Medical record saved and appointment completed.')) !== false, 'Doctor completes appointment');
    rejects(function () use ($pdo, $data) { saveAppointment($pdo, 'edit', $data); }, 'Completed appointment rejects edit');
    rejects(function () use ($pdo, $data) { cancelAppointment($pdo, $data['id']); }, 'Completed appointment rejects cancellation');
    $receptionCookie = loginAs('reception', 'rec123');
    $page = request('/receptionist/appointments.php', $receptionCookie);
    $response = request('/receptionist/appointments.php', $receptionCookie, ['csrf_token' => token($page['body']), 'action' => 'cancel', 'id' => $data['id']]);
    check(strpos($response['body'], t('Only scheduled appointments can be changed or cancelled.')) !== false, 'Forged cancellation POST is rejected');
    $response = request('/receptionist/appointments.php', $receptionCookie, ['csrf_token' => token($page['body']), 'action' => 'edit', 'id' => $data['id'], 'patient_id' => 1, 'doctor_id' => 1, 'appointment_date' => '2031-01-01', 'appointment_time' => '10:00', 'reason' => 'Changed']);
    check(strpos($response['body'], t('Only scheduled appointments can be changed or cancelled.')) !== false, 'Forged completed-appointment edit is rejected');
    check($pdo->query('SELECT appointment_date FROM appointments WHERE id=' . (int) $data['id'])->fetchColumn() === '2030-01-01', 'Completed appointment date retained');
    check($pdo->query('SELECT COUNT(*) FROM medical_records')->fetchColumn() == 1, 'Medical history retained');

    $data['date'] = '2030-01-02';
    saveAppointment($pdo, 'add', $data);
    $data['id'] = $pdo->query('SELECT MAX(id) FROM appointments')->fetchColumn();
    try {
        saveAppointment($pdo, 'add', $data);
        check(false, 'Duplicate slot rejected');
    } catch (PDOException $e) {
        check(($e->errorInfo[1] ?? null) == 1062, 'Duplicate slot rejected');
    }
    cancelAppointment($pdo, $data['id']);
    rejects(function () use ($pdo, $data) { saveAppointment($pdo, 'edit', $data); }, 'Cancelled appointment rejects edit');
    saveAppointment($pdo, 'add', $data);
    check(true, 'Cancelled slot can be booked again');
    $id = $pdo->query('SELECT MAX(id) FROM appointments')->fetchColumn();
    cancelAppointment($pdo, $id);
    check(true, 'Multiple cancelled appointments may share a slot');

    $pdo->beginTransaction();
    $pdo->exec("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time) VALUES (1, 1, '2030-01-03', '09:00')");
    $worker = proc_open([PHP_BINARY, __FILE__, '--slot-worker'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($worker)) throw new RuntimeException('Could not start concurrent booking worker');
    fgets($pipes[1]);
    // Give the second connection time to attempt its insert while the first holds the slot.
    usleep(200000);
    $pdo->commit();
    $output = stream_get_contents($pipes[1]);
    foreach ($pipes as $pipe) fclose($pipe);
    proc_close($worker);
    check(trim($output) === 'duplicate', 'Concurrent booking allows only one winner');

    $pdo->exec("UPDATE users SET is_active = 0 WHERE id = $doctorUser");
    $response = request('/doctor/dashboard.php', $doctorCookie);
    check($response['status'] === 302 && strpos($response['headers'], 'login.php') !== false, 'Deactivation revokes existing session');
    $pdo->exec("UPDATE users SET is_active = 1 WHERE id = $doctorUser");
    $doctorCookie = loginAs('regression_doctor', 'test123');
    try {
        $pdo->exec("UPDATE users SET role = 'Receptionist' WHERE id = $doctorUser");
        check(false, 'Doctor role protected by foreign key');
    } catch (PDOException $e) {
        check($e->getCode() === '23000', 'Doctor role protected by foreign key');
    }
    $pdo->exec("UPDATE users SET role = 'Admin' WHERE username = 'reception'");
    $response = request('/receptionist/dashboard.php', $receptionCookie);
    check($response['status'] === 302, 'Role change revokes existing session');

    // Simulate the old installer schema and verify its upgrade path.
    $pdo->exec('ALTER TABLE users DROP COLUMN is_active, DROP COLUMN updated_at');
    // InnoDB may use the slot index to support the doctor foreign key.
    $pdo->exec('ALTER TABLE appointments ADD INDEX regression_doctor_fk (doctor_id)');
    $pdo->exec('ALTER TABLE appointments DROP INDEX unique_doctor_slot, DROP COLUMN occupied_slot');
    $pdo->exec("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time) VALUES (1, 1, '2030-01-03', '09:00')");
    $duplicateId = $pdo->lastInsertId();
    $before = $pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn();
    try {
        upgradeHospitalSchema($pdo);
        check(false, 'Upgrade refuses existing booking conflicts');
    } catch (RuntimeException $e) {
        check(strpos($e->getMessage(), 'Conflicting appointments') !== false, 'Upgrade refuses existing booking conflicts');
    }
    check($pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn() == $before, 'Failed upgrade preserves appointment data');
    $pdo->exec('DELETE FROM appointments WHERE id=' . (int) $duplicateId);
    upgradeHospitalSchema($pdo);
    check($pdo->query("SELECT is_active FROM users LIMIT 1")->fetchColumn() == 1, 'Old schema gains active users and slot constraint');
    echo "$checks checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    foreach ($cookies as $cookie) if (file_exists($cookie)) unlink($cookie);
}
