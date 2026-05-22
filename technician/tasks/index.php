<?php
require_once '../../includes/auth.php';
requireTechnicianLogin();

$pageTitle = 'Daftar Tugas';
$tech = $_SESSION['technician'];

$type = $_GET['type'] ?? 'ticket'; // ticket | install

if ($type === 'ticket') {
    // Get Tickets
    $status = $_GET['status'] ?? 'all';
    $where = "technician_id = ?";
    $params = [$tech['id']];

    if ($status === 'pending') {
        $where .= " AND status IN ('pending', 'in_progress')";
    } elseif ($status === 'resolved') {
        $where .= " AND status = 'resolved'";
    }

    $tickets = fetchAll("
        SELECT t.*, c.name as customer_name, c.address 
        FROM trouble_tickets t 
        LEFT JOIN customers c ON t.customer_id = c.id 
        WHERE $where 
        ORDER BY FIELD(t.status, 'in_progress', 'pending', 'resolved'), t.created_at DESC
    ", $params);
} else {
    // Get Installations
    $status = $_GET['status'] ?? 'pending';
    $where = "installed_by = ?";
    $params = [$tech['id']];

    if ($status === 'pending') {
        $where .= " AND status = 'registered'";
    } elseif ($status === 'resolved') {
        $where .= " AND status = 'active'";
    } else {
        $where .= " AND status IN ('registered', 'active')";
    }

    $installs = fetchAll("
        SELECT * FROM customers 
        WHERE $where 
        ORDER BY created_at DESC
    ", $params);
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
    <title>Daftar Tugas - Teknisi</title>
    
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

        /* ==================== TYPE TABS ==================== */
        .type-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--bg-primary);
            padding: 8px 16px 0;
            border-bottom: 1px solid var(--border-muted);
        }

        .type-tab {
            text-align: center;
            padding: 10px 12px;
            color: var(--fg-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            transition: all var(--transition-fast);
        }

        .type-tab i {
            margin-right: 6px;
        }

        .type-tab.active {
            color: var(--accent-blue);
            border-bottom-color: var(--accent-blue);
        }

        .type-tab:hover:not(.active) {
            color: var(--fg-default);
            border-bottom-color: var(--border-default);
        }

        /* ==================== FILTER TABS ==================== */
        .filter-tabs {
            display: flex;
            padding: 12px 16px;
            gap: 8px;
            overflow-x: auto;
            background: var(--bg-canvas);
            border-bottom: 1px solid var(--border-muted);
        }

        .filter-tab {
            background: var(--bg-tertiary);
            padding: 6px 14px;
            border-radius: 20px;
            color: var(--fg-muted);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
            transition: all var(--transition-fast);
        }

        .filter-tab.active {
            background: rgba(47, 129, 247, 0.15);
            color: var(--accent-blue);
            border: 1px solid rgba(47, 129, 247, 0.3);
        }

        .filter-tab:hover:not(.active) {
            background: var(--bg-primary);
            color: var(--fg-default);
        }

        /* ==================== TASK LIST ==================== */
        .task-list {
            padding: 16px;
            max-width: 800px;
            margin: 0 auto;
        }

        .task-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            padding: 16px;
            margin-bottom: 12px;
            display: block;
            text-decoration: none;
            color: inherit;
            transition: all var(--transition-fast);
        }

        .task-card:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
        }

        /* ==================== TASK HEADER ==================== */
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .task-id {
            font-size: 0.7rem;
            color: var(--fg-muted);
            background: var(--bg-tertiary);
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
        }

        /* ==================== STATUS BADGES ==================== */
        .status-badge {
            font-size: 0.7rem;
            padding: 2px 10px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending {
            background: rgba(210, 153, 34, 0.15);
            color: var(--accent-orange);
            border: 1px solid rgba(210, 153, 34, 0.3);
        }

        .status-in_progress {
            background: rgba(47, 129, 247, 0.15);
            color: var(--accent-blue);
            border: 1px solid rgba(47, 129, 247, 0.3);
        }

        .status-resolved,
        .status-active {
            background: rgba(63, 185, 80, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(63, 185, 80, 0.3);
        }

        .status-registered {
            background: rgba(210, 153, 34, 0.15);
            color: var(--accent-orange);
            border: 1px solid rgba(210, 153, 34, 0.3);
        }

        /* ==================== TASK CONTENT ==================== */
        .customer-name {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--fg-default);
        }

        .task-desc {
            font-size: 0.85rem;
            color: var(--fg-muted);
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }

        .task-desc i {
            margin-right: 6px;
            font-size: 0.75rem;
        }

        .task-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.7rem;
            color: var(--fg-subtle);
            border-top: 1px solid var(--border-muted);
            padding-top: 10px;
            margin-top: 4px;
        }

        .task-footer i {
            margin-right: 4px;
            font-size: 0.65rem;
        }

        /* ==================== EMPTY STATE ==================== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--fg-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 0.85rem;
            margin-bottom: 4px;
        }

        .empty-state small {
            font-size: 0.7rem;
            color: var(--fg-subtle);
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 480px) {
            .task-list {
                padding: 12px;
            }

            .task-card {
                padding: 14px;
            }

            .customer-name {
                font-size: 0.9rem;
            }

            .type-tab {
                font-size: 0.8rem;
            }

            .type-tab i {
                margin-right: 4px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .task-card,
            .filter-tab,
            .type-tab {
                transition: none;
            }
            .task-card:hover {
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
        <h2>Daftar Tugas</h2>
    </div>

    <!-- Type Tabs -->
    <div class="type-tabs">
        <a href="?type=ticket&status=all" class="type-tab <?php echo $type === 'ticket' ? 'active' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i> Gangguan
        </a>
        <a href="?type=install&status=pending" class="type-tab <?php echo $type === 'install' ? 'active' : ''; ?>">
            <i class="fas fa-satellite-dish"></i> Pasang Baru
        </a>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?type=<?php echo $type; ?>&status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
            Semua
        </a>
        <a href="?type=<?php echo $type; ?>&status=pending" class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
            <?php echo $type === 'ticket' ? '🟠 Perlu Tindakan' : '🟡 Belum Pasang'; ?>
        </a>
        <a href="?type=<?php echo $type; ?>&status=resolved" class="filter-tab <?php echo $status === 'resolved' ? 'active' : ''; ?>">
            ✅ Selesai
        </a>
    </div>

    <!-- Task List -->
    <div class="task-list">
        <?php if ($type === 'ticket'): ?>
            <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-check"></i>
                    <p>Tidak ada tiket gangguan saat ini</p>
                    <small>Semua tiket telah selesai</small>
                </div>
            <?php else: ?>
                <?php foreach ($tickets as $t): ?>
                    <a href="view_ticket.php?id=<?php echo $t['id']; ?>" class="task-card">
                        <div class="task-header">
                            <span class="task-id">#<?php echo $t['id']; ?></span>
                            <span class="status-badge status-<?php echo $t['status']; ?>">
                                <?php 
                                switch($t['status']) {
                                    case 'pending': echo '<i class="fas fa-clock"></i> Menunggu'; break;
                                    case 'in_progress': echo '<i class="fas fa-spinner fa-pulse"></i> Dikerjakan'; break;
                                    case 'resolved': echo '<i class="fas fa-check-circle"></i> Selesai'; break;
                                }
                                ?>
                            </span>
                        </div>
                        <div class="customer-name">
                            <i class="fas fa-user-circle" style="color: var(--accent-blue); font-size: 0.8rem;"></i>
                            <?php echo htmlspecialchars($t['customer_name']); ?>
                        </div>
                        <div class="task-desc">
                            <i class="fas fa-exclamation-circle" style="color: var(--accent-red);"></i>
                            <?php echo htmlspecialchars($t['description']); ?>
                        </div>
                        <div class="task-footer">
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($t['address'] ?? '-', 0, 25)) . (strlen($t['address'] ?? '') > 25 ? '...' : ''); ?></span>
                            <span><i class="fas fa-calendar-alt"></i> <?php echo date('d M H:i', strtotime($t['created_at'])); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <?php if (empty($installs)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>Tidak ada jadwal pasang baru</p>
                    <small>Semua pemasangan telah selesai</small>
                </div>
            <?php else: ?>
                <?php foreach ($installs as $i): ?>
                    <a href="view_install.php?id=<?php echo $i['id']; ?>" class="task-card">
                        <div class="task-header">
                            <span class="task-id">#C<?php echo $i['id']; ?></span>
                            <span class="status-badge status-<?php echo $i['status']; ?>">
                                <?php echo $i['status'] === 'registered' ? '<i class="fas fa-clock"></i> Belum Pasang' : '<i class="fas fa-check-circle"></i> Aktif'; ?>
                            </span>
                        </div>
                        <div class="customer-name">
                            <i class="fas fa-user-circle" style="color: var(--accent-cyan); font-size: 0.8rem;"></i>
                            <?php echo htmlspecialchars($i['name']); ?>
                        </div>
                        <div class="task-desc">
                            <i class="fas fa-box"></i>
                            Paket Internet Baru - Pemasangan ONT/ODP
                        </div>
                        <div class="task-footer">
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($i['address'] ?? '-', 0, 25)) . (strlen($i['address'] ?? '') > 25 ? '...' : ''); ?></span>
                            <span><i class="fas fa-calendar-alt"></i> <?php echo date('d M H:i', strtotime($i['created_at'])); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <?php require_once '../includes/bottom_nav.php'; ?>
</body>
</html>