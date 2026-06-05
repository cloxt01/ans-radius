#!/usr/bin/php
<?php
/**
 * Cron Job Scheduler
 * Run this script every minute via server cron
 * Usage: * * * * * /usr/bin/php /path/to/gembok-simple/cron/scheduler.php
 */

// Load dependencies
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Use the configured application timezone for cron calculations.
// This keeps PHP date() and MySQL NOW() aligned with cron_schedules.schedule_time.
$schedulerTimezone = trim((string) getSetting('timezone', 'Asia/Jakarta'));
if ($schedulerTimezone === '') {
    $schedulerTimezone = 'Asia/Jakarta';
}
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set($schedulerTimezone);
} 

// CLI Check - Only run if called directly from CLI
if (php_sapi_name() === 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    runScheduler();
} else if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    die("This script can only be run from CLI or authorized web runner");
}

/**
 * Main function to run the scheduler
 */
function runScheduler() {
    global $schedulerTimezone;

    echo "[" . date('Y-m-d H:i:s') . "] Cron Scheduler started\n";

    try {
        $pdo = getDB();
        applySchedulerDatabaseTimezone($pdo, $schedulerTimezone);

        // Get all active schedules
        $schedules = fetchAll("
            SELECT * FROM cron_schedules 
            WHERE is_active = 1 
            AND (next_run IS NULL OR next_run <= NOW())
            ORDER BY next_run ASC
        ");

        if (empty($schedules)) {
            echo "No active schedules to run.\n";
            return;
        }

        echo "Found " . count($schedules) . " schedule(s) to run.\n";

        foreach ($schedules as $schedule) {
            echo "\n--- Running schedule: {$schedule['name']} ---\n";

            $startTime = microtime(true);
            $status = 'started';

            try {
                switch ($schedule['task_type']) {
                    case 'auto_invoice':
                        runAutoInvoice($pdo);
                        break;
                    case 'auto_isolir':
                        runAutoIsolir($pdo);
                        break;
                    case 'fiktif_customers':
                        runFiktifCustomers($pdo);
                        break;
                    case 'backup_db':
                        runBackupDb();
                        break;
                    case 'send_reminders':
                        sendReminders($pdo);
                        break;

                    case 'custom_script':
                        runCustomScript($pdo, $schedule);
                        break;

                    default:
                        echo "Unknown task type: {$schedule['task_type']}\n";
                        $status = 'failed';
                }

                $status = 'success';

            } catch (Exception $e) {
                echo "Error: " . $e->getMessage() . "\n";
                $status = 'failed';
            }

            $executionTime = round(microtime(true) - $startTime, 2);

            // Update schedule
            update('cron_schedules', [
                'last_run' => date('Y-m-d H:i:s'),
                'last_status' => $status,
                'next_run' => calculateNextRun($schedule)
            ], 'id = ?', [$schedule['id']]);

            // Log execution
            $pdo->prepare("INSERT INTO cron_logs (schedule_id, status, execution_time, created_at) VALUES (?, ?, ?, NOW())")
                ->execute([$schedule['id'], $status, $executionTime]);

            echo "Status: {$status}\n";
            echo "Execution time: {$executionTime}s\n";
        }

        echo "\n[" . date('Y-m-d H:i:s') . "] Cron Scheduler completed\n";

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        return;
    }
}

/**
 * Calculate next run time based on schedule
 */
function calculateNextRun($schedule)
{
    $scheduleTime = explode(':', $schedule['schedule_time']);
    $hour = (int) $scheduleTime[0];
    $minute = (int) $scheduleTime[1];

    $scheduleDays = $schedule['schedule_days'];

    // Calculate next run date
    $nextRun = date('Y-m-d') . ' ' . sprintf('%02d:%02d:00', $hour, $minute);

    // If today's time has passed, move to next valid day
    if (strtotime($nextRun) < time()) {
        $nextRun = date('Y-m-d', strtotime('+1 day')) . ' ' . sprintf('%02d:%02d:00', $hour, $minute);

        // Find the next valid day
        $daysMap = [
            'daily' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'weekly' => [$scheduleDays],
            'monthly' => null
        ];

        if ($scheduleDays === 'daily') {
            // Already handled above
        } elseif (in_array($scheduleDays, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])) {
            // Specific day of week
            $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $targetDay = array_search($scheduleDays, $dayNames);

            while (date('w', strtotime($nextRun)) != $targetDay) {
                $nextRun = date('Y-m-d', strtotime('+1 day', strtotime($nextRun))) . ' ' . sprintf('%02d:%02d:00', $hour, $minute);
            }
        }
    }

    return date('Y-m-d H:i:s', strtotime($nextRun));
}

