<?php
/**
 * regenerate_fiktif_invoices.php
 *
 * Menghapus invoice fiktif (customer terdaftar di fiktif_customers) yang
 * status-nya unpaid dan due_date jatuh di bulan berjalan, lalu generate
 * ulang invoice baru sesuai ketentuan:
 *
 *   invoice_number = INV-[Ymd][customer_id][rand 000000-999999]
 *   amount         = packages.price dari package_id customer saat ini
 *   status         = unpaid
 *   due_date       = due_date invoice bulan sebelumnya + 1 bulan
 *                     (jika tidak ada invoice bulan sebelumnya, acak
 *                      antara hari ini s/d akhir bulan berjalan)
 *   paid_at        = NULL
 *   payment_method = 'manual admin'
 *   payment_ref    = NULL
 *
 * Usage:
 *   php regenerate_fiktif_invoices.php --dry-run   # simulasi, TIDAK ada perubahan ke DB
 *   php regenerate_fiktif_invoices.php --apply      # eksekusi perubahan sungguhan
 *
 * Default (tanpa flag) = dry-run, demi safety.
 */

declare(strict_types=1);
include '../includes/config.php';
// ====== KONFIGURASI DB ======
$DB_HOST = DB_HOST;
$DB_USER = DB_USER;
$DB_PASS = DB_PASS; 
$DB_NAME = DB_NAME;
$pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// ── Config koneksi DB ─────────────────────────────────────────────
$dbHost = 'localhost';
$dbName = 'ans_radius';   // sesuaikan nama database
$dbUser = 'root';
$dbPass = '';

// ── Parse argumen ──────────────────────────────────────────────────
$options  = getopt('', ['dry-run', 'apply']);
$isDryRun = !isset($options['apply']); // default dry-run kecuali --apply eksplisit

echo "==============================================\n";
echo " Regenerate Fiktif Invoices (" . ($isDryRun ? 'DRY RUN' : 'APPLY') . ")\n";
echo "==============================================\n\n";

try {
	$DB_HOST = DB_HOST;
	$DB_USER = DB_USER;
	$DB_PASS = DB_PASS; 
	$DB_NAME = DB_NAME;
	$DB_HOST = DB_HOST;
$DB_USER = DB_USER;
$DB_PASS = DB_PASS; 
$DB_NAME = DB_NAME;
$pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
} catch (PDOException $e) {
    die("Gagal konek DB: " . $e->getMessage() . "\n");
}

$today        = new DateTime();
$startOfMonth = new DateTime(date('Y-m-01'));
$endOfMonth   = new DateTime(date('Y-m-t'));

// ── Ambil target: invoice fiktif, unpaid, due_date bulan ini ──────
$stmt = $pdo->prepare("
    SELECT i.*
    FROM invoices i
    INNER JOIN fiktif_customers fc ON fc.customer_id = i.customer_id
    WHERE i.status = 'unpaid'
      AND i.due_date BETWEEN :start AND :end
    ORDER BY i.customer_id
");
$stmt->execute([
    ':start' => $startOfMonth->format('Y-m-d'),
    ':end'   => $endOfMonth->format('Y-m-d'),
]);
$targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($targets)) {
    echo "Tidak ada invoice fiktif unpaid dengan due_date bulan ini. Selesai.\n";
    exit(0);
}

echo "Ditemukan " . count($targets) . " invoice fiktif untuk diproses.\n\n";

$processed = 0;
$skipped   = 0;

$pdo->beginTransaction();

