<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Run this repair from the command line.'); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/schema.php';
require_once __DIR__ . '/../includes/doctor_integrity.php';
try {
    foreach (repairDoctorAccounts($pdo) as $id => $username) echo "Doctor $id: linked to inactive account $username\n";
    upgradeHospitalSchema($pdo);
    echo "Doctor repair and schema upgrade complete.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Repair failed: ' . $e->getMessage() . "\n");
    exit(1);
}
