<?php

// =====================
// DB CONFIG
// =====================
include '../includes/config.php';

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

// =====================
// MODE: dry-run (default) vs apply
//   php update_isolation_date.php           -> dry-run
//   php update_isolation_date.php --apply    -> eksekusi update sungguhan
// =====================
$apply = in_array('--apply', $argv);

echo $apply
    ? "Mode: APPLY (data akan diupdate)\n\n"
    : "Mode: DRY-RUN (tidak ada perubahan ke database)\n\n";

// =====================
// AMBIL INVOICE TERAKHIR SETIAP CUSTOMER
// (tie-breaker: kalau due_date sama, ambil id terbesar/terbaru)
// HANYA status paid & unpaid, cancelled di-exclude
// =====================
$sqlInvoices = "
    SELECT 
        i.id,
        i.customer_id,
        i.status,
        i.due_date,
        i.paid_at,
        c.pppoe_username,
        c.isolation_date AS old_isolation_date
    FROM invoices i
    INNER JOIN (
        SELECT 
            customer_id,
            MAX(due_date) AS max_due
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

$invoices = $pdo->query($sqlInvoices)->fetchAll();

if (!$invoices) {
    die("Tidak ada invoice ditemukan.\n");
}

// =====================
// DEDUPLIKASI: kalau due_date sama (tie), ambil row pertama
// per customer_id (sudah ORDER BY id DESC, jadi id terbesar menang)
// =====================
$latestPerCustomer = [];
foreach ($invoices as $row) {
    $cid = $row['customer_id'];
    if (!isset($latestPerCustomer[$cid])) {
        $latestPerCustomer[$cid] = $row;
    }
}

// =====================
// PREPARE UPDATE
// =====================
$updateStmt = $pdo->prepare("
    UPDATE customers
    SET isolation_date = ?
    WHERE id = ?
");

// =====================
// PROSES
// =====================
$updated = 0;
$skipped = 0;
$logLines = [];

if ($apply) {
    $pdo->beginTransaction();
}

try {

    foreach ($latestPerCustomer as $cid => $invoice) {

        $username = $invoice['pppoe_username'];

        // =====================
        // VALIDASI TANGGAL
        // =====================
        try {
            if (
                strtolower($invoice['status']) === 'paid'
                && !empty($invoice['paid_at'])
            ) {
                $date = new DateTime($invoice['paid_at']);
                $date->modify('+30 days');
                $reason = 'paid_at + 30 hari';
            } else {
                if (empty($invoice['due_date'])) {
                    throw new Exception("due_date kosong");
                }
                $date = new DateTime($invoice['due_date']);
                $reason = 'due_date fallback';
            }
        } catch (Exception $e) {
            $skipped++;
            $logLines[] = sprintf(
                "[SKIP]   %-20s (customer_id=%d) -> error: %s",
                $username,
                $cid,
                $e->getMessage()
            );
            continue;
        }

        $isolation = $date->format('Y-m-d');
        $oldIsolation = $invoice['old_isolation_date'] ?? 'NULL';

        // skip kalau tidak ada perubahan
        if ($oldIsolation === $isolation) {
            $logLines[] = sprintf(
                "[SAME]   %-20s (customer_id=%d) -> %s (%s, tidak berubah)",
                $username,
                $cid,
                $isolation,
                $reason
            );
            continue;
        }

        if ($apply) {
            $updateStmt->execute([$isolation, $cid]);
        }

        $updated++;

        $logLines[] = sprintf(
            "[UPDATE] %-20s (customer_id=%d) -> %s -> %s (%s)",
            $username,
            $cid,
            $oldIsolation,
            $isolation,
            $reason
        );
    }

    if ($apply) {
        $pdo->commit();
    }

} catch (Exception $e) {
    if ($apply) {
        $pdo->rollBack();
    }
    die("ERROR: " . $e->getMessage() . "\n");
}

// =====================
// OUTPUT LOG
// =====================
foreach ($logLines as $line) {
    echo $line . "\n";
}

echo "\n====================\n";
echo "SELESAI\n";
echo "Total invoice diproses : " . count($latestPerCustomer) . "\n";
echo "Total diupdate         : {$updated}\n";
echo "Total dilewati (skip)  : {$skipped}\n";
echo "Total tidak berubah    : " . (count($latestPerCustomer) - $updated - $skipped) . "\n";

if (!$apply) {
    echo "\n(Dry-run) Tidak ada perubahan dilakukan. Jalankan dengan --apply untuk eksekusi.\n";
}

// =====================
// WRITE LOG FILE
// =====================
$logFile = __DIR__ . '/log_isolation_date_' . date('Ymd_His') . '.txt';
file_put_contents($logFile, implode("\n", $logLines) . "\n");
echo "\nLog disimpan ke: {$logFile}\n";