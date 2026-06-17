<?php

include '../includes/config.php';
// ====== KONFIGURASI DB ======
$DB_HOST = DB_HOST;
$DB_USER = DB_USER;
$DB_PASS = DB_PASS; // sesuaika
$DB_NAME = DB_NAME;

$pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$sql = "
SELECT 
    c.id AS customer_id,
    i.first_invoice_created_at
FROM customers c
JOIN (
    SELECT 
        customer_id,
        MIN(created_at) AS first_invoice_created_at
    FROM invoices
    GROUP BY customer_id
) i ON i.customer_id = c.id
WHERE DATE(i.first_invoice_created_at) BETWEEN '2026-05-01' AND '2026-06-30'
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "Target: " . count($rows) . "\n";

$update = $pdo->prepare("
    UPDATE customers
    SET created_at = ?
    WHERE id = ?
");

$pdo->beginTransaction();

foreach ($rows as $row) {

    // PENTING: jangan re-derive dari invoices lagi
    $update->execute([
        $row['first_invoice_created_at'],
        $row['customer_id']
    ]);

    echo "Updated {$row['customer_id']} -> {$row['first_invoice_created_at']}\n";
}

$pdo->commit();

echo "DONE\n";