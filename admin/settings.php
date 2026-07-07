<?php
/**
 * Admin Settings - Elegant Dark Minimalis Theme
 * 
 * @package Admin
 * @author ANS Team
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Settings';
$workdir = 'admin/settings.php';



// Get current settings dengan error handling
$settings = [];
try {
    $settingsData = fetchAll("SELECT * FROM settings");
    foreach ($settingsData as $s) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }
} catch (Exception $e) {
    logError('Failed to load settings: ' . $e->getMessage());
    setFlash('error', 'Gagal memuat pengaturan');
}

// Get site settings untuk landing page
$siteSettings = [];
try {
    $pdo = getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $siteSettingsData = fetchAll("SELECT * FROM site_settings");
    foreach ($siteSettingsData as $s) {
        $siteSettings[$s['setting_key']] = $s['setting_value'];
    }
} catch (Exception $e) {
    logError('Failed to load site settings: ' . $e->getMessage());
    $siteSettings = [];
}

// Get FAQs
$faqs = [];
try {
    $pdo = getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS faqs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question VARCHAR(500) NOT NULL,
        answer TEXT NOT NULL,
        sort_order INT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $faqs = fetchAll("SELECT id, question, answer, is_active FROM faqs ORDER BY sort_order ASC, id ASC");
} catch (Exception $e) {
    logError('Failed to load FAQs: ' . $e->getMessage());
    $faqs = [];
}

// ============================================================================
// HANDLE GET REQUESTS
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
//    Download backup
    if (isset($_GET['download_backup'])) {
        // Validasi CSRF untuk GET request
        actionLog('SETTINGS_DOWNLOAD_BACKUP_ATTEMPT', $workdir, "Mencoba mengunduh backup file");

        if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
            setFlash('error', 'Invalid CSRF token');
            redirect('settings.php');
        }

        $backupFile = sanitizeBackupFilename($_GET['download_backup'] ?? '');
        if ($backupFile === '') {
            actionLog('SETTINGS_DOWNLOAD_BACKUP_FAILED', $workdir, "Nama file tidak valid", json_encode($backupFile));
            setFlash('error', 'Nama file backup tidak valid');
            redirect('settings.php');
        }
        $fullPath = getBackupDirectory() . $backupFile;
        if (!is_file($fullPath)) {
            actionLog('SETTINGS_DOWNLOAD_BACKUP_FAILED', $workdir, "File tidak ditemukan", json_encode($fullPath));
            setFlash('error', 'File backup tidak ditemukan');
            redirect('settings.php');
        }
        actionLog('SETTINGS_DOWNLOAD_BACKUP_START', $workdir, "Memulai mengunduh backup file");
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $backupFile . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($fullPath);
        exit;
    }
    if (isset($_GET['get_nas']) && isset($_GET['id'])) {
        header('Content-Type: application/json');
        $nasId = (int)$_GET['id'];
        actionLog('GET_NAS_ATTEMPT', $workdir, "Mencoba mengambil data nas", $nasId);
        $nas = radiusGetNasById($nasId);
        if ($nas) {
            actionLog('GET_NAS_SUCCESS', $workdir, "Berhasil mengambil data nas", json_encode(['nas_id' => $nasId, 'data' => $nas]));
            echo json_encode(['success' => true, 'data' => $nas]);
        } else {
            actionLog('GET_NAS_FAILED' , $workdir, 'Gagal mengambil data nas', json_encode(['nas_id' => $nasId, 'data' => $nas]));
            echo json_encode(['success' => false, 'message' => 'NAS tidak ditemukan']);
        }
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        actionLog('SETTINGS_CSRF_FAILED', $workdir, "CSRF token tidak valid", json_encode(['ip' => $_SERVER['REMOTE_ADDR']]));
        setFlash('error', 'Invalid CSRF token');
        redirect('settings.php');
    }

    $action = $_POST['action'] ?? '';
    actionLog('SETTINGS_ACTION_RECEIVED', $workdir, "Menerima aksi settings", json_encode(['action' => $action]));

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            // ==================== ADD NAS ====================
            case 'add_nas':
                $nasName = sanitize($_POST['nas_name']);
                $nasIp = sanitize($_POST['nas_ip']);
                $nasSecret = sanitize($_POST['nas_secret']);
                $logData = ['nas_name' => $nasName, 'nas_ip' => $nasIp];

                actionLog('SETTINGS_ADD_NAS_ATTEMPT', $workdir, "Mencoba menambahkan NAS", json_encode($logData));

                if (empty($nasName) || empty($nasIp) || empty($nasSecret)) {
                    actionLog('SETTINGS_ADD_NAS_FAILED', $workdir, "Field NAS tidak lengkap", json_encode($logData));
                    setFlash('error', 'Semua field NAS harus diisi');
                } elseif (!filter_var($nasIp, FILTER_VALIDATE_IP)) {
                    actionLog('SETTINGS_ADD_NAS_FAILED', $workdir, "IP NAS tidak valid", json_encode($logData));
                    setFlash('error', 'IP NAS tidak valid');
                } elseif (radiusAddNas($nasName, $nasIp, $nasSecret)) {
                    actionLog('SETTINGS_ADD_NAS_SUCCESS', $workdir, "NAS berhasil ditambahkan", json_encode($logData));
                    setFlash('success', 'NAS berhasil ditambahkan');
                    logActivity('ADD_NAS', "Name: {$nasName}, IP: {$nasIp}");
                } else {
                    actionLog('SETTINGS_ADD_NAS_FAILED', $workdir, "Gagal menambahkan NAS", json_encode($logData));
                    setFlash('error', 'Gagal menambahkan NAS');
                }
                redirect('settings.php');
                break;

            // ==================== EDIT NAS ====================
            case 'edit_nas':
                $nasId = (int)$_POST['nas_id'];
                $nasName = sanitize($_POST['nas_name']);
                $nasIp = sanitize($_POST['nas_ip']);
                $nasSecret = sanitize($_POST['nas_secret']);
                $description = sanitize($_POST['description'] ?? '');
                $logData = ['nas_id' => $nasId, 'nas_name' => $nasName, 'nas_ip' => $nasIp];

                actionLog('SETTINGS_EDIT_NAS_ATTEMPT', $workdir, "Mencoba mengupdate NAS", json_encode($logData));

                if ($nasId <= 0) {
                    actionLog('SETTINGS_EDIT_NAS_FAILED', $workdir, "ID NAS tidak valid", json_encode($logData));
                    setFlash('error', 'ID NAS tidak valid');
                } elseif (empty($nasName) || empty($nasIp) || empty($nasSecret)) {
                    actionLog('SETTINGS_EDIT_NAS_FAILED', $workdir, "Field NAS tidak lengkap", json_encode($logData));
                    setFlash('error', 'Semua field NAS harus diisi');
                } elseif (!filter_var($nasIp, FILTER_VALIDATE_IP)) {
                    actionLog('SETTINGS_EDIT_NAS_FAILED', $workdir, "IP NAS tidak valid", json_encode($logData));
                    setFlash('error', 'IP NAS tidak valid');
                } elseif (radiusUpdateNas($nasId, $nasName, $nasIp, $nasSecret)) {
                    actionLog('SETTINGS_EDIT_NAS_SUCCESS', $workdir, "NAS berhasil diperbarui", json_encode($logData));
                    setFlash('success', 'NAS berhasil diperbarui');
                    shell_exec('sudo /bin/systemctl restart freeradius 2>/dev/null >/dev/null &');
                    logActivity('EDIT_NAS', "ID: {$nasId}, Name: {$nasName}");
                } else {
                    actionLog('SETTINGS_EDIT_NAS_FAILED', $workdir, "Gagal mengupdate NAS", json_encode($logData));
                    setFlash('error', 'Gagal memperbarui NAS');
                }
                redirect('settings.php');
                break;

            // ==================== DELETE NAS ====================
            case 'delete_nas':
                $nasId = (int)$_POST['nas_id'];
                actionLog('SETTINGS_DELETE_NAS_ATTEMPT', $workdir, "Mencoba menghapus NAS", json_encode(['nas_id' => $nasId]));

                if ($nasId <= 0) {
                    actionLog('SETTINGS_DELETE_NAS_FAILED', $workdir, "ID NAS tidak valid", json_encode(['nas_id' => $nasId]));
                    setFlash('error', 'ID NAS tidak valid');
                } elseif (radiusDeleteNas($nasId)) {
                    actionLog('SETTINGS_DELETE_NAS_SUCCESS', $workdir, "NAS berhasil dihapus", json_encode(['nas_id' => $nasId]));
                    setFlash('success', 'NAS berhasil dihapus');
                    shell_exec('sudo /bin/systemctl restart freeradius 2>/dev/null >/dev/null &');
                    logActivity('DELETE_NAS', "ID: {$nasId}");
                } else {
                    actionLog('SETTINGS_DELETE_NAS_FAILED', $workdir, "Gagal menghapus NAS", json_encode(['nas_id' => $nasId]));
                    setFlash('error', 'Gagal menghapus NAS');
                }
                redirect('settings.php');
                break;

            // ==================== ADD MIKROTIK CLIENT ====================
            case 'add_mikrotik_client':
                $version = sanitize($_POST['mikrotik_version']);
                actionLog('SETTINGS_ADD_MIKROTIK_CLIENT_ATTEMPT', $workdir, "Mencoba generate script MikroTik client", json_encode(['version' => $version]));

                $script = generateMikrotikClientScript($version);
                if (isset($script['error'])) {
                    actionLog('SETTINGS_ADD_MIKROTIK_CLIENT_FAILED', $workdir, "Error generate script", json_encode(['error' => $script['error']]));
                    setFlash('error', $script['error']);
                    redirect('settings.php');
                    break;
                }

                $nextAddress = nextAddressOvpnClient();
                if (!$nextAddress) {
                    $errMsg = 'Gagal mendapatkan IP berikutnya untuk client OVPN. Subnet mungkin sudah penuh.';
                    actionLog('SETTINGS_ADD_MIKROTIK_CLIENT_FAILED', $workdir, $errMsg, json_encode(['version' => $version]));
                    logError($errMsg);
                    setFlash('error', $errMsg);
                    redirect('settings.php');
                    break;
                }

                $NASAdded = radiusAddNas($script['radius']['nas_name'], $nextAddress, $script['radius']['nas_secret']);
                if (!$NASAdded) {
                    $errMsg = 'Gagal menambahkan NAS untuk client OVPN. Pastikan database RADIUS terkonfigurasi dengan benar.';
                    actionLog('SETTINGS_ADD_MIKROTIK_CLIENT_FAILED', $workdir, $errMsg, json_encode(['version' => $version, 'nas_name' => $script['radius']['nas_name']]));
                    logError($errMsg);
                    setFlash('error', $errMsg);
                    redirect('settings.php');
                    break;
                }

                require_once '../includes/vpn.php';
                $clientVpnAdded = upsertVpnUser([
                        'name' => $script['vpn']['name'],
                        'username' => $script['vpn']['username'],
                        'password' => $script['vpn']['password']
                ]);
                if (!$clientVpnAdded) {
                    $errMsg = 'Gagal menambahkan user VPN untuk client OVPN. Pastikan database VPN terkonfigurasi dengan benar.';
                    actionLog('SETTINGS_ADD_MIKROTIK_CLIENT_FAILED', $workdir, $errMsg, json_encode(['version' => $version, 'username' => $script['vpn']['username']]));
                    logError($errMsg);
                    setFlash('error', $errMsg);
                    redirect('settings.php');
                    break;
                }

                shell_exec('sudo /bin/systemctl restart freeradius 2>/dev/null >/dev/null &');
                $_SESSION['generated_script'] = $script['script'];
                actionLog('SETTINGS_ADD_MIKROTIK_CLIENT_SUCCESS', $workdir, "Script MikroTik berhasil digenerate", json_encode(['version' => $version, 'nas_name' => $script['radius']['nas_name']]));
                setFlash('success', 'Script MikroTik berhasil digenerate');
                logActivity('ADD_MIKROTIK_CLIENT', "Version: {$version}");
                redirect('settings.php');
                break;

            // ==================== SAVE SERVER ====================
            case 'save_server':
                $serverIp = trim(sanitize($_POST['server_ip']));
                $shortAppName = trim(sanitize($_POST['short_app_name']));
                actionLog('SETTINGS_SAVE_SERVER_ATTEMPT', $workdir, "Mencoba menyimpan pengaturan server", json_encode(['server_ip' => $serverIp, 'short_app_name' => $shortAppName]));

                $errors = [];
                if (empty($serverIp)) {
                    $errors[] = 'Server IP tidak boleh kosong';
                } elseif (!filter_var($serverIp, FILTER_VALIDATE_IP)) {
                    $errors[] = 'Server IP tidak valid';
                }
                if (empty($shortAppName)) {
                    $errors[] = 'Short App Name tidak boleh kosong';
                }
                if (!empty($errors)) {
                    actionLog('SETTINGS_SAVE_SERVER_FAILED', $workdir, "Validasi gagal: " . implode(', ', $errors), json_encode(['server_ip' => $serverIp, 'short_app_name' => $shortAppName]));
                    setFlash('error', implode(', ', $errors));
                    redirect('settings.php');
                    break;
                }

                // Update atau insert settings
                try {
                    $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", ['server_ip']);
                    if ($existing) {
                        update('settings', ['setting_value' => $serverIp], 'setting_key = ?', ['server_ip']);
                    } else {
                        insert('settings', ['setting_key' => 'server_ip', 'setting_value' => $serverIp]);
                    }

                    $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", ['short_app_name']);
                    if ($existing) {
                        update('settings', ['setting_value' => $shortAppName], 'setting_key = ?', ['short_app_name']);
                    } else {
                        insert('settings', ['setting_key' => 'short_app_name', 'setting_value' => $shortAppName]);
                    }

                    actionLog('SETTINGS_SAVE_SERVER_SUCCESS', $workdir, "Pengaturan server berhasil disimpan", json_encode(['server_ip' => $serverIp, 'short_app_name' => $shortAppName]));
                    setFlash('success', 'Pengaturan server berhasil disimpan');
                    logActivity('SAVE_SERVER_SETTINGS', "Server IP: {$serverIp}");
                } catch (Exception $e) {
                    actionLog('SETTINGS_SAVE_SERVER_FAILED', $workdir, "Exception: " . $e->getMessage(), json_encode(['server_ip' => $serverIp, 'short_app_name' => $shortAppName]));
                    setFlash('error', 'Gagal menyimpan pengaturan server: ' . $e->getMessage());
                }
                redirect('settings.php');
                break;

            // ==================== SAVE SYSTEM ====================
            case 'save_system':
                $systemSettings = [
                        'app_name' => sanitize($_POST['app_name']),
                        'timezone' => sanitize($_POST['timezone']),
                        'currency' => sanitize($_POST['currency']),
                        'invoice_prefix' => sanitize($_POST['invoice_prefix']),
                        'invoice_start' => (int)$_POST['invoice_start']
                ];
                actionLog('SETTINGS_SAVE_SYSTEM_ATTEMPT', $workdir, "Mencoba menyimpan pengaturan sistem", json_encode($systemSettings));

                try {
                    foreach ($systemSettings as $key => $value) {
                        $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                        if ($existing) {
                            update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                        } else {
                            insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                        }
                    }
                    if (function_exists('date_default_timezone_set')) {
                        @date_default_timezone_set($systemSettings['timezone']);
                    }
                    actionLog('SETTINGS_SAVE_SYSTEM_SUCCESS', $workdir, "Pengaturan sistem berhasil disimpan", json_encode($systemSettings));
                    setFlash('success', 'Pengaturan sistem berhasil disimpan');
                    logActivity('SAVE_SYSTEM_SETTINGS', "App: {$systemSettings['app_name']}");
                } catch (Exception $e) {
                    actionLog('SETTINGS_SAVE_SYSTEM_FAILED', $workdir, "Exception: " . $e->getMessage(), json_encode($systemSettings));
                    setFlash('error', 'Gagal menyimpan pengaturan sistem: ' . $e->getMessage());
                }
                redirect('settings.php');
                break;

            // ==================== SAVE MIKROTIK ====================
            case 'save_mikrotik':
                $mikrotikSettings = [
                        'MIKROTIK_HOST' => sanitize($_POST['mikrotik_host']),
                        'MIKROTIK_USER' => sanitize($_POST['mikrotik_user']),
                        'MIKROTIK_PASS' => sanitize($_POST['mikrotik_pass']),
                        'MIKROTIK_PORT' => (int)$_POST['mikrotik_port']
                ];
                actionLog('SETTINGS_SAVE_MIKROTIK_ATTEMPT', $workdir, "Mencoba menyimpan pengaturan MikroTik", json_encode(['host' => $mikrotikSettings['MIKROTIK_HOST'], 'user' => $mikrotikSettings['MIKROTIK_USER']]));

                try {
                    foreach ($mikrotikSettings as $key => $value) {
                        $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                        if ($existing) {
                            update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                        } else {
                            insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                        }
                    }
                    actionLog('SETTINGS_SAVE_MIKROTIK_SUCCESS', $workdir, "Pengaturan MikroTik berhasil disimpan", json_encode(['host' => $mikrotikSettings['MIKROTIK_HOST']]));
                    setFlash('success', 'Pengaturan MikroTik berhasil disimpan');
                    logActivity('SAVE_MIKROTIK_SETTINGS', "Host: {$mikrotikSettings['MIKROTIK_HOST']}");
                } catch (Exception $e) {
                    actionLog('SETTINGS_SAVE_MIKROTIK_FAILED', $workdir, "Exception: " . $e->getMessage(), json_encode(['host' => $mikrotikSettings['MIKROTIK_HOST']]));
                    setFlash('error', 'Gagal menyimpan pengaturan MikroTik: ' . $e->getMessage());
                }
                redirect('settings.php');
                break;

            // ==================== SAVE GENIEACS ====================
            case 'save_genieacs':
                $genieacsSettings = [
                        'GENIEACS_URL' => sanitize($_POST['genieacs_url']),
                        'GENIEACS_USERNAME' => sanitize($_POST['genieacs_username']),
                        'GENIEACS_PASSWORD' => sanitize($_POST['genieacs_password'])
                ];
                actionLog('SETTINGS_SAVE_GENIEACS_ATTEMPT', $workdir, "Mencoba menyimpan pengaturan GenieACS", json_encode(['url' => $genieacsSettings['GENIEACS_URL'], 'username' => $genieacsSettings['GENIEACS_USERNAME']]));

                try {
                    foreach ($genieacsSettings as $key => $value) {
                        $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                        if ($existing) {
                            update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                        } else {
                            insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                        }
                    }
                    actionLog('SETTINGS_SAVE_GENIEACS_SUCCESS', $workdir, "Pengaturan GenieACS berhasil disimpan", json_encode(['url' => $genieacsSettings['GENIEACS_URL']]));
                    setFlash('success', 'Pengaturan GenieACS berhasil disimpan');
                    logActivity('SAVE_GENIEACS_SETTINGS', "URL: {$genieacsSettings['GENIEACS_URL']}");
                } catch (Exception $e) {
                    actionLog('SETTINGS_SAVE_GENIEACS_FAILED', $workdir, "Exception: " . $e->getMessage(), json_encode(['url' => $genieacsSettings['GENIEACS_URL']]));
                    setFlash('error', 'Gagal menyimpan pengaturan GenieACS: ' . $e->getMessage());
                }
                redirect('settings.php');
                break;

            // ==================== SAVE WHATSAPP ====================
            case 'save_whatsapp_settings':
                $whatsAppSettings = [
                        'DEFAULT_WHATSAPP_GATEWAY' => sanitize($_POST['default_whatsapp_gateway']),
                        'FONNTE_API_TOKEN' => sanitize($_POST['fonnte_api_token']),
                        'WABLAS_API_TOKEN' => sanitize($_POST['wablas_api_token']),
                        'MPWA_API_KEY' => sanitize($_POST['mpwa_api_key']),
                        'MPWA_SENDER' => sanitize($_POST['mpwa_sender']),
                        'MPWA_API_URL' => sanitize($_POST['mpwa_api_url'] ?? ''),
                        'WHATSAPP_ADMIN_NUMBER' => sanitize($_POST['whatsapp_admin_number'])
                ];
                actionLog('SETTINGS_SAVE_WHATSAPP_ATTEMPT', $workdir, "Mencoba menyimpan pengaturan WhatsApp", json_encode(['gateway' => $whatsAppSettings['DEFAULT_WHATSAPP_GATEWAY']]));

                try {
                    foreach ($whatsAppSettings as $key => $value) {
                        $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                        if ($existing) {
                            update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                        } else {
                            insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                        }
                    }
                    actionLog('SETTINGS_SAVE_WHATSAPP_SUCCESS', $workdir, "Pengaturan WhatsApp berhasil disimpan", json_encode(['gateway' => $whatsAppSettings['DEFAULT_WHATSAPP_GATEWAY']]));
                    setFlash('success', 'Pengaturan WhatsApp berhasil disimpan');
                    logActivity('SAVE_WHATSAPP_SETTINGS', "Gateway: {$whatsAppSettings['DEFAULT_WHATSAPP_GATEWAY']}");
                } catch (Exception $e) {
                    actionLog('SETTINGS_SAVE_WHATSAPP_FAILED', $workdir, "Exception: " . $e->getMessage(), json_encode(['gateway' => $whatsAppSettings['DEFAULT_WHATSAPP_GATEWAY']]));
                    setFlash('error', 'Gagal menyimpan pengaturan WhatsApp: ' . $e->getMessage());
                }
                redirect('settings.php');
                break;

            // ==================== SAVE PAYMENT ====================
            case 'save_payment_settings':
                $paymentSettings = [
                        'TRIPAY_API_KEY' => sanitize($_POST['tripay_api_key']),
                        'TRIPAY_PRIVATE_KEY' => sanitize($_POST['tripay_private_key']),
                        'TRIPAY_MERCHANT_CODE' => sanitize($_POST['tripay_merchant_code']),
                        'TRIPAY_MODE' => sanitize($_POST['tripay_mode'] ?? ''),
                        'MIDTRANS_API_KEY' => sanitize($_POST['midtrans_api_key']),
                        'MIDTRANS_MERCHANT_CODE' => sanitize($_POST['midtrans_merchant_code']),
                        'DEFAULT_PAYMENT_GATEWAY' => sanitize($_POST['default_payment_gateway'])
                ];
                actionLog('SETTINGS_SAVE_PAYMENT_ATTEMPT', $workdir, "Mencoba menyimpan pengaturan Payment Gateway", json_encode(['default' => $paymentSettings['DEFAULT_PAYMENT_GATEWAY']]));

                try {
                    foreach ($paymentSettings as $key => $value) {
                        $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                        if ($existing) {
                            update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                        } else {
                            insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                        }
                    }
                    actionLog('SETTINGS_SAVE_PAYMENT_SUCCESS', $workdir, "Pengaturan Payment Gateway berhasil disimpan", json_encode(['default' => $paymentSettings['DEFAULT_PAYMENT_GATEWAY']]));
                    setFlash('success', 'Pengaturan Payment Gateway berhasil disimpan');
                    logActivity('SAVE_PAYMENT_SETTINGS', "Default: {$paymentSettings['DEFAULT_PAYMENT_GATEWAY']}");
                } catch (Exception $e) {
                    actionLog('SETTINGS_SAVE_PAYMENT_FAILED', $workdir, "Exception: " . $e->getMessage(), json_encode(['default' => $paymentSettings['DEFAULT_PAYMENT_GATEWAY']]));
                    setFlash('error', 'Gagal menyimpan pengaturan Payment Gateway: ' . $e->getMessage());
                }
                redirect('settings.php');
                break;

            // ==================== SAVE TELEGRAM ====================
            case 'save_telegram_settings':
                $telegramSettings = [
                        'TELEGRAM_BOT_TOKEN' => sanitize($_POST['telegram_bot_token'] ?? ''),
                        'TELEGRAM_ADMIN_CHAT_ID' => sanitize($_POST['telegram_admin_chat_id'] ?? '')
                ];
                actionLog('SETTINGS_SAVE_TELEGRAM_ATTEMPT', $workdir, "Mencoba menyimpan pengaturan Telegram", json_encode(['chat_id' => $telegramSettings['TELEGRAM_ADMIN_CHAT_ID']]));

                try {
                    foreach ($telegramSettings as $key => $value) {
                        $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                        if ($existing) {
                            update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                        } else {
                            insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                        }
                    }
                    actionLog('SETTINGS_SAVE_TELEGRAM_SUCCESS', $workdir, "Pengaturan Telegram berhasil disimpan", json_encode(['chat_id' => $telegramSettings['TELEGRAM_ADMIN_CHAT_ID']]));
                    setFlash('success', 'Pengaturan Telegram berhasil disimpan');
                    logActivity('SAVE_TELEGRAM_SETTINGS', "Chat ID: {$telegramSettings['TELEGRAM_ADMIN_CHAT_ID']}");
                } catch (Exception $e) {
                    actionLog('SETTINGS_SAVE_TELEGRAM_FAILED', $workdir, "Exception: " . $e->getMessage(), json_encode(['chat_id' => $telegramSettings['TELEGRAM_ADMIN_CHAT_ID']]));
                    setFlash('error', 'Gagal menyimpan pengaturan Telegram: ' . $e->getMessage());
                }
                redirect('settings.php');
                break;

            // ==================== SAVE LANDING ====================
            case 'save_landing':
                $landingSettings = [
                        'landing_template' => sanitize($_POST['landing_template']),
                        'hero_title' => sanitize($_POST['hero_title']),
                        'hero_description' => sanitize($_POST['hero_description']),
                        'contact_phone' => sanitize($_POST['contact_phone']),
                        'contact_email' => sanitize($_POST['contact_email']),
                        'contact_address' => sanitize($_POST['contact_address']),
                        'footer_about' => sanitize($_POST['footer_about']),
                        'feature_1_title' => sanitize($_POST['feature_1_title']),
                        'feature_1_desc' => sanitize($_POST['feature_1_desc']),
                        'feature_2_title' => sanitize($_POST['feature_2_title']),
                        'feature_2_desc' => sanitize($_POST['feature_2_desc']),
                        'feature_3_title' => sanitize($_POST['feature_3_title']),
                        'feature_3_desc' => sanitize($_POST['feature_3_desc']),
                        'social_facebook' => sanitize($_POST['social_facebook']),
                        'social_instagram' => sanitize($_POST['social_instagram']),
                        'social_twitter' => sanitize($_POST['social_twitter']),
                        'social_youtube' => sanitize($_POST['social_youtube']),
                        'theme_color' => sanitize($_POST['theme_color'])
                ];
                actionLog('SETTINGS_SAVE_LANDING_ATTEMPT', $workdir, "Mencoba menyimpan pengaturan Landing Page", json_encode(['template' => $landingSettings['landing_template']]));

                try {
                    foreach ($landingSettings as $key => $value) {
                        $existing = fetchOne("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
                        if ($existing) {
                            update('site_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                        } else {
                            insert('site_settings', ['setting_key' => $key, 'setting_value' => $value]);
                        }
                    }
                    actionLog('SETTINGS_SAVE_LANDING_SUCCESS', $workdir, "Pengaturan Landing Page berhasil disimpan", json_encode(['template' => $landingSettings['landing_template']]));
                    setFlash('success', 'Pengaturan Landing Page berhasil disimpan');
                    logActivity('SAVE_LANDING_SETTINGS', "Template: {$landingSettings['landing_template']}");
                } catch (Exception $e) {
                    actionLog('SETTINGS_SAVE_LANDING_FAILED', $workdir, "Exception: " . $e->getMessage(), json_encode(['template' => $landingSettings['landing_template']]));
                    setFlash('error', 'Gagal menyimpan pengaturan Landing Page: ' . $e->getMessage());
                }
                redirect('settings.php');
                break;

            // ==================== MANAGE FAQ ====================
            case 'manage_faq':
                $faq_action = trim((string) ($_POST['faq_action'] ?? ''));
                $logSubAction = $faq_action;
                actionLog('SETTINGS_MANAGE_FAQ_ATTEMPT', $workdir, "Mencoba mengelola FAQ", json_encode(['faq_action' => $faq_action]));

                if ($faq_action === 'add') {
                    $question = trim((string) sanitize($_POST['faq_question'] ?? ''));
                    $answer = trim((string) sanitize($_POST['faq_answer'] ?? ''));
                    if ($question !== '' && $answer !== '') {
                        if (saveFaq($question, $answer)) {
                            actionLog('SETTINGS_FAQ_ADD_SUCCESS', $workdir, "FAQ berhasil ditambahkan", json_encode(['question' => $question]));
                            setFlash('success', 'FAQ berhasil ditambahkan');
                            logActivity('ADD_FAQ', "Question: {$question}");
                        } else {
                            actionLog('SETTINGS_FAQ_ADD_FAILED', $workdir, "Gagal menambahkan FAQ", json_encode(['question' => $question]));
                            setFlash('error', 'Gagal menambahkan FAQ');
                        }
                    } else {
                        actionLog('SETTINGS_FAQ_ADD_FAILED', $workdir, "Pertanyaan atau jawaban kosong", json_encode(['question' => $question]));
                        setFlash('error', 'Pertanyaan dan jawaban wajib diisi');
                    }
                } elseif ($faq_action === 'update') {
                    $faq_id = (int) ($_POST['faq_id'] ?? 0);
                    $question = trim((string) sanitize($_POST['faq_question'] ?? ''));
                    $answer = trim((string) sanitize($_POST['faq_answer'] ?? ''));
                    $is_active = isset($_POST['faq_active']) ? 1 : 0;
                    if ($faq_id > 0 && $question !== '' && $answer !== '') {
                        if (updateFaq($faq_id, $question, $answer, $is_active)) {
                            actionLog('SETTINGS_FAQ_UPDATE_SUCCESS', $workdir, "FAQ berhasil diperbarui", json_encode(['faq_id' => $faq_id]));
                            setFlash('success', 'FAQ berhasil diperbarui');
                            logActivity('UPDATE_FAQ', "ID: {$faq_id}");
                        } else {
                            actionLog('SETTINGS_FAQ_UPDATE_FAILED', $workdir, "Gagal memperbarui FAQ", json_encode(['faq_id' => $faq_id]));
                            setFlash('error', 'Gagal memperbarui FAQ');
                        }
                    } else {
                        actionLog('SETTINGS_FAQ_UPDATE_FAILED', $workdir, "Data tidak valid", json_encode(['faq_id' => $faq_id]));
                        setFlash('error', 'Data tidak valid');
                    }
                } elseif ($faq_action === 'delete') {
                    $faq_id = (int) ($_POST['faq_id'] ?? 0);
                    if ($faq_id > 0) {
                        if (deleteFaq($faq_id)) {
                            actionLog('SETTINGS_FAQ_DELETE_SUCCESS', $workdir, "FAQ berhasil dihapus", json_encode(['faq_id' => $faq_id]));
                            setFlash('success', 'FAQ berhasil dihapus');
                            logActivity('DELETE_FAQ', "ID: {$faq_id}");
                        } else {
                            actionLog('SETTINGS_FAQ_DELETE_FAILED', $workdir, "Gagal menghapus FAQ", json_encode(['faq_id' => $faq_id]));
                            setFlash('error', 'Gagal menghapus FAQ');
                        }
                    } else {
                        actionLog('SETTINGS_FAQ_DELETE_FAILED', $workdir, "ID FAQ tidak valid", json_encode(['faq_id' => $faq_id]));
                        setFlash('error', 'ID FAQ tidak valid');
                    }
                }
                redirect('settings.php');
                break;

            // ==================== CHANGE PASSWORD ====================
            case 'change_password':
                $currentPassword = $_POST['current_password'];
                $newPassword = $_POST['new_password'];
                $confirmPassword = $_POST['confirm_password'];
                $sessionAdmin = getCurrentAdmin();
                $admin = getAdmin($sessionAdmin['id']);
                actionLog('SETTINGS_CHANGE_PASSWORD_ATTEMPT', $workdir, "Mencoba mengganti password admin", json_encode(['admin_id' => $admin['id'] ?? 0]));

                if (!$admin || !password_verify($currentPassword, $admin['password'])) {
                    actionLog('SETTINGS_CHANGE_PASSWORD_FAILED', $workdir, "Password saat ini salah", json_encode(['admin_id' => $admin['id'] ?? 0]));
                    setFlash('error', 'Password saat ini salah');
                    redirect('settings.php');
                    break;
                }
                if ($newPassword !== $confirmPassword) {
                    actionLog('SETTINGS_CHANGE_PASSWORD_FAILED', $workdir, "Password baru tidak sama", json_encode(['admin_id' => $admin['id']]));
                    setFlash('error', 'Password baru tidak sama');
                    redirect('settings.php');
                    break;
                }
                if (strlen($newPassword) < 6) {
                    actionLog('SETTINGS_CHANGE_PASSWORD_FAILED', $workdir, "Password terlalu pendek", json_encode(['admin_id' => $admin['id']]));
                    setFlash('error', 'Password minimal 6 karakter');
                    redirect('settings.php');
                    break;
                }
                if (updateAdminPassword($admin['id'], $newPassword)) {
                    actionLog('SETTINGS_CHANGE_PASSWORD_SUCCESS', $workdir, "Password berhasil diubah", json_encode(['admin_id' => $admin['id']]));
                    setFlash('success', 'Password berhasil diubah');
                    logActivity('CHANGE_PASSWORD', 'Admin ID: ' . $admin['id']);
                } else {
                    actionLog('SETTINGS_CHANGE_PASSWORD_FAILED', $workdir, "Gagal mengupdate password di DB", json_encode(['admin_id' => $admin['id']]));
                    setFlash('error', 'Gagal mengubah password');
                }
                redirect('settings.php');
                break;

            // ==================== BACKUP SETTINGS ====================
            case 'save_backup_settings':
                $retentionDays = (int) ($_POST['backup_retention_days'] ?? 7);
                if ($retentionDays < 1) $retentionDays = 1;
                if ($retentionDays > 365) $retentionDays = 365;
                actionLog('SETTINGS_SAVE_BACKUP_RETENTION_ATTEMPT', $workdir, "Mencoba menyimpan pengaturan retensi backup", json_encode(['retention_days' => $retentionDays]));

                try {
                    $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", ['BACKUP_RETENTION_DAYS']);
                    if ($existing) {
                        update('settings', ['setting_value' => $retentionDays], 'setting_key = ?', ['BACKUP_RETENTION_DAYS']);
                    } else {
                        insert('settings', ['setting_key' => 'BACKUP_RETENTION_DAYS', 'setting_value' => $retentionDays]);
                    }
                    actionLog('SETTINGS_SAVE_BACKUP_RETENTION_SUCCESS', $workdir, "Pengaturan retensi backup berhasil disimpan", json_encode(['retention_days' => $retentionDays]));
                    setFlash('success', 'Pengaturan retensi backup berhasil disimpan');
                    logActivity('SAVE_BACKUP_SETTINGS', "Retention days: {$retentionDays}");
                } catch (Exception $e) {
                    actionLog('SETTINGS_SAVE_BACKUP_RETENTION_FAILED', $workdir, "Exception: " . $e->getMessage(), json_encode(['retention_days' => $retentionDays]));
                    setFlash('error', 'Gagal menyimpan pengaturan retensi backup: ' . $e->getMessage());
                }
                redirect('settings.php');
                break;

            // ==================== BACKUP NOW ====================
            case 'backup_now':
                actionLog('SETTINGS_BACKUP_NOW_ATTEMPT', $workdir, "Mencoba membuat backup database", json_encode([]));
                $retentionDays = (int) getSettingValue('BACKUP_RETENTION_DAYS', 7);
                $result = createDatabaseBackup($retentionDays);
                if ($result['success']) {
                    $deletedCount = count($result['deleted_files'] ?? []);
                    $message = 'Backup berhasil dibuat: ' . ($result['file_name'] ?? '-');
                    if ($deletedCount > 0) {
                        $message .= " ({$deletedCount} backup lama dihapus)";
                    }
                    actionLog('SETTINGS_BACKUP_NOW_SUCCESS', $workdir, "Backup berhasil dibuat", json_encode(['file' => $result['file_name'], 'deleted' => $deletedCount]));
                    setFlash('success', $message);
                    logActivity('BACKUP_NOW', 'File: ' . ($result['file_name'] ?? '-'));
                } else {
                    actionLog('SETTINGS_BACKUP_NOW_FAILED', $workdir, "Gagal membuat backup", json_encode(['error' => $result['message'] ?? 'unknown']));
                    setFlash('error', $result['message'] ?? 'Gagal membuat backup');
                }
                redirect('settings.php');
                break;

            // ==================== RESTORE BACKUP ====================
            case 'restore_backup':
                $backupFile = sanitizeBackupFilename($_POST['backup_file'] ?? '');
                $confirmRestore = strtoupper(trim((string) ($_POST['confirm_restore'] ?? '')));
                actionLog('SETTINGS_RESTORE_BACKUP_ATTEMPT', $workdir, "Mencoba restore backup", json_encode(['file' => $backupFile]));

                if ($backupFile === '') {
                    actionLog('SETTINGS_RESTORE_BACKUP_FAILED', $workdir, "File backup tidak valid", json_encode(['file' => $backupFile]));
                    setFlash('error', 'Pilih file backup yang valid');
                    redirect('settings.php');
                    break;
                }
                if ($confirmRestore !== 'RESTORE') {
                    actionLog('SETTINGS_RESTORE_BACKUP_FAILED', $workdir, "Konfirmasi restore tidak valid", json_encode(['file' => $backupFile, 'confirm' => $confirmRestore]));
                    setFlash('error', 'Konfirmasi restore tidak valid. Ketik RESTORE untuk melanjutkan.');
                    redirect('settings.php');
                    break;
                }

                set_time_limit(0);
                $result = restoreDatabaseBackup($backupFile);
                if ($result['success']) {
                    actionLog('SETTINGS_RESTORE_BACKUP_SUCCESS', $workdir, "Restore backup berhasil", json_encode(['file' => $backupFile]));
                    setFlash('success', 'Restore berhasil dari file: ' . $backupFile);
                    logActivity('RESTORE_BACKUP', 'File: ' . $backupFile);
                } else {
                    actionLog('SETTINGS_RESTORE_BACKUP_FAILED', $workdir, "Restore backup gagal", json_encode(['file' => $backupFile, 'error' => $result['message'] ?? 'unknown']));
                    setFlash('error', $result['message'] ?? 'Restore backup gagal');
                }
                redirect('settings.php');
                break;

            // ==================== SAVE CRON ====================
            case 'save_cron_settings':
                $cronToken = sanitize($_POST['cron_token'] ?? '');
                if ($cronToken === '') {
                    $cronToken = bin2hex(random_bytes(16));
                }
                actionLog('SETTINGS_SAVE_CRON_TOKEN_ATTEMPT', $workdir, "Mencoba menyimpan cron token", json_encode(['token_length' => strlen($cronToken)]));

                try {
                    $key = 'CRON_TOKEN';
                    $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                    if ($existing) {
                        update('settings', ['setting_value' => $cronToken], 'setting_key = ?', [$key]);
                    } else {
                        insert('settings', ['setting_key' => $key, 'setting_value' => $cronToken]);
                    }
                    actionLog('SETTINGS_SAVE_CRON_TOKEN_SUCCESS', $workdir, "Cron token berhasil disimpan", json_encode(['token_length' => strlen($cronToken)]));
                    setFlash('success', 'Cron token berhasil disimpan');
                    logActivity('SAVE_CRON_TOKEN', '');
                } catch (Exception $e) {
                    actionLog('SETTINGS_SAVE_CRON_TOKEN_FAILED', $workdir, "Exception: " . $e->getMessage(), json_encode([]));
                    setFlash('error', 'Gagal menyimpan cron token: ' . $e->getMessage());
                }
                redirect('settings.php');
                break;

            // ==================== TEST WHATSAPP ====================
            case 'test_whatsapp':
                $testPhone = trim((string) ($_POST['test_whatsapp_phone'] ?? ''));
                $testMessage = trim((string) ($_POST['test_whatsapp_message'] ?? ''));
                actionLog('SETTINGS_TEST_WHATSAPP_ATTEMPT', $workdir, "Mencoba test WhatsApp", json_encode(['phone' => $testPhone]));

                if ($testPhone === '' || $testMessage === '') {
                    actionLog('SETTINGS_TEST_WHATSAPP_FAILED', $workdir, "Nomor atau pesan kosong", json_encode(['phone' => $testPhone]));
                    setFlash('error', 'Nomor WhatsApp dan pesan test wajib diisi');
                    redirect('settings.php');
                    break;
                }

                $digits = preg_replace('/\D+/', '', $testPhone);
                if ($digits !== '') {
                    if (strpos($digits, '0') === 0) {
                        $digits = '62' . substr($digits, 1);
                    } elseif (strpos($digits, '62') !== 0) {
                        $digits = '62' . $digits;
                    }
                }

                require_once '../includes/whatsapp.php';
                $defaultGateway = getSetting('DEFAULT_WHATSAPP_GATEWAY', 'fonnte');
                $result = sendWhatsAppMessage($digits, $testMessage, $defaultGateway);

                if (($result['success'] ?? false) === true) {
                    actionLog('SETTINGS_TEST_WHATSAPP_SUCCESS', $workdir, "Test WhatsApp berhasil", json_encode(['phone' => $digits, 'gateway' => $defaultGateway]));
                    setFlash('success', 'Test WhatsApp berhasil dikirim (gateway: ' . strtoupper($defaultGateway) . ')');
                } else {
                    $msg = $result['message'] ?? 'Test WhatsApp gagal';
                    actionLog('SETTINGS_TEST_WHATSAPP_FAILED', $workdir, "Test WhatsApp gagal", json_encode(['phone' => $digits, 'gateway' => $defaultGateway, 'error' => $msg]));
                    setFlash('error', 'Test WhatsApp gagal (gateway: ' . strtoupper($defaultGateway) . '): ' . $msg);
                }
                redirect('settings.php');
                break;

            // ==================== TEST MPWA ====================
            case 'test_mpwa_connection':
                $url = trim((string) getSetting('MPWA_API_URL', 'https://mpwa.official.id/api/send'));
                if ($url === '') $url = 'https://mpwa.official.id/api/send';
                actionLog('SETTINGS_TEST_MPWA_ATTEMPT', $workdir, "Mencoba test koneksi MPWA", json_encode(['url' => $url]));

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErrno = (int) curl_errno($ch);
                $curlError = (string) curl_error($ch);
                curl_close($ch);

                if ($curlErrno !== 0 || $httpCode === 0) {
                    $errorMsg = 'Koneksi MPWA gagal (HTTP ' . $httpCode . ', cURL ' . $curlErrno . '): ' . $curlError;
                    actionLog('SETTINGS_TEST_MPWA_FAILED', $workdir, $errorMsg, json_encode(['url' => $url]));
                    setFlash('error', $errorMsg);
                } else {
                    actionLog('SETTINGS_TEST_MPWA_SUCCESS', $workdir, "Koneksi MPWA OK", json_encode(['url' => $url, 'http_code' => $httpCode]));
                    setFlash('success', 'Koneksi MPWA OK (HTTP ' . $httpCode . ').');
                }
                redirect('settings.php');
                break;

            // ==================== TEST TELEGRAM ====================
            case 'test_telegram':
                $token = trim((string) getSetting('TELEGRAM_BOT_TOKEN', ''));
                $chatId = trim((string) getSetting('TELEGRAM_ADMIN_CHAT_ID', ''));
                actionLog('SETTINGS_TEST_TELEGRAM_ATTEMPT', $workdir, "Mencoba test Telegram", json_encode(['chat_id' => $chatId]));

                if ($token === '' || $chatId === '') {
                    actionLog('SETTINGS_TEST_TELEGRAM_FAILED', $workdir, "Token atau Chat ID kosong", json_encode(['token' => (bool)$token, 'chat_id' => (bool)$chatId]));
                    setFlash('error', 'Telegram Bot Token dan Admin Chat ID wajib diisi untuk test.');
                    redirect('settings.php');
                    break;
                }

                $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
                $payload = [
                        'chat_id' => $chatId,
                        'text' => 'Test Telegram ' . date('Y-m-d H:i:s'),
                        'parse_mode' => 'HTML'
                ];
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $response = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErrno = (int) curl_errno($ch);
                $curlError = (string) curl_error($ch);
                curl_close($ch);
                $decoded = json_decode((string) $response, true);

                if ($curlErrno !== 0 || $httpCode === 0) {
                    $errorMsg = 'Test Telegram gagal (HTTP ' . $httpCode . ', cURL ' . $curlErrno . '): ' . $curlError;
                    actionLog('SETTINGS_TEST_TELEGRAM_FAILED', $workdir, $errorMsg, json_encode(['chat_id' => $chatId]));
                    setFlash('error', $errorMsg);
                } elseif (is_array($decoded) && ($decoded['ok'] ?? false) === true) {
                    actionLog('SETTINGS_TEST_TELEGRAM_SUCCESS', $workdir, "Test Telegram berhasil", json_encode(['chat_id' => $chatId]));
                    setFlash('success', 'Test Telegram berhasil dikirim ke Chat ID: ' . $chatId);
                } else {
                    $msg = is_array($decoded) ? (string) ($decoded['description'] ?? 'Unknown error') : 'Unknown error';
                    actionLog('SETTINGS_TEST_TELEGRAM_FAILED', $workdir, "Test Telegram gagal: " . $msg, json_encode(['chat_id' => $chatId, 'response' => $decoded]));
                    setFlash('error', 'Test Telegram gagal (HTTP ' . $httpCode . '): ' . $msg);
                }
                redirect('settings.php');
                break;

            // ==================== TELEGRAM SET WEBHOOK ====================
            case 'telegram_set_webhook':
                $token = trim((string) getSetting('TELEGRAM_BOT_TOKEN', ''));
                $webhookUrl = rtrim(APP_URL, '/') . '/webhooks/telegram.php';
                actionLog('SETTINGS_TELEGRAM_SET_WEBHOOK_ATTEMPT', $workdir, "Mencoba set webhook Telegram", json_encode(['url' => $webhookUrl]));

                if ($token === '') {
                    actionLog('SETTINGS_TELEGRAM_SET_WEBHOOK_FAILED', $workdir, "Bot Token kosong", json_encode([]));
                    setFlash('error', 'Telegram Bot Token belum diisi.');
                    redirect('settings.php');
                    break;
                }
                if (stripos($webhookUrl, 'localhost') !== false || stripos($webhookUrl, '127.0.0.1') !== false) {
                    actionLog('SETTINGS_TELEGRAM_SET_WEBHOOK_FAILED', $workdir, "Webhook URL masih localhost", json_encode(['url' => $webhookUrl]));
                    setFlash('error', 'APP_URL masih localhost. Telegram tidak bisa mengakses webhook lokal.');
                    redirect('settings.php');
                    break;
                }

                $url = 'https://api.telegram.org/bot' . $token . '/setWebhook';
                $payload = ['url' => $webhookUrl];
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                $response = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErrno = (int) curl_errno($ch);
                $curlError = (string) curl_error($ch);
                curl_close($ch);
                $decoded = json_decode((string) $response, true);

                if ($curlErrno !== 0 || $httpCode === 0) {
                    $errorMsg = 'setWebhook gagal (HTTP ' . $httpCode . ', cURL ' . $curlErrno . '): ' . $curlError;
                    actionLog('SETTINGS_TELEGRAM_SET_WEBHOOK_FAILED', $workdir, $errorMsg, json_encode(['url' => $webhookUrl]));
                    setFlash('error', $errorMsg);
                } elseif (is_array($decoded) && ($decoded['ok'] ?? false) === true) {
                    actionLog('SETTINGS_TELEGRAM_SET_WEBHOOK_SUCCESS', $workdir, "Webhook berhasil di-set", json_encode(['url' => $webhookUrl]));
                    setFlash('success', 'Webhook Telegram berhasil di-set ke: ' . $webhookUrl);
                } else {
                    $msg = is_array($decoded) ? (string) ($decoded['description'] ?? 'Unknown error') : 'Unknown error';
                    actionLog('SETTINGS_TELEGRAM_SET_WEBHOOK_FAILED', $workdir, "setWebhook gagal: " . $msg, json_encode(['url' => $webhookUrl, 'response' => $decoded]));
                    setFlash('error', 'setWebhook gagal (HTTP ' . $httpCode . '): ' . $msg);
                }
                redirect('settings.php');
                break;

            // ==================== TELEGRAM WEBHOOK INFO ====================
            case 'telegram_webhook_info':
                $token = trim((string) getSetting('TELEGRAM_BOT_TOKEN', ''));
                actionLog('SETTINGS_TELEGRAM_WEBHOOK_INFO_ATTEMPT', $workdir, "Mencoba get webhook info Telegram", json_encode([]));

                if ($token === '') {
                    actionLog('SETTINGS_TELEGRAM_WEBHOOK_INFO_FAILED', $workdir, "Bot Token kosong", json_encode([]));
                    setFlash('error', 'Telegram Bot Token belum diisi.');
                    redirect('settings.php');
                    break;
                }

                $url = 'https://api.telegram.org/bot' . $token . '/getWebhookInfo';
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $response = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErrno = (int) curl_errno($ch);
                $curlError = (string) curl_error($ch);
                curl_close($ch);
                $decoded = json_decode((string) $response, true);

                if ($curlErrno !== 0 || $httpCode === 0) {
                    $errorMsg = 'getWebhookInfo gagal (HTTP ' . $httpCode . ', cURL ' . $curlErrno . '): ' . $curlError;
                    actionLog('SETTINGS_TELEGRAM_WEBHOOK_INFO_FAILED', $workdir, $errorMsg, json_encode([]));
                    setFlash('error', $errorMsg);
                } elseif (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
                    $msg = is_array($decoded) ? (string) ($decoded['description'] ?? 'Unknown error') : 'Unknown error';
                    actionLog('SETTINGS_TELEGRAM_WEBHOOK_INFO_FAILED', $workdir, "getWebhookInfo gagal: " . $msg, json_encode(['response' => $decoded]));
                    setFlash('error', 'getWebhookInfo gagal (HTTP ' . $httpCode . '): ' . $msg);
                } else {
                    $result = $decoded['result'] ?? [];
                    $currentUrl = (string) ($result['url'] ?? '');
                    $pending = (int) ($result['pending_update_count'] ?? 0);
                    $lastError = (string) ($result['last_error_message'] ?? '');
                    $info = 'Webhook URL: ' . ($currentUrl !== '' ? $currentUrl : '(kosong)') . ' | Pending: ' . $pending;
                    if ($lastError !== '') {
                        $info .= ' | Last error: ' . $lastError;
                    }
                    actionLog('SETTINGS_TELEGRAM_WEBHOOK_INFO_SUCCESS', $workdir, "Webhook info berhasil", json_encode(['url' => $currentUrl, 'pending' => $pending, 'last_error' => $lastError]));
                    setFlash('success', $info);
                }
                redirect('settings.php');
                break;

            default:
                if ($action !== '') {
                    actionLog('SETTINGS_UNKNOWN_ACTION', $workdir, "Aksi tidak dikenali", json_encode(['action' => $action]));
                    setFlash('error', 'Aksi tidak dikenali.');
                } else {
                    actionLog('SETTINGS_NO_ACTION', $workdir, "Tidak ada aksi yang dikirim", json_encode([]));
                }
                redirect('settings.php');
                break;
        }
    }
}

// ============================================================================
// AMBIL DATA UNTUK VIEW
// ============================================================================

$backupRetentionDays = (int) getSettingValue('BACKUP_RETENTION_DAYS', 7);
if ($backupRetentionDays < 1) $backupRetentionDays = 7;
$backupFiles = listDatabaseBackups();

// Get NAS list
$nasList = radiusDisplayNas();

// Get current admin
$currentAdmin = getCurrentAdmin();

// Get APP_URL
if (!defined('APP_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    define('APP_URL', $protocol . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/'));
}

// ============================================================================
// START OUTPUT
// ============================================================================

ob_start();
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo count($settings); ?></h3>
            <p>Pengaturan Tersimpan</p>
        </div>
        <div class="stat-icon blue">
            <i class="fas fa-cog"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo count($backupFiles); ?></h3>
            <p>Backup Database</p>
        </div>
        <div class="stat-icon green">
            <i class="fas fa-database"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo count($faqs); ?></h3>
            <p>Total FAQ</p>
        </div>
        <div class="stat-icon purple">
            <i class="fas fa-question-circle"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <?php 
            $connected = function_exists('mikrotikConnect') ? mikrotikConnect() : false;
            ?>
            <h3><?php echo $connected ? 'Online' : 'Offline'; ?></h3>
            <p>MikroTik Status</p>
        </div>
        <div class="stat-icon <?php echo $connected ? 'green' : 'red'; ?>">
            <i class="fas fa-network-wired"></i>
        </div>
    </div>
</div>

<!-- Pengaturan Server -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-cog"></i> Pengaturan Server
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_server">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

            <div class="form-group">
                <label class="form-label">Server IP</label>
                <input type="text" name="server_ip" class="form-control" value="<?php echo htmlspecialchars($settings['server_ip'] ?? '127.0.0.1', ENT_QUOTES, 'UTF-8'); ?>">
                <small class="form-hint">IP Address server ini, digunakan untuk webhook dan koneksi eksternal</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Short App Name</label>
                <input type="text" name="short_app_name" placeholder="ANS" class="form-control" value="<?php echo htmlspecialchars($settings['short_app_name'] ?? 'ANS', ENT_QUOTES, 'UTF-8'); ?>">
                <small class="form-hint">Nama singkat aplikasi, digunakan untuk script mikrotik</small>
            </div>
            
            <button type="submit" class="btn btn-primary">Simpan</button>
        </form>
    </div>
</div>

<!-- Service Management -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-server"></i> Manajemen Service
        </h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama Layanan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-label="Nama Layanan">Radius Server</td>
                    <td data-label="Status">
                        <?php
                        $radiusStatus = shell_exec('systemctl is-active freeradius 2>/dev/null');
                        $isRadiusRunning = trim($radiusStatus) === 'active';
                        ?>
                        <span class="badge <?php echo $isRadiusRunning ? 'badge-success' : 'badge-danger'; ?>">
                            <i class="fas <?php echo $isRadiusRunning ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> 
                            <?php echo $isRadiusRunning ? 'Running' : 'Stopped'; ?>
                        </span>
                    </td>
                    <td data-label="Aksi">
                        <button type="button" class="btn-icon" onclick="restartService('radius')" title="Restart Radius Server">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </td>
                </tr>
                <tr>
                    <td data-label="Nama Layanan">Cron Scheduler</td>
                    <td data-label="Status">
                        <span class="badge badge-info">
                            <i class="fas fa-clock"></i> Menunggu Cronjob
                        </span>
                    </td>
                    <td data-label="Aksi">
                        <span class="text-muted">-</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- System Settings -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-cog"></i> Pengaturan Sistem
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_system">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nama Aplikasi</label>
                    <input type="text" name="app_name" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['app_name'] ?? 'ANS Radius', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Timezone</label>
                    <select name="timezone" class="form-control">
                        <option value="Asia/Jakarta" <?php echo ($settings['timezone'] ?? '') === 'Asia/Jakarta' ? 'selected' : ''; ?>>Asia/Jakarta (WIB)</option>
                        <option value="Asia/Makassar" <?php echo ($settings['timezone'] ?? '') === 'Asia/Makassar' ? 'selected' : ''; ?>>Asia/Makassar (WITA)</option>
                        <option value="Asia/Jayapura" <?php echo ($settings['timezone'] ?? '') === 'Asia/Jayapura' ? 'selected' : ''; ?>>Asia/Jayapura (WIT)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Mata Uang</label>
                    <select name="currency" class="form-control">
                        <option value="IDR" <?php echo ($settings['currency'] ?? '') === 'IDR' ? 'selected' : ''; ?>>IDR - Rupiah</option>
                        <option value="USD" <?php echo ($settings['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD - Dollar</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Invoice Mulai Dari</label>
                    <input type="number" name="invoice_start" class="form-control" 
                           value="<?php echo (int)($settings['invoice_start'] ?? 1); ?>">
                    <small class="form-hint">Nomor invoice dimulai dari angka ini</small>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Invoice Prefix</label>
                <input type="text" name="invoice_prefix" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'INV', ENT_QUOTES, 'UTF-8'); ?>">
                <small class="form-hint">Contoh: INV akan menjadi INV-0001</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Sistem
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MikroTik Settings -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-network-wired"></i> Pengaturan MikroTik
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_mikrotik">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">MikroTik IP Address</label>
                    <input type="text" name="mikrotik_host" class="form-control" 
                           value="<?php echo htmlspecialchars(getSettingValue('MIKROTIK_HOST', ''), ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="192.168.1.1">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="mikrotik_user" class="form-control" 
                           value="<?php echo htmlspecialchars(getSettingValue('MIKROTIK_USER', ''), ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="admin">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="mikrotik_pass" class="form-control" 
                               value="<?php echo htmlspecialchars(getSettingValue('MIKROTIK_PASS', ''), ENT_QUOTES, 'UTF-8'); ?>" 
                               placeholder="Masukkan password" id="mikrotik_pass">
                        <i class="fas fa-eye toggle-password" data-target="mikrotik_pass"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">API Port</label>
                    <input type="number" name="mikrotik_port" class="form-control" 
                           value="<?php echo (int)getSettingValue('MIKROTIK_PORT', 8728); ?>">
                    <small class="form-hint">Default: 8728 (API), 8729 (API-SSL)</small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan MikroTik
                </button>
                <button type="button" class="btn btn-secondary" onclick="testMikrotikConnection()">
                    <i class="fas fa-plug"></i> Test Koneksi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Generate Script MikroTik Client -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-code"></i> Generate Script (Mikrotik Client)
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add_mikrotik_client">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-group">
                <label class="form-label">Versi MikroTik</label>
                <select name="mikrotik_version" id="mikrotik_version" class="form-control">
                    <option value="7">MikroTik 7.15 (Keatas)</option>
                    <option value="6">MikroTik 6 - 7.14 (Kebawah)</option>
                </select>
                <small class="form-hint">Pilih versi sesuai dengan RouterOS yang digunakan</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Script Hasil Generate</label>
                <textarea name="script" class="form-control" placeholder="Script MikroTik akan muncul di sini..." rows="10" id="mikrotik_script" readonly><?php 
                    if(isset($_SESSION['generated_script'])) { 
                        echo htmlspecialchars($_SESSION['generated_script'], ENT_QUOTES, 'UTF-8'); 
                        unset($_SESSION['generated_script']);
                    } 
                ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Generate
                </button>
                <button type="button" class="btn btn-secondary" onclick="copyToClipboardValueById('mikrotik_script')">
                    <i class="fas fa-copy"></i> Salin Script
                </button>
            </div>
        </form>
    </div>
</div>

<!-- NAS Settings (Radius Client) -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-database"></i> NAS (Radius Client)
        </h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div>NAS adalah client yang terhubung ke Radius Server (MikroTik, AP, dll). Perlu restart radius server setelah menambahkan atau menghapus NAS.</div>
        </div>
        
        <!-- Form Tambah NAS -->
        <form method="POST" class="add-nas-form">
            <input type="hidden" name="action" value="add_nas">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">NAS Name</label>
                    <input type="text" name="nas_name" class="form-control" placeholder="Contoh: Mikrotik-SERVER" required>
                </div>
                <div class="form-group">
                    <label class="form-label">NAS IP</label>
                    <input type="text" name="nas_ip" class="form-control" placeholder="10.7.0.1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">NAS Secret</label>
                    <input type="text" name="nas_secret" class="form-control" placeholder="testing123" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah NAS
            </button>
        </form>
        
        <!-- Daftar NAS -->
        <div class="table-responsive" style="margin-top: 24px;">
            <h4 class="section-subtitle">Daftar NAS</h4>
            <table class="data-table" id="nas-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama NAS</th>
                        <th>IP NAS</th>
                        <th>Secret</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($nasList)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>Tidak ada NAS yang terdaftar</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($nasList as $nas): ?>
                        <tr id="nas-row-<?php echo (int)$nas['id']; ?>">
                            <td data-label="ID"><?php echo (int)$nas['id']; ?></td>
                            <td data-label="Nama NAS"><?php echo htmlspecialchars($nas['shortname'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="IP NAS"><?php echo htmlspecialchars($nas['nasname'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Secret">
                                <span class="secret-dots">••••••••</span>
                                <span class="secret-value" style="display: none;"><?php echo htmlspecialchars($nas['secret'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <button type="button" class="btn-icon toggle-secret" title="Lihat Secret">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                            <td data-label="Aksi">
                                <button type="button" class="btn-icon" onclick="editNasModal(<?php echo (int)$nas['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Hapus NAS ' + <?php echo json_encode($nas['shortname']); ?> + '?');">
                                    <input type="hidden" name="action" value="delete_nas">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="nas_id" value="<?php echo (int)$nas['id']; ?>">
                                    <button type="submit" class="btn-icon danger" title="Hapus">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



<!-- GenieACS Settings -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-satellite-dish"></i> GenieACS
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_genieacs">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-group">
                <label class="form-label">GenieACS URL</label>
                <input type="text" name="genieacs_url" class="form-control" 
                       value="<?php echo htmlspecialchars(getSettingValue('GENIEACS_URL', ''), ENT_QUOTES, 'UTF-8'); ?>" 
                       placeholder="http://localhost:7557">
                <small class="form-hint">URL lengkap termasuk port (default: 7557)</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="genieacs_username" class="form-control" 
                           value="<?php echo htmlspecialchars(getSettingValue('GENIEACS_USERNAME', ''), ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="Username">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="genieacs_password" class="form-control" 
                               value="<?php echo htmlspecialchars(getSettingValue('GENIEACS_PASSWORD', ''), ENT_QUOTES, 'UTF-8'); ?>" 
                               placeholder="Password" id="genieacs_pass">
                        <i class="fas fa-eye toggle-password" data-target="genieacs_pass"></i>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan GenieACS
                </button>
            </div>
        </form>
    </div>
</div>

<!-- WhatsApp Settings -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fab fa-whatsapp"></i> WhatsApp Gateway
        </h3>
    </div>
    <div class="card-body">
        <!-- Webhook URL Info -->
        <div class="webhook-info">
            <div class="info-header">
                <i class="fas fa-link"></i>
                <strong>URL Webhook WhatsApp</strong>
            </div>
            <p class="info-text">Paste URL ini ke dashboard gateway WhatsApp Anda</p>
            <div class="url-wrapper">
                <input type="text" id="wa_webhook_url" readonly
                    value="<?php echo APP_URL; ?>/webhooks/whatsapp.php"
                    class="webhook-input"
                    onclick="this.select()">
                <button type="button" class="btn-icon" onclick="copyToClipboardById('wa_webhook_url')">
                    <i class="fas fa-copy"></i> Salin
                </button>
            </div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_whatsapp_settings">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-group">
                <label class="form-label">Gateway Default</label>
                <select name="default_whatsapp_gateway" class="form-control">
                    <option value="fonnte" <?php echo ($settings['DEFAULT_WHATSAPP_GATEWAY'] ?? '') === 'fonnte' ? 'selected' : ''; ?>>Fonnte</option>
                    <option value="wablas" <?php echo ($settings['DEFAULT_WHATSAPP_GATEWAY'] ?? '') === 'wablas' ? 'selected' : ''; ?>>Wablas</option>
                    <option value="mpwa" <?php echo ($settings['DEFAULT_WHATSAPP_GATEWAY'] ?? '') === 'mpwa' ? 'selected' : ''; ?>>MPWA</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Fonnte API Token</label>
                    <div class="password-wrapper">
                        <input type="password" name="fonnte_api_token" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['FONNTE_API_TOKEN'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               placeholder="Masukkan API Token Fonnte" id="fonnte_token">
                        <i class="fas fa-eye toggle-password" data-target="fonnte_token"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Wablas API Token</label>
                    <div class="password-wrapper">
                        <input type="password" name="wablas_api_token" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['WABLAS_API_TOKEN'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               placeholder="Masukkan API Token Wablas" id="wablas_token">
                        <i class="fas fa-eye toggle-password" data-target="wablas_token"></i>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">MPWA API Key</label>
                    <div class="password-wrapper">
                        <input type="password" name="mpwa_api_key" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['MPWA_API_KEY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               placeholder="API Key MPWA" id="mpwa_key">
                        <i class="fas fa-eye toggle-password" data-target="mpwa_key"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">MPWA Sender Number</label>
                    <input type="text" name="mpwa_sender" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['MPWA_SENDER'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="628xxxxxxxxxx">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">MPWA API URL</label>
                <input type="text" name="mpwa_api_url" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['MPWA_API_URL'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                       placeholder="https://mpwa.official.id/api/send">
                <small class="form-hint">Biarkan kosong untuk menggunakan default</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">WhatsApp Admin Number</label>
                <input type="text" name="whatsapp_admin_number" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['WHATSAPP_ADMIN_NUMBER'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                       placeholder="628xxxxxxxxxx">
                <small class="form-hint">Nomor untuk notifikasi admin (format: 628xxxx)</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan WhatsApp
                </button>
            </div>
        </form>
        
        <!-- Test WhatsApp -->
        <div class="test-section">
            <h4 class="section-subtitle">Test WhatsApp</h4>
            <form method="POST">
                <input type="hidden" name="action" value="test_whatsapp">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nomor Tujuan</label>
                        <input type="text" name="test_whatsapp_phone" class="form-control" 
                               placeholder="628xxxxxxxxxx" id="test_phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pesan Test</label>
                        <input type="text" name="test_whatsapp_message" class="form-control" 
                               value="Test WhatsApp - Sistem Berjalan Normal">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i> Kirim Test
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="testMpwaConnection()">
                        <i class="fas fa-network-wired"></i> Test Koneksi MPWA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Gateway Settings -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-credit-card"></i> Payment Gateway
        </h3>
    </div>
    <div class="card-body">
        <!-- Tripay Webhook -->
        <div class="webhook-info">
            <div class="info-header">
                <i class="fas fa-link"></i>
                <strong>URL Webhook Tripay</strong>
            </div>
            <p class="info-text">Paste URL ini ke menu Callback URL di merchant Tripay</p>
            <div class="url-wrapper">
                <input type="text" id="tripay_webhook_url" readonly
                    value="<?php echo APP_URL; ?>/webhooks/tripay.php"
                    class="webhook-input"
                    onclick="this.select()">
                <button type="button" class="btn-icon" onclick="copyToClipboardById('tripay_webhook_url')">
                    <i class="fas fa-copy"></i> Salin
                </button>
            </div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_payment_settings">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <h4 class="section-subtitle">Tripay</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tripay API Key</label>
                    <div class="password-wrapper">
                        <input type="password" name="tripay_api_key" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['TRIPAY_API_KEY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               id="tripay_api">
                        <i class="fas fa-eye toggle-password" data-target="tripay_api"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tripay Private Key</label>
                    <div class="password-wrapper">
                        <input type="password" name="tripay_private_key" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['TRIPAY_PRIVATE_KEY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               id="tripay_private">
                        <i class="fas fa-eye toggle-password" data-target="tripay_private"></i>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tripay Merchant Code</label>
                    <input type="text" name="tripay_merchant_code" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['TRIPAY_MERCHANT_CODE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tripay Mode</label>
                    <select name="tripay_mode" class="form-control">
                        <option value="" <?php echo empty($settings['TRIPAY_MODE'] ?? '') ? 'selected' : ''; ?>>Production</option>
                        <option value="sandbox" <?php echo (($settings['TRIPAY_MODE'] ?? '') === 'sandbox') ? 'selected' : ''; ?>>Sandbox</option>
                    </select>
                </div>
            </div>
            
            <!-- Midtrans Webhook -->
            <div class="webhook-info" style="margin-top: 24px;">
                <div class="info-header">
                    <i class="fas fa-link"></i>
                    <strong>URL Webhook Midtrans</strong>
                </div>
                <p class="info-text">Paste URL ini ke Payment Notification URL di Midtrans Dashboard</p>
                <div class="url-wrapper">
                    <input type="text" id="midtrans_webhook_url" readonly
                        value="<?php echo APP_URL; ?>/webhooks/midtrans.php"
                        class="webhook-input"
                        onclick="this.select()">
                    <button type="button" class="btn-icon" onclick="copyToClipboardById('midtrans_webhook_url')">
                        <i class="fas fa-copy"></i> Salin
                    </button>
                </div>
            </div>
            
            <h4 class="section-subtitle">Midtrans</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Midtrans API Key</label>
                    <div class="password-wrapper">
                        <input type="password" name="midtrans_api_key" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['MIDTRANS_API_KEY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               id="midtrans_api">
                        <i class="fas fa-eye toggle-password" data-target="midtrans_api"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Midtrans Merchant Code</label>
                    <input type="text" name="midtrans_merchant_code" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['MIDTRANS_MERCHANT_CODE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <small class="form-hint">Client ID / Merchant ID dari Midtrans</small>
                </div>
            </div>
            
            <h4 class="section-subtitle">Default Gateway</h4>
            <div class="form-group">
                <label class="form-label">Payment Gateway Default</label>
                <select name="default_payment_gateway" class="form-control">
                    <option value="tripay" <?php echo ($settings['DEFAULT_PAYMENT_GATEWAY'] ?? '') === 'tripay' ? 'selected' : ''; ?>>Tripay</option>
                    <option value="midtrans" <?php echo ($settings['DEFAULT_PAYMENT_GATEWAY'] ?? '') === 'midtrans' ? 'selected' : ''; ?>>Midtrans</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Telegram Settings -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fab fa-telegram-plane"></i> Telegram Bot
        </h3>
    </div>
    <div class="card-body">
        <div class="webhook-info">
            <div class="info-header">
                <i class="fas fa-link"></i>
                <strong>URL Webhook Telegram</strong>
            </div>
            <p class="info-text">Gunakan URL ini untuk setWebhook di BotFather</p>
            <div class="url-wrapper">
                <input type="text" id="telegram_webhook_url" readonly
                    value="<?php echo APP_URL; ?>/webhooks/telegram.php"
                    class="webhook-input"
                    onclick="this.select()">
                <button type="button" class="btn-icon" onclick="copyToClipboardById('telegram_webhook_url')">
                    <i class="fas fa-copy"></i> Salin
                </button>
            </div>
        </div>
        
        <form method="POST" id="telegram-form">
            <input type="hidden" name="action" value="save_telegram_settings">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Bot Token</label>
                    <div class="password-wrapper">
                        <input type="password" name="telegram_bot_token" class="form-control" 
                               value="<?php echo htmlspecialchars(getSettingValue('TELEGRAM_BOT_TOKEN', ''), ENT_QUOTES, 'UTF-8'); ?>" 
                               placeholder="123456:ABC-DEF..." id="telegram_token">
                        <i class="fas fa-eye toggle-password" data-target="telegram_token"></i>
                    </div>
                    <small class="form-hint">Dapatkan dari @BotFather di Telegram</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Admin Chat ID</label>
                    <input type="text" name="telegram_admin_chat_id" class="form-control" 
                           value="<?php echo htmlspecialchars(getSettingValue('TELEGRAM_ADMIN_CHAT_ID', ''), ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="123456789">
                    <small class="form-hint">Dapatkan dari @userinfobot atau @getidsbot</small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Telegram
                </button>
                <button type="button" class="btn btn-success" onclick="testTelegram()">
                    <i class="fas fa-paper-plane"></i> Test Kirim
                </button>
                <button type="button" class="btn btn-secondary" onclick="setTelegramWebhook()">
                    <i class="fas fa-link"></i> Set Webhook
                </button>
                <button type="button" class="btn btn-info" onclick="getTelegramWebhookInfo()">
                    <i class="fas fa-info-circle"></i> Info Webhook
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Landing Page Settings -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-globe"></i> Landing Page
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_landing">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-group">
                <label class="form-label">Template</label>
                <select name="landing_template" class="form-control">
                    <option value="shadcn_light" <?php echo ($siteSettings['landing_template'] ?? 'shadcn_light') === 'shadcn_light' ? 'selected' : ''; ?>>Shadcn UI (Light)</option>
                    <option value="shadcn_dark" <?php echo ($siteSettings['landing_template'] ?? 'shadcn_dark') === 'shadcn_dark' ? 'selected' : ''; ?>>Shadcn UI (Dark)</option>
                    <option value="shadcn" <?php echo ($siteSettings['landing_template'] ?? 'shadcn') === 'shadcn' ? 'selected' : ''; ?>>Shadcn UI</option>
                    <option value="neon" <?php echo ($siteSettings['landing_template'] ?? 'neon') === 'neon' ? 'selected' : ''; ?>>Neon Dark</option>
                    <option value="dark" <?php echo ($siteSettings['landing_template'] ?? '') === 'dark' ? 'selected' : ''; ?>>Dark (GitHub Style)</option>
                    <option value="modern" <?php echo ($siteSettings['landing_template'] ?? '') === 'modern' ? 'selected' : ''; ?>>Modern Clean</option>
                    <option value="glassmorphism" <?php echo ($siteSettings['landing_template'] ?? '') === 'glassmorphism' ? 'selected' : ''; ?>>Glassmorphism</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Theme Color</label>
                <select name="theme_color" class="form-control">
                    <option value="neon" <?php echo ($siteSettings['theme_color'] ?? 'neon') === 'neon' ? 'selected' : ''; ?>>Neon (Cyan & Purple)</option>
                    <option value="ocean" <?php echo ($siteSettings['theme_color'] ?? '') === 'ocean' ? 'selected' : ''; ?>>Ocean (Blue & Teal)</option>
                    <option value="nature" <?php echo ($siteSettings['theme_color'] ?? '') === 'nature' ? 'selected' : ''; ?>>Nature (Green & Lime)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Hero Title</label>
                <input type="text" name="hero_title" class="form-control" 
                       value="<?php echo htmlspecialchars($siteSettings['hero_title'] ?? 'Internet Cepat & Stabil', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Hero Description</label>
                <textarea name="hero_description" class="form-control" rows="3"><?php echo htmlspecialchars($siteSettings['hero_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['contact_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Email</label>
                    <input type="email" name="contact_email" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['contact_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Contact Address</label>
                <textarea name="contact_address" class="form-control" rows="2"><?php echo htmlspecialchars($siteSettings['contact_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Footer About</label>
                <textarea name="footer_about" class="form-control" rows="2"><?php echo htmlspecialchars($siteSettings['footer_about'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            
            <h4 class="section-subtitle">Fitur (3 Kolom)</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Fitur 1 - Judul</label>
                    <input type="text" name="feature_1_title" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['feature_1_title'] ?? 'Kecepatan Tinggi', ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-label" style="margin-top: 8px;">Deskripsi</label>
                    <textarea name="feature_1_desc" class="form-control" rows="2"><?php echo htmlspecialchars($siteSettings['feature_1_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Fitur 2 - Judul</label>
                    <input type="text" name="feature_2_title" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['feature_2_title'] ?? 'Unlimited Quota', ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-label" style="margin-top: 8px;">Deskripsi</label>
                    <textarea name="feature_2_desc" class="form-control" rows="2"><?php echo htmlspecialchars($siteSettings['feature_2_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Fitur 3 - Judul</label>
                    <input type="text" name="feature_3_title" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['feature_3_title'] ?? 'Support 24/7', ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-label" style="margin-top: 8px;">Deskripsi</label>
                    <textarea name="feature_3_desc" class="form-control" rows="2"><?php echo htmlspecialchars($siteSettings['feature_3_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
            
            <h4 class="section-subtitle">Media Sosial</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-facebook"></i> Facebook</label>
                    <input type="text" name="social_facebook" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['social_facebook'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-instagram"></i> Instagram</label>
                    <input type="text" name="social_instagram" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['social_instagram'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-twitter"></i> Twitter</label>
                    <input type="text" name="social_twitter" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['social_twitter'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-youtube"></i> YouTube</label>
                    <input type="text" name="social_youtube" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['social_youtube'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Landing Page
                </button>
            </div>
        </form>
    </div>
</div>

<!-- FAQ Management -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-question-circle"></i> FAQ Management
        </h3>
    </div>
    <div class="card-body">
        <!-- Add FAQ Form -->
        <div class="faq-add-form">
            <h4 class="section-subtitle">Tambah FAQ Baru</h4>
            <form method="POST">
                <input type="hidden" name="action" value="manage_faq">
                <input type="hidden" name="faq_action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <div class="form-group">
                    <label class="form-label">Pertanyaan</label>
                    <input type="text" name="faq_question" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Jawaban</label>
                    <textarea name="faq_answer" class="form-control" rows="3" required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah FAQ
                </button>
            </form>
        </div>
        
        <!-- FAQ List -->
        <div class="faq-list">
            <h4 class="section-subtitle">Daftar FAQ (<?php echo count($faqs); ?>)</h4>
            
            <?php if (empty($faqs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Belum ada FAQ</p>
                </div>
            <?php else: ?>
                <?php foreach ($faqs as $faq): ?>
                <div class="faq-item" id="faq-<?php echo (int)$faq['id']; ?>">
                    <div class="faq-content">
                        <strong><?php echo htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <p><?php echo htmlspecialchars(substr($faq['answer'], 0, 150), ENT_QUOTES, 'UTF-8'); ?>...</p>
                        <div class="faq-meta">
                            <span class="badge <?php echo $faq['is_active'] ? 'badge-success' : 'badge-muted'; ?>">
                                <?php echo $faq['is_active'] ? 'Tampil' : 'Tersembunyi'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="faq-actions">
                        <button type="button" class="btn-icon" onclick="editFaqModal(<?php echo (int)$faq['id']; ?>)" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Hapus FAQ ini?');">
                            <input type="hidden" name="action" value="manage_faq">
                            <input type="hidden" name="faq_action" value="delete">
                            <input type="hidden" name="faq_id" value="<?php echo (int)$faq['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <button type="submit" class="btn-icon danger" title="Hapus">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cron Settings -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-clock"></i> Cronjob & Scheduler
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_cron_settings">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="webhook-info">
                <div class="info-header">
                    <i class="fas fa-terminal"></i>
                    <strong>Cronjob URL / Command</strong>
                </div>
                <p class="info-text">Jalankan setiap 1 menit untuk tugas otomatis (check expired, kirim notifikasi, dll)</p>
                
<!--                <div class="url-wrapper">-->
<!--                    <input type="text" id="cron_web_url" readonly-->
<!--                        value="--><?php //echo APP_URL; ?><!--/cron/run.php?token=--><?php //echo htmlspecialchars(getSettingValue('CRON_TOKEN', ''), ENT_QUOTES, 'UTF-8'); ?><!--"-->
<!--                        class="webhook-input"-->
<!--                        onclick="this.select()">-->
<!--                    <button type="button" class="btn-icon" onclick="copyToClipboardById('cron_web_url')">-->
<!--                        <i class="fas fa-copy"></i> Salin URL-->
<!--                    </button>-->
<!--                </div>-->
                
                <?php
                $schedulerPath = realpath(__DIR__ . '/../cron/scheduler.php');
                if ($schedulerPath === false) {
                    $schedulerPath = __DIR__ . '/../cron/scheduler.php';
                }
                ?>
                <div class="url-wrapper" style="margin-top: 10px;">
                    <input type="text" id="cron_cli_path" readonly
                        value="* * * * * /usr/bin/php <?php echo htmlspecialchars($schedulerPath, ENT_QUOTES, 'UTF-8'); ?>"
                        class="webhook-input"
                        onclick="this.select()">
                    <button type="button" class="btn-icon" onclick="copyToClipboardById('cron_cli_path')">
                        <i class="fas fa-copy"></i> Salin Command
                    </button>
                </div>
            </div>
            
<!--            <div class="form-group">-->
<!--                <label class="form-label">Cron Token</label>-->
<!--                <div class="password-wrapper">-->
<!--                    <input type="text" name="cron_token" class="form-control" -->
<!--                           value="--><?php //echo htmlspecialchars(getSettingValue('CRON_TOKEN', ''), ENT_QUOTES, 'UTF-8'); ?><!--">-->
<!--                    <i class="fas fa-sync-alt toggle-generate" onclick="generateCronToken()" style="cursor: pointer; position: absolute; right: 12px; top: 50%; transform: translateY(-50%);"></i>-->
<!--                </div>-->
<!--                <small class="form-hint">Token untuk keamanan cronjob. Klik icon refresh untuk generate token baru</small>-->
<!--            </div>-->
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Token
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Backup & Restore-->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-database"></i> Backup & Restore
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_backup_settings">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Retensi Backup (hari)</label>
                    <input type="number" name="backup_retention_days" class="form-control"
                           min="1" max="365" value="<?php echo $backupRetentionDays; ?>">
                    <small class="form-hint">Backup lebih lama dari ini akan dihapus otomatis saat backup berikutnya</small>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Retensi
                    </button>
                </div>
            </div>
        </form>

        <div class="form-actions" style="justify-content: flex-start;">
            <form method="POST" onsubmit="return confirm('Buat backup database sekarang?');">
                <input type="hidden" name="action" value="backup_now">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-download"></i> Backup Sekarang
                </button>
            </form>
        </div>

        <h4 class="section-subtitle">Daftar Backup</h4>
        <?php if (empty($backupFiles)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Belum ada file backup</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama File</th>
                            <th>Ukuran</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backupFiles as $file): ?>
                        <tr>
                            <td data-label="Nama File"><?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Ukuran"><?php echo htmlspecialchars(formatBytes($file['size'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Tanggal"><?php echo htmlspecialchars($file['modified_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Aksi">
                                <a class="btn-icon" href="settings.php?download_backup=<?php echo urlencode($file['name']); ?>&csrf_token=<?php echo urlencode(generateCsrfToken()); ?>" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Restore Form -->
        <div class="restore-section" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-light);">
            <h4 class="section-subtitle">Restore Database</h4>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <div><strong>Peringatan!</strong> Restore akan menimpa data database saat ini. Pastikan Anda telah melakukan backup terlebih dahulu.</div>
            </div>

            <form method="POST" onsubmit="return confirmRestore();">
                <input type="hidden" name="action" value="restore_backup">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label">Pilih File Backup</label>
                        <select name="backup_file" class="form-control" required>
                            <option value="">-- Pilih file backup --</option>
                            <?php foreach ($backupFiles as $file): ?>
                                <option value="<?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    (<?php echo formatBytes($file['size'] ?? 0); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Ketik <strong class="text-danger">RESTORE</strong> untuk konfirmasi</label>
                        <input type="text" name="confirm_restore" class="form-control" placeholder="RESTORE" required>
                    </div>
                </div>

                <div class="form-actions" style="justify-content: flex-start;">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-upload"></i> Restore Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-key"></i> Ganti Password Admin
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-group">
                <label class="form-label">Password Saat Ini</label>
                <div class="password-wrapper">
                    <input type="password" name="current_password" class="form-control" placeholder="•••••••••" required>
                    <i class="fas fa-eye toggle-password"></i>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password Baru</label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                        <i class="fas fa-eye toggle-password"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Konfirmasi Password Baru</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" class="form-control" placeholder="Ketik ulang password baru" required minlength="6">
                        <i class="fas fa-eye toggle-password"></i>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-key"></i> Ubah Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL EDIT NAS ==================== -->
<div class="modal fade" id="editNasModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Edit NAS
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="editNasForm">
                <input type="hidden" name="action" value="edit_nas">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="nas_id" id="edit_nas_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">NAS Name</label>
                        <input type="text" name="nas_name" id="edit_nas_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">NAS IP</label>
                        <input type="text" name="nas_ip" id="edit_nas_ip" class="form-control" required>
                        <small class="form-hint">IP Address client (MikroTik, AP, dll)</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Secret</label>
                        <div class="password-wrapper">
                            <input type="text" name="nas_secret" id="edit_nas_secret" class="form-control" required>
                            <i class="fas fa-eye toggle-password"></i>
                        </div>
                        <small class="form-hint">Password/Secret untuk koneksi RADIUS</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Deskripsi (Opsional)</label>
                        <textarea name="description" id="edit_nas_description" class="form-control" rows="2" placeholder="Deskripsi NAS..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL EDIT FAQ ==================== -->
<div class="modal fade" id="editFaqModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Edit FAQ
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="editFaqForm">
                <input type="hidden" name="action" value="manage_faq">
                <input type="hidden" name="faq_action" value="update">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="faq_id" id="edit_faq_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Pertanyaan</label>
                        <input type="text" name="faq_question" id="edit_faq_question" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Jawaban</label>
                        <textarea name="faq_answer" id="edit_faq_answer" class="form-control" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="faq_active" id="edit_faq_active" value="1">
                            <span>Aktif (ditampilkan di landing page)</span>
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
:root {
    --bg-primary: #0a0e1a;
    --bg-secondary: #111827;
    --bg-tertiary: #1a2332;
    --text-primary: #ffffff;
    --text-secondary: #cbd5e1;
    --text-muted: #6b7280;
    --border-color: #2d3748;
    --border-light: #1f2937;
    --accent-blue: #3b82f6;
    --accent-green: #10b981;
    --accent-red: #ef4444;
    --accent-orange: #f59e0b;
    --accent-purple: #8b5cf6;
    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 16px;
    --transition-fast: 0.2s ease;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all var(--transition-fast);
}

.stat-card:hover {
    border-color: var(--accent-blue);
    transform: translateY(-2px);
}

.stat-info h3 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 4px;
    background: linear-gradient(135deg, var(--text-primary) 0%, var(--text-secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-info p {
    font-size: 13px;
    color: var(--text-muted);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.blue { background: rgba(59, 130, 246, 0.1); color: var(--accent-blue); }
.stat-icon.green { background: rgba(16, 185, 129, 0.1); color: var(--accent-green); }
.stat-icon.purple { background: rgba(139, 92, 246, 0.1); color: var(--accent-purple); }
.stat-icon.red { background: rgba(239, 68, 68, 0.1); color: var(--accent-red); }
.stat-icon.orange { background: rgba(245, 158, 11, 0.1); color: var(--accent-orange); }

/* Cards */
.card {
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    margin-bottom: 24px;
    overflow: hidden;
}

