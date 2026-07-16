<?php
/**
 * Helper Functions
 */

// Global settings cache
$global_settings_cache = null;
$site_settings_cache = null;

// Get setting from database with fallback to config constant
function getSetting($key, $default = '') {
    global $global_settings_cache;

    if ($global_settings_cache === null) {
        $global_settings_cache = [];
        $data = fetchAll("SELECT setting_key, setting_value FROM settings");
        foreach ($data as $row) {
            $global_settings_cache[$row['setting_key']] = $row['setting_value'];
        }
    }

    if (isset($global_settings_cache[$key]) && $global_settings_cache[$key] !== '') {
        return $global_settings_cache[$key];
    }

    if (defined($key)) {
        return constant($key);
    }

    return $default;
}
function getSettingValue($key, $default = '') {
    return getSetting($key, $default);
}

// Get site setting from site_settings table
function getSiteSetting($key, $default = '') {
    global $site_settings_cache;

    if ($site_settings_cache === null) {
        $site_settings_cache = [];
        try {
            $data = fetchAll("SELECT setting_key, setting_value FROM site_settings");
            if (is_array($data)) {
                foreach ($data as $row) {
                    $site_settings_cache[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (Exception $e) {
            // Table might not exist yet
        }
    }

    return $site_settings_cache[$key] ?? $default;
}

// Save FAQ
if (!function_exists('saveFaq')) {
    function saveFaq($question, $answer) {
        if (empty(trim($question)) || empty(trim($answer))) {
            return false;
        }
        return insert('faqs', [
            'question' => trim($question),
            'answer' => trim($answer),
            'sort_order' => 0,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}

// Update FAQ
if (!function_exists('updateFaq')) {
    function updateFaq($id, $question, $answer, $active = 1) {
        if (empty(trim($question)) || empty(trim($answer))) {
            return false;
        }
        return update('faqs', [
            'question' => trim($question),
            'answer' => trim($answer),
            'is_active' => $active ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);
    }
}

// Delete FAQ
if (!function_exists('deleteFaq')) {
    function deleteFaq($id) {
        return delete('faqs', 'id = ?', [$id]);
    }
}

// Get Mikrotik settings from database (supports multi-router)
require_once __DIR__ . '/mikrotik_api.php';

// Format currency
function formatCurrency($amount)
{
    $amount = is_numeric($amount) ? $amount : 0;
    $symbol = getSetting('CURRENCY_SYMBOL', 'Rp');
    return $symbol . ' ' . number_format((float) $amount, 0, ',', '.');
}

// Format date
function formatDate($date, $format = 'd M Y')
{
    if (!$date)
        return '-';
    $time = strtotime($date);
    return $time ? date($format, $time) : '-';
}

// Generate invoice number
// function generateInvoiceNumber()
// {
//     $prefix = INVOICE_PREFIX;
//     $start = INVOICE_START;

//     $lastInvoice = fetchOne("SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1");

//     if ($lastInvoice) {
//         $lastNum = (int) str_replace($prefix, '', $lastInvoice['invoice_number']);
//         $newNum = $lastNum + 1;
//     } else {
//         $newNum = $start;
//     }

//     return $prefix . str_pad($newNum, 6, '0', STR_PAD_LEFT);
// }

function generateInvoiceNumber($customerId)
{
    $prefix = INVOICE_PREFIX; // Diambil dari config: INV

    // Mengambil timestamp saat ini (format: YmdHis -> 20260506162205)
    $timestamp = date('YmdHis');
    $paddedId = str_pad($customerId, 5, '0', STR_PAD_LEFT);
    // Membuat 6 angka acak
    $random = str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);

    // Hasil: INV-20260506162205-123456
    return $prefix . '-' . $timestamp . $paddedId . $random;
}
function storeRedirectUrl($orderId, $redirectUrl) {
    $data = [
        'order_id' => $orderId,
        'redirect_url' => $redirectUrl
    ];
    $success = insert('payment_redirects', $data);

    if (!$success) {
        logError("Failed to store redirect URL for order_id: {$orderId}");
        return false;
    }

    return true;
}

function getRedirectUrl($orderId) {
    $record = fetchOne("SELECT redirect_url FROM payment_redirects WHERE order_id = ?", [$orderId]);
    return $record ? $record['redirect_url'] : null;
}
function sendWhatsApp($phone, $message)
{
    require_once __DIR__ . '/whatsapp.php';

    // Get default WhatsApp gateway from settings
    $defaultGateway = fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", ['DEFAULT_WHATSAPP_GATEWAY'])['setting_value'] ?? 'fonnte';
    $defaultGateway = trim((string) $defaultGateway);
    if ($defaultGateway === '') {
        $defaultGateway = 'fonnte';
    }

    // Format phone number (62 format)
    if (substr($phone, 0, 2) === '08') {
        $phone = '62' . substr($phone, 1);
    }

    // Send using selected gateway
    $result = sendWhatsAppMessage($phone, $message, $defaultGateway);

    $success = $result['success'] ?? false;
    if (!$success) {
        $err = (string) ($result['message'] ?? 'Unknown error');
        if (function_exists('logWhatsAppError')) {
            logWhatsAppError("SEND_FAILED: gateway={$defaultGateway} to={$phone} msg=" . $err);
        }
    }
    return $success;
}

function getWhatsAppFooter()
{
    $appName = trim((string) getSetting('app_name', defined('APP_NAME') ? APP_NAME : ''));
    $admin = trim((string) getSetting('WHATSAPP_ADMIN_NUMBER', ''));
    $adminDigits = preg_replace('/\D+/', '', $admin);
    if ($adminDigits !== '') {
        if (strpos($adminDigits, '0') === 0) {
            $adminDigits = '62' . substr($adminDigits, 1);
        } elseif (strpos($adminDigits, '62') !== 0) {
            $adminDigits = '62' . $adminDigits;
        }
    }

    $lines = [];
    if ($appName !== '') {
        $lines[] = $appName;
    }
    if ($adminDigits !== '') {
        $lines[] = 'CS/WA: ' . $adminDigits;
    }
    if (empty($lines)) {
        return '';
    }
    return "\n\n" . implode("\n", $lines);
}
/**
 * Get latest payment date from invoices for a customer
 * @param int $customerId Customer ID
 * @return string|null Latest payment date (Y-m-d) or null
 */
function getLatestPaymentDate($customerId) {
    $invoice = fetchOne("SELECT paid_at FROM invoices 
        WHERE customer_id = ? AND status = 'paid' 
        AND paid_at IS NOT NULL AND paid_at != '0000-00-00 00:00:00'
        ORDER BY paid_at DESC LIMIT 1",
        [$customerId]);

    if ($invoice && $invoice['paid_at']) {
        return date('Y-m-d', strtotime($invoice['paid_at']));
    }
    return null;
}

/**
 * Calculate next isolation date based on latest payment
 * @param int $customerId Customer ID
 * @return string Next isolation date (Y-m-d)
 */
function calculateNextIsolationDate($customerId): string
{
    $customer = fetchOne("
        SELECT isolation_date
        FROM customers
        WHERE id = ?
    ", [$customerId]);

    return $customer['isolation_date'] ?? date('Y-m-d');
}

// From billing dayyy
function buildIsolationDate(int $billingDay): string
{
    if ($billingDay <= 0) {
        $billingDay = 20;
    }

    $today = new DateTime();

    $day = min($billingDay, (int)$today->format('t'));

    $date = new DateTime($today->format('Y-m') . '-' . str_pad($day, 2, '0', STR_PAD_LEFT));

    if ($date < $today) {
        $date->modify('+1 month');

        $day = min($billingDay, (int)$date->format('t'));

        $date->setDate(
            (int)$date->format('Y'),
            (int)$date->format('m'),
            $day
        );
    }

    return $date->format('Y-m-d');
}
/**
 * Update isolation date based on paid invoices
 * @param int $customerId Customer ID
 * @return bool Success or failure
 */
function updateCustomerIsolationDateFromPaidInvoices($customerId)
{
    $customer = fetchOne("
        SELECT billing_day
        FROM customers
        WHERE id = ?
    ", [$customerId]);

    if (!$customer) {
        return false;
    }

    $billingDay = (int)$customer['billing_day'];

    $latestPaid = fetchOne("
        SELECT due_date
        FROM invoices
        WHERE customer_id = ?
        AND status='paid'
        ORDER BY due_date DESC
        LIMIT 1
    ", [$customerId]);

    if (!$latestPaid) {
        return false;
    }

    $nextMonth = date(
        'Y-m-01',
        strtotime($latestPaid['due_date'].' +1 month')
    );

    $lastDay = (int)date('t', strtotime($nextMonth));

    if ($billingDay > $lastDay) {
        $billingDay = $lastDay;
    }

    $newIsolationDate = sprintf(
        "%s-%02d",
        date('Y-m', strtotime($nextMonth)),
        $billingDay
    );

    return update(
        'customers',
        [
            'isolation_date'=>$newIsolationDate,
            'updated_at'=>date('Y-m-d H:i:s')
        ],
        'id=?',
        [$customerId]
    );
}
function customerHasPaidInvoice($customerId)
{
    return (bool)fetchOne("
        SELECT id
        FROM invoices
        WHERE customer_id=?
        AND status='paid'
        LIMIT 1
    ", [$customerId]);
}

function updateCustomerIsolationDateFromBillingDay($customerId)
{
    $customer = fetchOne("
        SELECT billing_day
        FROM customers
        WHERE id = ?
    ", [$customerId]);
    if (!$customer) {
        return false;
    }
    $billingDay = (int)$customer['billing_day'] ?? 20;
    return update(
        'customers',
        [
            'isolation_date'=>date('Y-m-'.$billingDay),
            'updated_at'=>date('Y-m-d H:i:s')
        ],
        'id=?',
        [$customerId]
    );
}
//function getCustomerDueDate($customer, $baseDate = null)
//{
//    $baseTimestamp = $baseDate ? strtotime($baseDate) : time();
//    $year = date('Y', $baseTimestamp);
//    $month = date('m', $baseTimestamp);
//
//    // Default ke tanggal 20
//    $day = 20;
//
//    if (!empty($customer['isolation_date']) && $customer['isolation_date'] !== '0000-00-00') {
//        // Cek jika datanya murni angka (berjaga-jaga jika ada format lama)
//        if (is_numeric($customer['isolation_date'])) {
//            $day = (int) $customer['isolation_date'];
//        } else {
//            // Ekstrak hari dari format 'YYYY-MM-DD'
//            $day = (int) date('d', strtotime($customer['isolation_date']));
//        }
//    }
//
//    $lastDayInMonth = (int) date('t', strtotime($year . '-' . $month . '-01'));
//
//    if ($day > $lastDayInMonth || $day < 1) {
//        $day = $lastDayInMonth;
//    }
//
//    return sprintf('%04d-%02d-%02d', $year, $month, $day);
//}

function getCustomerDueDate($customer, $baseDate = null)
{
    $baseTimestamp = $baseDate ? strtotime($baseDate) : time();

    $year  = date('Y', $baseTimestamp);
    $month = date('m', $baseTimestamp);

    $day = (int)($customer['billing_day'] ?? 20);

    $lastDay = (int)date('t', strtotime("$year-$month-01"));

    if ($day < 1) {
        $day = 1;
    }

    if ($day > $lastDay) {
        $day = $lastDay;
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}
function logError($message)
{
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] ERROR: {$message}\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Log activity
function getUserIP()
{
    // Daftar header yang biasa disisipkan oleh Proxy, Load Balancer, atau CDN (seperti Cloudflare)
    $headers = [
        'HTTP_CF_CONNECTING_IP',  // Khusus jika menggunakan Cloudflare
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',   // Paling umum digunakan oleh Reverse Proxy
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'             // Fallback terakhir (Default)
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            // Header X-Forwarded-For bisa berisi beberapa IP yang dipisah koma (Client IP, Proxy1, Proxy2, dst)
            // Kita ambil IP yang paling awal (IP asli client)
            $ipList = explode(',', $_SERVER[$header]);
            $ip = trim($ipList[0]);

            // Validasi apakah format IP tersebut valid
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function logActivity($action, $details = '')
{
    $logFile = __DIR__ . '/../logs/activity.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['admin']['username'] ?? 'guest';

    // Gunakan fungsi getUserIP() di sini, bukan langsung $_SERVER['REMOTE_ADDR']
    $ip = getUserIP();

    $logMessage = "[{$timestamp}] [{$user}] [{$ip}] {$action} - {$details}\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function actionLog($action, string $workdir, string $msg, string $data = '') {
    $logFile = __DIR__ . '/../logs/action.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $username = $_SESSION['admin']['username'] ?? 'guest';
    $ip = getUserIP();
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$username}] [{$ip}] {$action} - [{$workdir}] - [{$msg}] - {$data}\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
// Redirect
function redirect($url)
{
    header("Location: {$url}");
    exit;
}

// Flash message
function setFlash($type, $message)
{
    $_SESSION['flash'][$type] = $message;
}

function getFlash($type)
{
    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $message;
}

function hasFlash($type)
{
    return isset($_SESSION['flash'][$type]);
}

// Sanitize input
function sanitize($input)
{
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate email
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generate random string with charset options
function generateRandomString($length = 10, $type = 'mixed')
{
    switch ($type) {
        case 'numeric':
        case 'num':
            $x = '0123456789';
            break;
        case 'alpha':
        case 'low':
            $x = 'abcdefghijklmnopqrstuvwxyz';
            break;
        case 'up':
            $x = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
        case 'mixed':
            $x = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
            break; // Avoid ambiguous chars
        case 'alphanumeric':
        default:
            $x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
    }

    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $x[mt_rand(0, strlen($x) - 1)];
    }
    return $str;
}

// Mikhmon Metadata Helpers
function formatMikhmonComment($price, $validity, $profile)
{
    // Format: vc-user-dd-mm-yy (Price: Rp 5.000, Validity: 1d)
    // Note: Mikhmon often uses specific patterns like uct-ddmmyy-price
    $date = date('d/m/y');
    return "price:{$price},validity:{$validity},profile:{$profile},date:{$date}";
}

function parseMikhmonComment($comment)
{
    $data = [
        'price' => 0,
        'validity' => '-',
        'profile' => '-',
        'date' => '-',
        'raw' => $comment
    ];

    if (empty($comment))
        return $data;

    // 1. Try existing key:value format (e.g. price:5000,validity:1d,date=...)
    // Note: Mikhmon uses both : and =
    if (strpos($comment, 'price:') !== false || strpos($comment, 'price=') !== false) {
        $parts = preg_split('/[, ]+/', $comment);
        foreach ($parts as $part) {
            $kv = preg_split('/[:=]/', $part, 2);
            if (count($kv) === 2) {
                $itemKey = trim($kv[0]);
                $itemVal = trim($kv[1]);
                if (isset($data[$itemKey])) {
                    $data[$itemKey] = $itemVal;
                }
            }
        }
        return $data;
    }

    // 2. Try Standard Mikhmon Format: Date - Code - Price - Profile - Validity
    $parts = array_map('trim', explode('-', $comment));
    if (count($parts) >= 5) {
        $data['date'] = $parts[0];
        $data['price'] = preg_replace('/[^0-9]/', '', $parts[2]);
        $data['profile'] = $parts[3];
        $data['validity'] = $parts[4];
        return $data;
    }

    // 3. Fallback search using Regex - BE STRICTER
    // Prioritize Rp or price: prefixes. If none, only accept numeric strings if they are reasonable (< 1,000,000)
    // and not too long (vouchers rarely cost billions)

    $foundPrice = 0;

    // Pattern A: Explicit Price Prefix (Rp, price:, parent:)
    if (preg_match('/(?:price[:=]|Rp\.?\s?|rp\.?\s?|parent[:=])\s?(\d{1,3}(?:\.\d{3})*|\d{3,})/i', $comment, $matches)) {
        $foundPrice = str_replace('.', '', $matches[1]);
    }
    // Pattern B: Bare number at the end or surrounded by spaces (only if Pattern A failed)
    elseif (preg_match('/(?:\s|^)(\d{3,7})(?:\s|$|,)/', $comment, $matches)) {
        $tempPrice = $matches[1];
        // Sanity check: Mikhmon voucher prices are usually under 1,000,000
        if ((int) $tempPrice < 1000000) {
            $foundPrice = $tempPrice;
        }
    }

    if ($foundPrice) {
        $data['price'] = (int) $foundPrice;
    }

    // Date - Be careful not to pick up the same big number
    if (preg_match('/(?:date[:=]|^|\s)([a-z]{3}\/\d{2}\/\d{4}\s\d{2}:\d{2}:\d{2})/i', $comment, $matches)) {
        $data['date'] = $matches[1];
    } elseif (preg_match('/(\d{2}[-\/\.]\d{2}[-\/\.]\d{2,4})/', $comment, $matches)) {
        $data['date'] = $matches[1];
    }

    return $data;
}

function parseHotspotProfileComment($comment)
{
    $price = 0;

    if (empty($comment)) {
        return 0;
    }

    // 1. Try 'parent:PRICE' format (used by this app)
    if (strpos($comment, 'parent:') !== false) {
        // Extract everything after parent:
        $parts = explode('parent:', $comment);
        if (isset($parts[1])) {
            // Take the number immediately following parent:
            $val = trim($parts[1]);
            // If comma separated like parent:5000,other:value
            $valParts = explode(',', $val);
            $price = preg_replace('/[^0-9]/', '', $valParts[0]);
            return (int) $price;
        }
    }

    // 2. Try explicit 'price:' format
    if (preg_match('/price[:=]\s?(\d+)/i', $comment, $matches)) {
        return (int) $matches[1];
    }

    // 3. Try formatted currency format (Rp 5.000)
    if (preg_match('/Rp\.?\s?(\d{1,3}(?:\.\d{3})*|\d{3,})/i', $comment, $matches)) {
        $clean = str_replace('.', '', $matches[1]);
        return (int) $clean;
    }

    // 4. Try bare numeric price (with sanity check)
    // Mikhmon sometimes just puts the price. But we must ignore timestamps (YYYYMMDD...)
    if (preg_match('/(?:\s|^)(\d{3,7})(?:\s|$|,)/', $comment, $matches)) {
        $val = (int) $matches[1];
        // Sanity check: if it looks like a date/timestamp
        // (e.g. starts with 202, 201 or has 8+ digits), ignore it
        if ($val < 1000000 && strlen($matches[1]) <= 7) {
            return $val;
        }
    }

    return 0;
}

// Check if customer is isolated
function isCustomerIsolated($customerId)
{
    $customer = fetchOne("SELECT status FROM customers WHERE id = ?", [$customerId]);
    return $customer && $customer['status'] === 'isolated';
}
// update isolation date
function updateIsolationDat($customerId, $isolationDate) {
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        return false;
    }

    $updated = update('customers', [
        'isolation_date' => $isolationDate,
        'billing_day' => (int)$customer['billing_day'],
    ], 'id = ?', [$customerId]);
    return $updated ? true : false;
}
// Isolate customer

function isolateCustomer($customerId, $options = [])
{
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        return false;
    }

    if (($customer['status'] ?? '') === 'isolated') {
        return true;
    }

    // Update status
    update('customers', ['status' => 'isolated'], 'id = ?', [$customerId]);

    // Update MikroTik profile
    $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);
    $sendWhatsapp = true;
    if (is_array($options) && array_key_exists('send_whatsapp', $options)) {
        $sendWhatsapp = (bool) $options['send_whatsapp'];
    }

    if ($package && !empty($customer['pppoe_username']) && !empty($package['profile_isolir'])) {
        mikrotikSetProfile($customer['pppoe_username'], $package['profile_isolir'], $customer['router_id']);
        radiusUpdateUserProfile($customer['pppoe_username'], $package['profile_isolir']);
        mikrotikRemoveActiveSessionByName($customer['pppoe_username']);

        if ($sendWhatsapp) {
            $message = "Halo {$customer['name']},\n\nPembayaran internet Anda sudah melewati tanggal jatuh tempo.\n\nMohon segera lakukan pembayaran untuk mengaktifkan kembali koneksi internet Anda.\n\nTerima kasih.";
            $message .= getWhatsAppFooter();
            sendWhatsApp($customer['phone'], $message);
        }
    } elseif ($package && !empty($customer['pppoe_username']) && empty($package['profile_isolir'])) {
        logError('Isolir skipped MikroTik profile update (profile_isolir kosong). Customer ID: ' . $customerId);
    } else {
        logError('Isolir skipped MikroTik profile update (package not found or pppoe_username empty). Customer ID: ' . $customerId);
    }

    logActivity('ISOLATE_CUSTOMER', "Customer ID: {$customerId}");

    return true;
}

// Unisolate customer
function unisolateCustomer($customerId, $options = [])
{
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        return false;
    }

    if (($customer['status'] ?? '') !== 'isolated') {
        return true;
    }

    update('customers', ['status' => 'active'], 'id = ?', [$customerId]);

    $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);
    if ($package && !empty($customer['pppoe_username'])) {
        mikrotikSetProfile($customer['pppoe_username'], $package['profile_normal'], $customer['router_id']);
        radiusUpdateUserProfile($customer['pppoe_username'], $package['profile_normal']);
        mikrotikRemoveActiveSessionByName($customer['pppoe_username']);
        if (function_exists('radiusSetSessionTimeoutFromIsolationDate') && radiusUserProvisioningReady()) {
            radiusSetSessionTimeoutFromIsolationDate($customer['pppoe_username']);
        }
    }

    $sendWhatsapp = false;
    if (is_array($options) && array_key_exists('send_whatsapp', $options)) {
        $sendWhatsapp = (bool) $options['send_whatsapp'];
    }
    if ($sendWhatsapp && !empty($customer['phone'])) {
        $message = "Halo {$customer['name']},\n\nLayanan internet Anda sudah aktif kembali.\n\nTerima kasih.";
        $message .= getWhatsAppFooter();
        sendWhatsApp($customer['phone'], $message);
    }

    logActivity('UNISOLATE_CUSTOMER', "Customer ID: {$customerId}");

    return true;
}
function unisolateFiktifCustomer($customerId, $options = [])
{
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        return false;
    }

    if (($customer['status'] ?? '') !== 'isolated') {
        return true;
    }

    update('customers', ['status' => 'active'], 'id = ?', [$customerId]);

    $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);
    if ($package && !empty($customer['pppoe_username'])) {
        mikrotikSetProfile($customer['pppoe_username'], $package['profile_normal'], $customer['router_id']);
        radiusUpdateUserProfile($customer['pppoe_username'], $package['profile_normal']);
    }

    logActivity('UNISOLATE_FIKTIF_CUSTOMER', "Customer ID: {$customerId}");

    return true;
}

function generateLateDays(): int
{
    $roll = mt_rand(1, 100);

    // 60% cepat bayar
    if ($roll <= 60) {
        return mt_rand(0, 1);
    }

    // 25% telat ringan
    if ($roll <= 85) {
        return mt_rand(2, 5);
    }

    // 10% telat sedang
    if ($roll <= 95) {
        return mt_rand(6, 10);
    }

    // 5% telat berat
    return mt_rand(11, 20);
}

function updateCustomerWithRadiusSync($customerId, $updateData = [])
{
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        return false;
    }

    try {
        // Update customer in database
        if (!empty($updateData)) {
            update('customers', $updateData, 'id = ?', [$customerId]);
        }

        if (!empty($customer['pppoe_username']) && function_exists('radiusSetSessionTimeoutFromIsolationDate')) {
            $updatedCustomer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);

            // Recalculate and update RADIUS session timeout
            radiusSetSessionTimeoutFromIsolationDate($updatedCustomer['pppoe_username']);

            logActivity('RADIUS_TIMEOUT_SYNC', "Customer ID: {$customerId}, Username: {$updatedCustomer['pppoe_username']}");
        }

        return true;
    } catch (Exception $e) {
        logError('updateCustomerWithRadiusSync failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create new customer with RADIUS provisioning if applicable
 * Handles both MikroTik and RADIUS user setup
 *
 * @param array $customerData Customer data to insert
 * @return int|false Customer ID or false on failure
 */
function createCustomerWithRadiusProvisioning($customerData = [])
{
    // Validate required fields
    if (empty($customerData['pppoe_username'])) {
        logError('createCustomerWithRadiusProvisioning: pppoe_username is required');
        return false;
    }

    try {
        // Insert customer into database
        $customerId = insert('customers', $customerData);
        if (!$customerId) {
            return false;
        }

        $pppoeUsername = trim((string) $customerData['pppoe_username']);

        // Provision RADIUS user if RADIUS is available and password provided
        if (function_exists('radiusProvisionUser') && radiusUserProvisioningReady() && !empty($customerData['pppoe_password'])) {
            $package = fetchOne("SELECT * FROM packages WHERE id = ?", [(int) $customerData['package_id']]);
            $profileName = $package['name'] ?? 'default';
            $serviceType = 'Framed-User'; // PPPoE

            $success = radiusProvisionUser(
                $pppoeUsername,
                $customerData['pppoe_password'],
                $profileName,
                $serviceType
            );

            if (!$success) {
                logError("RADIUS provisioning failed for customer {$customerId}, continuing with database entry only");
                // Don't fail entirely - customer is created even if RADIUS provisioning fails
            }
        }

        logActivity('CREATE_CUSTOMER', "Customer ID: {$customerId}, Username: {$pppoeUsername}");
        return $customerId;
    } catch (Exception $e) {
        logError('createCustomerWithRadiusProvisioning failed: ' . $e->getMessage());
        return false;
    }
}

// Get GenieACS settings from database (override config.php)
function getGenieacsSettings()
{
    static $settings = null;
    if ($settings === null) {
        $settings = [
            'url' => defined('GENIEACS_URL') ? GENIEACS_URL : '',
            'username' => defined('GENIEACS_USERNAME') ? GENIEACS_USERNAME : '',
            'password' => defined('GENIEACS_PASSWORD') ? GENIEACS_PASSWORD : ''
        ];

        // Try to get from database
        $dbSettings = fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('GENIEACS_URL', 'GENIEACS_USERNAME', 'GENIEACS_PASSWORD')");
        foreach ($dbSettings as $s) {
            switch ($s['setting_key']) {
                case 'GENIEACS_URL':
                    $settings['url'] = $s['setting_value'];
                    break;
                case 'GENIEACS_USERNAME':
                    $settings['username'] = $s['setting_value'];
                    break;
                case 'GENIEACS_PASSWORD':
                    $settings['password'] = $s['setting_value'];
                    break;
            }
        }
    }
    return $settings;
}

/**
 * Set RADIUS Session-Timeout if username exists in radcheck
 * Called after customer creation/update to sync timeout
 *
 * @param string $pppoeUsername PPPoE username
 * @param int $customerId Customer ID (to fetch isolation_date)
 * @return bool Success status
 */
function syncRadiusTimeoutForCustomer($pppoeUsername, $customerId)
{
    $pppoeUsername = trim((string) $pppoeUsername);
    if ($pppoeUsername === '') {
        return false;
    }

    // Check if function exists (RADIUS not available)
    if (!function_exists('radiusSetSessionTimeoutFromIsolationDate')) {
        return false;
    }

    try {
        // Langsung set timeout ke radreply tanpa check radcheck
        // Function radiusSetSessionTimeoutFromIsolationDate akan:
        // 1. Ambil isolation_date dari customers table
        // 2. Hitung timeout
        // 3. Write ke radreply
        return radiusSetSessionTimeoutFromIsolationDate($pppoeUsername);
    } catch (Exception $e) {
        logError('syncRadiusTimeoutForCustomer failed: ' . $e->getMessage());
        return false;
    }
}
function getOVPNIP()
{
    $ip = trim(shell_exec(
        "ip -4 -o addr show tun0 2>/dev/null | awk '{print \$4}' | cut -d/ -f1"
    ));
    return $ip ?: null;
}

function upsertVPNclient($name, $username, $password)
{
    require_once __DIR__ . '/vpn.php';

    $upserted = upsertVpnUser([
        'name' => $name,
        'username' => $username,
        'password' => $password
    ]);

    logActivity('UPSERT_VPN_CLIENT', "Name: {$name}, Username: {$username}");

    return $upserted;
}

function generateMikrotikClientScript($version = '6')
{

    $appName = getSetting('app_name', null);
    $serverIP = getSetting('server_ip', null);
    $shortAppName = getSetting('short_app_name', null);
    $OvpnIP = getOVPNIP();

    $rangeNormal = "11.7.0.2-11.7.10.254";
    $rangeIsolir = "11.127.0.2-11.127.10.254";


    if (!$appName) {
        logError('App name belum diatur. Tidak dapat generate script MikroTik OVPN client.');
        return [
            'error' => 'App name belum diatur. Tidak dapat generate script MikroTik OVPN client.'
        ];
    }
    if (!$OvpnIP) {
        logError('OVPN IP tidak ditemukan. Pastikan tun0 sudah aktif dan memiliki IP.');
        return [
            'error' => 'OVPN IP tidak ditemukan. Pastikan tun0 sudah aktif dan memiliki IP.'
        ];
    }
    if (!$serverIP) {
        logError('Server IP belum diatur. Tidak dapat generate script MikroTik OVPN client.');
        return [
            'error' => 'Server IP belum diatur. Tidak dapat generate script MikroTik OVPN client.'
        ];
    }

    $radiusCredential = [
        'nas_name' => $appName . "-" . trim(generateRandomString(6, 'alpha')),
        'nas_secret' => trim(generateRandomString(16, 'mixed'))
    ];

    $vpnCredential = [
        'name' => $appName . "-OVPN-" . trim(generateRandomString(6, 'alpha')),
        'username' => trim(generateRandomString(10, 'mixed')),
        'password' => trim(generateRandomString(12, 'mixed'))
    ];

    $script = "# CLIENT - ".getSetting('app_name')."\n";
    $script .= "# Generated at: " . date('Y-m-d H:i:s') . "\n\n";
    $script .= "/ip dns set allow-remote-requests=yes;\n";
    $script .= "/ppp aaa set interim-update=18m use-radius=yes accounting=yes;\n";

    # RADIUS
    $script .= "/radius incoming set accept=yes port=3799;\n";
    $script .= "/radius rem [find comment=\"".$shortAppName."-RADIUS\"];\n";

    if ($version === '7') {
        $script .= "/radius add address=".$OvpnIP." comment=\"".$shortAppName."-RADIUS\" authentication-port=1812 accounting-port=1813 secret=\"".trim($radiusCredential['nas_secret'])."\" service=ppp,hotspot timeout=3s require-message-auth=no;\n";
    } else if($version === '6') {
        $script .= "/radius add address=".$OvpnIP." comment=\"".$shortAppName."-RADIUS\" authentication-port=1812 accounting-port=1813 secret=\"".trim($radiusCredential['nas_secret'])."\" service=ppp,hotspot timeout=3s;\n";
    } else {
        logError('Versi MikroTik tidak dikenali untuk generate script. Harap gunakan versi 6 atau 7.');
        return [
            'error' => 'Versi MikroTik tidak dikenali untuk generate script. Harap gunakan versi 6 atau 7.'
        ];
    }

    # POOL
    $script .= "/ip pool remove [find name=\"".$shortAppName."-POOL\"];\n";
    $script .= "/ip pool add name=\"".$shortAppName."-POOL\" ranges=".$rangeNormal.";\n";
    $script .= "/ip pool remove [find name=\"".$shortAppName."-ISOLIR\"];\n";
    $script .= "/ip pool add name=\"".$shortAppName."-ISOLIR\" ranges=".$rangeIsolir.";\n";

    # PPP PROFILE (VPN) - dibuat duluan
    $script .= "/ppp profile remove [find name=\"".$shortAppName."-VPN\"];\n";
    $script .= "/ppp profile add change-tcp-mss=yes comment=\"DEFAULT BY ".$appName." (DON'T CHANGE IT)\" name=\"".$shortAppName."-VPN\" only-one=default use-encryption=yes;\n";

    # PPP PROFILE
    $script .= "/ppp profile remove [find name=\"".$shortAppName."\"];\n";
    $script .= "/ppp profile remove [find name=\"".$shortAppName."-ISOLIR\"];\n";
    $script .= "/ppp profile add insert-queue-before=first local-address=11.7.0.1 name=\"".$shortAppName."\" only-one=default remote-address=\"".$shortAppName."-POOL\";\n";
    $script .= "/ppp profile add insert-queue-before=first local-address=11.7.0.1 name=\"".$shortAppName."-ISOLIR\" only-one=default remote-address=\"".$shortAppName."-ISOLIR\";\n";

    # INTERFACE (OVPN)
    $script .= "/interface ovpn-client remove [find name~\"".$shortAppName."-OVPN\"];\n";
    $script .= "/interface ovpn-client add disabled=no connect-to=".$serverIP." name=\"".$shortAppName."-OVPN\" profile=\"".$shortAppName."-VPN\" user=\"".$vpnCredential['username']."\" password=\"".$vpnCredential['password']."\";\n";

    # PROXY ISOLIR
    // $script .= "/ip proxy set enabled=yes port=8097;\n";
    // $script .= "/ip proxy access rem [find comment~\"".$shortAppName."\"];\n";
    // if ($version >= 7) {
    //     // versi 7+ pakai action=redirect action-data
    //     $script .= "/ip proxy access add action=redirect action-data=\"".$isolirDomain."\" comment=\"DENY OTHER THAN ISOLIR BY ".$shortAppName."\" dst-address=!".$bypassIP." local-port=8097;\n";
    // } else {
    //     // versi 6 pakai action=deny redirect-to
    //     $script .= "/ip proxy access add action=deny comment=\"DENY OTHER THAN ISOLIR BY ".$shortAppName."\" dst-address=!".$bypassIP." redirect-to=\"".$isolirDomain."\" local-port=8097;\n";
    // }

    # FIREWALL
    $script .= "/ip firewall filter remove [find comment=\"ANSISOLIR\"];\n";
    $script .= "/ip firewall filter add action=drop chain=input comment=\"ANSISOLIR\" src-address=".$rangeIsolir.";\n";
    return [
        'script' => $script,
        'vpn' => $vpnCredential,
        'radius' => $radiusCredential
    ];
}


function getOpenVpnActiveClients() {
    $path = '/var/log/openvpn/openvpn-status.log';
    if (!file_exists($path)) return ["error" => "File log tidak ditemukan."];

    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    $results = [];
    $mode = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line === 'END') continue;

        if (strpos($line, 'Virtual Address,Common Name') !== false) {
            $mode = 'ROUTING'; continue;
        }
        // Baru cek CLIENTS
        if (strpos($line, 'Common Name,Real Address') !== false) {
            $mode = 'CLIENTS'; continue;
        }
        if (strpos($line, 'GLOBAL STATS') !== false) break;

        $data = explode(',', $line);

        if ($mode === 'CLIENTS' && count($data) >= 5) {
            $name = trim($data[0]);
            $results[$name] = [
                'username'        => $name,
                'real_address'    => trim($data[1]),
                'bytes_received'  => round($data[2] / 1024, 2) . " KB",
                'bytes_sent'      => round($data[3] / 1024, 2) . " KB",
                'connected_since' => trim($data[4]),
                'virtual_ip'      => '-'
            ];
        }

        if ($mode === 'ROUTING' && count($data) >= 3) {
            $name = trim($data[1]);
            if (isset($results[$name])) {
                $results[$name]['virtual_ip'] = trim($data[0]);
                $results[$name]['last_ref']   = trim(($data[3] ?? '') . ' ' . ($data[4] ?? ''));
            }
        }
    }
    return array_values($results);
}


function nextAddressOvpnClient() {
    $maxLong = 0;

    $ippPath = '/var/log/openvpn/ipp.txt';
    if (file_exists($ippPath)) {
        $content = file_get_contents($ippPath);
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $data = explode(',', $line);
            if (count($data) < 2) continue;
            $ip = trim($data[1]);
            if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
            $long = ip2long($ip);
            if ($long > $maxLong) $maxLong = $long;
        }
    }

    if ($maxLong === 0) {
        $clients = getOpenVpnActiveClients();
        if (!isset($clients['error'])) {
            foreach ($clients as $client) {
                $ip = trim($client['virtual_ip'] ?? '');
                if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
                $long = ip2long($ip);
                if ($long > $maxLong) $maxLong = $long;
            }
        }
    }

    if ($maxLong === 0) {
        $ip = getOVPNIP();
        if (!$ip) return null;
        return preg_replace('/1$/', '4', $ip);
    }

    return long2ip($maxLong + 4);
}

function getClientOvpnByUsername($username)
{
    $clients = getOpenVpnActiveClients();
    foreach ($clients as $client) {
        if ($client['username'] === $username) {
            return $client;
        }
    }
    return null;
}
function getActiveRouter()
{
    if (!tableExists('routers')) {
        return null;
    }
    return fetchOne("SELECT * FROM routers WHERE is_active = 1 ORDER BY name ASC");
}

function getAllRouters()
{
    if (!tableExists('routers')) {
        return [];
    }
    return fetchAll("SELECT * FROM routers ORDER BY name ASC");
}


// GenieACS functions
function genieacsGetDevices()
{
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return [];
    }

    $projection = [
        '_id',
        '_lastInform',
        '_deviceId',
        'DeviceID',
        'VirtualParameters.pppoeUsername',
        'VirtualParameters.pppoeUsername2',
        'VirtualParameters.gettemp',
        'VirtualParameters.RXPower',
        'VirtualParameters.pppoeIP',
        'VirtualParameters.IPTR069',
        'VirtualParameters.pppoeMac',
        'VirtualParameters.getponmode',
        'VirtualParameters.PonMac',
        'VirtualParameters.getSerialNumber',
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase',
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations',
        'VirtualParameters.activedevices',
        'VirtualParameters.getdeviceuptime'
    ];

    $query = json_encode(['_id' => ['$regex' => '']]);
    $projectionStr = implode(',', $projection);

    $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query) . '&projection=' . $projectionStr;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased timeout for larger datasets

    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch); // Deprecated in PHP 8.0+

    if ($httpCode === 200) {
        $devices = json_decode($response, true);
        return is_array($devices) ? $devices : [];
    }

    return [];
}

function genieacsGetDevice($serial)
{
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return null;
    }

    // Attempt 1: Search by Serial Number
    $query1 = json_encode(['_deviceId._SerialNumber' => $serial]);
    $url1 = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query1);

    $ch1 = curl_init($url1);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch1, CURLOPT_TIMEOUT, 10);
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch1, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response1 = curl_exec($ch1);
    $httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);

    if ($httpCode1 === 200) {
        $devices = json_decode($response1, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0];
        }
    }

    // Attempt 2: Search by _id (Exact match)
    // Using query parameter is safer than direct URL access for special chars
    $query2 = json_encode(['_id' => $serial]);
    $url2 = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query2);

    $ch2 = curl_init($url2);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch2, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);

    if ($httpCode2 === 200) {
        $devices = json_decode($response2, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0];
        }
    }

    // Attempt 3: Search by _id (Decoded)
    // Handles cases where ID was passed encoded (e.g. %2D instead of -)
    $decodedSerial = urldecode($serial);
    if ($decodedSerial !== $serial) {
        $query3 = json_encode(['_id' => $decodedSerial]);
        $url3 = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query3);

        $ch3 = curl_init($url3);
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch3, CURLOPT_TIMEOUT, 10);
        if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
            curl_setopt($ch3, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
        }

        $response3 = curl_exec($ch3);
        $httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);

        if ($httpCode3 === 200) {
            $devices = json_decode($response3, true);
            if (is_array($devices) && count($devices) > 0) {
                return $devices[0];
            }
        }
    }

    // Attempt 4: Search by PPPoE Username (VirtualParameters.pppoeUsername)
    // Since `customers.php` maps PPPoE Username to the `serial_number` column in the database,
    // this acts as a vital fallback for finding online status on the map.
    $query4 = json_encode(['VirtualParameters.pppoeUsername' => $serial]);
    $url4 = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query4);

    $ch4 = curl_init($url4);
    curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch4, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch4, CURLOPT_TIMEOUT, 10);
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch4, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response4 = curl_exec($ch4);
    $httpCode4 = curl_getinfo($ch4, CURLINFO_HTTP_CODE);

    if ($httpCode4 === 200) {
        $devices = json_decode($response4, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0];
        }
    }

    return null;
}

