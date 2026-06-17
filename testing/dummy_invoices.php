<?php

include '../include/config.php';
$host = DB_HOST;
$db   = DB_NAME; 
$user = DB_USER;
$pass = DB_PASS;     
$charset = 'utf8mb4';

define('INVOICE_PREFIX', 'INV'); 
set_time_limit(0); 

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

function generateInvoiceNumberCustom($customDate, $customerId) {
    $timestamp = date('YmdHis', strtotime($customDate));
    $paddedCustomerId = str_pad($customerId, 5, '0', STR_PAD_LEFT);
    $random = str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);
    return INVOICE_PREFIX . '-' . $timestamp . $paddedCustomerId . $random;
}

function checkInvoiceExists($pdo, $customerId, $yearMonth) {
    $stmt = $pdo->prepare("
        SELECT id 
        FROM invoices 
        WHERE customer_id = ? 
        AND DATE_FORMAT(due_date, '%Y-%m') = ?
        LIMIT 1
    ");
    $stmt->execute([$customerId, $yearMonth]);
    return $stmt->fetch() !== false;
}

function updateIsolationDate($pdo, $customerId) {
    $stmt = $pdo->prepare("
        SELECT status, paid_at, due_date 
        FROM invoices 
        WHERE customer_id = ? 
        ORDER BY due_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$customerId]);
    $lastInvoice = $stmt->fetch();
    
    if ($lastInvoice) {
        if ($lastInvoice['status'] === 'paid' && !empty($lastInvoice['paid_at'])) {
            $paidDate = new DateTime($lastInvoice['paid_at']);
            $paidDate->modify('+30 days');
            $newIsolationDate = $paidDate->format('Y-m-d');
        } else {
            $newIsolationDate = $lastInvoice['due_date'];
        }
        
        $stmtUpdate = $pdo->prepare("UPDATE customers SET status = 'active', isolation_date = ? WHERE id = ?");
        $stmtUpdate->execute([$newIsolationDate, $customerId]);
        return $newIsolationDate;
    }
    return null;
}
function updateStatus($pdo, $customerId): bool{
    $stmt = $pdo->prepare("
    UPDATE customers SET status = 'active' WHERE id = ?");
    $success = $stmt->execute([$customerId]);
    return $success ? true : false;
}

function getInvoiceStatus($month = null) {
    $chance = rand(1, 100);
    
    $config = [
        4 => ['on_time' => 80, 'late' => 44.6],
        5 => ['on_time' => 84, 'late' => 46.2],   // Mei: 88% on time, 10% late, 2% unpaid
    ];
    
    $default = ['on_time' => 88, 'late' => 95];
    $cfg = $config[$month] ?? $default;
    
    if ($chance <= $cfg['on_time']) {
        return ['status' => 'paid', 'late_days' => 0];
    } elseif ($chance <= $cfg['late']) {
        return ['status' => 'paid', 'late_days' => rand(1, 7)];
    } else {
        return ['status' => 'unpaid', 'late_days' => null];
    }
}

function generateDynamicRadiusInvoices($pdo) {
    try {
        $pdo->beginTransaction();
        echo "Tahap 1: Mengambil customer dengan invoice Juni...\n";
        $stmtJuniCustomers = $pdo->query(
            "SELECT DISTINCT customer_id 
            FROM invoices 
            WHERE DATE_FORMAT(due_date, '%Y-%m') = '2026-06'
            ORDER BY customer_id"
        );
        $juniCustomers = $stmtJuniCustomers->fetchAll(PDO::FETCH_COLUMN);
        $totalJuniCustomers = count($juniCustomers);
        echo "✓ Ditemukan {$totalJuniCustomers} customer dengan invoice Juni (100%)\n\n";

        // =========================================================================
        // SETTING TARGET PELANGGAN BARU OTOMATIS (50 sampai 80 pelanggan)
        // =========================================================================
        $targetBaruMei = rand(50, 80);
        $targetBaruApril = rand(50, 80);

        // Rumus mengubah target jumlah orang menjadi persentase probabilitas
        $meiPercentage = (($totalJuniCustomers - $targetBaruMei) / $totalJuniCustomers) * 100;
        $aprilPercentage = (($totalJuniCustomers - $targetBaruApril) / $totalJuniCustomers) * 100;
        echo "✓ Ditemukan {$totalJuniCustomers} customer dengan invoice Juni (100%)\n\n";

        // ========== Tentukan amount per customer secara acak (konsisten untuk April & Mei) ==========
        $customerAmounts = [];
        $amountOptions = [100000, 125000, 150000];
        foreach ($juniCustomers as $cid) {
            $customerAmounts[$cid] = $amountOptions[array_rand($amountOptions)];
        }
        echo "✓ Amount per customer telah ditentukan (100k,125k,150k) secara acak.\n\n";

        $stmtFiktif = $pdo->query("SELECT customer_id FROM fiktif_customers");
        $fiktifIds = $stmtFiktif->fetchAll(PDO::FETCH_COLUMN);
        echo "✓ Ditemukan " . count($fiktifIds) . " customer fiktif\n\n";

        // Query INSERT tanpa created_at dan updated_at (biarkan default database)
        $sqlInsert = "INSERT INTO invoices (invoice_number, customer_id, amount, status, due_date, paid_at, payment_method) 
                      VALUES (:invoice_number, :customer_id, :amount, :status, :due_date, :paid_at, :payment_method)";
        $stmtInsert = $pdo->prepare($sqlInsert);

        // ==================== GENERATE MEI ====================
        echo "Tahap 2: Generate invoice Mei ({$meiPercentage}% dari customer Juni)...\n";
        $meiGenerated = 0;
        $meiBaruJuni = 0;

        foreach ($juniCustomers as $customerId) {
            if (checkInvoiceExists($pdo, $customerId, '2026-05')) continue;
            if ((rand(1, 1000) / 10) > $meiPercentage) { $meiBaruJuni++; continue; }

            $stmtJuni = $pdo->prepare("SELECT due_date FROM invoices WHERE customer_id = ? AND DATE_FORMAT(due_date, '%Y-%m') = '2026-06' LIMIT 1");
            $stmtJuni->execute([$customerId]);
            $juneInvoice = $stmtJuni->fetch();
            if (!$juneInvoice) continue;

            $juneDueDate = new DateTime($juneInvoice['due_date']);
            $paidMeiObj = clone $juneDueDate;
            $paidMeiObj->modify('-30 days');
            
            $statusInfo = getInvoiceStatus(5);
            $status = $statusInfo['status'];
            $lateDays = $statusInfo['late_days'];
            
            $dueMeiObj = clone $paidMeiObj;
            if ($lateDays !== null) $dueMeiObj->modify("-{$lateDays} days");
            
            if ((int)$dueMeiObj->format('m') != 5) {
                $dueMeiObj->setDate(2026, 5, rand(10, 25));
            }
            
            $paidMeiFinal = null;
            if ($status === 'paid') {
                $paidMeiFinal = clone $paidMeiObj;
                $paidMeiFinal->setTime(rand(8,20), rand(0,59), rand(0,59));
            }
            
            $stmtInsert->execute([
                ':invoice_number' => generateInvoiceNumberCustom($dueMeiObj->format('Y-m-d'), $customerId),
                ':customer_id'    => $customerId,
                ':amount'         => $customerAmounts[$customerId],
                ':status'         => $status,
                ':due_date'       => $dueMeiObj->format('Y-m-d'),
                ':paid_at'        => $paidMeiFinal ? $paidMeiFinal->format('Y-m-d H:i:s') : null,
                ':payment_method' => null,
            ]);
            $meiGenerated++;
        }
        echo "✓ Selesai generate Mei: {$meiGenerated} invoice (customer baru Juni: {$meiBaruJuni})\n\n";

        // ==================== GENERATE APRIL ====================
        echo "Tahap 3: Generate invoice April ({$aprilPercentage}% dari customer Juni)...\n";
        $aprilGenerated = 0;
        $pelangganBaruMei = 0;

        foreach ($juniCustomers as $customerId) {
            if (checkInvoiceExists($pdo, $customerId, '2026-04')) continue;
            if ((rand(1, 1000) / 10) > $aprilPercentage) { $pelangganBaruMei++; continue; }

            $stmtMei = $pdo->prepare("SELECT due_date FROM invoices WHERE customer_id = ? AND DATE_FORMAT(due_date, '%Y-%m') = '2026-05' LIMIT 1");
            $stmtMei->execute([$customerId]);
            $meiInvoice = $stmtMei->fetch();
            if (!$meiInvoice) continue;

            $meiDueDate = new DateTime($meiInvoice['due_date']);
            $paidAprilObj = clone $meiDueDate;
            $paidAprilObj->modify('-30 days');
            
            $statusInfo = getInvoiceStatus(4);
            $status = $statusInfo['status'];
            $lateDays = $statusInfo['late_days'];
            
            $dueAprilObj = clone $paidAprilObj;
            if ($lateDays !== null) $dueAprilObj->modify("-{$lateDays} days");
            
            if ((int)$dueAprilObj->format('m') != 4) {
                $dueAprilObj->setDate(2026, 4, rand(10, 25));
            }
            
            $paidAprilFinal = null;
            if ($status === 'paid') {
                $paidAprilFinal = clone $paidAprilObj;
                $paidAprilFinal->setTime(rand(8,20), rand(0,59), rand(0,59));
            }
            
            $stmtInsert->execute([
                ':invoice_number' => generateInvoiceNumberCustom($dueAprilObj->format('Y-m-d'), $customerId),
                ':customer_id'    => $customerId,
                ':amount'         => $customerAmounts[$customerId],
                ':status'         => $status,
                ':due_date'       => $dueAprilObj->format('Y-m-d'),
                ':paid_at'        => $paidAprilFinal ? $paidAprilFinal->format('Y-m-d H:i:s') : null,
                ':payment_method' => $status === 'paid' ? 'manual_admin' : null,
            ]);
            $aprilGenerated++;
        }
        echo "✓ Selesai generate April: {$aprilGenerated} invoice (customer baru Mei: {$pelangganBaruMei})\n\n";

        // ==================== UPDATE ISOLATION DATE FIKTIF ====================
        echo "Tahap 4: Update status & isolation_date untuk customer fiktif...\n";
        $updatedCount = 0;
        foreach ($fiktifIds as $customerId) {
            if (updateIsolationDate($pdo, $customerId) && updateStatus($pdo, $customerId)) $updatedCount++;
        }
        echo "✓ Selesai update isolation_date untuk {$updatedCount} customer fiktif\n\n";

        // ==================== VERIFIKASI ====================
        echo "Tahap 5: Verifikasi hasil...\n";
        $stmtVerifikasi = $pdo->query("
            SELECT 
                DATE_FORMAT(due_date, '%Y-%m') AS bulan_invoice,
                COUNT(*) AS total_invoice,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) AS unpaid,
                ROUND(COUNT(*) / (SELECT COUNT(*) FROM invoices WHERE DATE_FORMAT(due_date, '%Y-%m') = '2026-06') * 100, 2) AS persentase_dari_juni,
                SUM(amount) AS total_amount
            FROM invoices
            WHERE due_date >= '2026-04-01' AND due_date <= '2026-06-30'
            GROUP BY bulan_invoice
            ORDER BY bulan_invoice ASC
        ");
        
        echo "\n=== HASIL INVOICE ===\n";
        while ($row = $stmtVerifikasi->fetch()) {
            $persenPaid = $row['total_invoice'] > 0 ? round(($row['paid'] / $row['total_invoice']) * 100, 2) : 0;
            echo "{$row['bulan_invoice']}: {$row['total_invoice']} invoice ({$row['persentase_dari_juni']}% dari Juni) (Paid: {$row['paid']} - {$persenPaid}%, Unpaid: {$row['unpaid']}) Total: Rp " . number_format($row['total_amount'], 0, ',', '.') . "\n";
        }

        echo "\n--- Logika Relasi Invoice ---\n";
        echo "paid_at Mei   = due_date Juni - 30 hari\n";
        echo "due_date Mei  = paid_at Mei - (late_days 0-7)\n";
        echo "paid_at April = due_date Mei - 30 hari\n";
        echo "due_date April = paid_at April - (late_days 0-7)\n";
        echo "Amount per customer dipilih acak (100.000, 125.000, atau 150.000) dan konsisten untuk semua bulan.\n";

        $pdo->commit();
        echo "\n=== SELESAI SUKSES ===\n";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "❌ GAGAL: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
}

// Hapus data lama
echo "⚠️  Peringatan: Script akan menghapus invoice April dan Mei yang sudah ada?\n";
echo "Ketik 'yes' untuk lanjut: ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
if ($line !== 'yes') exit("Script dihentikan.\n");

echo "Menghapus invoice April dan Mei yang sudah ada...\n";

$pdo->exec("DELETE FROM invoices WHERE DATE_FORMAT(due_date, '%Y-%m') IN ('2026-04', '2026-05') AND customer_id IN (SELECT customer_id FROM fiktif_customers)");
echo "Data lama telah dihapus.\n\n";

generateDynamicRadiusInvoices($pdo);
?>

