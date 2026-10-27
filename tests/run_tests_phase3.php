<?php
require_once __DIR__ . '/../includes/functions.php';
// Test runner for Phase 3
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
    global $results, $res; if(!$passed) file_put_contents("debug.txt", $res["body"]);
    $results[] = "- $name: " . ($passed ? "PASS" : "FAIL");
}

$cookie = 'cookie_p3.txt';
if(file_exists($cookie)) unlink($cookie);

// Login as Admin
$login_page = fetch('/login.php', $cookie);
$csrf = get_csrf($login_page['body']);
fetch('/login.php', $cookie, ['username' => 'admin', 'password' => 'password123', 'csrf_token' => $csrf]);

// --- DEPARTMENTS ---
$dept_page = fetch('/admin/departments.php', $cookie);
$csrf = get_csrf($dept_page['body']);

$dept_name = 'TestDept_' . uniqid();

// Add department
$res = fetch('/admin/departments.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'name' => $dept_name,
    'description' => 'Test Desc'
]);
report("Add department", strpos($res['body'], t('Department added successfully.')) !== false);

// Duplicate department rejected
$res = fetch('/admin/departments.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'name' => $dept_name,
    'description' => 'Duplicate'
]);
report("Duplicate department rejected", strpos($res['body'], t('Department name already exists.')) !== false);

require __DIR__ . '/../includes/db.php';
$test_dept_id = $pdo->query("SELECT id FROM departments WHERE name='$dept_name'")->fetchColumn();

// Edit department
$res = fetch('/admin/departments.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'edit',
    'id' => $test_dept_id,
    'name' => $dept_name . '_Edited',
    'description' => 'Edited'
]);
report("Edit department", strpos($res['body'], t('Department updated successfully.')) !== false);

// Delete empty department
$res = fetch('/admin/departments.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'delete',
    'id' => $test_dept_id
]);
report("Delete empty department", strpos($res['body'], t('Department deleted successfully.')) !== false);

// Cannot delete department with doctors (Cardiology is id=1, has doctor_smith)
$res = fetch('/admin/departments.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'delete',
    'id' => 1
]);
report("Cannot delete department with doctors", strpos($res['body'], t('This department cannot be deleted because doctors are assigned to it.')) !== false);

// --- USERS ---
$users_page = fetch('/admin/users.php', $cookie);
$csrf = get_csrf($users_page['body']);

$rec_user = 'rec_' . uniqid();

// Add Receptionist
$res = fetch('/admin/users.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'username' => $rec_user,
    'password' => 'test1234',
    'first_name' => 'Rec',
    'last_name' => 'Test',
    'role' => 'Receptionist'
]);
report("Add Receptionist", strpos($res['body'], t('User added successfully.')) !== false);
$test_user_id = $pdo->query("SELECT id FROM users WHERE username='$rec_user'")->fetchColumn();

// Duplicate username
$res = fetch('/admin/users.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'username' => $rec_user,
    'password' => 'test',
    'first_name' => 'A',
    'last_name' => 'B',
    'role' => 'Admin'
]);
report("Duplicate username rejected", strpos($res['body'], t('Username already exists.')) !== false);

// Edit user (without password)
$res = fetch('/admin/users.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'edit',
    'id' => $test_user_id,
    'username' => $rec_user,
    'password' => '',
    'first_name' => 'RecEdited',
    'last_name' => 'Test',
    'role' => 'Receptionist'
]);
report("Edit user without changing password", strpos($res['body'], t('User updated successfully.')) !== false);

// Deactivate user
$res = fetch('/admin/users.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'toggle_active',
    'id' => $test_user_id
]);
report("Deactivate user", strpos($res['body'], t('User status updated.')) !== false);

// Current Admin cannot deactivate themselves (Admin is id=1 usually)
$admin_id = $pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
$res = fetch('/admin/users.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'toggle_active',
    'id' => $admin_id
]);
report("Admin cannot deactivate themselves", strpos($res['body'], t('You cannot deactivate your own account.')) !== false);

// --- DOCTORS ---
$docs_page = fetch('/admin/doctors.php', $cookie);
$csrf = get_csrf($docs_page['body']);

$dr_user = 'dr_' . uniqid();

// Create doctor
$res = fetch('/admin/doctors.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'add',
    'username' => $dr_user,
    'password' => 'tardis',
    'first_name' => 'The',
    'last_name' => 'Doctor',
    'department_id' => '2',
    'specialization' => 'Time Travel',
    'phone' => '999'
]);
report("Create doctor", strpos($res['body'], t('Doctor added successfully.')) !== false);
$dr_user_id = $pdo->query("SELECT id FROM users WHERE username='$dr_user'")->fetchColumn();
file_put_contents('debug_id.txt', "dr_user_id is: " . var_export($dr_user_id, true));

// Edit doctor
$res = fetch('/admin/doctors.php', $cookie, [
    'csrf_token' => $csrf,
    'action' => 'edit',
    'user_id' => $dr_user_id,
    'username' => $dr_user,
    'password' => '',
    'first_name' => 'The',
    'last_name' => 'Doctor',
    'department_id' => '1',
    'specialization' => 'Space',
    'phone' => '111'
]);
$passed = strpos($res['body'], t('Doctor updated successfully.')) !== false;
report("Edit doctor", $passed);
if (!$passed) {
    file_put_contents('debug_edit_doc.txt', $res['body']);
}

echo implode("\n", $results);
?>
