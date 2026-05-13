<?php
/**
 * Payment Gateway Integration
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function paymentLog($event, $data)
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $line = json_encode([
        'ts' => date('Y-m-d H:i:s'),
        'event' => (string) $event,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    @file_put_contents($logDir . '/payment_gateway.log', $line . PHP_EOL, FILE_APPEND);
}

function paymentGetConfig($key, $default = '')
{
    if (function_exists('getSetting')) {
        return getSetting($key, $default);
    }

    if (defined($key)) {
        $value = constant($key);
        if ($value !== '') {
            return $value;
        }
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && ($row['setting_value'] ?? '') !== '') {
            return $row['setting_value'];
        }
    } catch (Exception $_) {
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && ($row['setting_value'] ?? '') !== '') {
            return $row['setting_value'];
        }
    } catch (Exception $_) {
    }

    return $default;
}

function paymentGetTripayBaseUrl()
{
    $candidates = paymentGetTripayCandidateBaseUrls();
    return $candidates[0] ?? 'https://tripay.co.id/api';
}

function paymentGetTripayCandidateBaseUrls()
{
    $mode = strtolower(trim((string) paymentGetConfig('TRIPAY_MODE', '')));
    if (strpos($mode, 'sandbox') !== false) {
        return ['https://tripay.co.id/api-sandbox'];
    }
    return ['https://tripay.co.id/api'];
}

function paymentNormalizeTripayMethod($code)
{
    $value = strtoupper(trim((string) $code));
    if ($value === '') {
        return 'QRIS';
    }

    $legacyMap = [
        'VIRTUAL_ACCOUNT_BCA' => 'BCAVA',
        'VIRTUAL_ACCOUNT_BRI' => 'BRIVA',
        'VIRTUAL_ACCOUNT_MANDIRI' => 'MANDIRIVA',
        'VIRTUAL_ACCOUNT_BNI' => 'BNIVA',
        'EWALLET_OVO' => 'OVO',
        'EWALLET_DANA' => 'DANA',
        'EWALLET_LINKAJA' => 'LINKAJA',
        'EWALLET_SHOPEEPAY' => 'SHOPEEPAY',
        'QRIS' => 'QRIS',
        'ALFAMART' => 'ALFAMART',
        'INDOMARET' => 'INDOMARET'
    ];

    return $legacyMap[$value] ?? $value;
}

function tripayPickDefaultEnabledMethod(array $enabledChannels): string
{
    $enabledMap = array_fill_keys(array_map(static function ($channel) {
        return strtoupper(trim((string) $channel));
    }, $enabledChannels), true);

    $preferredOrder = [
        'QRIS',
        'BCAVA',
        'BRIVA',
        'MANDIRIVA',
        'BNIVA',
        'OVO',
        'DANA',
        'LINKAJA',
        'SHOPEEPAY',
        'ALFAMART',
        'INDOMARET'
    ];

    foreach ($preferredOrder as $channel) {
        if (isset($enabledMap[$channel])) {
            return $channel;
        }
    }

    return $enabledChannels[0] ?? '';
}

function paymentFallbackEmailFromPhone($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    $host = parse_url(APP_URL, PHP_URL_HOST);
    
    // Handle localhost - use a proper fallback domain
    if (!$host || $host === 'localhost' || strpos($host, '127.0.0.1') !== false) {
        $host = 'ansradius.id';
    }
    
    // Remove invalid characters from host (only keep alphanumeric and dots/hyphens)
    $host = preg_replace('/[^a-zA-Z0-9.-]/', '', $host);
    if ($host === '') {
        $host = 'ansradius.id';
    }
    
    if ($digits !== '' && strlen($digits) >= 7) {
        // Use last 10 digits of phone number for uniqueness
        $phoneDigits = substr($digits, -10);
        return 'cust' . $phoneDigits . '@' . $host;
    }
    return 'customer' . (time() % 100000) . '@' . $host;
}

function paymentTripayRequest($path, $method, $apiKey, $payload = null, $baseUrl = null)
{
    $base = $baseUrl !== null ? (string) $baseUrl : paymentGetTripayBaseUrl();
    $url = rtrim($base, '/') . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    if (defined('CURLOPT_POSTREDIR')) {
        $postRedir = defined('CURL_REDIR_POST_ALL') ? CURL_REDIR_POST_ALL : 7;
        curl_setopt($ch, CURLOPT_POSTREDIR, $postRedir);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ]);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES) : (string) $payload);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    unset($ch);

    return [
        'url' => $url,
        'effective_url' => $effectiveUrl,
        'http_code' => $httpCode,
        'redirect_count' => $redirectCount,
        'content_type' => $contentType,
        'error' => $error,
        'raw' => $response,
        'json' => json_decode((string) $response, true)
    ];
}

function paymentMidtransSnapBaseUrl()
{
    $mode = strtolower(trim((string) paymentGetConfig('MIDTRANS_MODE', paymentGetConfig('MIDTRANS_ENV', 'production'))));
    $isSandbox = strpos($mode, 'sandbox') !== false;
    return $isSandbox ? 'https://app.sandbox.midtrans.com' : 'https://app.midtrans.com';
}

// Generate payment link based on gateway
function generatePaymentLink($invoiceNumber, $amount, $customerName, $customerPhone, $dueDate, $gateway = 'tripay', $paymentMethod = '') {
    switch ($gateway) {
        case 'tripay':
            return generateTripayPaymentLink($invoiceNumber, $amount, $customerName, $customerPhone, $dueDate, $paymentMethod);
            
        case 'midtrans':
            return generateMidtransPaymentLink($invoiceNumber, $amount, $customerName, $customerPhone, $dueDate, $paymentMethod);
            
        default:
            return [
                'success' => false,
                'message' => 'Payment gateway not supported',
                'link' => null
            ];
    }
}

// Tripay Payment Link Generator
function generateTripayPaymentLink($invoiceNumber, $amount, $customerName, $customerPhone, $dueDate, $paymentMethod = '') {
    $apiKey = trim((string) paymentGetConfig('TRIPAY_API_KEY', ''));
    $merchantCode = trim((string) paymentGetConfig('TRIPAY_MERCHANT_CODE', ''));
    $privateKey = trim((string) paymentGetConfig('TRIPAY_PRIVATE_KEY', ''));
    if ($apiKey === '' || $merchantCode === '' || $privateKey === '') {
        return [
            'success' => false,
            'message' => 'Payment gateway not configured',
            'link' => null
        ];
    }

    $merchantRef = (string) $invoiceNumber;
    $amountInt = (int) $amount;

    $enabledChannels = getTripayEnabledPaymentChannels();
    if ($paymentMethod === '') {
        $method = tripayPickDefaultEnabledMethod($enabledChannels);
    } else {
        $method = paymentNormalizeTripayMethod($paymentMethod);
    }

    if ($method === '') {
        return [
            'success' => false,
            'message' => 'Tidak ada payment channel Tripay yang aktif untuk merchant ini',
            'link' => null
        ];
    }

    if (!empty($enabledChannels) && !in_array($method, $enabledChannels, true)) {
        return [
            'success' => false,
            'message' => 'Payment channel is not enabled (' . $method . '). Please enable in Merchant > Opsi > Atur Channel Pembayaran',
            'link' => null
        ];
    }

    $expiredTime = time() + (24 * 60 * 60);
    $dueTs = strtotime((string) $dueDate);
    if ($dueTs !== false && $dueTs > time()) {
        $expiredTime = min($expiredTime, (int) $dueTs);
    }
    $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $amountInt, $privateKey);

    $payload = [
        'method' => $method,
        'merchant_ref' => $merchantRef,
        'amount' => $amountInt,
        'customer_name' => (string) $customerName,
        'customer_email' => paymentFallbackEmailFromPhone($customerPhone),
        'customer_phone' => (string) $customerPhone,
        'order_items' => [
            [
                'sku' => $merchantRef,
                'name' => 'Pembayaran ' . $merchantRef,
                'price' => $amountInt,
                'quantity' => 1
            ]
        ],
        'expired_time' => $expiredTime,
        'callback_url' => rtrim(APP_URL, '/') . '/webhooks/tripay.php',
        'signature' => $signature
    ];

    $usePretty = (string) paymentGetConfig('USE_PRETTY_URLS', '1') === '1';
    if (preg_match('/^VCR/i', $merchantRef)) {
        $payload['return_url'] = rtrim(APP_URL, '/') . ($usePretty
            ? ('/voucher/status/' . rawurlencode($merchantRef))
            : ('/voucher-status.php?order=' . rawurlencode($merchantRef))
        );
    } else {
        $payload['return_url'] = rtrim(APP_URL, '/') . '/portal/dashboard.php';
    }

    $result = paymentTripayRequest('/transaction/create', 'POST', $apiKey, $payload);
    $json = $result['json'] ?? null;
    if (!is_array($json) || !($json['success'] ?? false)) {
        $message = is_array($json) ? (string) ($json['message'] ?? '') : '';
        if ($message === '') {
            $message = 'Gagal membuat transaksi Tripay';
        }

        paymentLog('tripay_create_failed', [
            'order' => $merchantRef,
            'mode' => (string) paymentGetConfig('TRIPAY_MODE', ''),
            'method' => $method,
            'url' => (string) ($result['effective_url'] ?? ($result['url'] ?? '')),
            'http_code' => (int) ($result['http_code'] ?? 0),
            'redirects' => (int) ($result['redirect_count'] ?? 0),
            'content_type' => (string) ($result['content_type'] ?? ''),
            'error' => (string) ($result['error'] ?? ''),
            'message' => $message,
            'raw' => mb_substr((string) ($result['raw'] ?? ''), 0, 800),
            'data' => is_array($json) ? $json : null
        ]);

        return ['success' => false, 'message' => $message, 'link' => null, 'data' => is_array($json) ? $json : null];
    }

    $data = $json['data'] ?? [];
    $checkoutUrl = $data['checkout_url'] ?? '';
    if ($checkoutUrl === '') {
        return ['success' => false, 'message' => 'Tripay tidak mengembalikan checkout_url', 'link' => null];
    }

    return ['success' => true, 'link' => $checkoutUrl, 'data' => $data];
}

// Midtrans Payment Link Generator
function generateMidtransPaymentLink($invoiceNumber, $amount, $customerName, $customerPhone, $dueDate, $paymentMethod = '') {
    $serverKey = trim((string) paymentGetConfig('MIDTRANS_API_KEY', ''));
    if ($serverKey === '') {
        return [
            'success' => false,
            'message' => 'Payment gateway not configured',
            'link' => null
        ];
    }

    $baseUrl = rtrim(paymentMidtransSnapBaseUrl(), '/');
    $url = $baseUrl . '/snap/v1/transactions';

    $orderId = (string) $invoiceNumber;
    $amountInt = (int) $amount;

    $durationHours = 24;
    $dueTs = strtotime((string) $dueDate);
    if ($dueTs !== false && $dueTs > time()) {
        $diffHours = (int) ceil(((int) $dueTs - time()) / 3600);
        if ($diffHours > 0 && $diffHours < $durationHours) {
            $durationHours = $diffHours;
        }
    }

    $payload = [
        'transaction_details' => [
            'order_id' => $orderId,
            'gross_amount' => $amountInt
        ],
        'customer_details' => [
            'first_name' => (string) $customerName,
            'phone' => (string) $customerPhone
        ],
        'item_details' => [
            [
                'id' => $orderId,
                'price' => $amountInt,
                'quantity' => 1,
                'name' => 'Pembayaran ' . $orderId
            ]
        ],
        'expiry' => [
            'start_time' => date('Y-m-d H:i:s O'),
            'unit' => 'hour',
            'duration' => $durationHours
        ]
    ];

    // Add email only if it's valid format
    $email = paymentFallbackEmailFromPhone($customerPhone);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $payload['customer_details']['email'] = $email;
    }

    if ($paymentMethod !== '') {
        $payload['enabled_payments'] = [(string) $paymentMethod];
    }

    $usePretty = (string) paymentGetConfig('USE_PRETTY_URLS', '1') === '1';
    if (preg_match('/^VCR/i', $orderId)) {
        $payload['callbacks'] = ['finish' => rtrim(APP_URL, '/') . ($usePretty
            ? ('/voucher/status/' . rawurlencode($orderId))
            : ('/voucher-status.php?order=' . rawurlencode($orderId))
        )];
    } else {
        $payload['callbacks'] = ['finish' => rtrim(APP_URL, '/') . '/portal/dashboard.php'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($serverKey . ':')
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    unset($ch);

    // Log curl errors
    if ($curlErrno !== 0) {
        logError("Midtrans CURL Error ({$curlErrno}): {$curlError}");
        return ['success' => false, 'message' => "Koneksi Midtrans gagal: {$curlError}", 'link' => null];
    }

    if ($httpCode !== 201 && $httpCode !== 200) { 
        if($httpCode === 400 && (strpos((string)$response, 'order_id sudah digunakan') !== false || strpos((string)$response, 'order_id has already been taken') !== false)) {
            $redirectUrl = getRedirectUrl($orderId);

            if(!$redirectUrl) {
                logError("Midtrans order_id already used but no redirect_url found for order_id: {$orderId}");
                return ['success' => false, 'message' => 'Midtrans error: order_id sudah digunakan, namun tidak ditemukan link pembayaran terkait. Silakan hubungi support.', 'link' => null];
            }
            return ['success' => true, 'link' => $redirectUrl, 'data' => null];
        }
        logError("Midtrans HTTP {$httpCode}: " . substr((string)$response, 0, 500));
        return ['success' => false, 'message' => "Midtrans error (HTTP {$httpCode}): " . substr((string)$response, 0, 100), 'link' => null];
    }
    
    $json = json_decode((string) $response, true);
    if (!is_array($json)) {
        logError("Midtrans invalid JSON response: " . substr((string)$response, 0, 500));
        return ['success' => false, 'message' => 'Response Midtrans tidak valid (JSON error)', 'link' => null];
    }
    
    $redirectUrl = $json['redirect_url'] ?? '';
    logError(json_encode($json));
    if ($redirectUrl === '') {
        logError("Midtrans no redirect_url: " . json_encode($json));
        return ['success' => false, 'message' => 'Midtrans error: ' . ($json['status_message'] ?? 'Tidak ada redirect_url'), 'link' => null];
    }

    $stored = storeRedirectUrl($invoiceNumber, $redirectUrl);
    if (!$stored) {
        logError("Failed to store Midtrans redirect URL for order_id: {$invoiceNumber}");
    }
    return ['success' => true, 'link' => $redirectUrl, 'data' => $json];
}

// Get supported payment gateways
function getPaymentGateways() {
    return [
        [
            'id' => 'tripay',
            'name' => 'Tripay',
            'icon' => 'fa-credit-card',
            'color' => '#00f5ff',
            'description' => 'Payment gateway populer Indonesia',
            'features' => ['QRIS', 'Virtual Account', 'VA'],
            'supported_channels' => ['QRIS', 'VA', 'Bank Transfer']
        ],
        [
            'id' => 'midtrans',
            'name' => 'Midtrans',
            'icon' => 'fa-credit-card',
            'color' => '#667eea',
            'description' => 'Payment gateway populer Indonesia',
            'features' => ['QRIS', 'Virtual Account', 'VA', 'Bank Transfer'],
            'supported_channels' => ['QRIS', 'VA', 'Bank Transfer']
        ]
    ];
}

// Send payment reminder via WhatsApp
function sendPaymentReminder($invoiceNumber, $amount, $customerName, $customerPhone, $dueDate) {
    $message = "Halo {$customerName},\n\n";
    $message .= "No Invoice: {$invoiceNumber}\n";
    $message .= "Tagihan internet Anda akan jatuh tempo pada " . formatDate($dueDate) . "\n\n";
    $message .= "Nominal: " . formatCurrency($amount) . "\n\n";
    $message .= "Mohon segera lakukan pembayaran untuk mengaktifkan kembali koneksi internet Anda.\n\n";
    $message .= "Terima kasih.";
    if (function_exists('getWhatsAppFooter')) {
        $message .= getWhatsAppFooter();
    }
    
    return sendWhatsApp($customerPhone, $message);
}

// Get payment status from Tripay
function getTripayPaymentStatus($merchantRef) {
    $apiKey = trim((string) paymentGetConfig('TRIPAY_API_KEY', ''));
    if ($apiKey === '') {
        return ['success' => false, 'message' => 'API Key not configured'];
    }

    $query = http_build_query([
        'merchant_ref' => (string) $merchantRef,
        'sort' => 'desc',
        'per_page' => 1
    ]);
    $result = paymentTripayRequest('/merchant/transactions?' . $query, 'GET', $apiKey);
    $json = $result['json'] ?? null;
    if (!is_array($json) || !($json['success'] ?? false)) {
        return ['success' => false, 'message' => is_array($json) ? ($json['message'] ?? 'Failed to get payment status') : 'Failed to get payment status'];
    }

    $transaction = null;
    if (isset($json['data']['data'][0]) && is_array($json['data']['data'][0])) {
        $transaction = $json['data']['data'][0];
    } elseif (isset($json['data'][0]) && is_array($json['data'][0])) {
        $transaction = $json['data'][0];
    }

    if (!$transaction) {
        return ['success' => false, 'message' => 'Transaction not found'];
    }

    return ['success' => true, 'data' => ['data' => $transaction]];
}

function tripayCollectEnabledChannels($node, array &$channels): void
{
    if (!is_array($node)) {
        return;
    }

    $channelCode = $node['code'] ?? $node['channel_code'] ?? $node['method'] ?? null;
    if (is_string($channelCode) && $channelCode !== '') {
        $isEnabled = true;
        foreach (['is_active', 'active', 'enabled', 'enable'] as $flagKey) {
            if (!array_key_exists($flagKey, $node)) {
                continue;
            }

            $flagValue = $node[$flagKey];
            if (is_bool($flagValue) && $flagValue === false) {
                $isEnabled = false;
            } elseif (is_numeric($flagValue) && (int) $flagValue === 0) {
                $isEnabled = false;
            } elseif (is_string($flagValue) && in_array(strtolower(trim($flagValue)), ['0', 'false', 'disabled', 'inactive', 'off', 'no'], true)) {
                $isEnabled = false;
            }
        }

        if ($isEnabled) {
            $channels[strtoupper(trim($channelCode))] = true;
        }
    }

    foreach ($node as $value) {
        if (is_array($value)) {
            tripayCollectEnabledChannels($value, $channels);
        }
    }
}

function getTripayEnabledPaymentChannels(): array
{
    $apiKey = trim((string) paymentGetConfig('TRIPAY_API_KEY', ''));
    if ($apiKey === '') {
        return [];
    }

    $result = paymentTripayRequest('/merchant/payment-channel', 'GET', $apiKey);
    $json = $result['json'] ?? null;
    if (!is_array($json) || !($json['success'] ?? false)) {
        return [];
    }

    $channels = [];
    tripayCollectEnabledChannels($json, $channels);
    return array_keys($channels);
}

function filterTripayPaymentMethods(array $methods): array
{
    $enabledChannels = getTripayEnabledPaymentChannels();
    if (empty($enabledChannels)) {
        return $methods;
    }

    $enabledMap = array_fill_keys($enabledChannels, true);
    return array_values(array_filter($methods, static function ($method) use ($enabledMap) {
        $code = strtoupper(trim((string) ($method['code'] ?? '')));
        return $code !== '' && isset($enabledMap[$code]);
    }));
}

// Get payment status from Midtrans
function getMidtransPaymentStatus($orderId) {
    // Note: MIDTRANS_API_KEY should contain your Server Key
    $serverKey = trim((string) paymentGetConfig('MIDTRANS_API_KEY', ''));
    if ($serverKey === '') {
        return ['success' => false, 'message' => 'API Key not configured'];
    }
    
    // Correct Midtrans status endpoint does not include merchant code
    $url = "https://api.midtrans.com/v2/{$orderId}/status";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($serverKey . ':')
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    unset($ch);
    
    if ($curlErrno !== 0) {
        logError("Midtrans status check CURL error ({$curlErrno}): {$curlError}");
        return ['success' => false, 'message' => "CURL Error: {$curlError}"];
    }

    if ($httpCode !== 200) {
        logError("Midtrans status check HTTP {$httpCode}: " . substr((string)$response, 0, 500));
        return ['success' => false, 'message' => "HTTP {$httpCode}: " . substr((string)$response, 0, 100)];
    }

    $json = json_decode((string) $response, true);
    if (!is_array($json)) {
        logError("Midtrans status invalid JSON: " . substr((string)$response, 0, 500));
        return ['success' => false, 'message' => 'Invalid JSON response'];
    }
    
    return ['success' => true, 'data' => $json];
}
