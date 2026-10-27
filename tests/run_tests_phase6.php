<?php
require_once __DIR__ . '/../includes/functions.php';
// Test runner for Phase 6
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
    if(!$passed && isset($res['body'])) file_put_contents("debug_p6_$name.txt", $res["body"]);
    $results[] = "- $name: " . ($passed ? "PASS" : "FAIL");
}

$cookie_admin = 'cookie_p6_admin.txt';
$cookie_recep = 'cookie_p6_recep.txt';
$cookie_doc = 'cookie_p6_doc.txt';
if(file_exists($cookie_admin)) unlink($cookie_admin);
if(file_exists($cookie_recep)) unlink($cookie_recep);
if(file_exists($cookie_doc)) unlink($cookie_doc);

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

// Doctor
$res = fetch('/login.php', $cookie_doc);
$csrf = get_csrf($res['body']);
fetch('/login.php', $cookie_doc, ['csrf_token' => $csrf, 'username' => 'doctor_smith', 'password' => 'password123']);

// 1. Authorization checks
$res = fetch('/admin/reports.php', $cookie_doc);
report("Doctor cannot access admin/reports.php", $res['status'] === 403);

$res = fetch('/admin/reports.php', $cookie_recep);
report("Receptionist cannot access admin/reports.php", $res['status'] === 403);

// 2. Admin Reports Page Check
$res = fetch('/admin/reports.php', $cookie_admin);
report("Admin can access admin/reports.php", strpos($res['body'], t('Hospital Analytics & Reports')) !== false);

// 3. Admin Reports Sections Check
report("Reports: Appointments per Doctor", strpos($res['body'], t('Appointments per Doctor (Detailed)')) !== false);
report("Reports: Monthly Appointment Statistics", strpos($res['body'], t('Monthly Appointment Statistics')) !== false);

// 4. Filters check
$res = fetch('/admin/reports.php?date_from=2030-01-01&date_to=2030-12-31', $cookie_admin);
report("Reports with Filters", strpos($res['body'], '2030-01-01') !== false);

// 5. Admin Dashboard check
$res = fetch('/admin/dashboard.php', $cookie_admin);
report("Admin Dashboard loaded", strpos($res['body'], t('Total Patients')) !== false);
report("Admin Dashboard JOIN check", strpos($res['body'], 'Sarah Jones') !== false);

// 6. Receptionist Dashboard check
$res = fetch('/receptionist/dashboard.php', $cookie_recep);
report("Receptionist Dashboard updated", strpos($res['body'], t('Day appointments')) !== false);

// 7. Doctor Dashboard check
$res = fetch('/doctor/dashboard.php', $cookie_doc);
report("Doctor Dashboard updated", strpos($res['body'], t('My day appointments')) !== false);

echo implode("\n", $results) . "\n";
