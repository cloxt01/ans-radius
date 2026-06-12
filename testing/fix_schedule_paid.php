<?php

$host = 'localhost';
$db   = 'ans_radius';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

date_default_timezone_set('Asia/Jakarta');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = new PDO($dsn, $user, $pass, $options);

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

// =========================
// HAPUS TEMP DATA LAMA
// =========================
$pdo->exec("DELETE FROM fiktif_invoices");

// =========================
// AMBIL DATA INVOICE FIKTIF
// =========================
$sql = "
    SELECT i.id AS invoice_id, i.due_date, i.paid_at, i.status
    FROM invoices i
    INNER JOIN fiktif_customers fc ON fc.customer_id = i.customer_id
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
        $scheduled = date('Y-m-d', strtotime($inv['paid_at']));
    } else {
        $lateDays = generateLateDays();
        $scheduled = date('Y-m-d', strtotime($inv['due_date'] . " +$lateDays days"));
    }

    $insert->execute([
        $inv['invoice_id'],
        $lateDays,
        $scheduled,
        $inv['status']
    ]);
}

$pdo->commit();

echo "Selesai generate fiktif invoices.\n";