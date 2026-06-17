<?php
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

$host = DB_HOST;
$user = DB_USER; // sesuaikan dengan user Anda
$password = DB_PASS; // sesuaikan
$database = DB_NAME; // sesuaikan

// Koneksi ke database
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Nama file CSV
$csvFile = 'fiktif_id.csv';

// Baca file CSV
if (!file_exists($csvFile)) {
    die("File $csvFile tidak ditemukan.");
}

$ids = [];
if (($handle = fopen($csvFile, 'r')) !== false) {
    // Lewati baris header
    fgetcsv($handle, 1000, ',', '"');

    // Baca setiap baris
    while (($data = fgetcsv($handle, 1000, ',', '"')) !== false) {
        // Data[0] adalah id
        if (isset($data[0]) && is_numeric($data[0])) {
            $ids[] = (int)$data[0];
        }
    }
    fclose($handle);
}

if (empty($ids)) {
    die("Tidak ada ID yang ditemukan di CSV.");
}

echo "Ditemukan " . count($ids) . " ID untuk diproses.\n\n";

// Siapkan prepared statements
$checkInvoice = $conn->prepare("SELECT 1 FROM invoices WHERE customer_id = ? LIMIT 1");
$updateIsolation = $conn->prepare("UPDATE customers SET isolation_date = '2026-04-01' WHERE id = ?");

// Mulai transaksi
$conn->begin_transaction();

$updated = 0;
$skipped = 0;
$errors = [];

foreach ($ids as $id) {
    // Cek apakah ada invoice
    $checkInvoice->bind_param('i', $id);
    $checkInvoice->execute();
    $result = $checkInvoice->get_result();
    $hasInvoice = ($result->num_rows > 0);
    $result->free();

    if ($hasInvoice) {
        echo "ID $id memiliki invoice, dilewati.\n";
        $skipped++;
        continue;
    }

    // Eksekusi update (Akan dibatalkan di akhir jika dalam mode Dry Run)
    $updateIsolation->bind_param('i', $id);
    if ($updateIsolation->execute()) {
        if ($conn->affected_rows > 0) {
            if ($isDryRun) {
                echo "[SIMULASI] ID $id AKAN diupdate menjadi '2026-04-01'.\n";
            } else {
                echo "[UPDATE] ID $id berhasil diupdate.\n";
            }
            $updated++;
        } else {
            echo "ID $id tidak ditemukan atau sudah memiliki tanggal yang sama.\n";
        }
    } else {
        $errors[] = "Gagal update ID $id: " . $conn->error;
    }
}

// ==========================================
// 4. COMMIT ATAU ROLLBACK BERDASARKAN MODE
// ==========================================
echo "\n----------------------------------------------------\n";
if (!empty($errors)) {
    $conn->rollback();
    echo "Terjadi error, transaksi dirollback:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
} else {
    if ($isDryRun) {
        $conn->rollback(); // Membatalkan semua perubahan agar database tetap aman
        echo "-> Simulasi Selesai.\n";
        echo "-> Jika --apply digunakan:\n";
        echo "   - $updated data pelanggan AKAN diupdate tanggal isolasinya.\n";
        echo "   - $skipped data pelanggan dilewati karena memiliki invoice.\n";
        echo "-> STATUS: Data database TIDAK berubah.\n";
    } else {
        $conn->commit(); // Menyimpan perubahan secara permanen
        echo "-> Proses Eksekusi Selesai.\n";
        echo "   - TOTAL $updated pelanggan berhasil diupdate.\n";
        echo "   - TOTAL $skipped pelanggan dilewati.\n";
    }
}
echo "----------------------------------------------------\n";

// Tutup koneksi
$checkInvoice->close();
$updateIsolation->close();
$conn->close();
?>