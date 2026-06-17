<?php

include '../includes/config.php';
$host = DB_HOST;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage() . "\n");
}

echo "Memulai proses pembersihan duplikat di fiktif_customers...\n\n";

try {
    // 1. Ambil jumlah total data saat ini
    $totalLama = $pdo->query("SELECT COUNT(*) FROM fiktif_customers")->fetchColumn();
    echo "Total data saat ini: {$totalLama} baris.\n";

    // 2. Ambil hanya customer_id yang UNIK (tidak kembar)
    $stmt = $pdo->query("SELECT DISTINCT customer_id FROM fiktif_customers");
    $uniqueCustomers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $totalBaru = count($uniqueCustomers);

    if ($totalLama == $totalBaru) {
        echo "Tidak ditemukan data duplikat. Tabel sudah bersih!\n";
    } else {
        $jumlahDuplikat = $totalLama - $totalBaru;
        echo "Ditemukan {$jumlahDuplikat} data duplikat. Memulai pembersihan...\n";

        // 3. Kosongkan tabel secara paksa (reset)
        $pdo->exec("TRUNCATE TABLE fiktif_customers");

        // 4. Masukkan kembali data yang sudah bersih
        $insert = $pdo->prepare("INSERT INTO fiktif_customers (customer_id) VALUES (?)");

        $pdo->beginTransaction();
        foreach ($uniqueCustomers as $cid) {
            $insert->execute([$cid]);
        }
        $pdo->commit();

        echo "✓ Berhasil menghapus {$jumlahDuplikat} data ganda.\n";
        echo "✓ Total data fiktif_customers sekarang menjadi bersih: {$totalBaru} baris.\n";
    }

    // 5. Tambahkan perlindungan UNIQUE KEY agar tidak bisa kembar lagi ke depannya
    try {
        $pdo->exec("ALTER TABLE fiktif_customers ADD UNIQUE KEY uk_customer_id (customer_id)");
        echo "\n✓ [KEAMANAN] Berhasil menambahkan UNIQUE KEY pada kolom customer_id.\n";
        echo "Mulai sekarang, database akan otomatis menolak jika ada ID yang diinput dua kali.\n";
    } catch (PDOException $e) {
        // Error kode 1061 artinya index sudah ada sebelumnya, jadi aman diabaikan
        if ($e->errorInfo[1] == 1061) {
            echo "\n✓ [KEAMANAN] Kolom customer_id sudah memiliki perlindungan UNIQUE KEY.\n";
        } else {
            echo "\n⚠️ Gagal menambahkan UNIQUE KEY: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== PROSES SELESAI ===\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
?>