.card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.card-title {
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-primary);
}

.card-title i {
    color: var(--accent-blue);
}

.card-body {
    padding: 20px;
}

/* Card Actions */
.card-actions {
    margin-bottom: 20px;
    text-align: right;
}

/* Form Elements */
.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--text-secondary);
}

.form-label i {
    margin-right: 6px;
    color: var(--accent-blue);
    font-size: 12px;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    font-size: 14px;
    transition: all var(--transition-fast);
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-blue);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.form-control:read-only,
.form-control[readonly] {
    background: var(--bg-tertiary);
    cursor: default;
    opacity: 0.8;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.form-hint {
    display: block;
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 16px;
    margin-top: 8px;
    border-top: 1px solid var(--border-light);
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all var(--transition-fast);
    border: none;
    background: none;
}

.btn-primary {
    background: var(--accent-blue);
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-secondary {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
}

.btn-secondary:hover {
    background: var(--bg-primary);
    border-color: var(--accent-blue);
    color: var(--accent-blue);
}

.btn-success {
    background: var(--accent-green);
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-danger {
    background: var(--accent-red);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.btn-warning {
    background: var(--accent-orange);
    color: white;
}

.btn-warning:hover {
    background: #d97706;
}

.btn-icon {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-light);
    color: var(--text-secondary);
    cursor: pointer;
    padding: 6px 10px;
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-icon:hover {
    background: var(--bg-secondary);
    border-color: var(--border-color);
    color: var(--accent-blue);
}

.btn-icon.danger:hover {
    color: var(--accent-red);
    border-color: var(--accent-red);
}

.btn-icon.success:hover {
    color: var(--accent-green);
    border-color: var(--accent-green);
}

/* Tables */
.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-light);
}

.data-table th {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    background: var(--bg-tertiary);
}

.data-table td {
    font-size: 14px;
}

.data-table tr:hover {
    background: rgba(59, 130, 246, 0.05);
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.badge-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--accent-green);
}

.badge-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--accent-red);
}

