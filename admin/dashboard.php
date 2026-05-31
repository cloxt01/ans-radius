<?php
/**
 * Admin Dashboard - Elegant Professional Design
 * Consistent with Customers page style
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Dashboard';

// Get statistics
$stats = [
    'totalCustomers' => fetchOne("SELECT COUNT(*) as total FROM customers")['total'] ?? 0,
    'activeCustomers' => fetchOne("SELECT COUNT(*) as total FROM customers WHERE status = 'active'")['total'] ?? 0,
    'isolatedCustomers' => fetchOne("SELECT COUNT(*) as total FROM customers WHERE status = 'isolated'")['total'] ?? 0,
    'totalPackages' => fetchOne("SELECT COUNT(*) as total FROM packages")['total'] ?? 0,
    'totalInvoices' => fetchOne("SELECT COUNT(*) as total FROM invoices")['total'] ?? 0,
    'paidInvoices' => fetchOne("SELECT COUNT(*) as total FROM invoices WHERE status = 'paid'")['total'] ?? 0,
    'pendingInvoices' => fetchOne("SELECT COUNT(*) as total FROM invoices WHERE status = 'unpaid'")['total'] ?? 0,
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

// Get monthly revenue for chart (last 6 months) - use DateTime to avoid month-end duplicates
$monthlyRevenue = [];
$dt = new DateTime('first day of this month');
for ($i = 5; $i >= 0; $i--) {
    $m = (clone $dt)->modify("-{$i} months");
    $month = $m->format('Y-m');
    $monthName = $m->format('M Y');

    $revenue = fetchOne(
        "SELECT SUM(amount) as total FROM invoices WHERE status = 'paid' AND DATE_FORMAT(paid_at, '%Y-%m') = ?",
        [$month]
    )['total'] ?? 0;

    $monthlyRevenue[] = [
        'month' => $monthName,
        'revenue' => (float) $revenue
    ];
}

// Get monthly customer growth (last 6 months) - use DateTime to avoid month-end duplicates
$monthlyCustomers = [];
$dt2 = new DateTime('first day of this month');
for ($i = 5; $i >= 0; $i--) {
    $m = (clone $dt2)->modify("-{$i} months");
    $month = $m->format('Y-m');
    $monthName = $m->format('M Y');

    $count = fetchOne(
        "SELECT COUNT(*) as total FROM customers WHERE DATE_FORMAT(created_at, '%Y-%m') = ?",
        [$month]
    )['total'] ?? 0;

    $monthlyCustomers[] = [
        'month' => $monthName,
        'count' => (int) $count
    ];
}

// Get unpaid invoices count by due status
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
    /* Additional dashboard-specific styles - consistent with customers page */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.2s ease;
        border: 1px solid var(--border-color);
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        border-color: var(--accent-cyan);
        box-shadow: var(--shadow-md);
    }
    
    .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
    }
    
    .stat-icon.cyan { background: rgba(0, 212, 255, 0.12); color: var(--accent-cyan); }
    .stat-icon.green { background: rgba(0, 230, 118, 0.12); color: var(--accent-green); }
    .stat-icon.red { background: rgba(255, 77, 77, 0.12); color: var(--accent-red); }
    .stat-icon.orange { background: rgba(255, 159, 67, 0.12); color: var(--accent-orange); }
    .stat-icon.purple { background: rgba(168, 85, 247, 0.12); color: var(--accent-purple); }
    .stat-icon.yellow { background: rgba(255, 215, 0, 0.12); color: var(--accent-yellow); }
    
    .stat-info h3 {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 0;
        line-height: 1.2;
    }
    
    .stat-info p {
        color: var(--text-secondary);
        font-size: 0.85rem;
        margin: 5px 0 0;
    }
    
    /* Router info row */
    .router-info-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .router-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        border: 1px solid var(--border-color);
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
    
    /* Quick action buttons */
    .quick-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 15px;
    }
    
    .quick-action-link {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 10px 18px;
        color: var(--text-primary);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .quick-action-link i {
        color: var(--accent-cyan);
        font-size: 0.9rem;
    }
    
    .quick-action-link:hover {
        text-decoration: none;
        background: var(--accent-cyan);
        border-color: var(--accent-cyan);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    .quick-action-link:hover i {
        color: var(--primary);
    }
    
    /* Alert banner */
    .alert-banner {
        background: linear-gradient(135deg, rgba(255, 159, 67, 0.12), rgba(255, 159, 67, 0.05));
        border-left: 4px solid var(--accent-orange);
        border-radius: var(--radius-md);
        padding: 14px 20px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    
    .alert-content {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .alert-content i {
        color: var(--accent-orange);
        font-size: 1.1rem;
    }
    
    /* Chart containers */
    .chart-wrapper {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        padding: 20px;
        height: 100%;
    }
    
    .chart-title {
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-secondary);
    }
    
    .chart-title i {
        color: var(--accent-cyan);
    }
    
    /* Log container */
    .log-container {
        max-height: 320px;
        overflow-y: auto;
    }
    
    .log-entry {
        padding: 10px 16px;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.8rem;
        display: flex;
        gap: 16px;
        font-family: 'Courier New', monospace;
    }
    
    .log-time {
        color: var(--text-muted);
        min-width: 100px;
        flex-shrink: 0;
    }
    
    .log-message {
        color: var(--text-secondary);
        word-break: break-word;
    }
    
    /* Progress bar */
    .progress {
        background: #2a2a3e;
        border-radius: 10px;
        height: 6px;
        overflow: hidden;
        margin: 8px 0;
    }
    
    .progress-bar {
        height: 100%;
        border-radius: 10px;
        transition: width 0.3s;
    }
    
    /* Responsive */
    @media (max-width: 1200px) {
        .stats-grid, .router-info-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid, .router-info-grid {
            grid-template-columns: 1fr;
        }
        
        .stat-card {
            padding: 15px;
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            font-size: 1.3rem;
        }
        
        .stat-info h3 {
            font-size: 1.4rem;
        }
        
        .log-entry {
            flex-direction: column;
            gap: 4px;
        }
        
        .log-time {
            min-width: auto;
        }
    }
</style>

<div class="dashboard-container" style="max-width: 1600px; margin: 0 auto; padding: 0;">
    <!-- Stats Grid - Same style as customers page -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon cyan">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($totalCustomers); ?></h3>
                <p>Total Pelanggan</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($activeCustomers); ?></h3>
                <p>Aktif</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-ban"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($isolatedCustomers); ?></h3>
                <p>Terisolir</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($unpaidCount); ?></h3>
                <p>Belum Lunas Bulan Ini</p>
            </div>
        </div>
    </div>

    <!-- Alert Banner -->
    <?php if ($overdueInvoices > 0 || $dueSoonInvoices > 0): ?>
    <div class="alert-banner">
        <div class="alert-content">
            <i class="fas fa-exclamation-triangle"></i>
            <span>
                <?php if ($overdueInvoices > 0): ?>
                    <strong><?php echo $overdueInvoices; ?> invoice</strong> melewati jatuh tempo.
                <?php endif; ?>
                <?php if ($dueSoonInvoices > 0): ?>
                    <strong><?php echo $dueSoonInvoices; ?> invoice</strong> akan jatuh tempo dalam 7 hari.
                <?php endif; ?>
            </span>
        </div>
        <a href="invoices.php" class="quick-action-link" style="padding: 6px 14px;">
            <i class="fas fa-arrow-right"></i> Lihat Detail
        </a>
    </div>
    <?php endif; ?>

    <!-- Router Info Row -->
    <div class="router-info-grid">
        <div class="router-card">
            <div class="router-icon" style="background: rgba(0, 212, 255, 0.1); color: var(--accent-cyan);">
                <i class="fas fa-calendar"></i>
            </div>
            <div class="router-info">
                <div class="router-label">SERVER TIME</div>
                <div class="router-value"><?php echo date('d M Y H:i:s'); ?></div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">Uptime: <?php echo htmlspecialchars($routerResource['uptime']); ?></div>
            </div>
        </div>
        
        <div class="router-card">
            <div class="router-icon" style="background: rgba(168, 85, 247, 0.1); color: var(--accent-purple);">
                <i class="fas fa-microchip"></i>
            </div>
            <div class="router-info">
                <div class="router-label">ROUTERBOARD</div>
                <div class="router-value"><?php echo htmlspecialchars($routerResource['board-name']); ?></div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">v<?php echo htmlspecialchars($routerResource['version']); ?></div>
            </div>
        </div>
        
        <div class="router-card">
            <div class="router-icon" style="background: rgba(255, 159, 67, 0.1); color: var(--accent-orange);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="router-info" style="flex: 1;">
                <div style="display: flex; justify-content: space-between;">
                    <span class="router-label">CPU</span>
                    <span class="router-value"><?php echo $routerResource['cpu-load']; ?>%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo $routerResource['cpu-load']; ?>%; background: <?php echo $routerResource['cpu-load'] > 80 ? '#ff4d4d' : '#ff9f43'; ?>;"></div>
                </div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">Free RAM: <?php echo formatBytes($routerResource['free-memory']); ?></div>
            </div>
        </div>
        
        <div class="router-card">
            <div class="router-icon" style="background: rgba(0, 230, 118, 0.1); color: var(--accent-green);">
                <i class="fas fa-plug"></i>
            </div>
            <div class="router-info">
                <div class="router-label">MIKROTIK API</div>
                <div class="router-value">
                    <i class="fas fa-circle" style="color: #00e676; font-size: 0.6rem;"></i>
                    Connected
                </div>
                <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo htmlspecialchars(getSettingValue('MIKROTIK_HOST')); ?></div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-bolt"></i> Menu Cepat</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="customers.php" class="quick-action-link"><i class="fas fa-users"></i> Pelanggan</a>
                <a href="packages.php" class="quick-action-link"><i class="fas fa-box"></i> Paket</a>
                <a href="invoices.php" class="quick-action-link"><i class="fas fa-file-invoice"></i> Invoice</a>
                <a href="mikrotik.php" class="quick-action-link"><i class="fas fa-network-wired"></i> PPPoE</a>
                <a href="genieacs.php" class="quick-action-link"><i class="fas fa-satellite-dish"></i> GenieACS</a>
                <a href="trouble.php" class="quick-action-link"><i class="fas fa-exclamation-triangle"></i> Gangguan</a>
                <a href="settings.php" class="quick-action-link"><i class="fas fa-cog"></i> Settings</a>
                <a href="export.php" class="quick-action-link"><i class="fas fa-file-excel"></i> Export/Import</a>
            </div>
        </div>
    </div>

    <!-- Live Monitoring Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px; margin-bottom: 30px;">
        <!-- Traffic Monitor -->
        <div class="card">
            <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
                <h3 class="card-title"><i class="fas fa-chart-area"></i> Traffic Monitor</h3>
                <select id="interfaceSelector" class="form-control" style="width: auto; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 20px; padding: 6px 14px; font-size: 0.8rem;">
                    <?php foreach ($interfaces as $iface): ?>
                        <option value="<?php echo htmlspecialchars($iface['name'] ?? ''); ?>"><?php echo htmlspecialchars($iface['name'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="card-body">
                <canvas id="trafficChart" height="200" style="max-height: 240px; width: 100%;"></canvas>
            </div>
        </div>

        <!-- PPPoE Log -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> PPPoE Event Log</h3>
            </div>
            <div class="log-container" id="pppoeLogContainer">
                <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                    <i class="fas fa-spinner fa-spin"></i> Memuat log...
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 30px;">
        <div class="chart-wrapper">
            <div class="chart-title">
                <i class="fas fa-chart-line"></i> Pendapatan 6 Bulan Terakhir
            </div>
            <canvas id="revenueChart" height="200" style="width: 100%;"></canvas>
        </div>
        
        <div class="chart-wrapper">
            <div class="chart-title">
                <i class="fas fa-chart-bar"></i> Pertumbuhan Pelanggan Baru
            </div>
            <canvas id="customerChart" height="200" style="width: 100%;"></canvas>
        </div>
    </div>

    <!-- Tables Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px;">
        <!-- Recent Invoices -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice"></i> Invoice Terbaru</h3>
                <a href="invoices.php" class="quick-action-link" style="padding: 6px 12px; font-size: 0.75rem;">
                    <i class="fas fa-arrow-right"></i> Semua
                </a>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>Invoice</th><th>Pelanggan</th><th>Jumlah</th><th>Status</th><th>Tanggal</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentInvoices)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada invoice</td></tr>
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

        <!-- Recent Customers -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-plus"></i> Pelanggan Terbaru</h3>
                <a href="customers.php" class="quick-action-link" style="padding: 6px 12px; font-size: 0.75rem;">
                    <i class="fas fa-arrow-right"></i> Semua
                </a>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>Nama</th><th>PPPoE</th><th>Paket</th><th>Status</th><th>Terdaftar</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentCustomers)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada pelanggan</td></tr>
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
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($monthlyRevenue, 'month')); ?>,
            datasets: [{
                label: 'Pendapatan',
                data: <?php echo json_encode(array_column($monthlyRevenue, 'revenue')); ?>,
                borderColor: '#00d4ff',
                backgroundColor: 'rgba(0, 212, 255, 0.05)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#00d4ff',
                pointBorderColor: '#00d4ff',
                pointRadius: 3,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => 'Rp ' + ctx.parsed.y.toLocaleString('id-ID') } }
            },
            scales: {
                y: { ticks: { callback: (v) => 'Rp ' + v.toLocaleString('id-ID'), color: '#a0a0b0' }, grid: { color: '#2a2a3e' } },
                x: { ticks: { color: '#a0a0b0' }, grid: { display: false } }
            }
        }
    });

    // Customer Chart
    const customerCtx = document.getElementById('customerChart').getContext('2d');
    new Chart(customerCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($monthlyCustomers, 'month')); ?>,
            datasets: [{
                label: 'Pelanggan Baru',
                data: <?php echo json_encode(array_column($monthlyCustomers, 'count')); ?>,
                backgroundColor: '#a855f7',
                borderRadius: 8,
                barPercentage: 0.65
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { ticks: { stepSize: 1, color: '#a0a0b0' }, grid: { color: '#2a2a3e' } },
                x: { ticks: { color: '#a0a0b0' }, grid: { display: false } }
            }
        }
    });

    // Traffic Monitor
    let trafficChart;
    const MAX_POINTS = 20;
    let currentInterface = document.getElementById('interfaceSelector')?.value || 'ether1';

    function formatBits(b) {
        if (b === 0) return '0 bps';
        const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps'];
        let i = Math.floor(Math.log(b) / Math.log(1024));
        return (b / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i];
    }

    function initTraffic() {
        const ctx = document.getElementById('trafficChart').getContext('2d');
        trafficChart = new Chart(ctx, {
            type: 'line',
            data: { 
                labels: [], 
                datasets: [
                    { label: 'Upload (Tx)', data: [], borderColor: '#00e676', backgroundColor: 'rgba(0,230,118,0.05)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 0 },
                    { label: 'Download (Rx)', data: [], borderColor: '#00d4ff', backgroundColor: 'rgba(0,212,255,0.05)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 0 }
                ] 
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { 
                    tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatBits(ctx.parsed.y)}` } },
                    legend: { labels: { color: '#a0a0b0', usePointStyle: true } }
                },
                scales: { 
                    y: { ticks: { callback: (v) => formatBits(v), color: '#a0a0b0' }, grid: { color: '#2a2a3e' } }, 
                    x: { ticks: { color: '#a0a0b0', maxRotation: 45 }, grid: { display: false } } 
                }
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

    // PPPoE Log
    function loadPppoeLog() {
        fetch('../api/pppoe-log.php?limit=15')
            .then(r => r.json())
            .then(logs => {
                const container = document.getElementById('pppoeLogContainer');
                if (!logs || logs.length === 0) {
                    container.innerHTML = '<div style="text-align: center; padding: 30px; color: var(--text-muted);"><i class="fas fa-info-circle"></i> Tidak ada log PPPoE</div>';
                    return;
                }
                let html = '';
                logs.forEach(l => {
                    const time = l.time || '';
                    const message = l.message || '';
                    html += `<div class="log-entry">
                        <span class="log-time">${escapeHtml(time)}</span>
                        <span class="log-message">${escapeHtml(message)}</span>
                    </div>`;
                });
                container.innerHTML = html;
            }).catch(() => {
                document.getElementById('pppoeLogContainer').innerHTML = '<div style="text-align: center; padding: 30px; color: var(--text-muted);"><i class="fas fa-exclamation-triangle"></i> Gagal memuat log</div>';
            });
    }

    function escapeHtml(s) {
        if (!s) return '';
        return s.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initTraffic();
        fetchTraffic();
        setInterval(fetchTraffic, 4000);
        loadPppoeLog();
        setInterval(loadPppoeLog, 10000);
        
        const selector = document.getElementById('interfaceSelector');
        if (selector) {
            selector.addEventListener('change', function() { changeInterface(this.value); });
        }
    });
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>