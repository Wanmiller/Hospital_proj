<?php
require_once __DIR__ . '/../includes/functions.php';
// Test runner for Phase 5
$base_url = getenv('HOSPITAL_TEST_URL') ?: 'http://localhost:8000';

function fetch($path, $cookie_file = '', $post_data = null) {
    global $base_url;
    $ch = curl_init($base_url . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($post_data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    }
    if ($cookie_file) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    }
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    curl_close($ch);
    return ['status' => $status, 'body' => $body, 'header' => $header];
}

function get_csrf($body) {
    if (preg_match('/name="csrf_token" value="([^"]+)"/', $body, $matches)) {
        return $matches[1];
    }
    return '';
}

$results = [];
function report($name, $passed) {
    global $results, $res;
    if(!$passed && isset($res['body'])) file_put_contents("debug_p5_$name.txt", $res["body"]);
    $results[] = "- $name: " . ($passed ? "PASS" : "FAIL");
}

$cookie_admin = 'cookie_p5_admin.txt';
$cookie_recep = 'cookie_p5_recep.txt';
$cookie_doc = 'cookie_p5_doc.txt';
$cookie_doc2 = 'cookie_p5_doc2.txt';
if(file_exists($cookie_admin)) unlink($cookie_admin);
if(file_exists($cookie_recep)) unlink($cookie_recep);
if(file_exists($cookie_doc)) unlink($cookie_doc);
if(file_exists($cookie_doc2)) unlink($cookie_doc2);

require __DIR__ . '/../includes/db.php';

// Login users
// Admin
$res = fetch('/login.php', $cookie_admin);
$csrf = get_csrf($res['body']);
fetch('/login.php', $cookie_admin, ['csrf_token' => $csrf, 'username' => 'admin', 'password' => 'password123']);

// Receptionist
$res = fetch('/login.php', $cookie_recep);
$csrf = get_csrf($res['body']);
fetch('/login.php', $cookie_recep, ['csrf_token' => $csrf, 'username' => 'reception', 'password' => 'password123']);

// Doctor 1 (doctor_smith)
$res = fetch('/login.php', $cookie_doc);
$csrf = get_csrf($res['body']);
fetch('/login.php', $cookie_doc, ['csrf_token' => $csrf, 'username' => 'doctor_smith', 'password' => 'password123']);

// Doctor 2 (doctor_jones)
$res = fetch('/login.php', $cookie_doc2);
$csrf = get_csrf($res['body']);
fetch('/login.php', $cookie_doc2, ['csrf_token' => $csrf, 'username' => 'doctor_jones', 'password' => 'password123']);

// 1. Authorization checks
$res = fetch('/admin/dashboard.php', $cookie_doc);
report("Doctor cannot access Admin pages", $res['status'] === 403);

$res = fetch('/receptionist/dashboard.php', $cookie_doc);
report("Doctor cannot access Receptionist pages", $res['status'] === 403);

$res = fetch('/doctor/dashboard.php', $cookie_admin);
report("Admin cannot access Doctor pages", $res['status'] === 403);

$res = fetch('/doctor/dashboard.php', $cookie_recep);
report("Receptionist cannot access Doctor pages", $res['status'] === 403);

// Setup appointments
$pat_id = $pdo->query("SELECT id FROM patients LIMIT 1")->fetchColumn();
$doc1_id = $pdo->query("SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username='doctor_smith')")->fetchColumn();
$doc2_id = $pdo->query("SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username='doctor_jones')")->fetchColumn();

$pdo->exec("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) VALUES ($pat_id, $doc1_id, '2030-01-01', '10:00:00', 'Scheduled')");
$app1_id = $pdo->lastInsertId();

$pdo->exec("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) VALUES ($pat_id, $doc2_id, '2030-01-01', '11:00:00', 'Scheduled')");
$app2_id = $pdo->lastInsertId();

$pdo->exec("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) VALUES ($pat_id, $doc1_id, '2030-01-01', '12:00:00', 'Cancelled')");
$app_cancel_id = $pdo->lastInsertId();

