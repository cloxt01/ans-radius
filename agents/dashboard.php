<?php
/**
 * Agent Portal - Dashboard
 */

require_once '../includes/auth.php';
requireAgentLogin();

$agentSession = getCurrentAgent();

// Inisialisasi variabel default
$agent = [];
$customers = [];
$activeCustomers = [];
$recentInvoices = [];

// Fetch fresh agent & data
if ($agentSession && isset($agentSession['id'])) {
    $agent = fetchOne('SELECT * FROM agents WHERE id = ?', [$agentSession['id']]);

    if ($agent) {
        // Jika agen ditemukan, perbarui sesi dan ambil data lainnya
        $agent['logged_in'] = true;
        $agent['login_time'] = $agentSession['login_time'] ?? time();
        $agent['must_change_password'] = password_verify('1234', $agent['password']);
        $_SESSION['agent'] = $agent;

        // PINDAHKAN QUERY KE DALAM BLOK INI
        $fetchedCustomers = fetchAll('SELECT * FROM customers WHERE agent_id = ?', [$agent['id']]);
        if ($fetchedCustomers) {
            $customers = $fetchedCustomers;
            $activeCustomers = array_filter($customers, fn($c) => $c['status'] === 'active');
        }

        $fetchedInvoices = fetchAll("
            SELECT i.*, c.name as customer_name 
            FROM invoices i 
            JOIN customers c ON i.customer_id = c.id 
            WHERE c.agent_id = ? 
            ORDER BY i.created_at DESC LIMIT 5
        ", [$agent['id']]);

        if ($fetchedInvoices) {
            $recentInvoices = $fetchedInvoices;
        }
    } else {
        // Jika data agen tidak ditemukan di database (misal terhapus), paksa logout
        agentLogout();
        exit;
    }
}

$pageTitle = 'Dashboard';
ob_start();
?>

    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    </style>

    <div style="max-width: 1200px; margin: 0 auto;">
        <?php if (!empty($agent['must_change_password'])): ?>
            <div class="card" style="border: 1px solid var(--neon-red); background: rgba(255, 71, 87, 0.05);">
                <h3 style="margin-bottom: 10px; color: var(--neon-red);">
                    <i class="fas fa-exclamation-triangle"></i> Keamanan Akun
                </h3>
                <p style="color: var(--text-secondary); margin: 0 0 15px 0;">
                    Password Anda saat ini masih default (1234). Demi keamanan, mohon segera ubah password Anda di halaman profil.
                </p>
                <a href="profile.php" class="btn btn-primary" style="background: var(--neon-red); color: white;">
                    <i class="fas fa-key"></i> Ubah Password Sekarang
                </a>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon cyan"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo count($customers); ?></h3>
                    <p>Total Pelanggan</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-wifi"></i></div>
                <div class="stat-info">
                    <h3><?php echo count($activeCustomers); ?></h3>
                    <p>Pelanggan Aktif</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info">
                    <h3>Rp <?php echo number_format($agent['fee'] ?? 0, 0, ',', '.'); ?></h3>
                    <p>Komisi per Pelanggan</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-file-invoice"></i> Tagihan Pelanggan Terbaru</div>
                <a href="reports.php" style="font-size: 0.9rem;">Lihat Semua</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Pelanggan</th>
                        <th>Nominal</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recentInvoices)): ?>
                        <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">Belum ada data tagihan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentInvoices as $inv): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($inv['customer_name']); ?></strong></td>
                                <td>Rp <?php echo number_format($inv['amount'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php if ($inv['status'] === 'paid'): ?>
                                        <span class="badge badge-success">Lunas</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Belum Bayar</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:var(--text-secondary); font-size: 0.9rem;">
                                    <?php echo date('d M Y', strtotime($inv['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php
// BAGIAN INI SANGAT PENTING DAN TIDAK BOLEH HILANG
$content = ob_get_clean();
require_once '../includes/agent_layout.php';
?>