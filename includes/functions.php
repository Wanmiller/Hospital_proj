<?php
require_once __DIR__ . '/i18n.php';
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function denyAccess($message) {
    http_response_code(403);
    die(h(t($message)));
}

function activityBadge($active) {
    return '<span class="badge activity-badge ' . ($active ? 'is-active' : 'is-inactive') . '">' . h(t($active ? 'Active' : 'Inactive')) . '</span>';
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}
?>
