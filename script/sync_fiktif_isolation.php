<?php
/**
 * sync_fiktif_isolation.php
 *
 * Sinkronisasi customers.isolation_date & customers.status
 * berdasarkan invoice TERBARU tiap fiktif customer.
 *
 * Aturan:
 *   UNPAID -> isolation_date = due_date (invoice terakhir)
 *             status         = isolated
 *   PAID   -> isolation_date = paid_at + 1 bulan
 *             status         = active
 *
 * Usage:
 *   php sync_fiktif_isolation.php            (dry run)
 *   php sync_fiktif_isolation.php --apply    (eksekusi nyata)
 */

if (php_sapi_name() !== 'cli') {
    die("Script ini hanya boleh dijalankan melalui terminal.\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$isApply = in_array('--apply', $argv);

echo $isApply
    ? "=== MODE APPLY: PERUBAHAN AKAN DISIMPAN KE DATABASE ===\n"
    : "=== MODE DRY RUN: HANYA SIMULASI (TIDAK ADA DATA YANG DIUBAH) ===\n";
echo str_repeat("-", 80) . "\n";

// Ambil invoice TERBARU per fiktif customer (unpaid atau paid saja; cancelled diabaikan)
$sql = "
    SELECT
        c.id                AS customer_id,
        c.name,
        c.status            AS current_status,
        c.isolation_date    AS current_isolation_date,
        i.id                AS invoice_id,
        i.due_date,
        i.paid_at,
        i.status            AS invoice_status
    FROM customers c
    INNER JOIN fiktif_customers fc ON fc.customer_id = c.id
    INNER JOIN invoices i ON i.customer_id = c.id
    WHERE i.status IN ('unpaid', 'paid')
      AND i.id = (
          SELECT i2.id
          FROM invoices i2
          WHERE i2.customer_id = c.id
            AND i2.status != 'cancelled'
          ORDER BY i2.due_date DESC, i2.id DESC
          LIMIT 1
      )
";

$rows = fetchAll($sql);

if (empty($rows)) {
    die("Tidak ada data fiktif customer yang ditemukan.\n");
}

echo "Ditemukan " . count($rows) . " fiktif customer (dengan invoice aktif terbaru).\n\n";

$pdo       = getDB();
$checked   = 0;
$toSync    = 0;
$success   = 0;
$failed    = 0;
$skipped   = 0;

foreach ($rows as $row) {
    $checked++;

    $cId          = (int) $row['customer_id'];
    $cName        = $row['name'];
    $currentStat  = $row['current_status'];
    $currentIso   = $row['current_isolation_date'];

    if ($row['invoice_status'] === 'unpaid') {
        $targetStatus = 'isolated';
        $targetIso    = $row['due_date'];
        $reason       = "unpaid, due_date: {$row['due_date']}";
    } else { // paid
        $targetStatus = 'active';
        $targetIso    = date('Y-m-d', strtotime($row['paid_at'] . ' +1 month'));
        $reason       = "paid, paid_at: {$row['paid_at']} -> +1 bulan";
    }

    // Skip kalau sudah sinkron (status & isolation_date sama-sama sudah benar)
    if ($currentStat === $targetStatus && $currentIso === $targetIso) {
        $skipped++;
        continue;
    }

    $toSync++;

    if (!$isApply) {
        echo "[SIMULASI] [#{$cId}] {$cName} | {$reason}\n";
        echo "           status: {$currentStat} -> {$targetStatus} | isolation_date: "
            . ($currentIso ?? 'NULL') . " -> {$targetIso}\n";
        continue;
    }

    try {
        $pdo->beginTransaction();

        $ok = update('customers', [
            'status'         => $targetStatus,
            'isolation_date' => $targetIso,
        ], 'id = ?', [$cId]);

        if (!$ok) {
            throw new Exception("Gagal update customers -> {$cId}");
        }

        $pdo->commit();

        echo "✓ SUKSES: [#{$cId}] {$cName} | status: {$targetStatus} | isolation_date: {$targetIso}\n";
        $success++;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "✗ GAGAL : [#{$cId}] {$cName} | Error: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo str_repeat("-", 80) . "\n";
echo "Total dicek       : {$checked}\n";
echo "Sudah sinkron     : {$skipped}\n";
echo "Perlu disinkron   : {$toSync}\n";

if ($isApply) {
    echo "Berhasil          : {$success}\n";
    echo "Gagal             : {$failed}\n";
} else {
    echo "\nJalankan dengan --apply untuk menyimpan perubahan.\n";
}
