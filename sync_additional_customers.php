<?php
/**
 * Script Sinkronisasi Langsung Antar 2 Database (Tanpa CSV)
 * DB 1: ans_radius (Utama) <--> DB 2: radius_db (RADIUS)
 */

// --- 1. Konfigurasi Database Utama ---
$db_main_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '', 
    'dbname' => 'ans_radius'
];

// --- 2. Konfigurasi Database FreeRADIUS ---
$db_radius_config = [
    'host' => 'localhost',
    'user' => 'root', 
    'pass' => '', 
    'dbname' => 'radius_db'
];

try {
    // Koneksi ke DB Utama (ans_radius)
    $db_ans = new PDO("mysql:host={$db_main_config['host']};dbname={$db_main_config['dbname']}", $db_main_config['user'], $db_main_config['pass']);
    $db_ans->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Koneksi ke DB RADIUS (radius_db)
    $db_radius = new PDO("mysql:host={$db_radius_config['host']};dbname={$db_radius_config['dbname']}", $db_radius_config['user'], $db_radius_config['pass']);
    $db_radius->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Koneksi Gagal: " . $e->getMessage() . "\n");
}

try {
    // --- Langkah 1: Ambil semua username dari database utama ---
    $sql_get_users = "SELECT pppoe_username FROM customers WHERE pppoe_username IS NOT NULL AND pppoe_username != ''";
    $users = $db_ans->query($sql_get_users)->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        die("Info: Tidak ada data user di tabel customers.\n");
    }

    // --- Langkah 2: Prepared Statement untuk DB RADIUS (Ambil sesi terbaru) ---
    $sql_radacct = "SELECT 
                        calledstationid AS pppoe_server,
                        callingstationid AS mac_address,
                        framedipaddress AS ip_address
                    FROM radacct
                    WHERE username = :username
                    ORDER BY acctstarttime DESC
                    LIMIT 1";
    $stmt_radacct = $db_radius->prepare($sql_radacct);

    // --- Langkah 3: Prepared Statement untuk DB Utama (Update data) ---
    $sql_update_net = "UPDATE customers 
                       SET 
                           pppoe_server = :pppoe_server,
                           mac_address  = :mac_address,
                           ip_address   = :ip_address
                       WHERE pppoe_username = :pppoe_username";
    $stmt_update_net = $db_ans->prepare($sql_update_net);

    echo "=============================================================\n";
    echo " Memulai Sinkronisasi Sesi Langsung Antar 2 Database...\n";
    echo "=============================================================\n\n";

    $total_updated = 0;

    foreach ($users as $user) {
        $pppoe_user = trim($user['pppoe_username']);

        // Cari sesi terakhir di DB radius_db
        $stmt_radacct->execute([':username' => $pppoe_user]);
        $session_terbaru = $stmt_radacct->fetch(PDO::FETCH_ASSOC);

        if ($session_terbaru) {
            // Update data jaringan ke DB ans_radius
            $stmt_update_net->execute([
                ':pppoe_server'   => $session_terbaru['pppoe_server'],
                ':mac_address'    => $session_terbaru['mac_address'],
                ':ip_address'     => $session_terbaru['ip_address'],
                ':pppoe_username' => $pppoe_user
            ]);

            echo "[SINKRON] User: {$pppoe_user} -> IP: {$session_terbaru['ip_address']} | MAC: {$session_terbaru['mac_address']}\n";
            $total_updated++;
        } else {
            echo "[LEWAT]   User: {$pppoe_user} -> Tidak ada log di radacct\n";
        }
    }

    echo "\n=============================================================\n";
    echo " SINKRONISASI SELESAI!\n";
    echo " Total pelanggan yang berhasil diperbarui: {$total_updated}\n";
    echo "=============================================================\n";

} catch (Exception $e) {
    echo "[CRITICAL ERROR] Proses terhenti: " . $e->getMessage() . "\n";
}
?>