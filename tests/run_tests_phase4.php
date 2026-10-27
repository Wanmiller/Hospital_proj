<?php
require_once __DIR__ . '/../includes/functions.php';
// Test runner for Phase 4
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
    if(!$passed && isset($res['body'])) file_put_contents("debug_p4_$name.txt", $res["body"]);
    $results[] = "- $name: " . ($passed ? "PASS" : "FAIL");
}

$cookie_admin = 'cookie_p4_admin.txt';
$cookie_recep = 'cookie_p4_recep.txt';
$cookie_doc = 'cookie_p4_doc.txt';
if(file_exists($cookie_admin)) unlink($cookie_admin);
if(file_exists($cookie_recep)) unlink($cookie_recep);
if(file_exists($cookie_doc)) unlink($cookie_doc);

require __DIR__ . '/../includes/db.php';

// Login as Admin
$res = fetch('/login.php', $cookie_admin);
$csrf = get_csrf($res['body']);
$res = fetch('/login.php', $cookie_admin, [
    'csrf_token' => $csrf,
    'username' => 'admin',
    'password' => 'password123'
]);

// Login as Receptionist (create one first if needed, but we can assume one exists from previous phase or create one)
// We will create Receptionist if not exists
$recep_exists = $pdo->query("SELECT COUNT(*) FROM users WHERE username='receptionist'")->fetchColumn();
if (!$recep_exists) {
    $pdo->exec("INSERT INTO users (username, password_hash, role, first_name, last_name) VALUES ('receptionist', '".password_hash('password123', PASSWORD_DEFAULT)."', 'Receptionist', 'Test', 'Receptionist')");
}
$res = fetch('/login.php', $cookie_recep);
$csrf = get_csrf($res['body']);
$res = fetch('/login.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'username' => 'receptionist',
    'password' => 'password123'
]);

// Login as Doctor
$res = fetch('/login.php', $cookie_doc);
$csrf = get_csrf($res['body']);
$res = fetch('/login.php', $cookie_doc, [
    'csrf_token' => $csrf,
    'username' => 'doctor_smith',
    'password' => 'password123'
]);


// --- SECURITY TESTS ---
$res = fetch('/receptionist/dashboard.php', $cookie_admin);
report("Admin cannot access Receptionist pages", $res['status'] === 403); 

$res = fetch('/receptionist/dashboard.php', $cookie_doc);
report("Doctor cannot access Receptionist pages", $res['status'] === 403);

// --- PATIENT TESTS ---
$p_page = fetch('/receptionist/patients.php', $cookie_recep);
$csrf = get_csrf($p_page['body']);

// Create patient
$res = fetch('/receptionist/patients.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'dob' => '1990-01-01',
    'gender' => 'Male',
    'phone' => '1234567890'
]);
report("Create patient", strpos($res['body'], t('Patient added successfully.')) !== false);
$pat_id = $pdo->query("SELECT id FROM patients WHERE first_name='John' AND last_name='Doe' ORDER BY id DESC LIMIT 1")->fetchColumn();

// Edit patient
$p_page = fetch('/receptionist/patients.php', $cookie_recep);
$csrf = get_csrf($p_page['body']);
$res = fetch('/receptionist/patients.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'edit',
    'id' => $pat_id,
    'first_name' => 'John',
    'last_name' => 'Doe',
    'dob' => '1990-01-01',
    'gender' => 'Male',
    'phone' => '0987654321',
    'email' => 'john@example.com'
]);
report("Edit patient", strpos($res['body'], t('Patient updated successfully.')) !== false);

// Search patient
$res = fetch('/receptionist/patients.php?search=0987654321', $cookie_recep);
report("Search patient", strpos($res['body'], 'john@example.com') !== false);

// View patient
$res = fetch("/receptionist/patient_details.php?id=$pat_id", $cookie_recep);
report("View patient", strpos($res['body'], 'john@example.com') !== false && strpos($res['body'], t('Patient Profile')) !== false);

// Invalid email rejected
$p_page = fetch('/receptionist/patients.php', $cookie_recep);
$csrf = get_csrf($p_page['body']);
$res = fetch('/receptionist/patients.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'first_name' => 'Bad',
    'last_name' => 'Email',
    'dob' => '1990-01-01',
    'gender' => 'Male',
    'phone' => '123',
    'email' => 'invalid-email'
]);
report("Invalid email rejected", strpos($res['body'], t('Invalid email format.')) !== false);


