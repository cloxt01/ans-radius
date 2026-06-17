<?php
// 1. Pengaturan Database
include '../includes/config.php';
// ====== KONFIGURASI DB ======
$host = DB_HOST;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
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

// 2. Baca File CSV
$csvFile = 'convertcsv (3).csv';
$usernames = [];

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $headers = fgetcsv($handle, 1000, ",");
    $userIdx = array_search('username', $headers);

    if ($userIdx === false) {
        die("Error: Kolom 'username' tidak ditemukan di file CSV.");
    }

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $user = trim($data[$userIdx]);
        if (!empty($user)) {
            $usernames[] = $user;
        }
    }
    fclose($handle);
}

$usernames = array_unique($usernames);

// 3. Update Database dengan JOIN
if (!empty($usernames)) {
    $placeholders = implode(',', array_fill(0, count($usernames), '?'));
    
    /* Penjelasan Query:
       - Kita update tabel 'invoices' (i)
       - Kita JOIN dengan tabel 'customers' (c) berdasarkan 'customer_id'
       - Filter berdasarkan 'pppoe_username' yang ada di daftar CSV
    */
    $sql = "UPDATE invoices i
            JOIN customers c ON i.customer_id = c.id
            SET i.status = 'unpaid'
            WHERE c.pppoe_username IN ($placeholders)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($usernames));

    echo "### Update Berhasil!\n";
    echo "Total Invoice yang di-unpaid: " . $stmt->rowCount() . " baris.\n";
} else {
    echo "Tidak ada data username di CSV.";
}
?>