// Helper function to extract value from GenieACS parameter structure
function genieacsGetValue($device, $path)
{
    // Navigate through nested structure
    $keys = explode('.', $path);
    $current = $device;

    foreach ($keys as $key) {
        if (!is_array($current)) {
            return null;
        }

        // Try direct key access
        if (isset($current[$key])) {
            $current = $current[$key];
        } else {
            // Try numeric index pattern (e.g., LANDevice.1 -> LANDevice["1"])
            $found = false;
            foreach ($current as $k => $v) {
                if (strpos($k, $key) === 0) {
                    $current = $v;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return null;
            }
        }
    }

    // Extract value - GenieACS stores values in different formats
    if (is_array($current)) {
        // Try common value keys
        if (isset($current['_value'])) {
            return $current['_value'];
        }
        if (isset($current['value'])) {
            return $current['value'];
        }
        if (isset($current[0]) && is_string($current[0])) {
            return $current[0];
        }
    }

    return is_string($current) ? $current : null;
}

// Get device info summary from GenieACS
function genieacsGetDeviceInfo($serial)
{
    $device = genieacsGetDevice($serial);

    if (!$device) {
        return null;
    }

    $info = [
        'id' => $device['_id'] ?? $serial,
        'serial_number' => $serial,
        'last_inform' => $device['_lastInform'] ?? null,
        'status' => 'unknown',
        'uptime' => null,
        'manufacturer' => null,
        'model' => null,
        'software_version' => null,
        'rx_power' => null,
        'tx_power' => null,
        'ssid' => null,
        'wifi_password' => null,
        'ip_address' => null,
        'mac_address' => null,
        'total_associations' => null
    ];

    // Determine online status (last inform within 5 minutes)
    if ($info['last_inform']) {
        $lastInform = strtotime($info['last_inform']);
        $info['status'] = (time() - $lastInform) < 300 ? 'online' : 'offline';
    }

    // Extract common parameters using different possible paths
    // Device Manufacturer
    $info['manufacturer'] =
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.Manufacturer') ??
        genieacsGetValue($device, 'Device.DeviceInfo.Manufacturer') ??
        genieacsGetValue($device, 'DeviceID.Manufacturer');

    // Device Model
    $info['model'] =
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.ModelName') ??
        genieacsGetValue($device, 'Device.DeviceInfo.ModelName') ??
        genieacsGetValue($device, 'DeviceID.ProductClass');

    // Software Version
    $info['software_version'] =
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.SoftwareVersion') ??
        genieacsGetValue($device, 'Device.DeviceInfo.SoftwareVersion');

    // Uptime
    $info['uptime'] =
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.UpTime') ??
        genieacsGetValue($device, 'Device.DeviceInfo.UpTime');

    // WAN IP Address
    $info['ip_address'] =
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress') ??
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ExternalIPAddress');

    // MAC Address
    $info['mac_address'] =
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.MACAddress') ??
        genieacsGetValue($device, 'Device.Ethernet.Interface.1.MACAddress');

    // WiFi SSID - try multiple paths
    $info['ssid'] =
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID') ??
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WiFi.Radio.1.SSID') ??
        genieacsGetValue($device, 'Device.WiFi.SSID.1.SSID');

    // WiFi Password
    $info['wifi_password'] =
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase') ??
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase') ??
        genieacsGetValue($device, 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase');

    // PON Optical Power (for GPON/EPON ONUs)
    $info['rx_power'] =
        genieacsGetValue($device, 'VirtualParameters.RXPower') ??
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.RxPower') ??
        genieacsGetValue($device, 'Device.Optical.Interface.1.RXPower');

    $info['tx_power'] =
        genieacsGetValue($device, 'VirtualParameters.TXPower') ??
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.TxPower') ??
        genieacsGetValue($device, 'Device.Optical.Interface.1.TXPower');

    // Connected Devices / Total Associations (SSID 1 Only)
    $info['total_associations'] = genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations');

    return $info;
}

function genieacsSetParameter($serial, $parameter, $value)
{
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return ['success' => false, 'message' => 'GenieACS URL not configured'];
    }

    // Get device first to find the actual device ID
    $device = genieacsGetDevice($serial);
    if (!$device) {
        // If device lookup fails, return specific error
        return ['success' => false, 'message' => "Device lookup failed for: $serial"];
    }

    $deviceId = $device['_id'] ?? $serial;
    // Use rawurlencode and add timeout parameter (3000ms) to avoid hanging
    // This matches GACS implementation reference
    $encodedId = rawurlencode($deviceId);
    $url = rtrim($genieacs['url'], '/') . "/devices/{$encodedId}/tasks?timeout=3000&connection_request";

    $data = [
        'name' => 'setParameterValues', // Note: GACS uses setParameterValues, check if different from setParameter
        'parameterValues' => [
            [$parameter, (string)$value, 'xsd:string']
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10s > 3s GenieACS timeout

    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys

    if ($httpCode === 200 || $httpCode === 201 || $httpCode === 202) {
        return ['success' => true, 'message' => 'Task created successfully'];
    }

    if ($curlError) {
        return ['success' => false, 'message' => "Curl Error: $curlError"];
    }

    return ['success' => false, 'message' => "GenieACS Error ($httpCode): " . ($response ?: 'Unknown error')];
}

function genieacsSetParameterValues($serial, $params)
{
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return false;
    }

    // Get device first to find the actual device ID
    $device = genieacsGetDevice($serial);
    if (!$device) {
        return false;
    }

    $deviceId = $device['_id'] ?? $serial;
    $encodedId = rawurlencode($deviceId);
    $url = rtrim($genieacs['url'], '/') . "/devices/{$encodedId}/tasks?timeout=3000&connection_request";

    $parameterValues = [];
    foreach ($params as $key => $value) {
        $parameterValues[] = [$key, (string)$value, 'xsd:string'];
    }

    $data = [
        'name' => 'setParameterValues',
        'parameterValues' => $parameterValues
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return $httpCode === 200 || $httpCode === 201 || $httpCode === 202;
}
function calculateNextIsolationDateFromBillingDay(int $billingDay): string
{
    $today = new DateTime();

    $day = min($billingDay, (int)$today->format('t'));

    $date = new DateTime(
        $today->format('Y-m') . "-{$day}"
    );

    if ($date < $today) {
        $date->modify('+1 month');

        $day = min(
            $billingDay,
            (int)$date->format('t')
        );

        $date->setDate(
            (int)$date->format('Y'),
            (int)$date->format('m'),
            $day
        );
    }

    return $date->format('Y-m-d');
}
// Find device by PPPoE username in GenieACS
function genieacsFindDeviceByPppoe($pppoeUsername)
{
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return null;
    }

    // First, try to find device using VirtualParameters.pppoeUsername which is the most reliable approach
    $query = json_encode(['VirtualParameters.pppoeUsername' => $pppoeUsername]);
    $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys

    if ($httpCode === 200) {
        $devices = json_decode($response, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0]; // Return first matching device
        }
    }

    // If not found via VirtualParameters, try alternative approaches
    // Try searching for devices with PPPoE username in various possible locations
    $possibleQueries = [
        // Alternative VirtualParameters that might contain the username
        ['VirtualParameters.pppoeUsername2' => $pppoeUsername],
        // Common paths where username might be stored in standard parameters
        ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Username' => $pppoeUsername],
        ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username' => $pppoeUsername],
        ['Device.PPP.Interface.1.Credentials.Username' => $pppoeUsername],
        ['InternetGatewayDevice.PPPPEngine.PPPoE.UnicastDiscovery.Username' => $pppoeUsername],
        // If PPPoE username is stored as part of device name or description
        ['Device.DeviceInfo.Description' => $pppoeUsername],
        ['Device.DeviceInfo.FriendlyName' => $pppoeUsername]
    ];

    foreach ($possibleQueries as $query) {
        $encodedQuery = json_encode($query);
        $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($encodedQuery);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Add authentication if credentials are set
        if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys

        if ($httpCode === 200) {
            $devices = json_decode($response, true);
            if (is_array($devices) && count($devices) > 0) {
                return $devices[0]; // Return first matching device
            }
        }
    }

    // If no device found by searching parameters, try a more general search
    // Sometimes the PPPoE username might be stored in custom fields
    $generalQuery = urlencode('"' . $pppoeUsername . '"');
    $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . $generalQuery;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys

    if ($httpCode === 200) {
        $devices = json_decode($response, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0]; // Return first matching device
        }
    }

    return null;
}

