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

// --- 1. BLOK API GET (Fetch Data via JS) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];

    switch ($action){
        case 'get_users':
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(500, max(1, (int) ($_GET['per_page'] ?? 10)));
            $search = strtolower(trim((string) ($_GET['search'] ?? '')));
            $offset = ($page - 1) * $perPage;

            $allUsers = radiusGetPppoeUsers();
            if (!is_array($allUsers)) $allUsers = [];

            $filteredUsers = [];
            if ($search !== '') {
                foreach ($allUsers as $u) {
                    if (strpos(strtolower($u['name'] ?? ''), $search) !== false ||
                            strpos(strtolower($u['profile'] ?? ''), $search) !== false) {
                        $filteredUsers[] = $u;
                    }
                }
            } else {
                $filteredUsers = $allUsers;
            }

            $total = count($filteredUsers);
            $totalPages = ceil($total / $perPage);
            $paginatedUsers = array_slice($filteredUsers, $offset, $perPage);

            $isConnected = mikrotikConnect();
            $onlineUsernames = [];

            if ($isConnected) {
                $activeSessions = mikrotikGetActiveSessionsAllRouter();
                if (!is_array($activeSessions)) $activeSessions = [];

                $fiktifData = getFiktifCustomers();
                if (is_array($fiktifData)) {
                    foreach ($fiktifData as $fUser) {
                        $activeSessions[] = ['name' => $fUser['name']];
                    }
                }
                $onlineUsernames = array_column($activeSessions, 'name');
            }

            foreach ($paginatedUsers as &$user) {
                $user['isOnline'] = in_array($user['name'] ?? '', $onlineUsernames);
                $user['isDisabled'] = ($user['disabled'] ?? 'false') === 'true';
            }

            echo json_encode([
                    'success' => true,
                    'data' => [
                            'users' => $paginatedUsers,
                            'total' => $total,
                            'page' => $page,
                            'perPage' => $perPage,
                            'totalPages' => $totalPages
                    ]
            ]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }
}

// --- 2. BLOK FORM POST (Tambah, Edit, Delete User via PHP) ---
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
        'start_subnet' => '11.7.1',
        'end_subnet'   => '11.7.10',
        'start_ip'     => 2,
        'end_ip'       => 254
];

if ($isConnected) {
    $activeSessions = mikrotikGetActiveSessionsAllRouter();
    if (!is_array($activeSessions)) {
        $activeSessions = [];
    }

    $usedIPs = [];
    foreach ($activeSessions as $session) {
        if (!empty($session['address']) && filter_var($session['address'], FILTER_VALIDATE_IP)) {
            $usedIPs[] = $session['address'];
        }
    }

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
    $availableIPs = array_values(array_diff($allPoolIPs, $usedIPs));
    shuffle($availableIPs);

    $fiktifData = getFiktifCustomers();
    if (is_array($fiktifData)) {
        foreach ($fiktifData as $user) {
            if (empty($availableIPs)) {
                break;
            }
            $newIP = array_shift($availableIPs);
            $activeSessions[] = [
                    'name'    => $user['name'],
                    'address' => $newIP,
                    'uptime'  => rand(3600, 86400),
                    'radius'  => 'true'
            ];
        }
    }
} else {
    $activeSessions = [];
}
$onlineCount = count($activeSessions);

$disabledCount = count(array_filter($mikrotikUsers, fn($u) => ($u['disabled'] ?? 'false') === 'true'));
$offlineCount = $onlineCount <= $totalUsers ? $totalUsers - $onlineCount : 0;

$mikrotikProfiles = radiusGetPppoeProfiles();
if (empty($mikrotikProfiles)) {
    $mikrotikProfiles = [['name' => 'default']];
}

