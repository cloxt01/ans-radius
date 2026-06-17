<?php

// =====================
// DB CONFIG
// =====================
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

// =====================
// MODE: dry-run vs apply
// =====================
$apply = in_array('--apply', $argv);

echo "=================================================\n";
echo "   UPDATE PACKAGE PELANGGAN FIKTIF BERDASARKAN %\n";
echo "=================================================\n";
echo $apply
    ? "Mode: APPLY (Data di database akan diubah)\n\n"
    : "Mode: DRY-RUN (Hanya simulasi, aman dari perubahan)\n\n";

// =====================
// CONFIG SETUP PERSENTASE PAKET
// =====================
// ID Paket => Persentase Probabilitas (Total HARUS 100)
$packageDistribution = [
    1 => 85, // Request lu 80%, gua tambahin sisa 5% ke sini biar totalnya pas 100%
    3 => 10, // 10%
    4 => 2,  // 2%
    5 => 2,  // 2%
    6 => 1   // 1%
];

// Validasi total persentase
$totalPercent = array_sum($packageDistribution);
if ($totalPercent !== 100) {
    die("❌ ERROR: Total persentase config lu saat ini {$totalPercent}%. Total harus pas 100%.\n");
}

// =====================
// AMBIL PELANGGAN FIKTIF
// =====================
$sqlSelect = "
    SELECT c.id, c.pppoe_username, c.package_id 
    FROM customers c
    INNER JOIN fiktif_customers fc ON c.id = fc.customer_id
";

$stmtSelect = $pdo->query($sqlSelect);
$targets = $stmtSelect->fetchAll();

if (!$targets) {
    die("Tidak ditemukan pelanggan fiktif.\n");
}

echo "Ditemukan " . count($targets) . " pelanggan fiktif. Memulai distribusi paket...\n\n";

// =====================
// PREPARE UPDATE
// =====================
$updateStmt = $pdo->prepare("UPDATE customers SET package_id = ? WHERE id = ?");

if ($apply) {
    $pdo->beginTransaction();
}

$updated = 0;
$stats = [1 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

try {
    foreach ($targets as $target) {
        $cid = $target['id'];

        // LOGIKA PENENTUAN PAKET BERDASARKAN PERSENTASE (CUMULATIVE PROBABILITY)
        $roll = mt_rand(1, 100);
        $cumulative = 0;
        $selectedPackageId = 1; // Default fallback

        foreach ($packageDistribution as $pkgId => $percent) {
            $cumulative += $percent;
            if ($roll <= $cumulative) {
                $selectedPackageId = $pkgId;
                break;
            }
        }

        if ($apply) {
            $updateStmt->execute([$selectedPackageId, $cid]);
        }

        $stats[$selectedPackageId]++;
        $updated++;
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
// HASIL & VERIFIKASI
// =====================
echo "=== HASIL DISTRIBUSI PAKET ===\n";
foreach ($stats as $pkgId => $count) {
    $realPercent = round(($count / $updated) * 100, 2);
    $targetPercent = $packageDistribution[$pkgId];

    $namaPaket = "";
    if ($pkgId == 1) $namaPaket = "Paket Stater (100k)   ";
    if ($pkgId == 3) $namaPaket = "Paket Advanced (150k) ";
    if ($pkgId == 4) $namaPaket = "Paket Ultimate (200k) ";
    if ($pkgId == 5) $namaPaket = "Paket Bussiness (250k)";
    if ($pkgId == 6) $namaPaket = "Paket Corporate (500k)";

    echo "ID {$pkgId} | {$namaPaket} : {$count} user ({$realPercent}% vs Target {$targetPercent}%)\n";
}

echo "\n==============================\n";
echo "SELESAI\n";
echo "Total diupdate: {$updated} pelanggan\n";

if (!$apply) {
    echo "\n⚠️ (Dry-run) Data belum disimpan ke database.\n";
    echo "Ketik perintah ini untuk mengeksekusi beneran:\n";
    echo "php " . basename(__FILE__) . " --apply\n";
}
?>