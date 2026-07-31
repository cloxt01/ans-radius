<?php
if (php_sapi_name() !== 'cli') die("Hanya untuk terminal.\n");

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$isApply = in_array('--apply', $argv);
$today = date('Y-m-d');

echo ($isApply ? "=== MODE APPLY: UPDATE STATUS KETAT ===" : "=== MODE DRY RUN: HANYA SIMULASI ===") . "\n";

// Mengambil pelanggan fiktif dengan invoice yang sudah PAID
$sql = "
    SELECT 
        c.id, 
        c.name, 
        i.paid_at,
        c.isolation_date
    FROM customers c
    JOIN fiktif_customers fc ON c.id = fc.customer_id
    JOIN invoices i ON c.id = i.customer_id
    JOIN fiktif_invoices fi ON i.id = fi.invoice_id
    WHERE fi.status = 'paid' 
      AND i.paid_at IS NOT NULL
";

$customers = fetchAll($sql);
$pdo = getDB();

foreach ($customers as $row) {
    $cId = (int)$row['id'];
    
    // 1. Hitung date dari paid_at
    $newIsolationDate = date('Y-m-d', strtotime($row['paid_at'] . ' +1 month'));
    
    // 2. Logika Status Ketat:
    // Jika isolation_date <= hari ini, maka 'isolated'
    // Jika isolation_date > hari ini, maka 'active'
    if ($newIsolationDate <= $today) {
        $newStatus = 'isolated';
    } else {
        $newStatus = 'active';
    }

    if (!$isApply) {
        echo "[SIMULASI] [#{$cId}] {$row['name']} | Isolasi: {$newIsolationDate} | Hari ini: {$today} | Status: {$newStatus}\n";
        continue;
    }

    try {
        $pdo->beginTransaction();

        // Update database
        update('customers', [
            'status' => $newStatus,
            'isolation_date' => $newIsolationDate
        ], 'id = ?', [$cId]);

        // Sinkronisasi ke Mikrotik
        if ($newStatus === 'isolated') {
            // isolateCustomer($cId); // Uncomment sesuai fungsi di system Anda
            echo "✗ [{$cId}] Terisolasi (Tanggal berlaku telah habis)\n";
        } else {
            // unisolateCustomer($cId); // Uncomment sesuai fungsi di system Anda
            echo "✓ [{$cId}] Aktif (Berlaku s/d {$newIsolationDate})\n";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "✗ GAGAL: [#{$cId}] {$e->getMessage()}\n";
    }
}
