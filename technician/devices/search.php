<?php
require_once '../../includes/auth.php';
requireTechnicianLogin();

$pageTitle = 'Cari Perangkat';
$tech = $_SESSION['technician'];
$results = [];
$search = $_GET['q'] ?? '';

if (!empty($search)) {
    // Search in local DB first to get PPPoE username
    $results = fetchAll("
        SELECT id, name, pppoe_username, address, phone 
        FROM customers 
        WHERE name LIKE ? OR pppoe_username LIKE ? OR phone LIKE ?
        LIMIT 10
    ", ["%$search%", "%$search%", "%$search%"]);
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
    <title>Cari Perangkat - Teknisi</title>
    
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
            --accent-cyan: #79c0ff;
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

        /* ==================== HEADER ==================== */
        .header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-default);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .back-btn {
            color: var(--fg-default);
            font-size: 1.2rem;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            transition: background var(--transition-fast);
        }

        .back-btn:hover {
            background: var(--bg-tertiary);
        }

        .header h2 {
            font-size: 1rem;
            font-weight: 600;
        }

        /* ==================== CONTAINER ==================== */
        .container {
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* ==================== SEARCH BOX ==================== */
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
        }

        .form-control {
            flex: 1;
            padding: 12px 14px;
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            color: var(--fg-default);
            font-size: 0.85rem;
            transition: all var(--transition-fast);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(47, 129, 247, 0.1);
        }

        .form-control::placeholder {
            color: var(--fg-muted);
        }

        .btn-search {
            padding: 0 20px;
            background: var(--accent-blue);
            border: none;
            border-radius: var(--radius-md);
            color: white;
            cursor: pointer;
            font-size: 1rem;
            transition: all var(--transition-fast);
        }

        .btn-search:hover {
            background: var(--accent-blue-hover);
            transform: translateY(-1px);
        }

        /* ==================== RESULT CARD ==================== */
        .result-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            color: inherit;
            transition: all var(--transition-fast);
        }

        .result-card:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
        }

        .result-info h3 {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--fg-default);
        }

        .result-info p {
            font-size: 0.75rem;
            color: var(--fg-muted);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .result-info p i {
            width: 16px;
            font-size: 0.7rem;
            color: var(--accent-cyan);
        }

        .btn-manage {
            background: rgba(47, 129, 247, 0.1);
            color: var(--accent-blue);
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            transition: all var(--transition-fast);
        }

        .btn-manage:hover {
            background: rgba(47, 129, 247, 0.2);
        }

        /* ==================== EMPTY STATE ==================== */
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--fg-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 0.85rem;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 480px) {
            .container {
                padding: 16px;
            }

            .result-card {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
                text-align: center;
            }

            .result-info p {
                justify-content: center;
            }

            .btn-manage {
                text-align: center;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .result-card,
            .btn-search,
            .btn-manage {
                transition: none;
            }
            .result-card:hover {
                transform: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <a href="../dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2>Cek Perangkat</h2>
    </div>

    <!-- Main Content -->
    <div class="container">
        <form method="GET" class="search-box">
            <input type="text" name="q" class="form-control" 
                   placeholder="Cari berdasarkan Nama, PPPoE, atau No HP..." 
                   value="<?php echo htmlspecialchars($search); ?>" autofocus>
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i>
            </button>
        </form>
        
        <?php if (!empty($search) && empty($results)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>Tidak ada hasil ditemukan untuk "<strong><?php echo htmlspecialchars($search); ?></strong>"</p>
                <p style="font-size: 0.7rem; margin-top: 8px;">Coba cek nama, username PPPoE, atau nomor telepon</p>
            </div>
        <?php endif; ?>
        
        <?php foreach ($results as $r): ?>
            <a href="manage.php?username=<?php echo urlencode($r['pppoe_username']); ?>" class="result-card">
                <div class="result-info">
                    <h3>
                        <i class="fas fa-user-circle" style="color: var(--accent-blue); font-size: 0.8rem;"></i>
                        <?php echo htmlspecialchars($r['name']); ?>
                    </h3>
                    <p>
                        <i class="fas fa-network-wired"></i>
                        <?php echo htmlspecialchars($r['pppoe_username']); ?>
                    </p>
                    <?php if (!empty($r['phone'])): ?>
                    <p>
                        <i class="fas fa-phone-alt"></i>
                        <?php echo htmlspecialchars($r['phone']); ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($r['address'])): ?>
                    <p>
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars(substr($r['address'], 0, 35)) . (strlen($r['address']) > 35 ? '...' : ''); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="btn-manage">
                    <i class="fas fa-cog"></i> Atur Perangkat
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Bottom Navigation -->
    <?php require_once '../includes/bottom_nav.php'; ?>
</body>
</html>