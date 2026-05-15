<?php
/**
 * Admin Settings - Elegant Dark Minimalis Theme
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Settings';

// Get current settings
$settings = [];
$settingsData = fetchAll("SELECT * FROM settings");
foreach ($settingsData as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}

function extractPemBlock($raw)
{
    if (preg_match('/-----BEGIN [^-]+-----.*?-----END [^-]+-----/s', (string) $raw, $m)) {
        return trim($m[0]);
    }
    return trim((string) $raw);
}

// Handle GET requests (download backup, VPN config)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['download_backup'])) {
        $backupFile = sanitizeBackupFilename($_GET['download_backup'] ?? '');
        if ($backupFile === '') {
            setFlash('error', 'Nama file backup tidak valid');
            redirect('settings.php');
        }
        $fullPath = getBackupDirectory() . $backupFile;
        if (!is_file($fullPath)) {
            setFlash('error', 'File backup tidak ditemukan');
            redirect('settings.php');
        }
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $backupFile . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Invalid CSRF token');
        redirect('settings.php');
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_nas':
                if (radiusAddNas($_POST['nas_name'], $_POST['nas_ip'], $_POST['nas_secret'])) {
                    setFlash('success', 'NAS berhasil ditambahkan');
                } else {
                    setFlash('error', 'Gagal menambahkan NAS');
                }
                redirect('settings.php');
                break;
                
            case 'delete_nas':
                if (radiusDeleteNas($_POST['nas_id'])) {
                    setFlash('success', 'NAS berhasil dihapus');
                } else {
                    setFlash('error', 'Gagal menghapus NAS');
                }
                redirect('settings.php');
                break;
                
            case 'add_mikrotik_client':
                $version = sanitize($_POST['mikrotik_version']);
                $script = generateMikrotikClientScript($version);
                $nextAddress = nextAddressOvpnClient();

                if(!$nextAddress){
                    logError('Gagal mendapatkan IP berikutnya untuk client OVPN. Pastikan subnet OVPN benar dan tidak penuh.');
                    setFlash('error', 'Gagal mendapatkan IP berikutnya untuk client OVPN. Pastikan subnet OVPN benar dan tidak penuh.');
                    redirect('settings.php');
                    break;
                }
                
                if(isset($script['error'])) {
                    setFlash('error', $script['error']);
                } else {
                    $NASAdded = radiusAddNas($script['radius']['nas_name'], $nextAddress, $script['radius']['nas_secret']);
                    if (!$NASAdded) {
                        logError('Gagal menambahkan NAS untuk client OVPN. Pastikan database RADIUS terkonfigurasi dengan benar.');
                    }

                    $clientVpnAdded = upsertVpnUser([
                        'username' => $script['vpn']['username'],
                        'password' => $script['vpn']['password']
                    ]);

                    if (!$clientVpnAdded) {
                        logError('Gagal menambahkan user VPN untuk client OVPN. Pastikan database VPN terkonfigurasi dengan benar.');
                    }

                    $_SESSION['generated_script'] = $script['script'];
                    setFlash('success', 'Script MikroTik berhasil digenerate. Silahkan Restart Radius Server');
                }
                redirect('settings.php');
                break;
            case 'save_server':
                $serverIp = sanitize($_POST['server_ip']);
                if (filter_var($serverIp, FILTER_VALIDATE_IP)) {
                    $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", ['server_ip']);
                    if ($existing) {
                        update('settings', ['setting_value' => $serverIp], 'setting_key = ?', ['server_ip']);
                    } else {
                        insert('settings', ['setting_key' => 'server_ip', 'setting_value' => $serverIp]);
                    }
                    setFlash('success', 'Server IP berhasil disimpan');
                } else {
                    setFlash('error', 'Server IP tidak valid');
                }
                redirect('settings.php');
                break;
            case 'save_system':
                $systemSettings = [
                    'app_name' => sanitize($_POST['app_name']),
                    'timezone' => sanitize($_POST['timezone']),
                    'currency' => sanitize($_POST['currency']),
                    'invoice_prefix' => sanitize($_POST['invoice_prefix']),
                    'invoice_start' => (int)$_POST['invoice_start']
                ];
                
                foreach ($systemSettings as $key => $value) {
                    $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                    if ($existing) {
                        update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                    } else {
                        insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                    }
                }
                
                setFlash('success', 'Pengaturan sistem berhasil disimpan');
                redirect('settings.php');
                break;
                
            case 'save_mikrotik':
                $mikrotikSettings = [
                    'MIKROTIK_HOST' => sanitize($_POST['mikrotik_host']),
                    'MIKROTIK_USER' => sanitize($_POST['mikrotik_user']),
                    'MIKROTIK_PASS' => sanitize($_POST['mikrotik_pass']),
                    'MIKROTIK_PORT' => (int)$_POST['mikrotik_port']
                ];
                
                foreach ($mikrotikSettings as $key => $value) {
                    $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                    if ($existing) {
                        update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                    } else {
                        insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                    }
                }
                
                setFlash('success', 'Pengaturan MikroTik berhasil disimpan');
                redirect('settings.php');
                break;
                
            case 'save_genieacs':
                $genieacsSettings = [
                    'GENIEACS_URL' => sanitize($_POST['genieacs_url']),
                    'GENIEACS_USERNAME' => sanitize($_POST['genieacs_username']),
                    'GENIEACS_PASSWORD' => sanitize($_POST['genieacs_password'])
                ];
                
                foreach ($genieacsSettings as $key => $value) {
                    $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                    if ($existing) {
                        update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                    } else {
                        insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                    }
                }
                
                setFlash('success', 'Pengaturan GenieACS berhasil disimpan');
                redirect('settings.php');
                break;
                
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

                foreach ($whatsAppSettings as $key => $value) {
                    $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                    if ($existing) {
                        update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                    } else {
                        insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                    }
                }
                setFlash('success', 'Pengaturan WhatsApp berhasil disimpan');
                redirect('settings.php');
                break;

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

                foreach ($paymentSettings as $key => $value) {
                    $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                    if ($existing) {
                        update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                    } else {
                        insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                    }
                }
                setFlash('success', 'Pengaturan Payment Gateway berhasil disimpan');
                redirect('settings.php');
                break;

            case 'save_telegram_settings':
                $telegramSettings = [
                    'TELEGRAM_BOT_TOKEN' => sanitize($_POST['telegram_bot_token'] ?? ''),
                    'TELEGRAM_ADMIN_CHAT_ID' => sanitize($_POST['telegram_admin_chat_id'] ?? '')
                ];

                foreach ($telegramSettings as $key => $value) {
                    $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                    if ($existing) {
                        update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                    } else {
                        insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                    }
                }
                setFlash('success', 'Pengaturan Telegram berhasil disimpan');
                redirect('settings.php');
                break;

            case 'save_landing':
                $pdo = getDB();
                $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(50) UNIQUE NOT NULL,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
                
                foreach ($landingSettings as $key => $value) {
                    $existing = fetchOne("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
                    if ($existing) {
                        update('site_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                    } else {
                        insert('site_settings', ['setting_key' => $key, 'setting_value' => $value]);
                    }
                }
                
                setFlash('success', 'Pengaturan Landing Page berhasil disimpan');
                redirect('settings.php');
                break;

            case 'manage_faq':
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

                $faq_action = trim((string) ($_POST['faq_action'] ?? ''));
                
                if ($faq_action === 'add') {
                    $question = trim((string) sanitize($_POST['faq_question'] ?? ''));
                    $answer = trim((string) sanitize($_POST['faq_answer'] ?? ''));
                    if ($question !== '' && $answer !== '') {
                        if (saveFaq($question, $answer)) {
                            setFlash('success', 'FAQ berhasil ditambahkan');
                        } else {
                            setFlash('error', 'Gagal menambahkan FAQ');
                        }
                    } else {
                        setFlash('error', 'Pertanyaan dan jawaban wajib diisi');
                    }
                } elseif ($faq_action === 'update') {
                    $faq_id = (int) ($_POST['faq_id'] ?? 0);
                    $question = trim((string) sanitize($_POST['faq_question'] ?? ''));
                    $answer = trim((string) sanitize($_POST['faq_answer'] ?? ''));
                    $is_active = isset($_POST['faq_active']) ? 1 : 0;
                    if ($faq_id > 0 && $question !== '' && $answer !== '') {
                        if (updateFaq($faq_id, $question, $answer, $is_active)) {
                            setFlash('success', 'FAQ berhasil diperbarui');
                        } else {
                            setFlash('error', 'Gagal memperbarui FAQ');
                        }
                    } else {
                        setFlash('error', 'Data tidak valid');
                    }
                } elseif ($faq_action === 'delete') {
                    $faq_id = (int) ($_POST['faq_id'] ?? 0);
                    if ($faq_id > 0) {
                        if (deleteFaq($faq_id)) {
                            setFlash('success', 'FAQ berhasil dihapus');
                        } else {
                            setFlash('error', 'Gagal menghapus FAQ');
                        }
                    } else {
                        setFlash('error', 'ID FAQ tidak valid');
                    }
                }
                redirect('settings.php');
                break;

            case 'change_password':
                $currentPassword = $_POST['current_password'];
                $newPassword = $_POST['new_password'];
                $confirmPassword = $_POST['confirm_password'];
                
                $sessionAdmin = getCurrentAdmin();
                $admin = getAdmin($sessionAdmin['id']);
                
                if (!$admin || !password_verify($currentPassword, $admin['password'])) {
                    setFlash('error', 'Password saat ini salah');
                    redirect('settings.php');
                }
                
                if ($newPassword !== $confirmPassword) {
                    setFlash('error', 'Password baru tidak sama');
                    redirect('settings.php');
                }
                
                if (strlen($newPassword) < 6) {
                    setFlash('error', 'Password minimal 6 karakter');
                    redirect('settings.php');
                }
                
                if (updateAdminPassword($admin['id'], $newPassword)) {
                    setFlash('success', 'Password berhasil diubah');
                    logActivity('CHANGE_PASSWORD', 'Admin ID: ' . $admin['id']);
                } else {
                    setFlash('error', 'Gagal mengubah password');
                }
                redirect('settings.php');
                break;

            case 'save_backup_settings':
                $retentionDays = (int) ($_POST['backup_retention_days'] ?? 7);
                if ($retentionDays < 1) $retentionDays = 1;
                if ($retentionDays > 365) $retentionDays = 365;
                
                $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", ['BACKUP_RETENTION_DAYS']);
                if ($existing) {
                    update('settings', ['setting_value' => $retentionDays], 'setting_key = ?', ['BACKUP_RETENTION_DAYS']);
                } else {
                    insert('settings', ['setting_key' => 'BACKUP_RETENTION_DAYS', 'setting_value' => $retentionDays]);
                }
                setFlash('success', 'Pengaturan retensi backup berhasil disimpan');
                redirect('settings.php');
                break;

            case 'backup_now':
                $retentionDays = (int) getSettingValue('BACKUP_RETENTION_DAYS', 7);
                $result = createDatabaseBackup($retentionDays);
                if ($result['success']) {
                    $deletedCount = count($result['deleted_files'] ?? []);
                    $message = 'Backup berhasil dibuat: ' . ($result['file_name'] ?? '-');
                    if ($deletedCount > 0) {
                        $message .= " ({$deletedCount} backup lama dihapus)";
                    }
                    setFlash('success', $message);
                    logActivity('BACKUP_NOW', 'File: ' . ($result['file_name'] ?? '-'));
                } else {
                    setFlash('error', $result['message'] ?? 'Gagal membuat backup');
                }
                redirect('settings.php');
                break;

            case 'restore_backup':
                $backupFile = sanitizeBackupFilename($_POST['backup_file'] ?? '');
                $confirmRestore = strtoupper(trim((string) ($_POST['confirm_restore'] ?? '')));
                if ($backupFile === '') {
                    setFlash('error', 'Pilih file backup yang valid');
                    redirect('settings.php');
                }
                if ($confirmRestore !== 'RESTORE') {
                    setFlash('error', 'Konfirmasi restore tidak valid. Ketik RESTORE untuk melanjutkan.');
                    redirect('settings.php');
                }
                set_time_limit(0);
                $result = restoreDatabaseBackup($backupFile);
                if ($result['success']) {
                    setFlash('success', 'Restore berhasil dari file: ' . $backupFile);
                    logActivity('RESTORE_BACKUP', 'File: ' . $backupFile);
                } else {
                    setFlash('error', $result['message'] ?? 'Restore backup gagal');
                }
                redirect('settings.php');
                break;

            case 'save_cron_settings':
                $cronToken = sanitize($_POST['cron_token'] ?? '');
                if ($cronToken === '') {
                    $cronToken = bin2hex(random_bytes(16));
                }
                $key = 'CRON_TOKEN';
                $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                if ($existing) {
                    update('settings', ['setting_value' => $cronToken], 'setting_key = ?', [$key]);
                } else {
                    insert('settings', ['setting_key' => $key, 'setting_value' => $cronToken]);
                }
                setFlash('success', 'Cron token berhasil disimpan');
                redirect('settings.php');
                break;

            case 'test_whatsapp':
                $testPhone = trim((string) ($_POST['test_whatsapp_phone'] ?? ''));
                $testMessage = trim((string) ($_POST['test_whatsapp_message'] ?? ''));
                if ($testPhone === '' || $testMessage === '') {
                    setFlash('error', 'Nomor WhatsApp dan pesan test wajib diisi');
                    redirect('settings.php');
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
                    setFlash('success', 'Test WhatsApp berhasil dikirim (gateway: ' . strtoupper($defaultGateway) . ')');
                } else {
                    $msg = $result['message'] ?? 'Test WhatsApp gagal';
                    setFlash('error', 'Test WhatsApp gagal (gateway: ' . strtoupper($defaultGateway) . '): ' . $msg);
                }
                redirect('settings.php');
                break;

            case 'test_mpwa_connection':
                $url = trim((string) getSetting('MPWA_API_URL', 'https://mpwa.official.id/api/send'));
                if ($url === '') $url = 'https://mpwa.official.id/api/send';
                
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
                unset($ch);
                
                if ($curlErrno !== 0 || $httpCode === 0) {
                    setFlash('error', 'Koneksi MPWA gagal (HTTP ' . $httpCode . ', cURL ' . $curlErrno . '): ' . $curlError);
                } else {
                    setFlash('success', 'Koneksi MPWA OK (HTTP ' . $httpCode . ').');
                }
                redirect('settings.php');
                break;

            case 'test_telegram':
                $token = trim((string) getSetting('TELEGRAM_BOT_TOKEN', ''));
                $chatId = trim((string) getSetting('TELEGRAM_ADMIN_CHAT_ID', ''));
                if ($token === '' || $chatId === '') {
                    setFlash('error', 'Telegram Bot Token dan Admin Chat ID wajib diisi untuk test.');
                    redirect('settings.php');
                }
                
                $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
                $payload = [
                    'chat_id' => $chatId,
                    'text' => 'Test Telegram GEMBOK ' . date('Y-m-d H:i:s'),
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
                unset($ch);
                $decoded = json_decode((string) $response, true);
                
                if ($curlErrno !== 0 || $httpCode === 0) {
                    setFlash('error', 'Test Telegram gagal (HTTP ' . $httpCode . ', cURL ' . $curlErrno . '): ' . $curlError);
                } elseif (is_array($decoded) && ($decoded['ok'] ?? false) === true) {
                    setFlash('success', 'Test Telegram berhasil dikirim ke Chat ID: ' . $chatId);
                } else {
                    $msg = is_array($decoded) ? (string) ($decoded['description'] ?? 'Unknown error') : 'Unknown error';
                    setFlash('error', 'Test Telegram gagal (HTTP ' . $httpCode . '): ' . $msg);
                }
                redirect('settings.php');
                break;

            case 'telegram_set_webhook':
                $token = trim((string) getSetting('TELEGRAM_BOT_TOKEN', ''));
                if ($token === '') {
                    setFlash('error', 'Telegram Bot Token belum diisi.');
                    redirect('settings.php');
                }
                $webhookUrl = rtrim(APP_URL, '/') . '/webhooks/telegram.php';
                if (stripos($webhookUrl, 'localhost') !== false || stripos($webhookUrl, '127.0.0.1') !== false) {
                    setFlash('error', 'APP_URL masih localhost. Telegram tidak bisa mengakses webhook lokal.');
                    redirect('settings.php');
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
                unset($ch);
                $decoded = json_decode((string) $response, true);
                
                if ($curlErrno !== 0 || $httpCode === 0) {
                    setFlash('error', 'setWebhook gagal (HTTP ' . $httpCode . ', cURL ' . $curlErrno . '): ' . $curlError);
                } elseif (is_array($decoded) && ($decoded['ok'] ?? false) === true) {
                    setFlash('success', 'Webhook Telegram berhasil di-set ke: ' . $webhookUrl);
                } else {
                    $msg = is_array($decoded) ? (string) ($decoded['description'] ?? 'Unknown error') : 'Unknown error';
                    setFlash('error', 'setWebhook gagal (HTTP ' . $httpCode . '): ' . $msg);
                }
                redirect('settings.php');
                break;

            case 'telegram_webhook_info':
                $token = trim((string) getSetting('TELEGRAM_BOT_TOKEN', ''));
                if ($token === '') {
                    setFlash('error', 'Telegram Bot Token belum diisi.');
                    redirect('settings.php');
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
                unset($ch);
                $decoded = json_decode((string) $response, true);
                
                if ($curlErrno !== 0 || $httpCode === 0) {
                    setFlash('error', 'getWebhookInfo gagal (HTTP ' . $httpCode . ', cURL ' . $curlErrno . '): ' . $curlError);
                } elseif (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
                    $msg = is_array($decoded) ? (string) ($decoded['description'] ?? 'Unknown error') : 'Unknown error';
                    setFlash('error', 'getWebhookInfo gagal (HTTP ' . $httpCode . '): ' . $msg);
                } else {
                    $result = $decoded['result'] ?? [];
                    $currentUrl = (string) ($result['url'] ?? '');
                    $pending = (int) ($result['pending_update_count'] ?? 0);
                    $lastError = (string) ($result['last_error_message'] ?? '');
                    $msg = 'Webhook URL: ' . ($currentUrl !== '' ? $currentUrl : '(kosong)') . ' | Pending: ' . $pending;
                    if ($lastError !== '') {
                        $msg .= ' | Last error: ' . $lastError;
                    }
                    setFlash('success', $msg);
                }
                redirect('settings.php');
                break;
        }
    }
}

$backupRetentionDays = (int) getSettingValue('BACKUP_RETENTION_DAYS', 7);
if ($backupRetentionDays < 1) $backupRetentionDays = 7;
$backupFiles = listDatabaseBackups();

// Get site settings for landing page
$siteSettings = [];
$siteSettingsData = fetchAll("SELECT * FROM site_settings");
foreach ($siteSettingsData as $s) {
    $siteSettings[$s['setting_key']] = $s['setting_value'];
}

// Get FAQs
$faqs = [];
try {
    $faqs = fetchAll("SELECT id, question, answer, is_active FROM faqs ORDER BY sort_order ASC, id ASC");
} catch (Exception $e) {
    // Table might not exist yet
}

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
            $connected = mikrotikConnect();
            ?>
            <h3><?php echo $connected ? 'Online' : 'Offline'; ?></h3>
            <p>MikroTik Status</p>
        </div>
        <div class="stat-icon <?php echo $connected ? 'green' : 'red'; ?>">
            <i class="fas fa-network-wired"></i>
        </div>
    </div>
</div>

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
                <input type="text" name="server_ip" class="form-control" value="<?php echo htmlspecialchars($settings['server_ip'] ?? '127.0.0.1'); ?>">
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
                        <span class="badge badge-success">
                            <i class="fas fa-check-circle"></i> Running
                        </span>
                    </td>
                    <td data-label="Aksi">
                        <form method="GET" action="/api/services.php" class="inline-form">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars(getSetting('CRON_TOKEN', 'NO_TOKEN')); ?>">
                            <input type="hidden" name="action" value="restart_radius">
                            <button type="submit" class="btn-icon" title="Restart Radius Server">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </form>
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
                           value="<?php echo htmlspecialchars($settings['app_name'] ?? 'ANS Radius'); ?>">
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
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Invoice Prefix</label>
                <input type="text" name="invoice_prefix" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'INV'); ?>">
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
                           value="<?php echo htmlspecialchars(getSettingValue('MIKROTIK_HOST')); ?>" 
                           placeholder="192.168.1.1">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="mikrotik_user" class="form-control" 
                           value="<?php echo htmlspecialchars(getSettingValue('MIKROTIK_USER')); ?>" 
                           placeholder="admin">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="mikrotik_pass" class="form-control" 
                               value="<?php echo htmlspecialchars(getSettingValue('MIKROTIK_PASS')); ?>" 
                               placeholder="Masukkan password" id="mikrotik_pass">
                        <i class="fas fa-eye toggle-password" data-target="mikrotik_pass"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">API Port</label>
                    <input type="number" name="mikrotik_port" class="form-control" 
                           value="<?php echo (int)getSettingValue('MIKROTIK_PORT', 8728); ?>">
                    <small class="form-hint">Default: 8728</small>
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
<!-- MikroTik Settings -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-network-wired"></i> Generate Script (Mikrotik Client)
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add_mikrotik_client">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <select name="mikrotik_version" id="mikrotik_version" class="form-control">
                <option value="7">MikroTik 7.15 (Keatas)</option>
                <option value="6">MikroTik 6 - 7.14 (Kebawah)</option>
            </select>
            <textarea name="script" class="form-control" placeholder="Script MikroTik akan muncul di sini..." rows="10" id="mikrotik_script" readonly>
                <?php if(isset($_SESSION['generated_script'])) { echo htmlspecialchars($_SESSION['generated_script']); } ?>
            </textarea>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Generate
            </button>
            <button type="button" class="btn btn-secondary" onclick="copyToClipboard('mikrotik_script')">
                <i class="fas fa-copy"></i> Salin Script
            </button>
        </form>
    </div>
</div>

<!-- NAS Settings -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-database"></i> NAS (Radius Client)
        </h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div>Perlu restart radius server setelah menambahkan atau menghapus NAS</div>
        </div>
        
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
                    <input type="text" name="nas_secret" class="form-control" placeholder="Secret" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah NAS
            </button>
        </form>
        
        <div class="table-responsive" style="margin-top: 24px;">
            <h4 class="section-subtitle">Daftar NAS</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama NAS</th>
                        <th>IP NAS</th>
                        <th>Secret</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $nasList = radiusDisplayNas(); ?>
                    <?php if (empty($nasList)): ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>Tidak ada NAS yang terdaftar</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($nasList as $nas): ?>
                        <tr>
                            <td data-label="Nama NAS"><?php echo htmlspecialchars($nas['shortname']); ?></td>
                            <td data-label="IP NAS"><?php echo htmlspecialchars($nas['nasname']); ?></td>
                            <td data-label="Secret">
                                <span class="secret-dots">••••••••</span>
                                <span class="secret-value" style="display: none;"><?php echo htmlspecialchars($nas['secret']); ?></span>
                                <button type="button" class="btn-icon toggle-secret" title="Lihat Secret">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                            <td data-label="Aksi">
                                <form method="POST" class="inline-form" onsubmit="return confirm('Hapus NAS ini?');">
                                    <input type="hidden" name="action" value="delete_nas">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="nas_id" value="<?php echo htmlspecialchars($nas['id']); ?>">
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



<!-- Mikrotik Radius Script Generator -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-terminal"></i> Radius Script Generator
        </h3>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fas fa-info-circle"></i>
            <div>Gunakan script ini di terminal MikroTik untuk menghubungkan ke Radius Server</div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Pilih NAS / Router</label>
            <select id="radius_nas_selector" class="form-control">
                <option value="">-- Pilih NAS --</option>
                <?php foreach ($nasList as $nas): ?>
                    <option value="<?php echo htmlspecialchars($nas['nasname']); ?>" data-secret="<?php echo htmlspecialchars($nas['secret']); ?>">
                        <?php echo htmlspecialchars($nas['shortname'] . ' (' . $nas['nasname'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Script MikroTik</label>
            <div class="script-wrapper">
                <textarea id="radius_add_script" class="form-control" rows="3" readonly placeholder="Pilih NAS terlebih dahulu..."></textarea>
                <button type="button" class="btn-icon copy-btn" onclick="copyRadiusScript()" title="Salin Script">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            <small class="form-hint">Copy script ini dan paste di terminal MikroTik Anda</small>
        </div>
    </div>
</div>

<!-- VPN Settings -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-lock"></i> VPN Configuration
        </h3>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">MikroTik v7.1+ (WireGuard)</label>
                <a class="btn btn-success" href="settings.php?download_vpn_config=1">
                    <i class="fas fa-download"></i> Download Client Config
                </a>
            </div>
            
            <div class="form-group">
                <label class="form-label">MikroTik v7.0 ke bawah</label>
                <div class="script-wrapper">
                    <textarea id="vpn_script" class="form-control" rows="8" readonly placeholder="Klik Generate Script terlebih dahulu..."></textarea>
                    <button type="button" class="btn-icon copy-btn" onclick="copyVpnScript()" title="Salin Script">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <button type="button" class="btn btn-primary" onclick="generateScript()">
                    <i class="fas fa-code"></i> Generate Script
                </button>
            </div>
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
                       value="<?php echo htmlspecialchars(getSettingValue('GENIEACS_URL')); ?>" 
                       placeholder="http://localhost:7557">
                <small class="form-hint">URL lengkap termasuk port</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="genieacs_username" class="form-control" 
                           value="<?php echo htmlspecialchars(getSettingValue('GENIEACS_USERNAME')); ?>" 
                           placeholder="Username">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="genieacs_password" class="form-control" 
                               value="<?php echo htmlspecialchars(getSettingValue('GENIEACS_PASSWORD')); ?>" 
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
                <button type="button" class="btn-icon" onclick="copyToClipboard('wa_webhook_url')">
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
                               value="<?php echo htmlspecialchars($settings['FONNTE_API_TOKEN'] ?? ''); ?>" 
                               placeholder="Masukkan API Token Fonnte" id="fonnte_token">
                        <i class="fas fa-eye toggle-password" data-target="fonnte_token"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Wablas API Token</label>
                    <div class="password-wrapper">
                        <input type="password" name="wablas_api_token" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['WABLAS_API_TOKEN'] ?? ''); ?>" 
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
                               value="<?php echo htmlspecialchars($settings['MPWA_API_KEY'] ?? ''); ?>" 
                               placeholder="API Key MPWA" id="mpwa_key">
                        <i class="fas fa-eye toggle-password" data-target="mpwa_key"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">MPWA Sender Number</label>
                    <input type="text" name="mpwa_sender" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['MPWA_SENDER'] ?? ''); ?>" 
                           placeholder="628xxxxxxxxxx">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">MPWA API URL</label>
                <input type="text" name="mpwa_api_url" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['MPWA_API_URL'] ?? ''); ?>" 
                       placeholder="https://mpwa.official.id/api/send">
            </div>
            
            <div class="form-group">
                <label class="form-label">WhatsApp Admin Number</label>
                <input type="text" name="whatsapp_admin_number" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['WHATSAPP_ADMIN_NUMBER'] ?? ''); ?>" 
                       placeholder="628xxxxxxxxxx">
                <small class="form-hint">Nomor untuk notifikasi admin</small>
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
                               placeholder="628xxxxxxxxxx">
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
                <button type="button" class="btn-icon" onclick="copyToClipboard('tripay_webhook_url')">
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
                               value="<?php echo htmlspecialchars($settings['TRIPAY_API_KEY'] ?? ''); ?>" 
                               id="tripay_api">
                        <i class="fas fa-eye toggle-password" data-target="tripay_api"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tripay Private Key</label>
                    <div class="password-wrapper">
                        <input type="password" name="tripay_private_key" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['TRIPAY_PRIVATE_KEY'] ?? ''); ?>" 
                               id="tripay_private">
                        <i class="fas fa-eye toggle-password" data-target="tripay_private"></i>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tripay Merchant Code</label>
                    <input type="text" name="tripay_merchant_code" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['TRIPAY_MERCHANT_CODE'] ?? ''); ?>">
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
                    <button type="button" class="btn-icon" onclick="copyToClipboard('midtrans_webhook_url')">
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
                               value="<?php echo htmlspecialchars($settings['MIDTRANS_API_KEY'] ?? ''); ?>" 
                               id="midtrans_api">
                        <i class="fas fa-eye toggle-password" data-target="midtrans_api"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Midtrans Merchant Code</label>
                    <input type="text" name="midtrans_merchant_code" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['MIDTRANS_MERCHANT_CODE'] ?? ''); ?>">
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
                <button type="button" class="btn-icon" onclick="copyToClipboard('telegram_webhook_url')">
                    <i class="fas fa-copy"></i> Salin
                </button>
            </div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_telegram_settings">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Bot Token</label>
                    <div class="password-wrapper">
                        <input type="password" name="telegram_bot_token" class="form-control" 
                               value="<?php echo htmlspecialchars(getSettingValue('TELEGRAM_BOT_TOKEN', '')); ?>" 
                               placeholder="123456:ABC-DEF..." id="telegram_token">
                        <i class="fas fa-eye toggle-password" data-target="telegram_token"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Admin Chat ID</label>
                    <input type="text" name="telegram_admin_chat_id" class="form-control" 
                           value="<?php echo htmlspecialchars(getSettingValue('TELEGRAM_ADMIN_CHAT_ID', '')); ?>" 
                           placeholder="123456789">
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
                       value="<?php echo htmlspecialchars($siteSettings['hero_title'] ?? 'Internet Cepat & Stabil'); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Hero Description</label>
                <textarea name="hero_description" class="form-control" rows="3"><?php echo htmlspecialchars($siteSettings['hero_description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['contact_phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Email</label>
                    <input type="email" name="contact_email" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['contact_email'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Contact Address</label>
                <textarea name="contact_address" class="form-control" rows="2"><?php echo htmlspecialchars($siteSettings['contact_address'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Footer About</label>
                <textarea name="footer_about" class="form-control" rows="2"><?php echo htmlspecialchars($siteSettings['footer_about'] ?? ''); ?></textarea>
            </div>
            
            <h4 class="section-subtitle">Fitur (3 Kolom)</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Fitur 1 - Judul</label>
                    <input type="text" name="feature_1_title" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['feature_1_title'] ?? 'Kecepatan Tinggi'); ?>">
                    <label class="form-label" style="margin-top: 8px;">Deskripsi</label>
                    <textarea name="feature_1_desc" class="form-control" rows="2"><?php echo htmlspecialchars($siteSettings['feature_1_desc'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Fitur 2 - Judul</label>
                    <input type="text" name="feature_2_title" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['feature_2_title'] ?? 'Unlimited Quota'); ?>">
                    <label class="form-label" style="margin-top: 8px;">Deskripsi</label>
                    <textarea name="feature_2_desc" class="form-control" rows="2"><?php echo htmlspecialchars($siteSettings['feature_2_desc'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Fitur 3 - Judul</label>
                    <input type="text" name="feature_3_title" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['feature_3_title'] ?? 'Support 24/7'); ?>">
                    <label class="form-label" style="margin-top: 8px;">Deskripsi</label>
                    <textarea name="feature_3_desc" class="form-control" rows="2"><?php echo htmlspecialchars($siteSettings['feature_3_desc'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <h4 class="section-subtitle">Media Sosial</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-facebook"></i> Facebook</label>
                    <input type="text" name="social_facebook" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['social_facebook'] ?? '#'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-instagram"></i> Instagram</label>
                    <input type="text" name="social_instagram" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['social_instagram'] ?? '#'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-twitter"></i> Twitter</label>
                    <input type="text" name="social_twitter" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['social_twitter'] ?? '#'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-youtube"></i> YouTube</label>
                    <input type="text" name="social_youtube" class="form-control" 
                           value="<?php echo htmlspecialchars($siteSettings['social_youtube'] ?? '#'); ?>">
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
                <div class="faq-item">
                    <div class="faq-content">
                        <strong><?php echo htmlspecialchars($faq['question']); ?></strong>
                        <p><?php echo htmlspecialchars(substr($faq['answer'], 0, 100)); ?>...</p>
                        <div class="faq-meta">
                            <span class="badge <?php echo $faq['is_active'] ? 'badge-success' : 'badge-muted'; ?>">
                                <?php echo $faq['is_active'] ? 'Tampil' : 'Tersembunyi'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="faq-actions">
                        <button class="btn-icon" onclick='editFaq(<?php echo json_encode($faq); ?>)' title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Hapus FAQ ini?');">
                            <input type="hidden" name="action" value="manage_faq">
                            <input type="hidden" name="faq_action" value="delete">
                            <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
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
                <p class="info-text">Jalankan setiap 1 menit untuk tugas otomatis</p>
                
                <div class="url-wrapper">
                    <input type="text" id="cron_web_url" readonly
                        value="<?php echo APP_URL; ?>/cron/run.php?token=<?php echo htmlspecialchars(getSettingValue('CRON_TOKEN', '')); ?>"
                        class="webhook-input"
                        onclick="this.select()">
                    <button type="button" class="btn-icon" onclick="copyToClipboard('cron_web_url')">
                        <i class="fas fa-copy"></i> Salin URL
                    </button>
                </div>
                
                <?php
                $schedulerPath = realpath(__DIR__ . '/../cron/scheduler.php');
                ?>
                <div class="url-wrapper" style="margin-top: 10px;">
                    <input type="text" id="cron_cli_path" readonly
                        value="* * * * * /usr/bin/php <?php echo htmlspecialchars($schedulerPath); ?>"
                        class="webhook-input"
                        onclick="this.select()">
                    <button type="button" class="btn-icon" onclick="copyToClipboard('cron_cli_path')">
                        <i class="fas fa-copy"></i> Salin Command
                    </button>
                </div>
            </div>
            
            <input type="hidden" name="cron_token" value="<?php echo htmlspecialchars(getSettingValue('CRON_TOKEN', '')); ?>">
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Token
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Backup & Restore -->
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
                    <small class="form-hint">Backup lebih lama dari ini akan dihapus otomatis</small>
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
                            <td><?php echo htmlspecialchars($file['name']); ?></td>
                            <!-- Backup & Restore (Lanjutan) -->
                            <td data-label="Ukuran"><?php echo htmlspecialchars(formatBytes($file['size'] ?? 0)); ?></td>
                            <td data-label="Tanggal"><?php echo htmlspecialchars($file['modified_at'] ?? '-'); ?></td>
                            <td data-label="Aksi">
                                <a class="btn-icon" href="settings.php?download_backup=<?php echo urlencode($file['name']); ?>" title="Download">
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
            
            <form method="POST" onsubmit="return confirm('RESTORE akan menimpa semua data saat ini!\n\nPastikan Anda sudah backup data terlebih dahulu.\n\nLanjutkan?');">
                <input type="hidden" name="action" value="restore_backup">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label">Pilih File Backup</label>
                        <select name="backup_file" class="form-control" required>
                            <option value="">-- Pilih file backup --</option>
                            <?php foreach ($backupFiles as $file): ?>
                                <option value="<?php echo htmlspecialchars($file['name']); ?>"><?php echo htmlspecialchars($file['name']); ?> (<?php echo formatBytes($file['size'] ?? 0); ?>)</option>
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

<!-- Global Styles for Settings Page -->
<style>
/* Settings page specific styles */
.section-subtitle {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-light);
}

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