/**
 * Set the MySQL session timezone so NOW() matches the app timezone.
 */
function applySchedulerDatabaseTimezone($pdo, $timezoneName)
{
    if (!$pdo || !($pdo instanceof PDO)) {
        return;
    }

    $timezoneName = trim((string) $timezoneName);
    if ($timezoneName === '') {
        return;
    }

    try {
        $timezone = new DateTimeZone($timezoneName);
        $now = new DateTimeImmutable('now', $timezone);
        $offsetSeconds = $timezone->getOffset($now);
        $sign = $offsetSeconds >= 0 ? '+' : '-';
        $offsetSeconds = abs($offsetSeconds);
        $hours = floor($offsetSeconds / 3600);
        $minutes = floor(($offsetSeconds % 3600) / 60);
        $mysqlTimezone = sprintf('%s%02d:%02d', $sign, $hours, $minutes);
        $pdo->exec("SET time_zone = " . $pdo->quote($mysqlTimezone));
    } catch (Exception $e) {
        // If the timezone cannot be applied, continue with the process timezone.
    }
}


/**
 * Run auto-isolir based on customers.isolation_date
 * (bukan berdasarkan due_date invoice)
 */
function runAutoIsolir($pdo)
{
    echo "Running auto-isolir based on isolation_date...\n";

    // Pastikan kolom isolation_date ada
    $hasAutoIsolate = ensureCustomersAutoIsolateColumn();
    $autoIsolateClause = $hasAutoIsolate ? "AND auto_isolate = 1" : "";

    // Cari pelanggan yang isolation_date-nya sudah lewat/hari ini
    $customersToIsolate = fetchAll("
        SELECT id, name, phone, pppoe_username, isolation_date
        FROM customers
        WHERE status = 'active'
        AND isolation_date IS NOT NULL
        AND isolation_date != '0000-00-00'
        AND isolation_date <= CURDATE()
        {$autoIsolateClause}
    ");

    echo "Found " . count($customersToIsolate) . " customers due for isolation\n";

    foreach ($customersToIsolate as $customer) {
        echo "Isolating customer: {$customer['name']} (isolation_date: {$customer['isolation_date']})\n";

        // Cek apakah memang ada invoice unpaid (validasi tambahan)
        $hasUnpaidInvoice = fetchOne("
            SELECT id FROM invoices 
            WHERE customer_id = ? AND status = 'unpaid' 
            LIMIT 1
        ", [$customer['id']]);

        if (!$hasUnpaidInvoice) {
            echo "  ⚠ Customer has no unpaid invoice, skipping...\n";
            continue;
        }

        // Isolir customer
        if (isolateCustomer($customer['id'], ['send_whatsapp' => false])) {
            echo "  ✓ Customer isolated\n";

            // Send WhatsApp notification
            $message = "Halo {$customer['name']},\n\n";
            $message .= "Koneksi internet Anda telah diisolir karena belum melakukan pembayaran tagihan.\n\n";
            $message .= "Tanggal isolasi: " . date('d/m/Y', strtotime($customer['isolation_date'])) . "\n\n";
            $message .= "Mohon segera lakukan pembayaran untuk mengaktifkan kembali koneksi internet Anda.\n\n";
            $message .= "Terima kasih.";
            $message .= getWhatsAppFooter();
            sendWhatsApp($customer['phone'], $message);

        } else {
            echo "  ✗ Failed to isolate customer\n";
        }
    }
}

/** 
 * Run fiktifCustomers 
*/
function runFiktifCustomers($pdo)
{
    $fiktifCustomers = fetchAll("SELECT customer_id FROM fiktif_customers");

    // Aktivasi pelanggan fiktif
    foreach ($fiktifCustomers as $fiktif) {
        $customerId = $fiktif['customer_id'];
        $customer = fetchOne("SELECT id, name, status FROM customers WHERE id = ?", [$customerId]);
        if ($customer && $customer['status'] === 'isolated') {
            if (activateCustomer($customerId)) {
                echo "Activated fiktif customer: {$customer['name']} (ID: {$customerId})\n";
            } else {
                echo "Failed to activate fiktif customer: {$customer['name']} (ID: {$customerId})\n";
            }
        }
    }

    // Perpanjangan pelanggan fiktif
    foreach ($fiktifCustomers as $fiktif) {
        $customerId = $fiktif['customer_id'];
        $invoice = fetchOne("SELECT * FROM invoices 
        WHERE customer_id = ? 
        AND MONTH(due_date) = MONTH(CURDATE()) 
        AND YEAR(due_date) = YEAR(CURDATE())
        ORDER BY due_date DESC 
        LIMIT 1", [$customerId]);

        if ($invoice) {
            if($invoice['status'] === 'unpaid') {
                $randomPaidAt = date('Y-m-d H:i:s', rand(strtotime(date('Y-m-01 00:00:00')), time()));
                $isolationDate = date('Y-m-d', strtotime($randomPaidAt . ' +30 days'));

                $updateResult = update('invoices', ['status' => 'paid', 'paid_at' => $randomPaidAt, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$invoice['id']]);
                update('customers', ['isolation_date' => $isolationDate], 'id = ?', [$customerId]);
                if ($updateResult) {
                    echo "Invoice ID {$invoice['id']} for customer ID {$customerId} marked as paid.\n";
                } else {
                    echo "Failed to mark invoice ID {$invoice['id']} for customer ID {$customerId} as paid.\n";
                }
            } else {
                echo "Invoice ID {$invoice['id']} for customer ID {$customerId} is not unpaid, skipping extension.\n";
            }
        }
    }
}

/**
 * Run auto invoice generation (for 1st of each month) - OPTIMIZED VERSION
 */
function runAutoInvoice($pdo)
{
    echo "Running auto invoice generation...\n";

    // Only run on the 1st of the month
    if (date('j') != '1') {
        echo "  Skipping - not the 1st of the month\n";
        return;
    }

    $currentYear = date('Y');
    $currentMonth = date('m');
    $firstDayOfMonth = date('Y-m-01');
    $now = date('Y-m-d H:i:s');
    $generatedCount = 0;
    $skippedCount = 0;
    $failedCount = 0;

    // Get all active customers with their package info in ONE QUERY
    $customers = fetchAll("
        SELECT c.*, p.price as package_price, p.name as package_name
        FROM customers c
        LEFT JOIN packages p ON c.package_id = p.id
        WHERE c.status = 'active'
        ORDER BY c.id
    ");
    
    echo "Found " . count($customers) . " active customers\n";

    if (empty($customers)) {
        echo "No active customers found\n";
        return;
    }

    // Get all existing invoices for this month in ONE QUERY (untuk batch check)
    $existingInvoices = fetchAll("
        SELECT customer_id, due_date, status 
        FROM invoices 
        WHERE YEAR(due_date) = ? AND MONTH(due_date) = ?
        AND status != 'cancelled'
    ", [$currentYear, $currentMonth]);
    
    // Convert to lookup array for O(1) check
    $existingMap = [];
    foreach ($existingInvoices as $inv) {
        $existingMap[$inv['customer_id']] = $inv['due_date'];
    }
    
    echo "Found " . count($existingMap) . " existing invoices for this month\n";

    $batchInsertData = [];
    
    foreach ($customers as $customer) {
        if (isset($existingMap[$customer['id']])) {
            echo "  ✗ Invoice already exists for: {$customer['name']} (due_date: {$existingMap[$customer['id']]})\n";
            $skippedCount++;
            continue;
        }
        
        // Check if customer has package
        if (!$customer['package_price']) {
            echo "  ⚠ No package found for: {$customer['name']}\n";
            $skippedCount++;
            continue;
        }

        // Get due date based on customer's isolation_date day
        $dueDate = getCustomerDueDate($customer, $firstDayOfMonth);
        
        // Validate due_date
        if (!$dueDate || $dueDate < $firstDayOfMonth) {
            echo "  ⚠ Invalid due_date for: {$customer['name']}, using default\n";
            $dueDate = date('Y-m-20', strtotime($firstDayOfMonth));
        }
        
        $invoiceNumber = generateInvoiceNumber();
        
        $batchInsertData[] = [
            'invoice_number' => $invoiceNumber,
            'customer_id' => $customer['id'],
            'amount' => $customer['package_price'],
            'status' => 'unpaid',
            'due_date' => $dueDate,
            'created_at' => $now
        ];
        
        echo "  ✓ Ready: {$customer['name']} - {$invoiceNumber} (due: {$dueDate})\n";
        $generatedCount++;
    }
    
    // Batch insert if using PDO (opsional, untuk performa)
    if ($generatedCount > 0 && function_exists('batchInsert')) {
        $result = batchInsert('invoices', $batchInsertData);
        if (!$result) {
            echo "  ✗ Batch insert failed!\n";
            $failedCount = $generatedCount;
            $generatedCount = 0;
        }
    } else {
        // Fallback to single insert
        foreach ($batchInsertData as $data) {
            if (insert('invoices', $data)) {
                echo "  ✓ Inserted: {$data['invoice_number']}\n";
            } else {
                echo "  ✗ Failed: {$data['invoice_number']}\n";
                $failedCount++;
                $generatedCount--;
            }
        }
    }

    echo "\n=== Summary ===\n";
    echo "✓ Generated: {$generatedCount} invoices\n";
    echo "⚠ Skipped: {$skippedCount} customers (already have invoice)\n";
    echo "✗ Failed: {$failedCount} customers\n";
    echo "Total processed: " . count($customers) . " active customers\n";

    // Log activity
    if ($generatedCount > 0) {
        logActivity('AUTO_INVOICE', "Auto-generated {$generatedCount} invoices for " . date('F Y'));
    }
}

/**
 * Run database backup
 */
function runBackupDb()
{
    echo "Running database backup...\n";
    $retentionDays = (int) getSetting('BACKUP_RETENTION_DAYS', 7);
    $result = createDatabaseBackup($retentionDays);
    if (!$result['success']) {
        echo "  ✗ " . ($result['message'] ?? 'Backup failed') . "\n";
        return;
    }
    $filePath = $result['file_path'] ?? '';
    $fileSize = $result['file_size'] ?? 0;
    echo "  ✓ Backup created: {$filePath} (" . round(((int) $fileSize) / 1024 / 1024, 2) . " MB)\n";
    $deletedFiles = $result['deleted_files'] ?? [];
    foreach ($deletedFiles as $deleted) {
        echo "  ✓ Deleted old backup: {$deleted}\n";
    }
}

/**
 * Send payment reminders
 */
function sendReminders($pdo)
{
    echo "Sending payment reminders...\n";

    // Get customers with unpaid invoices due in 3 days
    $upcomingInvoices = fetchAll("
        SELECT c.id, c.name, c.phone, c.pppoe_username, i.invoice_number, i.amount, i.due_date
        FROM customers c
        INNER JOIN invoices i ON c.id = i.customer_id
        WHERE i.status = 'unpaid'
        AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        AND c.status = 'active'
        AND i.due_date = (
            SELECT MIN(i2.due_date)
            FROM invoices i2
            WHERE i2.customer_id = c.id
            AND i2.status = 'unpaid'
            AND i2.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        )
    ");

    echo "Found " . count($upcomingInvoices) . " upcoming invoice reminders\n";

    foreach ($upcomingInvoices as $invoice) {
        $daysUntilDue = (strtotime($invoice['due_date']) - time()) / 86400;

        $message = "Halo {$invoice['name']},\n\n";
        $message .= "Pengingat: Tagihan internet Anda akan jatuh tempo dalam " . ceil($daysUntilDue) . " hari.\n\n";
        $message .= "Tagihan: " . formatCurrency($invoice['amount']) . "\n";
        $message .= "Invoice: {$invoice['invoice_number']}\n";
        $message .= "Jatuh Tempo: " . formatDate($invoice['due_date']) . "\n\n";
        $message .= "Mohon lakukan pembayaran sebelum jatuh tempo untuk menghindari isolir.\n\n";
        $message .= "Terima kasih.";
        $message .= getWhatsAppFooter();

        echo "  Sending reminder to: {$invoice['name']} ({$invoice['phone']})\n";
        sendWhatsApp($invoice['phone'], $message);
    }
}

/**
 * Run custom script
 */
function runCustomScript($pdo, $schedule)
{
    echo "Running custom script...\n";
    $rawPath = trim((string) ($schedule['custom_script_path'] ?? ''));
    if ($rawPath === '') {
        throw new Exception('custom_script_path belum diisi.');
    }

    $projectRoot = realpath(__DIR__ . '/..');
    if ($projectRoot === false) {
        throw new Exception('Project root tidak ditemukan.');
    }

    $isAbsolute = preg_match('/^(?:[a-zA-Z]:[\\\/]|\/)/', $rawPath) === 1;
    $candidatePath = $isAbsolute ? $rawPath : $projectRoot . DIRECTORY_SEPARATOR . ltrim($rawPath, '/\\');
    $resolvedPath = realpath($candidatePath);

    if ($resolvedPath === false || !is_file($resolvedPath)) {
        throw new Exception('File script tidak ditemukan: ' . $rawPath);
    }

    $normalizedRoot = str_replace('\\', '/', $projectRoot);
    $normalizedResolved = str_replace('\\', '/', $resolvedPath);
    if (strpos($normalizedResolved, $normalizedRoot . '/') !== 0 && $normalizedResolved !== $normalizedRoot) {
        throw new Exception('Akses script di luar folder project tidak diizinkan.');
    }

    $extension = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));
    if ($extension !== 'php') {
        throw new Exception('Custom script saat ini hanya mendukung file .php');
    }

    if (!function_exists('exec')) {
        throw new Exception('Fungsi exec() dinonaktifkan di server.');
    }

    $phpBin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $command = escapeshellarg($phpBin) . ' ' . escapeshellarg($resolvedPath);

    $argsRaw = trim((string) ($schedule['custom_script_args'] ?? ''));
    if ($argsRaw !== '') {
        $argTokens = preg_split('/\s+/', $argsRaw) ?: [];
        foreach ($argTokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            $command .= ' ' . escapeshellarg($token);
        }
    }

    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);

    foreach ($output as $line) {
        echo '  ' . $line . "\n";
    }

    if ($returnCode !== 0) {
        throw new Exception('Custom script gagal dijalankan. Exit code: ' . $returnCode);
    }

    echo "  ✓ Custom script selesai dijalankan.\n";
}

echo "\n";
