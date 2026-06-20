<?php
/**
 * Agent Portal Layout Template
 * Base layout for agent/reseller portal pages
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
$pageTitle = $pageTitle ?? APP_NAME . ' - Portal Agen';
$pageDescription = $pageDescription ?? '';

// Check if agent is logged in (Sesuaikan fungsi ini dengan sistem auth Anda)
if (!isAgentLoggedIn()) {
    header('Location: login.php');
    exit;
}

$agent = getCurrentAgent();

// Capture the content from the including file
ob_start();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            /* Dark Neon Theme */
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-card: rgba(20, 20, 35, 0.8);
            --bg-sidebar: #0d0d15;

            /* Neon Colors */
            --neon-cyan: #00f5ff;
            --neon-purple: #bf00ff;
            --neon-pink: #ff00aa;
            --neon-green: #00ff88;
            --neon-orange: #ff6b35;
            --neon-red: #ff4757;

            /* Gradients (Disesuaikan sedikit untuk Agen jika ingin beda nuansa, default tetap sama) */
            --gradient-primary: linear-gradient(135deg, #00f5ff 0%, #bf00ff 100%);
            --gradient-success: linear-gradient(135deg, #00ff88 0%, #00d4aa 100%);
            --gradient-warning: linear-gradient(135deg, #ff6b35 0%, #ff8c42 100%);

            /* Text */
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.6);
            --text-muted: rgba(255, 255, 255, 0.4);

            /* Border */
            --border-color: rgba(255, 255, 255, 0.08);
            --border-glow: rgba(0, 245, 255, 0.3);

            /* Shadows */
            --shadow-neon: 0 0 20px rgba(0, 245, 255, 0.3);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.4);

            /* Sidebar */
            --sidebar-width: 260px;
            --sidebar-collapsed: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }

        a {
            color: var(--neon-cyan);
            text-decoration: none;
            transition: all 0.3s;
        }

        a:hover {
            color: var(--neon-purple);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo {
            font-size: 1.3rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
        }

        .sidebar-nav {
            padding: 20px 0;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--text-secondary);
            transition: all 0.3s;
            cursor: pointer;
            border-left: 3px solid transparent;
        }

        .menu-item:hover, .menu-item.active {
            background: rgba(0, 245, 255, 0.1);
            color: var(--neon-cyan);
            border-left-color: var(--neon-cyan);
        }

        .menu-item i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .header-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1.5rem;
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-card);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--neon-cyan);
        }

        /* Stats, Buttons, Forms, Tables, Alerts (Standardized with previous) */
        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 15px; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-neon); }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .stat-icon.cyan { background: rgba(0, 245, 255, 0.2); color: var(--neon-cyan); }
        .stat-icon.purple { background: rgba(191, 0, 255, 0.2); color: var(--neon-purple); }
        .stat-icon.green { background: rgba(0, 255, 136, 0.2); color: var(--neon-green); }
        .stat-icon.orange { background: rgba(255, 107, 53, 0.2); color: var(--neon-orange); }
        .stat-info h3 { font-size: 1.8rem; font-weight: 700; margin-bottom: 5px; }
        .stat-info p { color: var(--text-secondary); font-size: 0.9rem; }

        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: var(--gradient-primary); color: #fff; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: var(--shadow-neon); }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); }
        .form-control { width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 1rem; transition: all 0.3s; }
        .form-control:focus { outline: none; border-color: var(--neon-cyan); background: rgba(255, 255, 255, 0.08); }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead { background: var(--bg-secondary); }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .data-table th { font-weight: 600; color: var(--text-secondary); font-size: 0.9rem; text-transform: uppercase; }
        .data-table tbody tr:hover { background: rgba(255, 255, 255, 0.02); }

        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge-success { background: rgba(0, 255, 136, 0.2); color: var(--neon-green); border: 1px solid var(--neon-green); }
        .badge-warning { background: rgba(255, 107, 53, 0.2); color: var(--neon-orange); border: 1px solid var(--neon-orange); }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(0, 255, 136, 0.1); border: 1px solid var(--neon-green); color: var(--neon-green); }
        .alert-error { background: rgba(255, 71, 87, 0.1); border: 1px solid var(--neon-red); color: var(--neon-red); }

        /* Responsive Mobile Adjustments */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; }
            .data-table { display: block; overflow-x: auto; white-space: nowrap; }
            body { padding-bottom: 80px; }
        }

        /* Mobile Bottom Navigation (Agent Specific) */
        .mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0; left: 0; width: 100%;
            background: rgba(18, 18, 26, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-top: 1px solid var(--border-color);
            z-index: 2000;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }

        .mobile-nav-inner { display: flex; justify-content: space-around; align-items: center; height: 65px; }
        .mobile-nav-item { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-secondary); text-decoration: none; gap: 4px; height: 100%; transition: all 0.3s; }
        .mobile-nav-item i { font-size: 1.25rem; transition: transform 0.3s; }
        .mobile-nav-item span { font-size: 0.7rem; font-weight: 600; }
        .mobile-nav-item.active { color: var(--neon-cyan); }
        .mobile-nav-item.active i { transform: translateY(-2px); text-shadow: var(--shadow-neon); }

        @media (max-width: 768px) {
            .mobile-bottom-nav { display: block; }
            .header-actions .menu-toggle { display: none !important; }
        }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-store" style="font-size: 1.5rem; color: var(--neon-cyan);"></i>
        <span class="sidebar-logo">PORTAL AGEN</span>
    </div>

    <div class="sidebar-nav">
        <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>

        <a href="customers.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Pelanggan Saya</span>
        </a>

        <a href="wallet.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'wallet.php' ? 'active' : ''; ?>">
            <i class="fas fa-wallet"></i>
            <span>Komisi & Saldo</span>
        </a>

        <a href="reports.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Laporan Transaksi</span>
        </a>

        <div style="margin-top: 20px; border-top: 1px solid var(--border-color);"></div>

        <a href="profile.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-cog"></i>
            <span>Pengaturan Profil</span>
        </a>

        <a href="logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<nav class="mobile-bottom-nav">
    <div class="mobile-nav-inner">
        <a href="dashboard.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Beranda</span>
        </a>
        <a href="customers.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>User</span>
        </a>
        <a href="wallet.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'wallet.php' ? 'active' : ''; ?>">
            <i class="fas fa-wallet"></i>
            <span>Komisi</span>
        </a>
        <a href="profile.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-cog"></i>
            <span>Profil</span>
        </a>
    </div>
</nav>

<div class="main-content">
    <div class="header">
        <div class="header-title">
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        </div>
        <div class="header-actions">
            <button class="menu-toggle" onclick="toggleSidebar()" style="display: none; background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.5rem;">
                <i class="fas fa-bars"></i>
            </button>
            <div style="text-align: right; line-height: 1.2;">
                    <span style="color: var(--text-primary); font-weight: 600; display: block;">
                        <?php echo htmlspecialchars($agent['name']); ?>
                    </span>
                <span style="color: var(--neon-cyan); font-size: 0.8rem;">
                        <i class="fas fa-store"></i> Agen Mitra
                    </span>
            </div>
        </div>
    </div>

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

    <?php echo $content; ?>

</div>

<script>
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
    }

    window.addEventListener('load', function() {
        const menuToggle = document.querySelector('.menu-toggle');
        if (window.innerWidth <= 768) {
            menuToggle.style.display = 'block';
        }

        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                menuToggle.style.display = 'block';
            } else {
                menuToggle.style.display = 'none';
                document.querySelector('.sidebar').classList.remove('active');
            }
        });
    });
</script>
</body>
</html>