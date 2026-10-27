<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this upgrade from the command line.');
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/schema.php';
try {
    upgradeHospitalSchema($pdo);
    echo "Database upgrade complete.\n";
} catch (Exception $e) {
    fwrite(STDERR, "Upgrade failed: " . $e->getMessage() . "\n");
    exit(1);
}
