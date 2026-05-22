<?php
/**
 * MikroTik PPPoE Management - dengan AJAX Search
 * 
 * Fitur: Search real-time ke backend, hanya tampilkan hasil pencarian
 */

require_once '../../includes/auth.php';
requireTechnicianLogin();

$pageTitle = 'PPPoE Management';

// Get MikroTik settings
$mikrotikSettings = getMikrotikSettings();

// Get MikroTik users (hanya untuk statistik awal)
$mikrotikUsers = mikrotikGetPppoeUsers();
$totalUsers = count($mikrotikUsers);

// Get active PPPoE sessions
$activeSessions = mikrotikGetActiveSessionsAllRouter();
$onlineCount = count($activeSessions);

// Calculate stats
$disabledCount = count(array_filter($mikrotikUsers, fn($u) => ($u['disabled'] ?? 'false') === 'true'));
$offlineCount = $totalUsers - $onlineCount;

$isMikrotikConnected = mikrotikConnect();

// Quick actions for technician
$quickActions = [
    ['icon' => 'fa-plus', 'label' => 'Tambah User', 'action' => 'addUser()', 'color' => 'green'],
    ['icon' => 'fa-sync-alt', 'label' => 'Refresh', 'action' => 'location.reload()', 'color' => 'blue'],
    ['icon' => 'fa-filter', 'label' => 'Filter', 'action' => 'toggleFilter()', 'color' => 'orange'],
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#0d1117">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Teknisi</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==================== MOBILE-FIRST GITHUB DARK ==================== */
        :root {
            --bg-canvas: #0d1117;
            --bg-primary: #161b22;
            --bg-tertiary: #21262d;
            --border-default: #30363d;
            --border-muted: #21262d;
            --fg-default: #e6edf3;
            --fg-muted: #7d8590;
            --accent-blue: #2f81f7;
            --accent-green: #3fb950;
            --accent-red: #f85149;
            --accent-orange: #d29922;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --touch-target: 44px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-canvas);
            color: var(--fg-default);
            line-height: 1.4;
            padding-bottom: 80px;
        }

        /* Header */
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-default);
            padding: 12px 16px;
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .header-title {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .back-btn {
            color: var(--fg-default);
            font-size: 1.2rem;
            padding: 10px;
            margin: -10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: var(--touch-target);
            min-height: var(--touch-target);
        }

        .connection-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-connected {
            background: rgba(63, 185, 80, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(63, 185, 80, 0.3);
        }

        .status-disconnected {
            background: rgba(248, 81, 73, 0.15);
            color: var(--accent-red);
            border: 1px solid rgba(248, 81, 73, 0.3);
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 12px;
            margin-top: 12px;
            overflow-x: auto;
            padding-bottom: 4px;
            -webkit-overflow-scrolling: touch;
        }

        .quick-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: 40px;
            color: var(--fg-default);
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .quick-btn:active {
            transform: scale(0.96);
            background: var(--accent-blue);
            border-color: var(--accent-blue);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            padding: 16px;
        }

        .stat-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.7rem;
            color: var(--fg-muted);
            margin-top: 4px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        /* Search Bar */
        .search-bar {
            padding: 0 16px 12px;
        }

        .search-input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: 44px;
            color: var(--fg-default);
            font-size: 0.9rem;
            font-family: inherit;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent-blue);
        }

        .search-wrapper {
            position: relative;
        }

        .search-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--fg-muted);
        }

        /* Loading Indicator */
        .search-loading {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-default);
            border-top-color: var(--accent-blue);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: none;
        }

        @keyframes spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }

        .search-loading.active {
            display: block;
        }

        /* User List */
        .user-list {
            padding: 0 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .user-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            padding: 14px;
            transition: all 0.15s ease;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .user-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .avatar-online {
            background: rgba(63, 185, 80, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(63, 185, 80, 0.3);
        }

        .avatar-offline {
            background: rgba(210, 153, 34, 0.15);
            color: var(--accent-orange);
            border: 1px solid rgba(210, 153, 34, 0.3);
        }

        .avatar-disabled {
            background: rgba(248, 81, 73, 0.15);
            color: var(--accent-red);
            border: 1px solid rgba(248, 81, 73, 0.3);
            opacity: 0.6;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .user-profile {
            font-size: 0.7rem;
            color: var(--fg-muted);
        }

        .user-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        .user-card-details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-muted);
        }

        .detail-item {
            flex: 1;
            min-width: 100px;
        }

        .detail-label {
            font-size: 0.6rem;
            color: var(--fg-muted);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Password Field */
        .password-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
        }

        .password-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            flex-wrap: wrap;
        }

        .password-field {
            flex: 2;
            min-width: 120px;
            padding: 8px 12px;
            background: var(--bg-canvas);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-sm);
            color: var(--fg-default);
            font-size: 0.75rem;
            font-family: monospace;
        }

        .password-toggle {
            flex-shrink: 0;
            padding: 8px 14px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-sm);
            color: var(--fg-default);
            font-size: 0.7rem;
            cursor: pointer;
        }

        @media (max-width: 480px) {
            .password-input-group {
                flex-direction: column;
            }
            .password-field {
                width: 100%;
            }
            .password-toggle {
                width: 100%;
                text-align: center;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--fg-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: 40px;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 200;
            transition: transform 0.3s ease;
            pointer-events: none;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
        }

        /* Filter Drawer */
        .filter-drawer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border-top: 1px solid var(--border-default);
            border-radius: 20px 20px 0 0;
            padding: 20px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            z-index: 150;
        }

        .filter-drawer.open {
            transform: translateY(0);
        }

        .filter-options {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .filter-chip {
            flex: 1;
            padding: 12px;
            background: var(--bg-tertiary);
            border: none;
            border-radius: 40px;
            color: var(--fg-default);
            font-size: 0.85rem;
            text-align: center;
            cursor: pointer;
        }

        .filter-chip.active {
            background: var(--accent-blue);
            color: white;
        }

        .drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 140;
            display: none;
        }

        .drawer-overlay.open {
            display: block;
        }

        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                max-width: 800px;
                margin: 0 auto;
            }
            .user-list {
                max-width: 800px;
                margin: 0 auto;
            }
            .search-bar {
                max-width: 800px;
                margin: 0 auto;
            }
            .quick-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-top">
        <a href="../dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">
            <i class="fas fa-network-wired" style="color: var(--accent-blue);"></i> PPPoE
        </div>
        <div class="connection-status <?php echo $isMikrotikConnected ? 'status-connected' : 'status-disconnected'; ?>">
            <i class="fas <?php echo $isMikrotikConnected ? 'fa-circle-check' : 'fa-exclamation-triangle'; ?>"></i>
            <?php echo $isMikrotikConnected ? 'Online' : 'Offline'; ?>
        </div>
    </div>

    <div class="quick-actions">
        <button class="quick-btn" onclick="addUser()">
            <i class="fas fa-plus" style="color: var(--accent-green);"></i> Tambah
        </button>
        <button class="quick-btn" onclick="location.reload()">
            <i class="fas fa-sync-alt" style="color: var(--accent-blue);"></i> Refresh
        </button>
        <button class="quick-btn" onclick="toggleFilter()">
            <i class="fas fa-filter" style="color: var(--accent-orange);"></i> Filter
        </button>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-number"><?php echo $totalUsers; ?></div>
            <div class="stat-label">Total User</div>
        </div>
        <div class="stat-icon" style="background: rgba(47,129,247,0.1); color: var(--accent-blue);"><i class="fas fa-users"></i></div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-number" style="color: var(--accent-green);"><?php echo $onlineCount; ?></div>
            <div class="stat-label">Online</div>
        </div>
        <div class="stat-icon" style="background: rgba(63,185,80,0.1); color: var(--accent-green);"><i class="fas fa-signal"></i></div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-number" style="color: var(--accent-orange);"><?php echo $offlineCount; ?></div>
            <div class="stat-label">Offline</div>
        </div>
        <div class="stat-icon" style="background: rgba(210,153,34,0.1); color: var(--accent-orange);"><i class="fas fa-circle"></i></div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-number" style="color: var(--accent-red);"><?php echo $disabledCount; ?></div>
            <div class="stat-label">Disabled</div>
        </div>
        <div class="stat-icon" style="background: rgba(248,81,73,0.1); color: var(--accent-red);"><i class="fas fa-ban"></i></div>
    </div>
