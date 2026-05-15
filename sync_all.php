<?php
// --- Konfigurasi Koneksi ---
$db_config = [
    'host' => 'localhost',
    'user' => 'ans_radius',
    'pass' => '95b3783482dc8', 
    'ans_radius' => 'ans_radius',
    'radius_db'  => 'radius_db'
];

try {
    // Koneksi ke Database Aplikasi (ans_radius)
    $db_ans = new PDO("mysql:host={$db_config['host']};dbname={$db_config['ans_radius']}", $db_config['user'], $db_config['pass']);
    
    // Koneksi ke Database RADIUS (radius_db)
    $db_radius = new PDO("mysql:host={$db_config['host']};dbname={$db_config['radius_db']}", $db_config['user'], $db_config['pass']);
    
    $db_ans->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_radius->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Koneksi Gagal: " . $e->getMessage());
}

// --- Path File CSV ---
$csvFile = 'convertcsv (4).csv';

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $headers = fgetcsv($handle, 1000, ",");
    
    // 1. Prepare Query untuk tabel 'customers' di ans_radius
    $sql_cust = "INSERT INTO customers (name, phone, pppoe_username, isolation_date, address, status, package_id) 
                 VALUES (:name, :phone, :username, :isolation_date, :address, :status, :package_id)
                 ON DUPLICATE KEY UPDATE 
                    name = VALUES(name),
                    phone = VALUES(phone),
                    status = VALUES(status), 
                    isolation_date = VALUES(isolation_date),
                    address = VALUES(address)";
    $stmt_cust = $db_ans->prepare($sql_cust);

    // 2. Prepare Query untuk tabel 'radusergroup' di radius_db
    $sql_rad_group = "INSERT INTO radusergroup (username, groupname, priority) 
                      VALUES (:username, :groupname, :priority)
                      ON DUPLICATE KEY UPDATE groupname = VALUES(groupname)";
    $stmt_rad_group = $db_radius->prepare($sql_rad_group);

    // 3. Prepare Query untuk tabel 'radcheck' (Password)
    $sql_rad_check = "INSERT INTO radcheck (username, attribute, op, value) 
                      VALUES (:username, 'Cleartext-Password', ':=', :password)
                      ON DUPLICATE KEY UPDATE value = VALUES(value)";
    $stmt_rad_check = $db_radius->prepare($sql_rad_check);

    echo "Memulai Sinkronisasi Database Aplikasi & RADIUS...\n\n";

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row = array_combine($headers, $data);
        $username = trim($row['username']);

        // --- LOGIKA UTAMA ---
        // Fokus hanya pada kolom voucher_status
        $v_status = strtoupper(trim($row['voucher_status']));

        if ($v_status === 'EXPIRED') {
            $status_cust = 'isolated';
            $groupname   = 'ISOLIR';
        } else {
            $status_cust = 'active';
            $groupname   = 'PAKET STAR LEGEND'; // Ganti dengan nama group default lu
        }
        
        $day_only = !empty($row['expired_at']) ? date('d', strtotime($row['expired_at'])) : null;

        try {
            // 1. Update tabel Customers (ans_radius) - Tetap pakai ON DUPLICATE KEY
            $stmt_cust->execute([
                ':name'           => substr($row['name'], 0, 50),
                ':phone'          => $row['whatsapp'],
                ':username'       => $username,
                ':isolation_date' => $day_only,
                ':address'        => substr($row['address'], 0, 100),
                ':status'         => $status_cust,
                ':package_id'     => 1
            ]);


            $db_radius->prepare("DELETE FROM radusergroup WHERE username = ?")->execute([$username]);
            $stmt_rad_group->execute([
                ':username'  => $username,
                ':groupname' => $groupname,
                ':priority'  => 1
            ]);


            $db_radius->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Cleartext-Password'")->execute([$username]);
            $stmt_rad_check->execute([
                ':username' => $username,
                ':password' => '1234'
            ]);

            echo "OK: {$username} -> Status: {$status_cust} | Group: {$groupname}\n";

        } catch (Exception $e) {
            echo "Error pada user {$username}: " . $e->getMessage() . "\n";
        }
    }
    fclose($handle);
}

echo "\nSelesai! Semua data (Customers & RADIUS) telah sinkron.";
?>