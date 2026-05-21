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
                    'isolation_date' => (int)$_POST['isolation_date'],
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
                    'isolation_date' => (int)$_POST['isolation_date'],
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
$perPage = 10000;
$offset = ($page - 1) * $perPage;

$customersTableExists = tableExists('customers');
$packagesTableExists = tableExists('packages');
$routersTableExists = tableExists('routers');

// Get technicians
$technicians = fetchAll("SELECT * FROM technician_users WHERE status = 'active' ORDER BY name ASC");

if ($customersTableExists) {
    $totalCustomers = fetchOne("SELECT COUNT(*) as total FROM customers")['total'] ?? 0;
    $totalPages = ceil($totalCustomers / $perPage);

    // Build query with proper JOINs to avoid N+1 queries
    $selectParts = [
        'c.id', 'c.name', 'c.phone', 'c.pppoe_username', 'c.package_id', 'c.router_id',
        'c.isolation_date', 'c.address', 'c.lat', 'c.lng', 'c.status', 'c.created_at', 'c.auto_isolate', 'c.installed_by',
        $packagesTableExists ? 'p.name as package_name' : "'Tanpa Paket' as package_name",
        $packagesTableExists ? 'p.price as package_price' : "'0' as package_price",
        $routersTableExists ? 'r.name as router_name' : "'' as router_name",
        'COALESCE(onu.odp_id, NULL) as onu_odp_id',
        'IF(rc.username IS NOT NULL, TRUE, FALSE) as in_radius'
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
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    
} else {
    $totalCustomers = 0;
    $totalPages = 0;
    $customers = [];
}

$packages = $packagesTableExists ? fetchAll("SELECT * FROM packages ORDER BY name") : [];
$routers = $routersTableExists ? getAllRouters() : [];

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
    
    // Calculate unpaid customers for current month
    // Logic: Active customers who don't have a 'paid' invoice for current month
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
    /* Make stats grid responsive for 4 cards */
    .stats-grid {
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 15px;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr) !important;
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

<!-- Add Customer Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-plus"></i> Tambah Pelanggan</h3>
    </div>
    
    <form method="POST" style="padding: 20px;" data-no-loading="true">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; max-width: 100%;">
            <div class="form-group">
                <label class="form-label">Nama Pelanggan</label>
                <input type="text" name="name" class="form-control" required placeholder="Nama Lengkap">
            </div>
            
            <div class="form-group">
                <label class="form-label">Nomor HP (WhatsApp)</label>
                <input type="text" name="phone" class="form-control" required placeholder="08xxxxxxxxxx">
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label">Username PPPoE</label>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" name="pppoe_username" id="pppoe_username_input" class="form-control" required placeholder="Pilih atau ketik username" style="flex: 1 1 200px; min-width: 0;">
                    <button type="button" class="btn btn-secondary" onclick="openPppoeUserModal()" style="flex: 0 0 auto; white-space: nowrap;">Pilih dari Daftar</button>
                </div>
                <small style="color: var(--text-muted);">Pilih username PPPoE dari user MikroTik untuk menghindari salah input</small>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label">Password PPPoE (Opsional)</label>
                <input type="text" name="pppoe_password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password PPPoE">
                <small style="color: var(--text-muted);">Jika diisi, password ini akan dikirim ke perangkat (GenieACS). Aplikasi tidak bisa membaca password dari MikroTik.</small>
            </div>
            
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
                <label class="form-label">Tanggal Isolir (1-28)</label>
                <input type="number" name="isolation_date" class="form-control" value="20" min="1" max="28" required>
            </div>

            <div class="form-group">
                <label class="form-label" style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="auto_isolate" value="1" checked>
                    <span>Isolir Otomatis</span>
                </label>
                <small style="color: var(--text-muted);">Jika dimatikan, pelanggan ini akan diabaikan oleh isolir otomatis saat tagihan belum dibayar.</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Alamat</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Alamat rumah"></textarea>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Lokasi (Latitude, Longitude)</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <input type="text" name="lat" class="form-control" placeholder="Latitude" readonly>
                <input type="text" name="lng" class="form-control" placeholder="Longitude" readonly>
            </div>
            <small style="color: var(--text-muted);">Klik pada peta untuk set lokasi</small>
        </div>
        
        <div style="height: 300px; margin-top: 15px; border-radius: 8px; overflow: hidden;" id="map-picker"></div>

        <div class="form-group" style="margin-top: 15px; background: var(--bg-card); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
            <label class="form-label" style="display: block; margin-bottom: 10px;">
                Mapping ONU
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="save_onu" value="1" checked>
                    <span>Sekaligus simpan titik ke ONU Locations</span>
                </label>
                <div>
                    <label class="form-label">ODP (Opsional)</label>
                    <select name="odp_id" id="add_odp_select" class="form-control" style="color: var(--text-primary); background: var(--bg-card);">
                        <option value="">-- Pilih ODP --</option>
                    </select>
                    <small style="color: var(--text-muted);">Jika belum ada, tambah ODP di menu GenieACS Peta</small>
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary" style="margin-top: 20px;">
            <i class="fas fa-save"></i> Simpan Pelanggan
        </button>
    </form>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users"></i> Daftar Pelanggan</h3>
        <div style="display: flex; gap: 10px;">
            <input type="text" id="searchCustomer" class="form-control" placeholder="Cari pelanggan..." style="width: 250px;">
            <a href="export.php" class="btn btn-primary btn-sm">
                <i class="fas fa-file-excel"></i> Export/Import
            </a>
        </div>
    </div>
    
    <table class="data-table" id="customerTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama & Kontak</th>
                <th>Paket & Router</th>
                <th>Status</th>
                <th>PPPoE</th>
                <th>Tgl Isolir</th>
                <th>IP Address</th>
                <th>MAC Address</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;" data-label="Data">
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
                    <td data-label="Status">
                        <?php if ($c['status'] === 'active'): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Isolir</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="PPPoE">
                        <code style="background: rgba(255,255,255,0.1); padding: 2px 4px; border-radius: 4px;">
                            <?php echo htmlspecialchars($c['pppoe_username']); ?>
                        </code>
                        <?php if (isset($c['in_radius'])): ?>
                            <?php if ($c['in_radius'] === true): ?>
                                <span class="badge badge-success" style="margin-left: 5px;" title="Username terdaftar di RADIUS">
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
                        <span class="badge badge-info">Tgl <?php echo $c['isolation_date']; ?></span>
                    </td>
                    <td data-label="IP Address">
                        <?php echo htmlspecialchars($c['ip_address']); ?>
                    </td>
                    <td data-label="MAC Address">
                        <?php echo htmlspecialchars($c['mac_address']); ?>
                    </td>
                    <td data-label="Aksi" style="display: flex; gap: 5px; flex-wrap: wrap;">
                        <a href="pay_process.php?id=<?php echo $c['id']; ?>" class="btn btn-success btn-sm" title="Bayar Tagihan">
                            <i class="fas fa-money-bill-wave"></i>
                        </a>
                        <button class="btn btn-secondary btn-sm" onclick="editCustomer(<?php echo htmlspecialchars(json_encode($c)); ?>)" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" data-no-loading="true" onsubmit="return confirm('Reset password portal pelanggan ini menjadi 1234?');">
                            <input type="hidden" name="action" value="reset_portal_password">
                            <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" title="Reset Password Portal">
                                <i class="fas fa-key"></i>
                            </button>
                        </form>
                        <form method="POST" data-no-loading="true"  onsubmit="return confirm('Apakah Anda yakin ingin menghapus pelanggan ini? Data yang dihapus tidak dapat dikembalikan.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php if ($c['status'] === 'isolated'): ?>
                            <form method="POST" data-no-loading="true">
                                <input type="hidden" name="action" value="unisolate">
                                <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <button type="submit" class="btn btn-success btn-sm" title="Buka Isolir">
                                    <i class="fas fa-unlock"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" data-no-loading="true">
                                <input type="hidden" name="action" value="isolate">
                                <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <button type="submit" class="btn btn-error btn-sm" title="Isolir">
                                    <i class="fas fa-lock"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px;">
        <a href="?page=1"class="btn btn-secondary btn-sm" <?php echo $page === 1 ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-double-left"></i>
        </a>
        <a href="?page=<?php echo max(1, $page - 1); ?>" class="btn btn-secondary btn-sm" <?php echo $page === 1 ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-left"></i> 
        </a>
        
        <span style="color: var(--text-secondary);">
            Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?>
            (Total: <?php echo $totalCustomers; ?> pelanggan)
        </span>
        
        <a href="?page=<?php echo min($totalPages, $page + 1); ?>" class="btn btn-secondary btn-sm" <?php echo $page === $totalPages ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-right"></i>
        </a>
        <a href="?page=<?php echo $totalPages; ?>" class="btn btn-secondary btn-sm" <?php echo $page === $totalPages ? 'disabled style="opacity: 0.5;"' : ''; ?>>
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
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <!-- Basic Information Section -->
            <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);">
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
                        <label class="form-label">Tanggal Isolir (1-28)</label>
                        <input type="number" name="isolation_date" id="edit_isolation_date" class="form-control" min="1" max="28" required>
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
        
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'btn btn-secondary';
        item.style.display = 'block';
        item.style.width = '100%';
        item.style.textAlign = 'left';
        item.style.marginBottom = '8px';
        item.textContent = username;
        item.onclick = function() {
            const input = document.getElementById('pppoe_username_input') || document.querySelector('input[name="pppoe_username"]');
            if (input) {
                input.value = username;
            }
            closePppoeUserModal();
        };
        
        list.appendChild(item);
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
});

function initMap() {
    // Add map
    map = L.map('map-picker').setView([-6.200000, 106.816666], 13);
    
    // Base layers
    var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    });
    
    var googleSat = L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
    });

    // Add default layer
    osm.addTo(map);

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
        
        document.querySelector('input[name="lat"]').value = e.latlng.lat.toFixed(6);
        document.querySelector('input[name="lng"]').value = e.latlng.lng.toFixed(6);
    });
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
    osm.addTo(editMap);

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
document.getElementById('searchCustomer').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#customerTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

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
    document.getElementById('edit_isolation_date').value = customer.isolation_date || 20;
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
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
