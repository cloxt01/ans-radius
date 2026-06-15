<?php
/**
 * Script untuk update customer fiktif - WITH RANDOM TIME
 * UNPAID: isolation_date dari besok (CURDATE+1) sampai akhir bulan
 * PAID: due_date < CURDATE()
 * PAID: paid_at > due_date DAN paid_at <= CURDATE()
 * PAID: Jika paid_at = CURDATE, jam <= jam sekarang
 */

// =====================================================
// KONFIGURASI DATABASE
// =====================================================

include '../includes/config.php';
$db_host     = DB_HOST;
$db_name   = DB_NAME;    // Sesuaikan nama database
$db_user = DB_USER;    // Sesuaikan username
$db_pass = DB_PASS;
function logMessage($message) {
    echo date('Y-m-d H:i:s') . " - " . $message . PHP_EOL;
}

// Koneksi database
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    logMessage("Koneksi database berhasil!");
} catch (Exception $e) {
    die("KONEKSI DATABASE GAGAL: " . $e->getMessage() . "\n");
}

logMessage("=== SCRIPT UPDATE CUSTOMER FIKTIF DIMULAI ===");

try {
    // =====================================================
    // BAGIAN 0: Pastikan semua customer fiktif memiliki invoice
    // =====================================================
    logMessage("BAGIAN 0: Memastikan semua customer fiktif memiliki invoice");
    
    $stmt = $pdo->prepare("
        INSERT INTO invoices (customer_id, due_date, status, created_at)
        SELECT 
            c.id,
            DATE_ADD(CURDATE(), INTERVAL 30 DAY) as due_date,
            'unpaid' as status,
            NOW() as created_at
        FROM customers c
        INNER JOIN fiktif_customers fc ON fc.customer_id = c.id
        WHERE c.id NOT IN (SELECT DISTINCT customer_id FROM invoices)
    ");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        logMessage("Invoice baru dibuat: {$stmt->rowCount()} rows");
    } else {
        logMessage("Semua customer fiktif sudah memiliki invoice");
    }
    
    // =====================================================
    // BAGIAN 1: Update 812 Customer Menjadi UNPAID
    // =====================================================
    logMessage("BAGIAN 1: Update 812 customer menjadi UNPAID");
    
    $today = new DateTime();
    $lastDayOfMonth = (int)$today->format('t');
    $currentDay = (int)$today->format('j');
    $minDay = $currentDay + 1;
    $maxDay = $lastDayOfMonth;
    $rangeDays = $maxDay - $minDay + 1;
    
    logMessage("Tanggal sekarang: {$today->format('Y-m-d H:i:s')}");
    logMessage("UNPAID isolation_date akan dibuat antara tanggal {$minDay} - {$maxDay}");
    
    if ($rangeDays <= 0) {
        $minDay = 1;
        $maxDay = $lastDayOfMonth;
        $rangeDays = $maxDay - $minDay + 1;
    }
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM fiktif_customers");
    $totalFiktif = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    logMessage("Total customer fiktif: {$totalFiktif}");
    
    $limit = min(812, $totalFiktif);
    
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_selected_fiktif");
    $pdo->exec("
        CREATE TEMPORARY TABLE temp_selected_fiktif AS
        SELECT customer_id
        FROM fiktif_customers
        ORDER BY RAND()
        LIMIT {$limit}
    ");
    
    // Update invoices jadi unpaid
    $stmt = $pdo->prepare("
        UPDATE invoices i
        INNER JOIN temp_selected_fiktif tf ON tf.customer_id = i.customer_id
        SET i.status = 'unpaid', i.paid_at = NULL
    ");
    $stmt->execute();
    logMessage("Update invoices unpaid: {$stmt->rowCount()} rows affected");
    
    // Update customers dengan random isolation_date
    $stmt = $pdo->prepare("
        UPDATE customers c
        INNER JOIN temp_selected_fiktif tf ON tf.customer_id = c.id
        SET c.isolation_date = DATE_ADD(
            CONCAT(YEAR(CURDATE()), '-', MONTH(CURDATE()), '-', {$minDay}),
            INTERVAL FLOOR(RAND() * {$rangeDays}) DAY
        )
    ");
    $stmt->execute();
    logMessage("Update customers isolation_date: {$stmt->rowCount()} rows affected");
    
    // Update due_date invoices = isolation_date
    $stmt = $pdo->prepare("
        UPDATE invoices i
        INNER JOIN customers c ON c.id = i.customer_id
        INNER JOIN temp_selected_fiktif tf ON tf.customer_id = c.id
        SET i.due_date = c.isolation_date
        WHERE i.status = 'unpaid'
    ");
    $stmt->execute();
    logMessage("Update due_date invoices: {$stmt->rowCount()} rows affected");
    
    // =====================================================
    // BAGIAN 2: Update Sisa Customer Menjadi PAID
    // paid_at > due_date, paid_at <= CURDATE()
    // Jam random TIDAK melebihi jam sekarang jika tanggal = CURDATE
    // =====================================================
    logMessage("BAGIAN 2: Update sisa customer menjadi PAID");
    logMessage("Ketentuan: due_date < CURDATE(), paid_at > due_date, paid_at <= CURDATE()");
    logMessage("Jam random tidak melebihi jam sekarang jika tanggal = CURDATE()");
    
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_sisa_fiktif");
    $pdo->exec("
        CREATE TEMPORARY TABLE temp_sisa_fiktif AS
        SELECT customer_id
        FROM fiktif_customers
        WHERE customer_id NOT IN (SELECT customer_id FROM temp_selected_fiktif)
    ");
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM temp_sisa_fiktif");
    $sisaCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    logMessage("Customer yang akan diupdate PAID: {$sisaCount}");
    
    if ($sisaCount > 0) {
        // Step 1: Set status dan due_date random (1 sampai CURDATE-1)
        $maxDayPaid = $currentDay - 1;
        
        if ($maxDayPaid < 1) {
            logMessage("⚠️ WARNING: Masih tanggal 1, tidak ada due_date yang < CURDATE()");
            $maxDayPaid = 1;
        }
        
        logMessage("Due date PAID akan dibuat antara tanggal 1 - {$maxDayPaid}");
        
        $stmt = $pdo->prepare("
            UPDATE invoices i
            INNER JOIN temp_sisa_fiktif tf ON tf.customer_id = i.customer_id
            SET 
                i.status = 'paid',
                i.due_date = DATE_ADD(
                    CONCAT(YEAR(CURDATE()), '-', MONTH(CURDATE()), '-01'),
                    INTERVAL FLOOR(RAND() * {$maxDayPaid}) DAY
                )
        ");
        $stmt->execute();
        logMessage("Set due_date (< CURDATE()): {$stmt->rowCount()} rows affected");
        
        // Step 2: Set paid_at dengan ketentuan jam tidak melebihi jam sekarang
        $stmt = $pdo->query("
            SELECT i.id, i.due_date
            FROM invoices i
            INNER JOIN temp_sisa_fiktif tf ON tf.customer_id = i.customer_id
            WHERE i.status = 'paid'
        ");
        
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $updated = 0;
        $todayDate = $today->format('Y-m-d');
        $currentHour = (int)$today->format('H');
        $currentMinute = (int)$today->format('i');
        $currentSecond = (int)$today->format('s');
        
        foreach ($invoices as $invoice) {
            $dueDate = $invoice['due_date'];
            $dueDateTime = new DateTime($dueDate);
            $todayDateTime = new DateTime($todayDate);
            
            // Hitung maksimal hari yang bisa ditambahkan
            $maxDaysAllowed = $todayDateTime->diff($dueDateTime)->days;
            
            if ($maxDaysAllowed < 1) {
                $maxDaysAllowed = 1;
            }
            
            // Random days antara 1 sampai maxDaysAllowed
            $randomDays = rand(1, $maxDaysAllowed);
            
            // Hitung tanggal paid_at
            $paidAtDate = date('Y-m-d', strtotime("{$dueDate} +{$randomDays} days"));
            
            // Tentukan jam berdasarkan tanggal
            if ($paidAtDate == $todayDate) {
                // Jika tanggal = hari ini, jam random 0 sampai jam sekarang
                $randomHour = rand(0, $currentHour);
                if ($randomHour == $currentHour) {
                    // Jika jam sama, menit dan detik tidak boleh melebihi
                    $randomMinute = rand(0, $currentMinute);
                    if ($randomMinute == $currentMinute) {
                        $randomSecond = rand(0, $currentSecond);
                    } else {
                        $randomSecond = rand(0, 59);
                    }
                } else {
                    $randomMinute = rand(0, 59);
                    $randomSecond = rand(0, 59);
                }
            } else {
                // Jika tanggal < hari ini, jam random penuh 0-23
                $randomHour = rand(0, 23);
                $randomMinute = rand(0, 59);
                $randomSecond = rand(0, 59);
            }
            
            $paidAt = date('Y-m-d H:i:s', strtotime("{$dueDate} +{$randomDays} days {$randomHour}:{$randomMinute}:{$randomSecond}"));
            
            // Validasi akhir: pastikan paid_at tidak melebihi CURDATE
            $paidAtDateTime = new DateTime($paidAt);
            if ($paidAtDateTime > $today) {
                // Jika masih melebihi, set ke CURDATE dengan jam random yang valid
                $randomHour = rand(0, $currentHour);
                if ($randomHour == $currentHour) {
                    $randomMinute = rand(0, $currentMinute);
                    if ($randomMinute == $currentMinute) {
                        $randomSecond = rand(0, $currentSecond);
                    } else {
                        $randomSecond = rand(0, 59);
                    }
                } else {
                    $randomMinute = rand(0, 59);
                    $randomSecond = rand(0, 59);
                }
                $paidAt = date('Y-m-d H:i:s', strtotime("{$todayDate} {$randomHour}:{$randomMinute}:{$randomSecond}"));
            }
            
            $updateStmt = $pdo->prepare("
                UPDATE invoices 
                SET paid_at = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$paidAt, $invoice['id']]);
            $updated++;
        }
        logMessage("Set paid_at dengan jam random (≤ jam sekarang jika tanggal = CURDATE): {$updated} rows affected");
        
        // Update customers.isolation_date = paid_at + 30 hari
        $stmt = $pdo->prepare("
            UPDATE customers c
            INNER JOIN invoices i ON i.customer_id = c.id
            INNER JOIN temp_sisa_fiktif tf ON tf.customer_id = c.id
            SET c.isolation_date = DATE_ADD(DATE(i.paid_at), INTERVAL 30 DAY)
            WHERE i.status = 'paid'
        ");
        $stmt->execute();
        logMessage("Update isolation_date (paid_at + 30 days): {$stmt->rowCount()} rows affected");
    }
    
    // =====================================================
    // VALIDASI
    // =====================================================
    logMessage("=== VALIDASI ===");
    
    // Validasi UNPAID
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_unpaid,
            SUM(CASE WHEN c.isolation_date > CURDATE() THEN 1 ELSE 0 END) as valid_future
        FROM customers c
        INNER JOIN temp_selected_fiktif tf ON tf.customer_id = c.id
        INNER JOIN invoices i ON i.customer_id = c.id
        WHERE i.status = 'unpaid'
    ");
    $result1 = $stmt->fetch(PDO::FETCH_ASSOC);
    logMessage("UNPAID - Total: {$result1['total_unpaid']}, Valid future: {$result1['valid_future']}");
    
    // Validasi PAID
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_paid,
            SUM(CASE WHEN i.due_date < CURDATE() THEN 1 ELSE 0 END) as valid_due_date,
            SUM(CASE WHEN i.paid_at > i.due_date THEN 1 ELSE 0 END) as paid_after_due,
            SUM(CASE WHEN i.paid_at <= CURDATE() THEN 1 ELSE 0 END) as paid_not_exceed_today,
            SUM(CASE WHEN DATE(i.paid_at) = CURDATE() AND TIME(i.paid_at) <= CURTIME() THEN 1 ELSE 0 END) as valid_time_today
        FROM invoices i
        INNER JOIN temp_sisa_fiktif tf ON tf.customer_id = i.customer_id
        WHERE i.status = 'paid'
    ");
    $result2 = $stmt->fetch(PDO::FETCH_ASSOC);
    logMessage("PAID - Total: {$result2['total_paid']}");
    logMessage("   due_date < CURDATE(): {$result2['valid_due_date']}");
    logMessage("   paid_at > due_date: {$result2['paid_after_due']}");
    logMessage("   paid_at <= CURDATE(): {$result2['paid_not_exceed_today']}");
    logMessage("   Valid time for today: {$result2['valid_time_today']}");
    
    // =====================================================
    // TAMPILKAN HASIL
    // =====================================================
    logMessage("=== HASIL UPDATE ===");
    
    // Sample data UNPAID
    logMessage("=== SAMPLE DATA (UNPAID) ===");
    $stmt = $pdo->query("
        SELECT c.id, c.name, c.isolation_date, i.due_date
        FROM customers c
        INNER JOIN temp_selected_fiktif tf ON tf.customer_id = c.id
        LEFT JOIN invoices i ON i.customer_id = c.id
        WHERE i.status = 'unpaid'
        LIMIT 5
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        logMessage("ID: {$row['id']}, Name: {$row['name']}, Due: {$row['due_date']}");
    }
    
    // Sample data PAID
    if ($sisaCount > 0) {
        logMessage("=== SAMPLE DATA (PAID) ===");
        $stmt = $pdo->query("
            SELECT c.id, c.name, i.due_date, i.paid_at,
                   DATE(i.paid_at) as paid_date,
                   TIME(i.paid_at) as paid_time,
                   CASE 
                       WHEN DATE(i.paid_at) = CURDATE() AND TIME(i.paid_at) <= CURTIME() THEN '✓ VALID TIME'
                       WHEN DATE(i.paid_at) < CURDATE() THEN '✓ VALID (PAST DATE)'
                       ELSE '⚠️ CHECK'
                   END as time_status
            FROM customers c
            INNER JOIN temp_sisa_fiktif tf ON tf.customer_id = c.id
            INNER JOIN invoices i ON i.customer_id = c.id
            LIMIT 5
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            logMessage("ID: {$row['id']}, Name: {$row['name']}");
            logMessage("   Due: {$row['due_date']} → Paid: {$row['paid_at']} {$row['time_status']}");
        }
    }
    
    // =====================================================
    // KONFIRMASI COMMIT
    // =====================================================
    echo "\n";
    echo "============================================\n";
    echo "Apakah data sudah benar? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $confirmation = trim(fgets($handle));
    
    if (strtolower($confirmation) === 'y') {
        $pdo->exec("COMMIT");
        logMessage("COMMIT berhasil dieksekusi!");
    } else {
        $pdo->exec("ROLLBACK");
        logMessage("ROLLBACK dieksekusi. Tidak ada perubahan yang disimpan.");
    }
    
    // Hapus temporary tables
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_selected_fiktif");
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_sisa_fiktif");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    if (isset($pdo)) {
        $pdo->exec("ROLLBACK");
        logMessage("ROLLBACK karena error");
    }
}

logMessage("=== SCRIPT SELESAI ===");
?>