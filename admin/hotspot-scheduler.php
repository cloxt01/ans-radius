<?php
/**
 * System Scheduler Viewer
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'System Schedulers';

// Get Data
$schedulers = mikrotikGetSchedulers();
$totalSchedulers = count($schedulers);

ob_start();
?>

<!-- Display status connection mikrotik -->
<?php if (!mikrotikConnect()): ?>
<div style="background: rgba(255, 0, 0, 0.1); border: 1px solid #ff4444; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 10px; color: #ff6666;">
        <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
        <div>
            <strong>Gagal terhubung ke MikroTik!</strong>
            <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: #f38282;">
                Profile yang ditampilkan adalah profil default. 
                Silakan periksa pengaturan MikroTik di <a href="settings.php" style="color: #66ccff;">Settings</a> 
                untuk memastikan kredensial benar.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- Stats -->
<div class="stats-grid" style="margin-bottom: 20px;">
    <div class="stat-card">
        <div class="stat-icon pink"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <h3>
                <?php echo $totalSchedulers; ?>
            </h3>
            <p>Total Schedulers</p>
        </div>
    </div>
</div>

<!-- Schedulers Table -->
<div class="card">
    <div class="card-header">
        <div style="display: flex; align-items: center; gap: 10px; color: var(--neon-cyan)">
            <i class="fas fa-calendar-alt"></i>
            <h3 class="card-title">Daftar System Scheduler</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Start Date</th>
                    <th>Start Time</th>
                    <th>Interval</th>
                    <th>Next Run</th>
                    <th>Run Count</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedulers)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">
                            <i class="fas fa-calendar-alt" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Tidak ada scheduler ditemukan</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schedulers as $s): ?>
                        <tr>
                            <td><strong>
                                    <?php echo htmlspecialchars($s['name'] ?? '-'); ?>
                                </strong></td>
                            <td>
                                <?php echo htmlspecialchars($s['start-date'] ?? '-'); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($s['start-time'] ?? '-'); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($s['interval'] ?? '-'); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($s['next-run'] ?? '-'); ?>
                            </td>
                            <td><span class="badge badge-info">
                                    <?php echo htmlspecialchars($s['run-count'] ?? '0'); ?>
                                </span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