.badge-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--accent-orange);
}

.badge-info {
    background: rgba(59, 130, 246, 0.1);
    color: var(--accent-blue);
}

.badge-muted {
    background: var(--bg-tertiary);
    color: var(--text-muted);
}

/* Alerts */
.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius-md);
    margin-bottom: 20px;
}

.alert-info {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: var(--accent-blue);
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: var(--accent-orange);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: var(--accent-red);
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: var(--accent-green);
}

.alert i {
    font-size: 18px;
}

.alert div {
    flex: 1;
}

/* Password Wrapper */
.password-wrapper {
    position: relative;
}

.password-wrapper .toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--text-muted);
    transition: color var(--transition-fast);
    z-index: 1;
}

.password-wrapper .toggle-password:hover {
    color: var(--accent-blue);
}

/* Webhook Info */
.webhook-info {
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 16px 20px;
    margin-bottom: 20px;
}

.info-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: var(--accent-blue);
}

.info-header i {
    font-size: 16px;
}

.info-text {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 12px;
}

.url-wrapper {
    display: flex;
    gap: 10px;
    align-items: center;
}

.webhook-input {
    flex: 1;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: var(--radius-sm);
    padding: 8px 12px;
    font-size: 12px;
    font-family: monospace;
    cursor: pointer;
}

