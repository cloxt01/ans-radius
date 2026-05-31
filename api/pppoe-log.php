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

$rawLogs = mikrotikGetHotspotLog(max(20, $limit * 3));

// Filter messages that contain 'pppoe' (case-insensitive)
$logs = [];
foreach ($rawLogs as $l) {
    $msg = $l['message'] ?? '';
    if ($msg !== '' && stripos($msg, 'pppoe') !== false) {
        $logs[] = $l;
    }
}

// Ensure we only return requested limit
$logs = array_slice($logs, 0, $limit);

// Parse log messages for display
$result = [];
foreach ($logs as $log) {
    $message = $log['message'] ?? '';
    $time = $log['time'] ?? '';

    // Parse Mikhmon v3 style log: "-> user (IP): message"
    $user = '';
    $info = $message;

    if (strpos($message, '->') === 0) {
        // Format: "-> user (IP): action details"
        $parts = explode(':', $message, 3);
        if (count($parts) >= 2) {
            $user = trim($parts[0], '-> ');
            if (isset($parts[1])) {
                // Reconstruct user with possible IPv6
                if (count($parts) > 3) {
                    $user = $parts[0] . ':' . $parts[1] . ':' . $parts[2] . ':' . $parts[3] . ':' . $parts[4] . ':' . $parts[5];
                    $info = $parts[count($parts) - 1] ?? '';
                } else {
                    $user = trim($parts[0], '-> ');
                    $info = trim(implode(':', array_slice($parts, 1)));
                }
            }
        }
    } else {
        // Standard log format
        $colonPos = strpos($message, ':');
        if ($colonPos !== false) {
            $user = substr($message, 0, $colonPos);
            $info = substr($message, $colonPos + 1);
        }
    }

    $result[] = [
        'time' => $time,
        'user' => trim($user),
        'message' => trim($info),
    ];
}

echo json_encode($result);
