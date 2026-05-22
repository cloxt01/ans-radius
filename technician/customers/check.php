<?php
/**
 * MikroTik PPPoE Management - GitHub Dark Theme
 */

require_once '../../includes/auth.php';
requireTechnicianLogin();

$pageTitle = 'PPPoE Management';

// Get MikroTik settings
$mikrotikSettings = getMikrotikSettings();

// Get MikroTik users (secrets)
$mikrotikUsers = mikrotikGetPppoeUsers();
$totalUsers = count($mikrotikUsers);

// Get active PPPoE sessions
$activeSessions = mikrotikGetActiveSessionsAllRouter();
$onlineCount = count($activeSessions);
$onlineUsernames = array_column($activeSessions, 'name');

// Calculate stats
$disabledCount = count(array_filter($mikrotikUsers, fn($u) => ($u['disabled'] ?? 'false') === 'true'));
$offlineCount = $totalUsers - $onlineCount;

// Get MikroTik profiles
$mikrotikProfiles = mikrotikGetProfiles();
if (empty($mikrotikProfiles)) {
    $mikrotikProfiles = [['name' => 'default']];
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d1117">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Teknisi</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==================== GITHUB DARK THEME ==================== */
        :root {
            --bg-canvas: #0d1117;
            --bg-inset: #010409;
            --bg-primary: #161b22;
            --bg-secondary: #0d1117;
            --bg-tertiary: #21262d;
            --border-default: #30363d;
            --border-muted: #21262d;
            --fg-default: #e6edf3;
            --fg-muted: #7d8590;
            --fg-subtle: #6e7681;
            --accent-blue: #2f81f7;
            --accent-blue-hover: #58a6ff;
            --accent-green: #3fb950;
            --accent-red: #f85149;
            --accent-orange: #d29922;
            --accent-purple: #a371f7;
            --shadow-small: 0 0 0 1px rgba(255,255,255,0.05);
            --shadow-medium: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-large: 0 8px 24px rgba(0,0,0,0.4);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --transition-fast: 0.15s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', sans-serif;
            background: var(--bg-canvas);
            color: var(--fg-default);
            line-height: 1.5;
            padding-bottom: 76px;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--border-default);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--fg-muted);
        }

        /* ==================== HEADER ==================== */
        .header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-default);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header .title {
            font-size: 1rem;
            font-weight: 600;
        }

        .back-btn {
            color: var(--fg-default);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            transition: background var(--transition-fast);
        }

        .back-btn:hover {
            background: var(--bg-tertiary);
        }

        /* ==================== CONTAINER ==================== */
        .container {
            padding: 20px;
            max-width: 1280px;
            margin: 0 auto;
        }

        /* ==================== ALERT ==================== */
        .alert {
            padding: 14px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid;
            font-size: 0.85rem;
        }

        .alert-warning {
            background: rgba(210, 153, 34, 0.1);
            border-color: rgba(210, 153, 34, 0.3);
            color: var(--accent-orange);
        }

        .alert-warning a {
            color: var(--accent-blue);
        }

        /* ==================== STATS GRID ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all var(--transition-fast);
        }

        .stat-card:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
        }

        .stat-info h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-info p {
            font-size: 0.7rem;
            color: var(--fg-muted);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.blue { color: var(--accent-blue); background: rgba(47, 129, 247, 0.1); }
        .stat-icon.green { color: var(--accent-green); background: rgba(63, 185, 80, 0.1); }
        .stat-icon.orange { color: var(--accent-orange); background: rgba(210, 153, 34, 0.1); }
        .stat-icon.red { color: var(--accent-red); background: rgba(248, 81, 73, 0.1); }

        /* ==================== CARD ==================== */
        .card {
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-muted);
        }

        .card-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--fg-default);
        }

        .card-title i {
            color: var(--accent-blue);
            margin-right: 8px;
        }

        /* ==================== SEARCH ==================== */
        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-wrapper i {
            position: absolute;
            left: 12px;
            color: var(--fg-muted);
            font-size: 0.8rem;
        }

        .search-wrapper .form-control {
            padding-left: 36px;
            width: 250px;
        }

        /* ==================== TABLE ==================== */
        .table-responsive {
            overflow-x: auto;
            padding: 0 20px 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead tr {
            border-bottom: 1px solid var(--border-default);
        }

        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--fg-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-muted);
            font-size: 0.85rem;
        }

        .data-table tbody tr:hover {
            background: var(--bg-tertiary);
        }

        /* ==================== USER AVATAR ==================== */
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
            font-weight: 700;
            font-size: 0.85rem;
        }

        .avatar.online {
            background: rgba(63, 185, 80, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(63, 185, 80, 0.3);
        }

        .avatar.offline {
            background: rgba(210, 153, 34, 0.15);
            color: var(--accent-orange);
            border: 1px solid rgba(210, 153, 34, 0.3);
        }

        .avatar.disabled {
            background: rgba(248, 81, 73, 0.15);
            color: var(--accent-red);
            border: 1px solid rgba(248, 81, 73, 0.3);
            opacity: 0.6;
        }

        .user-details strong {
            font-size: 0.85rem;
            display: block;
        }

        .user-details small {
            font-size: 0.7rem;
            color: var(--fg-muted);
        }

        /* ==================== BADGES ==================== */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(63, 185, 80, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(63, 185, 80, 0.3);
        }

        .badge-warning {
            background: rgba(210, 153, 34, 0.15);
            color: var(--accent-orange);
            border: 1px solid rgba(210, 153, 34, 0.3);
        }

        .badge-danger {
            background: rgba(248, 81, 73, 0.15);
            color: var(--accent-red);
            border: 1px solid rgba(248, 81, 73, 0.3);
        }

        .badge-info {
            background: rgba(47, 129, 247, 0.15);
            color: var(--accent-blue);
            border: 1px solid rgba(47, 129, 247, 0.3);
        }

        .badge-muted {
            background: var(--bg-tertiary);
            color: var(--fg-muted);
            border: 1px solid var(--border-default);
        }

        /* ==================== LAST LOGIN ==================== */
        .last-login {
            font-size: 0.75rem;
            color: var(--fg-muted);
        }

        .last-login i {
            margin-right: 4px;
            font-size: 0.7rem;
        }

        /* ==================== EMPTY STATE ==================== */
        .empty-state {
            text-align: center;
            padding: 60px 20px !important;
            color: var(--fg-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 0.9rem;
            margin-bottom: 4px;
        }

        .empty-state small {
            font-size: 0.75rem;
        }

        /* ==================== RESPONSIVE TABLE ==================== */
        @media (max-width: 768px) {
            .data-table thead {
                display: none;
            }

            .data-table tbody tr {
                display: block;
                border: 1px solid var(--border-default);
                border-radius: var(--radius-md);
                padding: 12px;
                margin-bottom: 12px;
                background: var(--bg-primary);
            }

            .data-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border: none;
            }

            .data-table td[data-label]::before {
                content: attr(data-label) ": ";
                font-weight: 600;
                color: var(--fg-muted);
                margin-right: 12px;
            }

            .search-wrapper .form-control {
                width: 100%;
            }

            .card-header {
                flex-direction: column;
                align-items: stretch;
            }
        }

        @media (min-width: 769px) {
            .data-table thead {
                display: table-header-group;
            }
            .data-table tbody tr {
                display: table-row;
                border: none;
                background: transparent;
                margin: 0;
            }
            .data-table td {
                display: table-cell;
                padding: 12px 16px;
            }
            .data-table td[data-label]::before {
                content: none;
            }
        }

        /* ==================== FORM CONTROLS ==================== */
        .form-control {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg-canvas);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-sm);
            color: var(--fg-default);
            font-size: 0.85rem;
            transition: all var(--transition-fast);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(47, 129, 247, 0.1);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="title">
            <i class="fas fa-network-wired" style="margin-right: 8px; color: var(--accent-blue);"></i>
            <?php echo htmlspecialchars($pageTitle); ?>
        </div>
        <a href="../dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="container">
        <!-- Warning Connection -->
        <?php if (!mikrotikConnect()): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Gagal terhubung ke MikroTik!</strong>
                <p style="margin: 4px 0 0 0; font-size: 0.75rem;">
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

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i> Daftar PPPoE User
                </h3>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mikrotikUsers)): ?>
                            <tr class="empty-state">
                                <td colspan="5">
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
                                $statusClass = $isDisabled ? 'disabled' : ($isOnline ? 'online' : 'offline');
                                $statusIcon = $isDisabled ? 'fa-ban' : 'fa-circle';
                            ?>
                            <tr>
                                <td data-label="Username">
                                    <div class="user-avatar">
                                        <div class="avatar <?php echo $statusClass; ?>">
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
                                            ? '<i class="fas fa-clock"></i> ' . date('d/m/Y H:i', strtotime($user['last-login'])) 
                                            : '<i class="fas fa-minus-circle"></i> Tidak pernah'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <?php require_once '../includes/bottom_nav.php'; ?>

    <script>
        // Search functionality
        document.getElementById('searchUser')?.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');
            
            rows.forEach(row => {
                if (row.classList.contains('empty-state')) return;
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        });
    </script>
</body>
</html>