.webhook-input:focus {
    outline: none;
    border-color: var(--accent-blue);
}

/* Script Wrapper */
.script-wrapper {
    position: relative;
}

.script-wrapper .copy-btn {
    position: absolute;
    right: 8px;
    top: 8px;
}

/* Section Subtitle */
.section-subtitle {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 20px 0 16px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-subtitle:first-of-type {
    margin-top: 0;
}

.section-subtitle i {
    color: var(--accent-blue);
    font-size: 14px;
}

/* FAQ Items */
.faq-add-form {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border-light);
}

.faq-list {
    margin-top: 16px;
}

.faq-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 16px;
    background: var(--bg-tertiary);
    border-radius: var(--radius-md);
    margin-bottom: 12px;
    transition: all var(--transition-fast);
}

.faq-item:hover {
    background: var(--bg-secondary);
    border-left: 3px solid var(--accent-blue);
}

.faq-content {
    flex: 1;
}

.faq-content strong {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    color: var(--text-primary);
}

.faq-content p {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.faq-meta {
    margin-top: 8px;
}

.faq-actions {
    display: flex;
    gap: 8px;
}

/* Test Section */
.test-section {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-light);
}

/* Restore Section */
.restore-section {
    margin-top: 24px;
}

/* Add NAS form */
.add-nas-form {
    margin-bottom: 24px;
}

