<?php
/**
 * PPPoE Active Sessions
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'PPPoE Active Sessions';

// Get active sessions
// $activeSessions = mikrotikGetPro();

$activeSessions = mikrotikGetActiveSessions();

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
                Data sesi aktif tidak dapat diambil. 
                Silakan periksa pengaturan MikroTik di <a href="settings.php" style="color: #66ccff;">Settings</a> 
                untuk memastikan kredensial benar.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px;">
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-plug"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($activeSessions); ?></h3>
            <p>Sesi Aktif</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-server"></i>
        </div>
        <div class="stat-info">
            <?php
            $totalUpload = 0;
            foreach ($activeSessions as $session) {
                $bytes = (int) ($session['limit-bytes-out'] ?? 0);
                $totalUpload += $bytes;
            }
            ?>
            <h3><?php echo formatBytes($totalUpload); ?></h3>
            <p>Total Upload</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-arrow-down"></i>
        </div>
        <div class="stat-info">
            <?php
            $totalDownload = 0;
            foreach ($activeSessions as $session) {
                $bytes = (int) ($session['limit-bytes-in'] ?? 0);
                $totalDownload += $bytes;
            }
            ?>
            <h3><?php echo formatBytes($totalDownload); ?></h3>
            <p>Total Download</p>
        </div>
    </div>
</div>

<style>
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .stat-card {
            padding: 15px;
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
        }
        .stat-info h3 {
            font-size: 1.5rem;
        }
        .stat-info p {
            font-size: 0.8rem;
        }
    }
</style>

<!-- Active Sessions Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list"></i> Daftar Sesi Aktif</h3>
    </div>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Alamat IP</th>
                    <th>Caller ID</th>
                    <th>Upload</th>
                    <th>Download</th>
                    <th>Uptime</th>
                    <th>Radius</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activeSessions)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            <i class="fas fa-wifi" style="font-size: 2rem; margin: 12px 0; display: block;"></i>
                            Tidak ada sesi PPPoE aktif
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activeSessions as $session): ?>
                        <tr>
                            
                            <td data-label="Username">
                                <strong><?php echo htmlspecialchars($session['name'] ?? '-'); ?></strong>
                            </td>
                            <td data-label="Alamat IP">
                                <code><?php echo htmlspecialchars($session['address'] ?? '-'); ?></code>
                            </td>
                            <td data-label="Caller ID">
                                <?php echo htmlspecialchars($session['caller-id'] ?? '-'); ?>
                            </td>
                            <td data-label="Upload">
                                <span class="badge badge-info">
                                    <?php echo formatBytes((int) ($session['limit-bytes-out'] ?? 0)); ?>
                                </span>
                            </td>
                            <td data-label="Download">
                                <span class="badge badge-success">
                                    <?php echo formatBytes((int) ($session['limit-bytes-in'] ?? 0)); ?>
                                </span>
                            </td>
                            <td data-label="Uptime">
                                <?php
                                $uptime = (int) ($session['uptime'] ?? 0);
                                if ($uptime > 0) {
                                    $hours = intdiv($uptime, 3600);
                                    $minutes = intdiv($uptime % 3600, 60);
                                    $seconds = $uptime % 60;
                                    echo sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $radiusStatus = $session['radius'] ?? 'unknown';
                                if ($radiusStatus !== 'true') {
                                    $statusClass = 'badge-danger';
                                    $statusText = 'Tidak';
                                }
                                else {
                                    $statusClass = 'badge-success';
                                    $statusText = 'Ya';
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
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
require_once '../includes/layout.php';