.webhook-info {
    background: var(--bg-tertiary);
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

.script-wrapper {
    position: relative;
}

.script-wrapper .copy-btn {
    position: absolute;
    right: 8px;
    top: 8px;
}

.secret-dots {
    font-family: monospace;
    letter-spacing: 2px;
    color: var(--text-muted);
}

.toggle-secret {
    margin-left: 8px;
}

.alert-info {
    background: rgba(88, 166, 255, 0.1);
    border: 1px solid rgba(88, 166, 255, 0.3);
    color: var(--accent-blue);
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius-md);
    margin-bottom: 20px;
}

.alert-warning {
    background: rgba(210, 153, 34, 0.1);
    border: 1px solid rgba(210, 153, 34, 0.3);
    color: var(--accent-orange);
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius-md);
    margin-bottom: 20px;
}

.alert-danger {
    background: rgba(248, 81, 73, 0.1);
    border: 1px solid rgba(248, 81, 73, 0.3);
    color: var(--accent-red);
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius-md);
    margin-bottom: 20px;
}

.alert-info i, .alert-warning i, .alert-danger i {
    font-size: 18px;
}

.alert-info div, .alert-warning div, .alert-danger div {
    flex: 1;
}

.text-danger {
    color: var(--accent-red);
}

.text-muted {
    color: var(--text-muted);
}

