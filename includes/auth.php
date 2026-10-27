<?php
require_once __DIR__ . '/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    require_once __DIR__ . '/db.php';
    $stmt = $pdo->prepare("SELECT username, first_name, last_name, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !$user['is_active'] || $user['role'] !== ($_SESSION['role'] ?? null)) {
        $_SESSION = [];
        session_destroy();
        header("Location: ../login.php");
        exit();
    }
    foreach (['username', 'first_name', 'last_name'] as $field) {
        $_SESSION[$field] = $user[$field];
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        denyAccess('Access Denied: You do not have permission to view this page.');
    }
}

function requireAnyRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        denyAccess('Access Denied: You do not have permission to view this page.');
    }
}
?>
