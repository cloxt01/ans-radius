<?php
// Pastikan script hanya dijalankan melalui CLI (Terminal)
if (php_sapi_name() !== 'cli') {
    die("Script ini hanya boleh dijalankan melalui terminal.\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$isApply = in_array('--apply', $argv);

if ($isApply) {
    echo "=== MODE APPLY: PERUBAHAN AKAN DISIMPAN KE DATABASE & MIKROTIK ===\n";
} else {
    echo "=== MODE DRY RUN: HANYA SIMULASI (TIDAK ADA DATA YANG DIUBAH) ===\n";
}
echo str_repeat("-", 70) . "\n";

// Query mengambil 1 invoice fiktif terbaru dari bulan ini ke depa
$sql = "
    SELECT 
        c.id, 
        c.name,
        MAX(fi.scheduled_paid_date) AS scheduled_paid_date
    FROM customers c
    JOIN invoices i ON c.id = i.customer_id
    JOIN fiktif_invoices fi ON i.id = fi.invoice_id
    WHERE c.status = 'isolated' 
      AND fi.status = 'paid'
    GROUP BY c.id, c.name
";
$customers = fetchAll($sql);

if (empty($customers)) {
    die("Tidak ada data pelanggan yang perlu diperbaiki.\n");
}

echo "Ditemukan " . count($customers) . " pelanggan.\n\n";

$pdo = getDB();
$success = 0;
$failed = 0;
$simulated = 0;

// Ambil tanggal hari ini untuk perbandingan (tanpa jam/menit)
$today = date('Y-m-d');

foreach ($customers as $row) {
    $cId = (int) $row['id'];
    $cName = $row['name'];
    
    // Ekstrak bagian YYYY-MM-DD dari tipe DATETIME
    $schedDate = date('Y-m-d', strtotime($row['scheduled_paid_date']));

    // Logika penentuan status
    if ($schedDate > $today) {
        $newStatus = 'active';
    } else {
        $newStatus = 'isolated';
    }

    if (!$isApply) {
        // DRY RUN
        echo "[SIMULASI] [#{$cId}] {$cName} | Sched: {$schedDate} | Status Baru: {$newStatus}\n";
        $simulated++;
        continue;
    }

    // APPLY MODE
    try {
        $pdo->beginTransaction();

        // 1. Update status dan isolation_date
        $updated = update('customers', [
            'status'         => $newStatus,
            'isolation_date' => $schedDate
        ], 'id = ?', [$cId]);

        if (!$updated) {
            throw new Exception("Gagal mengupdate database.");
        }

        // 2. Sinkronisasi Mikrotik berdasarkan status baru
        if ($newStatus === 'active') {
            if (function_exists('unisolateFiktifCustomer')) {
                $sync = unisolateFiktifCustomer($cId);
            } else {
                $sync = unisolateCustomer($cId);
            }
        } else {
            // Pastikan fungsi isolir ini ada di functions.php Anda
            $sync = isolateCustomer($cId);
        }

        if (!$sync) {
            throw new Exception("Gagal sinkronisasi ke API Mikrotik.");
        }

        $pdo->commit();
        echo "✓ SUKSES: [#{$cId}] {$cName} | Status: {$newStatus} | Isolasi: {$schedDate}\n";
        $success++;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "✗ GAGAL: [#{$cId}] {$cName} | Error: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo str_repeat("-", 70) . "\n";
if ($isApply) {
    echo "Eksekusi Nyata Selesai!\nBerhasil : {$success}\nGagal    : {$failed}\n";
} else {
    echo "Simulasi Selesai!\nTotal disimulasikan : {$simulated}\nJalankan dengan --apply untuk menyimpan perubahan.\n";
}