// Doctor sees own appointments
$res = fetch('/doctor/my_appointments.php', $cookie_doc);
report("Doctor sees own appointments", strpos($res['body'], '2030') !== false);

// Cannot access another doctor's appointment
$res = fetch("/doctor/appointment_details.php?id=$app2_id", $cookie_doc);
report("Cannot access another doctor's appointment", $res['status'] === 403);

// Create medical record
$a_page = fetch("/doctor/appointment_details.php?id=$app1_id", $cookie_doc);
$csrf = get_csrf($a_page['body']);
$res = fetch("/doctor/appointment_details.php?id=$app1_id", $cookie_doc, [
    'csrf_token' => $csrf,
    'action' => 'save_medical_record',
    'appointment_id' => $app1_id,
    'diagnosis' => 'Test Diagnosis'
]);
report("Create medical record", strpos($res['body'], t('Medical record saved and appointment completed.')) !== false);

// Appointment becomes Completed
$status = $pdo->query("SELECT status FROM appointments WHERE id=$app1_id")->fetchColumn();
report("Appointment becomes Completed", $status === 'Completed');

// Edit medical record
$a_page = fetch("/doctor/appointment_details.php?id=$app1_id", $cookie_doc);
$csrf = get_csrf($a_page['body']);
$res = fetch("/doctor/appointment_details.php?id=$app1_id", $cookie_doc, [
    'csrf_token' => $csrf,
    'action' => 'save_medical_record',
    'appointment_id' => $app1_id,
    'diagnosis' => 'Updated Diagnosis'
]);
report("Edit medical record", strpos($res['body'], t('Medical record updated successfully.')) !== false);

// Cannot edit another doctor's medical record
$res = fetch("/doctor/appointment_details.php?id=$app1_id", $cookie_doc2, [
    'csrf_token' => $csrf,
    'action' => 'save_medical_record',
    'appointment_id' => $app1_id,
    'diagnosis' => 'Hack'
]);
report("Cannot edit another doctor's medical record", $res['status'] === 403);

// Add prescription
$a_page = fetch("/doctor/appointment_details.php?id=$app1_id", $cookie_doc);
$csrf = get_csrf($a_page['body']);
$res = fetch("/doctor/appointment_details.php?id=$app1_id", $cookie_doc, [
    'csrf_token' => $csrf,
    'action' => 'add_prescription',
    'appointment_id' => $app1_id,
    'medicine_name' => 'Aspirin',
    'dosage' => '1',
    'frequency' => 'daily',
    'duration' => '1 week'
]);
report("Add prescription", strpos($res['body'], t('Prescription added.')) !== false);

// Cannot complete cancelled appointment
$res = fetch("/doctor/appointment_details.php?id=$app_cancel_id", $cookie_doc, [
    'csrf_token' => $csrf,
    'action' => 'save_medical_record',
    'appointment_id' => $app_cancel_id,
    'diagnosis' => 'Hack Cancelled'
]);
report("Cannot complete cancelled appointment", strpos($res['body'], t('Access Denied: Cannot modify a cancelled appointment.')) !== false);

// History ownership
// Doctor 1 has seen Patient 1. Doctor 2 has also seen Patient 1 (scheduled). Let's check if Doctor 1 can see history.
$res = fetch("/doctor/patient_history.php?patient_id=$pat_id", $cookie_doc);
report("Doctor can view history for their own patient", strpos($res['body'], t('Patient Medical History')) !== false);

// Let's create Patient 2 and give them NO appointments with Doc 1.
$pdo->exec("INSERT INTO patients (first_name, last_name, dob, gender, phone) VALUES ('NoDoc1', 'Patient', '2000-01-01', 'Male', '123')");
$pat2_id = $pdo->lastInsertId();

$res = fetch("/doctor/patient_history.php?patient_id=$pat2_id", $cookie_doc);
report("Doctor cannot view unrelated patient history", $res['status'] === 403);

echo implode("\n", $results) . "\n";
