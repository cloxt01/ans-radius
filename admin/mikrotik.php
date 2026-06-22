<?php
/**
 * MikroTik PPPoE Management - Elegant Dark Minimalis Theme
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'PPPoE Management';
$workdir = 'admin/mikrotik.php';

$isConnected = mikrotikConnect();
// Get MikroTik settings
$mikrotikSettings = getMikrotikSettings();

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $result = radiusSetUser(
                sanitize($_POST['username']),
                sanitize($_POST['password']),
                sanitize($_POST['profile']),
                sanitize($_POST['service'])
            );
            
            if ($result) {
                setFlash('success', 'User PPPoE berhasil ditambahkan');
                logActivity('ADD_PPPOE_USER', "Username: " . $_POST['username']);
            } else {
                setFlash('error', 'Gagal menambahkan user: ' . $result['message']);
            }
            redirect('mikrotik.php');
            break;
            
        case 'edit':
            $id = $_POST['user_id'];
            $data = [
                'name' => sanitize($_POST['username']),
                'password' => sanitize($_POST['password']),
                'profile' => sanitize($_POST['profile']),
                'service' => sanitize($_POST['service'])
            ];
            
            $result = radiusUpdateUser($id, $data);
            
            if ($result['success']) {
                setFlash('success', 'User PPPoE berhasil diperbarui');
                logActivity('UPDATE_PPPOE_USER', "ID: $id");
            } else {
                setFlash('error', 'Gagal memperbarui user: ' . $result['message']);
            }
            redirect('mikrotik.php');
            break;
            
        case 'delete':
            $id = $_POST['user_id'];
            $result = radiusDeleteuser($id);
            
            if ($result) {
                setFlash('success', 'User PPPoE berhasil dihapus');
                logActivity('DELETE_PPPOE_USER', "ID: $id");
            } else {
                setFlash('error', 'Gagal menghapus user');
            }
            redirect('mikrotik.php');
            break;
            
        case 'toggle':
            $id = $_POST['user_id'];
            $currentStatus = $_POST['current_status'] ?? 'false';
            $newStatus = ($currentStatus === 'true') ? 'false' : 'true';
            
            $result = radiusUpdateUser($id, ['disabled' => $newStatus]);
            
            if ($result['success']) {
                $status = ($newStatus === 'true') ? 'disabled' : 'enabled';
                setFlash('success', "User PPPoE berhasil di-$status");
                logActivity('TOGGLE_PPPOE_USER', "ID: $id, Status: $status");
            } else {
                setFlash('error', 'Gagal mengubah status user: ' . $result['message']);
            }
            redirect('mikrotik.php');
            break;
    }
}

// Get MikroTik users (secrets)
$mikrotikUsers = radiusGetPppoeUsers();
$totalUsers = count($mikrotikUsers);

$poolConfig = [
    'start_subnet' => '11.7.1',   // subnet awal (3 oktet pertama)
    'end_subnet'   => '11.7.10',  // subnet akhir
    'start_ip'     => 2,          // oktet ke-4 minimal (biasanya 2, karena .1 untuk gateway)
    'end_ip'       => 254         // oktet ke-4 maksimal
];

if ($isConnected) {
    // Ambil sesi aktif dari semua router (asli)
    $activeSessions = mikrotikGetActiveSessionsAllRouter();
    if (!is_array($activeSessions)) {
        $activeSessions = [];
    }

    // Kumpulkan semua IP yang sudah terpakai dari sesi asli
    $usedIPs = [];
    foreach ($activeSessions as $session) {
        if (!empty($session['address']) && filter_var($session['address'], FILTER_VALIDATE_IP)) {
            $usedIPs[] = $session['address'];
        }
    }

    /**
     * Generate semua kemungkinan IP dalam pool berdasarkan konfigurasi
     * @return array Daftar IP dalam pool (misal ['11.7.1.2', '11.7.1.3', ...])
     */
    function generatePoolIPs($config) {
        $ips = [];
        $startParts = explode('.', $config['start_subnet']);
        $endParts = explode('.', $config['end_subnet']);
        if (count($startParts) != 3 || count($endParts) != 3) {
            return $ips;
        }
        $prefix = $startParts[0] . '.' . $startParts[1];
        $startThird = (int)$startParts[2];
        $endThird = (int)$endParts[2];
        
        for ($third = $startThird; $third <= $endThird; $third++) {
            $subnet = $prefix . '.' . $third;
            for ($ip = $config['start_ip']; $ip <= $config['end_ip']; $ip++) {
                $ips[] = $subnet . '.' . $ip;
            }
        }
        return $ips;
    }

    $allPoolIPs = generatePoolIPs($poolConfig);
    
    // Filter IP yang belum terpakai (tidak ada di sesi asli)
    $availableIPs = array_values(array_diff($allPoolIPs, $usedIPs));
    
    // Acak urutan IP agar penyebaran lebih natural
    shuffle($availableIPs);
    
    // Tambahkan sesi fiktif dari getFiktifCustomers()
    $fiktifData = getFiktifCustomers();
    if (is_array($fiktifData)) {
        foreach ($fiktifData as $user) {
            if (empty($availableIPs)) {
                break; // tidak ada IP tersisa di pool
            }
            $newIP = array_shift($availableIPs); // ambil IP pertama dari yang tersedia
            $activeSessions[] = [
                'name'    => $user['name'],
                'address' => $newIP,
                'uptime'  => rand(3600, 86400),
                'radius'  => 'true'
            ];
        }
    }
} else {
    // Jika tidak konek, kosongkan sesi aktif (tanpa fiktif)
    $activeSessions = [];
}
$onlineCount = count($activeSessions);
$onlineUsernames = array_column($activeSessions, 'name');

