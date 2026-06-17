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

// --- BACA ID DARI FILE fix_id.csv ---
if (!file_exists($fixIdFile)) {
    die("Error: File $fixIdFile tidak ditemukan!\n");
}

$rows = file($fixIdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
array_shift($rows); // Hapus header baris pertama ("id")

$cleanIds = array_map(function($line) {
    return intval(str_replace('"', '', trim($line)));
}, $rows);

$allowedIds = implode(',', array_filter($cleanIds));

if (empty($allowedIds)) {
    die("Error: File $fixIdFile kosong atau tidak berisi ID yang valid!\n");
}

// --- BACA CSV DATA ---
if (!file_exists($fixCsvFile)) {
    die("Error: File $fixCsvFile tidak ditemukan!\n");
}

// Mapping rule sesuai instruksi
$statusMap = [
    'ONLINE'        => 'active',
    'WAITING LOGIN' => 'active',
    'OFFLINE'       => 'active',
    'EXPIRED'       => 'isolated'
];

// ==========================================
// 3. PROSES DATABASE
// ==========================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (($handle = fopen($fixCsvFile, "r")) !== FALSE) {
        $rawHeaders = fgetcsv($handle);

        // Bersihkan SEMUA header dari BOM, tanda kutip ganda, dan spasi tersembunyi
        $headers = array_map(function($val) {
            $val = preg_replace('/[\xef\xbb\xbf]/', '', $val); // Hapus BOM
            return trim($val, " \t\n\r\0\x0B\"");             // Hapus spasi dan tanda kutip (")
        }, $rawHeaders);

        // Cari index dengan normal
        $usernameIdx      = array_search('username', $headers);
        $voucherStatusIdx = array_search('voucher_status', $headers);

        if ($usernameIdx === false || $voucherStatusIdx === false) {
            echo "Header yang sudah dibersihkan: " . implode(", ", $headers) . "\n";
            die("Error: Kolom 'username' atau 'voucher_status' tetap tidak ditemukan di file $fixCsvFile\n");
        }

        // --- PREPARE STATEMENTS ---
        // Penambahan filter: AND id IN ($allowedIds)
        $stmtGetCustomer  = $pdo->prepare("SELECT id, status FROM customers WHERE pppoe_username = :username AND id IN ($allowedIds) LIMIT 1");
        $stmtUpdateStatus = $pdo->prepare("UPDATE customers SET status = :status WHERE id = :id");

        $pdo->beginTransaction();

        $updateCount  = 0;
        $skippedCount = 0;

        while (($row = fgetcsv($handle)) !== FALSE) {
            $csvUsername   = trim($row[$usernameIdx] ?? '');
            $rawStatus     = strtoupper(trim($row[$voucherStatusIdx] ?? ''));

            if (!empty($csvUsername) && !empty($rawStatus)) {

                $newStatus = $statusMap[$rawStatus] ?? null;

                if (!$newStatus) {
                    continue;
                }

                $stmtGetCustomer->execute([':username' => $csvUsername]);
                $customer = $stmtGetCustomer->fetch(PDO::FETCH_ASSOC);

                if ($customer) {
                    $customerId = $customer['id'];
                    $oldStatus  = $customer['status'];

                    if ($oldStatus !== $newStatus) {
                        echo "[UPDATE STATUS] User: $csvUsername | Sebelum: $oldStatus -> Sesudah: $newStatus\n";

                        $stmtUpdateStatus->execute([
                            ':status' => $newStatus,
                            ':id'     => $customerId
                        ]);

                        $updateCount++;
                    } else {
                        $skippedCount++;
                    }
                }
            }
        }
        fclose($handle);

        // ==========================================
        // 4. COMMIT ATAU ROLLBACK BERDASARKAN MODE
        // ==========================================
        echo "\n----------------------------------------------------\n";
        if ($isDryRun) {
            $pdo->rollBack();
            echo "-> Simulasi Selesai.\n";
            echo "-> Jika --apply digunakan: $updateCount status pelanggan dalam fix_id.csv AKAN diupdate.\n";
            echo "-> Terdapat $skippedCount pelanggan dilewati karena statusnya sudah sesuai.\n";
            echo "-> STATUS: Data database TIDAK berubah.\n";
        } else {
            $pdo->commit();
            echo "-> Proses Eksekusi Selesai.\n";
            echo "-> TOTAL $updateCount pelanggan dalam fix_id.csv BERHASIL diupdate statusnya.\n";
            echo "-> TOTAL $skippedCount pelanggan dilewati (status sudah sama).\n";
        }
        echo "----------------------------------------------------\n";

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