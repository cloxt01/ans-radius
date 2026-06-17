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

$fixIdFile = 'fiktif_id.csv';

// BACA ID DARI FILE fix_id.csv
if (!file_exists($fixIdFile)) {
    die("Error: File $fixIdFile tidak ditemukan!\n");
}

$rows = file($fixIdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
array_shift($rows); // Hapus header baris pertama ("id")

// Bersihkan ID dari file
$cleanIds = array_map(function($line) {
    return intval(str_replace('"', '', trim($line)));
}, $rows);

// Hapus duplikat (jika ada) dan hapus nilai 0
$cleanIds = array_unique(array_filter($cleanIds));

if (empty($cleanIds)) {
    die("Error: File $fixIdFile kosong atau tidak berisi ID yang valid!\n");
}

echo "Total ID tersedia di CSV: " . count($cleanIds) . " ID.\n";

// ==========================================
// 3. ACAK DAN AMBIL 150 ID
// ==========================================
// Acak urutan array
shuffle($cleanIds);

// Ambil maksimal 150 ID (jika ID < 150, ia akan mengambil semuanya)
$limit = 9000;
$selectedIds = array_slice($cleanIds, 0, $limit);

echo "Mengambil " . count($selectedIds) . " ID secara acak...\n\n";

// ==========================================
// 4. PROSES DATABASE
// ==========================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmtInsert = $pdo->prepare("
        INSERT INTO fiktif_customers (customer_id) 
        VALUES (:customer_id) 
        ON DUPLICATE KEY UPDATE customer_id = VALUES(customer_id)
    ");

    $pdo->beginTransaction();
    $insertCount = 0;

    foreach ($selectedIds as $id) {
        $stmtInsert->execute([':customer_id' => $id]);

        if ($isDryRun) {
            echo "[SIMULASI] Customer ID: $id AKAN dimasukkan ke fiktif_customers.\n";
        } else {
            echo "[INSERT] Customer ID: $id BERHASIL dimasukkan ke fiktif_customers.\n";
        }

        $insertCount++;
    }

    // ==========================================
    // 5. COMMIT ATAU ROLLBACK BERDASARKAN MODE
    // ==========================================
    echo "\n----------------------------------------------------\n";
    if ($isDryRun) {
        $pdo->rollBack();
        echo "-> Simulasi Selesai.\n";
        echo "-> Jika --apply digunakan: $insertCount ID acak AKAN ditambahkan ke tabel fiktif_customers.\n";
        echo "-> STATUS: Data database TIDAK berubah.\n";
    } else {
        $pdo->commit();
        echo "-> Proses Eksekusi Selesai.\n";
        echo "-> TOTAL $insertCount ID acak BERHASIL ditambahkan ke tabel fiktif_customers.\n";
    }
    echo "----------------------------------------------------\n";

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("\nDatabase Error: " . $e->getMessage() . "\n");
}

?>