try {
    foreach ($targets as $inv) {
        $customerId       = (int) $inv['customer_id'];
        $oldInvoiceId      = (int) $inv['id'];
        $oldInvoiceNumber  = $inv['invoice_number'];

        echo "--- Customer #{$customerId} | Invoice lama: {$oldInvoiceNumber} (id={$oldInvoiceId}) ---\n";

        // Ambil data customer + harga package saat ini
        $custStmt = $pdo->prepare("
            SELECT c.id, c.package_id, p.price
            FROM customers c
            LEFT JOIN packages p ON p.id = c.package_id
            WHERE c.id = :id
        ");
        $custStmt->execute([':id' => $customerId]);
        $customer = $custStmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer || $customer['package_id'] === null) {
            echo "  [SKIP] Customer tidak punya package_id, dilewati.\n\n";
            $skipped++;
            continue;
        }
        if ($customer['price'] === null) {
            echo "  [SKIP] Package id {$customer['package_id']} tidak ditemukan di tabel packages, dilewati.\n\n";
            $skipped++;
            continue;
        }
        $amount = $customer['price'];

        // Cari due_date invoice bulan sebelumnya (paling baru sebelum bulan ini)
        $prevStmt = $pdo->prepare("
            SELECT due_date
            FROM invoices
            WHERE customer_id = :cid
              AND due_date < :start
              AND id != :excludeId
            ORDER BY due_date DESC
            LIMIT 1
        ");
        $prevStmt->execute([
            ':cid'       => $customerId,
            ':start'     => $startOfMonth->format('Y-m-d'),
            ':excludeId' => $oldInvoiceId,
        ]);
        $prevDueDate = $prevStmt->fetchColumn();

        if ($prevDueDate) {
            $newDueDate = (new DateTime($prevDueDate))->modify('+1 month');
            echo "  Invoice bulan sebelumnya ditemukan (due_date: {$prevDueDate}) -> due_date baru: {$newDueDate->format('Y-m-d')}\n";
        } else {
            $daysRange  = max(0, (int) $today->diff($endOfMonth)->days);
            $randomDays = random_int(0, $daysRange);
            $newDueDate = (clone $today)->modify("+{$randomDays} days");
            echo "  Tidak ada invoice bulan sebelumnya -> due_date acak: {$newDueDate->format('Y-m-d')}\n";
        }

        // Generate invoice_number unik
        do {
            $rand             = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $newInvoiceNumber = 'INV-' . date('Ymd') . $customerId . $rand;
            $checkStmt        = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = :inv");
            $checkStmt->execute([':inv' => $newInvoiceNumber]);
            $exists = (int) $checkStmt->fetchColumn() > 0;
        } while ($exists);

        echo "  Invoice number baru : {$newInvoiceNumber}\n";
        echo "  Amount              : {$amount}\n";

        if (!$isDryRun) {
            // Hapus fiktif_invoices terkait dulu (hindari orphan / bentrok unique invoice_id)
            $delFiktifInv = $pdo->prepare("DELETE FROM fiktif_invoices WHERE invoice_id = :id");
            $delFiktifInv->execute([':id' => $oldInvoiceId]);

            // Hapus invoice lama
            $delInv = $pdo->prepare("DELETE FROM invoices WHERE id = :id");
            $delInv->execute([':id' => $oldInvoiceId]);

            // Insert invoice baru
            $insStmt = $pdo->prepare("
                INSERT INTO invoices
                    (invoice_number, customer_id, amount, status, due_date, paid_at, payment_method, payment_ref, created_at, updated_at)
                VALUES
                    (:invoice_number, :customer_id, :amount, 'unpaid', :due_date, NULL, 'manual admin', NULL, NOW(), NOW())
            ");
            $insStmt->execute([
                ':invoice_number' => $newInvoiceNumber,
                ':customer_id'    => $customerId,
                ':amount'         => $amount,
                ':due_date'       => $newDueDate->format('Y-m-d'),
            ]);

            echo "  [OK] Invoice lama dihapus, invoice baru dibuat (id=" . $pdo->lastInsertId() . ").\n\n";
        } else {
            echo "  [DRY-RUN] Tidak ada perubahan dieksekusi ke DB.\n\n";
        }

        $processed++;
    }

    if (!$isDryRun) {
        $pdo->commit();
        echo "==============================================\n";
        echo "Selesai. Diproses: {$processed}, Dilewati: {$skipped}. Perubahan sudah di-commit.\n";
    } else {
        $pdo->rollBack();
        echo "==============================================\n";
        echo "Dry run selesai. Akan diproses: {$processed}, akan dilewati: {$skipped}.\n";
        echo "Tidak ada perubahan yang disimpan. Jalankan ulang dengan --apply untuk eksekusi.\n";
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo "Transaksi di-rollback, tidak ada perubahan yang disimpan.\n";
    exit(1);
}
