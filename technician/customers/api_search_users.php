<?php
/**
 * API Endpoint untuk mencari PPPoE user dari MikroTik
 * Active session tidak di-load ulang, cukup gunakan data yang dikirim dari frontend
 */

require_once '../../includes/auth.php';
requireTechnicianLogin();

header('Content-Type: application/json');

// Ambil query parameter
$query = $_GET['q'] ?? '';
$filter = $_GET['filter'] ?? 'all';

// Online usernames dikirim dari frontend (sudah di-load di halaman utama)
$onlineUsernamesRaw = $_GET['online_usernames'] ?? '[]';
$onlineUsernames = json_decode($onlineUsernamesRaw, true);

if (!is_array($onlineUsernames)) {
    $onlineUsernames = [];
}

if (strlen($query) < 2) {
    echo json_encode([
        'success' => true,
        'users' => [],
        'total' => 0,
        'message' => 'Ketik minimal 2 karakter untuk mencari'
    ]);
    exit;
}

// Ambil semua user dari MikroTik (hanya user, tanpa active sessions)
$allUsers = mikrotikGetPppoeUsers();

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