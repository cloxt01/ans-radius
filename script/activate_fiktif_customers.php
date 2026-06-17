<?php
include '../includes/config.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $limit = 85;

    $sqlSelect = "
        SELECT c.id 
        FROM customers c
        INNER JOIN fiktif_customers fc ON c.id = fc.customer_id
        LEFT JOIN invoices i ON c.id = i.customer_id
        WHERE i.id IS NULL
        ORDER BY RAND() 
        LIMIT $limit
    ";

    $stmtSelect = $pdo->query($sqlSelect);
    $fiktifTanpaInvoice = $stmtSelect->fetchAll(PDO::FETCH_COLUMN);

    if (empty($fiktifTanpaInvoice)) {
        echo "Tidak ada lagi pelanggan fiktif tanpa invoice yang ditemukan.\n";
        exit;
    }

    echo "Ditemukan " . count($fiktifTanpaInvoice) . " pelanggan fiktif untuk diaktifkan. Mengupdate status...\n";

    // 2. Update status ke 'active'
    $pdo->beginTransaction();
    $stmtUpdate = $pdo->prepare("UPDATE customers SET status = 'active' WHERE id = ?");

    foreach ($fiktifTanpaInvoice as $customerId) {
        $stmtUpdate->execute([$customerId]);
        echo "✓ Mengaktifkan ID: $customerId\n";
    }

    $pdo->commit();
    echo "\n=== SELESAI: {$limit} pelanggan berhasil diaktifkan ===\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>