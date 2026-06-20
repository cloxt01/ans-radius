<?php
/**
 * Agent Portal - Wallet & Commission
 */

require_once '../includes/auth.php';
requireAgentLogin();

$agentSession = getCurrentAgent();
$agent = fetchOne('SELECT * FROM agents WHERE id = ?', [$agentSession['id']]);

// Kalkulasi sederhana: Total Pelanggan Aktif x Fee Agen
$activeCustomers = fetchOne('SELECT COUNT(id) as total FROM customers WHERE agent_id = ? AND status = "active"', [$agent['id']]);
$totalActive = $activeCustomers['total'];
$estimatedCommission = $totalActive * $agent['fee'];

$pageTitle = 'Komisi & Saldo';
ob_start();
?>

    <div style="max-width: 800px; margin: 0 auto;">
        <div class="card" style="background: var(--gradient-primary); border: none; text-align: center; padding: 40px 20px;">
            <h2 style="color: white; margin-bottom: 10px; font-weight: 400;">Potensi Komisi Bulan Ini</h2>
            <h1 style="color: white; font-size: 3rem; margin-bottom: 20px;">
                Rp <?php echo number_format($estimatedCommission, 0, ',', '.'); ?>
            </h1>
            <div style="display: inline-flex; gap: 20px; background: rgba(0,0,0,0.2); padding: 10px 20px; border-radius: 20px; color: rgba(255,255,255,0.9);">
                <span><i class="fas fa-users"></i> <?php echo $totalActive; ?> Pelanggan Aktif</span>
                <span>|</span>
                <span><i class="fas fa-tag"></i> Rp <?php echo number_format($agent['fee'], 0, ',', '.'); ?> / user</span>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title" style="margin-bottom: 15px;"><i class="fas fa-info-circle"></i> Informasi Komisi</h3>
            <p style="color: var(--text-secondary);">
                Komisi dihitung berdasarkan jumlah pelanggan dengan status <strong>Aktif</strong> yang berhasil membayar tagihan pada bulan berjalan.
                Pencairan komisi dapat dilakukan dengan menghubungi admin pusat atau langsung dipotong dari tagihan setor Anda.
            </p>
        </div>
    </div>

<?php
$content = ob_get_clean();
require_once '../includes/agent_layout.php';
?>