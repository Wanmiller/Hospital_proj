<?php
// Create tables from the same schema used by the sample SQL import.
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/schema.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    $sql = file_get_contents(__DIR__ . '/database/hospital.sql');
    if ($sql === false || !preg_match_all('/CREATE TABLE \w+\s*\(.*?\);/s', $sql, $matches)) {
        throw new RuntimeException('Could not read the hospital schema.');
    }
    foreach ($matches[0] as $statement) {
        $pdo->exec(preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $statement));
    }
    upgradeHospitalSchema($pdo);
    echo "Tables created successfully.<br>";

    // 3. Insert Default Admin if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, first_name, last_name) VALUES (?, ?, 'Admin', 'Super', 'Admin')");
        $stmt->execute(['admin', $admin_pass]);
        echo "Default Admin user created. (Username: admin, Password: admin123)<br>";
    }

    // Insert Default Receptionist if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'reception'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $rec_pass = password_hash('rec123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, first_name, last_name) VALUES (?, ?, 'Receptionist', 'Front', 'Desk')");
        $stmt->execute(['reception', $rec_pass]);
        echo "Default Receptionist user created. (Username: reception, Password: rec123)<br>";
    }

    echo "Database setup complete! You can now delete this file and go to <a href='index.php'>login page</a>.";

} catch (Exception $e) {
    die("DB Setup Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
