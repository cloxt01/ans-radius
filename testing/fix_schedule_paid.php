<?php

include '../include/config.php';
$host = DB_HOST;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = 'utf8mb4';

date_default_timezone_set('Asia/Jakarta');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = new PDO($dsn, $user, $pass, $options);

if($pdo === false){
    die("DB connection failed\n");
}
/**
 * =========================
 * CONFIG DISTRIBUSI LATE DAYS
 * =========================
 */
function generateLateDays(): int
{
    $roll = mt_rand(1, 100);

    // 60% cepat bayar
    if ($roll <= 60) {
        return mt_rand(0, 1);
    }

    // 25% telat ringan
    if ($roll <= 85) {
        return mt_rand(2, 5);
    }

    // 10% telat sedang
    if ($roll <= 95) {
        return mt_rand(6, 10);
    }

    // 5% telat berat
    return mt_rand(11, 20);
}

// Tambahan: Fungsi untuk waktu acak
function getRandomTime(): string {
    return sprintf("%02d:%02d:%02d", mt_rand(8, 19), mt_rand(0, 59), mt_rand(0, 59));
}

// =========================
// HAPUS TEMP DATA LAMA (KHUSUS JUNI)
// =========================
$pdo->exec("
    DELETE fi FROM fiktif_invoices fi
    INNER JOIN invoices i ON fi.invoice_id = i.id
    WHERE DATE_FORMAT(i.due_date, '%Y-%m') = '2026-06'
");

// =========================
// AMBIL DATA INVOICE FIKTIF (KHUSUS JUNI)
// =========================
$sql = "
    SELECT i.id AS invoice_id, i.due_date, i.paid_at, i.status
    FROM invoices i
    INNER JOIN fiktif_customers fc ON fc.customer_id = i.customer_id
    WHERE DATE_FORMAT(i.due_date, '%Y-%m') = '2026-06'
";

$invoices = $pdo->query($sql)->fetchAll();

$insert = $pdo->prepare("
    INSERT INTO fiktif_invoices
    (invoice_id, late_days, scheduled_paid_date, status)
    VALUES (?, ?, ?, ?)
");

$pdo->beginTransaction();

foreach ($invoices as $inv) {

    if ($inv['status'] === 'paid') {
        $lateDays = (int) max(
            0,
            (strtotime($inv['paid_at']) - strtotime($inv['due_date'])) / 86400
        );
        // Menggunakan format Y-m-d H:i:s
        $scheduled = date('Y-m-d H:i:s', strtotime($inv['paid_at']));
    } else {
        $lateDays = generateLateDays();
        // Menggunakan format Y-m-d + jam acak
        $scheduled = date('Y-m-d', strtotime($inv['due_date'] . " +$lateDays days")) . ' ' . getRandomTime();
    }

    $insert->execute([
        $inv['invoice_id'],
        $lateDays,
        $scheduled,
        $inv['status']
    ]);
}

$pdo->commit();

echo "Selesai generate fiktif invoices untuk bulan Juni.\n";
?>