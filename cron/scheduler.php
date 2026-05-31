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
 * Run auto-isolir task
 */
function runAutoIsolir($pdo)
{
    echo "Running auto-isolir...\n";

    // Get customers with unpaid invoices that are overdue
    $hasAutoIsolate = ensureCustomersAutoIsolateColumn();
    $autoIsolateClause = $hasAutoIsolate ? "AND c.auto_isolate = 1" : "";
    $overdueInvoices = fetchAll("
        SELECT c.id, c.name, c.phone, c.pppoe_username, c.package_id, i.invoice_number, i.amount, i.due_date
        FROM customers c
        INNER JOIN invoices i ON c.id = i.customer_id
        WHERE i.status = 'unpaid'
        AND i.due_date < CURDATE()
        AND c.status = 'active'
        {$autoIsolateClause}
        AND i.due_date = (
            SELECT MIN(i2.due_date)
            FROM invoices i2
            WHERE i2.customer_id = c.id
            AND i2.status = 'unpaid'
            AND i2.due_date < CURDATE()
        )
    ");

    echo "Found " . count($overdueInvoices) . " overdue invoices\n";

    foreach ($overdueInvoices as $invoice) {
        echo "Isolating customer: {$invoice['name']} (Invoice: {$invoice['invoice_number']})\n";

        // Isolate customer (hindari double WA dari isolateCustomer)
        if (isolateCustomer($invoice['id'], ['send_whatsapp' => false])) {
            echo "  ✓ Customer isolated\n";

            // Send WhatsApp notification
            $message = "Halo {$invoice['name']},\n\nPembayaran internet Anda sudah melewati tanggal jatuh tempo.\n\nTagihan: " . formatCurrency($invoice['amount']) . "\nInvoice: {$invoice['invoice_number']}\n\nMohon segera lakukan pembayaran untuk mengaktifkan kembali koneksi internet Anda.\n\nTerima kasih.";
            $message .= getWhatsAppFooter();
            sendWhatsApp($invoice['phone'], $message);

        } else {
            echo "  ✗ Failed to isolate customer\n";
        }
    }
}

/**
 * Run auto invoice generation (for 1st of each month)
 */
function runAutoInvoice($pdo)
{
    echo "Running auto invoice generation...\n";

    // Only run on the 1st of the month
    if (date('j') != '1') {
        echo "  Skipping - not the 1st of the month\n";
        return;
    }

    $currentMonth = date('Y-m');
    $generatedCount = 0;

    // Get all active customers
    $customers = fetchAll("SELECT * FROM customers WHERE status = 'active'");

    echo "Found " . count($customers) . " active customers\n";

    foreach ($customers as $customer) {
        // Check if invoice already exists for this month
        $existingInvoice = fetchOne("
            SELECT id FROM invoices 
            WHERE customer_id = ? 
            AND DATE_FORMAT(created_at, '%Y-%m') = ?",
            [$customer['id'], $currentMonth]
        );

        if (!$existingInvoice) {
            $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);

            if ($package) {
                $dueDate = getCustomerDueDate($customer, $currentMonth . '-01');
                $invoiceData = [
                    'invoice_number' => generateInvoiceNumber(),
                    'customer_id' => $customer['id'],
                    'amount' => $package['price'],
                    'status' => 'unpaid',
                    'due_date' => $dueDate,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                insert('invoices', $invoiceData);
                $generatedCount++;
                echo "  ✓ Generated invoice for: {$customer['name']}\n";
            }
        }
    }

    echo "Generated {$generatedCount} invoices for " . date('F Y') . "\n";

    // Log activity
    logActivity('AUTO_INVOICE', "Auto-generated {$generatedCount} invoices for " . date('F Y'));
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
