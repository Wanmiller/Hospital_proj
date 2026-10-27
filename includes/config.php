<?php
define('DB_HOST', getenv('HOSPITAL_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('HOSPITAL_DB_USER') ?: 'root');
define('DB_PASS', getenv('HOSPITAL_DB_PASS') !== false ? getenv('HOSPITAL_DB_PASS') : '');
define('DB_NAME', getenv('HOSPITAL_DB_NAME') ?: 'hospital_db');
?>