// Reboot device via GenieACS
function genieacsReboot($serial)
{
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return false;
    }

    // Get device first to find the actual device ID
    $device = genieacsGetDevice($serial);
    if (!$device) {
        return false;
    }

    $deviceId = $device['_id'] ?? $serial;
    $url = rtrim($genieacs['url'], '/') . '/devices/' . urlencode($deviceId) . '/tasks?connection_request';

    $data = [
        'name' => 'reboot'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys

    return $httpCode === 200 || $httpCode === 201;
}
// Pagination
function paginate($table, $page = 1, $perPage = ITEMS_PER_PAGE, $where = '', $params = [])
{
    $offset = ($page - 1) * $perPage;

    // Get total
    $countSql = "SELECT COUNT(*) as total FROM {$table}";
    if ($where) {
        $countSql .= " WHERE {$where}";
    }
    $totalResult = fetchOne($countSql, $params);
    $total = $totalResult['total'] ?? 0;

    // Get data
    $dataSql = "SELECT * FROM {$table}";
    if ($where) {
        $dataSql .= " WHERE {$where}";
    }
    $perPage = (int) $perPage;
    $offset = (int) $offset;
    $dataSql .= " ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";

    $data = fetchAll($dataSql, $params);

    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage)
    ];
}

// Generate CSRF token
function generateCsrfToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getApiCsrfToken($input = null)
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' && is_array($input)) {
        $token = $input['csrf_token'] ?? '';
    }
    return is_string($token) ? $token : '';
}

