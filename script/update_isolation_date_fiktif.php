<?php


// =====================
// DB CONFIG
// =====================
include '../includes/config.php';

$host = DB_HOST;
$db = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = 'utf8mb4';

date_default_timezone_set('Asia/Jakarta');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage() . "\n");
}

// =====================
// MODE: dry-run (default) vs apply
// =====================
$apply = in_array('--apply', $argv);

echo "===============================================================\n";
echo "  UPDATE ISOLATION DATE (< 30 APRIL 2026) FIKTIF TANPA INVOICE \n";
echo "  (Mode: Ultra-Natural ISP Cut-off Simulation)                 \n";
echo "===============================================================\n";
echo $apply
    ? "Mode: APPLY (data akan diupdate ke database)\n\n"
    : "Mode: DRY-RUN (hanya simulasi, tidak ada data yang diubah)\n\n";

// =====================
// 1. CARI PELANGGAN FIKTIF TANPA INVOICE
// =====================

$sqlSelect = "
    SELECT c.id, c.pppoe_username, c.isolation_date, c.status
    FROM customers c
    INNER JOIN fiktif_customers fc ON c.id = fc.customer_id
    LEFT JOIN invoices i ON c.id = i.customer_id
    WHERE i.id IS NULL
";

$stmtSelect = $pdo->query($sqlSelect);
$targets = $stmtSelect->fetchAll();

if (!$targets) {
    die("✓ Keren! Tidak ditemukan pelanggan fiktif yang tidak memiliki invoice.\n");
}

echo "Ditemukan " . count($targets) . " pelanggan fiktif tanpa invoice.\n\n";

$updateStmt = $pdo->prepare("
    UPDATE customers 
    SET isolation_date = ?, status = 'isolated' 
    WHERE id = ?
");

$updated = 0;
$logLines = [];

if ($apply) {
    $pdo->beginTransaction();
}

try {
    // Tanggal-tanggal umum ISP melakukan pemutusan (cut-off massal)
    $commonCutoffDays = [1, 5, 10, 15, 20, 25];

    foreach ($targets as $target) {
        $cid = $target['id'];
        $username = $target['pppoe_username'];
        $oldIsolation = $target['isolation_date'] ?? 'NULL';
        $oldStatus = $target['status'];

        // --- LOGIKA NATURAL: Distribusi Bulan Tertimbang ---
        $monthRoll = mt_rand(1, 100);
        if ($monthRoll <= 45) {
            $month = 4; // 45% kemungkinan terisolasi di bulan April (paling banyak karena masih baru)
        } elseif ($monthRoll <= 75) {
            $month = 3; // 30% kemungkinan di bulan Maret
        } elseif ($monthRoll <= 90) {
            $month = 2; // 15% kemungkinan di bulan Februari
        } else {
            $month = 1; // 10% kemungkinan di bulan Januari (akun fosil)
        }

        // --- LOGIKA NATURAL: Tanggal Batch Cut-off ---
        // Pilih tanggal dari array commonCutoffDays
        $baseDay = $commonCutoffDays[array_rand($commonCutoffDays)];

        // --- LOGIKA NATURAL: Keterlambatan Sistem / Toleransi ---
        // Kadang mati pas di tanggalnya, kadang molor 1-2 hari
        $delay = mt_rand(0, 2);
        $finalDay = $baseDay + $delay;

        // Validasi khusus bulan Februari agar tidak lewat dari tanggal 28
        if ($month == 2 && $finalDay > 28) {
            $finalDay = 28;
        }

        $newIsolation = sprintf('2026-%02d-%02d', $month, $finalDay);

        // Batas absolut: Tidak boleh lewat dari 29 April 2026
        if ($newIsolation >= '2026-04-30') {
            $newIsolation = '2026-04-29';
        }

        if ($apply) {
            $updateStmt->execute([$newIsolation, $cid]);
        }

        $updated++;
        $logLines[] = sprintf(
            "[UPDATE] ID: %-5d | User: %-15s | Status: %-8s -> isolated | Date: %-10s -> %s",
            $cid,
            $username,
            $oldStatus,
            $oldIsolation,
            $newIsolation
        );
    }

    if ($apply) {
        $pdo->commit();
    }

} catch (Exception $e) {
    if ($apply) {
        $pdo->rollBack();
    }
    die("❌ ERROR: " . $e->getMessage() . "\n");
}

// =====================
// OUTPUT LOG
// =====================
foreach ($logLines as $line) {
    echo $line . "\n";
}

echo "\n====================\n";
echo "SELESAI\n";
echo "Total target fiktif  : " . count($targets) . "\n";
echo "Total diupdate       : {$updated}\n";

if (!$apply) {
    echo "\n⚠️ (Dry-run) Data belum diubah. Jalankan dengan perintah:\n";
    echo "php " . basename(__FILE__) . " --apply\n";
} else {
    $logFile = __DIR__ . '/log_fiktif_no_invoice_natural_' . date('Ymd_His') . '.txt';
    file_put_contents($logFile, implode("\n", $logLines) . "\n");
    echo "\nLog disimpan ke: {$logFile}\n";
}
