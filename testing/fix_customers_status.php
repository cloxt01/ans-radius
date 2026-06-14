<?php

/**
 * fix_customer_status.php
 *
 * Sinkronisasi customers.status berdasarkan customers.isolation_date.
 *
 * Aturan:
 *  1. status = 'isolated' TAPI isolation_date > CURDATE()
 *     -> belum waktunya isolir -> status diubah jadi 'active'
 *     (isolation_date NULL tidak termasuk rule ini)
 *
 *  2. status = 'active' TAPI (isolation_date IS NULL ATAU isolation_date <= CURDATE())
 *     DAN auto_isolate = 1
 *     -> sudah waktunya isolir (atau belum pernah dijadwalkan) -> status diubah jadi 'isolated'
 *
 *  Customer dengan auto_isolate = 0 tidak akan di-auto-isolasi (rule #2 dilewati),
 *  tapi rule #1 (isolated padahal isolation_date di masa depan) tetap diproses
 *  untuk semua customer.
 *
 * Jalankan via CLI:
 *   php fix_customer_status.php            -> dry-run (tampilkan saja, tidak update)
 *   php fix_customer_status.php --apply     -> eksekusi update sungguhan
 */

// =====================
// DB CONFIG
// =====================
$host    = 'localhost';
$db      = 'ans_radius';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

date_default_timezone_set('Asia/Jakarta');

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

echo $apply
    ? "Mode: APPLY (data akan diupdate)\n\n"
    : "Mode: DRY-RUN (tidak ada perubahan ke database)\n\n";

// =====================
// 1. Cari mismatch
//    Rule 1: isolated tapi isolation_date masih masa depan / NULL -> active
//    Rule 2: active tapi isolation_date sudah lewat & auto_isolate=1 -> isolated
// =====================
$sqlMismatch = "
    SELECT
        id,
        pppoe_username,
        status,
        isolation_date,
        auto_isolate,
        CASE
            WHEN status = 'isolated'
                 AND isolation_date > CURDATE()
                THEN 'active'
            WHEN status = 'active'
                 AND (isolation_date IS NULL OR isolation_date <= CURDATE())
                 AND auto_isolate = 1
                THEN 'isolated'
        END AS expected_status
    FROM customers
    WHERE
        (status = 'isolated' AND isolation_date > CURDATE())
        OR
        (status = 'active' AND (isolation_date IS NULL OR isolation_date <= CURDATE()) AND auto_isolate = 1)
    ORDER BY id
";

$mismatches = $pdo->query($sqlMismatch)->fetchAll();

echo "Total customer status tidak sinkron: " . count($mismatches) . "\n\n";

if (empty($mismatches)) {
    echo "Tidak ada yang perlu diupdate.\n";
    exit(0);
}

// =====================
// 2. Tampilkan detail
// =====================
$nullWarnings = [];

foreach ($mismatches as $row) {
    $isNull = $row['isolation_date'] === null;

    printf(
        "[%s] %-20s id=%-6d status: %-9s -> %-9s | isolation_date=%-12s auto_isolate=%d%s\n",
        $apply ? 'UPDATE' : 'WILL-UPDATE',
        $row['pppoe_username'],
        $row['id'],
        $row['status'],
        $row['expected_status'],
        $row['isolation_date'] ?? 'NULL',
        $row['auto_isolate'],
        $isNull ? '  <-- WARNING: isolation_date NULL' : ''
    );

    if ($isNull) {
        $nullWarnings[] = $row;
    }
}

if (!empty($nullWarnings)) {
    echo "\n=== WARNING: isolation_date NULL (perlu perbaikan manual) ===\n";
    echo "Customer berikut akan diset 'isolated' karena isolation_date NULL\n";
    echo "(belum pernah dihitung/dijadwalkan). Status akan benar, tapi\n";
    echo "isolation_date tetap NULL -- sebaiknya dihitung ulang secara manual\n";
    echo "dari invoice terkait agar tanggal isolir tercatat dengan benar.\n\n";
    foreach ($nullWarnings as $row) {
        printf("  - %-20s (id=%d)\n", $row['pppoe_username'], $row['id']);
    }
    echo "\n";
}

// =====================
// 3. Apply update
// =====================
if (!$apply) {
    echo "\n(Dry-run) Tidak ada perubahan dilakukan. Jalankan dengan --apply untuk eksekusi.\n";
    exit(0);
}

$stmtUpdate = $pdo->prepare("
    UPDATE customers
    SET status = :status
    WHERE id = :id
");

$pdo->beginTransaction();

$updated = 0;

try {
    foreach ($mismatches as $row) {
        $stmtUpdate->execute([
            ':status' => $row['expected_status'],
            ':id'     => $row['id'],
        ]);
        $updated++;
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("\nERROR: " . $e->getMessage() . "\n");
}

echo "\n====================\n";
echo "SELESAI\n";
echo "Total customer diupdate: {$updated}\n";
echo "\nCatatan: jangan lupa jalankan sync_radusergroup.php setelah ini,\n";
echo "agar radius_db.radusergroup ikut sinkron dengan status baru.\n";