/* Secret dots */
.secret-dots {
    font-family: monospace;
    letter-spacing: 2px;
    color: var(--text-muted);
}

.toggle-secret {
    margin-left: 8px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px !important;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 14px;
}

.empty-state small {
    font-size: 12px;
}

/* Inline Form */
.inline-form {
    display: inline;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.modal.show {
    display: flex;
}

.modal-dialog {
    width: 90%;
    max-width: 500px;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-content {
    background: var(--bg-secondary);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-light);
    background: var(--bg-tertiary);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header .modal-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-header .modal-title i {
    color: var(--accent-blue);
}

.modal-header .close {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 28px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
}

.modal-header .close:hover {
    background: var(--bg-primary);
    color: var(--accent-red);
}

.modal-body {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border-light);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: var(--bg-tertiary);
}

/* Checkbox */
.checkbox-group {
    display: flex;
    align-items: flex-end;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 10px 0;
}

.checkbox-label input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent-blue);
}

.checkbox-label span {
    font-size: 13px;
    color: var(--text-secondary);
}

.checkbox-label span i {
    margin-right: 4px;
}

.form-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    color: var(--text-secondary);
}

.form-checkbox input {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: var(--accent-blue);
}

/* Search Wrapper */
.search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-wrapper i {
    position: absolute;
    left: 12px;
    color: var(--text-muted);
    font-size: 14px;
}