</div>

<!-- Search Bar -->
<div class="search-bar">
    <div class="search-wrapper">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" class="search-input" placeholder="Cari username... minimal 2 karakter" autocomplete="off">
        <div class="search-loading" id="searchLoading"></div>
    </div>
</div>

<!-- User List -->
<div class="user-list" id="userList">
    <div class="empty-state">
        <i class="fas fa-search"></i>
        <p>Ketik username untuk mencari</p>
        <small>Contoh: "kar", "rtrw", "pelanggan"</small>
    </div>
</div>

<!-- Filter Drawer -->
<div class="drawer-overlay" id="filterOverlay" onclick="closeFilter()"></div>
<div class="filter-drawer" id="filterDrawer">
    <h4 style="margin-bottom: 12px;">Filter Status</h4>
    <div class="filter-options">
        <button class="filter-chip active" data-filter="all">Semua</button>
        <button class="filter-chip" data-filter="online">Online</button>
        <button class="filter-chip" data-filter="offline">Offline</button>
        <button class="filter-chip" data-filter="disabled">Disabled</button>
    </div>
    <button class="filter-chip" onclick="closeFilter()" style="margin-top: 16px;">Tutup</button>
</div>

<!-- Toast -->
<div class="toast" id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toastMsg">Tersimpan</span>
</div>

