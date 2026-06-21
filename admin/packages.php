<?php
/**
 * Packages Management - Elegant Dark Minimalis Theme
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Paket Internet';
$workdir = 'admin/packages.php';

// Migrasi database jika perlu
try {
    $hasProductType = fetchOne("SHOW COLUMNS FROM packages LIKE 'product_type'");
    if (!$hasProductType) {
        query("ALTER TABLE packages ADD COLUMN product_type VARCHAR(50) NOT NULL DEFAULT 'general' AFTER name");
    }
} catch (Exception $e) {
    // Ignore migration errors
}

$supportsPackageServices = false;
try {
    $hasPackageServices = fetchOne("SHOW COLUMNS FROM packages LIKE 'package_services'");
    if (!$hasPackageServices) {
        query("ALTER TABLE packages ADD COLUMN package_services TEXT NULL AFTER description");
        $hasPackageServices = fetchOne("SHOW COLUMNS FROM packages LIKE 'package_services'");
    }
    $supportsPackageServices = !empty($hasPackageServices);
} catch (Exception $e) {
    $supportsPackageServices = false;
}

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Invalid CSRF token');
        redirect('packages.php');
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $data = [
                        'name' => sanitize($_POST['name']),
                        'price' => (float)$_POST['price'],
                        'profile_normal' => sanitize($_POST['profile_normal']),
                        'profile_isolir' => sanitize($_POST['profile_isolir']),
                        'description' => sanitize($_POST['description']),
                        'created_at' => date('Y-m-d H:i:s')
                ];

                AppLog('ADD_PACKAGE_ATTEMPT', $workdir, "Mencoba menambahkan paket", json_encode($data));

                if (insert('packages', $data)) {
                    AppLog('ADD_PACKAGE_SUCCESS', $workdir, "Berhasil menambahkan paket", json_encode($data));
                    setFlash('success', 'Paket berhasil ditambahkan');
                    logActivity('ADD_PACKAGE', "Name: {$data['name']}");
                } else {
                    AppLog('ADD_PACKAGE_FAILED', $workdir, "Gagal menambahkan paket", json_encode($data));
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

                AppLog('EDIT_PACKAGE_ATTEMPT', $workdir, "Mencoba mengupdate paket", json_encode(['id' => $packageId, 'data' => $data]));

                if (update('packages', $data, 'id = ?', [$packageId])) {
                    AppLog('EDIT_PACKAGE_SUCCESS', $workdir, "Berhasil mengupdate paket", json_encode(['id' => $packageId, 'data' => $data]));
                    setFlash('success', 'Paket berhasil diperbarui');
                    logActivity('UPDATE_PACKAGE', "ID: {$packageId}");
                } else {
                    AppLog('EDIT_PACKAGE_FAILED', $workdir, "Gagal mengupdate paket", json_encode(['id' => $packageId, 'data' => $data]));
                    setFlash('error', 'Gagal memperbarui paket');
                }
                redirect('packages.php');
                break;

            case 'delete':
                $packageId = (int)$_POST['package_id'];

                AppLog('DELETE_PACKAGE_ATTEMPT', $workdir, "Mencoba menghapus paket", json_encode(['id' => $packageId]));

                $customerCount = fetchOne("SELECT COUNT(*) as total FROM customers WHERE package_id = ?", [$packageId])['total'];
                if ($customerCount > 0) {
                    AppLog('DELETE_PACKAGE_FAILED', $workdir, "Paket masih memiliki pelanggan, tidak dapat dihapus", json_encode(['id' => $packageId, 'customer_count' => $customerCount]));
                    setFlash('error', "Tidak dapat menghapus paket yang masih memiliki {$customerCount} pelanggan");
                    redirect('packages.php');
                }

                if (delete('packages', 'id = ?', [$packageId])) {
                    AppLog('DELETE_PACKAGE_SUCCESS', $workdir, "Berhasil menghapus paket", json_encode(['id' => $packageId]));
                    setFlash('success', 'Paket berhasil dihapus');
                    logActivity('DELETE_PACKAGE', "ID: {$packageId}");
                } else {
                    AppLog('DELETE_PACKAGE_FAILED', $workdir, "Gagal menghapus paket", json_encode(['id' => $packageId]));
                    setFlash('error', 'Gagal menghapus paket');
                }
                redirect('packages.php');
                break;
        }
    }
}

$packages = fetchAll("
    SELECT p.*, COUNT(c.id) as customer_count 
    FROM packages p 
    LEFT JOIN customers c ON p.id = c.package_id 
    GROUP BY p.id 
    ORDER BY p.price ASC
");

$mikrotikConnected = true;
$mikrotikProfiles = mikrotikGetProfiles();

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

ob_start();
?>

<!-- Warning Connection -->
<?php if (!mikrotikConnect()): ?>
<div class="alert alert-warning" style="margin-bottom: 24px;">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>Gagal terhubung ke MikroTik!</strong>
        <p style="margin: 4px 0 0 0; font-size: 13px;">
            Profile yang ditampilkan adalah profil default. 
            Silakan periksa pengaturan MikroTik di <a href="settings.php" style="color: var(--accent-blue);">Settings</a>.
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo count($packages); ?></h3>
            <p>Total Paket</p>
        </div>
        <div class="stat-icon blue">
            <i class="fas fa-box"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <?php 
            $totalCustomers = array_sum(array_column($packages, 'customer_count'));
            ?>
            <h3><?php echo number_format($totalCustomers); ?></h3>
            <p>Pelanggan Aktif</p>
        </div>
        <div class="stat-icon green">
            <i class="fas fa-users"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <?php 
            $mostPopularPackage = '-';
            $maxCustomers = 0;
            
            foreach ($packages as $p) {
                if ($p['customer_count'] > $maxCustomers) {
                    $maxCustomers = $p['customer_count'];
                    $mostPopularPackage = $p['name'];
                }
            }
            ?>
            <h3 style="font-size: 18px;"><?php echo htmlspecialchars($mostPopularPackage); ?></h3>
            <p>Paket Terlaris</p>
        </div>
        <div class="stat-icon purple">
            <i class="fas fa-star"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <?php 
            $activePackages = 0;
            foreach ($packages as $p) {
                if ($p['customer_count'] > 0) $activePackages++;
            }
            ?>
            <h3><?php echo $activePackages; ?></h3>
            <p>Paket Terpakai</p>
        </div>
        <div class="stat-icon orange">
            <i class="fas fa-check-double"></i>
        </div>
    </div>
</div>

<!-- Add Package Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-plus-circle"></i> Tambah Paket Baru
        </h3>
    </div>
    
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nama Paket</label>
                    <input type="text" name="name" class="form-control" required placeholder="Misal: Paket 10 Mbps">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Harga per Bulan</label>
                    <input type="number" name="price" class="form-control" required placeholder="250000">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Profile MikroTik (Normal)</label>
                    <select name="profile_normal" id="profile_normal" class="form-control" required>
                        <?php foreach ($mikrotikProfiles as $profile): ?>
                            <option value="<?php echo htmlspecialchars($profile['name']); ?>">
                                <?php echo htmlspecialchars($profile['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="profile_info_normal" class="profile-info"></div>
                    <small class="form-hint">Digunakan saat pelanggan aktif</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Profile MikroTik (Isolir)</label>
                    <select name="profile_isolir" id="profile_isolir" class="form-control" required>
                        <?php foreach ($mikrotikProfiles as $profile): ?>
                            <option value="<?php echo htmlspecialchars($profile['name']); ?>">
                                <?php echo htmlspecialchars($profile['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="profile_info_isolir" class="profile-info"></div>
                    <small class="form-hint">Digunakan saat pelanggan belum bayar</small>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Keterangan</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Keterangan tambahan (opsional)"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Paket
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Packages Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-list"></i> Daftar Paket
        </h3>
    </div>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama Paket</th>
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
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Belum ada paket terdaftar</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($packages as $pkg): ?>
                    <tr>
                        <td data-label="Nama Paket">
                            <strong><?php echo htmlspecialchars($pkg['name']); ?></strong>
                            <?php if ($pkg['description']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($pkg['description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Harga">
                            <strong class="price"><?php echo formatCurrency($pkg['price']); ?></strong>
                        </td>
                        <td data-label="Profile Normal">
                            <span class="badge badge-success"><?php echo htmlspecialchars($pkg['profile_normal']); ?></span>
                        </td>
                        <td data-label="Profile Isolir">
                            <span class="badge badge-warning"><?php echo htmlspecialchars($pkg['profile_isolir']); ?></span>
                        </td>
                        <td data-label="Pelanggan">
                            <span class="badge badge-info"><?php echo $pkg['customer_count']; ?> pelanggan</span>
                        </td>
                        <td data-label="Aksi">
                            <div class="action-buttons">
                                <button class="btn-icon" onclick="editPackage(<?php echo $pkg['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon danger" onclick="deletePackage(<?php echo $pkg['id']; ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Paket</h3>
            <button class="close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editForm" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="package_id" id="edit_package_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Paket</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Harga per Bulan</label>
                    <input type="number" name="price" id="edit_price" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Profile Normal</label>
                        <select name="profile_normal" id="edit_profile_normal" class="form-control" required>
                            <?php foreach ($mikrotikProfiles as $profile): ?>
                                <option value="<?php echo htmlspecialchars($profile['name']); ?>">
                                    <?php echo htmlspecialchars($profile['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="edit_profile_info_normal" class="profile-info"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Profile Isolir</label>
                        <select name="profile_isolir" id="edit_profile_isolir" class="form-control" required>
                            <?php foreach ($mikrotikProfiles as $profile): ?>
                                <option value="<?php echo htmlspecialchars($profile['name']); ?>">
                                    <?php echo htmlspecialchars($profile['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="edit_profile_info_isolir" class="profile-info"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Additional styles for packages page */
