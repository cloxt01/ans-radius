<?php
/**
 * PPPoE Profile Management - Elegant Dark Minimalis Theme
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'PPPoE Profiles';
$workdir = 'admin/pppoe-profile.php';

function pppoeProfileBuildPayloadFromPost()
{
    $name = sanitize($_POST['name'] ?? '');
    $rate = sanitize($_POST['rate_limit'] ?? '');
    $local = sanitize($_POST['local_address'] ?? '');
    $profile = sanitize($_POST['profile'] ?? '');
    $pool = sanitize($_POST['remote_pool'] ?? 'none');
    $dns = sanitize($_POST['dns_server'] ?? '');

    $payload = [
        'name' => $name,
        'rate-limit' => $rate,
        'profile' => $profile,
        'local-address' => $local,
        'remote-address' => $pool,
        'dns-server' => $dns,
    ];

    return $payload;
}

function pppoeProfileResolveIdFromPost()
{
    $id = sanitize($_POST['id'] ?? '');
    if ($id !== '') {
        return $id;
    }

    return sanitize($_POST['name'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Log aksi yang diterima (untuk tracing umum)
    actionLog('PPPOE_PROFILE_ACTION_RECEIVED', $workdir, "Menerima aksi PPPoE Profile", json_encode(['action' => $action]));

    switch ($action) {
        case 'add':
        case 'edit':
            $data = pppoeProfileBuildPayloadFromPost();
            $name = $data['name'] ?? '';

            if ($name === '') {
                actionLog('PPPOE_PROFILE_VALIDATION_FAILED', $workdir, "Nama profile kosong", json_encode(['action' => $action]));
                setFlash('error', 'Nama profile wajib diisi.');
                redirect('pppoe-profile.php');
            }

            if ($action === 'add') {
                actionLog('PPPOE_PROFILE_ADD_ATTEMPT', $workdir, "Mencoba menambahkan profile PPPoE", json_encode($data));

                $ok = radiusUpsertPppoeProfile(null, $data);
                if ($ok) {
                    actionLog('PPPOE_PROFILE_ADD_SUCCESS', $workdir, "Berhasil menambahkan profile PPPoE", json_encode(['name' => $name, 'data' => $data]));
                    setFlash('success', "Profile {$name} berhasil ditambahkan.");
                } else {
                    actionLog('PPPOE_PROFILE_ADD_FAILED', $workdir, "Gagal menambahkan profile PPPoE", json_encode(['name' => $name, 'data' => $data]));
                    setFlash('error', 'Gagal menambahkan profile (pastikan mikrotik terhubung dan konfigurasi benar).');
                }
                redirect('pppoe-profile.php');
            }

            // Edit
            $id = pppoeProfileResolveIdFromPost();
            if ($id === '') {
                actionLog('PPPOE_PROFILE_EDIT_FAILED', $workdir, "ID profile tidak valid untuk edit", json_encode(['id' => $id, 'name' => $name]));
                setFlash('error', 'ID profile tidak valid.');
                redirect('pppoe-profile.php');
            }

            actionLog('PPPOE_PROFILE_EDIT_ATTEMPT', $workdir, "Mencoba mengupdate profile PPPoE", json_encode(['id' => $id, 'data' => $data]));

            $ok = radiusUpsertPppoeProfile($id, $data);
            if ($ok) {
                actionLog('PPPOE_PROFILE_EDIT_SUCCESS', $workdir, "Berhasil mengupdate profile PPPoE", json_encode(['id' => $id, 'name' => $name, 'data' => $data]));
                setFlash('success', "Profile {$name} berhasil diperbarui.");
            } else {
                actionLog('PPPOE_PROFILE_EDIT_FAILED', $workdir, "Gagal mengupdate profile PPPoE", json_encode(['id' => $id, 'name' => $name, 'data' => $data]));
                setFlash('error', 'Gagal memperbarui profile (pastikan mikrotik terhubung dan konfigurasi benar).');
            }
            redirect('pppoe-profile.php');
            break;

        case 'delete':
            $id = pppoeProfileResolveIdFromPost();
            if ($id === '') {
                actionLog('PPPOE_PROFILE_DELETE_FAILED', $workdir, "ID profile tidak valid untuk hapus", json_encode(['id' => $id]));
                setFlash('error', 'ID profile tidak valid.');
                redirect('pppoe-profile.php');
            }

            actionLog('PPPOE_PROFILE_DELETE_ATTEMPT', $workdir, "Mencoba menghapus profile PPPoE", json_encode(['id' => $id]));

            $ok = ($id !== '') ? radiusDeletePppoeProfile($id) : false;
            if ($ok) {
                actionLog('PPPOE_PROFILE_DELETE_SUCCESS', $workdir, "Berhasil menghapus profile PPPoE", json_encode(['id' => $id]));
                setFlash('success', 'Profile berhasil dihapus.');
            } else {
                actionLog('PPPOE_PROFILE_DELETE_FAILED', $workdir, "Gagal menghapus profile PPPoE", json_encode(['id' => $id]));
                setFlash('error', 'Gagal menghapus profile.');
            }
            redirect('pppoe-profile.php');
            break;

        default:
            if ($action !== '') {
                actionLog('PPPOE_PROFILE_UNKNOWN_ACTION', $workdir, "Aksi tidak dikenali", json_encode(['action' => $action]));
                setFlash('error', 'Aksi tidak dikenali.');
            } else {
                actionLog('PPPOE_PROFILE_NO_ACTION', $workdir, "Tidak ada aksi yang dikirim", json_encode([]));
                // optional: no flash if empty action, but maybe set a general error?
                // Could set flash if needed, but we follow original logic (only flash for unknown action)
            }
            redirect('pppoe-profile.php');
            break;
    }
}

$profilesRadius = function_exists('pppoeGetProfiles') ? pppoeGetProfiles() : radiusGetPppoeProfiles();
$addressPools = mikrotikGetAddressPools();
$isMikrotikConnected = mikrotikConnect();
$profilesMikrotik = mikrotikGetProfiles($isMikrotikConnected ? getMikrotikConnection() : null);

ob_start();
?>

<!-- Warning Connection -->
<?php if (!$isMikrotikConnected): ?>
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
            <h3><?php echo count($profilesRadius); ?></h3>
            <p>Total Profile</p>
        </div>
        <div class="stat-icon blue">
            <i class="fas fa-id-card"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo count($profilesMikrotik); ?></h3>
            <p>PPP Profile</p>
        </div>
        <div class="stat-icon purple">
            <i class="fas fa-tachometer-alt"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo count($addressPools); ?></h3>
            <p>Address Pool</p>
        </div>
        <div class="stat-icon green">
            <i class="fas fa-network-wired"></i>
        </div>
    </div>
</div>

<!-- Add/Edit Profile Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-plus-circle"></i> Tambah / Edit Profile
        </h3>
    </div>
    <div class="card-body">
        <form method="POST" id="profileForm">
            <input type="hidden" name="action" value="add" id="formAction">
            <input type="hidden" name="id" id="profileId">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tag"></i> Nama Profile
                    </label>
                    <input type="text" name="name" id="pName" class="form-control" 
                           placeholder="Contoh: Premium-10M" required>
                    <small class="form-hint">Nama unik untuk profile ini</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tachometer-alt"></i> Rate Limit
                    </label>
                    <input type="text" name="rate_limit" id="pRate" class="form-control" 
                           placeholder="10M/10M atau 20M/20M">
                    <small class="form-hint">Upload/Download dalam format (Mbps)</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-server"></i> PPP Profile
                    </label>
                    <select name="profile" id="pppProfile" class="form-control">
                        <option value="none">-- Pilih PPP Profile --</option>
                        <?php foreach ($profilesMikrotik as $profile): ?>
                            <option value="<?php echo htmlspecialchars($profile['name']); ?>">
                                <?php echo htmlspecialchars($profile['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Profile PPP dari MikroTik</small>
                </div>
                
<!--                <div class="form-group">-->
<!--                    <label class="form-label">-->
<!--                        <i class="fas fa-globe"></i> DNS Server-->
<!--                    </label>-->
<!--                    <input type="text" name="dns_server" id="pDns" class="form-control" -->
<!--                           placeholder="8.8.8.8, 1.1.1.1">-->
<!--                    <small class="form-hint">DNS server (pisah dengan koma)</small>-->
<!--                </div>-->
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?php echo !$isMikrotikConnected ? 'disabled' : ''; ?>>
                    <i class="fas fa-save"></i> Simpan Profile
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                    <i class="fas fa-undo-alt"></i> Reset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Profiles Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-list"></i> Daftar PPPoE Profile
        </h3>
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="searchProfile" class="form-control" placeholder="Cari profile...">
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="data-table" id="profilesTable">
            <thead>
                <tr>
                    <th>Nama Profile</th>
                    <th>Rate Limit</th>
                    <th>PPP Profile</th>
                    <th>DNS Server</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($profilesRadius)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Belum ada profile PPPoE</p>
                            <small>Tambahkan profile menggunakan form di atas</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($profilesRadius as $p): ?>
                        <tr>
                            <td data-label="Nama Profile">
                                <div class="profile-info">
                                    <div class="profile-avatar">
                                        <?php echo strtoupper(substr($p['name'] ?? 'P', 0, 1)); ?>
                                    </div>
                                    <div class="profile-details">
                                        <strong><?php echo htmlspecialchars($p['name'] ?? '-'); ?></strong>
                                        <?php if (!empty($p['local-address'])): ?>
                                            <small><i class="fas fa-ip-address"></i> <?php echo htmlspecialchars($p['local-address']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Rate Limit">
                                <?php if (!empty($p['rate-limit'])): ?>
                                    <span class="badge badge-info">
                                        <i class="fas fa-tachometer-alt"></i> <?php echo htmlspecialchars($p['rate-limit']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-muted">Tidak terbatas</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="PPP Profile">
                                <span class="badge badge-purple">
                                    <?php echo htmlspecialchars($p['profile'] ?? 'default'); ?>
                                </span>
                            </td>
                            <td data-label="DNS Server">
                                <?php if (!empty($p['dns-server'])): ?>
                                    <code class="dns-value"><?php echo htmlspecialchars($p['dns-server']); ?></code>
                                <?php else: ?>
                                    <span class="text-muted">Default</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Aksi">
                                <div class="action-buttons">
                                    <button onclick='editProfile(<?php echo json_encode($p); ?>)'
                                        class="btn-icon" title="Edit Profile">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <form method="POST" class="inline-form" onsubmit="return confirmDelete('<?php echo htmlspecialchars($p['name'] ?? ''); ?>')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($p['.id'] ?? ($p['name'] ?? '')); ?>">
                                        <input type="hidden" name="name" value="<?php echo htmlspecialchars($p['name'] ?? ''); ?>">
                                        <button type="submit" class="btn-icon danger" title="Hapus Profile" <?php echo !$isMikrotikConnected ? 'disabled' : ''; ?>>
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* Additional styles for PPPoE profiles page */
.profile-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.profile-avatar {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, var(--accent-purple), var(--accent-blue));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    color: white;
}