function verifyApiCsrfToken($input = null)
{
    return verifyCsrfToken(getApiCsrfToken($input));
}

function requireApiCsrfToken($input = null)
{
    if (!verifyApiCsrfToken($input)) {
        jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 419);
    }
}

function getClientIpAddress()
{
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $value = trim((string) $_SERVER[$key]);
            if ($key === 'HTTP_X_FORWARDED_FOR' && strpos($value, ',') !== false) {
                $parts = explode(',', $value);
                $value = trim($parts[0]);
            }
            if ($value !== '') {
                return $value;
            }
        }
    }
    return 'unknown';
}

function getLoginThrottleStorePath()
{
    return __DIR__ . '/../logs/login_throttle.json';
}

function readLoginThrottleData()
{
    $file = getLoginThrottleStorePath();
    if (!file_exists($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function writeLoginThrottleData($data)
{
    $file = getLoginThrottleStorePath();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function buildLoginThrottleKey($scope, $identifier, $ip)
{
    $scopeValue = strtolower(trim((string) $scope));
    $identifierValue = strtolower(trim((string) $identifier));
    $ipValue = strtolower(trim((string) $ip));
    return hash('sha256', $scopeValue . '|' . $identifierValue . '|' . $ipValue);
}

function getLoginThrottleStatus($scope, $identifier, $maxAttempts = 5, $windowSeconds = 900, $blockSeconds = 900)
{
    $now = time();
    $ip = getClientIpAddress();
    $key = buildLoginThrottleKey($scope, $identifier, $ip);
    $data = readLoginThrottleData();
    $record = $data[$key] ?? ['attempts' => [], 'blocked_until' => 0];
    $attempts = array_values(array_filter($record['attempts'] ?? [], function ($ts) use ($now, $windowSeconds) {
        return is_numeric($ts) && (int) $ts > ($now - $windowSeconds);
    }));
    $blockedUntil = (int) ($record['blocked_until'] ?? 0);
    if (count($attempts) >= $maxAttempts && $blockedUntil < $now) {
        $blockedUntil = $now + $blockSeconds;
    }
    $record['attempts'] = $attempts;
    $record['blocked_until'] = $blockedUntil;
    $data[$key] = $record;
    writeLoginThrottleData($data);
    return [
        'blocked' => $blockedUntil > $now,
        'retry_after' => max(0, $blockedUntil - $now),
        'attempts' => count($attempts)
    ];
}

function addLoginFailure($scope, $identifier, $maxAttempts = 5, $windowSeconds = 900, $blockSeconds = 900)
{
    $now = time();
    $ip = getClientIpAddress();
    $key = buildLoginThrottleKey($scope, $identifier, $ip);
    $data = readLoginThrottleData();
    $record = $data[$key] ?? ['attempts' => [], 'blocked_until' => 0];
    $attempts = array_values(array_filter($record['attempts'] ?? [], function ($ts) use ($now, $windowSeconds) {
        return is_numeric($ts) && (int) $ts > ($now - $windowSeconds);
    }));
    $attempts[] = $now;
    $blockedUntil = (int) ($record['blocked_until'] ?? 0);
    if (count($attempts) >= $maxAttempts) {
        $blockedUntil = max($blockedUntil, $now + $blockSeconds);
    }
    $record['attempts'] = $attempts;
    $record['blocked_until'] = $blockedUntil;
    $data[$key] = $record;
    writeLoginThrottleData($data);
}

function clearLoginFailures($scope, $identifier)
{
    $ip = getClientIpAddress();
    $key = buildLoginThrottleKey($scope, $identifier, $ip);
    $data = readLoginThrottleData();
    if (isset($data[$key])) {
        unset($data[$key]);
        writeLoginThrottleData($data);
    }
}

// Check if admin is logged in
function isAdminLoggedIn()
{
    if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
        return false;
    }
    $loginTime = $_SESSION['admin']['login_time'] ?? null;
    if (is_numeric($loginTime) && (time() - (int) $loginTime) > 43200) {
        unset($_SESSION['admin']);
        return false;
    }
    return true;
}
// Check if agent is logged in
function isAgentLoggedIn()
{
    if (!isset($_SESSION['agent']['logged_in']) || $_SESSION['agent']['logged_in'] !== true) {
        return false;
    }
    $loginTime = $_SESSION['agent']['login_time'] ?? null;
    if (is_numeric($loginTime) && (time() - (int) $loginTime) > 43200) {
        unset($_SESSION['agent']);
        return false;
    }
    return true;
}

// Check if customer is logged in
function isCustomerLoggedIn()
{
    if (!isset($_SESSION['customer']['logged_in']) || $_SESSION['customer']['logged_in'] !== true) {
        return false;
    }
    $loginTime = $_SESSION['customer']['login_time'] ?? null;
    if (is_numeric($loginTime) && (time() - (int) $loginTime) > 43200) {
        unset($_SESSION['customer']);
        return false;
    }
    return true;
}

// Get current admin
function getCurrentAdmin()
{
    return $_SESSION['admin'] ?? null;
}
function getCurrentAgent()
{
    return $_SESSION['agent'] ?? null;
}

function getFiktifCustomers($onlyActive = false) {
    $sql = "SELECT c.* 
            FROM customers c 
            INNER JOIN fiktif_customers fc ON c.id = fc.customer_id";

    if ($onlyActive) {
        $sql .= " WHERE c.status = 'active'";
    }

    $sql .= " ORDER BY c.id DESC";

    return fetchAll($sql);
}
// Get current customer
function getCurrentCustomer()
{
    return $_SESSION['customer'] ?? null;
}
function activateCustomer($customerId)
{
    $execute = update('customers', ['status' => 'active'], 'id = ?', [$customerId]);
    return $execute;
}
function getRandomCustomer()
{
    // Select a random isolated customer whose PPPoE username contains 'ans',
    // ensure there are no invoices for the customer (any period).
    $sql = "SELECT * FROM customers
            WHERE pppoe_username LIKE ?
              AND status = 'isolated'
              AND NOT EXISTS (
                  SELECT 1 FROM invoices inv WHERE inv.customer_id = customers.id
              )
              AND address = 'Area Kasemen'
            ORDER BY RAND()
            LIMIT 1";

    // Use a broader pattern: '%ans%'
    $customer = fetchOne($sql, ['%ans%']);
    return $customer ?: null;
}

// JSON response
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Check if request is AJAX
function isAjax()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Get current URL
function getCurrentUrl()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// Format bytes to human readable format
function formatBytes($bytes, $precision = 2)
{
    $bytes = is_numeric($bytes) ? (float) $bytes : 0;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function getBackupDirectory()
{
    return __DIR__ . '/../backups/';
}

function ensureBackupDirectory()
{
    $backupDir = getBackupDirectory();
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0777, true);
    }
    return is_dir($backupDir);
}

function ensureCustomersAutoIsolateColumn()
{
    static $checked = false;
    if ($checked) {
        return true;
    }
    if (!tableExists('customers')) {
        return false;
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'auto_isolate'");
        $exists = $stmt && $stmt->rowCount() > 0;
        if (!$exists) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN auto_isolate TINYINT(1) NOT NULL DEFAULT 1 AFTER status");
        }
        $checked = true;
        return true;
    } catch (Exception $e) {
        logError('Ensure customers.auto_isolate failed: ' . $e->getMessage());
        return false;
    }
}

function sanitizeBackupFilename($filename)
{
    $name = basename((string) $filename);
    return preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $name) ? $name : '';
}

