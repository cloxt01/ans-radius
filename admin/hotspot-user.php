<?php
/**
 * Hotspot User Management
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Hotspot Users';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $qty = (int) $_POST['qty'];
        $server = sanitize($_POST['server'] ?? 'all');
        $userMode = sanitize($_POST['user_mode'] ?? 'up'); // u=p (username=password) or up (username & password)
        $length = (int) $_POST['length'];
        $prefix = sanitize($_POST['prefix'] ?? '');
        $profile = sanitize($_POST['profile']);
        $timelimit = sanitize($_POST['timelimit'] ?? '');
        $datalimit = sanitize($_POST['datalimit'] ?? '');
        $charMode = sanitize($_POST['char_mode'] ?? 'alphanumeric'); // alphanumeric, numeric, alpha

        $profilePrice = 0;
        $profileValidity = '';
        $profileSelling = 0;
        $profileRateLimit = '';
        $profileIdleTimeout = '';
        $profileAddressPool = '';
        $profileParentQueue = '';
        $profileSource = function_exists('radiusGetHotspotProfilesCloud') ? radiusGetHotspotProfilesCloud() : [];
        foreach ($profileSource as $hp) {
            if (($hp['name'] ?? '') !== $profile) {
                continue;
            }

            $profilePrice = isset($hp['price']) && is_numeric($hp['price']) ? (float) $hp['price'] : 0;
            $profileSelling = isset($hp['selling-price']) && is_numeric($hp['selling-price']) ? (float) $hp['selling-price'] : 0;
            $profileValidity = trim((string) ($hp['session-timeout'] ?? ''));
            $profileRateLimit = trim((string) ($hp['rate-limit'] ?? ''));
            $profileIdleTimeout = trim((string) ($hp['idle-timeout'] ?? ''));
            $profileAddressPool = trim((string) ($hp['address-pool'] ?? ''));
            $profileParentQueue = trim((string) ($hp['parent-queue'] ?? ''));
            break;
        }
        $voucherPrice = $profileSelling > 0 ? $profileSelling : $profilePrice;
        // Use profile validity if user didn't specify a timelimit
        if (empty($timelimit) && $profileValidity !== '-') {
            $timelimit = $profileValidity;
        }
        // Add 'vc' comment for Mikhmon scheduler compatibility
        $mikhmonComment = 'vc-' . date('d.m.y') . '-' . $profile;

        $successCount = 0;
        $generatedVouchers = [];
        for ($i = 0; $i < $qty; $i++) {
            $user = $prefix . generateRandomString($length, $charMode);
            $pass = ($userMode === 'up') ? generateRandomString($length, $charMode) : $user;

            $extraData = [
                'server' => $server,
                'limit-uptime' => $timelimit,
                'limit-bytes-total' => $datalimit,
                'comment' => $mikhmonComment,
                'price' => $voucherPrice,
                'rate-limit' => $profileRateLimit,
                'idle-timeout' => $profileIdleTimeout,
                'address-pool' => $profileAddressPool,
                'parent-queue' => $profileParentQueue,
            ];

            if (mikrotikAddHotspotUser($user, $pass, $profile, $extraData)) {
                $successCount++;
                // Record sale if price is set
                if ($voucherPrice > 0) {
                    recordHotspotSale($user, $profile, $profilePrice, $profileSelling, $prefix);
                }
                // Store generated voucher for printing
                $generatedVouchers[] = [
                    'username' => $user,
                    'password' => $pass,
                    'profile' => $profile,
                    'price' => $voucherPrice > 0 ? formatCurrency($voucherPrice) : '-',
                    'validity' => $timelimit ?: '-'
                ];
            }
        }

        // Store generated vouchers in session for printing
        if (!empty($generatedVouchers)) {
            $_SESSION['generated_vouchers'] = $generatedVouchers;
        }

        setFlash('success', "Berhasil generate $successCount voucher.");
        redirect('hotspot-user.php');
    }

    if ($action === 'delete') {
        $name = $_POST['name'];
        if (mikrotikDeleteHotspotUser($name)) {
            setFlash('success', "User $name berhasil dihapus.");
        } else {
            setFlash('error', "Gagal menghapus user $name.");
        }
        redirect('hotspot-user.php');
    }

    if ($action === 'bulk_delete') {
        $names = $_POST['names'] ?? [];
        if (!empty($names)) {
            $successCount = 0;
            foreach ($names as $name) {
                if (mikrotikDeleteHotspotUser($name)) {
                    $successCount++;
                }
            }
            setFlash('success', "Berhasil menghapus $successCount user terpilih.");
        }
        redirect('hotspot-user.php');
    }
}

// Get Data (cloud-first and cloud-only for hotspot users)
$radiusReady = function_exists('radiusUserProvisioningReady') ? radiusUserProvisioningReady() : false;
$hotspotUsers = mikrotikGetHotspotUsers();
$hotspotProfiles = function_exists('radiusGetHotspotProfilesCloud') ? radiusGetHotspotProfilesCloud() : [];
$isRadiusSource = true;
$activeUsers = [];
$activeUsernames = array_column($activeUsers, 'user');

// Build profile metadata lookup from cloud profile source
$profileMetaMap = [];
foreach ($hotspotProfiles as $p) {
    $profileName = (string) ($p['name'] ?? '');
    if ($profileName === '') {
        continue;
    }

    $validity = trim((string) ($p['session-timeout'] ?? ''));
    $price = isset($p['selling-price']) && is_numeric($p['selling-price']) ? (float) $p['selling-price'] : 0;
    if ($price <= 0) {
        $price = isset($p['price']) && is_numeric($p['price']) ? (float) $p['price'] : 0;
    }
    if ($price <= 0) {
        $parsedMeta = parseMikhmonOnLogin((string) ($p['on-login'] ?? ''));
        if (isset($parsedMeta['selling_price']) && is_numeric($parsedMeta['selling_price']) && (float) $parsedMeta['selling_price'] > 0) {
            $price = (float) $parsedMeta['selling_price'];
        } elseif (isset($parsedMeta['price']) && is_numeric($parsedMeta['price']) && (float) $parsedMeta['price'] > 0) {
            $price = (float) $parsedMeta['price'];
        }
    }

    $profileMetaMap[$profileName] = [
        'price' => $price,
        'validity' => $validity !== '' ? $validity : '-',
    ];
}

$totalUsers = count($hotspotUsers);
$onlineCount = count($activeUsers);

// Extract unique values for filters
$filterServers = array_unique(array_filter(array_column($hotspotUsers, 'server')));
sort($filterServers);

$filterProfiles = array_unique(array_filter(array_column($hotspotUsers, 'profile')));
sort($filterProfiles);

$filterComments = array_unique(array_filter(array_column($hotspotUsers, 'comment')));
sort($filterComments);

$printVoucherPayload = buildHotspotVoucherPrintData($_SESSION['generated_vouchers'] ?? []);

ob_start();
?>

<!-- Data source info -->
<div style="background: rgba(0, 170, 255, 0.1); border: 1px solid #2aa7e0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 10px; color: #7ed4ff;">
        <i class="fas fa-database" style="font-size: 1.2rem;"></i>
        <div>
            <strong>
                <?php echo $isRadiusSource ? 'Daftar user diambil dari DB Radius.' : 'Daftar user menggunakan sumber MikroTik (fallback).'; ?>
            </strong>
            <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: #9ee0ff;">
                <?php echo $radiusReady ? 'Tabel di bawah ini menampilkan data hotspot user dari database cloud.' : 'Radius DB belum siap. Tambah user hotspot akan diblokir sampai tabel Radius tersedia.'; ?>
            </p>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon cyan"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3>
                <?php echo $totalUsers; ?>
            </h3>
            <p>Total User</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-plug"></i></div>
        <div class="stat-info">
            <h3>
                <?php echo $onlineCount; ?>
            </h3>
            <p>Online</p>
        </div>
    </div>
</div>

<!-- Mass Generator Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-magic"></i> Generate Massal</h3>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="generate">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label">Qty</label>
                <input type="number" name="qty" class="form-control" value="10" required>
            </div>
            <div class="form-group">
                <label class="form-label">Server</label>
                <select name="server" class="form-control">
                    <option value="all">All</option>
                    <option value="hotspot1">Hotspot1</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">User Mode</label>
                <select name="user_mode" class="form-control">
                    <option value="up">Username & Password</option>
                    <option value="u=p">Username = Password</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Length</label>
                <input type="number" name="length" class="form-control" value="6">
            </div>
            <div class="form-group">
                <label class="form-label">Prefix</label>
                <input type="text" name="prefix" class="form-control" placeholder="ABC-">
            </div>
            <div class="form-group">
                <label class="form-label">Karakter</label>
                <select name="char_mode" class="form-control">
                    <option value="alphanumeric">Huruf & Angka</option>
                    <option value="numeric">Hanya Angka</option>
                    <option value="alpha">Hanya Huruf</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Profile</label>
                <select name="profile" class="form-control" required>
                    <?php foreach ($hotspotProfiles as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['name']); ?>">
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Time Limit (1d/12h)</label>
                <input type="text" name="timelimit" class="form-control" placeholder="Contoh: 1d">
            </div>
            <div class="form-group">
                <label class="form-label">Data Limit (MB/GB)</label>
                <input type="text" name="datalimit" class="form-control" placeholder="Contoh: 1000M">
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top: 10px; <?php echo !$radiusReady ? 'cursor: not-allowed;' : ''; ?>" <?php echo !$radiusReady ? 'disabled' : ''; ?>>
            <i class="fas fa-rocket"></i> Generate
        </button>
        
    </form>
</div>

<!-- User List Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list"></i> Daftar Hotspot User (DB Radius)</h3>
    </div>
    <!-- Filter Toolbar -->
    <div style="display: flex; flex-wrap: wrap; gap: 8px; padding: 12px 15px; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); align-items: center;">
        <div style="display: flex; align-items: center; gap: 6px;">
            <i class="fas fa-server" style="color: var(--text-muted); font-size: 0.8rem;"></i>
            <select id="filterServer" class="form-control" style="width: auto; min-width: 110px; padding: 6px 10px; font-size: 0.85rem;">
                <option value="">Semua Server</option>
                <?php foreach ($filterServers as $s): ?>
                    <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <i class="fas fa-id-badge" style="color: var(--text-muted); font-size: 0.8rem;"></i>
            <select id="filterProfile" class="form-control" style="width: auto; min-width: 120px; padding: 6px 10px; font-size: 0.85rem;">
                <option value="">Semua Profile</option>
                <?php foreach ($filterProfiles as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <i class="fas fa-tag" style="color: var(--text-muted); font-size: 0.8rem;"></i>
            <select id="filterComment" class="form-control" style="width: auto; min-width: 140px; padding: 6px 10px; font-size: 0.85rem;">
                <option value="">Semua Batch</option>
                <?php foreach ($filterComments as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="margin-left: auto; display: flex; align-items: center; gap: 6px;">
            <i class="fas fa-search" style="color: var(--text-muted); font-size: 0.8rem;"></i>
            <input type="text" id="searchUser" class="form-control" placeholder="Cari user..." style="width: 180px; padding: 6px 10px; font-size: 0.85rem;">
        </div>
        <button type="button" id="btnResetFilter" class="btn btn-secondary btn-sm" style="padding: 6px 10px; font-size: 0.8rem;" title="Reset Filter">
            <i class="fas fa-times"></i> Reset
        </button>
    </div>
    <form method="POST" id="bulkForm">
        <input type="hidden" name="action" value="bulk_delete">

        <!-- Bulk Action Bar (Hidden by default) -->
        <div id="bulkActionBar"
            style="display: none; background: var(--bg-secondary); padding: 10px 15px; border-radius: 8px; margin-bottom: 15px; align-items: center; justify-content: space-between; border: 1px solid var(--neon-cyan); box-shadow: 0 0 10px rgba(0, 245, 255, 0.1);">
            <div style="font-weight: 600; color: var(--text-primary);">
                <span id="selectedCount">0</span> Item Terpilih
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn btn-success btn-sm" onclick="printSelectedUsers()">
                    <i class="fas fa-print"></i> Print Terpilih
                </button>
                <button type="submit" class="btn btn-danger btn-sm"
                    onclick="return confirm('Hapus semua user yang dipilih?');">
                    <i class="fas fa-trash-alt"></i> Hapus Terpilih
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="selectAll" style="cursor: pointer;">
                        </th>
                        <th>User</th>
                        <th>Profile</th>
                        <th>Price</th>
                        <th>Validity</th>
                        <th>Comment</th>
                        <th>Limit</th>
                        <th>Usage</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($hotspotUsers)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px; color: var(--text-muted);">
                                <i class="fas fa-info-circle"></i> Tidak ada hotspot user.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($hotspotUsers as $user): ?>
                            <?php
                            $userName = $user['name'] ?? '';
                            $userProfile = $user['profile'] ?? 'default';
                            $isOnline = $userName !== '' && in_array($userName, $activeUsernames);
                            $userComment = $user['comment'] ?? '';
                            $cloudPrice = isset($user['price']) && is_numeric($user['price']) ? (float) $user['price'] : 0;
                            if ($cloudPrice <= 0 && isset($profileMetaMap[$userProfile]['price']) && is_numeric($profileMetaMap[$userProfile]['price'])) {
                                $cloudPrice = (float) $profileMetaMap[$userProfile]['price'];
                            }
                            if ($cloudPrice <= 0 && isset($hotspotProfiles)) {
                                foreach ($hotspotProfiles as $profileRow) {
                                    if (($profileRow['name'] ?? '') === $userProfile) {
                                        $parsedProfile = parseMikhmonOnLogin((string) ($profileRow['on-login'] ?? ''));
                                        if (isset($parsedProfile['selling_price']) && is_numeric($parsedProfile['selling_price']) && (float) $parsedProfile['selling_price'] > 0) {
                                            $cloudPrice = (float) $parsedProfile['selling_price'];
                                        } elseif (isset($parsedProfile['price']) && is_numeric($parsedProfile['price']) && (float) $parsedProfile['price'] > 0) {
                                            $cloudPrice = (float) $parsedProfile['price'];
                                        }
                                        break;
                                    }
                                }
                            }
                            $cloudValidity = (string) ($user['validity'] ?? ($user['limit-uptime'] ?? '-'));
                            if (trim($cloudValidity) === '') {
                                $cloudValidity = '-';
                            }
                            $displayPrice = $cloudPrice > 0 ? formatCurrency($cloudPrice) : '-';
                            ?>
                            <tr data-server="<?php echo htmlspecialchars($user['server'] ?? ''); ?>"
                                data-profile="<?php echo htmlspecialchars($userProfile); ?>"
                                data-comment="<?php echo htmlspecialchars($userComment); ?>"
                                data-username="<?php echo htmlspecialchars($userName); ?>"
                                data-password="<?php echo htmlspecialchars((string) ($user['password'] ?? '')); ?>"
                                data-price="<?php echo htmlspecialchars($displayPrice); ?>"
                                data-validity="<?php echo htmlspecialchars($cloudValidity); ?>">
                                <td style="text-align: center;">
                                    <input type="checkbox" name="names[]" value="<?php echo htmlspecialchars($userName); ?>"
                                        class="user-checkbox" style="cursor: pointer;" onchange="updateBulkBar()">
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div
                                            style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $isOnline ? 'var(--neon-green)' : 'var(--text-muted)'; ?>">
                                        </div>
                                        <strong>
                                            <?php echo htmlspecialchars($userName); ?>
                                        </strong><br>
                                        <small
                                            class="text-muted"><?php echo htmlspecialchars($user['password'] ?? ''); ?></small>
                                    </div>
                                </td>
                                <td data-label="Profile"><span class="badge badge-info">
                                        <?php echo htmlspecialchars($userProfile); ?>
                                    </span></td>
                                <td data-label="Price">
                                    <small><?php echo $displayPrice; ?></small>
                                </td>
                                <td data-label="Validity">
                                    <small><?php echo htmlspecialchars($cloudValidity); ?></small>
                                </td>
                                <td data-label="Comment">
                                    <small><?php echo htmlspecialchars($userComment ?: '-'); ?></small>
                                </td>
                                <td data-label="Limit">
                                    <small>
                                        R: <?php echo htmlspecialchars($user['rate-limit'] ?? '∞'); ?><br>
                                        T: <?php echo $user['limit-uptime'] ?: '∞'; ?><br>
                                        D:
                                        <?php echo $user['limit-bytes-total'] ? formatBytes($user['limit-bytes-total']) : '∞'; ?>
                                    </small>
                                </td>
                                <td data-label="Usage">
                                    <small>
                                        U: <?php echo $user['uptime'] ?: '0'; ?><br>
                                        D: <?php echo formatBytes(($user['bytes-in'] ?? 0) + ($user['bytes-out'] ?? 0)); ?>
                                    </small>
                                </td>
                                <td data-label="Aksi">
                                    <div style="display: flex; gap: 5px;">
                                        <a href="hotspot-user-edit.php?name=<?php echo urlencode($userName); ?>"
                                            class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                        <button type="button" class="btn btn-danger btn-sm"
                                            onclick="deleteSingleUser('<?php echo htmlspecialchars($userName, ENT_QUOTES); ?>')">
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
    </form>
</div>

<script>
    const profileMetaMap = <?php echo json_encode($profileMetaMap); ?>;

    // Build profile-to-comments mapping from table data
    const profileCommentsMap = {};
    document.querySelectorAll('.data-table tbody tr').forEach(row => {
        const profile = row.getAttribute('data-profile') || '';
        const comment = row.getAttribute('data-comment') || '';
        if (!profileCommentsMap[profile]) profileCommentsMap[profile] = new Set();
        if (comment) profileCommentsMap[profile].add(comment);
    });

    // All comments for reset
    const allComments = <?php echo json_encode(array_values($filterComments)); ?>;

    function updateCommentDropdown() {
        const profile = document.getElementById('filterProfile').value;
        const commentSelect = document.getElementById('filterComment');
        const currentVal = commentSelect.value;

        // Clear options
        commentSelect.innerHTML = '<option value="">Semua Batch</option>';

        let relevantComments;
        if (profile && profileCommentsMap[profile]) {
            relevantComments = Array.from(profileCommentsMap[profile]).sort();
        } else {
            relevantComments = allComments;
        }

        relevantComments.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            if (c === currentVal) opt.selected = true;
            commentSelect.appendChild(opt);
        });
    }

    function filterTable() {
        const search = document.getElementById('searchUser').value.toLowerCase();
        const server = document.getElementById('filterServer').value;
        const profile = document.getElementById('filterProfile').value;
        const comment = document.getElementById('filterComment').value;

        const rows = document.querySelectorAll('.data-table tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const rowServer = row.getAttribute('data-server');
            const rowProfile = row.getAttribute('data-profile');
            const rowComment = row.getAttribute('data-comment');
            const rowText = row.textContent.toLowerCase();

            const matchServer = !server || rowServer === server;
            const matchProfile = !profile || rowProfile === profile;
            const matchComment = !comment || rowComment === comment;
            const matchSearch = !search || rowText.includes(search);

            if (matchServer && matchProfile && matchComment && matchSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Uncheck hidden checkboxes
        document.querySelectorAll('.user-checkbox').forEach(cb => {
            if (cb.closest('tr').style.display === 'none') cb.checked = false;
        });
        updateBulkBar();
    }

    document.getElementById('searchUser').addEventListener('input', filterTable);
    document.getElementById('filterServer').addEventListener('change', filterTable);
    document.getElementById('filterProfile').addEventListener('change', function() {
        updateCommentDropdown();
        filterTable();
    });
    document.getElementById('filterComment').addEventListener('change', filterTable);

    // Reset filter button
    document.getElementById('btnResetFilter').addEventListener('click', function() {
        document.getElementById('filterServer').value = '';
        document.getElementById('filterProfile').value = '';
        document.getElementById('filterComment').value = '';
        document.getElementById('searchUser').value = '';
        updateCommentDropdown();
        filterTable();
    });

    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    const bulkBar = document.getElementById('bulkActionBar');
    const selectedCount = document.getElementById('selectedCount');

    selectAll.addEventListener('change', function () {
        checkboxes.forEach(cb => {
            if (cb.closest('tr').style.display !== 'none') {
                cb.checked = this.checked;
            }
        });
        updateBulkBar();
    });

    function updateBulkBar() {
        const checked = document.querySelectorAll('.user-checkbox:checked').length;
        selectedCount.textContent = checked;
        bulkBar.style.display = checked > 0 ? 'flex' : 'none';

        // Update selectAll state
        const visibleCbs = Array.from(checkboxes).filter(cb => cb.closest('tr').style.display !== 'none');
        if (visibleCbs.length > 0) {
            selectAll.checked = visibleCbs.every(cb => cb.checked);
        }
    }

    function deleteSingleUser(name) {
        if (!confirm('Hapus user ini?')) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="action" value="delete">'
            + '<input type="hidden" name="name" value="' + name.replace(/"/g, '&quot;') + '">';
        document.body.appendChild(form);
        form.submit();
    }

    function printSelectedUsers() {
        const selectedRows = Array.from(document.querySelectorAll('.user-checkbox:checked'))
            .map(cb => cb.closest('tr'))
            .filter(Boolean);

        if (selectedRows.length === 0) {
            alert('Pilih minimal satu user untuk dicetak.');
            return;
        }

        const voucherData = selectedRows.map(row => ({
            username: row.getAttribute('data-username') || '',
            password: row.getAttribute('data-password') || '',
            profile: row.getAttribute('data-profile') || 'default',
            price: row.getAttribute('data-price') || '-',
            validity: row.getAttribute('data-validity') || '-'
        }));

        const selectedTemplate = <?php echo json_encode(getSettingValue('voucher_template', 'default.php')); ?>;
        const printUrl = 'print_vouchers.php?vouchers=' + encodeURIComponent(JSON.stringify(voucherData)) + '&template=' + encodeURIComponent(selectedTemplate);
        window.open(printUrl, '_blank');
    }

    
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