.profile-details {
    display: flex;
    flex-direction: column;
}

.profile-details strong {
    font-size: 14px;
}

.profile-details small {
    font-size: 11px;
    color: var(--text-muted);
}

.profile-details small i {
    margin-right: 4px;
    font-size: 10px;
}

.badge-purple {
    background: rgba(188, 140, 255, 0.15);
    color: var(--accent-purple);
    border: 1px solid rgba(188, 140, 255, 0.3);
}

.dns-value {
    font-family: monospace;
    font-size: 12px;
    background: var(--bg-tertiary);
    padding: 4px 8px;
    border-radius: 4px;
    color: var(--accent-blue);
}

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

.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.inline-form {
    display: inline;
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

.btn-icon:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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

.empty-state small {
    font-size: 12px;
}

.text-muted {
    color: var(--text-muted);
    font-size: 12px;
}

@media (max-width: 768px) {
    .profile-info {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .search-wrapper {
        width: 100%;
        margin-top: 12px;
    }
    
    .search-wrapper .form-control {
        width: 100%;
    }
    
    .action-buttons {
        justify-content: flex-start;
    }
}
</style>

<script>
function editProfile(p) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('profileId').value = p['.id'] || '';
    document.getElementById('pName').value = p['name'] || '';
    document.getElementById('pppProfile').value = p['profile'] || 'none';
    document.getElementById('pRate').value = p['rate-limit'] || '';
    document.getElementById('pDns').value = p['dns-server'] || '';
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Highlight form
    const form = document.getElementById('profileForm');
    form.style.transition = 'all 0.3s ease';
    form.style.boxShadow = '0 0 0 2px var(--accent-blue)';
    setTimeout(() => {
        form.style.boxShadow = '';
    }, 1000);
}

function resetForm() {
    document.getElementById('profileForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('profileId').value = '';
    document.getElementById('pppProfile').value = 'none';
}

function confirmDelete(profileName) {
    return confirm(`Hapus profile "${profileName}"?\n\nTindakan ini tidak dapat dibatalkan!`);
}

// Search functionality
document.getElementById('searchProfile')?.addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#profilesTable tbody tr');
    
    rows.forEach(row => {
        if (row.querySelector('.empty-state')) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Initialize form hints
document.addEventListener('DOMContentLoaded', function() {
    // Add placeholder formatting hints
    const rateInput = document.getElementById('pRate');
    if (rateInput) {
        rateInput.addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase();
            if (value && !value.includes('M') && !value.includes('K')) {
                // Auto-suggest format
                if (value.match(/^\d+$/)) {
                    e.target.value = value + 'M/' + value + 'M';
                }
            }
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';