function listDatabaseBackups()
{
    if (!ensureBackupDirectory()) {
        return [];
    }
    $files = glob(getBackupDirectory() . 'backup_*.sql');
    if (!is_array($files)) {
        return [];
    }
    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });
    $result = [];
    foreach ($files as $file) {
        $result[] = [
            'name' => basename($file),
            'path' => $file,
            'size' => is_file($file) ? filesize($file) : 0,
            'modified_at' => is_file($file) ? date('Y-m-d H:i:s', filemtime($file)) : null,
            'timestamp' => is_file($file) ? filemtime($file) : 0
        ];
    }
    return $result;
}

function applyBackupRetention($retentionDays = 7)
{
    $days = (int) $retentionDays;
    if ($days < 1) {
        $days = 1;
    }
    $deleted = [];
    if (!ensureBackupDirectory()) {
        return $deleted;
    }
    $threshold = strtotime("-{$days} days");
    $files = glob(getBackupDirectory() . 'backup_*.sql');
    if (!is_array($files)) {
        return $deleted;
    }
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        if (filemtime($file) < $threshold) {
            if (@unlink($file)) {
                $deleted[] = basename($file);
            }
        }
    }
    return $deleted;
}

function createDatabaseBackup($retentionDays = 7)
{
    if (!ensureBackupDirectory()) {
        return ['success' => false, 'message' => 'Folder backup tidak bisa dibuat'];
    }
    $backupFile = getBackupDirectory() . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $command = sprintf(
        "mysqldump -h %s -u %s -p%s %s > %s",
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($backupFile)
    );
    exec($command, $output, $returnCode);
    if ($returnCode !== 0 || !file_exists($backupFile)) {
        logError('Database backup failed: ' . implode("\n", $output));
        logError('Backup command: ' . $command);
        return ['success' => false, 'message' => 'Gagal membuat backup database'];
    }
    $deletedFiles = applyBackupRetention($retentionDays);
    return [
        'success' => true,
        'message' => 'Backup database berhasil dibuat',
        'file_path' => $backupFile,
        'file_name' => basename($backupFile),
        'file_size' => filesize($backupFile),
        'deleted_files' => $deletedFiles
    ];
}

