<?php
include '../includes/config.php';

// Cek apakah ada argumen --apply
$apply = in_array('--apply', $argv);

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== MODE " . ($apply ? "APPLY" : "DRY-RUN (SIMULASI)") . " ===\n\n";

    // Mencari data yang perlu diperbaiki
    $sqlSelect = "SELECT username, value FROM radcheck WHERE attribute = 'Cleartext-Password' AND value != '1234'";
    $stmt = $pdo->query($sqlSelect);
    $results = $stmt->fetchAll();

    if (count($results) === 0) {
        die("✓ Semua password sudah '1234'. Tidak ada yang perlu diperbaiki.\n");
    }

    echo "Ditemukan " . count($results) . " akun yang password-nya bukan '1234':\n\n";

    if ($apply) {
        $pdo->beginTransaction();
        try {
            $stmtUpdate = $pdo->prepare("UPDATE radcheck SET value = '1234' WHERE attribute = 'Cleartext-Password' AND value != '1234'");
            $stmtUpdate->execute();
            $pdo->commit();
            echo "✓ SUKSES: Semua password telah diperbarui menjadi '1234'.\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "❌ GAGAL: Terjadi error saat update: " . $e->getMessage() . "\n";
        }
    } else {
        // Mode Simulasi
        printf("%-20s | %-15s\n", "Username", "Password Lama");
        echo str_repeat("-", 40) . "\n";
        foreach ($results as $row) {
            printf("%-20s | %-15s\n", $row['username'], $row['value']);
        }
        echo "\n⚠️ Mode Dry-run (Simulasi). Tidak ada data yang diubah.\n";
        echo "Gunakan perintah 'php fix_password.php --apply' untuk eksekusi.\n";
    }

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage();
}