<?php
// Setelah menambahkan , jangan lupa untuk menambahkan izin browser untuk eksekusi `sudo` di :
// 
// sudo visudo
// 
// Lalu tambahkan baris berikut
// 
// Jika bash
// www ALL=(ALL) NOPASSWD: /bin/bash [namafile.sh]
//
// Lainnya
// www ALL=(ALL) NOPASSWD: ../cron/custom/cleanup-peer-wg.sh
//
session_start();

require_once '../includes/auth.php';
require_once '../includes/config.php';

requireAdminLogin();

/**
 * 🔒 HARUS LOGIN ADMIN

/**
 * ACTION WHITELIST
 */
$action = $_GET['action'] ?? '';

$allowed_actions = [
    'restart_radius',
    'clear_peer'
];

if (!in_array($action, $allowed_actions)) {
    setFlash('error', 'Invalid action specified');
    redirect('/admin/settings.php');
    exit;
}

/**
 * EXECUTION ROUTER
 */
switch ($action) {

    case 'restart_radius':
        $output = shell_exec('sudo /bin/systemctl restart freeradius 2>&1');
        setFlash('success', "Radius Server Restarted\n$output");
        break;
}

/**
 * ALWAYS REDIRECT BACK
 */
redirect('/admin/settings.php');
exit;