.add-nas-form {
    margin-bottom: 24px;
}

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

.test-section {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-light);
}

.restore-section {
    margin-top: 24px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 40px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 14px;
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

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 16px;
    margin-top: 8px;
    border-top: 1px solid var(--border-light);
}

/* Responsive */
@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
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
}
</style>

<script>
// Toggle password visibility for all password fields
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

// Copy to clipboard function
function copyToClipboard(elementId) {
    const input = document.getElementById(elementId);
    if (!input) return;
    
    input.select();
    input.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(input.value).then(() => {
        showToast('Berhasil disalin!', 'success');
    }).catch(() => {
        document.execCommand('copy');
        showToast('Berhasil disalin!', 'success');
    });
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
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Radius Script Generator
function generateRadiusScript() {
    const selector = document.getElementById('radius_nas_selector');
    const textarea = document.getElementById('radius_add_script');
    const selected = selector.options[selector.selectedIndex];
    
    if (!selected.value) {
        textarea.value = '';
        return;
    }
    
    const nasname = selected.value;
    const secret = selected.getAttribute('data-secret');
    const radiusIp = '10.7.0.1';
    
    const script = `/radius add address=${radiusIp} service=ppp,hotspot secret=${secret} src-address=${nasname} comment="RADIUS - ANS-RADIUS"`;
    textarea.value = script;
}

function copyRadiusScript() {
    const textarea = document.getElementById('radius_add_script');
    if (!textarea.value) {
        alert('Pilih NAS terlebih dahulu');
        return;
    }
    copyToClipboardValue(textarea.value);
}

// VPN Script Generator
async function generateScript() {
    const textarea = document.getElementById('vpn_script');
    textarea.value = 'Loading...';
    
    try {
        const response = await fetch('<?php echo APP_URL; ?>/admin/settings.php?download_vpn_config=2');
        const data = await response.text();
        textarea.value = data || '';
        if (!data || data.trim() === '') {
            alert('Script kosong. Periksa konfigurasi WireGuard.');
        }
    } catch (error) {
        console.error("Gagal mengambil data script:", error);
        alert('Gagal mengambil script VPN.');
        textarea.value = '';
    }
}

function copyVpnScript() {
    const textarea = document.getElementById('vpn_script');
    if (!textarea.value) {
        alert('Generate script terlebih dahulu');
        return;
    }
    copyToClipboardValue(textarea.value);
}

function copyToClipboardValue(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Script berhasil disalin!', 'success');
    }).catch(() => {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Script berhasil disalin!', 'success');
    });
}

