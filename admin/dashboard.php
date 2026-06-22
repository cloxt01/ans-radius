<?php
/**
 * Admin Dashboard - Anthropic Style
 * Tema seragam dengan referensi yang diberikan
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Dashboard';

// Get statistics
$stats = [
    'totalCustomers' => fetchOne("SELECT COUNT(*) as total FROM customers")['total'] ?? 0,
    'activeCustomers' => fetchOne("SELECT COUNT(*) as total FROM customers WHERE status = 'active'")['total'] ?? 0,
    'isolatedCustomers' => fetchOne("SELECT COUNT(*) as total FROM customers WHERE status = 'isolated'")['total'] ?? 0,
    'totalRevenue' => fetchOne("
        SELECT SUM(amount) as total 
        FROM invoices 
        WHERE status = 'paid' 
        AND due_date IS NOT NULL
        AND DATE_FORMAT(due_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
    ")['total'] ?? 0,
];

// Get recent invoices
$recentInvoices = fetchAll("
    SELECT i.*, c.name as customer_name 
    FROM invoices i 
    LEFT JOIN customers c ON i.customer_id = c.id 
    ORDER BY i.created_at DESC 
    LIMIT 10
");

// Get recent customers
$recentCustomers = fetchAll("
    SELECT c.*, p.name as package_name 
    FROM customers c 
    LEFT JOIN packages p ON c.package_id = p.id 
    ORDER BY c.created_at DESC 
    LIMIT 5
");

// Get monthly revenue for chart (last 6 months)
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    $monthName = date('M', strtotime("-{$i} months"));
    
    $revenue = fetchOne("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM invoices 
        WHERE status = 'paid' 
        AND due_date IS NOT NULL
        AND DATE_FORMAT(due_date, '%Y-%m') = ?
    ", [$month])['total'] ?? 0;
    
    $count = fetchOne("
        SELECT COUNT(*) as total 
        FROM customers 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ", [$month])['total'] ?? 0;
    
    $monthlyData[] = [
        'month' => $monthName,
        'revenue' => (float) $revenue,
        'count' => (int) $count
    ];
}

// Get unpaid invoices
$overdueInvoices = fetchOne("
    SELECT COUNT(*) as total 
    FROM invoices 
    WHERE status = 'unpaid' 
    AND due_date < CURDATE()
    AND MONTH(due_date) = MONTH(CURDATE())
")['total'] ?? 0;

$dueSoonInvoices = fetchOne("
    SELECT COUNT(*) as total 
    FROM invoices 
    WHERE status = 'unpaid' 
    AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")['total'] ?? 0;

$statsInvoicesThisMonth = getInvoicesStatsThisMonth();
$totalInvoices = 0;
foreach ($statsInvoicesThisMonth as $row) {
    $totalInvoices += $row['count'];
}

// Persiapkan data untuk donut
$donutData = [];
$donutColors = [];
$donutLabels = [];

foreach ($statsInvoicesThisMonth as $row) {
    $status = $row['status'];
    $count = $row['count'];
    
    // Set warna berdasarkan status
    $color = '#6c6c7a'; // default grey
    if ($status === 'paid') $color = '#1D9E75';      // Hijau - Lunas
    elseif ($status === 'unpaid') $color = '#facc15'; // Kuning - Belum Bayar
    elseif ($status === 'cancelled') $color = '#f87171'; // Merah - Dibatalkan
    
    // Hitung persentase
    $percent = $totalInvoices > 0 ? round(($count / $totalInvoices) * 100) : 0;
    
    $donutData[] = $percent;
    $donutColors[] = $color;
    $donutLabels[] = ucfirst($status);
}

// Jika tidak ada data, set default
if (empty($donutData)) {
    $donutData = [100];
    $donutColors = ['#6c6c7a'];
    $donutLabels = ['No Data'];
}
// Get Mikrotik data
$routerResource = mikrotikGetSystemResource();
$isConnected = ($routerResource['board-name'] !== '-' && $routerResource['board-name'] !== null && $routerResource['board-name'] !== '' && $routerResource['board-name'] !== 'N/A');
$activeRouter = getActiveRouter();
$interfaces = mikrotikGetInterfaces();
$activeCustomers = $stats['activeCustomers'] ?? 0;
$isolatedCustomers = $stats['isolatedCustomers'] ?? 0;
$totalCustomers = $stats['totalCustomers'] ?? 0;

// Jika tidak konek, set nilai default
if (!$isConnected) {
    $cpuLoad = 0;
    $freeMemory = 0;
    $totalMemory = 0;
    $usedMemory = 0;
    $ramFreePercent = 0;
    $ramUsedPercent = 0;
} else {
    $cpuLoad = max(0, min(100, (int) ($routerResource['cpu-load'] ?? 0)));
    $freeMemory = max(0, (int) ($routerResource['free-memory'] ?? 0));
    $totalMemory = max(0, (int) ($routerResource['total-memory'] ?? 0));
    $usedMemory = max(0, $totalMemory - $freeMemory);
    $ramFreePercent = $totalMemory > 0 ? (int) round(($freeMemory / $totalMemory) * 100) : 0;
    $ramUsedPercent = $totalMemory > 0 ? max(0, min(100, 100 - $ramFreePercent)) : 0;
}

// Get traffic stats from Mikrotik
//$trafficStats = [];
//if ($interfaces) {
//    $firstInterface = $interfaces[0]['name'] ?? 'ether1';
//    $trafficData = mikrotikMonitorTraffic($firstInterface);
//    $trafficStats = [
//        'tx' => $trafficData['tx'] ?? 0,
//        'rx' => $trafficData['rx'] ?? 0,
//        'total_tx' => $trafficData['total_tx'] ?? 0,
//        'total_rx' => $trafficData['total_rx'] ?? 0,
//    ];
//}

// Current month unpaid count
$currentMonth = date('m');
$currentYear = date('Y');
$unpaidCount = fetchOne("
    SELECT COUNT(*) as total 
    FROM customers c 
    WHERE c.status = 'active' 
    AND NOT EXISTS (
        SELECT 1 FROM invoices i 
        WHERE i.customer_id = c.id 
        AND MONTH(i.due_date) = ? 
        AND YEAR(i.due_date) = ? 
        AND i.status = 'paid'
    )
", [$currentMonth, $currentYear])['total'] ?? 0;

ob_start();
?>
    <style>
        /* ─── GLOBAL RESET & VARIABLES ───────────────────────────── */
        :root {
            --bg: #050816;
            --bg2: #0f172a;
            --glass: rgba(255,255,255,.06);
            --glass-border: rgba(255,255,255,.12);
            --text: #ffffff;
            --muted: rgba(255,255,255,.65);
            --primary: #3b82f6;
            --secondary: #06b6d4;
            --radius: 28px;
            --radius-md: 16px;
            --radius-sm: 10px;
            --font-sans: 'Geist', system-ui, -apple-system, sans-serif;
            --font-serif: 'Geist', system-ui, -apple-system, sans-serif;
        }

        /* ── FONTS ──────────────────────────────────────────────────── */
        @font-face {
            font-family: "Geist";
            src: url("https://assets.claude.ai/Fonts/AnthropicSans-Text-Regular-Static.otf") format("opentype");
            font-weight: 400;
            font-display: swap;
        }
        @font-face {
            font-family: "Geist";
            src: url("https://assets.claude.ai/Fonts/AnthropicSans-Text-Medium-Static.otf") format("opentype");
            font-weight: 500;
            font-display: swap;
        }
        @font-face {
            font-family: "Geist";
            src: url("https://assets.claude.ai/Fonts/AnthropicSans-Text-Semibold-Static.otf") format("opentype");
            font-weight: 600;
            font-display: swap;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-sans);
            background:
                    radial-gradient(circle at top left, rgba(59,130,246,.25), transparent 35%),
                    radial-gradient(circle at bottom right, rgba(6,182,212,.20), transparent 35%),
                    radial-gradient(circle at center, rgba(124,58,237,.15), transparent 40%),
                    #050816;
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px 28px;
        }

        @media (max-width: 768px) {
            .dashboard-container { padding: 16px; }
        }

        /* ── TYPOGRAPHY ───────────────────────────────────────────── */
        .page-title {
            font-family: var(--font-serif);
            font-size: clamp(1.9rem, 2.7vw, 2.4rem);
            font-weight: 500;
            line-height: 1.08;
            letter-spacing: -0.03em;
            color: var(--text);
            margin-bottom: 4px;
        }
        .page-sub {
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* ── CARD BASE ────────────────────────────────────────────── */
        .card {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            box-shadow: 0 20px 50px rgba(0,0,0,.25), inset 0 1px 0 rgba(255,255,255,.08);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            transition: all 0.25s ease;
            overflow: hidden;
        }
        .card:hover {
            border-color: rgba(255,255,255,.2);
            transform: translateY(-2px);
            box-shadow: 0 30px 60px rgba(0,0,0,.4);
        }
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text);
        }
        .card-title i { color: var(--primary); }
        .card-body { padding: 18px 20px 20px; }

        /* ── ROUTER GRID ───────────────────────────────────────────── */
        .router-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .router-card {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            transition: all 0.25s;
            box-shadow: 0 10px 30px rgba(0,0,0,.2);
        }
        .router-card:hover {
            border-color: rgba(59,130,246,0.3);
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(0,0,0,.4);
        }
        .router-icon {
            width: 44px; height: 44px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            background: rgba(255,255,255,.06);
            border: 1px solid var(--glass-border);
        }
        .router-icon.blue { color: var(--primary); }
        .router-icon.green { color: #10b981; }
        .router-icon.cyan { color: var(--secondary); }
        .router-icon.orange { color: #f59e0b; }

        .router-info { flex: 1; }
        .router-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .router-value {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text);
            margin-top: 2px;
        }
        .router-sub {
            font-size: 0.7rem;
            color: var(--muted);
            margin-top: 2px;
        }
        .router-ip {
            font-family: monospace;
            color: var(--text);
            font-size: 0.85rem;
            margin-top: 2px;
        }

        /* Donut kecil untuk CPU/RAM */
        .router-donut-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
        }
        .router-donut-wrap svg {
            flex-shrink: 0;
            width: 60px;
            height: 60px;
            filter: drop-shadow(0 0 6px rgba(0,0,0,.3));
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 2px;
        }
        .legend-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

        /* ── STATS METRICS ─────────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.25s;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow: 0 10px 30px rgba(0,0,0,.2);
        }
        .stat-card:hover {
            border-color: rgba(255,255,255,.2);
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(0,0,0,.4);
        }
        .stat-info h3 {
            font-family: var(--font-serif);
            font-size: 2rem;
            font-weight: 600;
            line-height: 1;
            letter-spacing: -0.03em;
            color: var(--text);
        }
        .stat-info p {
            color: var(--muted);
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 4px;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            background: rgba(255,255,255,.06);
            border: 1px solid var(--glass-border);
            color: var(--primary);
        }
        .stat-icon.green { color: #10b981; }
        .stat-icon.red { color: #ef4444; }
        .stat-icon.orange { color: #f59e0b; }

        /* ── ACTION BUTTONS ────────────────────────────────────────── */
        .actions-row {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .action-btn {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 999px;
            padding: 10px 18px;
            color: var(--text);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-family: var(--font-sans);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 4px 15px rgba(0,0,0,.2);
        }
        .action-btn:hover {
            background: rgba(255,255,255,.12);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        .action-btn i { color: var(--muted); }

        /* ── GRID 2 COLUMN ────────────────────────────────────────── */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        /* ── BAR CHART STYLE ──────────────────────────────────────── */
        .bar-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .bar-label {
            width: 40px;
            font-size: 13px;
            color: var(--muted);
            text-align: right;
            font-weight: 500;
        }
        .bar-track {
            flex: 1;
            height: 6px;
            background: rgba(255,255,255,.06);
            border-radius: 99px;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            border-radius: 99px;
            background: var(--secondary);
            transition: width 0.5s ease;
        }
        .bar-value {
            width: 70px;
            font-size: 13px;
            color: var(--text);
            text-align: right;
            font-weight: 500;
        }

        /* ── DONUT CHART ──────────────────────────────────────────── */
        .donut-wrap {
            display: flex;
            align-items: center;
            gap: 20px;
            justify-content: center;
            padding: 8px 0;
        }
        .donut-wrap svg {
            width: 100px;
            height: 100px;
            filter: drop-shadow(0 0 8px rgba(0,0,0,.3));
        }
        .donut-legend .legend-item {
            font-size: 13px;
            margin-bottom: 6px;
        }

        /* ── TABLES ────────────────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .data-table th {
            text-align: left;
            padding: 12px 16px;
            color: var(--muted);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--glass-border);
            background: rgba(255,255,255,.02);
        }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255,255,255,.04);
            color: var(--text);
        }
        .data-table tr:hover td {
            background: rgba(255,255,255,.04);
        }
        .data-table tr:last-child td { border-bottom: none; }

        .code-pill {
            font-family: monospace;
            background: rgba(59,130,246,.12);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            color: var(--primary);
            border: 1px solid rgba(59,130,246,.15);
        }

        /* ── BADGES ────────────────────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 1px solid transparent;
            background: var(--glass);
            backdrop-filter: blur(8px);
        }
        .badge-success {
            background: rgba(16,185,129,.15);
            color: #10b981;
            border-color: rgba(16,185,129,.2);
        }
        .badge-warning {
            background: rgba(245,158,11,.15);
            color: #f59e0b;
            border-color: rgba(245,158,11,.2);
        }
        .badge-danger {
            background: rgba(239,68,68,.15);
            color: #ef4444;
            border-color: rgba(239,68,68,.2);
        }
        .badge-info {
            background: rgba(59,130,246,.15);
            color: var(--primary);
            border-color: rgba(59,130,246,.2);
        }

        /* ── ALERT BANNER ──────────────────────────────────────────── */
        .alert-banner {
            background: linear-gradient(135deg, rgba(245,158,11,.12), rgba(255,255,255,.02));
            border: 1px solid rgba(245,158,11,.2);
            border-left: 4px solid #f59e0b;
            border-radius: var(--radius-md);
            padding: 14px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            background: var(--glass);
        }

        /* ── TRAFFIC MONITOR ──────────────────────────────────────── */
        .traffic-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            background: var(--glass-border);
            border-radius: var(--radius-md);
            overflow: hidden;
        }
        .traffic-stat-item {
            background: var(--glass);
            padding: 16px 20px;
            text-align: center;
            transition: all 0.2s;
            backdrop-filter: blur(12px);
        }
        .traffic-stat-item:hover {
            background: rgba(255,255,255,.06);
        }
        .traffic-stat-title {
            font-size: 0.75rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .traffic-stat-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .traffic-stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text);
        }
        .traffic-stat-sub {
            font-size: 0.7rem;
            color: var(--muted);
            margin-top: 4px;
        }

        .traffic-selector {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 999px;
            padding: 6px 14px;
            color: var(--text);
            font-size: 0.8rem;
            cursor: pointer;
            font-family: var(--font-sans);
            transition: all 0.2s;
            backdrop-filter: blur(12px);
        }
        .traffic-selector:hover {
            border-color: var(--primary);
        }
        .traffic-selector:focus {
            outline: none;
            border-color: var(--primary);
        }

        .chart-container {
            padding: 16px 20px;
            height: 280px;
        }

        /* ── RESPONSIVE ───────────────────────────────────────────── */
        @media (max-width: 1200px) {
            .router-grid, .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 1024px) {
            .grid-2 { grid-template-columns: 1fr; }
            .traffic-stats { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .router-grid, .stats-grid, .traffic-stats { grid-template-columns: 1fr; }
            .dashboard-container { padding: 12px; }
            .card-header { flex-direction: column; align-items: flex-start; }
        }

        /* ── CHART.JS OVERRIDE ────────────────────────────────────── */
        canvas {
            border-radius: var(--radius-sm);
            background: transparent;
        }

        /* ── MISC ──────────────────────────────────────────────────── */
        .text-muted { color: var(--muted); }
        .fw-600 { font-weight: 600; }
        .flex { display: flex; align-items: center; gap: 6px; }
        .gap-1 { gap: 4px; }
    </style>

<div class="dashboard-container">

    <!-- PAGE TITLE -->
    <div style="margin-bottom: 24px;">
        <div class="page-title">Dashboard</div>
        <div class="page-sub">Pantau performa jaringan dan bisnis ISP Anda</div>
    </div>

    <!-- ROUTER INFO -->
    <div class="router-grid">
        <div class="router-card">
            <div class="router-icon blue"><i class="fas fa-microchip"></i></div>
            <div class="router-info">
                <div class="router-label">Routerboard</div>
                <div class="router-value"><?php echo htmlspecialchars($routerResource['board-name'] ?? 'N/A'); ?></div>
                <div class="router-sub">v<?php echo htmlspecialchars($routerResource['version'] ?? 'N/A'); ?></div>
            </div>
        </div>
        <div class="router-card">
            <div class="router-donut-wrap">
                <svg viewBox="0 0 60 60">
                    <!-- Track = hijau (free) -->
                    <circle cx="30" cy="30" r="22" fill="none" 
                            stroke="#10b981" stroke-width="12"/>
                    <!-- Used = merah, menimpa dari atas -->
                    <?php if ($isConnected && $cpuLoad > 0): ?>
                    <circle cx="30" cy="30" r="22" fill="none"
                            stroke="#ef4444" stroke-width="12"
                            stroke-dasharray="<?php echo round(1.3823 * $cpuLoad, 1); ?> 138.23"
                            stroke-dashoffset="34.56"
                            transform="rotate(-90 30 30)"/>
                    <?php elseif (!$isConnected): ?>
                    <circle cx="30" cy="30" r="22" fill="none" 
                            stroke="#6c6c7a" stroke-width="12"/>
                    <?php endif; ?>
                    <text x="30" y="34" text-anchor="middle" font-size="11" 
                        font-weight="600" fill="#e8e8f0">
                        <?php echo $isConnected ? $cpuLoad : '?'; ?>%
                    </text>
                </svg>
                <div>
                    <div class="router-label">CPU Usage</div>
                    <?php if ($isConnected): ?>
                        <div class="legend-item"><span class="legend-dot" style="background:#ef4444"></span>Used <?php echo $cpuLoad; ?>%</div>
                        <div class="legend-item"><span class="legend-dot" style="background:#10b981"></span>Free <?php echo max(0, 100 - $cpuLoad); ?>%</div>
                    <?php else: ?>
                        <div class="legend-item" style="color: var(--text-muted);">Tidak ada data</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="router-card">
            <div class="router-donut-wrap">
                <svg viewBox="0 0 60 60">
                    <circle cx="30" cy="30" r="22" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="12"></circle>
                    <?php if ($isConnected): ?>
                        <!-- FREE (Hijau) - lapisan bawah -->
                        <circle cx="30" cy="30" r="22" fill="none" stroke="#10b981" stroke-width="12" stroke-dasharray="<?php echo round(13.823*$ramFreePercent, 1); ?> 138.23" stroke-dashoffset="0"></circle>
                        <!-- USED (Merah) - lapisan atas -->
                        <circle cx="30" cy="30" r="22" fill="none" stroke="#ef4444" stroke-width="12" stroke-dasharray="<?php echo round(13.823*$ramUsedPercent, 1); ?> 138.23" stroke-dashoffset="<?php echo round(-13.823*$ramFreePercent, 1); ?>"></circle>
                    <?php else: ?>
                        <circle cx="30" cy="30" r="22" fill="none" stroke="#6c6c7a" stroke-width="12" stroke-dasharray="138.23 138.23" stroke-dashoffset="0"></circle>
                    <?php endif; ?>
                    <text x="30" y="34" text-anchor="middle" font-size="11" font-weight="600" fill="#e8e8f0">
                        <?php echo $isConnected ? $ramFreePercent : '?'; ?>%
                    </text>
                </svg>
                <div>
                    <div class="router-label">Free RAM</div>
                    <?php if ($isConnected): ?>
                        <div class="legend-item"><span class="legend-dot" style="background:#10b981"></span>Free <?php echo $ramFreePercent; ?>%</div>
                        <div class="legend-item"><span class="legend-dot" style="background:#ef4444"></span>Used <?php echo $ramUsedPercent; ?>%</div>
                    <?php else: ?>
                        <div class="legend-item" style="color: var(--text-muted);">Tidak ada data</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="router-card">
            <div class="router-icon cyan"><i class="fas fa-plug"></i></div>
            <div class="router-info">
                <div class="router-label">Mikrotik</div>
                <div class="router-ip"><?php echo htmlspecialchars($activeRouter['host'] ?? 'N/A'); ?></div>
                <?php 
                if (($routerResource['board-name'] !== '-') && ($routerResource['version'] !== '-')) {
                    echo '<div class="router-sub" style="color: #10b981;">Connected</div>';
                } else {
                    echo '<div class="router-sub" style="color: #ef4444;">Disconnected</div>';
                } 
                ?>
            </div>
        </div>
    </div>

    <!-- STATS METRICS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo number_format($totalCustomers); ?></h3>
                <p>Total Pelanggan</p>
            </div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo number_format($activeCustomers); ?></h3>
                <p>Aktif</p>
            </div>
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo number_format($isolatedCustomers); ?></h3>
                <p>Terisolir</p>
            </div>
            <div class="stat-icon red"><i class="fas fa-ban"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo formatCurrency($stats['totalRevenue']); ?></h3>
                <p>Pendapatan Bulan Ini</p>
            </div>
            <div class="stat-icon orange"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="actions-row">
        <a href="customers.php" class="action-btn"><i class="fas fa-users"></i> Pelanggan</a>
        <a href="packages.php" class="action-btn"><i class="fas fa-box"></i> Paket</a>
        <a href="invoices.php" class="action-btn"><i class="fas fa-file-invoice"></i> Invoice</a>
        <a href="mikrotik.php" class="action-btn"><i class="fas fa-network-wired"></i> PPPoE</a>
        <a href="genieacs.php" class="action-btn"><i class="fas fa-satellite-dish"></i> GenieACS</a>
        <a href="settings.php" class="action-btn"><i class="fas fa-cog"></i> Settings</a>
    </div>
    <!-- ALERT BANNER -->
    <?php if ($overdueInvoices > 0 || $dueSoonInvoices > 0): ?>
    <div class="alert-banner">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-exclamation-triangle" style="color: var(--accent-orange);"></i>
            <span>
                <?php if ($overdueInvoices > 0): ?>
                    <strong><?php echo $overdueInvoices; ?> invoice</strong> melewati jatuh tempo.
                <?php endif; ?>
                <?php if ($dueSoonInvoices > 0): ?>
                    <strong><?php echo $dueSoonInvoices; ?> invoice</strong> akan jatuh tempo.
                <?php endif; ?>
            </span>
        </div>
        <a href="invoices.php" class="action-btn" style="padding:4px 12px;font-size:12px;">Lihat Detail →</a>
    </div>
    <?php endif; ?>
    <!-- GRID 2 COLUMN -->
    <div class="grid-2">
        <!-- LEFT: Pendapatan per Bulan -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Pendapatan per Bulan</div>
            </div>
            <div class="card-body" style="height: 260px;">
                <canvas id="revenueBarChart" style="width:100%;height:100%;"></canvas>
            </div>
        </div>

        <!-- RIGHT: Status Tagihan -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Status Tagihan (bulan ini)</div>
            </div>
            <div class="card-body">
                <div class="donut-wrap">
                    <svg viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="38" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="16"></circle>
                        <?php 
                        $currentOffset = 0;
                        $totalCircumference = 2 * M_PI * 38; // ≈ 238.76
                        
                        foreach ($donutData as $index => $percent):
                            if ($percent == 0) continue;
                            $dashLength = ($percent / 100) * $totalCircumference;
                            $dashOffset = -$currentOffset;
                        ?>
                        <circle cx="50" cy="50" r="38" fill="none" stroke="<?php echo $donutColors[$index]; ?>" stroke-width="16" 
                                stroke-dasharray="<?php echo $dashLength; ?> <?php echo $totalCircumference - $dashLength; ?>" 
                                stroke-dashoffset="<?php echo $dashOffset; ?>">
                        </circle>
                        <?php 
                            $currentOffset += $dashLength;
                        endforeach; 
                        ?>
                        <text x="50" y="55" text-anchor="middle" font-size="14" font-weight="600" fill="#e8e8f0">
                            <?php echo $totalInvoices; ?>
                        </text>
                    </svg>
                    <div class="donut-legend">
                        <?php foreach ($donutLabels as $index => $label): ?>
                        <div class="legend-item">
                            <span class="legend-dot" style="background:<?php echo $donutColors[$index]; ?>"></span>
                            <?php echo $label; ?>: <?php echo $donutData[$index]; ?>%
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>


    </div>

    <!-- TABLES ROW -->
    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-file-invoice"></i> Invoice Terbaru</div>
                <a href="invoices.php" class="action-btn" style="padding:4px 12px;font-size:12px;">Lihat Semua →</a>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Invoice</th><th>Pelanggan</th><th>Jumlah</th><th>Status</th><th>Tanggal</th></tr></thead>
                    <tbody>
                        <?php if (empty($recentInvoices)): ?>
                            <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted);">Belum ada invoice</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($recentInvoices, 0, 5) as $inv): ?>
                            <tr>
                                <td><span class="code-pill"><?php echo htmlspecialchars($inv['invoice_number']); ?></span></td>
                                <td><?php echo htmlspecialchars($inv['customer_name'] ?? '-'); ?></td>
                                <td><?php echo formatCurrency($inv['amount']); ?></td>
                                <td>
                                    <span class="badge <?php echo $inv['status'] === 'paid' ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo $inv['status'] === 'paid' ? 'Lunas' : 'Belum Bayar'; ?>
                                    </span>
                                </td>
                                <td style="color:var(--text-muted);"><?php echo formatDate($inv['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-plus"></i> Pelanggan Terbaru</div>
                <a href="customers.php" class="action-btn" style="padding:4px 12px;font-size:12px;">Lihat Semua →</a>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Nama</th><th>PPPoE</th><th>Paket</th><th>Status</th><th>Terdaftar</th></tr></thead>
                    <tbody>
                        <?php if (empty($recentCustomers)): ?>
                            <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted);">Belum ada pelanggan</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentCustomers as $cust): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cust['name']); ?></strong></td>
                                <td><span class="code-pill"><?php echo htmlspecialchars($cust['pppoe_username']); ?></span></td>
                                <td><?php echo htmlspecialchars($cust['package_name'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge <?php echo $cust['status'] === 'active' ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo $cust['status'] === 'active' ? 'Aktif' : 'Isolir'; ?>
                                    </span>
                                </td>
                                <td style="color:var(--text-muted);"><?php echo formatDate($cust['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // ── Helpers ──────────────────────────────────────────────
    function formatBytes(b) {
        if (b === 0) return '0 B';
        const u = ['B','KB','MB','GB','TB'];
        const i = Math.floor(Math.log(b) / Math.log(1024));
        return parseFloat((b / Math.pow(1024, i)).toFixed(2)) + ' ' + u[i];
    }
    
    function formatBits(b) {
        if (b === 0) return '0 bps';
        const u = ['bps','Kbps','Mbps','Gbps'];
        const i = Math.floor(Math.log(b) / Math.log(1024));
        return parseFloat((b / Math.pow(1024, i)).toFixed(1)) + ' ' + u[i];
    }

    // ── Initialize all charts ─────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        
        // ── Revenue Bar Chart ──────────────────────────────────
        const rBarCtx = document.getElementById('revenueBarChart');
        if (rBarCtx) {
            new Chart(rBarCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($monthlyData, 'month')); ?>,
                    datasets: [{
                        label: 'Pendapatan',
                        data: <?php echo json_encode(array_column($monthlyData, 'revenue')); ?>,
                        backgroundColor: '#1D9E75',
                        borderRadius: 6,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return 'Rp ' + ctx.parsed.y.toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                },
                                color: '#a0a0b0'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.06)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#a0a0b0'
                            },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // ── Customer Chart ─────────────────────────────────────
        const cCtx = document.getElementById('customerChart');
        if (cCtx) {
            new Chart(cCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($monthlyData, 'month')); ?>,
                    datasets: [{
                        label: 'Pelanggan Baru',
                        data: <?php echo json_encode(array_column($monthlyData, 'count')); ?>,
                        backgroundColor: '#8b5cf6',
                        borderRadius: 6,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            ticks: { stepSize: 1, color: '#a0a0b0' },
                            grid: { color: 'rgba(255,255,255,0.06)' }
                        },
                        x: {
                            ticks: { color: '#a0a0b0' },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // // ── Traffic Monitor ────────────────────────────────────
        // let trafficChart;
        // const MAX_POINTS = 20;
        // let currentInterface = document.getElementById('interfaceSelector')?.value || 'ether1';
        //
        // function initTraffic() {
        //     const ctx = document.getElementById('trafficChart');
        //     if (!ctx) {
        //         console.warn('Element #trafficChart tidak ditemukan');
        //         return;
        //     }
        //     trafficChart = new Chart(ctx.getContext('2d'), {
        //         type: 'line',
        //         data: {
        //             labels: [],
        //             datasets: [
        //                 {
        //                     label: 'Upload (Tx)',
        //                     data: [],
        //                     borderColor: '#10b981',
        //                     backgroundColor: 'rgba(16, 185, 129, 0.05)',
        //                     borderWidth: 2,
        //                     fill: true,
        //                     tension: 0.3,
        //                     pointRadius: 0
        //                 },
        //                 {
        //                     label: 'Download (Rx)',
        //                     data: [],
        //                     borderColor: '#f59e0b',
        //                     backgroundColor: 'rgba(245, 158, 11, 0.05)',
        //                     borderWidth: 2,
        //                     fill: true,
        //                     tension: 0.3,
        //                     pointRadius: 0
        //                 }
        //             ]
        //         },
        //         options: {
        //             responsive: true,
        //             maintainAspectRatio: false,
        //             interaction: { mode: 'index', intersect: false },
        //             plugins: {
        //                 tooltip: {
        //                     callbacks: {
        //                         label: function(ctx) {
        //                             return ctx.dataset.label + ': ' + formatBits(ctx.parsed.y);
        //                         }
        //                     }
        //                 },
        //                 legend: {
        //                     labels: { color: '#a0a0b0', usePointStyle: true }
        //                 }
        //             },
        //             scales: {
        //                 y: {
        //                     ticks: { callback: function(v) { return formatBits(v); }, color: '#a0a0b0' },
        //                     grid: { color: 'rgba(255,255,255,0.06)' }
        //                 },
        //                 x: {
        //                     ticks: { color: '#a0a0b0', maxRotation: 45 },
        //                     grid: { display: false }
        //                 }
        //             }
        //         }
        //     });
        // }
        //
        // function fetchTraffic() {
        //     fetch('../api/traffic.php?interface=' + encodeURIComponent(currentInterface))
        //         .then(r => r.json())
        //         .then(d => {
        //             if (!d || d.length < 2) return;
        //
        //             const now = new Date();
        //             const label = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        //             const tx = parseInt(d[0].data) || 0;
        //             const rx = parseInt(d[1].data) || 0;
        //             const totalTx = parseInt(d[0].total) || 0;
        //             const totalRx = parseInt(d[1].total) || 0;
        //
        //             // Update stats display
        //             document.getElementById('txSpeed').innerText = formatBits(tx);
        //             document.getElementById('rxSpeed').innerText = formatBits(rx);
        //             document.getElementById('totalTx').innerText = formatBytes(totalTx);
        //             document.getElementById('totalRx').innerText = formatBytes(totalRx);
        //
        //             trafficChart.data.labels.push(label);
        //             trafficChart.data.datasets[0].data.push(tx);
        //             trafficChart.data.datasets[1].data.push(rx);
        //
        //             if (trafficChart.data.labels.length > MAX_POINTS) {
        //                 trafficChart.data.labels.shift();
        //                 trafficChart.data.datasets[0].data.shift();
        //                 trafficChart.data.datasets[1].data.shift();
        //             }
        //             trafficChart.update('none');
        //         }).catch(e => console.error('Traffic error:', e));
        // }
        //
        // function changeInterface(iface) {
        //     currentInterface = iface;
        //     trafficChart.data.labels = [];
        //     trafficChart.data.datasets[0].data = [];
        //     trafficChart.data.datasets[1].data = [];
        //     trafficChart.update('none');
        //     fetchTraffic();
        // }

        // ── PPPoE Log ──────────────────────────────────────────
        const PPPoE_LIMIT = 20;
        function renderPppoeLogs(logs) {
            const tbody = document.querySelector('#pppoeLogTable tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            if (!logs || logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="2" class="text-muted" style="text-align:center; padding: 24px;">No PPPoE log entries</td></tr>';
                return;
            }
            logs.forEach(l => {
                const tr = document.createElement('tr');
                const tdTime = document.createElement('td');
                tdTime.style.verticalAlign = 'top';
                tdTime.style.whiteSpace = 'nowrap';
                tdTime.textContent = l.time || '';
                const tdMsg = document.createElement('td');
                tdMsg.style.whiteSpace = 'normal';
                tdMsg.style.overflowWrap = 'anywhere';
                tdMsg.textContent = l.message || '';
                tr.appendChild(tdTime);
                tr.appendChild(tdMsg);
                tbody.appendChild(tr);
            });
        }

        function fetchPppoeLogs() {
            fetch('../api/pppoe-log.php?limit=' + PPPoE_LIMIT)
                .then(r => r.json())
                .then(data => {
                    renderPppoeLogs(data);
                    const el = document.getElementById('pppoeLimit');
                    if (el) el.textContent = PPPoE_LIMIT;
                }).catch(e => {
                    console.error('PPPoE log fetch error', e);
                    const tbody = document.querySelector('#pppoeLogTable tbody');
                    if (tbody) tbody.innerHTML = '<tr><td colspan="2" class="text-muted" style="text-align:center; padding: 24px;">Error loading PPPoE logs</td></tr>';
                });
        }

        // ── Start everything ──────────────────────────────────
        // initTraffic();
        // fetchTraffic();
        // setInterval(fetchTraffic, 3000);
        
        const selector = document.getElementById('interfaceSelector');
        if (selector) {
            selector.addEventListener('change', function() { changeInterface(this.value); });
        }
        
        fetchPppoeLogs();
        setInterval(fetchPppoeLogs, 5000);
    });
</script>
<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>