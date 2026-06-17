<?php
/**
 * SCRIPT KHUSUS UPDATE RADCHECK (PASSWORD RADIUS)
 */

// --- 1. KONFIGURASI DATABASE RADIUS ---
include '../includes/config.php';

$db_config = [
    'host' => DB_HOST,
    'user' => DB_USER,
    'pass' => DB_PASS,
    'radius_db'  => RADIUS_DB_NAME
];

try {
    $db_radius = new PDO("mysql:host={$db_config['host']};dbname={$db_config['radius_db']}", $db_config['user'], $db_config['pass']);
    $db_radius->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi Gagal: " . $e->getMessage());
}

// --- 2. KONFIGURASI FILE CSV ---
$csvFile = 'convertcsv (4).csv'; 

if (!file_exists($csvFile)) {
    die("Error: File $csvFile tidak ditemukan!");
}

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $headers = fgetcsv($handle, 1000, ",");
    
    echo "--- MEMULAI UPDATE RADCHECK ---\n";

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row = array_combine($headers, $data);
        
        $username = trim($row['username']);
        // Ambil password dari CSV, jika kosong default ke '1234'
        $password = !empty($row['password']) ? trim($row['password']) : '1234';

        if (empty($username)) continue;

        try {
            // A. Hapus password lama untuk user ini (Anti Duplikat)
            $stmt_del = $db_radius->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Cleartext-Password'");
            $stmt_del->execute([$username]);

            // B. Insert password baru hasil sinkronisasi CSV
            $stmt_ins = $db_radius->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
            $stmt_ins->execute([$username, $password]);

            echo "OK: {$username} -> Password updated.\n";

        } catch (Exception $e) {
            echo "Gagal pada user {$username}: " . $e->getMessage() . "\n";
        }
    }
    fclose($handle);
    echo "\nSelesai! radcheck sudah di-update.";
}
?>