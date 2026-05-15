<?php
// 1. Konfigurasi Database
$host = 'localhost';
$db   = 'ans_radius';
$user = 'ans_radius';
$pass = '95b3783482dc8';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Koneksi Gagal: " . $e->getMessage());
}

// 2. Path ke File CSV
$csvFile = 'forinvoice.csv'; 

// 3. Proses File
if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $header = fgetcsv($handle, 1000, ",");
    
    // Index Mapping
    $idxKode    = array_search('Kode', $header);
    $idxExpired = array_search('ISOLIR Date', $header);
    $idxPhone   = array_search('Whatsapp', $header); // Pastikan di CSV ada kolom bernama 'phone'

    // Prepared Statement untuk cari Customer ID
    $stmtGetCust = $pdo->prepare("SELECT id FROM customers WHERE phone = ? OR phone = ? LIMIT 1");

    // Prepared Statement untuk Insert Invoice
    $sqlInsert = "INSERT INTO invoices (invoice_number, customer_id, amount, status, due_date) VALUES (?, ?, ?, ?, ?)";
    $stmtInsert = $pdo->prepare($sqlInsert);

    $successCount = 0;
    $errorCount = 0;

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (empty($data[$idxKode])) continue;

        // --- PROSES MEMBERSIHKAN NOMOR HP ---
        // Menghilangkan karakter ` dan spasi jika ada
        $rawPhone = str_replace(['`', ' '], '', $data[$idxPhone]);
        
        // Cari ID Customer di Database
        // Kita cek dua kemungkinan: nomor murni (628...) atau dengan tanda + (+628...)
        $stmtGetCust->execute([$rawPhone, "+" . $rawPhone]);
        $customer = $stmtGetCust->fetch();

        if ($customer) {
            $customer_id = $customer['id'];
            
            try {
                $stmtInsert->execute([
                    $data[$idxKode],    // invoice_number
                    $customer_id,       // ID hasil pencarian
                    100000,             // amount
                    'paid',             // status
                    $data[$idxExpired]  // due_date
                ]);
                $successCount++;
            } catch (Exception $e) {
                echo "Gagal Simpan Invoice {$data[$idxKode]}: " . $e->getMessage() . "<br>";
                $errorCount++;
            }
        } else {
            echo "Skipped: Pelanggan dengan nomor <b>$rawPhone</b> tidak ditemukan di database.<br>";
            $errorCount++;
        }
    }
    
    fclose($handle);
    echo "<br><b>Proses Selesai!</b><br>";
    echo "Berhasil: $successCount invoice.<br>";
    echo "Gagal/Lewat: $errorCount data.";
}
?>