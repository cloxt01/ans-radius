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

$jsonFile = 'fix_invoices_april.json';

if (!file_exists($jsonFile)) {
    die("Error: File $jsonFile tidak ditemukan!\n");
}

$jsonData = json_decode(file_get_contents($jsonFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error parsing JSON: " . json_last_error_msg() . "\n");
}

// SET TIMEZONE KE ASIA/JAKARTA
date_default_timezone_set('Asia/Jakarta');

// ==========================================
// 3. PROSES DATABASE
// ==========================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- PREPARE STATEMENTS ---
    // 1. Ambil customer_id berdasarkan pppoe_username
    $stmtGetCustomer = $pdo->prepare("SELECT id FROM customers WHERE pppoe_username = :username LIMIT 1");

    // 2. Cek invoice berdasarkan customer_id, bulan, dan tahun dari due_date
    $stmtCheckInvoice = $pdo->prepare("SELECT id, invoice_number FROM invoices WHERE customer_id = :customer_id AND MONTH(due_date) = :month AND YEAR(due_date) = :year LIMIT 1");

    // 3. Update invoice yang sudah ada (TANPA mengubah invoice_number)
    $stmtUpdate = $pdo->prepare("UPDATE invoices SET amount = :amount, status = 'paid', paid_at = :paid_at, payment_method = :payment_method, due_date = :due_date WHERE id = :id");

    // 4. Insert invoice baru
    $stmtInsert = $pdo->prepare("INSERT INTO invoices (invoice_number, customer_id, amount, status, due_date, paid_at, payment_method, created_at) VALUES (:invoice_number, :customer_id, :amount, 'paid', :due_date, :paid_at, :payment_method, :created_at)");

    $pdo->beginTransaction();

    $insertCount  = 0;
    $updateCount  = 0;
    $skippedCount = 0;

    foreach ($jsonData as $row) {
        // Ambil Username dari JSON untuk Mapping
        $jsonUsername  = trim($row['username']);

        $amount        = $row['price_sell'];
        $paymentMethod = $row['via'] ?? 'CASH';

        // Konversi Timestamp Unix langsung ke Format
        $dueDateUnix = intval($row['expired_at']);
        $paidAtUnix  = intval($row['created_at']);

        // Format Date string: 20260820
        $dueDate   = date('Ymd', $dueDateUnix);
        $paidAt    = date('Y-m-d H:i:s', $paidAtUnix);
        $createdAt = date('Y-m-d H:i:s', $paidAtUnix);

        $dueMonth = date('m', $dueDateUnix);
        $dueYear  = date('Y', $dueDateUnix);

        // --- STEP A: MAPPING USERNAME KE CUSTOMER ID ---
        $stmtGetCustomer->execute([':username' => $jsonUsername]);
        $customer = $stmtGetCustomer->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            echo "   [SKIP] Customer tidak ditemukan di DB untuk username: $jsonUsername\n";
            $skippedCount++;
            continue; // Lewati jika username tidak ada di tabel customers
        }

        $customerId = $customer['id']; // Ini adalah ID asli dari database

        // --- GENERATE INVOICE NUMBER BARU ---
        // Format: INV-{due_date}.{customer_id}.{8 digit acak}
        $random8Digits = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $newInvoiceNum = "INV-{$dueDate}.{$customerId}.{$random8Digits}";

        // --- STEP B: SINKRONISASI INVOICES ---
        $stmtCheckInvoice->execute([
            ':customer_id' => $customerId,
            ':month'       => $dueMonth,
            ':year'        => $dueYear
        ]);
        $existingInvoice = $stmtCheckInvoice->fetch(PDO::FETCH_ASSOC);

        if ($existingInvoice) {
            // Jika Sudah Ada -> Lakukan UPDATE (tanpa menyentuh invoice_number)
            $stmtUpdate->execute([
                ':amount'         => $amount,
                ':paid_at'        => $paidAt,
                ':payment_method' => $paymentMethod,
                ':due_date'       => $dueDate,
                ':id'             => $existingInvoice['id']
            ]);

            echo "[UPDATE] User: $jsonUsername (ID: $customerId) | Periode: $dueYear-$dueMonth | Invoice: {$existingInvoice['invoice_number']} | Set status -> paid\n";
            $updateCount++;

        } else {
            // Jika Belum Ada -> Lakukan INSERT dengan Invoice Number format baru
            $stmtInsert->execute([
                ':invoice_number' => $newInvoiceNum,
                ':customer_id'    => $customerId,
                ':amount'         => $amount,
                ':due_date'       => $dueDate,
                ':paid_at'        => $paidAt,
                ':payment_method' => $paymentMethod,
                ':created_at'     => $createdAt
            ]);

            echo "[INSERT] User: $jsonUsername (ID: $customerId) | Periode: $dueYear-$dueMonth | Buat Inv Baru: $newInvoiceNum\n";
            $insertCount++;
        }
    }

    // ==========================================
    // 4. COMMIT ATAU ROLLBACK BERDASARKAN MODE
    // ==========================================
    echo "\n----------------------------------------------------\n";
    if ($isDryRun) {
        $pdo->rollBack();
        echo "-> Simulasi Selesai.\n";
        echo "-> Jika --apply digunakan:\n";
        echo "   - $updateCount invoice AKAN diupdate.\n";
        echo "   - $insertCount invoice AKAN ditambahkan dengan format baru.\n";
        echo "   - $skippedCount data dari JSON DILEWATI (Username tidak ditemukan di DB).\n";
        echo "-> STATUS: Data database TIDAK berubah.\n";
    } else {
        $pdo->commit();
        echo "-> Proses Eksekusi Selesai.\n";
        echo "   - $updateCount invoice BERHASIL diupdate menjadi paid.\n";
        echo "   - $insertCount invoice BERHASIL ditambahkan sebagai paid.\n";
        echo "   - $skippedCount data DILEWATI (Username tidak ditemukan).\n";
    }
    echo "----------------------------------------------------\n";

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("\nDatabase Error: " . $e->getMessage() . "\n");
}

?>