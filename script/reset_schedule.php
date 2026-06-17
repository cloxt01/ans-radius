<?php

include '../includes/config.php';
$host = DB_HOST;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = new PDO($dsn, $user, $pass, $options);

if(!$pdo) {"Koneksi ke database gagal";}

if(isset($argv[1]) && isset($argv[2]) && $argv[1] === '-t') {
    $taskType = $argv[2];
    $success = $pdo->exec("
        UPDATE cron_schedules
        SET last_run = NULL,
        next_run = NULL WHERE task_type = '$taskType'");

    if($success) {
        echo "Schedule `$taskType` dihapus.\n\n";
    }
} else {
    echo "Usage: php reset_schedule.php -t <task_type>\n";
    exit(1);
}

$success = $pdo->exec("
    UPDATE cron_schedules
    SET last_run = NULL,
    next_run = NULL WHERE task_type = 'fiktif_customers'");

if($success) {
    echo "Schedule `$taskType` direset.\n\n";
}