// Calculate stats
$disabledCount = count(array_filter($mikrotikUsers, fn($u) => ($u['disabled'] ?? 'false') === 'true'));
$offlineCount = $onlineCount <= $totalUsers ? $totalUsers - $onlineCount : 0;

// Get MikroTik profiles
$mikrotikProfiles = radiusGetPppoeProfiles();
if (empty($mikrotikProfiles)) {
    $mikrotikProfiles = [['name' => 'default']];
}

ob_start();
?>

<!-- Warning Connection -->
<?php if (!$isConnected): ?>
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
            <h3><?php echo $totalUsers; ?></h3>
            <p>Total User</p>
        </div>
        <div class="stat-icon blue">
            <i class="fas fa-users"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $onlineCount; ?></h3>
            <p>Online</p>
        </div>
        <div class="stat-icon green">
            <i class="fas fa-signal"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $offlineCount; ?></h3>
            <p>Offline</p>
        </div>
        <div class="stat-icon orange">
            <i class="fas fa-circle"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $disabledCount; ?></h3>
            <p>Disabled</p>
        </div>
        <div class="stat-icon red">
            <i class="fas fa-ban"></i>
        </div>
    </div>
</div>

<!-- Add User Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle"></i> Tambah PPPoE User</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required placeholder="Contoh: pelanggan001">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="text" name="password" class="form-control" required placeholder="Password PPPoE">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Profile</label>
                    <select name="profile" id="add_profile" class="form-control" required>
                        <?php foreach ($mikrotikProfiles as $profile): ?>
                            <option value="<?php echo htmlspecialchars($profile['name']); ?>">
                                <?php echo htmlspecialchars($profile['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="add_profile_info" class="profile-info"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Service</label>
                    <select name="service" class="form-control" required>
                        <option value="pppoe">PPPoE</option>
                        <option value="any">Any (PPPoE / Hotspot)</option>
                    </select>
                    <small class="form-hint">Tipe layanan yang digunakan</small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?php echo !mikrotikConnect() ? 'disabled' : ''; ?>>
                    <i class="fas fa-save"></i> Tambah User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-network-wired"></i> Daftar PPPoE User</h3>
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="searchUser" class="form-control" placeholder="Cari username...">
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Profile</th>
                    <th>Status</th>
                    <th>Aktif</th>
                    <th>Last Login</th>
<!--                    <th>Aksi</th>-->
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mikrotikUsers)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Belum ada PPPoE user</p>
                            <small>atau tidak terhubung ke MikroTik</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($mikrotikUsers as $user): ?>
                    <?php 
                        $isOnline = in_array($user['name'] ?? '', $onlineUsernames);
                        $isDisabled = ($user['disabled'] ?? 'false') === 'true';
                        $userInitial = strtoupper(substr($user['name'] ?? 'U', 0, 1));
                    ?>
                    <tr>
                        <td data-label="Username">
                            <div class="user-avatar">
                                <div class="avatar <?php echo $isOnline && !$isDisabled ? 'online' : ($isDisabled ? 'disabled' : 'offline'); ?>">
                                    <?php echo $userInitial; ?>
                                </div>
                                <div class="user-details">
                                    <strong><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></strong>
                                    <?php if (!empty($user['password'])): ?>
                                        <small><i class="fas fa-lock"></i> <?php echo str_repeat('•', 8); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td data-label="Profile">
                            <span class="badge badge-info"><?php echo htmlspecialchars($user['profile'] ?? 'default'); ?></span>
                        </td>
                        <td data-label="Status">
                            <?php if ($isDisabled): ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-ban"></i> Disabled
                                </span>
                            <?php elseif ($isOnline): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-circle"></i> Online
                                </span>
                            <?php else: ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-circle"></i> Offline
                                </span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Aktif">
                            <?php if ($isDisabled): ?>
                                <span class="badge badge-muted">Tidak</span>
                            <?php else: ?>
                                <span class="badge badge-success">Ya</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Last Login">
                            <span class="last-login">
                                <?php echo !empty($user['last-login']) && $user['last-login'] !== 'never' 
                                    ? date('d/m/Y H:i', strtotime($user['last-login'])) 
                                    : '<i class="fas fa-minus-circle"></i> Tidak pernah'; ?>
                            </span>
                        </td>
<!--                        <td data-label="Aksi">-->
<!--                            <div class="action-buttons">-->
<!--                                <button class="btn-icon" onclick='editUser(--><?php //echo json_encode($user); ?>
<!--                                    <i class="fas fa-edit"></i>-->
<!--                                </button>-->

<!--                                <form method="POST" class="inline-form" onsubmit="return confirmToggle('--><?php ////echo htmlspecialchars($user['name'] ?? ''); ?> <?php ////echo $isDisabled ? 'true' : 'false'; ?>
<!--                                    <input type="hidden" name="action" value="toggle">-->
<!--                                    <input type="hidden" name="user_id" value="--><?php ////echo htmlspecialchars($user['.id'] ?? ''); ?>
<!--                                    <input type="hidden" name="current_status" value="--><?php //echo $user['disabled'] ?? 'false'; ?><!--">-->
<!--                                    <button type="submit" class="btn-icon --><?php //echo $isDisabled ? 'success' : 'warning'; ?><!--" title="--><?php //echo $isDisabled ? 'Enable' : 'Disable'; ?>
<!--                                        <i class="fas fa---><?php //echo $isDisabled ? 'play' : 'pause'; ?><!--"></i>-->
<!--                                    </button>-->
<!--                                </form>-->
<!--                                -->
<!--                                <form method="POST" class="inline-form" onsubmit="return confirmDelete('--><?php //echo htmlspecialchars($user['name'] ?? ''); ?>
<!--                                   <input type="hidden" name="action" value="delete">-->
<!--                                    <input type="hidden" name="user_id" value="--><?php ////echo htmlspecialchars($user['.id'] ?? ''); ?>
<!--                                    <button type="submit" class="btn-icon danger" title="Hapus">-->
<!--                                        <i class="fas fa-trash-alt"></i>-->
<!--                                    </button>-->
<!--                                </form>-->
<!--                            </div>-->
<!--                        </td>-->
<!--                    </tr>-->
                        <?php endforeach; ?>
                    <?php endif; ?>
<!--            </tbody>-->
<!--        </table>-->
<!--    </div>-->
<!--</div>-->

<!-- Edit Modal -->
<!--<div id="editModal" class="modal">-->
<!--    <div class="modal-content">-->
<!--        <div class="modal-header">-->
<!--            <h3><i class="fas fa-edit"></i> Edit PPPoE User</h3>-->
<!--            <button class="close" onclick="closeEditModal()">&times;</button>-->
<!--        </div>-->
<!--        <form method="POST">-->
<!--            <input type="hidden" name="action" value="edit">-->
<!--            <input type="hidden" name="user_id" id="edit_user_id">-->
<!--            -->
<!--            <div class="modal-body">-->
<!--                <div class="form-group">-->
<!--                    <label class="form-label">Username</label>-->
<!--                    <input type="text" name="username" id="edit_username" class="form-control" readonly required>-->
<!--                    <small class="form-hint">-->
<!--                        <i class="fas fa-info-circle"></i> Username hanya dapat diubah melalui halaman -->
<!--                        <a href="--><?php //echo APP_URL; ?><!--/admin/customers.php">pelanggan</a>-->
<!--                    </small>-->
<!--                </div>-->
<!--                -->
<!--                <div class="form-group">-->
<!--                    <label class="form-label">Password</label>-->
<!--                    <input type="text" name="password" id="edit_password" class="form-control" required>-->
<!--                    <small class="form-hint">Masukkan password baru untuk mengubah</small>-->
<!--                </div>-->
<!--                -->
<!--                <div class="form-row">-->
<!--                    <div class="form-group">-->
<!--                        <label class="form-label">Profile</label>-->
<!--                        <select name="profile" id="edit_profile" class="form-control" required>-->
<!--                            --><?php //foreach ($mikrotikProfiles as $profile): ?>
<!--                                <option value="--><?php //echo htmlspecialchars($profile['name']); ?><!--">-->
<!--                                    --><?php //echo htmlspecialchars($profile['name']); ?>
<!--                                </option>-->
<!--                            --><?php //endforeach; ?>
<!--                        </select>-->
<!--                        <div id="edit_profile_info" class="profile-info"></div>-->
<!--                    </div>-->
<!--                    -->
<!--                    <div class="form-group">-->
<!--                        <label class="form-label">Service</label>-->
<!--                        <select name="service" id="edit_service" class="form-control" required>-->
<!--                            <option value="pppoe">PPPoE</option>-->
<!--                            <option value="any">Any</option>-->
<!--                        </select>-->
<!--                    </div>-->
<!--                </div>-->
<!--            </div>-->
<!--            -->
<!--            <div class="modal-footer">-->
<!--                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>-->
<!--                <button type="submit" class="btn btn-primary">-->
<!--                    <i class="fas fa-save"></i> Simpan Perubahan-->
<!--                </button>-->
<!--            </div>-->
<!--        </form>-->
<!--    </div>-->
<!--</div>-->

<style>
/* Additional styles for mikrotik page */
.user-avatar {
    display: flex;
    align-items: center;
    gap: 12px;
}

.avatar {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    position: relative;
    transition: all var(--transition-fast);
}

.avatar.online {
    background: rgba(63, 185, 80, 0.15);
    color: var(--accent-green);
    box-shadow: 0 0 0 2px rgba(63, 185, 80, 0.2);
}

.avatar.offline {
    background: rgba(210, 153, 34, 0.15);
    color: var(--accent-orange);
}

.avatar.disabled {
    background: rgba(248, 81, 73, 0.15);
    color: var(--accent-red);
    opacity: 0.6;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-details strong {
    font-size: 14px;
}

.user-details small {
    font-size: 11px;
    color: var(--text-muted);
}

.user-details small i {
    margin-right: 4px;
}

.last-login {
    font-size: 12px;
    color: var(--text-secondary);
}

.last-login i {
    margin-right: 4px;
    font-size: 10px;
}

.profile-info {
    margin-top: 8px;
    padding: 8px 12px;
    background: var(--bg-tertiary);
    border-left: 3px solid var(--accent-blue);
    border-radius: var(--radius-sm);
    font-size: 12px;
    display: none;
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

.btn-icon.success:hover {
    color: var(--accent-green);
    border-color: var(--accent-green);
}

.btn-icon.warning:hover {
    color: var(--accent-orange);
    border-color: var(--accent-orange);
}

.btn-icon.danger:hover {
    color: var(--accent-red);
    border-color: var(--accent-red);
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

@media (max-width: 768px) {
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
    
    .user-avatar {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<script>
const mikrotikProfiles = <?php echo json_encode($mikrotikProfiles); ?>;

function updateProfileInfo(selectId, displayId) {
    const select = document.getElementById(selectId);
    const display = document.getElementById(displayId);
    if (!select || !display) return;
    
    const profileName = select.value;
    const profile = mikrotikProfiles?.find(p => p.name === profileName);
    
    if (profile && Object.keys(profile).length > 1) {
        let html = '<div class="profile-details" style="display: flex; gap: 12px; flex-wrap: wrap;">';
        if (profile['rate-limit']) {
            html += `<span><i class="fas fa-tachometer-alt"></i> Limit: ${profile['rate-limit']}</span>`;
        }
        if (profile['session-timeout']) {
            html += `<span><i class="fas fa-clock"></i> Timeout: ${profile['session-timeout']}</span>`;
        }
        if (profile['only-one']) {
            html += `<span><i class="fas fa-user-lock"></i> Only One: ${profile['only-one']}</span>`;
        }
        html += '</div>';
        
        if (html === '<div class="profile-details" style="display: flex; gap: 12px; flex-wrap: wrap;"></div>') {
            html = `<span><i class="fas fa-tag"></i> ${profile.name}</span>`;
        }
        
        display.innerHTML = html;
        display.style.display = 'block';
    } else {
        display.style.display = 'none';
    }
}

// Search functionality
document.getElementById('searchUser')?.addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.data-table tbody tr');
    
    rows.forEach(row => {
        if (row.querySelector('.empty-state')) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

function editUser(user) {
    document.getElementById('edit_user_id').value = user['.id'] || '';
    document.getElementById('edit_username').value = user.name || '';
    document.getElementById('edit_password').value = user.password || '';
    document.getElementById('edit_profile').value = user.profile || 'default';
    document.getElementById('edit_service').value = user.service || 'pppoe';
    
    updateProfileInfo('edit_profile', 'edit_profile_info');
    
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function confirmToggle(username, isDisabled) {
    const action = isDisabled ? 'enable' : 'disable';
    return confirm(`Yakin ingin ${action} user "${username}"?`);
}

function confirmDelete(username) {
    return confirm(`Hapus user "${username}"?\n\nTindakan ini tidak dapat dibatalkan!`);
}

// Initialize profile info for add form
document.addEventListener('DOMContentLoaded', function() {
    updateProfileInfo('add_profile', 'add_profile_info');
    document.getElementById('add_profile')?.addEventListener('change', () => 
        updateProfileInfo('add_profile', 'add_profile_info'));
    document.getElementById('edit_profile')?.addEventListener('change', () => 
        updateProfileInfo('edit_profile', 'edit_profile_info'));
});

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
?>