function restoreDatabaseBackup($filename)
{
    $safeName = sanitizeBackupFilename($filename);
    if ($safeName === '') {
        return ['success' => false, 'message' => 'Nama file backup tidak valid'];
    }
    if (!ensureBackupDirectory()) {
        return ['success' => false, 'message' => 'Folder backup tidak ditemukan'];
    }
    $backupFile = getBackupDirectory() . $safeName;
    if (!is_file($backupFile)) {
        return ['success' => false, 'message' => 'File backup tidak ditemukan'];
    }
    $command = sprintf(
        "mysql -h %s -u %s -p%s %s < %s",
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($backupFile)
    );
    exec($command, $output, $returnCode);
    if ($returnCode !== 0) {
        logError('Database restore failed: ' . implode("\n", $output));
        logError('Restore command: ' . $command);
        return ['success' => false, 'message' => 'Restore backup gagal dijalankan'];
    }
    return ['success' => true, 'message' => 'Restore backup berhasil', 'file_name' => $safeName];
}

function ensurePublicVoucherTables()
{
    static $checked = false;
    if ($checked) {
        return true;
    }
    $pdo = getDB();
    $sql = "CREATE TABLE IF NOT EXISTS hotspot_voucher_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(50) UNIQUE NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        profile_name VARCHAR(100) NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        payment_gateway VARCHAR(20) NOT NULL DEFAULT 'tripay',
        payment_method VARCHAR(100) DEFAULT NULL,
        payment_link TEXT,
        payment_reference VARCHAR(100) DEFAULT NULL,
        payment_payload LONGTEXT,
        status ENUM('pending','paid','failed','expired') DEFAULT 'pending',
        paid_at DATETIME DEFAULT NULL,
        voucher_username VARCHAR(100) DEFAULT NULL,
        voucher_password VARCHAR(100) DEFAULT NULL,
        voucher_generated_at DATETIME DEFAULT NULL,
        fulfillment_status ENUM('pending','success','failed') DEFAULT 'pending',
        fulfillment_error TEXT,
        whatsapp_status ENUM('pending','sent','failed') DEFAULT 'pending',
        whatsapp_sent_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try {
        $pdo->exec($sql);
        $checked = true;
        return true;
    } catch (Exception $e) {
        logError('Ensure hotspot_voucher_orders failed: ' . $e->getMessage());
        return false;
    }
}

function getInvoicesStatsThisMonth(){
    $sql = "SELECT status, COUNT(*) as count 
            FROM invoices 
            WHERE MONTH(due_date) = MONTH(CURDATE()) 
              AND YEAR(due_date) = YEAR(CURDATE()) 
            GROUP BY status";

    return fetchAll($sql);
}
function ensureInvoiceNotificationTables()
{
    static $checked = false;
    if ($checked) {
        return true;
    }
    $pdo = getDB();
    $sql = "CREATE TABLE IF NOT EXISTS invoice_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(50) NOT NULL,
        event VARCHAR(30) NOT NULL,
        whatsapp_status ENUM('pending','sent','failed') DEFAULT 'pending',
        whatsapp_sent_at DATETIME DEFAULT NULL,
        last_error TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_invoice_event (invoice_number, event)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try {
        $pdo->exec($sql);
        $checked = true;
        return true;
    } catch (Exception $e) {
        logError('Ensure invoice_notifications failed: ' . $e->getMessage());
        return false;
    }
}

function sendInvoicePaidWhatsapp($invoiceNumber, $gateway = '', $paymentData = [])
{
    if (!ensureInvoiceNotificationTables()) {
        return false;
    }
    $invoiceNumber = trim((string) $invoiceNumber);
    if ($invoiceNumber === '') {
        return false;
    }
    $existing = fetchOne("SELECT * FROM invoice_notifications WHERE invoice_number = ? AND event = ?", [$invoiceNumber, 'paid']);
    if ($existing && ($existing['whatsapp_status'] ?? '') === 'sent') {
        return true;
    }

    $invoice = fetchOne("SELECT i.*, c.name as customer_name, c.phone as customer_phone, p.name as package_name FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id LEFT JOIN packages p ON i.package_id = p.id WHERE i.invoice_number = ?", [$invoiceNumber]);
    if (!$invoice) {
        return false;
    }

    $phone = trim((string) ($invoice['customer_phone'] ?? ''));
    if ($phone === '') {
        return false;
    }

    $method = '';
    if (is_array($paymentData)) {
        $method = (string) ($paymentData['payment_method'] ?? ($paymentData['payment_type'] ?? ''));
    }
    if ($method === '') {
        $method = (string) ($invoice['payment_method'] ?? '');
    }
    if ($gateway === '') {
        $gateway = (string) ($invoice['payment_gateway'] ?? '');
    }

    $message = "Pembayaran Diterima\n\n";
    $message .= "Invoice: {$invoiceNumber}\n";
    $message .= "Nama: " . ($invoice['customer_name'] ?? '-') . "\n";
    if (!empty($invoice['package_name'])) {
        $message .= "Paket: " . $invoice['package_name'] . "\n";
    }
    $message .= "Total: " . formatCurrency($invoice['amount'] ?? 0) . "\n";
    $message .= "Metode: " . ($method !== '' ? $method : '-') . "\n";
    $message .= "Gateway: " . ($gateway !== '' ? strtoupper((string) $gateway) : '-') . "\n";
    $paidAt = $invoice['paid_at'] ?? '';
    if ($paidAt) {
        $message .= "Waktu: {$paidAt}\n";
    }
    $message .= "\nTerima kasih. Jika layanan sempat terisolir, sistem akan membuka otomatis.";
    $message .= getWhatsAppFooter();

    $sent = sendWhatsApp($phone, $message);
    if($sent) {
        logActivity('INVOICE_PAID_NOTIF', 'Pesan terkirim ke '.$phone);
    } else {
        logError('Gagal mengirim notifikasi pembayaran ke '. $phone);
    }

    $data = [
        'invoice_number' => $invoiceNumber,
        'event' => 'paid',
        'whatsapp_status' => $sent ? 'sent' : 'failed',
        'whatsapp_sent_at' => $sent ? date('Y-m-d H:i:s') : null,
        'last_error' => $sent ? null : 'sendWhatsApp failed'
    ];

    if ($existing) {
        update('invoice_notifications', [
            'whatsapp_status' => $data['whatsapp_status'],
            'whatsapp_sent_at' => $data['whatsapp_sent_at'],
            'last_error' => $data['last_error']
        ], 'id = ?', [(int) $existing['id']]);
    } else {
        insert('invoice_notifications', $data);
    }

    return $sent;
}

