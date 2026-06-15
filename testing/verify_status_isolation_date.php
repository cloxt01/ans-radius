<?php

/**
 * verify_fix_isolation_status.php
 *
 * Verifikasi & perbaikan menyeluruh untuk:
 *
 *  A. customers.isolation_date
 *     Seharusnya = invoice TERAKHIR (due_date paling besar, status paid/unpaid):
 *       - jika invoice terakhir status='paid' & paid_at terisi
 *             -> expected_isolation_date = DATE(paid_at) + 30 hari
 *       - jika invoice terakhir status='unpaid' (paid_at NULL)
 *             -> expected_isolation_date = due_date (fallback)
 *
 *  B. customers.status (active/isolated)
 *     Berdasarkan expected_isolation_date (hasil poin A) vs CURDATE():
 *       - expected_isolation_date > CURDATE()              -> status seharusnya 'active'
 *       - expected_isolation_date <= CURDATE() & auto_isolate=1 -> status seharusnya 'isolated'
 *       - expected_isolation_date <= CURDATE() & auto_isolate=0 -> status tidak dipaksa (dibiarkan)
 *
 *  Customer TANPA invoice (paid/unpaid) sama sekali -> tidak bisa dihitung,
 *  ditampilkan sebagai WARNING "perlu perbaikan manual".
 *
 * Jalankan via CLI:
 *   php verify_fix_isolation_status.php            -> dry-run (tampilkan saja, tidak update)
 *   php verify_fix_isolation_status.php --apply     -> eksekusi update sungguhan
 */

// =====================
// DB CONFIG
// =====================
include '../includes/config.php';
// ====== KONFIGURASI DB ======

$host    = DB_HOST;
$db      = DB_NAME;    // Sesuaikan nama database
$user    = DB_USER;    // Sesuaikan username
$pass    = DB_PASS;
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
$today = date('Y-m-d');

echo $apply
    ? "Mode: APPLY (data akan diupdate)\n"
    : "Mode: DRY-RUN (tidak ada perubahan ke database)\n";
echo "Tanggal referensi (CURDATE): {$today}\n\n";

// =====================
// 1. Ambil invoice TERAKHIR per customer (status paid/unpaid, tie-break id terbesar)
// =====================
$sqlInvoices = "
    SELECT 
        i.id,
        i.customer_id,
        i.status AS invoice_status,
        i.due_date,
        i.paid_at,
        c.pppoe_username,
        c.status AS customer_status,
        c.isolation_date AS current_isolation_date,
        c.auto_isolate
    FROM invoices i
    INNER JOIN (
        SELECT customer_id, MAX(due_date) AS max_due
        FROM invoices
        WHERE status IN ('paid','unpaid')
        GROUP BY customer_id
    ) latest
        ON i.customer_id = latest.customer_id
       AND i.due_date = latest.max_due
    INNER JOIN customers c ON c.id = i.customer_id
    WHERE i.status IN ('paid','unpaid')
    ORDER BY i.customer_id, i.id DESC
";

$rows = $pdo->query($sqlInvoices)->fetchAll();

// dedupe: kalau due_date sama (tie), ambil id terbesar (sudah ORDER BY id DESC)
$latestPerCustomer = [];
foreach ($rows as $row) {
    $cid = $row['customer_id'];
    if (!isset($latestPerCustomer[$cid])) {
        $latestPerCustomer[$cid] = $row;
    }
}

// =====================
// 2. Customer TANPA invoice paid/unpaid sama sekali
// =====================
$sqlNoInvoice = "
    SELECT c.id, c.pppoe_username, c.status, c.isolation_date, c.auto_isolate
    FROM customers c
    LEFT JOIN invoices i 
        ON i.customer_id = c.id AND i.status IN ('paid','unpaid')
    WHERE i.id IS NULL
    ORDER BY c.id
";
$noInvoiceCustomers = $pdo->query($sqlNoInvoice)->fetchAll();

// =====================
// 3. Hitung expected_isolation_date & expected_status per customer
// =====================
$isoMismatches    = [];  // isolation_date perlu diupdate
$statusMismatches = [];  // status perlu diupdate
$noChange         = 0;

foreach ($latestPerCustomer as $cid => $inv) {

    $username      = $inv['pppoe_username'];
    $currentIso    = $inv['current_isolation_date'];
    $currentStatus = $inv['customer_status'];
    $autoIsolate   = (int) $inv['auto_isolate'];

    // ---- A. Hitung expected_isolation_date ----
    if (strtolower($inv['invoice_status']) === 'paid' && !empty($inv['paid_at'])) {
        $date = new DateTime($inv['paid_at']);
        $date->modify('+30 days');
        $reasonIso = 'paid_at + 30 hari';
    } else {
        $date = new DateTime($inv['due_date']);
        $reasonIso = 'due_date (fallback, invoice terakhir unpaid)';
    }
    $expectedIso = $date->format('Y-m-d');

    $isoMismatch = ($currentIso !== $expectedIso);

    if ($isoMismatch) {
        $isoMismatches[] = [
            'id'          => $cid,
            'username'    => $username,
            'old'         => $currentIso ?? 'NULL',
            'new'         => $expectedIso,
            'reason'      => $reasonIso,
        ];
    }

    // ---- B. Hitung expected_status berdasarkan expected_isolation_date ----
    if ($expectedIso > $today) {
        $expectedStatus = 'active';
    } else {
        // expectedIso <= today
        if ($autoIsolate === 1) {
            $expectedStatus = 'isolated';
        } else {
            // auto_isolate = 0 -> tidak dipaksa, biarkan status saat ini
            $expectedStatus = $currentStatus;
        }
    }

    $statusMismatch = ($currentStatus !== $expectedStatus);

    if ($statusMismatch) {
        $statusMismatches[] = [
            'id'              => $cid,
            'username'        => $username,
            'old_status'      => $currentStatus,
            'new_status'      => $expectedStatus,
            'expected_iso'    => $expectedIso,
            'auto_isolate'    => $autoIsolate,
        ];
    }

    if (!$isoMismatch && !$statusMismatch) {
        $noChange++;
    }
}

