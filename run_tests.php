<?php
require_once __DIR__ . '/includes/functions.php';
// A simple test runner
$base_url = getenv('HOSPITAL_TEST_URL') ?: 'http://localhost:8000';

function fetch($path, $cookie_file = '', $post_data = null) {
    global $base_url;
    $ch = curl_init($base_url . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    // Don't follow redirects to see where it goes
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
    
    $location = '';
    if (preg_match('/^Location:\s*(.+)$/im', $header, $matches)) {
        $location = trim($matches[1]);
    }
    
    return [
        'status' => $status,
        'body' => $body,
        'location' => $location
    ];
}

function get_csrf($body) {
    if (preg_match('/name="csrf_token" value="([^"]+)"/', $body, $matches)) {
        return $matches[1];
    }
    return '';
}

$results = [];
function report($name, $passed) {
    global $results;
    $results[] = "- $name: " . ($passed ? "PASS" : "FAIL");
}

// 2. Database Connection
$res = fetch('/login.php');
report("Database connection (loads login.php)", $res['status'] == 200 && strpos($res['body'], t('Database Connection failed.')) === false);

// 4. Unauthenticated redirects
$res = fetch('/admin/dashboard.php');
report("Unauthenticated access to /admin/dashboard.php redirects to login", strpos($res['location'], 'login.php') !== false);
$res = fetch('/doctor/dashboard.php');
report("Unauthenticated access to /doctor/dashboard.php redirects to login", strpos($res['location'], 'login.php') !== false);
$res = fetch('/receptionist/dashboard.php');
report("Unauthenticated access to /receptionist/dashboard.php redirects to login", strpos($res['location'], 'login.php') !== false);

// 5. CSRF Tests
$cookie = 'cookie_test.txt';
if(file_exists($cookie)) unlink($cookie);
$login_page = fetch('/login.php', $cookie);
$csrf = get_csrf($login_page['body']);

// Missing CSRF
$res = fetch('/login.php', $cookie, ['username' => 'admin', 'password' => 'password123']);
report("Login fails when CSRF token is missing", strpos($res['body'], t('Invalid CSRF token.')) !== false);

// Invalid CSRF
$res = fetch('/login.php', $cookie, ['username' => 'admin', 'password' => 'password123', 'csrf_token' => 'invalid_token']);
report("Login fails when CSRF token is invalid", strpos($res['body'], t('Invalid CSRF token.')) !== false);

// 3. Login behavior
// Valid Admin
$res = fetch('/login.php', $cookie, ['username' => 'admin', 'password' => 'password123', 'csrf_token' => $csrf]);
report("Valid Admin login works (redirects to admin/dashboard)", strpos($res['location'], 'admin/dashboard.php') !== false);

// Test Admin Authorization
$res = fetch('/doctor/dashboard.php', $cookie);
report("Admin cannot access Doctor pages", $res['status'] === 403);
$res = fetch('/receptionist/dashboard.php', $cookie);
report("Admin cannot access Receptionist pages", $res['status'] === 403);

// Logout
$res = fetch('/logout.php', $cookie);
report("Logout redirects to login.php", strpos($res['location'], 'login.php') !== false);
$res = fetch('/admin/dashboard.php', $cookie);
report("Protected pages cannot be accessed after logout", strpos($res['location'], 'login.php') !== false);

// Valid Receptionist
if(file_exists($cookie)) unlink($cookie);
$login_page = fetch('/login.php', $cookie);
$csrf = get_csrf($login_page['body']);
$res = fetch('/login.php', $cookie, ['username' => 'reception', 'password' => 'password123', 'csrf_token' => $csrf]);
report("Valid Receptionist login works", strpos($res['location'], 'receptionist/dashboard.php') !== false);

// Test Receptionist Authorization
$res = fetch('/admin/dashboard.php', $cookie);
report("Receptionist cannot access Admin pages", $res['status'] === 403);
$res = fetch('/doctor/dashboard.php', $cookie);
report("Receptionist cannot access Doctor pages", $res['status'] === 403);

// Valid Doctor
if(file_exists($cookie)) unlink($cookie);
$login_page = fetch('/login.php', $cookie);
$csrf = get_csrf($login_page['body']);
$res = fetch('/login.php', $cookie, ['username' => 'doctor_jones', 'password' => 'password123', 'csrf_token' => $csrf]);
report("Valid Doctor login works", strpos($res['location'], 'doctor/dashboard.php') !== false);

// Test Doctor Authorization
$res = fetch('/admin/dashboard.php', $cookie);
report("Doctor cannot access Admin pages", $res['status'] === 403);
$res = fetch('/receptionist/dashboard.php', $cookie);
report("Doctor cannot access Receptionist pages", $res['status'] === 403);

// Inactive Doctor
require_once __DIR__ . '/includes/db.php';
$originalActive = $pdo->query("SELECT is_active FROM users WHERE username = 'doctor_smith'")->fetchColumn();
$pdo->exec("UPDATE users SET is_active = 0 WHERE username = 'doctor_smith'");
try {
if(file_exists($cookie)) unlink($cookie);
$login_page = fetch('/login.php', $cookie);
$csrf = get_csrf($login_page['body']);
$res = fetch('/login.php', $cookie, ['username' => 'doctor_smith', 'password' => 'password123', 'csrf_token' => $csrf]);
report("Inactive doctor_smith cannot log in", strpos($res['body'], t('Your account is inactive.')) !== false);
} finally {
    $restore = $pdo->prepare("UPDATE users SET is_active = ? WHERE username = 'doctor_smith'");
    $restore->execute([$originalActive]);
}

// Wrong password
if(file_exists($cookie)) unlink($cookie);
$login_page = fetch('/login.php', $cookie);
$csrf = get_csrf($login_page['body']);
$res = fetch('/login.php', $cookie, ['username' => 'admin', 'password' => 'wrongpass', 'csrf_token' => $csrf]);
report("Wrong password is rejected", strpos($res['body'], t('Invalid username or password.')) !== false);

echo implode("\n", $results);
?>
