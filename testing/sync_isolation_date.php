<?php

// =====================
// DB CONFIG
// =====================
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

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

// =====================
// GET FIKTIF CUSTOMERS
// =====================
$fiktifIds = $pdo->query("SELECT customer_id FROM fiktif_customers")
    ->fetchAll(PDO::FETCH_COLUMN);

if (!$fiktifIds) {
    die("Tidak ada customer fiktif.\n");
}

echo "Fiktif ditemukan: " . count($fiktifIds) . "\n";

// =====================
// AMBIL INVOICE TERAKHIR (BATCH, NO N+1)
// =====================
$placeholders = implode(',', array_fill(0, count($fiktifIds), '?'));

$sqlInvoices = "
    SELECT i.*
    FROM invoices i
    INNER JOIN (
        SELECT customer_id, MAX(due_date) AS max_due
        FROM invoices
        WHERE customer_id IN ($placeholders)
        GROUP BY customer_id
    ) latest
    ON i.customer_id = latest.customer_id
    AND i.due_date = latest.max_due
";

$stmtInv = $pdo->prepare($sqlInvoices);
$stmtInv->execute($fiktifIds);
$invoices = $stmtInv->fetchAll();

// index by customer_id biar gampang lookup
$invoiceMap = [];
foreach ($invoices as $inv) {
    $invoiceMap[$inv['customer_id']] = $inv;
}

// =====================
// UPDATE ISOLATION DATE
// =====================
$updateStmt = $pdo->prepare("
    UPDATE customers 
    SET isolation_date = ? 
    WHERE id = ?
");

// =====================
// TRANSACTION (BIAR GA SETENGAH MATI)
// =====================
$pdo->beginTransaction();

$updated = 0;
$noInvoice = 0;

foreach ($fiktifIds as $cid) {

    if (!isset($invoiceMap[$cid])) {
        $noInvoice++;
        echo "Customer $cid: tidak ada invoice\n";
        continue;
    }

    $invoice = $invoiceMap[$cid];

    $isolation = null;
    $reason = '';

    // =====================
    // BUSINESS RULE
    // =====================
    if ($invoice['status'] === 'paid' && !empty($invoice['paid_at'])) {

        $date = new DateTime($invoice['paid_at']);
        $date->modify('+30 days');

        $isolation = $date->format('Y-m-d');
        $reason = "paid_at + 30 hari";

    } else {

        $date = new DateTime($invoice['due_date']);
        $isolation = $date->format('Y-m-d');
        $reason = "due_date fallback";
    }

    $updateStmt->execute([$isolation, $cid]);

    $updated++;
    echo "Customer $cid -> $isolation ($reason)\n";
}

$pdo->commit();

// =====================
// SUMMARY
// =====================
echo "\nSELESAI\n";
echo "Updated: $updated\n";
echo "Tanpa invoice: $noInvoice\n";

?>