.search-wrapper .form-control {
    padding-left: 36px;
    width: 250px;
}

/* Text utilities */
.text-danger {
    color: var(--accent-red);
}

.text-muted {
    color: var(--text-muted);
}

.text-success {
    color: var(--accent-green);
}

.text-warning {
    color: var(--accent-orange);
}

/* Code styling */
code {
    background: var(--bg-tertiary);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    color: var(--accent-blue);
}

.host-address {
    font-family: monospace;
    font-size: 13px;
    background: var(--bg-tertiary);
    padding: 4px 8px;
    border-radius: 4px;
    color: var(--accent-blue);
    display: inline-block;
}

.port-badge {
    display: inline-block;
    background: var(--bg-tertiary);
    padding: 4px 10px;
    border-radius: 20px;
    font-family: monospace;
    font-size: 12px;
    font-weight: 600;
}

/* Toast animation */
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.toast {
    animation: slideIn 0.3s ease;
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-tertiary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--accent-blue);
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }
    
    .url-wrapper {
        flex-direction: column;
    }
    
    .url-wrapper .btn-icon {
        width: 100%;
    }
    
    .faq-item {
        flex-direction: column;
    }
    
    .faq-actions {
        justify-content: flex-end;
        width: 100%;
    }
    
    .webhook-input {
        font-size: 10px;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px 12px;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .search-wrapper {
        width: 100%;
    }
    
    .search-wrapper .form-control {
        width: 100%;
    }
    
    .modal-dialog {
        width: 95%;
        margin: 16px;
    }
}

