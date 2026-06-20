<?php
/**
 * Agent Portal - Customers List
 */

require_once '../includes/auth.php';
requireAgentLogin();

$agent = getCurrentAgent();
$customers = fetchAll('
    SELECT c.*, p.name as package_name 
    FROM customers c 
    LEFT JOIN packages p ON c.package_id = p.id 
    WHERE c.agent_id = ? 
    ORDER BY c.name ASC
', [$agent['id']]);

$pageTitle = 'Data Pelanggan';
ob_start();
?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-users"></i> Daftar Pelanggan Saya</h3>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Nama & Info</th>
                    <th>Kontak</th>
                    <th>Paket</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="4" style="text-align:center; padding: 40px; color:var(--text-muted);">Belum ada pelanggan terdaftar di bawah Anda.</td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td data-label="Nama & Info">
                                <strong><?php echo htmlspecialchars($c['name']); ?></strong><br>
                                <small style="color:var(--text-secondary);"><i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($c['pppoe_username']); ?></small>
                            </td>
                            <td data-label="Kontak">
                                <?php if(!empty($c['phone'])): ?>
                                    <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $c['phone'])); ?>" target="_blank" style="color: #25D366;">
                                        <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($c['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td data-label="Paket"><?php echo htmlspecialchars($c['package_name'] ?? 'Tanpa Paket'); ?></td>
                            <td data-label="Status">
                                <?php if ($c['status'] === 'active'): ?>
                                    <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Isolir</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php
$content = ob_get_clean();
require_once '../includes/agent_layout.php';
?>