.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 16px;
    border-top: 1px solid var(--border-light);
    margin-top: 8px;
}

.form-hint {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: var(--text-muted);
}

.profile-info {
    margin-top: 8px;
    padding: 8px 12px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    display: none;
}

.profile-info[style*="display: block"],
.profile-info:not([style*="display: none"]) {
    background: var(--bg-tertiary);
    border-left: 3px solid var(--accent-blue);
}

.price {
    color: var(--accent-green);
    font-weight: 600;
}

.text-muted {
    color: var(--text-muted);
    font-size: 12px;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-icon {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 6px;
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
}

.btn-icon:hover {
    background: var(--bg-tertiary);
    color: var(--accent-blue);
}

.btn-icon.danger:hover {
    color: var(--accent-red);
}

.empty-state {
    text-align: center;
    padding: 48px 20px !important;
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

.alert-warning {
    background: rgba(210, 153, 34, 0.1);
    border: 1px solid rgba(210, 153, 34, 0.3);
    color: var(--accent-orange);
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius-md);
}

.alert-warning i {
    font-size: 18px;
}
</style>

<script>
const packagesData = <?php echo json_encode($packages); ?>;
const mikrotikProfiles = <?php echo json_encode($mikrotikProfiles); ?>;

function updateProfileInfo(selectId, displayId) {
    const select = document.getElementById(selectId);
    const display = document.getElementById(displayId);
    if (!select || !display) return;
    
    const profileName = select.value;
    const profile = mikrotikProfiles?.find(p => p.name === profileName);
    
    if (profile && Object.keys(profile).length > 1) {
        let html = '<div class="profile-details">';
        if (profile['rate-limit']) {
            html += `<span><i class="fas fa-tachometer-alt"></i> ${profile['rate-limit']}</span>`;
        }
        if (profile['session-timeout']) {
            html += `<span><i class="fas fa-clock"></i> ${profile['session-timeout']}</span>`;
        }
        if (profile['only-one']) {
            html += `<span><i class="fas fa-user-lock"></i> ${profile['only-one']}</span>`;
        }
        html += '</div>';
        
        if (html === '<div class="profile-details"></div>') {
            html = `<span><i class="fas fa-tag"></i> ${profile.name}</span>`;
        }
        
        display.innerHTML = html;
        display.style.display = 'block';
    } else {
        display.style.display = 'none';
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateProfileInfo('profile_normal', 'profile_info_normal');
    updateProfileInfo('profile_isolir', 'profile_info_isolir');
    
    const normalSelect = document.getElementById('profile_normal');
    const isolirSelect = document.getElementById('profile_isolir');
    
    if (normalSelect) {
        normalSelect.addEventListener('change', () => updateProfileInfo('profile_normal', 'profile_info_normal'));
    }
    if (isolirSelect) {
        isolirSelect.addEventListener('change', () => updateProfileInfo('profile_isolir', 'profile_info_isolir'));
    }
});

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
    
    updateProfileInfo('edit_profile_normal', 'edit_profile_info_normal');
    updateProfileInfo('edit_profile_isolir', 'edit_profile_info_isolir');
    
    document.getElementById('editModal').style.display = 'flex';
    
    // Add event listeners for edit modal
    document.getElementById('edit_profile_normal').addEventListener('change', () => 
        updateProfileInfo('edit_profile_normal', 'edit_profile_info_normal'));
    document.getElementById('edit_profile_isolir').addEventListener('change', () => 
        updateProfileInfo('edit_profile_isolir', 'edit_profile_info_isolir'));
}

function deletePackage(id) {
    const pkg = packagesData.find(p => p.id == id);
    if (!pkg) return;
    
    if (confirm(`Yakin ingin menghapus paket "${pkg.name}"?\n\nPelanggan yang menggunakan paket ini akan terpengaruh!`)) {
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

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('editModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEditModal();
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';