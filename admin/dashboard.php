<?php
/**
 * Admin Dashboard - AApanel Style
 * Modern, clean, professional design
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
        AND paid_at IS NOT NULL
        AND DATE_FORMAT(paid_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
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
    $monthName = date('M Y', strtotime("-{$i} months"));
    
    $revenue = fetchOne("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM invoices 
        WHERE status = 'paid' 
        AND paid_at IS NOT NULL
        AND DATE_FORMAT(paid_at, '%Y-%m') = ?
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
")['total'] ?? 0;

$dueSoonInvoices = fetchOne("
    SELECT COUNT(*) as total 
    FROM invoices 
    WHERE status = 'unpaid' 
    AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")['total'] ?? 0;

// Get Mikrotik data
$routerResource = mikrotikGetSystemResource();
$interfaces = mikrotikGetInterfaces();
$activeCustomers = $stats['activeCustomers'] ?? 0;
$isolatedCustomers = $stats['isolatedCustomers'] ?? 0;
$totalCustomers = $stats['totalCustomers'] ?? 0;

// Get traffic stats from Mikrotik
$trafficStats = [];
if ($interfaces) {
    $firstInterface = $interfaces[0]['name'] ?? 'ether1';
    $trafficData = mikrotikMonitorTraffic($firstInterface);
    $trafficStats = [
        'tx' => $trafficData['tx'] ?? 0,
        'rx' => $trafficData['rx'] ?? 0,
        'total_tx' => $trafficData['total_tx'] ?? 0,
        'total_rx' => $trafficData['total_rx'] ?? 0,
    ];
}

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
    /* AApanel Style CSS */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --bg-primary: #0f0f14;
        --bg-secondary: #14141c;
        --bg-card: #1a1a24;
        --bg-hover: #20202c;
        --text-primary: #e8e8f0;
        --text-secondary: #a0a0b0;
        --text-muted: #6c6c7a;
        --border-color: #2a2a35;
        --accent-blue: #3b82f6;
        --accent-cyan: #06b6d4;
        --accent-green: #10b981;
        --accent-orange: #f59e0b;
        --accent-purple: #8b5cf6;
        --accent-red: #ef4444;
        --accent-pink: #ec4899;
        --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
        --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.4);
        --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.5);
        --radius-sm: 6px;
        --radius-md: 10px;
        --radius-lg: 14px;
        --radius-xl: 18px;
    }

    /* Dashboard Container */
    .dashboard-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 24px 28px;
    }

    @media (max-width: 768px) {
        .dashboard-container {
            padding: 16px;
        }
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 28px;
    }

    .stat-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        padding: 20px 24px;
        display: flex;
    align-items: center;
        justify-content: space-between;
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan));
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        border-color: rgba(59, 130, 246, 0.3);
        box-shadow: var(--shadow-md);
    }

    .stat-card:hover::before {
        opacity: 1;
    }

    .stat-info h3 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 4px;
        background: linear-gradient(135deg, #fff, var(--accent-blue));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    .stat-info p {
        color: var(--text-secondary);
        font-size: 0.8rem;
        font-weight: 500;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        background: rgba(59, 130, 246, 0.1);
    }

    /* Traffic Monitor Card - AApanel Style */
    .traffic-card {
        background: var(--bg-card);
        border-radius: var(--radius-xl);
        border: 1px solid var(--border-color);
        margin-bottom: 28px;
        overflow: hidden;
        transition: all 0.2s ease;
    }

    .traffic-card:hover {
        border-color: rgba(59, 130, 246, 0.3);
        box-shadow: var(--shadow-md);
    }

    .traffic-header {
        padding: 18px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .traffic-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1rem;
        font-weight: 600;
    }

    .traffic-title i {
        color: var(--accent-blue);
        font-size: 1.2rem;
    }

    .interface-selector {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 6px 14px;
        color: var(--text-primary);
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .interface-selector:hover {
        border-color: var(--accent-blue);
    }

    /* Traffic Stats - Like AApanel */
    .traffic-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1px;
        background: var(--border-color);
        margin-top: 16px;
    }

    .traffic-stat-item {
        background: var(--bg-card);
        padding: 20px 24px;
        text-align: center;
        transition: all 0.2s;
    }

    .traffic-stat-item:hover {
        background: var(--bg-hover);
    }

    .traffic-stat-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }

    .traffic-stat-title {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .traffic-stat-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .traffic-stat-sub {
        font-size: 0.7rem;
        color: var(--text-muted);
        margin-top: 4px;
    }

    /* Chart Container */
    .chart-container {
        padding: 20px 24px;
        height: 320px;
    }

    /* Router Info Bar */
    .router-info-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 28px;
    }

    .router-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        border: 1px solid var(--border-color);
        transition: all 0.2s;
    }

    .router-card:hover {
        border-color: rgba(59, 130, 246, 0.3);
        transform: translateY(-1px);
    }

    .router-icon {
        width: 44px;
        height: 44px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    .router-info {
        flex: 1;
    }

    .router-label {
        font-size: 0.7rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .router-value {
        font-weight: 600;
        font-size: 0.9rem;
    }

    /* Progress Bar */
    .progress {
        background: #2a2a35;
        border-radius: 10px;
        height: 4px;
        overflow: hidden;
        margin: 8px 0 4px;
    }

    .progress-bar {
        height: 100%;
        border-radius: 10px;
        transition: width 0.3s;
    }

    /* Quick Actions */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 12px;
        margin-top: 8px;
    }

    .quick-action-btn {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 12px 16px;
        text-align: center;
        color: var(--text-primary);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .quick-action-btn i {
        color: var(--accent-blue);
        font-size: 0.95rem;
    }

    .quick-action-btn:hover {
        background: var(--accent-blue);
        border-color: var(--accent-blue);
        color: #fff;
        transform: translateY(-2px);
    }

    .quick-action-btn:hover i {
        color: #fff;
    }

    /* Alert Banner */
    .alert-banner {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
        border-left: 3px solid var(--accent-orange);
        border-radius: var(--radius-md);
        padding: 14px 20px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        text-align: left;
        padding: 12px 16px;
        color: var(--text-secondary);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--border-color);
    }

    .data-table td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.85rem;
    }

    .data-table tr:hover td {
        background: rgba(59, 130, 246, 0.05);
    }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-success { background: rgba(16, 185, 129, 0.15); color: var(--accent-green); }
    .badge-warning { background: rgba(245, 158, 11, 0.15); color: var(--accent-orange); }
    .badge-info { background: rgba(6, 182, 212, 0.15); color: var(--accent-cyan); }

    /* Grid */
    .grid-2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
        margin-bottom: 28px;
    }

    @media (max-width: 1024px) {
        .stats-grid, .router-info-grid { grid-template-columns: repeat(2, 1fr); }
        .grid-2 { grid-template-columns: 1fr; }
        .traffic-stats { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 640px) {
        .stats-grid, .router-info-grid { grid-template-columns: 1fr; }
        .traffic-stats { grid-template-columns: 1fr; }
    }

    /* Code */
    code {
        background: rgba(59, 130, 246, 0.1);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.75rem;
        color: var(--accent-cyan);
    }

    .text-muted { color: var(--text-muted); }
</style>

<div class="dashboard-container">
    <!-- Welcome Section -->
    <div style="margin-bottom: 28px;">
        <h1 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 4px;">Dashboard</h1>
        <p style="color: var(--text-secondary); font-size: 0.85rem;">Selamat datang, pantau performa jaringan dan bisnis ISP Anda</p>
    </div>

        <!-- Router Info Row -->
    <div class="router-info-grid">
        <div class="router-card">
            <div class="router-icon" style="background: rgba(59, 130, 246, 0.1);"><i class="fas fa-microchip" style="color: var(--accent-blue);"></i></div>
            <div class="router-info">
                <div class="router-label">ROUTERBOARD</div>
                <div class="router-value"><?php echo htmlspecialchars($routerResource['board-name']); ?></div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">v<?php echo htmlspecialchars($routerResource['version']); ?></div>
            </div>
        </div>
        <div class="router-card">
            <div class="router-icon" style="background: rgba(245, 158, 11, 0.1);"><i class="fas fa-chart-line" style="color: var(--accent-orange);"></i></div>
            <div class="router-info">
                <div class="router-label">CPU USAGE</div>
                <div class="router-value"><?php echo $routerResource['cpu-load']; ?>%</div>
                <div class="progress"><div class="progress-bar" style="width: <?php echo $routerResource['cpu-load']; ?>%; background: <?php echo $routerResource['cpu-load'] > 80 ? '#ef4444' : '#f59e0b'; ?>;"></div></div>
            </div>
        </div>
        <div class="router-card">
            <div class="router-icon" style="background: rgba(16, 185, 129, 0.1);"><i class="fas fa-memory" style="color: var(--accent-green);"></i></div>
            <div class="router-info">
                <div class="router-label">FREE RAM</div>
                <div class="router-value"><?php echo formatBytes($routerResource['free-memory']); ?></div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">Uptime: <?php echo htmlspecialchars($routerResource['uptime']); ?></div>
            </div>
        </div>
        <div class="router-card">
            <div class="router-icon" style="background: rgba(6, 182, 212, 0.1);"><i class="fas fa-plug" style="color: var(--accent-cyan);"></i></div>
            <div class="router-info">
                <div class="router-label">MIKROTIK API</div>
                <div class="router-value"><i class="fas fa-circle" style="color: #10b981; font-size: 0.6rem;"></i> Connected</div>
                <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo htmlspecialchars(getSettingValue('MIKROTIK_HOST')); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Stats Grid -->
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
            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1);"><i class="fas fa-check-circle" style="color: var(--accent-green);"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo number_format($isolatedCustomers); ?></h3>
                <p>Terisolir</p>
            </div>
            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1);"><i class="fas fa-ban" style="color: var(--accent-red);"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo formatCurrency($stats['totalRevenue']); ?></h3>
                <p>Pendapatan Bulan Ini</p>
            </div>
            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1);"><i class="fas fa-chart-line" style="color: var(--accent-orange);"></i></div>
        </div>
    </div>


    <!-- Alert Banner -->
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
        <a href="invoices.php" class="quick-action-btn" style="padding: 6px 14px;">Lihat Detail →</a>
    </div>
    <?php endif; ?>

    <!-- Traffic Monitor Card - AApanel Style -->
    <div class="traffic-card">
        <div class="traffic-header">
            <div class="traffic-title">
                <i class="fas fa-chart-line"></i>
                <span>Traffic Monitor</span>
            </div>
            <?php
                // Prefer SFP1-INTERNET if present, otherwise fall back to first available interface
                $defaultInterface = 'SFP1-INTERNET';
                $selectedName = '';
                if (!empty($interfaces) && is_array($interfaces)) {
                    foreach ($interfaces as $if) {
                        $inameCheck = trim((string) ($if['name'] ?? ''));
                        if (strcasecmp($inameCheck, $defaultInterface) === 0) {
                            $selectedName = $if['name'];
                            break;
                        }
                    }
                    if ($selectedName === '') {
                        $selectedName = $interfaces[0]['name'] ?? '';
                    }
                }
            ?>
            <select id="interfaceSelector" class="interface-selector">
                <?php foreach ($interfaces as $iface):
                    $iname = $iface['name'] ?? '';
                    $sel = ($iname !== '' && $iname === $selectedName) ? ' selected' : '';
                ?>
                    <option value="<?php echo htmlspecialchars($iname); ?>"<?php echo $sel; ?>>
                        <?php echo htmlspecialchars($iname); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Traffic Stats like AApanel -->
        <div class="traffic-stats">
            <div class="traffic-stat-item">
                <div class="traffic-stat-title">
                    <span class="traffic-stat-dot" style="background: var(--accent-green);"></span>
                    <span>Upstream</span>
                </div>
                <div class="traffic-stat-value" id="txSpeed">0 bps</div>
                <div class="traffic-stat-sub">Current</div>
            </div>
            <div class="traffic-stat-item">
                <div class="traffic-stat-title">
                    <span class="traffic-stat-dot" style="background: var(--accent-orange);"></span>
                    <span>Downstream</span>
                </div>
                <div class="traffic-stat-value" id="rxSpeed">0 bps</div>
                <div class="traffic-stat-sub">Current</div>
            </div>
            <div class="traffic-stat-item">
                <div class="traffic-stat-title">
                    <i class="fas fa-upload" style="font-size: 0.7rem;"></i>
                    <span>Total Sent</span>
                </div>
                <div class="traffic-stat-value" id="totalTx">0 B</div>
                <div class="traffic-stat-sub">Lifetime</div>
            </div>
            <div class="traffic-stat-item">
                <div class="traffic-stat-title">
                    <i class="fas fa-download" style="font-size: 0.7rem;"></i>
                    <span>Total Received</span>
                </div>
                <div class="traffic-stat-value" id="totalRx">0 B</div>
                <div class="traffic-stat-sub">Lifetime</div>
            </div>
        </div>

        <!-- Chart Container -->
        <div class="chart-container">
            <canvas id="trafficChart" style="width: 100%; height: 100%;"></canvas>
        </div>
    </div>
    <!-- PPPoE Log Card -->
    <div class="card" style="margin-bottom:28px;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> PPPoE Log</h3>
            <div style="margin-left: auto; color: var(--text-muted); font-size: 0.85rem;">Showing last <span id="pppoeLimit">20</span> entries</div>
        </div>
        <div class="card-body" style="padding: 12px 16px;">
            <div style="max-height: 255px; overflow-y: auto;">
                <table class="data-table" id="pppoeLogTable" style="table-layout: fixed; width: 100%;">
                    <thead><tr><th style="width: 160px;">Time</th><th>Message</th></tr></thead>
                    <tbody>
                        <tr><td colspan="2" class="text-muted" style="text-align:center; padding: 40px;">Loading PPPoE logs…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>



    <!-- Quick Actions -->
    <div class="card" style="margin-bottom: 28px;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-bolt"></i> Menu Cepat</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions-grid">
                <a href="customers.php" class="quick-action-btn"><i class="fas fa-users"></i> Pelanggan</a>
                <a href="packages.php" class="quick-action-btn"><i class="fas fa-box"></i> Paket</a>
                <a href="invoices.php" class="quick-action-btn"><i class="fas fa-file-invoice"></i> Invoice</a>
                <a href="mikrotik.php" class="quick-action-btn"><i class="fas fa-network-wired"></i> PPPoE</a>
                <a href="genieacs.php" class="quick-action-btn"><i class="fas fa-satellite-dish"></i> GenieACS</a>
                <a href="settings.php" class="quick-action-btn"><i class="fas fa-cog"></i> Settings</a>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-line"></i> Pendapatan 6 Bulan Terakhir</h3>
            </div>
            <div class="card-body" style="height: 280px;">
                <canvas id="revenueChart" style="width: 100%; height: 100%;"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar"></i> Pelanggan Baru</h3>
            </div>
            <div class="card-body" style="height: 280px;">
                <canvas id="customerChart" style="width: 100%; height: 100%;"></canvas>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice"></i> Invoice Terbaru</h3>
                <a href="invoices.php" class="quick-action-btn" style="padding: 6px 12px;">Lihat Semua →</a>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Invoice</th><th>Pelanggan</th><th>Jumlah</th><th>Status</th><th>Tanggal</th></tr></thead>
                    <tbody>
                        <?php if (empty($recentInvoices)): ?>
                            <tr><td colspan="5" class="text-muted" style="text-align: center; padding: 40px;">Belum ada invoice</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($recentInvoices, 0, 5) as $inv): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($inv['invoice_number']); ?></code></td>
                                <td><?php echo htmlspecialchars($inv['customer_name'] ?? '-'); ?></td>
                                <td><?php echo formatCurrency($inv['amount']); ?></td>
                                <td><span class="badge <?php echo $inv['status'] === 'paid' ? 'badge-success' : 'badge-warning'; ?>"><?php echo $inv['status'] === 'paid' ? 'Lunas' : 'Belum Bayar'; ?></span></td>
                                <td class="text-muted"><?php echo formatDate($inv['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-plus"></i> Pelanggan Terbaru</h3>
                <a href="customers.php" class="quick-action-btn" style="padding: 6px 12px;">Lihat Semua →</a>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Nama</th><th>PPPoE</th><th>Paket</th><th>Status</th><th>Terdaftar</th></tr></thead>
                    <tbody>
                        <?php if (empty($recentCustomers)): ?>
                            <tr><td colspan="5" class="text-muted" style="text-align: center; padding: 40px;">Belum ada pelanggan</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentCustomers as $cust): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cust['name']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($cust['pppoe_username']); ?></code></td>
                                <td><?php echo htmlspecialchars($cust['package_name'] ?? '-'); ?></td>
                                <td><span class="badge <?php echo $cust['status'] === 'active' ? 'badge-success' : 'badge-warning'; ?>"><?php echo $cust['status'] === 'active' ? 'Aktif' : 'Isolir'; ?></span></td>
                                <td class="text-muted"><?php echo formatDate($cust['created_at']); ?></td>
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
    // Format bytes to human readable
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function formatBits(bits) {
        if (bits === 0) return '0 bps';
        const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps'];
        let i = Math.floor(Math.log(bits) / Math.log(1024));
        return parseFloat((bits / Math.pow(1024, i)).toFixed(1)) + ' ' + sizes[i];
    }

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($monthlyData, 'month')); ?>,
            datasets: [{
                label: 'Pendapatan',
                data: <?php echo json_encode(array_column($monthlyData, 'revenue')); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.05)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#3b82f6',
                pointRadius: 3,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => 'Rp ' + ctx.parsed.y.toLocaleString('id-ID') } } },
            scales: { y: { ticks: { callback: (v) => 'Rp ' + v.toLocaleString('id-ID'), color: '#a0a0b0' }, grid: { color: '#2a2a35' } }, x: { ticks: { color: '#a0a0b0' }, grid: { display: false } } }
        }
    });

    // Customer Chart
    const customerCtx = document.getElementById('customerChart').getContext('2d');
    new Chart(customerCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($monthlyData, 'month')); ?>,
            datasets: [{
                label: 'Pelanggan Baru',
                data: <?php echo json_encode(array_column($monthlyData, 'count')); ?>,
                backgroundColor: '#8b5cf6',
                borderRadius: 8,
                barPercentage: 0.65
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: { y: { ticks: { stepSize: 1, color: '#a0a0b0' }, grid: { color: '#2a2a35' } }, x: { ticks: { color: '#a0a0b0' }, grid: { display: false } } }
        }
    });

    // Traffic Monitor
    let trafficChart;
    const MAX_POINTS = 20;
    let currentInterface = document.getElementById('interfaceSelector')?.value || 'ether1';

    function initTraffic() {
        const ctx = document.getElementById('trafficChart').getContext('2d');
        trafficChart = new Chart(ctx, {
            type: 'line',
            data: { labels: [], datasets: [
                { label: 'Upload (Tx)', data: [], borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.05)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 0 },
                { label: 'Download (Rx)', data: [], borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.05)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 0 }
            ] },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatBits(ctx.parsed.y)}` } }, legend: { labels: { color: '#a0a0b0', usePointStyle: true } } },
                scales: { y: { ticks: { callback: (v) => formatBits(v), color: '#a0a0b0' }, grid: { color: '#2a2a35' } }, x: { ticks: { color: '#a0a0b0', maxRotation: 45 }, grid: { display: false } } }
            }
        });
    }

    function fetchTraffic() {
        fetch('../api/traffic.php?interface=' + encodeURIComponent(currentInterface))
            .then(r => r.json())
            .then(d => {
                if (!d || d.length < 2) return;
                
                const now = new Date();
                const label = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                const tx = parseInt(d[0].data) || 0;
                const rx = parseInt(d[1].data) || 0;
                const totalTx = parseInt(d[0].total) || 0;
                const totalRx = parseInt(d[1].total) || 0;
                
                // Update stats display
                document.getElementById('txSpeed').innerText = formatBits(tx);
                document.getElementById('rxSpeed').innerText = formatBits(rx);
                document.getElementById('totalTx').innerText = formatBytes(totalTx);
                document.getElementById('totalRx').innerText = formatBytes(totalRx);
                
                trafficChart.data.labels.push(label);
                trafficChart.data.datasets[0].data.push(tx);
                trafficChart.data.datasets[1].data.push(rx);
                
                if (trafficChart.data.labels.length > MAX_POINTS) {
                    trafficChart.data.labels.shift();
                    trafficChart.data.datasets[0].data.shift();
                    trafficChart.data.datasets[1].data.shift();
                }
                trafficChart.update('none');
            }).catch(e => console.error('Traffic error:', e));
    }

    function changeInterface(iface) {
        currentInterface = iface;
        trafficChart.data.labels = [];
        trafficChart.data.datasets[0].data = [];
        trafficChart.data.datasets[1].data = [];
        trafficChart.update('none');
        fetchTraffic();
    }

    document.addEventListener('DOMContentLoaded', function() {
        initTraffic();
        fetchTraffic();
        setInterval(fetchTraffic, 3000);
        
        const selector = document.getElementById('interfaceSelector');
        if (selector) {
            selector.addEventListener('change', function() { changeInterface(this.value); });
        }
    });

    // PPPoE Log fetching
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
                const el = document.getElementById('pppoeLimit'); if (el) el.textContent = PPPoE_LIMIT;
            }).catch(e => {
                console.error('PPPoE log fetch error', e);
                const tbody = document.querySelector('#pppoeLogTable tbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="2" class="text-muted" style="text-align:center; padding: 24px;">Error loading PPPoE logs</td></tr>';
            });
    }

    // Start PPPoE log polling
    fetchPppoeLogs();
    setInterval(fetchPppoeLogs, 5000);
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>