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
$dbUser   = DB_USER;
$dbPass   = DB_PASS;

$fileUpdate = 'fiktif_update.csv';
$fileId     = 'fiktif_id.csv';

function readCsvLine($handle) {
    $line = fgets($handle);
    if ($line === false) return false;
    $delimiter = (strpos($line, ';') !== false) ? ';' : ',';
    return str_getcsv($line, $delimiter);
}

// ==========================================
// 3. PROSES BACA 2 FILE & UPDATE DATABASE
// ==========================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $handleUpdate = fopen($fileUpdate, "r");
    $handleId     = fopen($fileId, "r");

    if ($handleUpdate !== FALSE && $handleId !== FALSE) {

        readCsvLine($handleUpdate);
        readCsvLine($handleId);

        // Siapkan 2 Query: 1 untuk ngecek, 1 untuk update
        $sqlCheck = "SELECT id FROM customers WHERE pppoe_username = :pppoe_username LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);

        $sqlUpdate = "UPDATE customers SET pppoe_username = :pppoe_username, address = :address WHERE id = :id";
        $stmtUpdate = $pdo->prepare($sqlUpdate);

        $pdo->beginTransaction();

        $updatedCount = 0;
        $skippedCount = 0;
        $totalBaris   = 0;

        $seenUsernames = []; // Melacak duplikat dari CSV itu sendiri

        while (($rowUpdate = readCsvLine($handleUpdate)) !== FALSE && ($rowId = readCsvLine($handleId)) !== FALSE) {

            if (empty($rowUpdate) || empty($rowId)) continue;

            $totalBaris++;

            $newUsername = trim($rowUpdate[0] ?? '');
            $newAddress  = trim($rowUpdate[1] ?? '');
            $customerId  = trim($rowId[0] ?? '');

            if (!empty($customerId) && is_numeric($customerId) && !empty($newUsername)) {

                // 1. Cek duplikat internal di dalam file CSV
                if (in_array($newUsername, $seenUsernames)) {
                    echo "   [SKIP] CSV Duplikat: '$newUsername' (ID: $customerId) sudah ada di baris CSV sebelumnya.\n";
                    $skippedCount++;
                    continue;
                }
                $seenUsernames[] = $newUsername;

                // 2. Cek duplikat ke Database (Apakah username sudah dipakai orang lain?)
                $stmtCheck->execute([':pppoe_username' => $newUsername]);
                $existingUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existingUser) {
                    // Jika username sudah ada di tabel customers
                    // Dan ID yang punya username itu BEDA dengan ID yang lagi mau kita update
                    if ($existingUser['id'] != $customerId) {
                        echo "   [SKIP] DB Duplikat : '$newUsername' gagal diupdate untuk ID $customerId karena sudah dipakai oleh ID " . $existingUser['id'] . ".\n";
                        $skippedCount++;
                        continue; // Lewati, jangan di-update
                    }
                }

                // 3. Jika aman dari duplikat, lakukan UPDATE
                $stmtUpdate->execute([
                    ':pppoe_username' => $newUsername,
                    ':address'  => $newAddress,
                    ':id'       => $customerId
                ]);

                if ($stmtUpdate->rowCount() > 0) {
                    $updatedCount++;
                }
            }
        }

        fclose($handleUpdate);
        fclose($handleId);

        // ==========================================
        // 4. COMMIT ATAU ROLLBACK BERDASARKAN MODE
        // ==========================================
        echo "\n----------------------------------------------------\n";
        if ($isDryRun) {
            $pdo->rollBack();
            echo "-> Simulasi Selesai. Total $totalBaris pasang baris dibaca.\n";
            echo "-> Jika --apply digunakan:\n";
            echo "   - $updatedCount data AKAN diupdate.\n";
            echo "   - $skippedCount data AKAN dilewati (karena duplikat).\n";
            echo "-> STATUS: Data database TIDAK berubah.\n";
        } else {
            $pdo->commit();
            echo "-> Proses Eksekusi Selesai. Total $totalBaris baris diproses.\n";
            echo "   - $updatedCount pelanggan BERHASIL diperbarui.\n";
            echo "   - $skippedCount pelanggan DILEWATI karena duplikat.\n";
        }
        echo "----------------------------------------------------\n";

    } else {
        die("Error: Tidak dapat membuka salah satu/kedua file ($fileUpdate atau $fileId).\n");
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("\nDatabase Error: " . $e->getMessage() . "\n");
}

?>