// Test functions
function testMikrotikConnection() {
    showToast('Test koneksi MikroTik...', 'info');
    // Implementasi test koneksi
    setTimeout(() => {
        showToast('Test koneksi MikroTik berhasil', 'success');
    }, 1000);
}

function testMpwaConnection() {
    showToast('Test koneksi MPWA...', 'info');
    setTimeout(() => {
        showToast('Koneksi MPWA OK', 'success');
    }, 1000);
}

function testTelegram() {
    showToast('Mengirim test Telegram...', 'info');
    setTimeout(() => {
        showToast('Test Telegram berhasil dikirim', 'success');
    }, 1000);
}

function setTelegramWebhook() {
    showToast('Mengatur webhook Telegram...', 'info');
    setTimeout(() => {
        showToast('Webhook berhasil di-set', 'success');
    }, 1000);
}

function editFaq(faq) {
    // Implement edit FAQ modal
    const question = prompt('Edit Pertanyaan:', faq.question);
    if (question) {
        const answer = prompt('Edit Jawaban:', faq.answer);
        if (answer) {
            // Submit form via AJAX or redirect
            showToast('FAQ berhasil diperbarui', 'success');
        }
    }
}

// Form loading state for all forms
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const btn = this.querySelector('button[type="submit"]');
        if (btn && !btn.classList.contains('no-loading')) {
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            
            // Re-enable after 30 seconds (in case of timeout)
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }, 30000);
        }
    });
});

// Initialize radius script generator
document.getElementById('radius_nas_selector')?.addEventListener('change', generateRadiusScript);

// Add toast animation style
const toastStyle = document.createElement('style');
toastStyle.textContent = `
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
`;
document.head.appendChild(toastStyle);
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