// Missing fields rejected
$p_page = fetch('/receptionist/patients.php', $cookie_recep);
$csrf = get_csrf($p_page['body']);
$res = fetch('/receptionist/patients.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'first_name' => '', // Missing
    'last_name' => 'Missing',
    'dob' => '1990-01-01',
    'gender' => 'Male',
    'phone' => '123'
]);
report("Missing required fields rejected", strpos($res['body'], t('First name is required.')) !== false);


// --- APPOINTMENTS TESTS ---
$a_page = fetch('/receptionist/appointments.php', $cookie_recep);
$csrf = get_csrf($a_page['body']);

$doc_id = $pdo->query("SELECT id FROM doctors LIMIT 1")->fetchColumn();

// Create appointment
$res = fetch('/receptionist/appointments.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'patient_id' => $pat_id,
    'doctor_id' => $doc_id,
    'appointment_date' => '2027-01-01',
    'appointment_time' => '10:00',
    'reason' => 'Test reason'
]);
report("Create appointment", strpos($res['body'], t('Appointment added successfully.')) !== false);
$app_id = $pdo->query("SELECT id FROM appointments WHERE patient_id=$pat_id AND doctor_id=$doc_id ORDER BY id DESC LIMIT 1")->fetchColumn();

// Edit appointment
$a_page = fetch('/receptionist/appointments.php', $cookie_recep);
$csrf = get_csrf($a_page['body']);
$res = fetch('/receptionist/appointments.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'edit',
    'id' => $app_id,
    'patient_id' => $pat_id,
    'doctor_id' => $doc_id,
    'appointment_date' => '2027-01-01',
    'appointment_time' => '10:00',
    'reason' => 'Edited reason'
]);
report("Edit appointment", strpos($res['body'], t('Appointment updated successfully.')) !== false);

// Prevent double booking
$a_page = fetch('/receptionist/appointments.php', $cookie_recep);
$csrf = get_csrf($a_page['body']);
$res = fetch('/receptionist/appointments.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'patient_id' => $pat_id,
    'doctor_id' => $doc_id,
    'appointment_date' => '2027-01-01',
    'appointment_time' => '10:00',
    'reason' => 'Another reason'
]);
report("Prevent double booking", strpos($res['body'], t('This doctor already has an appointment at this date and time.')) !== false);

// Cancel appointment
$a_page = fetch('/receptionist/appointments.php', $cookie_recep);
$csrf = get_csrf($a_page['body']);
$res = fetch('/receptionist/appointments.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'cancel',
    'id' => $app_id
]);
report("Cancel appointment", strpos($res['body'], t('Appointment cancelled successfully.')) !== false);

// Verify cancelled slot behavior (allow double booking now)
$a_page = fetch('/receptionist/appointments.php', $cookie_recep);
$csrf = get_csrf($a_page['body']);
$res = fetch('/receptionist/appointments.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'patient_id' => $pat_id,
    'doctor_id' => $doc_id,
    'appointment_date' => '2027-01-01',
    'appointment_time' => '10:00',
    'reason' => 'Rebooked reason'
]);
report("Verify cancelled-slot behavior", strpos($res['body'], t('Appointment added successfully.')) !== false);


// Filter by status
$res = fetch('/receptionist/appointments.php?status=Cancelled', $cookie_recep);
report("Filter by status", strpos($res['body'], 'Edited reason') !== false);


// Prevent deletion of patient with appointments
$p_page = fetch('/receptionist/patients.php', $cookie_recep);
$csrf = get_csrf($p_page['body']);
$res = fetch('/receptionist/patients.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'delete',
    'id' => $pat_id
]);
report("Prevent deletion of patient with appointments", strpos($res['body'], t('This patient cannot be deleted because appointment history exists.')) !== false);

// Delete patient without appointments
// Create a temporary patient
$p_page = fetch('/receptionist/patients.php', $cookie_recep);
$csrf = get_csrf($p_page['body']);
$res = fetch('/receptionist/patients.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'first_name' => 'Temp',
    'last_name' => 'Patient',
    'dob' => '2000-01-01',
    'gender' => 'Female',
    'phone' => '000000'
]);
$temp_pat_id = $pdo->query("SELECT id FROM patients WHERE first_name='Temp' ORDER BY id DESC LIMIT 1")->fetchColumn();
// Delete it
$p_page = fetch('/receptionist/patients.php', $cookie_recep);
$csrf = get_csrf($p_page['body']);
$res = fetch('/receptionist/patients.php', $cookie_recep, [
    'csrf_token' => $csrf,
    'action' => 'delete',
    'id' => $temp_pat_id
]);
report("Delete patient without appointments", strpos($res['body'], t('Patient deleted successfully.')) !== false);

echo implode("\n", $results) . "\n";
