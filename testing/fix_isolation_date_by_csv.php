<?php

include '../includes/config.php';
// ==========================================
// 1. CEK ARGUMEN COMMAND LINE (--apply)
// ==========================================
$isDryRun = !in_array('--apply', $argv);

if ($isDryRun) {
    echo "====================================================\n";
    echo " [ DRY RUN MODE ] - Simulasi Aktif\n";
    echo " Tidak ada data yang akan diubah di database.\n";
    echo " Gunakan argumen '--apply' untuk menyimpan perubahan.\n";
    echo "====================================================\n\n";
} else {
    echo "====================================================\n";
    echo " [ APPLY MODE ] - Perubahan akan DISIMPAN!\n";
    echo "====================================================\n\n";
}

// ==========================================
// 2. KONFIGURASI DATABASE & FILE
// ==========================================
$host     = DB_HOST;
$dbname   = DB_NAME;
$username = DB_USER;
$password = DB_PASS;

$fixCsvFile = 'fix.csv';
$fixIdFile  = 'fix_id.csv';

// BACA ID DARI FILE fix_id.csv DINAMIS
if (!file_exists($fixIdFile)) {
    die("Error: File $fixIdFile tidak ditemukan!\n");
}

// Membaca file baris per baris
$rows = file($fixIdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Hapus header baris pertama ("id")
array_shift($rows);

// Bersihkan tanda kutip dan pastikan hanya angka yang masuk ke query
$cleanIds = array_map(function($line) {
    // Hapus tanda kutip ganda dan spasi
    return intval(str_replace('"', '', trim($line)));
}, $rows);

// Filter agar tidak ada angka 0 (akibat gagal parsing) dan gabungkan
$allowedIds = implode(',', array_filter($cleanIds));

if (empty($allowedIds)) {
    die("Error: File $fixIdFile kosong atau tidak berisi ID yang valid!\n");
}
// ==========================================
// 3. PROSES DATABASE
// ==========================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (($handle = fopen($fixCsvFile, "r")) !== FALSE) {
        $headers = fgetcsv($handle);
        $usernameIdx = array_search('username', $headers);
        $expiredAtIdx = array_search('expired_at', $headers);

        // --- PREPARE STATEMENTS ---
        // Tambahkan query SELECT untuk ambil data lama
        $stmtGetCustomer = $pdo->prepare("SELECT id, isolation_date FROM customers WHERE pppoe_username = :pppoe_username AND id IN ($allowedIds)");
        $stmtGetInvoice   = $pdo->prepare("SELECT id, status FROM invoices WHERE customer_id = :customer_id ORDER BY id DESC LIMIT 1");

        $stmtUpdateCustomer = $pdo->prepare("UPDATE customers SET isolation_date = :isolation_date WHERE id = :id");
        $stmtUpdateInvoice  = $pdo->prepare("UPDATE invoices SET status = 'unpaid' WHERE id = :invoice_id");

        $pdo->beginTransaction();
        $customerUpdatedCount = 0;
        $invoiceUpdatedCount = 0;

        while (($row = fgetcsv($handle)) !== FALSE) {
            $csvUsername = $row[$usernameIdx] ?? '';
            $csvExpiredAt = $row[$expiredAtIdx] ?? '';

            if (!empty($csvUsername) && !empty($csvExpiredAt)) {
                $newIsolationDate = substr($csvExpiredAt, 0, 10);

                $stmtGetCustomer->execute([':pppoe_username' => $csvUsername]);
                $customer = $stmtGetCustomer->fetch(PDO::FETCH_ASSOC);

                if ($customer) {
                    $customerId = $customer['id'];
                    $oldDate = $customer['isolation_date'];

                    // Log Perubahan Isolasi
                    if ($oldDate !== $newIsolationDate) {
                        echo "[ISOLASI] User: $csvUsername | Before: $oldDate | After: $newIsolationDate\n";
                        $stmtUpdateCustomer->execute([':isolation_date' => $newIsolationDate, ':id' => $customerId]);
                        $customerUpdatedCount++;
                    }

                    // Log Perubahan Invoice
                    $stmtGetInvoice->execute([':customer_id' => $customerId]);
                    $invoice = $stmtGetInvoice->fetch(PDO::FETCH_ASSOC);

                    if ($invoice && $invoice['status'] !== 'unpaid') {
                        echo "[INVOICE] User: $csvUsername | Invoice ID: {$invoice['id']} | Status: {$invoice['status']} -> unpaid\n";
                        $stmtUpdateInvoice->execute([':invoice_id' => $invoice['id']]);
                        $invoiceUpdatedCount++;
                    }
                }
            }
        }
        fclose($handle);

        if ($isDryRun) {
            $pdo->rollBack();
            echo "-> Simulasi Selesai.\n";
            echo "-> Jika --apply digunakan: $customerUpdatedCount update isolasi, $invoiceUpdatedCount update invoice.\n";
        } else {
            $pdo->commit();
            echo "-> Proses Eksekusi Selesai.\n";
            echo "-> TOTAL $customerUpdatedCount pelanggan diperbarui, $invoiceUpdatedCount invoice di-unpaid.\n";
        }

    } else {
        die("Error: Tidak dapat membuka file $fixCsvFile.\n");
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("\nDatabase Error: " . $e->getMessage() . "\n");
}
?>