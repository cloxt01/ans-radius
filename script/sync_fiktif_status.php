<?php
if (php_sapi_name() !== 'cli') die("Hanya untuk terminal.\n");

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$isApply = in_array('--apply', $argv);
$today = date('Y-m-d');

echo ($isApply ? "=== MODE APPLY: UPDATE HANYA DATA FIKTIF ===" : "=== MODE DRY RUN: HANYA SIMULASI ===") . "\n";

// Mengambil HANYA pelanggan yang ada di fiktif_customers
// Dan invoice yang tercatat di fiktif_invoices dengan status PAID
$sql = "
    SELECT 
        c.id, 
        c.name, 
        i.paid_at 
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
    
    // Hitung isolation_date: +1 bulan dari paid_at
    $newIsolationDate = date('Y-m-d', strtotime($row['paid_at'] . ' +1 month'));
    
    // Logika: Jika isolation_date >= hari ini, maka status Active
    $newStatus = ($newIsolationDate >= $today) ? 'active' : 'isolated';

    if (!$isApply) {
        echo "[SIMULASI] [#{$cId}] {$row['name']} | Paid: {$row['paid_at']} | Exp: {$newIsolationDate} | Status: {$newStatus}\n";
        continue;
    }

    try {
        $pdo->beginTransaction();

        // Update database pelanggan
        update('customers', [
            'status' => $newStatus,
            'isolation_date' => $newIsolationDate
        ], 'id = ?', [$cId]);

        // Opsional: Jika status active, pastikan di Mikrotik tidak ter-isolasi
        if ($newStatus === 'active') {
            // unisolateCustomer($cId); // Uncomment jika fungsi ini tersedia
        }

        $pdo->commit();
        echo "✓ SUKSES: [#{$cId}] -> {$newStatus} (Berlaku s/d {$newIsolationDate})\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "✗ GAGAL: [#{$cId}] {$e->getMessage()}\n";
    }
}

