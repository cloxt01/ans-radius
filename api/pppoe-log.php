<?php
/**
 * PPPoE Log API Endpoint
 * Returns last N MikroTik log entries where the message contains "pppoe"
 */
require_once '../includes/auth.php';
requireAdminLogin();

// Release session lock early so long-running MikroTik calls don't block other requests
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json');
$limit = (int) ($_GET['limit'] ?? 20);
if ($limit < 1 || $limit > 100) {
    $limit = 20;
}

$logs = mikrotikGetPppoeLog($limit);

// Return only time and message fields
$result = [];
foreach ($logs as $log) {
    $message = $log['message'] ?? '';
    $time = $log['time'] ?? '';

    $result[] = [
        'time' => $time,
        'message' => trim($message),
    ];
}

echo json_encode($result);
