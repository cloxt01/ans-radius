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
include '../includes/config.php';
$host     = DB_HOST;
$dbname   = DB_NAME;    // Sesuaikan nama database
$username = DB_USER;    // Sesuaikan username
$password = DB_PASS;    // Sesuaikan password

$fixCsvFile = 'fix.csv';

// Daftar ID yang diizinkan untuk diupdate (128 ID)
$allowedIds = "3, 40, 77, 96, 109, 113, 114, 156, 299, 309, 311, 313, 316, 317, 318, 320, 321, 322, 323, 327, 329, 332, 333, 335, 346, 348, 356, 357, 373, 374, 393, 534, 545, 551, 557, 623, 624, 626, 627, 628, 629, 630, 631, 632, 633, 634, 635, 636, 637, 638, 639, 640, 641, 642, 643, 644, 645, 646, 647, 648, 649, 650, 651, 652, 653, 654, 655, 656, 657, 658, 659, 660, 661, 662, 663, 664, 665, 666, 667, 668, 669, 670, 671, 672, 673, 674, 675, 676, 677, 678, 679, 680, 681, 682, 683, 684, 685, 686, 687, 688, 689, 690, 691, 692, 693, 694, 695, 696, 697, 698, 699, 700, 701, 702, 703, 704, 705, 706, 707, 708, 709, 710, 711, 712, 713, 3443, 3448, 3471";

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

        if ($usernameIdx === false || $expiredAtIdx === false) {
            die("Error: Kolom 'username' atau 'expired_at' tidak ditemukan di $fixCsvFile\n");
        }

        // --- PREPARE STATEMENTS ---
        // 1. Ambil ID Customer
        $stmtGetCustomer = $pdo->prepare("SELECT id FROM customers WHERE pppoe_username = :pppoe_username AND id IN ($allowedIds)");

        // 2. Update Customer
        $stmtUpdateCustomer = $pdo->prepare("UPDATE customers SET isolation_date = :isolation_date WHERE id = :id");

        // 3. Update Invoice Terakhir
        // Asumsi: Tabel bernama 'invoices', direlasikan dengan 'customer_id', dan statusnya menggunakan string 'unpaid'
        $stmtUpdateInvoice = $pdo->prepare("UPDATE invoices SET status = 'unpaid' WHERE customer_id = :customer_id ORDER BY id DESC LIMIT 1");

        // Memulai Transaksi
        $pdo->beginTransaction();
        $customerUpdatedCount = 0;
        $invoiceUpdatedCount = 0;

        while (($row = fgetcsv($handle)) !== FALSE) {
            $csvUsername = $row[$usernameIdx];
            $csvExpiredAt = $row[$expiredAtIdx];

            if (!empty($csvUsername) && !empty($csvExpiredAt)) {
                $isolationDate = substr($csvExpiredAt, 0, 10);

                // Cek apakah user ada di database dan masuk dalam list allowedIds
                $stmtGetCustomer->execute([':pppoe_username' => $csvUsername]);
                $customer = $stmtGetCustomer->fetch(PDO::FETCH_ASSOC);

                if ($customer) {
                    $customerId = $customer['id'];

                    // A. Update tabel customers
                    $stmtUpdateCustomer->execute([
                        ':isolation_date' => $isolationDate,
                        ':id' => $customerId
                    ]);

                    if ($stmtUpdateCustomer->rowCount() > 0) {
                        $customerUpdatedCount++;
                    }

                    // B. Update tabel invoices (Invoice terakhir saja)
                    $stmtUpdateInvoice->execute([
                        ':customer_id' => $customerId
                    ]);

                    if ($stmtUpdateInvoice->rowCount() > 0) {
                        $invoiceUpdatedCount++;
                    }
                }
            }
        }

        fclose($handle);

        // ==========================================
        // 4. COMMIT ATAU ROLLBACK BERDASARKAN MODE
        // ==========================================
        if ($isDryRun) {
            $pdo->rollBack(); // Membatalkan semua perubahan simulasi
            echo "-> Simulasi Selesai.\n";
            echo "-> Jika --apply digunakan, maka:\n";
            echo "   - $customerUpdatedCount data 'isolation_date' AKAN diupdate.\n";
            echo "   - $invoiceUpdatedCount data 'invoice terakhir' AKAN diubah menjadi unpaid.\n";
            echo "-> STATUS: Data database TIDAK berubah.\n";
        } else {
            $pdo->commit(); // Menyimpan perubahan permanen
            echo "-> Proses Eksekusi Selesai.\n";
            echo "   - TOTAL $customerUpdatedCount pelanggan BERHASIL diperbarui.\n";
            echo "   - TOTAL $invoiceUpdatedCount invoice terakhir BERHASIL diset menjadi unpaid.\n";
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