// =====================
// 4. Tampilkan hasil: isolation_date mismatch
// =====================
echo "=== A. isolation_date tidak sesuai (akan diupdate) ===\n";
echo "Total: " . count($isoMismatches) . "\n";
foreach ($isoMismatches as $r) {
    printf(
        "[ISO]    %-20s id=%-6d %s -> %s  (%s)\n",
        $r['username'],
        $r['id'],
        $r['old'],
        $r['new'],
        $r['reason']
    );
}

// =====================
// 5. Tampilkan hasil: status mismatch
// =====================
echo "\n=== B. status tidak sesuai (akan diupdate) ===\n";
echo "Total: " . count($statusMismatches) . "\n";
foreach ($statusMismatches as $r) {
    printf(
        "[STATUS] %-20s id=%-6d %-9s -> %-9s  (expected_isolation_date=%s, auto_isolate=%d)\n",
        $r['username'],
        $r['id'],
        $r['old_status'],
        $r['new_status'],
        $r['expected_iso'],
        $r['auto_isolate']
    );
}

// =====================
// 6. Customer tanpa invoice (warning, manual)
// =====================
if (!empty($noInvoiceCustomers)) {
    echo "\n=== C. WARNING: Customer TANPA invoice paid/unpaid (perlu perbaikan manual) ===\n";
    echo "isolation_date tidak bisa dihitung otomatis untuk customer berikut:\n\n";
    foreach ($noInvoiceCustomers as $r) {
        printf(
            "  - %-20s id=%-6d status=%-9s isolation_date=%-12s auto_isolate=%d\n",
            $r['pppoe_username'],
            $r['id'],
            $r['status'],
            $r['isolation_date'] ?? 'NULL',
            $r['auto_isolate']
        );
    }
}

// =====================
// 7. Ringkasan
// =====================
echo "\n====================\n";
echo "RINGKASAN\n";
echo "Total customer dengan invoice  : " . count($latestPerCustomer) . "\n";
echo "isolation_date perlu update    : " . count($isoMismatches) . "\n";
echo "status perlu update            : " . count($statusMismatches) . "\n";
echo "Sudah sinkron (tidak berubah)  : {$noChange}\n";
echo "Tanpa invoice (manual)         : " . count($noInvoiceCustomers) . "\n";

if (!$apply) {
    echo "\n(Dry-run) Tidak ada perubahan dilakukan. Jalankan dengan --apply untuk eksekusi.\n";
    exit(0);
}

if (empty($isoMismatches) && empty($statusMismatches)) {
    echo "\nTidak ada yang perlu diupdate.\n";
    exit(0);
}

// =====================
// 8. APPLY: update isolation_date dulu, baru status
// =====================
$pdo->beginTransaction();

try {
    $stmtIso = $pdo->prepare("UPDATE customers SET isolation_date = :iso WHERE id = :id");
    foreach ($isoMismatches as $r) {
        $stmtIso->execute([':iso' => $r['new'], ':id' => $r['id']]);
    }

    $stmtStatus = $pdo->prepare("UPDATE customers SET status = :status WHERE id = :id");
    foreach ($statusMismatches as $r) {
        $stmtStatus->execute([':status' => $r['new_status'], ':id' => $r['id']]);
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("\nERROR: " . $e->getMessage() . "\n");
}

echo "\nSELESAI.\n";
echo "isolation_date diupdate : " . count($isoMismatches) . " customer\n";
echo "status diupdate         : " . count($statusMismatches) . " customer\n";
echo "\nCatatan: jangan lupa jalankan sync_radusergroup.php --apply setelah ini,\n";
echo "agar radius_db.radusergroup ikut sinkron dengan status baru.\n";

// =====================
// 9. Simpan log
// =====================
$logFile = __DIR__ . '/log_verify_fix_isolation_' . date('Ymd_His') . '.txt';
$logLines = [];

$logLines[] = "=== ISOLATION_DATE MISMATCH ===";
foreach ($isoMismatches as $r) {
    $logLines[] = "{$r['username']} (id={$r['id']}): {$r['old']} -> {$r['new']} ({$r['reason']})";
}

$logLines[] = "\n=== STATUS MISMATCH ===";
foreach ($statusMismatches as $r) {
    $logLines[] = "{$r['username']} (id={$r['id']}): {$r['old_status']} -> {$r['new_status']} (expected_iso={$r['expected_iso']}, auto_isolate={$r['auto_isolate']})";
}

$logLines[] = "\n=== NO INVOICE (MANUAL) ===";
foreach ($noInvoiceCustomers as $r) {
    $logLines[] = "{$r['pppoe_username']} (id={$r['id']}): status={$r['status']} isolation_date=" . ($r['isolation_date'] ?? 'NULL');
}

file_put_contents($logFile, implode("\n", $logLines) . "\n");
echo "\nLog disimpan ke: {$logFile}\n";