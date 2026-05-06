<?php

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', '');
define('DB_USER', 'root');
define('DB_PASS', '');

define('RADIUS_DB_HOST', 'localhost');
define('RADIUS_DB_NAME', 'radius_db');
define('RADIUS_DB_USER', 'root');
define('RADIUS_DB_PASS', '');

// MikroTik Configuration
define('MIKROTIK_HOST', '');
define('MIKROTIK_USER', '');
define('MIKROTIK_PASS', '');
define('MIKROTIK_PORT', null);

// Application Configuration
define('APP_NAME', 'ANS Radius');
if (php_sapi_name() !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $scriptDir = preg_replace('#/(admin|api|portal|cron|webhooks|install_steps|includes|sales|templates|technician)$#', '', $scriptDir);
    $scriptDir = rtrim($scriptDir, '/');
    define('APP_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $scriptDir);
} else {
    define('APP_URL', 'http://localhost');
}
define('APP_VERSION', '2.0.6');
define('GEMBOK_UPDATE_VERSION_URL', 'https://raw.githubusercontent.com/alijayanet/gembok-simple/main/version.txt');

// Pagination
define('ITEMS_PER_PAGE', 20);
define('INVOICE_PREFIX', 'INV');
define('INVOICE_START', 1);
// Security
define('ENCRYPTION_KEY', '732788ede1926e10e87fc31db1cdef3757421a79b01263555ddb1079c1109f44');

// WhatsApp Configuration
define('WHATSAPP_API_URL', '');
define('WHATSAPP_TOKEN', '');

// Tripay Configuration
define('TRIPAY_API_KEY', '');
define('TRIPAY_PRIVATE_KEY', '');
define('TRIPAY_MERCHANT_CODE', '');

// Telegram Configuration
define('TELEGRAM_BOT_TOKEN', '');

// GenieACS Configuration
define('GENIEACS_URL', 'http://localhost:7557');
define('GENIEACS_USERNAME', '');
define('GENIEACS_PASSWORD', '');