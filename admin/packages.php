<?php
/**
 * Packages Management
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Paket Internet';

$defaultAvailableServices = [
];

// Master table for service options.
try {
    query("CREATE TABLE IF NOT EXISTS available_services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_key VARCHAR(80) UNIQUE NOT NULL,
        service_name VARCHAR(150) NOT NULL,
        service_type VARCHAR(50) NOT NULL DEFAULT 'general',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $hasServiceType = fetchOne("SHOW COLUMNS FROM available_services LIKE 'service_type'");
    if (!$hasServiceType) {
        query("ALTER TABLE available_services ADD COLUMN service_type VARCHAR(50) NOT NULL DEFAULT 'general' AFTER service_name");
    }

    // Seed defaults one-time only, tracked by settings flag.
    $seedFlagKey = 'AVAILABLE_SERVICES_SEEDED';
    $seedFlag = fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$seedFlagKey]);
    $isSeeded = !empty($seedFlag) && (string) ($seedFlag['setting_value'] ?? '') === '1';

    if (!$isSeeded) {
        $seedIndex = 1;
        foreach ($defaultAvailableServices as $serviceKey => $serviceName) {
            $exists = fetchOne("SELECT id FROM available_services WHERE service_key = ?", [$serviceKey]);
            if (!$exists) {
                insert('available_services', [
                    'service_key' => $serviceKey,
                    'service_name' => $serviceName,
                    'is_active' => 1,
                    'sort_order' => $seedIndex
                ]);
            }
            $seedIndex++;
        }

    
        $flagExists = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$seedFlagKey]);
        if ($flagExists) {
            update('settings', ['setting_value' => '1'], 'setting_key = ?', [$seedFlagKey]);
        } else {
            insert('settings', ['setting_key' => $seedFlagKey, 'setting_value' => '1']);
        }
    }
} catch (Exception $e) {
    // Fallback to hardcoded services when table cannot be created/read.
}

// Lazy migration: add product_type column if it does not exist yet.
try {
    $hasProductType = fetchOne("SHOW COLUMNS FROM packages LIKE 'product_type'");
    if (!$hasProductType) {
        query("ALTER TABLE packages ADD COLUMN product_type VARCHAR(50) NOT NULL DEFAULT 'general' AFTER name");
    }
} catch (Exception $e) {
    // Ignore migration errors
}

$availableServiceRows = fetchAll("SELECT service_key, service_name, service_type FROM available_services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
$availableServices = [];
$availableServiceTypes = [];
if (!empty($availableServiceRows)) {
    foreach ($availableServiceRows as $row) {
        $k = (string) ($row['service_key'] ?? '');
        $v = (string) ($row['service_name'] ?? '');
        $t = trim((string) ($row['service_type'] ?? 'general'));
        if ($k !== '' && $v !== '') {
            $availableServices[$k] = $v;
            $availableServiceTypes[$k] = $t !== '' ? strtolower($t) : 'general';
        }
    }
}
if (empty($availableServices)) {
    $availableServices = $defaultAvailableServices;
}

$productTypeOptions = [
    'general' => 'General',
    'router' => 'Router',
    'pppoe' => 'PPPoE',
    'hotspot' => 'Hotspot',
    'voucher' => 'Voucher',
    'wifi' => 'WiFi',
    'vpn' => 'VPN'
];

foreach ($availableServiceTypes as $serviceType) {
    if ($serviceType !== '' && !isset($productTypeOptions[$serviceType])) {
        $productTypeOptions[$serviceType] = ucfirst($serviceType);
    }
}

// Lazy migration: add package_services column if it does not exist yet.
$supportsPackageServices = false;
try {
    $hasPackageServices = fetchOne("SHOW COLUMNS FROM packages LIKE 'package_services'");
    if (!$hasPackageServices) {
        query("ALTER TABLE packages ADD COLUMN package_services TEXT NULL AFTER description");
        $hasPackageServices = fetchOne("SHOW COLUMNS FROM packages LIKE 'package_services'");
    }
    $supportsPackageServices = !empty($hasPackageServices);
} catch (Exception $e) {
    // Ignore migration errors, form still works without this field.
    $supportsPackageServices = false;
}

function normalizePackageServices($input, $availableServices)
{
    $selected = [];
    if (is_array($input)) {
        foreach ($input as $key) {
            $k = trim((string) $key);
            if ($k !== '' && isset($availableServices[$k])) {
                $selected[] = $k;
            }
        }
    }
    return array_values(array_unique($selected));
}

function makeServiceKey($raw)
{
    $s = strtolower(trim((string) $raw));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    $s = trim((string) $s, '_');
    return $s;
}

function normalizeTypeKey($raw)
{
    $s = strtolower(trim((string) $raw));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    $s = trim((string) $s, '_');
    return $s !== '' ? $s : 'general';
}

function migratePackageServiceKey($oldKey, $newKey)
{
    $oldKey = trim((string) $oldKey);
    $newKey = trim((string) $newKey);
    if ($oldKey === '' || $newKey === '' || $oldKey === $newKey) {
        return;
    }

    $rows = fetchAll("SELECT id, package_services FROM packages WHERE package_services LIKE ?", ['%\"' . $oldKey . '\"%']);
    foreach ($rows as $row) {
        $pkgId = (int) ($row['id'] ?? 0);
        $raw = (string) ($row['package_services'] ?? '');
        if ($pkgId <= 0 || $raw === '') {
            continue;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            continue;
        }

        $changed = false;
        foreach ($decoded as &$item) {
            if ((string) $item === $oldKey) {
                $item = $newKey;
                $changed = true;
            }
        }
        unset($item);

        if ($changed) {
            $decoded = array_values(array_unique(array_map('strval', $decoded)));
            update('packages', ['package_services' => json_encode($decoded)], 'id = ?', [$pkgId]);
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Invalid CSRF token');
        redirect('packages.php');
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_service':
                $serviceName = trim((string) sanitize($_POST['service_name'] ?? ''));
                $serviceKeyInput = trim((string) sanitize($_POST['service_key'] ?? ''));
                $serviceKey = makeServiceKey($serviceKeyInput !== '' ? $serviceKeyInput : $serviceName);
                $serviceType = normalizeTypeKey(sanitize($_POST['service_type'] ?? 'general'));

                if ($serviceName === '' || $serviceKey === '') {
                    setFlash('error', 'Nama service wajib diisi.');
                    redirect('packages.php');
                }

                if (!preg_match('/^[a-z0-9_]{2,80}$/', $serviceKey)) {
                    setFlash('error', 'Service key tidak valid. Gunakan huruf kecil, angka, underscore.');
                    redirect('packages.php');
                }

                $exists = fetchOne("SELECT id FROM available_services WHERE service_key = ?", [$serviceKey]);
                if ($exists) {
                    setFlash('error', 'Service key sudah ada, gunakan key lain.');
                    redirect('packages.php');
                }

                $maxSort = fetchOne("SELECT COALESCE(MAX(sort_order), 0) as max_sort FROM available_services");
                $nextSort = (int) ($maxSort['max_sort'] ?? 0) + 1;

                $ok = insert('available_services', [
                    'service_key' => $serviceKey,
                    'service_name' => $serviceName,
                    'service_type' => $serviceType,
                    'is_active' => 1,
                    'sort_order' => $nextSort
                ]);

                if ($ok) {
                    setFlash('success', 'Service paket berhasil ditambahkan.');
                    logActivity('ADD_AVAILABLE_SERVICE', "Key: {$serviceKey}");
                } else {
                    setFlash('error', 'Gagal menambahkan service paket.');
                }
                redirect('packages.php');
                break;

            case 'edit_service':
                $serviceId = (int) ($_POST['service_id'] ?? 0);
                $serviceName = trim((string) sanitize($_POST['service_name'] ?? ''));
                $serviceKeyInput = trim((string) sanitize($_POST['service_key'] ?? ''));
                $serviceKey = makeServiceKey($serviceKeyInput);
                $serviceType = normalizeTypeKey(sanitize($_POST['service_type'] ?? 'general'));

                if ($serviceId <= 0 || $serviceName === '' || $serviceKey === '') {
                    setFlash('error', 'Data service tidak valid.');
                    redirect('packages.php');
                }

                if (!preg_match('/^[a-z0-9_]{2,80}$/', $serviceKey)) {
                    setFlash('error', 'Service key tidak valid. Gunakan huruf kecil, angka, underscore.');
                    redirect('packages.php');
                }

                $serviceRow = fetchOne("SELECT id, service_key, service_name FROM available_services WHERE id = ?", [$serviceId]);
                if (!$serviceRow) {
                    setFlash('error', 'Service tidak ditemukan.');
                    redirect('packages.php');
                }

                $oldKey = (string) ($serviceRow['service_key'] ?? '');
                if ($oldKey !== $serviceKey) {
                    $dup = fetchOne("SELECT id FROM available_services WHERE service_key = ? AND id <> ?", [$serviceKey, $serviceId]);
                    if ($dup) {
                        setFlash('error', 'Service key sudah digunakan service lain.');
                        redirect('packages.php');
                    }
                }

                $ok = update('available_services', [
                    'service_name' => $serviceName,
                    'service_key' => $serviceKey,
                    'service_type' => $serviceType
                ], 'id = ?', [$serviceId]);

                if ($ok) {
                    if ($supportsPackageServices && $oldKey !== $serviceKey) {
                        migratePackageServiceKey($oldKey, $serviceKey);
                    }
                    setFlash('success', 'Service berhasil diperbarui.');
                    logActivity('EDIT_AVAILABLE_SERVICE', 'ID: ' . $serviceId . ', OldKey: ' . $oldKey . ', NewKey: ' . $serviceKey);
                } else {
                    setFlash('error', 'Gagal memperbarui service.');
                }
                redirect('packages.php');
                break;

            case 'delete_service':
                $serviceId = (int) ($_POST['service_id'] ?? 0);
                if ($serviceId <= 0) {
                    setFlash('error', 'Service tidak valid.');
                    redirect('packages.php');
                }

                $serviceRow = fetchOne("SELECT service_key, service_name FROM available_services WHERE id = ?", [$serviceId]);
                if (!$serviceRow) {
                    setFlash('error', 'Service tidak ditemukan.');
                    redirect('packages.php');
                }

                $serviceKey = (string) ($serviceRow['service_key'] ?? '');
                $serviceName = (string) ($serviceRow['service_name'] ?? '');
                $usedCount = 0;
                if ($supportsPackageServices && $serviceKey !== '') {
                    $used = fetchOne("SELECT COUNT(*) as total FROM packages WHERE package_services LIKE ?", ['%\"' . $serviceKey . '\"%']);
                    $usedCount = (int) ($used['total'] ?? 0);
                }

                if ($usedCount > 0) {
                    setFlash('error', 'Service tidak bisa dihapus karena masih dipakai ' . $usedCount . ' paket.');
                    redirect('packages.php');
                }

                if (delete('available_services', 'id = ?', [$serviceId])) {
                    setFlash('success', 'Service berhasil dihapus: ' . $serviceName);
                    logActivity('DELETE_AVAILABLE_SERVICE', 'ID: ' . $serviceId . ', Key: ' . $serviceKey);
                } else {
                    setFlash('error', 'Gagal menghapus service.');
                }
                redirect('packages.php');
                break;

            case 'add':
                $data = [
                    'name' => sanitize($_POST['name']),
                    'price' => (float)$_POST['price'],
                    'profile_normal' => sanitize($_POST['profile_normal']),
                    'profile_isolir' => sanitize($_POST['profile_isolir']),
                    'description' => sanitize($_POST['description']),
                    'created_at' => date('Y-m-d H:i:s')
                ];

                
                if (insert('packages', $data)) {
                    setFlash('success', 'Paket berhasil ditambahkan');
                    logActivity('ADD_PACKAGE', "Name: {$data['name']}");
                } else {
                    setFlash('error', 'Gagal menambahkan paket');
                }
                redirect('packages.php');
                break;
                
            case 'edit':
                $packageId = (int)$_POST['package_id'];
                $data = [
                    'name' => sanitize($_POST['name']),
                    'price' => (float)$_POST['price'],
                    'profile_normal' => sanitize($_POST['profile_normal']),
                    'profile_isolir' => sanitize($_POST['profile_isolir']),
                    'description' => sanitize($_POST['description']),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                if (update('packages', $data, 'id = ?', [$packageId])) {
                    setFlash('success', 'Paket berhasil diperbarui');
                    logActivity('UPDATE_PACKAGE', "ID: {$packageId}");
                } else {
                    setFlash('error', 'Gagal memperbarui paket');
                }
                redirect('packages.php');
                break;
                
            case 'delete':
                $packageId = (int)$_POST['package_id'];
                
                // Check if package has customers
                $customerCount = fetchOne("SELECT COUNT(*) as total FROM customers WHERE package_id = ?", [$packageId])['total'];
                if ($customerCount > 0) {
                    setFlash('error', "Tidak dapat menghapus paket yang masih memiliki {$customerCount} pelanggan");
                    redirect('packages.php');
                }
                
                if (delete('packages', 'id = ?', [$packageId])) {
                    setFlash('success', 'Paket berhasil dihapus');
                    logActivity('DELETE_PACKAGE', "ID: {$packageId}");
                } else {
                    setFlash('error', 'Gagal menghapus paket');
                }
                redirect('packages.php');
                break;
        }
    }
}

// Get data
$packages = fetchAll("
    SELECT p.*, COUNT(c.id) as customer_count 
    FROM packages p 
    LEFT JOIN customers c ON p.id = c.package_id 
    GROUP BY p.id 
    ORDER BY p.name
");

// Get MikroTik profiles from actual MikroTik
$mikrotikConnected = true;
$mikrotikProfiles = mikrotikGetProfiles();

// If connection fails, use fallback profiles
if (empty($mikrotikProfiles)) {
    $mikrotikConnected = false;
    $mikrotikProfiles = [
        ['name' => 'default'],
        ['name' => '10Mbps'],
        ['name' => '20Mbps'],
        ['name' => '50Mbps'],
        ['name' => 'isolir-10Mbps'],
        ['name' => 'isolir-20Mbps'],
        ['name' => 'isolir-50Mbps']
    ];
}

if ($supportsPackageServices) {
    $allAvailableServices = fetchAll("SELECT s.id, s.service_key, s.service_name, s.service_type, s.is_active,
        (SELECT COUNT(*) FROM packages p WHERE p.package_services LIKE CONCAT('%\"', s.service_key, '\"%')) as used_count
        FROM available_services s
        ORDER BY s.sort_order ASC, s.id ASC");
} else {
    $allAvailableServices = fetchAll("SELECT s.id, s.service_key, s.service_name, s.service_type, s.is_active, 0 as used_count
        FROM available_services s
        ORDER BY s.sort_order ASC, s.id ASC");
}

ob_start();
?>

<!-- Display status connection mikrotik -->
<?php if (!mikrotikConnect()): ?>
<div style="background: rgba(255, 0, 0, 0.1); border: 1px solid #ff4444; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 10px; color: #ff6666;">
        <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
        <div>
            <strong>Gagal terhubung ke MikroTik!</strong>
            <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: #f38282;">
                Profile yang ditampilkan adalah profil default. 
                Silakan periksa pengaturan MikroTik di <a href="settings.php" style="color: #66ccff;">Settings</a> 
                untuk memastikan kredensial benar.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px;">
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($packages); ?></h3>
            <p>Total Paket</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <?php 
            $totalCustomers = array_sum(array_column($packages, 'customer_count'));
            ?>
            <h3><?php echo $totalCustomers; ?></h3>
            <p>Pelanggan Aktif</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-info">
            <?php 
            $mostPopularPackage = '-';
            $maxCustomers = 0;
            $avgPrice = 0;
            $totalPrice = 0;
            $countPrice = 0;
            
            foreach ($packages as $p) {
                if ($p['customer_count'] > $maxCustomers) {
                    $maxCustomers = $p['customer_count'];
                    $mostPopularPackage = $p['name'];
                }
                $totalPrice += $p['price'];
                $countPrice++;
            }
            
            if ($countPrice > 0) {
                $avgPrice = $totalPrice / $countPrice;
            }
            ?>
            <h3 style="font-size: 1.2rem;"><?php echo htmlspecialchars($mostPopularPackage); ?></h3>
            <p>Paket Terlaris</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-check-double"></i>
        </div>
        <div class="stat-info">
            <?php 
            $activePackages = 0;
            foreach ($packages as $p) {
                if ($p['customer_count'] > 0) {
                    $activePackages++;
                }
            }
            ?>
            <h3><?php echo $activePackages; ?></h3>
            <p>Paket Terpakai</p>
        </div>
    </div>
</div>

<style>
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

<!-- Add Service Form
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-layer-group"></i> Tambah Service Paket</h3>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="add_service">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Nama Service</label>
                <input type="text" name="service_name" class="form-control" placeholder="Contoh: Free Instalasi" required>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Service Key (opsional)</label>
                <input type="text" name="service_key" class="form-control" placeholder="contoh: free_instalasi">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Tipe Service</label>
                <select name="service_type" class="form-control">
                    <?php foreach ($productTypeOptions as $typeKey => $typeLabel): ?>
                        <option value="<?php echo htmlspecialchars($typeKey); ?>"><?php echo htmlspecialchars($typeLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-plus"></i> Tambah Service</button>
            </div>
        </div>
    </form>

    <?php if (!empty($allAvailableServices)): ?>
        <div style="margin-top: 14px;">
            <strong style="color: var(--text-primary);">Daftar Service:</strong>
            <div style="overflow-x: auto; margin-top: 8px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama Service</th>
                            <th>Service Key</th>
                            <th>Tipe</th>
                            <th>Dipakai Paket</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allAvailableServices as $svc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($svc['service_name']); ?></td>
                                <td><code><?php echo htmlspecialchars($svc['service_key']); ?></code></td>
                                <td><?php echo htmlspecialchars($svc['service_type'] ?? 'general'); ?></td>
                                <td><?php echo (int) ($svc['used_count'] ?? 0); ?></td>
                                <td>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openEditServiceModal(<?php echo (int) $svc['id']; ?>, '<?php echo htmlspecialchars($svc['service_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($svc['service_key'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($svc['service_type'] ?? 'general', ENT_QUOTES); ?>')" title="Edit Service">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="deleteService(<?php echo (int) $svc['id']; ?>, '<?php echo htmlspecialchars($svc['service_name'], ENT_QUOTES); ?>', <?php echo (int) ($svc['used_count'] ?? 0); ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div> -->

<!-- Edit Service Modal -->
<div id="editServiceModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2001; overflow-y: auto; padding: 40px 0;">
    <div class="card" style="width: 520px; max-width: 90%; margin: 0 auto; position: relative;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-pen"></i> Edit Service Paket</h3>
            <button onclick="closeEditServiceModal()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="edit_service">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="service_id" id="edit_service_id">

            <div class="form-group">
                <label class="form-label">Nama Service</label>
                <input type="text" name="service_name" id="edit_service_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Service Key</label>
                <input type="text" name="service_key" id="edit_service_key" class="form-control" required>
                <small style="color: var(--text-muted);">Jika key diganti, relasi key lama di paket akan otomatis dimigrasi.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Tipe Service</label>
                <select name="service_type" id="edit_service_type" class="form-control">
                    <?php foreach ($productTypeOptions as $typeKey => $typeLabel): ?>
                        <option value="<?php echo htmlspecialchars($typeKey); ?>"><?php echo htmlspecialchars($typeLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 12px;">
                <button type="button" class="btn btn-secondary" onclick="closeEditServiceModal()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Package Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle"></i> Tambah Paket Baru</h3>
    </div>
    
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
            <div class="form-group">
                <label class="form-label">Nama Paket</label>
                <input type="text" name="name" class="form-control" required placeholder="Misal: Paket 10 Mbps">
            </div>
            
            <div class="form-group">
                <label class="form-label">Harga per Bulan</label>
                <input type="number" name="price" class="form-control" required placeholder="250000">
            </div>

            <div class="form-group">
                <label class="form-label">Profile MikroTik (Normal)</label>
                <select name="profile_normal" id="profile_normal" class="form-control" required style="color: var(--text-primary); background: var(--bg-card);">
                    <?php foreach ($mikrotikProfiles as $profile): ?>
                        <option value="<?php echo htmlspecialchars($profile['name']); ?>">
                            <?php echo htmlspecialchars($profile['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="profile_info_normal" class="profile-info-display" style="margin-top: 8px; font-size: 0.85rem; padding: 8px; border-radius: 6px; background: rgba(0,255,255,0.05); border: 1px dashed rgba(0,255,255,0.2); display: none;"></div>
                <small style="color: var(--text-muted);">Profile saat pelanggan aktif</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Profile MikroTik (Isolir)</label>
                <select name="profile_isolir" id="profile_isolir" class="form-control" required style="color: var(--text-primary); background: var(--bg-card);">
                    <?php foreach ($mikrotikProfiles as $profile): ?>
                        <option value="<?php echo htmlspecialchars($profile['name']); ?>">
                            <?php echo htmlspecialchars($profile['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="profile_info_isolir" class="profile-info-display" style="margin-top: 8px; font-size: 0.85rem; padding: 8px; border-radius: 6px; background: rgba(255,150,0,0.05); border: 1px dashed rgba(255,150,0,0.2); display: none;"></div>
                <small style="color: var(--text-muted);">Profile saat pelanggan belum bayar</small>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Keterangan</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Keterangan tambahan (opsional)"></textarea>
        </div>

        <!-- <div class="form-group">
            <label class="form-label">Daftar Service Paket</label>
            <div class="service-checklist">
                <?php foreach ($availableServices as $serviceKey => $serviceLabel): ?>
                    <label class="service-item">
                        <input type="checkbox" name="services[]" value="<?php echo htmlspecialchars($serviceKey); ?>">
                        <span><?php echo htmlspecialchars($serviceLabel); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <small style="color: var(--text-muted);">Centang service yang tersedia pada paket ini. Service tidak dicentang akan line-through, kecuali tipenya sama dengan tipe produk paket (otomatis di-hide di landing).</small>
        </div> -->
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Simpan Paket
        </button>
    </form>
</div>

<!-- Packages Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list"></i> Daftar Paket</h3>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Nama Paket</th>
                <th>Tipe</th>
                <th>Harga</th>
                <th>Profile Normal</th>
                <th>Profile Isolir</th>
                <th>Pelanggan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($packages)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                        Belum ada paket terdaftar
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($packages as $pkg): ?>
                <tr>
                    <td data-label="Nama Paket">
                        <strong><?php echo htmlspecialchars($pkg['name']); ?></strong>
                        <?php if ($pkg['description']): ?>
                            <br><small style="color: var(--text-muted);"><?php echo htmlspecialchars($pkg['description']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td data-label="Tipe"><span class="badge badge-info"><?php echo htmlspecialchars($pkg['product_type'] ?? 'general'); ?></span></td>
                    <td data-label="Harga">
                        <strong style="color: var(--neon-green);">
                            <?php echo formatCurrency($pkg['price']); ?>
                        </strong>
                    </td>
                    <td data-label="Profile Normal">
                        <span class="badge badge-success"><?php echo htmlspecialchars($pkg['profile_normal']); ?></span>
                    </td>
                    <td data-label="Profile Isolir">
                        <span class="badge badge-warning"><?php echo htmlspecialchars($pkg['profile_isolir']); ?></span>
                    </td>
                    <td data-label="Jumlah Pelanggan"><?php echo $pkg['customer_count']; ?> pelanggan</td>
                    <td data-label="Aksi">
                        <button class="btn btn-secondary btn-sm" onclick="editPackage(<?php echo $pkg['id']; ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="deletePackage(<?php echo $pkg['id']; ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Edit Package Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000; overflow-y: auto; padding: 40px 0;">
    <div class="card" style="width: 500px; max-width: 90%; margin: 0 auto; position: relative;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-edit"></i> Edit Paket</h3>
            <button onclick="closeEditModal()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="editForm" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="package_id" id="edit_package_id">
            
            <div class="form-group">
                <label class="form-label">Nama Paket</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Harga per Bulan</label>
                <input type="number" name="price" id="edit_price" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label class="form-label">Profile Normal</label>
                    <select name="profile_normal" id="edit_profile_normal" class="form-control" required style="color: var(--text-primary); background: var(--bg-card);">
                        <?php foreach ($mikrotikProfiles as $profile): ?>
                            <option value="<?php echo htmlspecialchars($profile['name']); ?>">
                                <?php echo htmlspecialchars($profile['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="edit_profile_info_normal" class="profile-info-display" style="margin-top: 8px; font-size: 0.85rem; padding: 8px; border-radius: 6px; background: rgba(0,255,255,0.05); border: 1px dashed rgba(0,255,255,0.2); display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Profile Isolir</label>
                    <select name="profile_isolir" id="edit_profile_isolir" class="form-control" required style="color: var(--text-primary); background: var(--bg-card);">
                        <?php foreach ($mikrotikProfiles as $profile): ?>
                            <option value="<?php echo htmlspecialchars($profile['name']); ?>">
                                <?php echo htmlspecialchars($profile['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="edit_profile_info_isolir" class="profile-info-display" style="margin-top: 8px; font-size: 0.85rem; padding: 8px; border-radius: 6px; background: rgba(255,150,0,0.05); border: 1px dashed rgba(255,150,0,0.2); display: none;"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Keterangan</label>
                <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Daftar Service Paket</label>
                <div class="service-checklist" id="edit_service_checklist">
                    <?php foreach ($availableServices as $serviceKey => $serviceLabel): ?>
                        <label class="service-item">
                            <input type="checkbox" name="services[]" value="<?php echo htmlspecialchars($serviceKey); ?>">
                            <span><?php echo htmlspecialchars($serviceLabel); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
const packagesData = <?php echo json_encode($packages); ?>;
const mikrotikProfiles = <?php echo json_encode($mikrotikProfiles); ?>;

function parseServices(value) {
    if (!value) return [];
    if (Array.isArray(value)) return value;
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
}

function updateProfileInfo(selectId, displayId) {
    const select = document.getElementById(selectId);
    const display = document.getElementById(displayId);
    const profileName = select.value;
    
    if (!profileName || !mikrotikProfiles) {
        display.style.display = 'none';
        return;
    }
    
    // Find profile in our list
    const profile = mikrotikProfiles.find(p => p.name === profileName);
    
    if (profile) {
        let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px;">';
        
        // Key fields to display
        if (profile['rate-limit']) {
            html += `<div><strong><i class="fas fa-tachometer-alt"></i> Limit:</strong> ${profile['rate-limit']}</div>`;
        }
        if (profile['local-address']) {
            html += `<div><strong><i class="fas fa-server"></i> Local:</strong> ${profile['local-address']}</div>`;
        }
        if (profile['remote-address']) {
            html += `<div><strong><i class="fas fa-globe"></i> Remote Pool:</strong> ${profile['remote-address']}</div>`;
        }
        if (profile['session-timeout']) {
            html += `<div><strong><i class="fas fa-clock"></i> Timeout:</strong> ${profile['session-timeout']}</div>`;
        }
        if (profile['only-one']) {
            html += `<div><strong><i class="fas fa-user-lock"></i> Only One:</strong> ${profile['only-one']}</div>`;
        }
        
        html += '</div>';
        
        // If no key fields found, show "General profile info"
        if (html === '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px;"></div>') {
            html = `<div><i class="fas fa-info-circle"></i> Profile: <strong>${profile.name}</strong></div>`;
        }
        
        display.innerHTML = html;
        display.style.display = 'block';
    } else {
        display.style.display = 'none';
    }
}

// Initial update for Add form
document.addEventListener('DOMContentLoaded', function() {
    updateProfileInfo('profile_normal', 'profile_info_normal');
    updateProfileInfo('profile_isolir', 'profile_info_isolir');
});

// Event listeners for Add form
document.getElementById('profile_normal').addEventListener('change', () => updateProfileInfo('profile_normal', 'profile_info_normal'));
document.getElementById('profile_isolir').addEventListener('change', () => updateProfileInfo('profile_isolir', 'profile_info_isolir'));

// Event listeners for Edit modal
document.getElementById('edit_profile_normal').addEventListener('change', () => updateProfileInfo('edit_profile_normal', 'edit_profile_info_normal'));
document.getElementById('edit_profile_isolir').addEventListener('change', () => updateProfileInfo('edit_profile_isolir', 'edit_profile_info_isolir'));

function editPackage(id) {
    const pkg = packagesData.find(p => p.id == id);
    if (!pkg) {
        alert('Paket tidak ditemukan!');
        return;
    }
    
    document.getElementById('edit_package_id').value = pkg.id;
    document.getElementById('edit_name').value = pkg.name || '';
    document.getElementById('edit_price').value = pkg.price || '';
    document.getElementById('edit_profile_normal').value = pkg.profile_normal || '';
    document.getElementById('edit_profile_isolir').value = pkg.profile_isolir || '';
    document.getElementById('edit_description').value = pkg.description || '';

    const activeServices = parseServices(pkg.package_services);
    document.querySelectorAll('#edit_service_checklist input[type="checkbox"]').forEach(cb => {
        cb.checked = activeServices.includes(cb.value);
    });
    
    // Update profile info in modal
    updateProfileInfo('edit_profile_normal', 'edit_profile_info_normal');
    updateProfileInfo('edit_profile_isolir', 'edit_profile_info_isolir');
    
    document.getElementById('editForm').action = 'packages.php';
    document.getElementById('editModal').style.display = 'flex';
}

function deletePackage(id) {
    const pkg = packagesData.find(p => p.id == id);
    if (!pkg) return;
    
    if (confirm('Yakin ingin menghapus paket "' + pkg.name + '"?\n\nPelanggan yang menggunakan paket ini akan terpengaruh!')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="package_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteService(id, name, usedCount) {
    if (usedCount > 0) {
        alert('Service "' + name + '" masih dipakai ' + usedCount + ' paket, tidak bisa dihapus.');
        return;
    }

    if (confirm('Yakin ingin menghapus service "' + name + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_service">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="service_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function openEditServiceModal(id, name, key, type) {
    document.getElementById('edit_service_id').value = id;
    document.getElementById('edit_service_name').value = name || '';
    document.getElementById('edit_service_key').value = key || '';
    document.getElementById('edit_service_type').value = (type || 'general');
    document.getElementById('editServiceModal').style.display = 'flex';
}

function closeEditServiceModal() {
    document.getElementById('editServiceModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

document.getElementById('editServiceModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditServiceModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
        closeEditServiceModal();
    }
});
</script>

<style>
.service-checklist {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 8px 12px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 12px;
}

.service-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-primary);
    font-size: 0.92rem;
}

.service-item input[type="checkbox"] {
    accent-color: #3da8ff;
}
</style>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