function getPublicVoucherCatalog()
{
    $profiles = mikrotikGetHotspotProfiles();
    $catalog = [];
    foreach ($profiles as $profile) {
        $name = trim((string) ($profile['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $onLogin = parseMikhmonOnLogin($profile['on-login'] ?? '');
        $price = (int) ($onLogin['selling_price'] ?? 0);
        if ($price <= 0) {
            $price = (int) ($onLogin['price'] ?? 0);
        }
        if ($price <= 0) {
            continue;
        }
        $catalog[] = [
            'profile_name' => $name,
            'display_name' => $name,
            'price' => $price,
            'validity' => $onLogin['validity'] ?? '-'
        ];
    }
    usort($catalog, function ($a, $b) {
        return ((int) $a['price']) <=> ((int) $b['price']);
    });
    return $catalog;
}

function findPublicVoucherPackage($catalog, $profileName)
{
    $target = trim((string) $profileName);
    foreach ($catalog as $item) {
        if (($item['profile_name'] ?? '') === $target) {
            return $item;
        }
    }
    return null;
}

function getPackageIdbyProfileName($profileName)
{
    $sql = "SELECT id FROM packages WHERE profile_normal LIKE ? OR profile_isolir LIKE ? LIMIT 1";
    $data = fetchOne($sql, ["%$profileName%", "%$profileName%"]);
    return $data['id'] ?? null;
}

function getProfileFromPackageId($id)
{
    $package = "SELECT * FROM packages where id = ?";
    $data = fetchOne($package, [$id]);
    return [
        'profile_normal' => $data['profile_normal'] ?? null,
        'profile_isolir' => $data['profile_isolir'] ?? null
    ];
}

function normalizePublicVoucherPhone($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '') {
        return '';
    }
    if (strpos($digits, '0') === 0) {
        return '62' . substr($digits, 1);
    }
    if (strpos($digits, '62') === 0) {
        return $digits;
    }
    return $digits;
}
function generateInvoicesThisMonth()
{
    $customers = fetchAll("SELECT * FROM customers WHERE status = 'active'");
    $generatedCount = 0;
    $currentMonth = date('Y-m');
    $firstDayOfMonth = $currentMonth . '-01';
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

    foreach ($customers as $customer) {
        // Cek berdasarkan due_date dalam rentang bulan ini
        $existingInvoice = fetchOne("
            SELECT id FROM invoices 
            WHERE customer_id = ? 
            AND due_date BETWEEN ? AND ?
            AND status IN ('paid', 'unpaid')",
            [$customer['id'], $firstDayOfMonth, $lastDayOfMonth]
        );

        if (!$existingInvoice) {
            $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);

            if ($package) {
                $dueDate = getCustomerDueDate($customer, $firstDayOfMonth);
                $invoiceData = [
                    'invoice_number' => generateInvoiceNumber($customer['id']),
                    'customer_id' => $customer['id'],
                    'amount' => $package['price'],
                    'status' => 'unpaid',
                    'due_date' => $dueDate,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                insert('invoices', $invoiceData);
                $generatedCount++;
            }

        }
    }
    return $generatedCount;
}

function generateScheduledPaidDate(string $dueDate, int $lateDays): string
{
    $roll = mt_rand(1, 100);

    if ($roll <= 30) {
        // 09:00 - 10:59
        $hour = mt_rand(9, 10);
    } elseif ($roll <= 65) {
        // 11:00 - 12:59
        $hour = mt_rand(11, 12);
    } elseif ($roll <= 90) {
        // 13:00 - 16:59
        $hour = mt_rand(13, 16);
    } else {
        // 17:00 - 18:59
        $hour = mt_rand(17, 18);
    }

    $minute = mt_rand(0, 59);
    $second = mt_rand(0, 59);

    $date = (new DateTime($dueDate))
        ->modify("+{$lateDays} days");

    $date->setTime($hour, $minute, $second);

    return $date->format('Y-m-d H:i:s');
}

function generateInvoicesForFiktifCustomers()
{
    $customers=getFiktifCustomers(true);
    $generatedCount=0;

    $currentMonth=date('Y-m');
    $firstDayOfMonth=$currentMonth.'-01';
    $lastDayOfMonth=date('Y-m-t',strtotime($firstDayOfMonth));

    foreach ($customers as $customer) {

        $existingInvoice=fetchOne(
            "SELECT id
            FROM invoices
            WHERE customer_id=?
            AND due_date BETWEEN ? AND ?
            AND status IN ('paid', 'unpaid')",
            [$customer['id'],$firstDayOfMonth,$lastDayOfMonth]
        );

        if ($existingInvoice) {
            continue;
        }

        $package=fetchOne(
            "SELECT *
            FROM packages
            WHERE id=?",
            [$customer['package_id']]
        );

        if (!$package) {
            continue;
        }

        $dueDate=getCustomerDueDate($customer,$firstDayOfMonth);

        $invoiceId=insert(
            'invoices',
            [
                'invoice_number'=>generateInvoiceNumber($customer['id']),
                'customer_id'=>$customer['id'],
                'amount'=>$package['price'],
                'status'=>'unpaid',
                'due_date'=>$dueDate,
                'created_at'=>date('Y-m-d H:i:s')
            ]
        );

        if (!$invoiceId) {
            continue;
        }

        $lateDays=generateLateDays();

        $scheduledPaidDate=generateScheduledPaidDate($dueDate,$lateDays);

        $ok=insert(
            'fiktif_invoices',
            [
                'invoice_id'=>$invoiceId,
                'late_days'=>$lateDays,
                'scheduled_paid_date'=>$scheduledPaidDate,
                'status'=>'unpaid'
            ]
        );

        if (!$ok) {
            writeLog(
                "Failed creating fiktif_invoices for invoice #{$invoiceId}",
                "ERROR"
            );
            continue;
        }

        $generatedCount++;
    }

    return $generatedCount;
}
function generatePublicVoucherOrderNumber()
{
    return 'VCR' . date('YmdHis') . strtoupper(generateRandomString(4, 'mixed'));
}

function createPublicVoucherOrder($payload)
{
    if (!ensurePublicVoucherTables()) {
        return ['success' => false, 'message' => 'Gagal menyiapkan tabel voucher publik'];
    }
    require_once __DIR__ . '/payment.php';
    $name = trim((string) ($payload['customer_name'] ?? ''));
    $phone = normalizePublicVoucherPhone($payload['customer_phone'] ?? '');
    $profileName = trim((string) ($payload['profile_name'] ?? ''));
    $amount = (int) ($payload['amount'] ?? 0);
    $gateway = strtolower(trim((string) ($payload['payment_gateway'] ?? 'tripay')));
    $paymentMethod = trim((string) ($payload['payment_method'] ?? ''));
    if ($name === '' || $phone === '' || $profileName === '' || $amount <= 0) {
        return ['success' => false, 'message' => 'Data order voucher tidak valid'];
    }
    if (!in_array($gateway, ['tripay', 'midtrans'], true)) {
        $gateway = 'tripay';
    }
    $orderNumber = '';
    for ($i = 0; $i < 5; $i++) {
        $candidate = generatePublicVoucherOrderNumber();
        $exists = fetchOne("SELECT id FROM hotspot_voucher_orders WHERE order_number = ?", [$candidate]);
        if (!$exists) {
            $orderNumber = $candidate;
            break;
        }
    }
    if ($orderNumber === '') {
        return ['success' => false, 'message' => 'Gagal membuat nomor order voucher'];
    }
    $payment = generatePaymentLink(
        $orderNumber,
        $amount,
        $name,
        $phone,
        date('Y-m-d', strtotime('+1 day')),
        $gateway,
        $paymentMethod
    );
    if (!($payment['success'] ?? false)) {
        return ['success' => false, 'message' => $payment['message'] ?? 'Gagal membuat link pembayaran'];
    }
    $paymentData = $payment['data'] ?? [];
    $paymentReference = null;
    if (is_array($paymentData)) {
        if ($gateway === 'tripay' && isset($paymentData['reference'])) {
            $paymentReference = (string) $paymentData['reference'];
        } elseif ($gateway === 'midtrans' && isset($paymentData['token'])) {
            $paymentReference = (string) $paymentData['token'];
        }
    }
    $insertId = insert('hotspot_voucher_orders', [
        'order_number' => $orderNumber,
        'customer_name' => $name,
        'customer_phone' => $phone,
        'profile_name' => $profileName,
        'amount' => $amount,
        'payment_gateway' => $gateway,
        'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
        'payment_link' => $payment['link'] ?? '',
        'payment_reference' => $paymentReference,
        'payment_payload' => is_array($paymentData) ? json_encode($paymentData) : null,
        'status' => 'pending',
        'fulfillment_status' => 'pending',
        'whatsapp_status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    if (!$insertId) {
        return ['success' => false, 'message' => 'Gagal menyimpan order voucher'];
    }
    return [
        'success' => true,
        'order_number' => $orderNumber,
        'payment_link' => $payment['link'] ?? '',
        'id' => $insertId
    ];
}

function sanitizePublicVoucherOrderNumber($orderNumber)
{
    $value = trim((string) $orderNumber);
    return preg_match('/^VCR[0-9]{14}[A-Za-z0-9]{4}$/', $value) ? strtoupper($value) : '';
}

function getPublicVoucherOrderByNumber($orderNumber)
{
    if (!ensurePublicVoucherTables()) {
        return null;
    }
    $safe = sanitizePublicVoucherOrderNumber($orderNumber);
    if ($safe === '') {
        return null;
    }
    return fetchOne("SELECT * FROM hotspot_voucher_orders WHERE order_number = ?", [$safe]);
}

function buildPublicVoucherMessage($order)
{
    $message = "Pembayaran voucher hotspot berhasil.\n\n";
    $message .= "No Order: " . ($order['order_number'] ?? '-') . "\n";
    $message .= "Profile: " . ($order['profile_name'] ?? '-') . "\n";
    $username = (string) ($order['voucher_username'] ?? '-');
    $password = (string) ($order['voucher_password'] ?? '-');
    if ($username !== '-' && $password !== '-' && $username === $password) {
        $message .= "Kode Voucher: " . $username . "\n";
        $message .= "Password: sama dengan kode voucher\n";
    } else {
        $message .= "Username: " . $username . "\n";
        $message .= "Password: " . $password . "\n";
    }
    $message .= "Nominal: " . formatCurrency($order['amount'] ?? 0) . "\n\n";
    $message .= "Simpan kode voucher ini dengan aman.";
    return $message;
}

function sendPublicVoucherWhatsapp($order)
{
    $phone = $order['customer_phone'] ?? '';
    if ($phone === '') {
        return false;
    }
    $message = buildPublicVoucherMessage($order);
    return sendWhatsApp($phone, $message);
}

function fulfillPublicVoucherOrder($orderNumber)
{
    if (!ensurePublicVoucherTables()) {
        return ['success' => false, 'message' => 'Tabel order voucher belum siap'];
    }
    $safe = sanitizePublicVoucherOrderNumber($orderNumber);
    if ($safe === '') {
        return ['success' => false, 'message' => 'Nomor order voucher tidak valid'];
    }
    $order = fetchOne("SELECT * FROM hotspot_voucher_orders WHERE order_number = ?", [$safe]);
    if (!$order) {
        return ['success' => false, 'message' => 'Order voucher tidak ditemukan'];
    }
    if (($order['status'] ?? '') !== 'paid') {
        return ['success' => false, 'message' => 'Order voucher belum lunas'];
    }
    if (!empty($order['voucher_username']) && !empty($order['voucher_password'])) {
        if (($order['whatsapp_status'] ?? 'pending') !== 'sent') {
            $waSent = sendPublicVoucherWhatsapp($order);
            update('hotspot_voucher_orders', [
                'whatsapp_status' => $waSent ? 'sent' : 'failed',
                'whatsapp_sent_at' => $waSent ? date('Y-m-d H:i:s') : null
            ], 'order_number = ?', [$safe]);
        }
        return ['success' => true, 'message' => 'Voucher sudah tersedia', 'order' => getPublicVoucherOrderByNumber($safe)];
    }
    $prefix = trim((string) getSetting('PUBLIC_VOUCHER_PREFIX', 'VCH-'));
    $numericOnly = (string) getSetting('PUBLIC_VOUCHER_CODE_TYPE', 'numeric') === 'numeric'
        || (int) getSetting('PUBLIC_VOUCHER_NUMERIC_ONLY', 0) === 1;
    $passwordSame = (string) getSetting('PUBLIC_VOUCHER_PASSWORD_MODE', 'same') === 'same'
        || (int) getSetting('PUBLIC_VOUCHER_PASSWORD_SAME', 0) === 1;
    if ($numericOnly) {
        $prefix = preg_replace('/\D+/', '', $prefix);
    }
    $length = (int) getSetting('PUBLIC_VOUCHER_LENGTH', 6);
    if ($length < 4) {
        $length = 4;
    }
    if ($length > 12) {
        $length = 12;
    }
    $profileName = trim((string) ($order['profile_name'] ?? ''));
    if ($profileName === '') {
        return ['success' => false, 'message' => 'Profile voucher tidak valid'];
    }
    $created = false;
    $username = '';
    $password = '';
    $errorMessage = '';
    for ($i = 0; $i < 20; $i++) {
        $seed = generateRandomString($length, $numericOnly ? 'numeric' : 'mixed');
        $username = $prefix . ($numericOnly ? $seed : strtoupper($seed));
        $password = $passwordSame ? $username : ($numericOnly ? $seed : strtoupper($seed));
        $comment = 'vc-public-voucher-' . $safe;
        if (mikrotikAddHotspotUser($username, $password, $profileName, ['comment' => $comment])) {
            $created = true;
            break;
        }
    }
    if (!$created) {
        $errorMessage = 'Gagal membuat voucher di MikroTik';
        update('hotspot_voucher_orders', [
            'fulfillment_status' => 'failed',
            'fulfillment_error' => $errorMessage
        ], 'order_number = ?', [$safe]);
        logError('Fulfill public voucher failed: ' . $safe);
        return ['success' => false, 'message' => $errorMessage];
    }
    update('hotspot_voucher_orders', [
        'voucher_username' => $username,
        'voucher_password' => $password,
        'voucher_generated_at' => date('Y-m-d H:i:s'),
        'fulfillment_status' => 'success',
        'fulfillment_error' => null
    ], 'order_number = ?', [$safe]);
    recordHotspotSale($username, $profileName, (int) $order['amount'], (int) $order['amount'], $prefix);
    $updatedOrder = getPublicVoucherOrderByNumber($safe);
    $waSent = false;
    if ($updatedOrder) {
        $waSent = sendPublicVoucherWhatsapp($updatedOrder);
    }
    update('hotspot_voucher_orders', [
        'whatsapp_status' => $waSent ? 'sent' : 'failed',
        'whatsapp_sent_at' => $waSent ? date('Y-m-d H:i:s') : null
    ], 'order_number = ?', [$safe]);
    return [
        'success' => true,
        'message' => $waSent ? 'Voucher berhasil dibuat dan dikirim ke WhatsApp' : 'Voucher berhasil dibuat, pengiriman WhatsApp gagal',
        'order' => getPublicVoucherOrderByNumber($safe)
    ];
}

function getPppoeUsernameByCustomerId($customerId)
{
    $customerId = (int) $customerId;
    if ($customerId <= 0) {
        return null;
    }
    $customer = fetchOne("SELECT pppoe_username FROM customers WHERE id = ?", [$customerId]);
    return $customer['pppoe_username'] ?? null;
}
// function customerRenameUsername($customerId, $newUsername)
// {
//     $customerId = (int) $customerId;
//     if ($customerId <= 0) {
//         return false;
//     }
//     $newUsername = trim((string) $newUsername);
//     if ($newUsername === '') {
//         return false;
//     }
//     $existing = fetchOne("SELECT id FROM customers WHERE pppoe_username = ? AND id != ?", [$newUsername, $customerId]);
//     if ($existing) {
//         return false;
//     }
//     logActivity("Customer ID {$customerId} rename PPPoE username to '{$newUsername}'");
//     return update('customers', ['pppoe_username' => $newUsername], 'id = ?', [$customerId]);
// }
function customerRenameUsernameByUsername($oldUsername, $newUsername)
{
    $oldUsername = trim((string) $oldUsername);
    $newUsername = trim((string) $newUsername);
    if ($oldUsername === '' || $newUsername === '') {
        return false;
    }
    $existing = fetchOne("SELECT id FROM customers WHERE pppoe_username = ?", [$newUsername]);
    if ($existing) {
        return false;
    }
    logActivity("Customer with PPPoE username '{$oldUsername}' rename to '{$newUsername}'");
    return update('customers', ['pppoe_username' => $newUsername], 'pppoe_username = ?', [$oldUsername]);
}
function markPublicVoucherOrderPaid($orderNumber, $gateway, $paymentData = [])
{
    if (!ensurePublicVoucherTables()) {
        return false;
    }
    $safe = sanitizePublicVoucherOrderNumber($orderNumber);
    if ($safe === '') {
        return false;
    }
    $order = fetchOne("SELECT * FROM hotspot_voucher_orders WHERE order_number = ?", [$safe]);
    if (!$order) {
        return false;
    }
    $paymentMethod = $paymentData['payment_method'] ?? ($paymentData['payment_type'] ?? null);
    $paymentRef = $paymentData['reference'] ?? ($paymentData['transaction_id'] ?? null);
    update('hotspot_voucher_orders', [
        'status' => 'paid',
        'paid_at' => $order['paid_at'] ?: date('Y-m-d H:i:s'),
        'payment_gateway' => $gateway,
        'payment_method' => $paymentMethod ?: ($order['payment_method'] ?? null),
        'payment_reference' => $paymentRef ?: ($order['payment_reference'] ?? null),
        'payment_payload' => json_encode($paymentData)
    ], 'order_number = ?', [$safe]);
    $result = fulfillPublicVoucherOrder($safe);
    return $result['success'] ?? false;
}

function markPublicVoucherOrderFailed($orderNumber, $status, $paymentData = [])
{
    if (!ensurePublicVoucherTables()) {
        return false;
    }
    $safe = sanitizePublicVoucherOrderNumber($orderNumber);
    if ($safe === '') {
        return false;
    }
    $order = fetchOne("SELECT * FROM hotspot_voucher_orders WHERE order_number = ?", [$safe]);
    if (!$order) {
        return false;
    }
    if (($order['status'] ?? '') === 'paid') {
        return true;
    }
    $failedStatus = strtolower((string) $status) === 'expired' ? 'expired' : 'failed';
    return update('hotspot_voucher_orders', [
        'status' => $failedStatus,
        'payment_payload' => json_encode($paymentData)
    ], 'order_number = ?', [$safe]);
}

function syncPublicVoucherOrderPaymentStatus($orderNumber)
{
    $order = getPublicVoucherOrderByNumber($orderNumber);
    if (!$order) {
        return null;
    }
    if (($order['status'] ?? '') === 'paid') {
        if (($order['fulfillment_status'] ?? '') !== 'success' || ($order['whatsapp_status'] ?? 'pending') !== 'sent') {
            fulfillPublicVoucherOrder($order['order_number']);
            $order = getPublicVoucherOrderByNumber($order['order_number']);
        }
        return $order;
    }
    require_once __DIR__ . '/payment.php';
    $gateway = strtolower((string) ($order['payment_gateway'] ?? 'tripay'));
    $payload = [];
    $status = '';
    if ($gateway === 'midtrans') {
        $result = getMidtransPaymentStatus($order['order_number']);
        if (!($result['success'] ?? false)) {
            return $order;
        }
        $payload = $result['data'] ?? [];
        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = $payload['data'];
        }
        $status = strtolower((string) ($payload['transaction_status'] ?? ''));
        if ($status === 'settlement' || $status === 'capture') {
            markPublicVoucherOrderPaid($order['order_number'], 'midtrans', $payload);
        } elseif (in_array($status, ['expire', 'cancel', 'deny'], true)) {
            markPublicVoucherOrderFailed($order['order_number'], $status, $payload);
        }
    } else {
        $result = getTripayPaymentStatus($order['order_number']);
        if (!($result['success'] ?? false)) {
            return $order;
        }
        $payload = $result['data'] ?? [];
        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = $payload['data'];
        }
        $status = strtoupper((string) ($payload['status'] ?? ''));
        if ($status === 'PAID') {
            markPublicVoucherOrderPaid($order['order_number'], 'tripay', $payload);
        } elseif (in_array($status, ['EXPIRED', 'FAILED'], true)) {
            markPublicVoucherOrderFailed($order['order_number'], strtolower($status), $payload);
        }
    }
    return getPublicVoucherOrderByNumber($order['order_number']);
}