/* Toast animation */
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.toast {
    animation: slideIn 0.3s ease;
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-tertiary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--accent-blue);
}

/* Loading state */
.btn-loading {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn-loading i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Code styling */
code {
    background: var(--bg-tertiary);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    color: var(--accent-blue);
}

/* Host address like routers.php */
.host-address {
    font-family: monospace;
    font-size: 13px;
    background: var(--bg-tertiary);
    padding: 4px 8px;
    border-radius: 4px;
    color: var(--accent-blue);
    display: inline-block;
}

/* Port badge like routers.php */
.port-badge {
    display: inline-block;
    background: var(--bg-tertiary);
    padding: 4px 10px;
    border-radius: 20px;
    font-family: monospace;
    font-size: 12px;
    font-weight: 600;
}

/* Card actions like routers.php */
.card-actions {
    margin-bottom: 20px;
    text-align: right;
}
</style>

<!-- ==================== SCRIPTS ==================== -->
<script>
// Helper function untuk copy ke clipboard
function copyToClipboardById(elementId) {
    const input = document.getElementById(elementId);
    if (!input) return;
    
    input.select();
    input.setSelectionRange(0, 99999);
    
    try {
        navigator.clipboard.writeText(input.value);
        showToast('Berhasil disalin!', 'success');
    } catch (err) {
        document.execCommand('copy');
        showToast('Berhasil disalin!', 'success');
    }
}

function copyToClipboardValueById(elementId) {
    const textarea = document.getElementById(elementId);
    if (!textarea || !textarea.value) {
        showToast('Tidak ada teks untuk disalin', 'error');
        return;
    }
    
    textarea.select();
    textarea.setSelectionRange(0, 99999);
    
    try {
        navigator.clipboard.writeText(textarea.value);
        showToast('Script berhasil disalin!', 'success');
    } catch (err) {
        document.execCommand('copy');
        showToast('Script berhasil disalin!', 'success');
    }
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i> ${message}`;
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        padding: 12px 20px;
        border-radius: var(--radius-md);
        color: var(--text-primary);
        z-index: 10001;
        animation: slideIn 0.3s ease;
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(icon => {
    icon.addEventListener('click', function() {
        const input = this.closest('.password-wrapper').querySelector('input');
        if (input.type === 'password') {
            input.type = 'text';
            this.classList.remove('fa-eye');
            this.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            this.classList.remove('fa-eye-slash');
            this.classList.add('fa-eye');
        }
    });
});

// Toggle secret visibility in NAS table
document.querySelectorAll('.toggle-secret').forEach(btn => {
    btn.addEventListener('click', function() {
        const row = this.closest('tr');
        const dots = row.querySelector('.secret-dots');
        const value = row.querySelector('.secret-value');
        const icon = this.querySelector('i');
        
        if (dots.style.display === 'none') {
            dots.style.display = 'inline';
            value.style.display = 'none';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            dots.style.display = 'none';
            value.style.display = 'inline';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    });
});

// Radius Script Generator
function generateRadiusScript() {
    const selector = document.getElementById('radius_nas_selector');
    const textarea = document.getElementById('radius_add_script');
    
    if (!selector || !textarea) return;
    
    const selected = selector.options[selector.selectedIndex];
    
    if (!selected.value) {
        textarea.value = '';
        return;
    }
    
    const nasname = selected.value;
    const secret = selected.getAttribute('data-secret');
    const nasName = selected.getAttribute('data-name');
    const radiusIp = document.querySelector('input[name="server_ip"]')?.value || '10.7.0.1';
    
    const script = `/radius add address=${radiusIp} service=ppp,hotspot secret=${secret} src-address=${nasname} comment="${nasName} - RADIUS Client"`;
    textarea.value = script;
}

function copyRadiusScript() {
    const textarea = document.getElementById('radius_add_script');
    if (!textarea || !textarea.value) {
        alert('Pilih NAS terlebih dahulu');
        return;
    }
    copyToClipboardValueById('radius_add_script');
}

// Initialize radius script generator
document.getElementById('radius_nas_selector')?.addEventListener('change', generateRadiusScript);

// ==================== EDIT NAS MODAL ====================
function editNasModal(nasId) {
    // Fetch NAS data via AJAX
    fetch(`settings.php?get_nas=1&id=${nasId}&csrf_token=<?php echo generateCsrfToken(); ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_nas_id').value = data.data.id;
                document.getElementById('edit_nas_name').value = data.data.shortname;
                document.getElementById('edit_nas_ip').value = data.data.nasname;
                document.getElementById('edit_nas_secret').value = data.data.secret;
                document.getElementById('edit_nas_description').value = data.data.description || '';
                
                const modal = document.getElementById('editNasModal');
                modal.classList.add('show');
            } else {
                showToast(data.message || 'Gagal mengambil data NAS', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Terjadi kesalahan saat mengambil data', 'error');
        });
}

