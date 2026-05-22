<?php
/**
 * API Endpoint untuk mencari PPPoE user dari MikroTik
 * Dipanggil via AJAX saat teknisi mengetik di search box
 */

require_once '../../includes/auth.php';
requireTechnicianLogin();

header('Content-Type: application/json');

// Ambil query parameter
$query = $_GET['q'] ?? '';
$filter = $_GET['filter'] ?? 'all'; // all, online, offline, disabled

if (strlen($query) < 2) {
    echo json_encode([
        'success' => true,
        'users' => [],
        'total' => 0,
        'message' => 'Ketik minimal 2 karakter untuk mencari'
    ]);
    exit;
}

// Ambil semua user dari MikroTik
$allUsers = mikrotikGetPppoeUsers();

// Get active PPPoE sessions untuk mengetahui status online
$activeSessions = mikrotikGetActiveSessionsAllRouter();
$onlineUsernames = array_column($activeSessions, 'name');

// Filter berdasarkan username (LIKE %query%)
$matchedUsers = [];
foreach ($allUsers as $user) {
    $username = $user['name'] ?? '';
    $isOnline = in_array($username, $onlineUsernames);
    $isDisabled = ($user['disabled'] ?? 'false') === 'true';
    
    // Status untuk filter
    $status = $isDisabled ? 'disabled' : ($isOnline ? 'online' : 'offline');
    
    // Cek apakah username match dengan query (LIKE)
    if (stripos($username, $query) !== false) {
        // Filter by status jika diperlukan
        if ($filter === 'all' || $status === $filter) {
            $matchedUsers[] = [
                'name' => $username,
                'password' => $user['password'] ?? '',
                'profile' => $user['profile'] ?? 'default',
                'disabled' => $isDisabled,
                'online' => $isOnline,
                'status' => $status,
                'last_login' => $user['last-login'] ?? null,
                'comment' => $user['comment'] ?? ''
            ];
        }
    }
}

// Urutkan berdasarkan username
usort($matchedUsers, fn($a, $b) => strcmp($a['name'], $b['name']));

// Batasi maksimal 50 hasil
$matchedUsers = array_slice($matchedUsers, 0, 50);

echo json_encode([
    'success' => true,
    'users' => $matchedUsers,
    'total' => count($matchedUsers),
    'query' => $query
]);