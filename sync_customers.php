<?php
// --- Konfigurasi Koneksi ---
$db_config = [
    'host' => 'localhost',
    'user' => 'ans_radius',
    'pass' => '95b3783482dc8', 
    'ans_radius' => 'ans_radius'
];

try {
    $db_ans = new PDO("mysql:host={$db_config['host']};dbname={$db_config['ans_radius']}", $db_config['user'], $db_config['pass']);
    $db_ans->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi Gagal: " . $e->getMessage());
}

// --- Path File CSV ---
$csvFile = 'convertcsv (4).csv'; // Pastikan nama file sesuai

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $headers = fgetcsv($handle, 1000, ",");
    
    $sql_cust = "INSERT INTO customers (name, phone, pppoe_username, isolation_date, address, status, package_id) 
                 VALUES (:name, :phone, :username, :isolation_date, :address, :status, :package_id)
                 ON DUPLICATE KEY UPDATE 
                    name = VALUES(name),
                    phone = VALUES(phone),
                    status = VALUES(status), 
                    isolation_date = VALUES(isolation_date),
                    address = VALUES(address)";
    
    $stmt_cust = $db_ans->prepare($sql_cust);

    echo "Proses Update ke Status ACTIVE...\n\n";

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row = array_combine($headers, $data);

        // --- LOGIKA SESUAI PERMINTAAN ---
        // Bersihkan spasi dan paksa jadi huruf besar semua untuk perbandingan
        $v_status = strtoupper(trim($row['voucher_status']));

        if ($v_status === 'EXPIRED') {
            $status_cust = 'isolated';
        } else {
            $status_cust = 'active';
        }
        
        // Ambil tanggal hari dari expired_at
        $day_only = !empty($row['expired_at']) ? date('d', strtotime($row['expired_at'])) : null;

        try {
            $stmt_cust->execute([
                ':name'           => substr($row['name'], 0, 50),
                ':phone'          => $row['whatsapp'],
                ':username'       => $row['username'],
                ':isolation_date' => $day_only,
                ':address'        => substr($row['address'], 0, 100),
                ':status'         => $status_cust, 
                ':package_id'     => 1
            ]);

            echo "User: {$row['username']} | Voucher: {$v_status} -> Status DB: {$status_cust}\n";

        } catch (Exception $e) {
            echo "Error pada user {$row['username']}: " . $e->getMessage() . "\n";
        }
    }
    fclose($handle);
}

echo "\nSelesai! Semua data di CSV telah di-set menjadi ACTIVE di database.";
?>