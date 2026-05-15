<?php
// Endpoint JSON untuk aksi service dari halaman settings.

session_start();

require_once '../includes/auth.php';
require_once '../includes/config.php';

requireAdminLogin();

header('Content-Type: application/json; charset=utf-8');

function serviceJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

$action = $_GET['action'] ?? '';

$allowedActions = [
    'restart_radius',
    'clear_peer'
];

if (!in_array($action, $allowedActions, true)) {
    serviceJsonResponse([
        'success' => false,
        'message' => 'Invalid action specified'
    ], 400);
}

switch ($action) {
    case 'restart_radius':
        $output = shell_exec('sudo /bin/systemctl restart freeradius 2>&1');
        serviceJsonResponse([
            'success' => true,
            'message' => 'Radius Server restarted',
            'output' => trim((string) $output)
        ]);

    case 'clear_peer':
        serviceJsonResponse([
            'success' => false,
            'message' => 'Action clear_peer belum diimplementasikan'
        ], 501);
}

serviceJsonResponse([
    'success' => false,
    'message' => 'Unknown action'
], 400);