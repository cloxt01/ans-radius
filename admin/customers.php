<?php
/**
 * Customers Management
 */

require_once '../includes/auth.php';
require_once '../includes/radius.php';
requireAdminLogin();

$pageTitle = 'Pelanggan';
$hasAutoIsolate = ensureCustomersAutoIsolateColumn();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Invalid CSRF token');
        redirect('customers.php');
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $pppoePassword = isset($_POST['pppoe_password']) ? trim((string) $_POST['pppoe_password']) : '';
                $data = [
                    'name' => sanitize($_POST['name']),
                    'phone' => sanitize($_POST['phone']),
                    'pppoe_username' => sanitize($_POST['pppoe_username']),
                    'package_id' => (int)$_POST['package_id'],
                    'router_id' => (int)($_POST['router_id'] ?? 0),
                    'isolation_date' => !empty($_POST['isolation_date']) ? $_POST['isolation_date'] : date('Y-m-d', strtotime('+1 month', strtotime(date('Y-m-20')))),
                    'address' => sanitize($_POST['address']),
                    'lat' => (!isset($_POST['lat']) || trim($_POST['lat']) === '') ? null : (string) str_replace(',', '.', trim($_POST['lat'])),
                    'lng' => (!isset($_POST['lng']) || trim($_POST['lng']) === '') ? null : (string) str_replace(',', '.', trim($_POST['lng'])),
                    'installed_by' => !empty($_POST['installed_by']) ? (int)$_POST['installed_by'] : null,
                    'portal_password' => password_hash('1234', PASSWORD_DEFAULT),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                if ($hasAutoIsolate) {
                    $data['auto_isolate'] = isset($_POST['auto_isolate']) ? 1 : 0;
                }
                
                $customerId = insert('customers', $data);
                if ($customerId) {
                    // Sync RADIUS timeout if username exists in radcheck
                    if (!radiusIsUserExistsByUsername($data['pppoe_username'])) {
                        $profile = getProfileFromPackageId($data['package_id'])['profile_normal'] ?? null;
                        if (!$profile) {
                            logError('Failed to sync RADIUS for new customer - profile not found. Customer ID: ' . $customerId);
                        }
                        # Add ke RADIUS
                        mikrotikAddSecret($data['pppoe_username'], $pppoePassword, $profile);
                    }
                    if (!empty($data['pppoe_username'])) {
                        syncRadiusTimeoutForCustomer($data['pppoe_username'], $customerId);
                    }

                    // Sync to onu_locations if requested
                    $saveOnu = isset($_POST['save_onu']) && $_POST['save_onu'] == '1';
                    $odpId = isset($_POST['odp_id']) && $_POST['odp_id'] !== '' ? (int) $_POST['odp_id'] : null;
                    if ($saveOnu) {
                        try {
                            $serial = $data['pppoe_username']; // Use PPPoE username as identifier if serial not known yet
                            $exists = fetchOne("SELECT id FROM onu_locations WHERE serial_number = ?", [$serial]);
                            $payload = [
                                'name' => $data['name'],
                                'lat' => $data['lat'],
                                'lng' => $data['lng'],
                                'odp_id' => $odpId,
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                            if ($exists) {
                                update('onu_locations', $payload, 'serial_number = ?', [$serial]);
                            } else {
                                $payload['serial_number'] = $serial;
                                $payload['created_at'] = date('Y-m-d H:i:s');
                                insert('onu_locations', $payload);
                            }
                            
                            // Synchronize PPPoE Username to GenieACS if applicable
                            if (!empty($serial)) {
                                genieacsSetParameter($serial, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username', $serial);
                                if ($pppoePassword !== '') {
                                     genieacsSetParameter($serial, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Password', $pppoePassword);
                                }
                            }
                        } catch (Exception $e) {
                            // Do not block customer creation if ONU sync fails
                            logError('ONU sync (add customer) failed: ' . $e->getMessage());
                        }
                    }
                    setFlash('success', 'Pelanggan berhasil ditambahkan');
                    logActivity('ADD_CUSTOMER', "Name: {$data['name']}");
                    
                    // Notify Technician if assigned
                    if (!empty($data['installed_by'])) {
                        $tech = fetchOne("SELECT phone, name FROM technician_users WHERE id = ?", [$data['installed_by']]);
                        if ($tech && !empty($tech['phone'])) {
                            require_once '../includes/whatsapp.php';
                            $msg = "🔔 *TUGAS INSTALASI BARU*\n\n";
                            $msg .= "Pelanggan: {$data['name']}\n";
                            $msg .= "Alamat: " . ($data['address'] ?: '-') . "\n";
                            $msg .= "Paket: " . fetchOne("SELECT name FROM packages WHERE id = ?", [$data['package_id']])['name'] . "\n";
                            $msg .= "Maps: https://www.google.com/maps?q={$data['lat']},{$data['lng']}\n\n";
                            $msg .= "Mohon segera diproses. Terima kasih.";
                            
                            sendWhatsAppMessage($tech['phone'], $msg);
                        }
                    }
                } else {
                    setFlash('error', 'Gagal menambahkan pelanggan');
                }
                redirect('customers.php');
                break;
                
            case 'edit':
                $customerId = (int)$_POST['customer_id'];
                if(!fetchOne("SELECT id FROM customers WHERE id = ?", [$customerId])) {
                    setFlash('error', 'Pelanggan tidak ditemukan');
                    redirect('customers.php');
                }
            
                // Ambil username lama dari DB lokal sebelum di-update
                $customer_username = getPppoeUsernameByCustomerId($customerId);
                
                // Ambil & bersihkan data input baru dari form
                $new_username = sanitize($_POST['pppoe_username']);
                $new_password = isset($_POST['pppoe_password']) ? trim((string)$_POST['pppoe_password']) : '';
            
                // 1. Sinkronisasi Perubahan Username di RADIUS
                if($customer_username !== $new_username) {
                    radiusRenameUser($customer_username, $new_username);
                }
                
                // 2. Sinkronisasi Perubahan Password di RADIUS (Hanya jika password baru DIISI)
                if ($new_password !== '') {
                    // Ambil password lama di RADIUS (menggunakan username baru jika baru saja di-rename)
                    $old_radius_password = trim((string)radiusGetUserPassword($new_username));
                    
                    if($old_radius_password !== $new_password) {
                        radiusUpdateUserPassword($new_username, $new_password);
                    }
                }
            
                // Buat data untuk update ke DB lokal
                $data = [
                    'pppoe_username' => $new_username,
                    'name' => sanitize($_POST['name']),
                    'phone' => sanitize($_POST['phone']),
                    'package_id' => (int)$_POST['package_id'],
                    'router_id' => (int)($_POST['router_id'] ?? 0),
                    'isolation_date' => !empty($_POST['isolation_date']) ? $_POST['isolation_date'] : date('Y-m-d', strtotime('+1 month')),
                    'address' => sanitize($_POST['address']),
                    'lat' => (!isset($_POST['lat']) || trim($_POST['lat']) === '') ? null : (string) str_replace(',', '.', trim($_POST['lat'])),
                    'lng' => (!isset($_POST['lng']) || trim($_POST['lng']) === '') ? null : (string) str_replace(',', '.', trim($_POST['lng'])),
                    'installed_by' => !empty($_POST['installed_by']) ? (int)$_POST['installed_by'] : null,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if ($hasAutoIsolate) {
                    $data['auto_isolate'] = isset($_POST['auto_isolate']) ? 1 : 0;
                }
                
                if (update('customers', $data, 'id = ?', [$customerId])) {
                    // Get updated customer data for RADIUS sync
                    $customer = fetchOne("SELECT pppoe_username FROM customers WHERE id = ?", [$customerId]);
                    if ($customer && !empty($customer['pppoe_username'])) {
                        syncRadiusTimeoutForCustomer($customer['pppoe_username'], $customerId);
                    }
            
                    // Sync to onu_locations if requested
                    $saveOnu = isset($_POST['save_onu']) && $_POST['save_onu'] == '1';
                    $odpId = isset($_POST['odp_id']) && $_POST['odp_id'] !== '' ? (int) $_POST['odp_id'] : null;
                    if ($saveOnu) {
                        try {
                            if (!$customer) {
                                $customer = fetchOne("SELECT pppoe_username FROM customers WHERE id = ?", [$customerId]);
                            }
                            if ($customer && !empty($customer['pppoe_username'])) {
                                $serial = $customer['pppoe_username'];
                                $exists = fetchOne("SELECT id FROM onu_locations WHERE serial_number = ?", [$serial]);
                                $payload = [
                                    'name' => $data['name'],
                                    'lat' => $data['lat'],
                                    'lng' => $data['lng'],
                                    'odp_id' => $odpId,
                                    'updated_at' => date('Y-m-d H:i:s')
                                ];
                                if ($exists) {
                                    update('onu_locations', $payload, 'serial_number = ?', [$serial]);
                                } else {
                                    $payload['serial_number'] = $serial;
                                    $payload['created_at'] = date('Y-m-d H:i:s');
                                    insert('onu_locations', $payload);
                                }
            
                                // Synchronize PPPoE Username to GenieACS
                                genieacsSetParameter($serial, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username', $serial);
                                
                                // JIKA password diganti, kirim juga password barunya ke GenieACS
                                if ($new_password !== '') {
                                    genieacsSetParameter($serial, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Password', $new_password);
                                }
                            }
                        } catch (Exception $e) {
                            logError('ONU sync (edit customer) failed: ' . $e->getMessage());
                        }
                    }
                    setFlash('success', 'Pelanggan berhasil diperbarui');
                    logActivity('UPDATE_CUSTOMER', "ID: {$customerId}");
                } else {
                    setFlash('error', 'Gagal memperbarui pelanggan');
                }
                redirect('customers.php');
                break;
                
            case 'delete':
                $customerId = (int)$_POST['customer_id'];
                $customer_username = getPppoeUsernameByCustomerId($customerId);
                if(radiusIsUserExistsByUsername($customer_username)) {
                    radiusDeleteUser($customer_username);
                    mikrotikRemoveActiveSessionByName($customer_username);
                }
                if (delete('customers', 'id = ?', [$customerId])) {
                    setFlash('success', 'Pelanggan berhasil dihapus');
                    logActivity('DELETE_CUSTOMER', "ID: {$customerId}");
                } else {
                    setFlash('error', 'Gagal menghapus pelanggan');
                }
                redirect('customers.php');
                break;
                
            case 'unisolate':
                $customerId = (int)$_POST['customer_id'];
                if (unisolateCustomer($customerId)) {
                    setFlash('success', 'Pelanggan berhasil di-unisolate');
                } else {
                    setFlash('error', 'Gagal meng-unisolate pelanggan');
                }
                redirect('customers.php');
                break;
            case 'isolate':
                $customerId = (int)$_POST['customer_id'];
                if (isolateCustomer($customerId)) {
                    setFlash('success', 'Pelanggan berhasil diisolir');
                } else {
                    setFlash('error', 'Gagal mengisolir pelanggan');
                }
                redirect('customers.php');
                break;

            case 'reset_portal_password':
                $customerId = (int)($_POST['customer_id'] ?? 0);
                $customer = fetchOne("SELECT id, name, phone FROM customers WHERE id = ?", [$customerId]);
                if (!$customer) {
                    setFlash('error', 'Pelanggan tidak ditemukan');
                    redirect('customers.php');
                }

                $tempPassword = '1234';
                if (setCustomerPortalPassword($customerId, $tempPassword)) {
                    $waStatus = '';
                    $phone = (string) ($customer['phone'] ?? '');
                    if (trim($phone) !== '') {
                        $msg = "Halo {$customer['name']},\n\n";
                        $msg .= "Password login Portal Pelanggan telah direset.\n";
                        $msg .= "Password sementara: {$tempPassword}\n\n";
                        $msg .= "Silakan login, lalu segera ganti password Anda.";
                        if (function_exists('getWhatsAppFooter')) {
                            $msg .= getWhatsAppFooter();
                        }
                        $waSent = sendWhatsApp($phone, $msg);
                        $waStatus = $waSent ? ' Notifikasi WhatsApp terkirim.' : ' Notifikasi WhatsApp gagal terkirim (cek pengaturan gateway).';
                    }

                    setFlash('success', 'Password portal pelanggan berhasil direset.' . $waStatus);
                    logActivity('RESET_CUSTOMER_PORTAL_PASSWORD', "ID: {$customerId}");
                } else {
                    setFlash('error', 'Gagal reset password portal pelanggan');
                }
                redirect('customers.php');
                break;
        }
    }
}

// Get data with pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = min(500, max(10, (int)($_GET['per_page'] ?? 10)));
$offset = ($page - 1) * $perPage;

$customersTableExists = tableExists('customers');
$packagesTableExists = tableExists('packages');
$routersTableExists = tableExists('routers');

// Get technicians
$technicians = fetchAll("SELECT * FROM technician_users WHERE status = 'active' ORDER BY name ASC");

if ($customersTableExists) {
    // Read filter parameters (used for initial server-side listing when provided)
    $search = trim((string)($_GET['search'] ?? ''));
    $filter_status = trim((string)($_GET['filter_status'] ?? ''));
    $filter_package = isset($_GET['filter_package']) && $_GET['filter_package'] !== '' ? (int)$_GET['filter_package'] : null;
    $filter_router = isset($_GET['filter_router']) && $_GET['filter_router'] !== '' ? (int)$_GET['filter_router'] : null;
    $filter_tech = isset($_GET['filter_tech']) && $_GET['filter_tech'] !== '' ? (int)$_GET['filter_tech'] : null;
    // New range filters
    $filter_last_paid_from = trim((string)($_GET['filter_last_paid_from'] ?? ''));
    $filter_last_paid_to = trim((string)($_GET['filter_last_paid_to'] ?? ''));
    $filter_isolation_from = trim((string)($_GET['filter_isolation_from'] ?? ''));
    $filter_isolation_to = trim((string)($_GET['filter_isolation_to'] ?? ''));
    $filter_register_from = trim((string)($_GET['filter_register_from'] ?? ''));
    $filter_register_to = trim((string)($_GET['filter_register_to'] ?? ''));

    $whereClauses = ['1=1'];
    $whereParams = [];

    if ($filter_status !== '') {
        $whereClauses[] = 'c.status = ?';
        $whereParams[] = $filter_status;
    }
    if ($filter_package) {
        $whereClauses[] = 'c.package_id = ?';
        $whereParams[] = $filter_package;
    }
    if ($filter_router) {
        $whereClauses[] = 'c.router_id = ?';
        $whereParams[] = $filter_router;
    }
    if ($filter_tech) {
        $whereClauses[] = 'c.installed_by = ?';
        $whereParams[] = $filter_tech;
    }

    // Last paid range (dates expected in YYYY-MM-DD)
    if ($filter_last_paid_from !== '') {
        $whereClauses[] = "(SELECT MAX(i.due_date) FROM invoices i WHERE i.customer_id = c.id AND i.status = 'paid') >= ?";
        $whereParams[] = $filter_last_paid_from . ' 00:00:00';
    }
    if ($filter_last_paid_to !== '') {
        $whereClauses[] = "(SELECT MAX(i.due_date) FROM invoices i WHERE i.customer_id = c.id AND i.status = 'paid') <= ?";
        $whereParams[] = $filter_last_paid_to . ' 23:59:59';
    }

    // Isolation date (day of month) range
    if ($filter_isolation_from !== '') { $whereClauses[] = 'c.isolation_date >= ?'; $whereParams[] = $filter_isolation_from; }
	if ($filter_isolation_to !== '') { $whereClauses[] = 'c.isolation_date <= ?'; $whereParams[] = $filter_isolation_to; }

    // Register date range
    if ($filter_register_from !== '') {
        $whereClauses[] = 'c.created_at >= ?';
        $whereParams[] = $filter_register_from . ' 00:00:00';
    }
    if ($filter_register_to !== '') {
        $whereClauses[] = 'c.created_at <= ?';
        $whereParams[] = $filter_register_to . ' 23:59:59';
    }

    // Only apply text search when at least 2 characters provided
    if ($search !== '' && mb_strlen($search) >= 2) {
        $whereClauses[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.pppoe_username LIKE ?)';
        $like = '%' . $search . '%';
        $whereParams[] = $like;
        $whereParams[] = $like;
        $whereParams[] = $like;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

    $totalCustomers = fetchOne("SELECT COUNT(*) as total FROM customers c $whereSql", $whereParams)['total'] ?? 0;
    $totalPages = ceil($totalCustomers / $perPage);

    // Build query with proper JOINs to avoid N+1 queries
    $selectParts = [
        'c.id', 'c.name', 'c.phone', 'c.pppoe_username', 'c.package_id', 'c.router_id',
        'c.isolation_date', 'c.address', 'c.lat', 'c.lng', 'c.status', 'c.created_at', 'c.auto_isolate', 'c.installed_by', 'c.ip_address', 'c.mac_address',
        $packagesTableExists ? 'p.name as package_name' : "'Tanpa Paket' as package_name",
        $packagesTableExists ? 'p.price as package_price' : "'0' as package_price",
        $routersTableExists ? 'r.name as router_name' : "'' as router_name",
        'COALESCE(onu.odp_id, NULL) as onu_odp_id',
        'IF(rc.username IS NOT NULL, TRUE, FALSE) as in_radius'
        , '(SELECT MAX(i.due_date) FROM invoices i WHERE i.customer_id = c.id AND i.status = \'paid\') as last_paid'
    ];

    $joinParts = [];
    if ($packagesTableExists) {
        $joinParts[] = 'LEFT JOIN packages p ON c.package_id = p.id';
    }
    if ($routersTableExists) {
        $joinParts[] = 'LEFT JOIN routers r ON c.router_id = r.id';
    }
    
    // LEFT JOIN untuk ONU locations
    $joinParts[] = 'LEFT JOIN onu_locations onu ON onu.serial_number = c.pppoe_username';
    
    // LEFT JOIN untuk RADIUS check (jika RADIUS ready)
    if (function_exists('radiusUserProvisioningReady') && radiusUserProvisioningReady()) {
        try {
            $radiusDb = defined('RADIUS_DB_NAME') ? '`' . trim((string) RADIUS_DB_NAME) . '`' : '';
            if ($radiusDb) {
                $joinParts[] = "LEFT JOIN {$radiusDb}.radcheck rc ON rc.username = c.pppoe_username AND rc.attribute IN ('Cleartext-Password', 'User-Password')";
            }
        } catch (Exception $e) {
            // Silent fail, continue without RADIUS JOIN
        }
    }

    $customers = fetchAll("
        SELECT " . implode(', ', $selectParts) . "
        FROM customers c 
        " . implode("\n        ", $joinParts) . "
        $whereSql
        GROUP BY c.id
        ORDER BY COALESCE(c.updated_at) DESC, c.id DESC
        LIMIT $perPage OFFSET $offset
    ", $whereParams);
    
} else {
    $totalCustomers = 0;
    $totalPages = 0;
    $customers = [];
}

$packages = $packagesTableExists ? fetchAll("SELECT * FROM packages ORDER BY name") : [];
$routers = $routersTableExists ? getAllRouters() : [];
$csrfToken = generateCsrfToken();
$randomCustomer = getRandomCustomer();
$paginationQuery = $_GET;
unset($paginationQuery['page']);
$paginationQueryString = http_build_query($paginationQuery);
$defaultIsolationDate = date('Y-m-d', strtotime('+1 month', strtotime(date('Y-m-20'))));

if ($paginationQueryString !== '') {
    $paginationQueryString = '&' . $paginationQueryString;
}

ob_start();
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo (int) $totalCustomers; ?></h3>
            <p>Total Pelanggan</p>
        </div>
    </div>
    
    <?php
    $activeCount = fetchOne("SELECT COUNT(*) as total FROM customers WHERE status = 'active'")['total'] ?? 0;
    $isolatedCount = fetchOne("SELECT COUNT(*) as total FROM customers WHERE status = 'isolated'")['total'] ?? 0;
    
    $currentMonth = date('m');
    $currentYear = date('Y');
    $unpaidCount = fetchOne("
        SELECT COUNT(*) as total 
        FROM customers c 
        WHERE c.status = 'active' 
        AND NOT EXISTS (
            SELECT 1 FROM invoices i 
            WHERE i.customer_id = c.id 
            AND MONTH(i.due_date) = ? 
            AND YEAR(i.due_date) = ? 
            AND i.status = 'paid'
        )
    ", [$currentMonth, $currentYear])['total'] ?? 0;
    ?>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $activeCount; ?></h3>
            <p>Aktif</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-ban"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $isolatedCount; ?></h3>
            <p>Terisolir</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $unpaidCount; ?></h3>
            <p>Belum Lunas</p>
        </div>
    </div>
</div>

<style>
    /* Tour Styles */
    .badge-danger {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }


    .tour-highlight {
        position: relative !important;
        z-index: 9999 !important;
    }

    .tour-highlight::before {
        content: '' !important;
        position: absolute !important;
        top: -5px !important;
        left: -5px !important;
        right: -5px !important;
        bottom: -5px !important;
        border: 4px solid #00d4ff !important;
        border-radius: 12px !important;
        box-shadow: 0 0 20px #00d4ff !important;
        pointer-events: none !important;
        animation: tourPulse 0.5s ease-in-out infinite alternate !important;
        z-index: 10000 !important;
    }

    @keyframes tourPulse {
        from { box-shadow: 0 0 0 4000px rgba(0,0,0,0.6), 0 0 10px #00d4ff; }
        to   { box-shadow: 0 0 0 4000px rgba(0,0,0,0.6), 0 0 25px #00d4ff, 0 0 8px #00d4ff inset; }
    }
    .tour-tooltip {
        position: absolute;
        background: var(--bg-card);
        border: 1px solid var(--accent-cyan);
        border-radius: 12px;
        padding: 16px 20px;
        max-width: 340px;
        z-index: 10000;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        animation: tourFadeIn 0.3s ease;
    }

    @keyframes tourFadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .tour-tooltip h4 {
        margin: 0 0 8px 0;
        color: var(--accent-cyan);
        font-size: 1rem;
    }

    .tour-tooltip p {
        margin: 0 0 12px 0;
        color: var(--text-secondary);
        font-size: 0.85rem;
        line-height: 1.4;
    }

    .tour-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .tour-next {
        background: var(--gradient-primary);
        padding: 6px 14px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .tour-next {
        background: var(--gradient-primary);
        color: #ffffff;
        border: none;
    }

    .tour-next:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 212, 255, 0.4);
    }

    .tour-prev {
        border-radius: 6px;
        padding: 6px 14px;
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }

    .tour-prev:hover {
        border-color: var(--accent-cyan);
        color: var(--accent-cyan);
    }

    .tour-buttons .tour-close {
        background: transparent;
        color: var(--text-muted);
        border: none;
    }

    .tour-buttons .tour-close:hover {
        color: var(--text-primary);
    }

    .tour-tooltip::before {
        content: '';
        position: absolute;
        width: 12px;
        height: 12px;
        background: var(--bg-card);
        border-left: 1px solid var(--accent-cyan);
        border-top: 1px solid var(--accent-cyan);
        transform: rotate(45deg);
    }

    /* placement=bottom → arrow di atas tooltip, geser horizontal */
    .tour-tooltip[data-placement="bottom"]::before {
        top: -7px;
        left: var(--arrow-x, 20px);
        border-right: none;
        border-bottom: none;
    }

    /* placement=top → arrow di bawah tooltip, geser horizontal */
    .tour-tooltip[data-placement="top"]::before {
        bottom: -7px;
        left: var(--arrow-x, 20px);
        transform: rotate(225deg);
        border-right: none;
        border-bottom: none;
    }

    /* placement=right → arrow di kiri tooltip, geser vertikal */
    .tour-tooltip[data-placement="right"]::before {
        left: -7px;
        top: var(--arrow-y, 20px);
        transform: rotate(-45deg);
        border-right: none;
        border-bottom: none;
    }

    /* placement=left → arrow di kanan tooltip, geser vertikal */
    .tour-tooltip[data-placement="left"]::before {
        right: -7px;
        top: var(--arrow-y, 20px);
        transform: rotate(135deg);
        border-right: none;
        border-bottom: none;
    }

    /* Tombol Tour di header */
    .tour-btn {
        background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .tour-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    }
    /* Make stats grid responsive for 4 cards */
    .stats-grid {
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 15px;
    }

        .actions-row {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    .action-btn {
        background: var(--bg-secondary);
        border: 0.5px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 10px 18px;
        color: var(--text-primary);
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        font-family: var(--font-sans);
    }
    .action-btn:hover {
        background: var(--bg-card);
        border-color: var(--accent-blue);
        transform: translateY(-1px);
    }
    .action-btn i { color: var(--text-secondary); }


    .form-section {
        background: rgba(255, 255, 255, 0.02);
        padding: 18px;
        border-radius: 10px;
        margin-bottom: 18px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .form-section h4 {
        margin: 0 0 14px 0;
        color: var(--neon-cyan);
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .form-group-full {
        grid-column: 1 / -1;
    }

    .map-container {
        height: 360px;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.02);
    }

    .btn-submit {
        margin-top: 8px;
        width: 100%;
        background: linear-gradient(135deg, var(--accent-green), #1f9d55);
        color: #fff;
        border: none;
        padding: 12px 16px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-submit:hover {
        filter: brightness(1.04);
    }

    .customer-action-group {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
    }

    .customer-action-group .btn,
    .customer-action-group form {
        flex: 0 0 auto;
    }

    .customer-action-group .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .customer-action-group .btn i {
        font-size: 0.85rem;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
        .stat-card {
            padding: 15px;
            
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
        }
        .stat-info h3 {
            font-size: 1.5rem;
        }
        .stat-info p {
            font-size: 0.8rem;
        }
    }
</style>

<div class="alert alert-warning">
    <i class="fas fa-warning"></i>
    <strong>PENTING !!!</strong> Pastikan untuk membuat invoice & perpanjang setelah menambahkan pelanggan agar tagihan muncul di portal pelanggan dan pelanggan tidak langsung terisolir di hari berikutnya.
</div>
<?php if (empty($randomCustomer)): ?>
    <div class="alert alert-warning" style="margin-top:8px;">
        <i class="fas fa-exclamation-triangle"></i> Tidak ada username PPPoE cadangan yang memenuhi kriteria (%ans%, status isolir, tanpa invoice). Tombol "Tambah Pelanggan (via Rename)" dinonaktifkan.
    </div>
<?php endif; ?>

<div class="actions-row">
    <button type="button" class="action-btn" onclick="openAddCustomerModal()">
        <i class="fas fa-user-plus"></i> Tambah Pelanggan
    </button>
    <?php if ($randomCustomer): ?>
        <button type="button" class="action-btn" onclick="openRenameCustomerModal(<?php echo htmlspecialchars(json_encode($randomCustomer), ENT_QUOTES, 'UTF-8'); ?>)">
            <i class="fas fa-retweet"></i> Tambah Pelanggan via Rename
        </button>
    <?php else: ?>
        <button type="button" class="action-btn" style="opacity: 0.7; cursor: not-allowed;" title="Tidak ada username cadangan untuk rename" disabled>
            <i class="fas fa-retweet"></i> Tambah Pelanggan via Rename
        </button>
    <?php endif; ?>
    <a href="export.php" class="action-btn">
        <i class="fas fa-file-excel"></i> Export/Import
    </a>
</div>

<!-- Add Customer Modal -->
<div id="addCustomerModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div class="card" style="width: 920px; max-width: 94%; margin: 2rem; max-height: 92vh; overflow-y: auto; padding: 0;">
        <div style="padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: var(--bg-card); z-index: 10;">
            <h3 style="margin: 0; color: var(--neon-cyan); font-size: 1.2rem;">
                <i class="fas fa-user-plus"></i> Tambah Pelanggan
            </h3>
            <button type="button" onclick="closeAddCustomerModal()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.5rem; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>
        <div style="padding: 25px;">
        <form method="POST" id="addCustomerForm" data-no-loading="true">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="form-section">
                <h4><i class="fas fa-user"></i> Informasi Dasar</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nama Pelanggan</label>
                        <input type="text" name="name" class="form-control" required placeholder="Nama Lengkap">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nomor HP (WhatsApp)</label>
                        <input type="text" name="phone" class="form-control" required placeholder="08xxxxxxxxxx">
                    </div>

                    <div class="form-group-full">
                        <label class="form-label">Username PPPoE</label>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <input type="text" name="pppoe_username" id="pppoe_username_input" class="form-control" required placeholder="Pilih atau ketik username" style="flex: 1 1 200px; min-width: 0;">
                            <button type="button" class="btn btn-secondary" onclick="openPppoeUserModal()" style="flex: 0 0 auto; white-space: nowrap;">Pilih dari Daftar</button>
                        </div>
                        <small style="color: var(--text-muted);">Pilih username PPPoE dari user MikroTik untuk menghindari salah input</small>
                    </div>

                    <div class="form-group-full">
                        <label class="form-label">Password PPPoE (Opsional)</label>
                        <input type="text" name="pppoe_password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password PPPoE">
                        <small style="color: var(--text-muted);">Jika diisi, password ini akan dikirim ke perangkat (GenieACS). Aplikasi tidak bisa membaca password dari MikroTik.</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4><i class="fas fa-cogs"></i> Konfigurasi Layanan</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Paket Langganan</label>
                        <select name="package_id" class="form-control" required style="color: var(--text-primary); background: var(--bg-card);">
                            <option value="">Pilih Paket</option>
                            <?php foreach ($packages as $pkg): ?>
                                <option value="<?php echo $pkg['id']; ?>">
                                    <?php echo htmlspecialchars($pkg['name']); ?> (<?php echo formatCurrency($pkg['price']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Router / MikroTik</label>
                        <select name="router_id" class="form-control" required style="color: var(--text-primary); background: var(--bg-card);">
                            <option value="0">Default Router</option>
                            <?php foreach ($routers as $r): ?>
                                <option value="<?php echo $r['id']; ?>">
                                    <?php echo htmlspecialchars($r['name']); ?> (<?php echo htmlspecialchars($r['host']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Teknisi Instalasi (Opsional)</label>
                        <select name="installed_by" class="form-control" style="color: var(--text-primary); background: var(--bg-card);">
                            <option value="">-- Pilih Teknisi --</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tanggal Isolir</label>
                        <input type="date" name="isolation_date" class="form-control" value="<?php echo $defaultIsolationDate; ?>" required>
                    </div>

                    <div class="form-group-full">
                        <label class="form-label" style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="auto_isolate" value="1" checked>
                            <span>Isolir Otomatis</span>
                        </label>
                        <small style="color: var(--text-muted);">Jika dimatikan, pelanggan ini akan diabaikan oleh isolir otomatis saat tagihan belum dibayar.</small>
                    </div>

                    <div class="form-group-full">
                        <label class="form-label">Alamat</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Alamat rumah"></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4><i class="fas fa-map-marker-alt"></i> Lokasi</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Latitude</label>
                        <input type="text" name="lat" id="add_lat" class="form-control" readonly placeholder="Klik pada peta">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude</label>
                        <input type="text" name="lng" id="add_lng" class="form-control" readonly placeholder="Klik pada peta">
                    </div>
                </div>
                <div id="addMap" class="map-container"></div>
                <small style="color: var(--text-muted); display: block; margin-top: 8px;">Klik pada peta untuk menentukan lokasi</small>
            </div>

            <div class="form-section">
                <h4><i class="fas fa-link"></i> Mapping ONU</h4>
                <div class="form-grid">
                    <div class="form-group-full">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="save_onu" value="1" checked>
                            <span>Sekaligus simpan titik ke ONU Locations</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ODP (Opsional)</label>
                        <select name="odp_id" id="add_odp_select" class="form-control">
                            <option value="">-- Pilih ODP --</option>
                        </select>
                        <small style="color: var(--text-muted);">Jika belum ada, tambah ODP di menu GenieACS Peta</small>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i> Simpan Pelanggan
            </button>
        </form>
        </div>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px;"><i class="fas fa-users"></i> Daftar Pelanggan</h3>
        <div style="display: flex; gap: 8px; align-items: center;">
            <select id="perPageSelect" class="form-control" style="width: 110px;">
                <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10 / page</option>
                <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50 / page</option>
                <option value="100" <?php echo $perPage === 100 ? 'selected' : ''; ?>>100 / page</option>
                <option value="250" <?php echo $perPage === 250 ? 'selected' : ''; ?>>250 / page</option>
                <option value="500" <?php echo $perPage === 500 ? 'selected' : ''; ?>>500 / page</option>
            </select>
            <input type="text" id="searchCustomer" class="form-control" placeholder="Cari pelanggan..." style="width: 220px;" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            <button type="button" class="tour-btn" onclick="startTour()" style="margin-left: 8px;">
                <i class="fas fa-question-circle"></i> Panduan
            </button>
        </div>
    </div>
    <div id="filterContainer" class="card-body" style="padding: 12px 16px; border-top: 1px solid rgba(255,255,255,0.04);">
        <div style="display: flex; flex-direction: column; gap: 8px; align-items: center;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: center;">
                <select id="filterStatus" class="form-control" style="width: 140px;">
                <option value="">Semua Status</option>
                <option value="active" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'active') ? 'selected' : ''; ?>>Aktif</option>
                <option value="isolated" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'isolated') ? 'selected' : ''; ?>>Isolir</option>
            </select>
            <select id="filterPackage" class="form-control" style="width: 180px;">
                <option value="">Semua Paket</option>
                <?php foreach ($packages as $pkg): ?>
                    <option value="<?php echo $pkg['id']; ?>" <?php echo (isset($_GET['filter_package']) && (int)$_GET['filter_package'] === (int)$pkg['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pkg['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterRouter" class="form-control" style="width: 180px;">
                <option value="">Semua Router</option>
                <?php foreach ($routers as $r): ?>
                    <option value="<?php echo $r['id']; ?>" <?php echo (isset($_GET['filter_router']) && (int)$_GET['filter_router'] === (int)$r['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterTech" class="form-control" style="width: 180px;">
                <option value="">Semua Teknisi</option>
                <?php foreach ($technicians as $tech): ?>
                    <option value="<?php echo $tech['id']; ?>" <?php echo (isset($_GET['filter_tech']) && (int)$_GET['filter_tech'] === (int)$tech['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tech['name']); ?></option>
                <?php endforeach; ?>
            </select>
            </div>

            <!-- Additional range filters -->
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: center; margin-top: 8px;">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Last Paid From</label>
                    <input type="date" id="filterLastPaidFrom" class="form-control" value="<?php echo htmlspecialchars($_GET['filter_last_paid_from'] ?? ''); ?>">
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Last Paid To</label>
                    <input type="date" id="filterLastPaidTo" class="form-control" value="<?php echo htmlspecialchars($_GET['filter_last_paid_to'] ?? ''); ?>">
                </div>

                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Isolir Dari (Tanggal)</label>
                    <input type="date" id="filterIsolationFrom" class="form-control" style="width: 160px;" value="<?php echo htmlspecialchars($_GET['filter_isolation_from'] ?? ''); ?>">
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Isolir Sampai (Tanggal)</label>
                    <input type="date" id="filterIsolationTo" class="form-control" style="width: 160px;" value="<?php echo htmlspecialchars($_GET['filter_isolation_to'] ?? ''); ?>">
                </div>

                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Register From</label>
                    <input type="date" id="filterRegisterFrom" class="form-control" value="<?php echo htmlspecialchars($_GET['filter_register_from'] ?? ''); ?>">
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Register To</label>
                    <input type="date" id="filterRegisterTo" class="form-control" value="<?php echo htmlspecialchars($_GET['filter_register_to'] ?? ''); ?>">
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: center; margin-top: 8px;">
                <button id="applyFilterBtn" class="btn btn-primary" style="padding: 10px 18px; font-size: 1rem; min-width: 110px; border-radius: 8px;">Filter</button>
                <button id="resetFilterBtn" class="btn btn-secondary" style="padding: 10px 18px; font-size: 1rem; min-width: 110px; border-radius: 8px;">Reset</button>
            </div>
        </div>
    </div>
    
    <div style="border-top: 2px solid rgba(255,255,255,0.04); margin-top: 10px;"></div>

    <table class="data-table" id="customerTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama & Kontak</th>
                <th>Paket & Router</th>
                <th>Last Paid</th>
                <th>Status</th>
                <th>PPPoE</th>
                <th>Tgl Isolir</th>
                <th>Register Date</th>
                <th>IP Address</th>
                <th>MAC Address</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 30px;" data-label="Data">
                        Belum ada data pelanggan
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td data-label="ID">#<?php echo $c['id']; ?></td>
                    <td data-label="Nama & Kontak">
                        <strong><?php echo htmlspecialchars($c['name']); ?></strong><br>
                        <small><i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($c['phone'] ?? 'N/A'); ?></small>
                    </td>
                    <td data-label="Paket & Router">
                        <?php echo htmlspecialchars($c['package_name'] ?? 'Tanpa Paket'); ?><br>
                        <small style="color: var(--neon-cyan);">
                            <i class="fas fa-server"></i> <?php echo htmlspecialchars($c['router_name'] ?? 'Default Router'); ?>
                        </small>
                    </td>
                    <td>
                        <?php
                        $lastInvoice = fetchOne("SELECT due_date FROM invoices WHERE customer_id = ? AND status = 'paid' ORDER BY due_date DESC LIMIT 1", [$c['id']]);
                        if ($lastInvoice && isset($lastInvoice['due_date'])) {
                            echo date('d M Y', strtotime($lastInvoice['due_date']));
                        } else {
                            echo '<span style="color: var(--text-muted);">Belum ada pembayaran</span>';
                        }
                        ?>
                    </td>
                    <td data-label="Status">
                        <?php if ($c['status'] === 'active'): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Isolir</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="PPPoE">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <code style="background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; font-family: 'Courier New', monospace;">
                                <?php echo htmlspecialchars($c['pppoe_username']); ?>
                            </code>
                            <button type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($c['pppoe_username']); ?>')" title="Salin ke clipboard" style="background: rgba(25, 29, 26, 0.15); border: 1px solid rgba(202, 206, 202, 0.3); color: var(--accent-green); width: 32px; height: 32px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; font-size: 0.9rem;" onmouseover="this.style.background='rgba(76,175,80,0.25)'; this.style.borderColor='rgba(7, 7, 7, 0.5)'; this.style.transform='scale(1.05)';" onmouseout="this.style.background='rgba(76,175,80,0.15)'; this.style.borderColor='rgba(76,175,80,0.3)'; this.style.transform='scale(1);">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <?php if (isset($c['in_radius'])): ?>
                            <?php if ($c['in_radius'] === true): ?>
                                <span class="badge badge-success" style="margin-left: 5px; margin-top: 5px;" title="Username terdaftar di RADIUS">
                                    <i class="fas fa-check-circle"></i> OK
                                </span>
                            <?php elseif ($c['in_radius'] === false): ?>
                                <span class="badge badge-warning" style="margin-left: 5px;" title="Username TIDAK terdaftar di RADIUS - Timeout tidak akan aktif">
                                    <i class="fas fa-exclamation-circle"></i> NOT
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td data-label="Tgl Isolir">
                        <?php 
                        if (!empty($c['isolation_date']) && $c['isolation_date'] != '0000-00-00') {
                            $isPast = (strtotime($c['isolation_date']) < strtotime(date('Y-m-d')));
                            $badgeClass = $isPast ? 'badge-danger' : 'badge-info';
                            echo '<span class="badge ' . $badgeClass . '">' . date('d M Y', strtotime($c['isolation_date'])) . '</span>';
                        } else {
                            echo '<span class="badge badge-muted">Belum diatur</span>';
                        }
                        ?>
                    </td>
                    <td data-label="Register Date">
                        <?php echo date('d M Y', strtotime($c['created_at'])); ?>
                    </td>
                    <td data-label="IP Address">
                        <?php echo htmlspecialchars($c['ip_address'] ?? 'N/A'); ?>
                    </td>
                    <td data-label="MAC Address">
                        <?php echo htmlspecialchars($c['mac_address'] ?? 'N/A'); ?>
                    </td>
                    <td data-label="Aksi">
                        <div class="customer-action-group">
                        <a href="pay_process.php?id=<?php echo $c['id']; ?>" class="btn btn-success btn-sm" title="Bayar Tagihan">
                            <i class="fas fa-money-bill-wave"></i> Bayar
                        </a>
                        <button class="btn btn-secondary btn-sm" onclick="editCustomer(<?php echo htmlspecialchars(json_encode($c)); ?>)" title="Edit">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <form method="POST" data-no-loading="true" onsubmit="return confirm('Reset password portal pelanggan ini menjadi 1234?');">
                            <input type="hidden" name="action" value="reset_portal_password">
                            <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" title="Reset Password Portal">
                                <i class="fas fa-key"></i> Reset Password
                            </button>
                        </form>
                        <form method="POST" data-no-loading="true"  onsubmit="return confirm('Apakah Anda yakin ingin menghapus pelanggan ini? Data yang dihapus tidak dapat dikembalikan.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </form>
                        <?php if ($c['status'] === 'isolated'): ?>
                            <form method="POST" data-no-loading="true">
                                <input type="hidden" name="action" value="unisolate">
                                <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <button type="submit" class="btn btn-success btn-sm" title="Buka Isolir">
                                    <i class="fas fa-unlock"></i> Buka Isolir
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" data-no-loading="true">
                                <input type="hidden" name="action" value="isolate">
                                <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <button type="submit" class="btn btn-error btn-sm" title="Isolir">
                                    <i class="fas fa-lock"></i> Isolir
                                </button>
                            </form>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div id="customerPagination" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px;">
        <a href="?page=1<?php echo $paginationQueryString; ?>" class="btn btn-secondary btn-sm" <?php echo $page === 1 ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-double-left"></i>
        </a>
        <a href="?page=<?php echo max(1, $page - 1); ?><?php echo $paginationQueryString; ?>" class="btn btn-secondary btn-sm" <?php echo $page === 1 ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-left"></i> 
        </a>
        
        <span style="color: var(--text-secondary); display: inline-block; text-align: center; min-width: 260px;">
            Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?>
            (Total: <?php echo $totalCustomers; ?> pelanggan)
        </span>
        
        <a href="?page=<?php echo min($totalPages, $page + 1); ?><?php echo $paginationQueryString; ?>" class="btn btn-secondary btn-sm" <?php echo $page === $totalPages ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-right"></i>
        </a>
        <a href="?page=<?php echo $totalPages; ?><?php echo $paginationQueryString; ?>" class="btn btn-secondary btn-sm" <?php echo $page === $totalPages ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-double-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div>
        
<!-- PPPoE User Modal -->
<div id="pppoeUserModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000;">
    <div class="card" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 360px; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0; color: var(--neon-cyan);">
                <i class="fas fa-network-wired"></i> Pilih Username PPPoE
            </h3>
            <button type="button" onclick="closePppoeUserModal()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem;">&times;</button>
        </div>
        <div class="form-group" style="margin-bottom: 15px;">
            <input type="text" id="pppoeUserSearch" class="form-control" placeholder="Cari username PPPoE...">
        </div>
        <div id="pppoeUserList" style="max-height: 60vh; overflow-y: auto;"></div>
    </div>
</div>
        
<!-- Edit Customer Modal -->
<div id="editCustomerModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div class="card" style="width: 800px; max-width: 90%; margin: 2rem; max-height: 90vh; overflow-y: auto; padding: 0;">
        <div style="padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: var(--bg-card); z-index: 10;">
            <h3 style="margin: 0; color: var(--neon-cyan); font-size: 1.2rem;">
                <i class="fas fa-edit"></i> Edit Pelanggan
            </h3>
            <button onclick="closeEditModal()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.5rem; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>
        
        <div style="padding: 25px;">
        <form method="POST" id="editCustomerForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="customer_id" id="edit_customer_id">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <!-- Basic Information Section -->
            <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);">
                <div class="form-group">
                    <label class="form-label">Load Form (paste data form disini)</label>
                    <textarea name="form" id="formInput" class="form-control" rows="10" placeholder="Nama yang di daftarkan : XXX&#10;Username : XXX@XXX &#10;Password : 1234&#10;Nama wifi : XXXX&#10;Password : XXXX&#10;Alamat : KP XXX&#10;RT/RW : XX/XX&#10;Kecamatan : XXXXX&#10;NO HP : +62 8XXXXXXXX&#10;Paket Wifi : STAR LEGEND" style="background: rgba(255,255,255,0.05); font-family: 'Courier New', monospace; font-size: 0.75rem; color: var(--text-muted);"></textarea>
                    <button type="button" onclick="loadFormCreate()" style="margin-top: 10px;" class="btn btn-primary">Load</button>
                </div>
                <h4 style="margin: 0 0 15px 0; color: var(--neon-cyan); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-user"></i> Informasi Dasar
                </h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Nama Pelanggan</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required placeholder="Nama Lengkap">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nomor HP (WhatsApp)</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control" required placeholder="08xxxxxxxxxx">
                    </div>
                </div>
            </div>
            
            <!-- PPPoE Configuration Section -->
            <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);">
                <h4 style="margin: 0 0 15px 0; color: var(--neon-cyan); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-network-wired"></i> Konfigurasi PPPoE
                </h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Username PPPoE</label>
                        <input type="text" name="pppoe_username" id="edit_pppoe_username" class="form-control" required placeholder="Username di MikroTik" style="background: rgba(255,255,255,0.05);">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password PPPoE</label>
                        <input type="text" name="pppoe_password" id="edit_pppoe_password" class="form-control" placeholder="(Loading...)" style="background: rgba(255,255,255,0.05);" autocomplete="off">
                        <small style="color: var(--text-muted); margin-top: 5px; display: block;">Auto-load dari RADIUS. Kosongkan untuk skip update.</small>
                    </div>
                </div>
            </div>

            <!-- Service Configuration Section -->
            <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);">
                <h4 style="margin: 0 0 15px 0; color: var(--neon-cyan); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-cogs"></i> Konfigurasi Layanan
                </h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Paket Langganan</label>
                        <select name="package_id" id="edit_package_id" class="form-control" required style="color: var(--text-primary); background: var(--bg-card);">
                            <option value="">Pilih Paket</option>
                            <?php foreach ($packages as $pkg): ?>
                                <option value="<?php echo $pkg['id']; ?>">
                                    <?php echo htmlspecialchars($pkg['name']); ?> (<?php echo formatCurrency($pkg['price']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Router / MikroTik</label>
                        <select name="router_id" id="edit_router_id" class="form-control" required style="color: var(--text-primary); background: var(--bg-card);">
                            <option value="0">Default Router</option>
                            <?php foreach ($routers as $r): ?>
                                <option value="<?php echo $r['id']; ?>">
                                    <?php echo htmlspecialchars($r['name']); ?> (<?php echo htmlspecialchars($r['host']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tanggal Isolir</label>
                        <input type="date" name="isolation_date" id="edit_isolation_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="auto_isolate" id="edit_auto_isolate" value="1" style="width: 18px; height: 18px; cursor: pointer;">
                            <span>Isolir Otomatis</span>
                        </label>
                        <small style="color: var(--text-muted);">Jika dimatikan, pelanggan diabaikan saat belum bayar.</small>
                    </div>
                </div>
            </div>

            <!-- Address Section -->
            <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);">
                <h4 style="margin: 0 0 15px 0; color: var(--neon-cyan); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-map-marker-alt"></i> Lokasi & Alamat
                </h4>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label">Alamat</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="2" placeholder="Alamat rumah pelanggan" style="resize: vertical;"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label">Koordinat (Latitude, Longitude)</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <input type="text" name="lat" id="edit_lat" class="form-control" placeholder="Latitude" readonly style="background: rgba(255,255,255,0.03); cursor: not-allowed;">
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">Klik peta untuk set</small>
                        </div>
                        <div>
                            <input type="text" name="lng" id="edit_lng" class="form-control" placeholder="Longitude" readonly style="background: rgba(255,255,255,0.03); cursor: not-allowed;">
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">Klik peta untuk set</small>
                        </div>
                    </div>
                </div>
                
                <div style="height: 300px; margin-top: 15px; border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1);" id="edit-map-picker"></div>
            </div>

            <!-- ONU Mapping Section -->
            <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);">
                <h4 style="margin: 0 0 15px 0; color: var(--neon-cyan); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-link"></i> Mapping ONU
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; padding: 10px; background: rgba(76,175,80,0.1); border-radius: 6px;">
                        <input type="checkbox" name="save_onu" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
                        <span>Sinkronisasi ke ONU Locations</span>
                    </label>
                    <div>
                        <label class="form-label">ODP (Optional)</label>
                        <select name="odp_id" id="edit_odp_select" class="form-control" style="color: var(--text-primary); background: var(--bg-card);">
                            <option value="">-- Pilih ODP --</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div style="display: flex; gap: 10px; margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px; font-size: 1rem; font-weight: 500;">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="flex: 1; padding: 12px; font-size: 1rem; font-weight: 500;">
                    <i class="fas fa-times"></i> Batal
                </button>
            </div>
        </form>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script>

let map, marker;
let editMap, editMarker;
let pppoeUsers = [];
const CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
const customerTableBody = document.querySelector('#customerTable tbody');
const initialCustomerTableHtml = customerTableBody ? customerTableBody.innerHTML : '';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatDateLabel(dateStr) {
    if (!dateStr) {
        return '';
    }

    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) {
        return escapeHtml(dateStr);
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    }).format(date);
}

function restoreInitialCustomers() {
    if (!customerTableBody) {
        return;
    }

    customerTableBody.innerHTML = initialCustomerTableHtml;

    const pagination = document.getElementById('customerPagination');
    if (pagination) {
        pagination.style.display = '';
    }
}

function getFilterElementValue(id) {
    const element = document.getElementById(id);
    return element ? element.value.trim() : '';
}

function renderFetchedCustomers(customers) {
    if (!customerTableBody) {
        return;
    }

    if (!customers || customers.length === 0) {
        customerTableBody.innerHTML = `
            <tr>
                <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 30px;" data-label="Data">
                    Tidak ada data pelanggan ditemukan
                </td>
            </tr>
        `;
        return;
    }

    customerTableBody.innerHTML = customers.map(customer => {
        const customerJson = escapeHtml(JSON.stringify(customer));
        const pppoeUsername = customer.pppoe_username || '';
        const statusBadge = customer.status === 'active'
            ? '<span class="badge badge-success">Aktif</span>'
            : '<span class="badge badge-warning">Isolir</span>';
        const radiusBadge = customer.in_radius === true
            ? '<span class="badge badge-success" style="margin-left: 5px; margin-top: 5px;" title="Username terdaftar di RADIUS"><i class="fas fa-check-circle"></i> OK</span>'
            : (customer.in_radius === false
                ? '<span class="badge badge-warning" style="margin-left: 5px;" title="Username TIDAK terdaftar di RADIUS - Timeout tidak akan aktif"><i class="fas fa-exclamation-circle"></i> NOT</span>'
                : '');

        return `
            <tr data-customer="${customerJson}">
                <td data-label="ID">#${escapeHtml(customer.id)}</td>
                <td data-label="Nama & Kontak">
                    <strong>${escapeHtml(customer.name)}</strong><br>
                    <small><i class="fab fa-whatsapp"></i> ${escapeHtml(customer.phone || 'N/A')}</small>
                </td>
                <td data-label="Paket & Router">
                    ${escapeHtml(customer.package_name || 'Tanpa Paket')}<br>
                    <small style="color: var(--neon-cyan);">
                        <i class="fas fa-server"></i> ${escapeHtml(customer.router_name || 'Default Router')}
                    </small>
                </td>
                <td>
                    ${customer.last_paid ? formatDateLabel(customer.last_paid) : '<span style="color: var(--text-muted);">Belum ada pembayaran</span>'}
                </td>
                <td data-label="Status">${statusBadge}</td>
                <td data-label="PPPoE">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <code style="background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; font-family: 'Courier New', monospace;">${escapeHtml(pppoeUsername)}</code>
                        <button type="button" onclick='copyToClipboard(${JSON.stringify(pppoeUsername)})' title="Salin ke clipboard" style="background: rgba(25, 29, 26, 0.15); border: 1px solid rgba(202, 206, 202, 0.3); color: var(--accent-green); width: 32px; height: 32px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; font-size: 0.9rem;" onmouseover="this.style.background='rgba(76,175,80,0.25)'; this.style.borderColor='rgba(7, 7, 7, 0.5)'; this.style.transform='scale(1.05)';" onmouseout="this.style.background='rgba(76,175,80,0.15)'; this.style.borderColor='rgba(76,175,80,0.3)'; this.style.transform='scale(1);'">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    ${radiusBadge}
                </td>
                <td data-label="Tgl Isolir"><span class="badge badge-info">${escapeHtml(customer.isolation_date)}</span></td>
                <td data-label="Register Date">${formatDateLabel(customer.created_at)}</td>
                <td data-label="IP Address">${escapeHtml(customer.ip_address || 'N/A')}</td>
                <td data-label="MAC Address">${escapeHtml(customer.mac_address || 'N/A')}</td>
                <td data-label="Aksi">
                    <div class="customer-action-group">
                    <a href="pay_process.php?id=${encodeURIComponent(customer.id)}" class="btn btn-success btn-sm" title="Bayar Tagihan">
                        <i class="fas fa-money-bill-wave"></i> Bayar
                    </a>
                    <button class="btn btn-secondary btn-sm" type="button" onclick="editCustomerFromRow(this)" title="Edit">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <form method="POST" data-no-loading="true" onsubmit="return confirm('Reset password portal pelanggan ini menjadi 1234?');">
                        <input type="hidden" name="action" value="reset_portal_password">
                        <input type="hidden" name="customer_id" value="${escapeHtml(customer.id)}">
                        <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
                        <button type="submit" class="btn btn-secondary btn-sm" title="Reset Password Portal">
                            <i class="fas fa-key"></i> Reset Password
                        </button>
                    </form>
                    <form method="POST" data-no-loading="true" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pelanggan ini? Data yang dihapus tidak dapat dikembalikan.');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="customer_id" value="${escapeHtml(customer.id)}">
                        <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
                        <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </form>
                    ${customer.status === 'isolated' ? `
                        <form method="POST" data-no-loading="true">
                            <input type="hidden" name="action" value="unisolate">
                            <input type="hidden" name="customer_id" value="${escapeHtml(customer.id)}">
                            <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
                            <button type="submit" class="btn btn-success btn-sm" title="Buka Isolir">
                                <i class="fas fa-unlock"></i> Buka Isolir
                            </button>
                        </form>
                    ` : `
                        <form method="POST" data-no-loading="true">
                            <input type="hidden" name="action" value="isolate">
                            <input type="hidden" name="customer_id" value="${escapeHtml(customer.id)}">
                            <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
                            <button type="submit" class="btn btn-error btn-sm" title="Isolir">
                                <i class="fas fa-lock"></i> Isolir
                            </button>
                        </form>
                    `}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function fetchCustomerSearch(search) {
    const pagination = document.getElementById('customerPagination');

    const status = document.getElementById('filterStatus') ? document.getElementById('filterStatus').value : '';
    const pkg = document.getElementById('filterPackage') ? document.getElementById('filterPackage').value : '';
    const router = document.getElementById('filterRouter') ? document.getElementById('filterRouter').value : '';
    const tech = document.getElementById('filterTech') ? document.getElementById('filterTech').value : '';
    const lastPaidFrom = getFilterElementValue('filterLastPaidFrom');
    const lastPaidTo = getFilterElementValue('filterLastPaidTo');
    const isolationFrom = getFilterElementValue('filterIsolationFrom');
    const isolationTo = getFilterElementValue('filterIsolationTo');
    const registerFrom = getFilterElementValue('filterRegisterFrom');
    const registerTo = getFilterElementValue('filterRegisterTo');
    const perPageSelect = document.getElementById('perPageSelect');
    const perPage = perPageSelect ? perPageSelect.value : '10';
    const hasFilters = status || pkg || router || tech || lastPaidFrom || lastPaidTo || isolationFrom || isolationTo || registerFrom || registerTo;

    // If no search and no filters, restore initial server-rendered rows
    if ((!search || search.length < 2) && !hasFilters) {
        restoreInitialCustomers();
        return;
    }

    if (pagination) {
        pagination.style.display = 'none';
    }

    if (customerTableBody) {
        customerTableBody.innerHTML = `
            <tr>
                <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 30px;">Memuat...</td>
            </tr>
        `;
    }

    try {
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (status) params.append('filter_status', status);
        if (pkg) params.append('filter_package', pkg);
        if (router) params.append('filter_router', router);
        if (tech) params.append('filter_tech', tech);
        if (lastPaidFrom) params.append('filter_last_paid_from', lastPaidFrom);
        if (lastPaidTo) params.append('filter_last_paid_to', lastPaidTo);
        if (isolationFrom) params.append('filter_isolation_from', isolationFrom);
        if (isolationTo) params.append('filter_isolation_to', isolationTo);
        if (registerFrom) params.append('filter_register_from', registerFrom);
        if (registerTo) params.append('filter_register_to', registerTo);
        params.append('per_page', perPage);
        params.append('page', '1');

        const response = await fetch(`../api/customers.php?${params.toString()}`);
        const data = await response.json();

        if (data.success && data.data && Array.isArray(data.data.customers)) {
            renderFetchedCustomers(data.data.customers);
        } else if (customerTableBody) {
            customerTableBody.innerHTML = `
                <tr>
                    <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 30px;">${escapeHtml(data.message || 'Tidak ada data ditemukan')}</td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Search customer error:', error);
        if (customerTableBody) {
            customerTableBody.innerHTML = `
                <tr>
                    <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 30px;">Terjadi kesalahan saat mencari</td>
                </tr>
            `;
        }
    }
}



function editCustomerFromRow(button) {
    const row = button.closest('tr');
    if (!row || !row.dataset.customer) {
        return;
    }

    try {
        editCustomer(JSON.parse(row.dataset.customer));
    } catch (error) {
        console.error('Failed to parse customer row data:', error);
    }
}

function openAddCustomerModal() {
    const modal = document.getElementById('addCustomerModal');
    if (!modal) {
        return;
    }

    modal.style.display = 'flex';

    const form = document.getElementById('addCustomerForm');
    if (form) {
        form.reset();
    }

    const latInput = document.getElementById('add_lat');
    const lngInput = document.getElementById('add_lng');
    if (latInput) {
        latInput.value = '';
    }
    if (lngInput) {
        lngInput.value = '';
    }

    if (!map) {
        initMap();
    } else {
        setTimeout(() => {
            if (map) {
                map.invalidateSize();
            }
        }, 150);
    }
}

function closeAddCustomerModal() {
    const modal = document.getElementById('addCustomerModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openRenameCustomerModal(customer) {
    editCustomer(customer);
}

function openPppoeUserModal() {
    const modal = document.getElementById('pppoeUserModal');
    if (!modal) {
        return;
    }
    modal.style.display = 'flex';
    
    const list = document.getElementById('pppoeUserList');
    if (list) {
        list.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">Memuat data dari MikroTik...</div>';
    }
    
    fetch('../api/mikrotik.php?action=users')
        .then(response => response.text())
        .then(text => {
            let data = null;
            try {
                const start = text.indexOf('{');
                if (start !== -1) {
                    data = JSON.parse(text.slice(start));
                }
            } catch (e) {
                console.error('Respon MikroTik tidak valid:', text, e);
            }
            
            if (data && data.success && data.data && Array.isArray(data.data.users)) {
                pppoeUsers = data.data.users;
                renderPppoeUserList(pppoeUsers);
            } else if (list) {
                list.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">Gagal mengambil data dari MikroTik</div>';
            }
        })
        .catch(error => {
            console.error('Fetch MikroTik error:', error);
            if (list) {
                list.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">Gagal mengambil data dari MikroTik</div>';
            }
        });
}

function renderPppoeUserList(users) {
    const list = document.getElementById('pppoeUserList');
    if (!list) {
        return;
    }
    
    if (!users || users.length === 0) {
        list.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">Tidak ada user PPPoE ditemukan</div>';
        return;
    }
    
    list.innerHTML = '';
    
    users.forEach(user => {
        const username = user.name || user['name'];
        if (!username) {
            return;
        }
        
        const container = document.createElement('div');
        container.style.display = 'flex';
        container.style.gap = '8px';
        container.style.marginBottom = '8px';
        container.style.alignItems = 'stretch';
        
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'btn btn-secondary';
        item.style.flex = '1';
        item.style.textAlign = 'left';
        item.textContent = username;
        item.onclick = function() {
            const input = document.getElementById('pppoe_username_input') || document.querySelector('input[name="pppoe_username"]');
            if (input) {
                input.value = username;
            }
            closePppoeUserModal();
        };
        
        const copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.style.flex = '0 0 38px';
        copyBtn.style.padding = '0';
        copyBtn.style.background = 'rgba(255,255,255,0.08)';
        copyBtn.style.border = '1px solid rgba(255,255,255,0.2)';
        copyBtn.style.color = 'rgba(255,255,255,0.7)';
        copyBtn.style.borderRadius = '6px';
        copyBtn.style.cursor = 'pointer';
        copyBtn.style.transition = 'all 0.2s ease';
        copyBtn.style.display = 'flex';
        copyBtn.style.alignItems = 'center';
        copyBtn.style.justifyContent = 'center';
        copyBtn.title = 'Salin ke clipboard';
        copyBtn.innerHTML = '<i class="fas fa-copy" style="font-size: 0.9rem;"></i>';
        copyBtn.onmouseover = function() {
            this.style.background = 'rgba(255,255,255,0.15)';
            this.style.borderColor = 'rgba(255,255,255,0.4)';
            this.style.color = 'rgba(255,255,255,0.95)';
            this.style.transform = 'scale(1.08)';
        };
        copyBtn.onmouseout = function() {
            this.style.background = 'rgba(255,255,255,0.08)';
            this.style.borderColor = 'rgba(255,255,255,0.2)';
            this.style.color = 'rgba(255,255,255,0.7)';
            this.style.transform = 'scale(1)';
        };
        copyBtn.onclick = function(e) {
            e.stopPropagation();
            copyToClipboard(username);
        };
        
        container.appendChild(item);
        container.appendChild(copyBtn);
        list.appendChild(container);
    });
}

function closePppoeUserModal() {
    const modal = document.getElementById('pppoeUserModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('pppoeUserSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const filtered = (pppoeUsers || []).filter(user => {
                const username = user.name || user['name'] || '';
                return username.toLowerCase().includes(term);
            });
            renderPppoeUserList(filtered);
        });
    }
    
    const modal = document.getElementById('pppoeUserModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closePppoeUserModal();
            }
        });
    }

    const addModal = document.getElementById('addCustomerModal');
    if (addModal) {
        addModal.addEventListener('click', function(e) {
            if (e.target === addModal) {
                closeAddCustomerModal();
            }
        });
    }

    // Filter button handlers (separate from live-search)
    const applyFilterBtn = document.getElementById('applyFilterBtn');
    const resetFilterBtn = document.getElementById('resetFilterBtn');
    const perPageSelect = document.getElementById('perPageSelect');
    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', function() {
            const searchVal = (document.getElementById('searchCustomer') || { value: '' }).value.trim();
            const params = new URLSearchParams(window.location.search);
            params.set('page', '1');
            params.set('search', searchVal);
            if (perPageSelect && perPageSelect.value) {
                params.set('per_page', perPageSelect.value);
            }

            const filterParamMap = {
                filterStatus: 'filter_status',
                filterPackage: 'filter_package',
                filterRouter: 'filter_router',
                filterTech: 'filter_tech',
                filterLastPaidFrom: 'filter_last_paid_from',
                filterLastPaidTo: 'filter_last_paid_to',
                filterIsolationFrom: 'filter_isolation_from',
                filterIsolationTo: 'filter_isolation_to',
                filterRegisterFrom: 'filter_register_from',
                filterRegisterTo: 'filter_register_to'
            };

            Object.keys(filterParamMap).forEach(id => {
                const element = document.getElementById(id);
                const value = element ? element.value.trim() : '';
                const paramName = filterParamMap[id];

                if (value) {
                    params.set(paramName, value);
                } else {
                    params.delete(paramName);
                }
            });

            window.location.search = params.toString();
        });
    }
    if (resetFilterBtn) {
        resetFilterBtn.addEventListener('click', function() {
            window.location.href = window.location.pathname;
        });
    }
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function() {
            const params = new URLSearchParams(window.location.search);
            params.set('page', '1');
            params.set('per_page', perPageSelect.value || '10');
            window.location.search = params.toString();
        });
    }
});

function initMap() {
    if (map) {
        return;
    }

    // Add map
    map = L.map('addMap').setView([-6.200000, 106.816666], 13);
    
    // Base layers
    var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    });
    
    var googleSat = L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
    });

    // Add default layer
    googleSat.addTo(map);

    // Layer control
    var baseMaps = {
        "OpenStreetMap": osm,
        "Satelit": googleSat
    };
    L.control.layers(baseMaps).addTo(map);
    
    map.on('click', function(e) {
        if (marker) {
            map.removeLayer(marker);
        }
        
        marker = L.marker(e.latlng).addTo(map);
        
        const latInput = document.getElementById('add_lat');
        const lngInput = document.getElementById('add_lng');
        if (latInput) {
            latInput.value = e.latlng.lat.toFixed(6);
        }
        if (lngInput) {
            lngInput.value = e.latlng.lng.toFixed(6);
        }
    });
}
function formatPhoneNumber(phoneStr) {
    if (!phoneStr) return '';
    
    let cleanNumber = phoneStr.replace(/\D/g, '');
    
    if (cleanNumber.startsWith('0')) {
        cleanNumber = '62' + cleanNumber.slice(1);
    }
    
    return cleanNumber;
}

function formatPhoneNumber(phoneStr) {
    if (!phoneStr) return '';
    let cleanNumber = phoneStr.replace(/\D/g, '');
    if (cleanNumber.startsWith('0')) {
        cleanNumber = '62' + cleanNumber.slice(1);
    }
    return cleanNumber;
}

function loadFormCreate(){
    const formInput = document.getElementById('formInput');
    if (formInput && formInput.value.trim() === '') {
        alert('Form input kosong!');
        return;
    }

    // LOGIC PARSING (Dibuat toleran terhadap Spasi & Huruf Kapital pada Key)
    const formInputObject = formInput.value.split("\n")
        .filter(baris => baris.trim() !== "") 
        .reduce((acc, baris) => {
            // Memastikan baris mengandung karakter ":" sebelum di-split
            if (baris.includes(":")) {
                const [rawKey, rawValue] = baris.split(":");
                
                // Normalisasi KEY: ubah ke lowercase, trim spasi luar, dan ganti spasi ganda menjadi single spasi
                const key = rawKey.trim().toLowerCase().replace(/\s+/g, ' ');
                const value = rawValue ? rawValue.trim() : '';
                
                if (!acc[key]) {
                    acc[key] = value;
                }
            }
            return acc;
        }, {});

    // Ambil tanggal hari ini untuk default isolation_date
    const today = new Date();
    const todayFormatted = today.toISOString().slice(0, 10); // Format YYYY-MM-DD
    
    console.log('Tanggal hari ini:', todayFormatted);
    console.log('Parsed form input:', formInputObject);
    
    // 1. Ambil & Toleransi Nama
    const nameValue = formInputObject['nama yang di daftarkan'] || formInputObject['nama yg di daftarkan'] || '';
    document.getElementById('edit_name').value = nameValue;
    
    // 2. Ambil & Toleransi No HP
    const rawPhone = formInputObject['no hp'] || formInputObject['no. hp'] || '';
    document.getElementById('edit_phone').value = formatPhoneNumber(rawPhone);
    
    // 3. Ambil Paket & Sinkronisasi dengan PHP
    const packageName = formInputObject['paket wifi'] || '';
    // Gunakan AJAX atau mapping sederhana, untuk sementara gunakan nilai default
    // document.getElementById('edit_package_id').value = <?php echo json_encode(getPackageIdByProfileName($formInputObject['paket wifi'] ?? '') ?: 1); ?>;
    
    // 4. Ambil Username
    document.getElementById('edit_pppoe_username').value = formInputObject['username'] || '';
    
    // 5. Password dikunci langsung ke 1234 sesuai request
    document.getElementById('edit_pppoe_password').value = '1234'; 
    
    // 6. Set Tanggal Isolir (DATE) - default ke HARI INI
    let isolationDate = formInputObject['tanggal isolir'] || formInputObject['tgl isolir'] || '';
    
    if (isolationDate) {
        // Parse berbagai format tanggal dari form input
        if (isolationDate.match(/\d{2}\/\d{2}\/\d{4}/)) {
            // Format: 31/12/2026
            let parts = isolationDate.split('/');
            isolationDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
        } else if (isolationDate.match(/\d{2}-\d{2}-\d{4}/)) {
            // Format: 31-12-2026
            let parts = isolationDate.split('-');
            isolationDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
        } else if (isolationDate.match(/\d{4}-\d{2}-\d{2}/)) {
            // Format: 2026-12-31 (sudah benar)
            isolationDate = isolationDate;
        } else if (isolationDate.match(/^\d+$/)) {
            // Hanya angka (tanggal saja), gunakan bulan depan dengan tanggal tersebut
            const defaultDate = new Date();
            defaultDate.setMonth(defaultDate.getMonth() + 1);
            defaultDate.setDate(parseInt(isolationDate, 10));
            isolationDate = defaultDate.toISOString().slice(0, 10);
        }
        document.getElementById('edit_isolation_date').value = isolationDate;
    } else {
        // Jika tidak ada input tanggal isolir, gunakan HARI INI
        document.getElementById('edit_isolation_date').value = todayFormatted;
    }
    
    // 7. Gabungkan Alamat Lengkap
    document.getElementById('edit_address').value = 
        (formInputObject['alamat'] || '') + 
        ' RT/RW ' + (formInputObject['rt/rw'] || '') + 
        ' Kecamatan ' + (formInputObject['kecamatan'] || '');
}
function initEditMap() {
    if (editMap) return;
    
    editMap = L.map('edit-map-picker').setView([-6.200000, 106.816666], 13);
    
    // Base layers
    var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    });
    
    var googleSat = L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
    });

    // Add default layer
    googleSat.addTo(editMap);

    // Layer control
    var baseMaps = {
        "OpenStreetMap": osm,
        "Satelit": googleSat
    };
    L.control.layers(baseMaps).addTo(editMap);
    
    editMap.on('click', function(e) {
        if (editMarker) {
            editMap.removeLayer(editMarker);
        }
        
        editMarker = L.marker(e.latlng).addTo(editMap);
        
        document.getElementById('edit_lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('edit_lng').value = e.latlng.lng.toFixed(6);
    });
}

// Search functionality
const searchCustomerInput = document.getElementById('searchCustomer');
if (searchCustomerInput) {
    let customerSearchTimer = null;
    searchCustomerInput.addEventListener('input', function(e) {
        const search = e.target.value.trim();
        clearTimeout(customerSearchTimer);
        customerSearchTimer = setTimeout(() => {
            fetchCustomerSearch(search);
        }, 350);
    });
}

// Filter fields are applied through the Filter button so pagination stays visible.

// Edit customer
function editCustomer(customer) {
    // If id is passed (number or string), fetch data (backward compatibility)
    if (typeof customer !== 'object') {
        fetch(`../api/customers.php?id=${customer}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    editCustomer(data.data);
                } else {
                    alert('Gagal mengambil data pelanggan: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengambil data pelanggan');
            });
        return;
    }

    document.getElementById('edit_customer_id').value = customer.id;
    document.getElementById('edit_name').value = customer.name;
    document.getElementById('edit_phone').value = customer.phone;
    document.getElementById('edit_pppoe_username').value = customer.pppoe_username;
    document.getElementById('edit_package_id').value = customer.package_id;
    document.getElementById('edit_router_id').value = customer.router_id || 0;
    if (customer.isolation_date && customer.isolation_date !== '0000-00-00') { document.getElementById('edit_isolation_date').value = customer.isolation_date; } else { const defaultDate = new Date(); defaultDate.setMonth(defaultDate.getMonth() + 1); defaultDate.setDate(20); document.getElementById('edit_isolation_date').value = defaultDate.toISOString().slice(0, 10); }
    const autoIsolate = document.getElementById('edit_auto_isolate');
    if (autoIsolate) {
        autoIsolate.checked = String(customer.auto_isolate ?? 1) === '1';
    }
    document.getElementById('edit_address').value = customer.address || '';
    document.getElementById('edit_lat').value = customer.lat || '';
    document.getElementById('edit_lng').value = customer.lng || '';
    
    // Set technician
    const techSelect = document.getElementById('edit_installed_by');
    if (techSelect) {
        techSelect.value = customer.installed_by || '';
    }

    // Set ODP
    const odpSelect = document.getElementById('edit_odp_select');
    if (odpSelect) {
        odpSelect.value = customer.onu_odp_id || '';
    }
    
    // Clear password field initially
    const passwordField = document.getElementById('edit_pppoe_password');
    passwordField.value = '';
    passwordField.readOnly = true;
    
    // Fetch current password from RADIUS
    if (customer.pppoe_username) {
        fetch(`../api/customers.php?action=get_password&username=${encodeURIComponent(customer.pppoe_username)}`)
            .then(response => response.json())
            .then(data => {
                console.log('Password fetch response:', data);
                if (data.success && data.password) {
                    console.log('Setting password:', data.password);
                    passwordField.value = data.password;
                } else {
                    console.warn('No password returned or error:', data.message);
                    passwordField.value = '(Tidak ada password di RADIUS)';
                }
                // Enable editing after password is loaded
                passwordField.readOnly = false;
            })
            .catch(error => {
                console.error('Error fetching password:', error);
                passwordField.value = '(Error loading password)';
                passwordField.readOnly = false;
            });
    }
    
    // Show modal
    document.getElementById('editCustomerModal').style.display = 'flex';
    
    // Initialize map if needed and set view
    setTimeout(() => {
        initEditMap();
        editMap.invalidateSize();
        
        if (customer.lat && customer.lng) {
            const latlng = [customer.lat, customer.lng];
            editMap.setView(latlng, 15);
            
            if (editMarker) editMap.removeLayer(editMarker);
            editMarker = L.marker(latlng).addTo(editMap);
        }
    }, 100);
}

function closeEditModal() {
    document.getElementById('editCustomerModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('editCustomerModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Initialize map when page loads
setTimeout(initMap, 500);

// Load ODP list for dropdowns
function loadOdpOptions() {
    fetch('../api/onu_locations.php')
        .then(r => r.json())
        .then(j => {
            if (!j.success) return;
            const odps = j.odps || [];
            const addSel = document.getElementById('add_odp_select');
            const editSel = document.getElementById('edit_odp_select');
            const makeOptions = (sel) => {
                if (!sel) return;
                // keep first option
                sel.innerHTML = '<option value=\"\">-- Pilih ODP --</option>';
                odps.forEach(o => {
                    const opt = document.createElement('option');
                    opt.value = o.id;
                    opt.textContent = o.name + (o.code ? (' (' + o.code + ')') : '');
                    sel.appendChild(opt);
                });
            };
            makeOptions(addSel);
            makeOptions(editSel);
        })
        .catch(() => {});
}

document.addEventListener('DOMContentLoaded', loadOdpOptions);

// Copy to clipboard helper function
function copyToClipboard(text) {
    if (!text) return;
    
    navigator.clipboard.writeText(text).then(() => {
        // Show success feedback
        const feedback = document.createElement('div');
        feedback.textContent = '✓ Username disalin!';
        feedback.style.position = 'fixed';
        feedback.style.top = '24px';
        feedback.style.right = '24px';
        feedback.style.background = 'rgba(255,255,255,0.95)';
        feedback.style.color = '#000';
        feedback.style.padding = '12px 20px';
        feedback.style.borderRadius = '8px';
        feedback.style.zIndex = '9999';
        feedback.style.fontWeight = '600';
        feedback.style.fontSize = '0.9rem';
        feedback.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)';
        feedback.style.border = '1px solid rgba(0,0,0,0.1)';
        feedback.style.animation = 'slideInOut 2.5s ease-in-out forwards';
        
        document.body.appendChild(feedback);
        
        setTimeout(() => feedback.remove(), 2500);
    }).catch(err => {
        console.error('Gagal menyalin:', err);
        // Show error feedback
        const error = document.createElement('div');
        error.textContent = '✕ Gagal menyalin';
        error.style.position = 'fixed';
        error.style.top = '24px';
        error.style.right = '24px';
        error.style.background = 'rgba(255,0,0,0.1)';
        error.style.color = '#ff6b6b';
        error.style.padding = '12px 20px';
        error.style.borderRadius = '8px';
        error.style.zIndex = '9999';
        error.style.fontWeight = '600';
        error.style.fontSize = '0.9rem';
        error.style.boxShadow = '0 4px 12px rgba(255,0,0,0.2)';
        error.style.border = '1px solid rgba(255,0,0,0.3)';
        error.style.animation = 'slideInOut 2.5s ease-in-out forwards';
        
        document.body.appendChild(error);
        
        setTimeout(() => error.remove(), 2500);
    });
}

// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInOut {
        0% { opacity: 0; transform: translateX(20px) translateY(-10px); }
        10% { opacity: 1; transform: translateX(0) translateY(0); }
        90% { opacity: 1; transform: translateX(0) translateY(0); }
        100% { opacity: 0; transform: translateX(20px) translateY(-10px); }
    }
`;
document.head.appendChild(style);
class ProductTour {
    constructor(steps) {
        this.steps = steps;
        this.currentStep = 0;
        this.overlay = null;
        this.tooltip = null;
        this.highlightBox = null; // elemen overlay terpisah untuk highlight
    }

    start() {
        if (localStorage.getItem('tourCompleted_customers') === 'true') return;
        this.showStep(0);
    }

    restart() {
        localStorage.removeItem('tourCompleted_customers');
        this.currentStep = 0;
        this.showStep(0);
    }

    showStep(index) {
        if (index >= this.steps.length) { this.finish(); return; }

        this.currentStep = index;
        const step = this.steps[index];
        const element = document.querySelector(step.element);

        // Jika elemen tidak ada di DOM, skip ke step berikutnya
        if (!element) {
            this.showStep(index + 1);
            return;
        }

        this.removeHighlight();



        // Scroll elemen ke tengah viewport dulu
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Tunggu scroll selesai, baru gambar highlight dan tooltip
        setTimeout(() => {
            this.drawHighlight(element);
            this.createTooltip(element, step);
        }, 400);
    }

    drawHighlight(element) {
        if (this.highlightBox) {
            this.highlightBox.remove();
            this.highlightBox = null;
        }

        const rect = element.getBoundingClientRect();
        const PAD = 6;

        this.highlightBox = document.createElement('div');
        Object.assign(this.highlightBox.style, {
            position:      'fixed',
            top:           `${rect.top - PAD}px`,
            left:          `${rect.left - PAD}px`,
            width:         `${rect.width + PAD * 2}px`,
            height:        `${rect.height + PAD * 2}px`,
            border:        '3px solid #00d4ff',
            borderRadius:  '10px',
            // box-shadow spread 9999px = gelap di luar, glow di dalam border
            boxShadow:     '0 0 0 9999px rgba(0,0,0,0.72), 0 0 18px 4px #00d4ff',
            zIndex:        '9999',
            pointerEvents: 'none',
            animation:     'tourPulse 1s ease-in-out infinite alternate',
        });

        document.body.appendChild(this.highlightBox);
    }
    createTooltip(element, step) {
        if (this.tooltip) this.tooltip.remove();

        const placement = step.placement || 'bottom';

        this.tooltip = document.createElement('div');
        this.tooltip.className = 'tour-tooltip';
        this.tooltip.setAttribute('data-placement', placement);
        this.tooltip.innerHTML = `
            <h4>${step.title}</h4>
            <p>${step.description}</p>
            <div class="tour-buttons">
                <button class="tour-close" onclick="tour.finish()">Lewati</button>
                ${this.currentStep > 0 ? '<button class="tour-prev" onclick="tour.prev()">Kembali</button>' : ''}
                <button class="tour-next" onclick="tour.next()">
                    ${this.currentStep === this.steps.length - 1 ? 'Selesai' : 'Selanjutnya'}
                </button>
            </div>
        `;

        this.tooltip.style.visibility = 'hidden';
        this.tooltip.style.position = 'fixed';
        this.tooltip.style.zIndex = '10000';
        document.body.appendChild(this.tooltip);

        requestAnimationFrame(() => {
            const elRect  = element.getBoundingClientRect();
            const ttRect  = this.tooltip.getBoundingClientRect();
            const GAP     = 14;
            const MARGIN  = 10;
            const ARROW_HALF     = 6;
            const MIN_ARROW_OFFSET = 16;

            let top, left;

            switch (placement) {
                case 'top':
                    top  = elRect.top - ttRect.height - GAP;
                    left = elRect.left + elRect.width / 2 - ttRect.width / 2;
                    break;
                case 'bottom':
                    top  = elRect.bottom + GAP;
                    left = elRect.left + elRect.width / 2 - ttRect.width / 2;
                    break;
                case 'left':
                    top  = elRect.top + elRect.height / 2 - ttRect.height / 2;
                    left = elRect.left - ttRect.width - GAP;
                    break;
                case 'right':
                    top  = elRect.top + elRect.height / 2 - ttRect.height / 2;
                    left = elRect.right + GAP;
                    break;
                default:
                    top  = elRect.bottom + GAP;
                    left = elRect.left + elRect.width / 2 - ttRect.width / 2;
            }

            left = Math.max(MARGIN, Math.min(left, window.innerWidth  - ttRect.width  - MARGIN));
            top  = Math.max(MARGIN, Math.min(top,  window.innerHeight - ttRect.height - MARGIN));

            this.tooltip.style.left = `${left}px`;
            this.tooltip.style.top  = `${top}px`;

            // Posisi arrow
            const targetCX = elRect.left + elRect.width  / 2;
            const targetCY = elRect.top  + elRect.height / 2;

            if (placement === 'top' || placement === 'bottom') {
                let arrowLeft = targetCX - left - ARROW_HALF;
                arrowLeft = Math.max(MIN_ARROW_OFFSET, Math.min(arrowLeft, ttRect.width - MIN_ARROW_OFFSET));
                this.tooltip.style.setProperty('--arrow-x', `${arrowLeft}px`);
                this.tooltip.style.removeProperty('--arrow-y');
            } else {
                let arrowTop = targetCY - top - ARROW_HALF;
                arrowTop = Math.max(MIN_ARROW_OFFSET, Math.min(arrowTop, ttRect.height - MIN_ARROW_OFFSET));
                this.tooltip.style.setProperty('--arrow-y', `${arrowTop}px`);
                this.tooltip.style.removeProperty('--arrow-x');
            }

            this.tooltip.style.visibility = '';
        });
    }

    removeHighlight() {
        if (this.highlightBox) {
            this.highlightBox.remove();
            this.highlightBox = null;
        }
        // Bersihkan juga sisa-sisa class lama jika ada
        document.querySelectorAll('.tour-highlight').forEach(el => el.classList.remove('tour-highlight'));
    }

    next() { this.removeHighlight(); this.showStep(this.currentStep + 1); }
    prev() { this.removeHighlight(); this.showStep(this.currentStep - 1); }

    finish() {
        if (this.tooltip)       { this.tooltip.remove();       this.tooltip = null; }
        if (this.highlightBox)  { this.highlightBox.remove();  this.highlightBox = null; }
        this.removeHighlight();
        localStorage.setItem('tourCompleted_customers', 'true');
    }

    reset() {
        localStorage.removeItem('tourCompleted_customers');
        this.currentStep = 0;
        this.start();
    }
}

// Definisi langkah-langkah tour untuk halaman Customers
const tourSteps = [
    {
        element: '.stats-grid',
        title: '📊 Statistik Dashboard',
        description: 'Lihat ringkasan data penting: Total Pelanggan, Aktif, Terisolir, dan Belum Lunas bulan ini.',
        placement: 'bottom'
    },
    {
        element: 'body > div.main-content > div.page-content > div.actions-row > button:nth-child(1)',
        title: '➕ Tambah Pelanggan',
        description: 'Klik untuk menambah pelanggan baru. Isi data nama, nomor HP, username PPPoE, paket, dan lokasi.',
        placement: 'left'
    },
    {
        element: 'body > div.main-content > div.page-content > div.actions-row > button:nth-child(2)',
        title: '🔁 Tambah via Rename',
        description: 'Gunakan username PPPoE cadangan yang sudah ada di MikroTik untuk mendaftarkan pelanggan baru.',
        placement: 'bottom'
    },
        {
        element: 'body > div.main-content > div.page-content > div.actions-row > button:nth-child(3)',
        title: '📤 Export/Import',
        description: 'Export atau import data pelanggan dalam format Excel untuk manajemen data massal yang lebih mudah.',
        placement: 'right'
    },
    {
        element: '#perPageSelect',
        title: '📄 Jumlah Data per Halaman',
        description: 'Ubah jumlah data yang ditampilkan per halaman: 10, 50, 100, 250, atau 500.',
        placement: 'bottom'
    },
    {
        element: '#searchCustomer',
        title: '🔎 Pencarian Pelanggan',
        description: 'Cari berdasarkan nama, nomor HP, atau username PPPoE. Ketik minimal 2 karakter untuk memulai pencarian.',
        placement: 'left'
    },
    {
        element: '#filterContainer',
        title: '🔍 Filter Status',
        description: 'Filter pelanggan berdasarkan kondisi tertentu.',
        placement: 'bottom'
    },

    {
        element: '#applyFilterBtn',
        title: '✅ Terapkan Filter',
        description: 'Klik Filter untuk menerapkan semua kriteria yang sudah dipilih. Klik Reset untuk menghapus semua filter.',
        placement: 'right'
    },
    {
        element: '#customerTable',
        title: '📋 Tabel Pelanggan',
        description: 'Semua data pelanggan ditampilkan di sini beserta status, paket, router, dan tombol aksi.',
        placement: 'top'
    },
    {
        element: '#customerTable tbody tr:first-child .customer-action-group',
        title: '⚙️ Tombol Aksi',
        description: 'Setiap baris memiliki: Bayar (catat pembayaran), Edit (ubah data), Reset Password, Hapus, dan Isolir/Buka Isolir.',
        placement: 'left'
    },
    {
        element: '#customerPagination',
        title: '⏮️ Navigasi Halaman',
        description: 'Gunakan tombol navigasi untuk berpindah antar halaman data pelanggan.',
        placement: 'top'
    },
    {
        element: '.tour-btn',
        title: '🎓 Tour Kapan Saja',
        description: 'Klik tombol ini untuk memulai ulang tour jika ingin melihat panduan lagi.',
        placement: 'left'
    }
    
];
// Inisialisasi tour
const tour = new ProductTour(tourSteps);

// Fungsi untuk memulai tour (bisa dipanggil dari tombol)
function startTour() {
    tour.restart();
}

// Do not auto-start tour to avoid unexpected overlays; start only via button
document.addEventListener('DOMContentLoaded', function() {
    // Wire header tour button if present
    const tourBtn = document.querySelector('.tour-btn') || document.getElementById('startTourBtn');
    if (tourBtn) tourBtn.addEventListener('click', () => startTour());
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
