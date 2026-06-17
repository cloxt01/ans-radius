<?php
include '../includes/config.php';

$host    = DB_HOST;
$db      = DB_NAME;
$user    = DB_USER;
$pass    = DB_PASS;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage() . "\n");
}

$apply = in_array('--apply', $argv);

echo "Update Amount Invoice Berdasarkan Package Baru...\n";

// Query ini akan mengupdate semua invoice (April, Mei, Juni)
// milik pelanggan fiktif agar harganya sesuai dengan harga paket yang terdaftar di database
$sqlUpdateAmount = "
    UPDATE invoices i
    INNER JOIN customers c ON i.customer_id = c.id
    INNER JOIN packages p ON c.package_id = p.id
    INNER JOIN fiktif_customers fc ON c.id = fc.customer_id
    SET i.amount = p.price
    WHERE i.customer_id IN (SELECT customer_id FROM fiktif_customers)
";

if ($apply) {
    $pdo->exec($sqlUpdateAmount);
    echo "✓ Amount invoice berhasil disinkronkan dengan harga paket.\n";
} else {
    echo "⚠️ Dry-run: Script ini akan mengeksekusi:\n";
    echo $sqlUpdateAmount . "\n\n";
    echo "Jalankan dengan --apply untuk eksekusi.\n";
}
?>