// Close modal when clicking on close button or outside
document.querySelectorAll('.modal .close, .modal .btn-secondary').forEach(btn => {
    btn?.addEventListener('click', function() {
        this.closest('.modal')?.classList.remove('show');
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
});

// ==================== EDIT FAQ MODAL ====================
function editFaqModal(faqId) {
    // Find FAQ data from the DOM
    const faqItem = document.getElementById(`faq-${faqId}`);
    if (!faqItem) return;
    
    const questionElem = faqItem.querySelector('.faq-content strong');
    const answerPreview = faqItem.querySelector('.faq-content p');
    const activeBadge = faqItem.querySelector('.badge');
    
    const question = questionElem ? questionElem.textContent : '';
    const isActive = activeBadge ? activeBadge.textContent.trim() === 'Tampil' : true;
    
    // For full answer, we need to get from data attribute or fetch
    // For simplicity, we'll fetch from server
    fetch(`settings.php?get_faq=1&id=${faqId}&csrf_token=<?php echo generateCsrfToken(); ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_faq_id').value = data.data.id;
                document.getElementById('edit_faq_question').value = data.data.question;
                document.getElementById('edit_faq_answer').value = data.data.answer;
                document.getElementById('edit_faq_active').checked = data.data.is_active == 1;
                
                const modal = document.getElementById('editFaqModal');
                modal.classList.add('show');
            } else {
                // Fallback: use data from DOM
                document.getElementById('edit_faq_id').value = faqId;
                document.getElementById('edit_faq_question').value = question;
                document.getElementById('edit_faq_answer').value = answerPreview ? answerPreview.textContent.replace('...', '') : '';
                document.getElementById('edit_faq_active').checked = isActive;
                
                const modal = document.getElementById('editFaqModal');
                modal.classList.add('show');
            }
        })
        .catch(() => {
            // Fallback
            document.getElementById('edit_faq_id').value = faqId;
            document.getElementById('edit_faq_question').value = question;
            document.getElementById('edit_faq_answer').value = '';
            document.getElementById('edit_faq_active').checked = isActive;
            
            const modal = document.getElementById('editFaqModal');
            modal.classList.add('show');
        });
}

// ==================== TEST FUNCTIONS ====================
function testMikrotikConnection() {
    showToast('Menguji koneksi MikroTik...', 'info');
    
    const host = document.querySelector('input[name="mikrotik_host"]')?.value;
    const user = document.querySelector('input[name="mikrotik_user"]')?.value;
    const pass = document.querySelector('input[name="mikrotik_pass"]')?.value;
    const port = document.querySelector('input[name="mikrotik_port"]')?.value || 8728;
    
    if (!host) {
        showToast('Masukkan IP MikroTik terlebih dahulu', 'error');
        return;
    }
    
    fetch('../api/mikrotik_test.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ host, user, pass, port })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Koneksi MikroTik berhasil!', 'success');
        } else {
            showToast('Koneksi MikroTik gagal: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        showToast('Error: ' + error.message, 'error');
    });
}

function testMpwaConnection() {
    showToast('Menguji koneksi MPWA...', 'info');
    
    fetch('settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'test_mpwa_connection',
            csrf_token: '<?php echo generateCsrfToken(); ?>'
        })
    })
    .then(() => {
        // Redirect will happen, so we don't need to handle response
    });
}

function testTelegram() {
    const token = document.querySelector('input[name="telegram_bot_token"]')?.value;
    const chatId = document.querySelector('input[name="telegram_admin_chat_id"]')?.value;
    
    if (!token || !chatId) {
        showToast('Isi Bot Token dan Admin Chat ID terlebih dahulu', 'error');
        return;
    }
    
    showToast('Mengirim test Telegram...', 'info');
    
    fetch('settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'test_telegram',
            csrf_token: '<?php echo generateCsrfToken(); ?>'
        })
    });
}

function setTelegramWebhook() {
    showToast('Mengatur webhook Telegram...', 'info');
    
    fetch('settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'telegram_set_webhook',
            csrf_token: '<?php echo generateCsrfToken(); ?>'
        })
    });
}

function getTelegramWebhookInfo() {
    showToast('Mengambil info webhook...', 'info');
    
    fetch('settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'telegram_webhook_info',
            csrf_token: '<?php echo generateCsrfToken(); ?>'
        })
    });
}

function generateCronToken() {
    const randomToken = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
    document.querySelector('input[name="cron_token"]').value = randomToken;
    showToast('Token baru telah digenerate', 'success');
}

function restartService(service) {
    if (!confirm(`Restart service ${service}?`)) return;
    
    showToast(`Merestart ${service}...`, 'info');
    
    fetch(`../api/services.php?action=restart_${service}&token=<?php echo getSettingValue('CRON_TOKEN', ''); ?>`)
        .then(async response => {
            const contentType = response.headers.get('content-type') || '';
            const rawText = await response.text();

            if (!response.ok) {
                throw new Error(rawText || `HTTP ${response.status}`);
            }

            if (contentType.includes('application/json')) {
                return JSON.parse(rawText);
            }

            throw new Error(rawText || 'Response bukan JSON');
        })
        .then(data => {
            if (data.success) {
                showToast(`Service ${service} berhasil direstart`, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(`Gagal restart ${service}: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            showToast(`Error: ${error.message}`, 'error');
        });
}

function confirmRestore() {
    const confirmText = document.querySelector('input[name="confirm_restore"]')?.value;
    if (confirmText !== 'RESTORE') {
        alert('Ketik RESTORE untuk konfirmasi restore database');
        return false;
    }
    return confirm('PERINGATAN! Restore akan menimpa semua data saat ini.\n\nPastikan Anda sudah backup data terlebih dahulu.\n\nLanjutkan?');
}

// Add slideIn animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);

// Form loading state
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const btn = this.querySelector('button[type="submit"]');
        if (btn && !btn.classList.contains('no-loading')) {
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }, 30000);
        }
    });
});

// Auto refresh script when NAS selector changes
document.getElementById('radius_nas_selector')?.addEventListener('change', generateRadiusScript);
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
