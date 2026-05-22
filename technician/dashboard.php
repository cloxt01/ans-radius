<?php
require_once '../includes/auth.php';
requireTechnicianLogin();

$pageTitle = 'Dashboard Teknisi';
$tech = $_SESSION['technician'];

// Get Task Summary
// 1. Pending Trouble Tickets
$pendingTickets = fetchOne("SELECT COUNT(*) as total FROM trouble_tickets WHERE technician_id = ? AND status IN ('pending', 'in_progress')", [$tech['id']]);

// 2. Pending Installations (PSB)
$pendingInstalls = fetchOne("SELECT COUNT(*) as total FROM customers WHERE installed_by = ? AND status = 'registered'", [$tech['id']]);

// 3. Today's Completed Tasks
$todayCompleted = fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM trouble_tickets WHERE technician_id = ? AND status = 'resolved' AND DATE(resolved_at) = CURDATE()) +
        (SELECT COUNT(*) FROM customers WHERE installed_by = ? AND status = 'active' AND DATE(installation_date) = CURDATE()) as total
", [$tech['id'], $tech['id']]);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d1117">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <title>Dashboard - <?php echo htmlspecialchars($tech['name']); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==================== GITHUB DARK THEME - TEKNISI DASHBOARD ==================== */
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
            --accent-yellow: #e3b341;
            --accent-purple: #a371f7;
            --accent-cyan: #79c0ff;
            --shadow-small: 0 0 0 1px rgba(255,255,255,0.05);
            --shadow-medium: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-large: 0 8px 24px rgba(0,0,0,0.4);
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

        .profile-info h2 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .profile-info p {
            font-size: 0.7rem;
            color: var(--fg-muted);
        }

        .btn-logout {
            color: var(--accent-red);
            text-decoration: none;
            font-size: 1.2rem;
            padding: 8px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .btn-logout:hover {
            background: var(--bg-tertiary);
        }

        /* ==================== CONTAINER ==================== */
        .container {
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* ==================== SECTION TITLE ==================== */
        .section-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--fg-muted);
            margin-bottom: 12px;
        }

        /* ==================== STATS GRID ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: 16px;
            padding: 16px;
            text-align: center;
            transition: all 0.2s;
        }

        .stat-card:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
        }

        .stat-card.span-2 {
            grid-column: span 2;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-number.warning { color: var(--accent-orange); }
        .stat-number.primary { color: var(--accent-blue); }
        .stat-number.success { color: var(--accent-green); }

        .stat-label {
            font-size: 0.7rem;
            color: var(--fg-muted);
        }

        /* ==================== MENU GRID ==================== */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 8px;
        }

        .menu-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: 16px;
            padding: 24px 16px;
            text-align: center;
            text-decoration: none;
            color: var(--fg-default);
            transition: all 0.2s;
        }

        .menu-card:active {
            transform: scale(0.97);
        }

        .menu-card:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
        }

        .menu-icon {
            font-size: 2rem;
            margin-bottom: 12px;
            display: inline-block;
        }

        .menu-icon.blue { color: var(--accent-blue); }
        .menu-icon.orange { color: var(--accent-orange); }
        .menu-icon.cyan { color: var(--accent-cyan); }
        .menu-icon.red { color: var(--accent-red); }
        .menu-icon.purple { color: var(--accent-purple); }

        .menu-title {
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 480px) {
            .container {
                padding: 16px;
            }
            
            .menu-card {
                padding: 20px 12px;
            }
            
            .menu-icon {
                font-size: 1.6rem;
            }
            
            .stat-number {
                font-size: 1.6rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .stat-card,
            .menu-card {
                transition: none;
            }
            .stat-card:hover,
            .menu-card:hover {
                transform: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="profile-info">
            <h2>Hai, <?php echo htmlspecialchars($tech['name']); ?> 👋</h2>
            <p>Teknisi Lapangan</p>
        </div>
        <a href="logout.php" class="btn-logout" onclick="return confirm('Keluar dari aplikasi?');">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="section-title">Ringkasan Tugas</div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number warning">
                    <?php echo $pendingTickets['total']; ?>
                </div>
                <div class="stat-label">Tiket Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number primary">
                    <?php echo $pendingInstalls['total']; ?>
                </div>
                <div class="stat-label">Pasang Baru</div>
            </div>
            <div class="stat-card span-2">
                <div class="stat-number success">
                    <?php echo $todayCompleted['total']; ?>
                </div>
                <div class="stat-label">Selesai Hari Ini</div>
            </div>
        </div>

        <div class="section-title">Menu Utama</div>
        
        <div class="menu-grid">
            <a href="tasks/index.php" class="menu-card">
                <i class="fas fa-clipboard-list menu-icon blue"></i>
                <div class="menu-title">Daftar Tugas</div>
            </a>
            <a href="map/index.php" class="menu-card">
                <i class="fas fa-map-marked-alt menu-icon orange"></i>
                <div class="menu-title">Peta Lokasi</div>
            </a>
            <a href="devices/search.php" class="menu-card">
                <i class="fas fa-microchip menu-icon cyan"></i>
                <div class="menu-title">Cek Perangkat</div>
            </a>
            <a href="customers/check.php" class="menu-card">
                <i class="fas fa-users menu-icon red"></i>
                <div class="menu-title">Cek Pelanggan</div>
            </a>
            <a href="profile.php" class="menu-card">
                <i class="fas fa-user-cog menu-icon purple"></i>
                <div class="menu-title">Profil Saya</div>
            </a>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <?php require_once 'includes/bottom_nav.php'; ?>
</body>
</html>