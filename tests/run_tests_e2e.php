<?php
require_once __DIR__ . '/../includes/functions.php';
// E2E Test Runner for Phase 7
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
    if(!$passed && isset($res['body'])) file_put_contents("debug_e2e_$name.txt", $res["body"]);
    $results[] = "- $name: " . ($passed ? "PASS" : "FAIL");
}

$cookie_admin = 'cookie_e2e_admin.txt';
$cookie_recep = 'cookie_e2e_recep.txt';
$cookie_doc = 'cookie_e2e_doc.txt';
if(file_exists($cookie_admin)) unlink($cookie_admin);
if(file_exists($cookie_recep)) unlink($cookie_recep);
if(file_exists($cookie_doc)) unlink($cookie_doc);

require __DIR__ . '/../includes/db.php';

// Check DB state before test
$initial_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$initial_docs = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$initial_depts = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

// --- 1. Login Admin ---
$res = fetch('/login.php', $cookie_admin);
$csrf = get_csrf($res['body']);
fetch('/login.php', $cookie_admin, ['csrf_token' => $csrf, 'username' => 'admin', 'password' => 'password123']);

// --- 2. Create Dept ---
$res = fetch('/admin/departments.php', $cookie_admin);
$csrf = get_csrf($res['body']);
$res = fetch('/admin/departments.php', $cookie_admin, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'name' => 'E2E Department',
    'description' => 'Test Desc'
]);
report("Create Department", strpos($res['body'], t('Department added successfully.')) !== false);
$dept_id = $pdo->query("SELECT id FROM departments WHERE name='E2E Department'")->fetchColumn();

// --- 3. Create Doctor ---
$res = fetch('/admin/doctors.php', $cookie_admin);
$csrf = get_csrf($res['body']);
$res = fetch('/admin/doctors.php', $cookie_admin, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'username' => 'e2e_doc',
    'password' => 'password123',
    'first_name' => 'Doc',
    'last_name' => 'E2e',
    'department_id' => $dept_id,
    'specialization' => 'Spec',
    'phone' => '123'
]);
report("Create Doctor", strpos($res['body'], t('Doctor added successfully.')) !== false);
$doc_id = $pdo->query("SELECT d.id FROM doctors d JOIN users u ON d.user_id = u.id WHERE u.username='e2e_doc'")->fetchColumn();

// Logout Admin
fetch('/logout.php', $cookie_admin);

// --- 4. Login Receptionist ---
$res = fetch('/login.php', $cookie_recep);
$csrf = get_csrf($res['body']);
fetch('/login.php', $cookie_recep, ['csrf_token' => $csrf, 'username' => 'reception', 'password' => 'password123']);

// --- 5. Create Patient ---
$res = fetch('/receptionist/patients.php', $cookie_recep);
$csrf = get_csrf($res['body']);
$res = fetch('/receptionist/patients.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'first_name' => 'Pat',
    'last_name' => 'E2e',
    'dob' => '2000-01-01',
    'gender' => 'Male',
    'phone' => '123'
]);
report("Create Patient", strpos($res['body'], t('Patient added successfully.')) !== false);
$pat_id = $pdo->query("SELECT id FROM patients WHERE first_name='Pat' AND last_name='E2e'")->fetchColumn();

// --- 6. Schedule Appointment ---
$res = fetch('/receptionist/appointments.php', $cookie_recep);
$csrf = get_csrf($res['body']);
$res = fetch('/receptionist/appointments.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'patient_id' => $pat_id,
    'doctor_id' => $doc_id,
    'appointment_date' => '2030-10-10',
    'appointment_time' => '10:00',
    'reason' => 'Test'
]);
report("Schedule Appointment", strpos($res['body'], t('Appointment added successfully.')) !== false);

// --- 7. Attempt Double Booking ---
$res = fetch('/receptionist/appointments.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'patient_id' => $pat_id,
    'doctor_id' => $doc_id,
    'appointment_date' => '2030-10-10',
    'appointment_time' => '10:00',
    'reason' => 'Double'
]);
report("Double Booking Rejected", strpos($res['body'], t('This doctor already has an appointment at this date and time.')) !== false);
$app_id = $pdo->query("SELECT id FROM appointments WHERE patient_id=$pat_id AND doctor_id=$doc_id")->fetchColumn();

