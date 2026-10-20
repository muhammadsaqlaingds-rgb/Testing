<?php
declare(strict_types=1);

if (!extension_loaded('mongodb')) {
    http_response_code(500);
    exit('PHP "mongodb" extension is not enabled. See README.md (Step 1).');
}
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Time.php';
require __DIR__ . '/../src/AttendanceRepository.php';

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['timezone']);
Database::init($config);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(16));
