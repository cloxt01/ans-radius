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
$csvFile = 'convertcsv.csv';

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $headers = fgetcsv($handle, 1000, ",");
    
    // 1. Prepare Query untuk tabel 'customers' di ans_radius
    $sql_cust = "INSERT INTO customers (name, phone, pppoe_username, isolation_date, address, status, package_id) 
                 VALUES (:name, :phone, :username, :isolation_date, :address, :status, :package_id)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), isolation_date = VALUES(isolation_date)";
    $stmt_cust = $db_ans->prepare($sql_cust);

    // 2. Prepare Query untuk tabel 'radusergroup' di radius_db
    $sql_rad_group = "INSERT INTO radusergroup (username, groupname, priority) 
                      VALUES (:username, :groupname, :priority)
                      ON DUPLICATE KEY UPDATE groupname = VALUES(groupname)";
    $stmt_rad_group = $db_radius->prepare($sql_rad_group);

    // 3. Prepare Query untuk tabel 'radcheck' di radius_db (Password)
    $sql_rad_check = "INSERT INTO radcheck (username, attribute, op, value) 
                      VALUES (:username, 'Cleartext-Password', ':=', :password)
                      ON DUPLICATE KEY UPDATE value = VALUES(value)";
    $stmt_rad_check = $db_radius->prepare($sql_rad_check);

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row = array_combine($headers, $data);

        // Penentuan Logika Group & Status
        $is_expired = (strtoupper($row['voucher_status']) == 'EXPIRED' || ($row['isolir'] ?? '0') != '0');
        $groupname = $is_expired ? 'ISOLIR' : 'PAKET STAR LEGEND';
        
        // Sesuaikan 'isolated'/'active' dengan panjang kolom status di DB agar tidak truncated
        $status_cust = $is_expired ? 'isolated' : 'active';
        
        // Ambil tanggal isolir (hanya angka harinya saja)
        $day_only = !empty($row['expired_at']) ? date('d', strtotime($row['expired_at'])) : null;

        try {
            // Eksekusi ke ans_radius.customers
            $stmt_cust->execute([
                ':name'           => substr($row['name'], 0, 50),
                ':phone'          => $row['whatsapp'],
                ':username'       => $row['username'],
                ':isolation_date' => $day_only,
                ':address'        => substr($row['address'], 0, 100),
                ':status'         => $status_cust,
                ':package_id'     => 1
            ]);

            // Eksekusi ke radius_db.radusergroup
            $stmt_rad_group->execute([
                ':username'  => $row['username'],
                ':groupname' => $groupname,
                ':priority'  => 1
            ]);

            // Eksekusi ke radius_db.radcheck (Set Password 1234)
            $stmt_rad_check->execute([
                ':username' => $row['username'],
                ':password' => '1234'
            ]);

            echo "Processed: {$row['username']} -> Group: {$groupname} | Pass: 1234\n";

        } catch (Exception $e) {
            echo "Error pada user {$row['username']}: " . $e->getMessage() . "\n";
        }
    }
    fclose($handle);
}
echo "\nSelesai! Data (Customers, Group, & Password) telah disinkronkan.";
?>