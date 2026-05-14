<?php
/**
 * Layout Template - Elegant Dark Minimalis Theme
 * Base layout for all pages
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get page title
$appName = getSetting('app_name', 'ANS RADIUS');
$pageTitle = $pageTitle ?? $appName;
$pageDescription = $pageDescription ?? '';

// Phase 3: Multi-router support
$currentRouter = getMikrotikSettings();
$allRouters = getAllRouters();

// Handle global router switching via GET (optional but convenient)
if (isset($_GET['switch_router'])) {
    $swId = (int)$_GET['switch_router'];
    $_SESSION['active_router_id'] = $swId;
    $currentUrl = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: " . $currentUrl);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($appName); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" type="text/css">

    <style>
        :root {
            /* GitHub Dark Theme */
            --bg-canvas: #0d1117;
            --bg-primary: #0d1117;
            --bg-secondary: #161b22;
            --bg-tertiary: #21262d;
            --bg-card: #161b22;
            --bg-sidebar: rgba(13, 17, 23, 0.95);
            --bg-inset: #010409;
            
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --text-muted: #6e7681;
            --text-link: #58a6ff;
            
            --border-color: #30363d;
            --border-light: #21262d;
            
            --accent-blue: #58a6ff;
            --accent-green: #3fb950;
            --accent-red: #f85149;
            --accent-orange: #d29922;
            --accent-purple: #bc8cff;
            --accent-pink: #db61a2;
            
            --gradient-primary: linear-gradient(135deg, #58a6ff 0%, #1f6feb 100%);
            --gradient-success: linear-gradient(135deg, #3fb950 0%, #2ea043 100%);
            --gradient-warning: linear-gradient(135deg, #d29922 0%, #9e6a03 100%);
            
            --shadow-sm: 0 1px 0 rgba(0,0,0,0.2);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.4);
            
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            
            --sidebar-width: 260px;
            
            --transition-fast: 0.1s ease;
            --transition-base: 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-canvas);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.5;
            font-size: 14px;
        }

        /* Custom Scrollbar - GitHub Style */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        a {
            color: var(--text-link);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        a:hover {
            text-decoration: underline;
        }

        /* Sidebar - GitHub Style */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            transition: transform var(--transition-base);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-header a {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-brand-logo {
            width: 32px;
            height: 32px;
            display: block;
        }

        .sidebar-brand-text {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .sidebar-nav {
            padding: 16px 8px;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .nav-section {
            margin-bottom: 24px;
        }

        .nav-section-title {
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            margin: 2px 0;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            transition: all var(--transition-fast);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .menu-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .menu-item.active {
            background: var(--bg-tertiary);
            color: var(--text-link);
        }

        .menu-item i {
            width: 20px;
            font-size: 14px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        /* Header - GitHub Style */
        .header {
            position: sticky;
            top: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            z-index: 100;
        }

        .header-title h1 {
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -0.3px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        /* Cards - GitHub Style */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            transition: border-color var(--transition-fast);
        }

        .card:hover {
            border-color: var(--text-muted);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-light);
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-body {
            padding: 20px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: border-color var(--transition-fast);
        }

        .stat-card:hover {
            border-color: var(--text-muted);
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .stat-info p {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue { color: var(--accent-blue); background: rgba(88, 166, 255, 0.1); }
        .stat-icon.green { color: var(--accent-green); background: rgba(63, 185, 80, 0.1); }
        .stat-icon.red { color: var(--accent-red); background: rgba(248, 81, 73, 0.1); }
        .stat-icon.orange { color: var(--accent-orange); background: rgba(210, 153, 34, 0.1); }
        .stat-icon.purple { color: var(--accent-purple); background: rgba(188, 140, 255, 0.1); }

        /* Buttons - GitHub Style */
        .btn {
            padding: 6px 16px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid;
            background: transparent;
        }

        .btn-primary {
            background: var(--gradient-primary);
            border-color: rgba(88, 166, 255, 0.3);
            color: #fff;
        }

        .btn-primary:hover {
            background: #1f6feb;
            border-color: var(--accent-blue);
        }

        .btn-secondary {
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--bg-tertiary);
            border-color: var(--text-muted);
        }

        .btn-success {
            background: var(--gradient-success);
            border-color: rgba(63, 185, 80, 0.3);
            color: #fff;
        }

        .btn-danger {
            background: var(--gradient-warning);
            border-color: rgba(210, 153, 34, 0.3);
            color: #fff;
        }

        .btn-sm {
            padding: 4px 12px;
            font-size: 12px;
        }

        /* Forms - GitHub Style */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 14px;
            transition: all var(--transition-fast);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(88, 166, 255, 0.2);
        }

        select.form-control {
            background: var(--bg-primary);
            cursor: pointer;
        }

        /* Tables - GitHub Style */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead tr {
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-light);
            font-size: 14px;
        }

        .data-table tbody tr:hover {
            background: var(--bg-tertiary);
        }

        /* Badges - GitHub Style */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
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
            background: rgba(88, 166, 255, 0.15);
            color: var(--accent-blue);
            border: 1px solid rgba(88, 166, 255, 0.3);
        }

        .badge-muted {
            background: var(--bg-tertiary);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }

        /* Alerts - GitHub Style */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(63, 185, 80, 0.1);
            border: 1px solid rgba(63, 185, 80, 0.3);
            color: var(--accent-green);
        }

        .alert-error, .alert-danger {
            background: rgba(248, 81, 73, 0.1);
            border: 1px solid rgba(248, 81, 73, 0.3);
            color: var(--accent-red);
        }

        .alert-info {
            background: rgba(88, 166, 255, 0.1);
            border: 1px solid rgba(88, 166, 255, 0.3);
            color: var(--accent-blue);
        }

        /* Modal - GitHub Style */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 {
            font-size: 16px;
            font-weight: 600;
        }

        .modal-header .close {
            font-size: 24px;
            cursor: pointer;
            color: var(--text-muted);
            transition: color var(--transition-fast);
        }

        .modal-header .close:hover {
            color: var(--text-primary);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        /* Loading Overlay */
        .global-loading-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(13, 17, 23, 0.9);
            z-index: 99999;
        }

        .global-loading-overlay.active {
            display: flex;
        }

        .global-loading-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .global-loading-spinner {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            border-top-color: var(--accent-blue);
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header-title h1 {
                font-size: 18px;
            }
            
            .menu-toggle {
                display: block !important;
            }
            
            .bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                background: var(--bg-secondary);
                border-top: 1px solid var(--border-color);
                display: flex;
                justify-content: space-around;
                padding: 8px 0;
                z-index: 1000;
            }
            
            .nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
                font-size: 11px;
                color: var(--text-secondary);
            }
            
            .nav-item.active {
                color: var(--accent-blue);
            }
            
            .nav-item i {
                font-size: 18px;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
        }

        @media (min-width: 769px) {
            .bottom-nav,
            .menu-toggle {
                display: none !important;
            }
        }

        /* Utility Classes */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .page-content {
            padding: 24px;
        }
    </style>
    <script>
        // Apply theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
</head>

<body>
    <?php if (isAdminLoggedIn()): ?>
        <!-- Mobile Bottom Navigation -->
        <div class="bottom-nav">
            <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/customers.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Pelanggan</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/pay.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) === 'pay.php' || basename($_SERVER['PHP_SELF']) === 'pay_process.php') ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>Bayar</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/menu.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'menu.php' ? 'active' : ''; ?>">
                <i class="fas fa-bars"></i>
                <span>Menu</span>
            </a>
        </div>

        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <!-- Sidebar -->
        <div class="sidebar" id="mainSidebar">
            <div class="sidebar-header">
                <a href="<?php echo APP_URL; ?>">
                    <img src="<?php echo APP_URL; ?>/assets/icons/icon.png" class="sidebar-brand-logo" alt="Icon">
                    <span class="sidebar-brand-text"><?php echo htmlspecialchars($appName); ?></span>
                </a>
            </div>

            <div class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Overview</div>
                    <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/customers.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Pelanggan</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/packages.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'packages.php' ? 'active' : ''; ?>">
                        <i class="fas fa-box"></i>
                        <span>Paket Layanan</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/invoices.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'invoices.php' ? 'active' : ''; ?>">
                        <i class="fas fa-file-invoice"></i>
                        <span>Invoice</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Network</div>
                    <div class="menu-item" onclick="toggleSubmenu(this)">
                        <i class="fas fa-network-wired"></i>
                        <span>PPPoE</span>
                        <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 10px;"></i>
                    </div>
                    <div class="submenu" style="display: none; padding-left: 32px;">
                        <a href="<?php echo APP_URL; ?>/admin/mikrotik.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'mikrotik.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i>
                            <span>PPPoE User</span>
                        </a>
                        <a href="<?php echo APP_URL; ?>/admin/pppoe-active.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'pppoe-active.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plug"></i>
                            <span>Active Sessions</span>
                        </a>
                        <a href="<?php echo APP_URL; ?>/admin/pppoe-profile.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'pppoe-profile.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Profiles</span>
                        </a>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="<?php echo APP_URL; ?>/admin/genieacs.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'genieacs.php' ? 'active' : ''; ?>">
                        <i class="fas fa-satellite-dish"></i>
                        <span>GenieACS</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/trouble.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'trouble.php' ? 'active' : ''; ?>">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Gangguan</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/technicians.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'technicians.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tools"></i>
                        <span>Teknisi</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="<?php echo APP_URL; ?>/admin/settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/update.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'update.php' ? 'active' : ''; ?>">
                        <i class="fas fa-sync-alt"></i>
                        <span>Update</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/routers.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'routers.php' ? 'active' : ''; ?>">
                        <i class="fas fa-server"></i>
                        <span>Manajemen Router</span>
                    </a>
                </div>

                <div style="margin-top: auto; border-top: 1px solid var(--border-color); margin-top: 16px; padding-top: 8px;">
                    <a href="<?php echo APP_URL; ?>/admin/logout.php" class="menu-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (isAdminLoggedIn()): ?>
            <div class="header">
                <div class="header-title">
                    <button class="menu-toggle" onclick="toggleSidebar()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; margin-right: 12px;">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                </div>
                <div class="header-actions">
                    <div class="router-switcher">
                        <?php if (count($allRouters) > 1): ?>
                            <select onchange="window.location.href='?switch_router=' + this.value" 
                                style="background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 6px 10px; border-radius: var(--radius-sm); font-size: 12px; cursor: pointer;">
                                <?php foreach ($allRouters as $r): ?>
                                    <option value="<?php echo $r['id']; ?>" <?php echo $currentRouter['id'] == $r['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($r['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    
                    <span class="badge badge-muted">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars(getCurrentAdmin()['username']); ?>
                    </span>
                    
                    <?php if (count($allRouters) > 0): ?>
                        <span class="badge badge-info">
                            <i class="fas fa-server"></i> <?php echo htmlspecialchars($currentRouter['name'] ?? 'Default'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="page-content">
            <!-- Flash Messages -->
            <?php if (hasFlash('success')): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars(getFlash('success')); ?>
                </div>
            <?php endif; ?>

            <?php if (hasFlash('error')): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars(getFlash('error')); ?>
                </div>
            <?php endif; ?>

            <?php if (hasFlash('info')): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo htmlspecialchars(getFlash('info')); ?>
                </div>
            <?php endif; ?>

            <!-- Page Content -->
            <?php echo isset($content) ? $content : ''; ?>
        </div>
    </div>

    <!-- Global Loading -->
    <div id="globalFormLoading" class="global-loading-overlay">
        <div class="global-loading-box">
            <div class="global-loading-spinner"></div>
            <span>Memproses...</span>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/umd/simple-datatables.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function toggleSubmenu(el) {
            const submenu = el.nextElementSibling;
            const icon = el.querySelector('.fa-chevron-down');
            if (submenu.style.display === 'none') {
                submenu.style.display = 'block';
                icon.style.transform = 'rotate(180deg)';
            } else {
                submenu.style.display = 'none';
                icon.style.transform = 'rotate(0deg)';
            }
        }

        // Global form loading
        const overlay = document.getElementById('globalFormLoading');
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form instanceof HTMLFormElement && form.dataset.noLoading !== 'true') {
                overlay.classList.add('active');
            }
        });

        // Initialize submenus
        document.querySelectorAll('.submenu').forEach(sub => {
            sub.style.display = 'none';
        });

        // Close sidebar on overlay click
        document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
            document.getElementById('mainSidebar')?.classList.remove('active');
            this.classList.remove('active');
        });
    </script>
</body>

</html>