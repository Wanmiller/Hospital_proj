<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run demo-data import from the command line.');
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/demo_data.php';
try {
    $added = seedHospitalDemoData($pdo);
    echo "Demo data imported. Existing records preserved.\n";
    foreach ($added as $table => $count) echo "$table: +$count\n";
    echo 'Total added: ' . array_sum($added) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . "\n");
    exit(1);
}