<!-- Bottom Navigation -->
<?php require_once '../includes/bottom_nav.php'; ?>

<script>
    // DOM Elements
    const searchInput = document.getElementById('searchInput');
    const userListDiv = document.getElementById('userList');
    const searchLoading = document.getElementById('searchLoading');
    let currentFilter = 'all';
    let searchTimeout = null;
    let currentRequest = null; // Untuk abort request sebelumnya

    // Debounce function untuk menghindari terlalu banyak request
    function debounce(func, delay) {
        return function(...args) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // Render user cards ke dalam list
    function renderUsers(users) {
        if (!users || users.length === 0) {
            userListDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <p>Tidak ada user yang ditemukan</p>
                    <small>Coba dengan kata kunci lain</small>
                </div>
            `;
            return;
        }

        let html = '';
        users.forEach((user, index) => {
            const isOnline = user.online;
            const isDisabled = user.disabled;
            const avatarClass = isDisabled ? 'avatar-disabled' : (isOnline ? 'avatar-online' : 'avatar-offline');
            const statusText = isDisabled ? 'Disabled' : (isOnline ? 'Online' : 'Offline');
            const statusColor = isDisabled ? 'red' : (isOnline ? 'green' : 'orange');
            
            html += `
                <div class="user-card" data-username="${user.name.toLowerCase()}">
                    <div class="user-card-header">
                        <div class="user-avatar ${avatarClass}">
                            ${(user.name?.charAt(0) || 'U').toUpperCase()}
                        </div>
                        <div class="user-info">
                            <div class="user-name">${escapeHtml(user.name)}</div>
                            <div class="user-profile">${escapeHtml(user.profile)}</div>
                        </div>
                        <div class="user-status" style="background: rgba(${statusColor === 'green' ? '63,185,80' : (statusColor === 'orange' ? '210,153,34' : '248,81,73')}, 0.15);">
                            <i class="fas fa-circle" style="font-size: 0.5rem; color: var(--accent-${statusColor});"></i>
                            ${statusText}
                        </div>
                    </div>
                    <div class="user-card-details">
                        <div class="detail-item">
                            <div class="detail-label">Status Aktif</div>
                            <div class="detail-value">${isDisabled ? 'Tidak' : 'Ya'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Last Login</div>
                            <div class="detail-value">${user.last_login && user.last_login !== 'never' ? formatDate(user.last_login) : 'Tidak pernah'}</div>
                        </div>
                        <div class="detail-item" style="flex: 2;">
                            <div class="password-container">
                                <div class="password-label"><i class="fas fa-lock"></i> Password</div>
                                <div class="password-input-group">
                                    <input type="password" class="password-field" id="pw_${index}" value="${escapeHtml(user.password)}" readonly>
                                    <button type="button" class="password-toggle" onclick="togglePasswordById('pw_${index}', this)"><i class="fas fa-eye"></i> Show</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        userListDiv.innerHTML = html;
    }

    // Escape HTML untuk keamanan
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Format tanggal
    function formatDate(dateStr) {
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        } catch(e) {
            return dateStr;
        }
    }

    // Search ke backend
    async function searchUsers(query) {
        // Hanya search jika minimal 2 karakter
        if (!query || query.length < 2) {
            userListDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>Ketik minimal 2 karakter untuk mencari</p>
                    <small>Contoh: "kar", "rtrw", "pelanggan"</small>
                </div>
            `;
            return;
        }

        // Tampilkan loading
        searchLoading.classList.add('active');
        
        // Abort request sebelumnya jika ada
        if (currentRequest) {
            currentRequest.abort();
        }
        
        // Buat AbortController baru
        const controller = new AbortController();
        currentRequest = controller;
        
        try {
            const response = await fetch(`api_search_users.php?q=${encodeURIComponent(query)}&filter=${currentFilter}`, {
                signal: controller.signal
            });
            const data = await response.json();
            
            if (data.success) {
                renderUsers(data.users);
            } else {
                userListDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Gagal mengambil data</p>
                        <small>${data.message || 'Silakan coba lagi'}</small>
                    </div>
                `;
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('Request dibatalkan');
                return;
            }
            console.error('Search error:', error);
            userListDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-wifi"></i>
                    <p>Gagal terhubung ke server</p>
                    <small>Periksa koneksi Anda</small>
                </div>
            `;
        } finally {
            searchLoading.classList.remove('active');
            if (currentRequest === controller) {
                currentRequest = null;
            }
        }
    }

    // Debounced search
    const debouncedSearch = debounce(searchUsers, 500);

    // Event listener untuk search input
    searchInput?.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        debouncedSearch(query);
    });

    // Filter Function
    function toggleFilter() {
        document.getElementById('filterDrawer').classList.add('open');
        document.getElementById('filterOverlay').classList.add('open');
    }

    function closeFilter() {
        document.getElementById('filterDrawer').classList.remove('open');
        document.getElementById('filterOverlay').classList.remove('open');
    }

    // Filter chips
    document.querySelectorAll('.filter-chip[data-filter]').forEach(chip => {
        chip.addEventListener('click', function() {
            document.querySelectorAll('.filter-chip[data-filter]').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            
            // Re-search dengan filter baru
            const currentQuery = searchInput?.value.trim() || '';
            if (currentQuery.length >= 2) {
                searchUsers(currentQuery);
            }
            closeFilter();
        });
    });

    // Toggle Password
    function togglePasswordById(inputId, btn) {
        const field = document.getElementById(inputId);
        if (!field) return;
        
        const isHidden = field.type === 'password';
        field.type = isHidden ? 'text' : 'password';
        btn.innerHTML = isHidden ? '<i class="fas fa-eye-slash"></i> Hide' : '<i class="fas fa-eye"></i> Show';
        
        if (window.navigator && window.navigator.vibrate) {
            window.navigator.vibrate(20);
        }
    }

    // Show Toast
    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        const icon = toast.querySelector('i');
        const msgSpan = document.getElementById('toastMsg');
        
        icon.className = isError ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
        icon.style.color = isError ? 'var(--accent-red)' : 'var(--accent-green)';
        msgSpan.textContent = message;
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 2500);
    }

    function addUser() {
        showToast('Fitur tambah user akan segera hadir', false);
    }

    // Haptic feedback
    function vibrate() {
        if (window.navigator && window.navigator.vibrate) {
            window.navigator.vibrate(50);
        }
    }
    
    document.querySelectorAll('button, .quick-btn').forEach(el => {
        el.addEventListener('click', vibrate);
    });
</script>
</body>
</html>