// Test cancelled appointment reuse
fetch('/receptionist/appointments.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'cancel',
    'id' => $app_id
]);
$res = fetch('/receptionist/appointments.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'patient_id' => $pat_id,
    'doctor_id' => $doc_id,
    'appointment_date' => '2030-10-10',
    'appointment_time' => '10:00',
    'reason' => 'Rebooked'
]);
report("Cancelled Slot Reused", strpos($res['body'], t('Appointment added successfully.')) !== false);
$new_app_id = $pdo->query("SELECT id FROM appointments WHERE patient_id=$pat_id AND doctor_id=$doc_id AND status='Scheduled'")->fetchColumn();

// Logout Recep
fetch('/logout.php', $cookie_recep);

// --- 8. Login Doctor ---
$res = fetch('/login.php', $cookie_doc);
$csrf = get_csrf($res['body']);
fetch('/login.php', $cookie_doc, ['csrf_token' => $csrf, 'username' => 'e2e_doc', 'password' => 'password123']);

// --- 9. Check Dashboard & Apps ---
$res = fetch('/doctor/my_appointments.php', $cookie_doc);
report("Assigned App Visible", strpos($res['body'], 'Rebooked') !== false);

// --- 10. Open App & Add Med Record ---
$res = fetch("/doctor/appointment_details.php?id=$new_app_id", $cookie_doc);
$csrf = get_csrf($res['body']);
$res = fetch("/doctor/appointment_details.php?id=$new_app_id", $cookie_doc, [
    'csrf_token' => $csrf,
    'action' => 'save_medical_record',
    'appointment_id' => $new_app_id,
    'diagnosis' => 'E2E Diagnosis'
]);
report("Save Medical Record & Complete App", strpos($res['body'], t('Medical record saved and appointment completed.')) !== false);

$status = $pdo->query("SELECT status FROM appointments WHERE id=$new_app_id")->fetchColumn();
report("App Status is Completed", $status === 'Completed');

// --- 11. Add Prescriptions ---
$res = fetch("/doctor/appointment_details.php?id=$new_app_id", $cookie_doc);
$csrf = get_csrf($res['body']);
$res = fetch("/doctor/appointment_details.php?id=$new_app_id", $cookie_doc, [
    'csrf_token' => $csrf,
    'action' => 'add_prescription',
    'appointment_id' => $new_app_id,
    'medicine_name' => 'Med 1', 'dosage' => '1', 'frequency' => '1', 'duration' => '1'
]);
$res = fetch("/doctor/appointment_details.php?id=$new_app_id", $cookie_doc, [
    'csrf_token' => $csrf,
    'action' => 'add_prescription',
    'appointment_id' => $new_app_id,
    'medicine_name' => 'Med 2', 'dosage' => '2', 'frequency' => '2', 'duration' => '2'
]);
report("Add Prescriptions", strpos($res['body'], t('Prescription added.')) !== false);

// --- 12. Check Patient History ---
$res = fetch("/doctor/patient_history.php?patient_id=$pat_id", $cookie_doc);
report("Patient History Shows Prescriptions", strpos($res['body'], 'Med 1') !== false && strpos($res['body'], 'Med 2') !== false && strpos($res['body'], 'E2E Diagnosis') !== false);

// Logout Doctor
fetch('/logout.php', $cookie_doc);

// --- 13. Login Admin to Check Reports ---
$res = fetch('/login.php', $cookie_admin);
$csrf = get_csrf($res['body']);
fetch('/login.php', $cookie_admin, ['csrf_token' => $csrf, 'username' => 'admin', 'password' => 'password123']);

$res = fetch('/admin/dashboard.php', $cookie_admin);
$final_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$final_docs = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$final_depts = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

report("Admin Dashboard Counters Updated", ($final_patients > $initial_patients) && ($final_docs > $initial_docs) && ($final_depts > $initial_depts));

$res = fetch('/admin/reports.php', $cookie_admin);
report("Reports Show Completed Status", strpos($res['body'], 'Doc E2e') !== false);
report("Reports Include New Department", strpos($res['body'], 'E2E Department') !== false);

echo implode("\n", $results) . "\n";