ob_start();
?>

    <style>
        .user-avatar { display: flex; align-items: center; gap: 12px; }
        .avatar { width: 36px; height: 36px; border-radius: var(--radius-md); background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; position: relative; transition: all var(--transition-fast); }
        .avatar.online { background: rgba(63, 185, 80, 0.15); color: var(--accent-green); box-shadow: 0 0 0 2px rgba(63, 185, 80, 0.2); }
        .avatar.offline { background: rgba(210, 153, 34, 0.15); color: var(--accent-orange); }
        .avatar.disabled { background: rgba(248, 81, 73, 0.15); color: var(--accent-red); opacity: 0.6; }
        .user-details { display: flex; flex-direction: column; }
        .user-details strong { font-size: 14px; }
        .user-details small { font-size: 11px; color: var(--text-muted); }
        .user-details small i { margin-right: 4px; }
        .last-login { font-size: 12px; color: var(--text-secondary); }
        .last-login i { margin-right: 4px; font-size: 10px; }
        .profile-info { margin-top: 8px; padding: 8px 12px; background: var(--bg-tertiary); border-left: 3px solid var(--accent-blue); border-radius: var(--radius-sm); font-size: 12px; display: none; }
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .inline-form { display: inline; }
        .btn-icon { background: var(--bg-tertiary); border: 1px solid var(--border-light); color: var(--text-secondary); cursor: pointer; padding: 6px 10px; border-radius: var(--radius-sm); transition: all var(--transition-fast); font-size: 12px; }
        .btn-icon:hover { background: var(--bg-secondary); border-color: var(--border-color); color: var(--accent-blue); }
        .btn-icon.success:hover { color: var(--accent-green); border-color: var(--accent-green); }
        .btn-icon.warning:hover { color: var(--accent-orange); border-color: var(--accent-orange); }
        .btn-icon.danger:hover { color: var(--accent-red); border-color: var(--accent-red); }
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 12px; color: var(--text-muted); font-size: 14px; }
        .search-wrapper .form-control { padding-left: 36px; width: 250px; }
        .empty-state { text-align: center; padding: 48px 20px !important; color: var(--text-muted); }
        .empty-state i { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }
        .empty-state p { margin: 0; font-size: 14px; }
        @media (max-width: 768px) {
            .search-wrapper { width: 100%; margin-top: 12px; }
            .search-wrapper .form-control { width: 100%; }
            .action-buttons { justify-content: flex-start; }
            .user-avatar { flex-direction: column; align-items: flex-start; }
        }
    </style>

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

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalUsers; ?></h3>
                <p>Total User</p>
            </div>
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $onlineCount; ?></h3>
                <p>Online</p>
            </div>
            <div class="stat-icon green"><i class="fas fa-signal"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $offlineCount; ?></h3>
                <p>Offline</p>
            </div>
            <div class="stat-icon orange"><i class="fas fa-circle"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $disabledCount; ?></h3>
                <p>Disabled</p>
            </div>
            <div class="stat-icon red"><i class="fas fa-ban"></i></div>
        </div>
    </div>

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

    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
            <h3 class="card-title" style="margin: 0;"><i class="fas fa-network-wired"></i> Daftar PPPoE User</h3>
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <select id="perPageSelect" class="form-control" style="width: 110px;">
                    <option value="10" selected>10 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </select>
                <div class="search-wrapper" style="margin: 0;">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchUser" class="form-control" placeholder="Cari username...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="usersTable">
                <thead>
                <tr>
                    <th>Username</th>
                    <th>Profile</th>
                    <th>Status</th>
                    <th>Aktif</th>
                    <th>Last Login</th>
                </tr>
                </thead>
                <tbody id="usersTableBody">
                <tr>
                    <td colspan="5" class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Memuat data user...</p>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <div id="userPagination" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin: 20px 0;"></div>
    </div>

    <script>
        const usersTableBody = document.getElementById('usersTableBody');
        const userPagination = document.getElementById('userPagination');
        const searchInput = document.getElementById('searchUser');
        const perPageSelect = document.getElementById('perPageSelect');

        let currentPage = 1;
        let currentSearch = '';
        let searchTimer = null;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(m) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
            });
        }

        function formatDateLabel(dateStr) {
            if (!dateStr || dateStr === 'never') return '<i class="fas fa-minus-circle"></i> Tidak pernah';
            const date = new Date(dateStr);
            if (Number.isNaN(date.getTime())) return escapeHtml(dateStr);
            return new Intl.DateTimeFormat('id-ID', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            }).format(date);
        }

        function renderUsers(users) {
            if (!users || users.length === 0) {
                usersTableBody.innerHTML = `
        <tr>
            <td colspan="5" class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Data PPPoE user tidak ditemukan</p>
            </td>
        </tr>`;
                return;
            }

            usersTableBody.innerHTML = users.map(user => {
                const initial = (user.name || 'U').substring(0, 1).toUpperCase();
                let avatarClass = user.isDisabled ? 'disabled' : (user.isOnline ? 'online' : 'offline');

                let statusBadge = '';
                if (user.isDisabled) statusBadge = '<span class="badge badge-danger"><i class="fas fa-ban"></i> Disabled</span>';
                else if (user.isOnline) statusBadge = '<span class="badge badge-success"><i class="fas fa-circle"></i> Online</span>';
                else statusBadge = '<span class="badge badge-warning"><i class="fas fa-circle"></i> Offline</span>';

                let activeBadge = user.isDisabled ? '<span class="badge badge-muted">Tidak</span>' : '<span class="badge badge-success">Ya</span>';
                let passHtml = user.password ? `<small><i class="fas fa-lock"></i> ••••••••</small>` : '';

                return `
        <tr>
            <td data-label="Username">
                <div class="user-avatar">
                    <div class="avatar ${avatarClass}">${initial}</div>
                    <div class="user-details">
                        <strong>${escapeHtml(user.name)}</strong>
                        ${passHtml}
                    </div>
                </div>
            </td>
            <td data-label="Profile">
                <span class="badge badge-info">${escapeHtml(user.profile || 'default')}</span>
            </td>
            <td data-label="Status">${statusBadge}</td>
            <td data-label="Aktif">${activeBadge}</td>
            <td data-label="Last Login"><span class="last-login">${formatDateLabel(user['last-login'])}</span></td>
        </tr>`;
            }).join('');
        }

        function renderPagination(page, totalPages, totalItems) {
            if (totalPages <= 1) {
                userPagination.innerHTML = '';
                return;
            }

            let html = `
    <button class="btn btn-secondary btn-sm" onclick="fetchUsers(1)" ${page === 1 ? 'disabled style="opacity: 0.5;"' : ''}><i class="fas fa-angle-double-left"></i></button>
    <button class="btn btn-secondary btn-sm" onclick="fetchUsers(${Math.max(1, page - 1)})" ${page === 1 ? 'disabled style="opacity: 0.5;"' : ''}><i class="fas fa-angle-left"></i></button>
    <span style="color: var(--text-secondary); text-align: center; min-width: 260px;">
        Halaman ${page} dari ${totalPages} (Total: ${totalItems} user)
    </span>
    <button class="btn btn-secondary btn-sm" onclick="fetchUsers(${Math.min(totalPages, page + 1)})" ${page === totalPages ? 'disabled style="opacity: 0.5;"' : ''}><i class="fas fa-angle-right"></i></button>
    <button class="btn btn-secondary btn-sm" onclick="fetchUsers(${totalPages})" ${page === totalPages ? 'disabled style="opacity: 0.5;"' : ''}><i class="fas fa-angle-double-right"></i></button>
`;
            userPagination.innerHTML = html;
        }

        async function fetchUsers(page = 1) {
            currentPage = page;
            usersTableBody.innerHTML = `<tr><td colspan="5" class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Memuat data...</p></td></tr>`;

            try {
                const params = new URLSearchParams({
                    action: 'get_users',
                    page: currentPage,
                    per_page: perPageSelect.value,
                    search: currentSearch
                });

                const response = await fetch(`mikrotik.php?${params.toString()}`, { credentials: 'same-origin' });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    renderUsers(data.data.users);
                    renderPagination(data.data.page, data.data.totalPages, data.data.total);
                } else {
                    throw new Error(data.message);
                }
            } catch (err) {
                console.error("Gagal mengambil data:", err);
                usersTableBody.innerHTML = `<tr><td colspan="5" class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Gagal memuat data dari server. Cek console.</p></td></tr>`;
            }
        }

        searchInput.addEventListener('input', function(e) {
            currentSearch = e.target.value.trim();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { fetchUsers(1); }, 350);
        });

        perPageSelect.addEventListener('change', function() {
            fetchUsers(1);
        });

        document.addEventListener('DOMContentLoaded', () => fetchUsers(1));
    </script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>