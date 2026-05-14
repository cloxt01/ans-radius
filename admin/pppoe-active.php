<?php
/**
 * PPPoE Active Sessions - Elegant Dark Minimalis Theme
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'PPPoE Active Sessions';

// Get active sessions
$activeSessions = mikrotikGetActiveSessionsAllRouter();

ob_start();
?>

<!-- Warning Connection -->
<?php if (!mikrotikConnect()): ?>
<div class="alert alert-warning" style="margin-bottom: 24px;">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>Gagal terhubung ke MikroTik!</strong>
        <p style="margin: 4px 0 0 0; font-size: 13px;">
            Data sesi aktif tidak dapat diambil. 
            Silakan periksa pengaturan MikroTik di <a href="settings.php" style="color: var(--accent-blue);">Settings</a>.
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo count($activeSessions); ?></h3>
            <p>Sesi Aktif</p>
        </div>
        <div class="stat-icon blue">
            <i class="fas fa-plug"></i>
        </div>
    </div>
    
    <div class="stat-card">
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
        <div class="stat-icon orange">
            <i class="fas fa-upload"></i>
        </div>
    </div>
    
    <div class="stat-card">
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
        <div class="stat-icon green">
            <i class="fas fa-download"></i>
        </div>
    </div>
</div>

<!-- Active Sessions Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-list"></i> Daftar Sesi Aktif
        </h3>
        <div class="session-info">
            <span class="badge badge-info">
                <i class="fas fa-sync-alt"></i> Real-time
            </span>
            <button onclick="refreshPage()" class="btn-icon" title="Refresh">
                <i class="fas fa-redo-alt"></i>
            </button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="data-table" id="activeSessionsTable">
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
                        <td colspan="7" class="empty-state">
                            <i class="fas fa-wifi"></i>
                            <p>Tidak ada sesi PPPoE aktif</p>
                            <small>Belum ada pengguna yang sedang online</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activeSessions as $session): ?>
                        <?php
                        $uptime = (int) ($session['uptime'] ?? 0);
                        $isRadius = ($session['radius'] ?? 'unknown') === 'true';
                        $upload = (int) ($session['limit-bytes-out'] ?? 0);
                        $download = (int) ($session['limit-bytes-in'] ?? 0);
                        ?>
                        <tr>
                            <td data-label="Username">
                                <div class="user-info">
                                    <div class="user-avatar online">
                                        <?php echo strtoupper(substr($session['name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <strong><?php echo htmlspecialchars($session['name'] ?? '-'); ?></strong>
                                        <small class="status-badge">
                                            <span class="online-dot"></span> Online
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Alamat IP">
                                <code class="ip-address"><?php echo htmlspecialchars($session['address'] ?? '-'); ?></code>
                            </td>
                            <td data-label="Caller ID">
                                <span class="caller-id"><?php echo htmlspecialchars($session['caller-id'] ?? '-'); ?></span>
                            </td>
                            <td data-label="Upload">
                                <div class="traffic-info upload">
                                    <i class="fas fa-arrow-up"></i>
                                    <span class="traffic-value"><?php echo formatBytes($upload); ?></span>
                                </div>
                            </td>
                            <td data-label="Download">
                                <div class="traffic-info download">
                                    <i class="fas fa-arrow-down"></i>
                                    <span class="traffic-value"><?php echo formatBytes($download); ?></span>
                                </div>
                            </td>
                            <td data-label="Uptime">
                                <div class="uptime-info">
                                    <i class="fas fa-clock"></i>
                                    <?php if ($uptime > 0): ?>
                                        <span class="uptime-value">
                                            <?php 
                                            $hours = intdiv($uptime, 3600);
                                            $minutes = intdiv($uptime % 3600, 60);
                                            $seconds = $uptime % 60;
                                            echo sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Radius">
                                <?php if ($isRadius): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Ya
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-muted">
                                        <i class="fas fa-times-circle"></i> Tidak
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* Additional styles for active sessions page */
.session-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    position: relative;
}

.user-avatar.online {
    background: rgba(63, 185, 80, 0.15);
    color: var(--accent-green);
    box-shadow: 0 0 0 2px rgba(63, 185, 80, 0.2);
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-details strong {
    font-size: 14px;
}

.status-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: var(--accent-green);
}

.online-dot {
    width: 6px;
    height: 6px;
    background: var(--accent-green);
    border-radius: 50%;
    display: inline-block;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(63, 185, 80, 0.4);
    }
    70% {
        box-shadow: 0 0 0 6px rgba(63, 185, 80, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(63, 185, 80, 0);
    }
}

.ip-address {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 12px;
    background: var(--bg-tertiary);
    padding: 4px 8px;
    border-radius: 4px;
    color: var(--accent-blue);
}

.caller-id {
    font-family: monospace;
    font-size: 12px;
    color: var(--text-secondary);
}

.traffic-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.traffic-info.upload i {
    color: var(--accent-orange);
}

.traffic-info.download i {
    color: var(--accent-green);
}

.traffic-value {
    font-weight: 600;
    font-size: 13px;
}

.uptime-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.uptime-info i {
    color: var(--accent-blue);
    font-size: 12px;
}

.uptime-value {
    font-family: monospace;
    font-size: 12px;
    font-weight: 500;
}

.text-muted {
    color: var(--text-muted);
}

.btn-icon {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-light);
    color: var(--text-secondary);
    cursor: pointer;
    padding: 6px 10px;
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
}

.btn-icon:hover {
    background: var(--bg-secondary);
    border-color: var(--border-color);
    color: var(--accent-blue);
}

.empty-state {
    text-align: center;
    padding: 48px 20px !important;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 14px;
}

.empty-state small {
    font-size: 12px;
}

/* Auto-refresh indicator */
@keyframes spin {
    to { transform: rotate(360deg); }
}

.btn-icon .fa-redo-alt {
    transition: transform var(--transition-base);
}

.btn-icon:hover .fa-redo-alt {
    transform: rotate(180deg);
}

/* Responsive */
@media (max-width: 768px) {
    .user-info {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .session-info {
        width: 100%;
        justify-content: space-between;
        margin-top: 12px;
    }
    
    .traffic-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    
    .uptime-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
}

/* Table cell min-width for better readability */
@media (min-width: 769px) {
    .data-table td {
        white-space: nowrap;
    }
}
</style>

<script>
// Auto-refresh every 30 seconds
let autoRefreshInterval = null;
let isAutoRefreshEnabled = true;

function refreshPage() {
    window.location.reload();
}

function toggleAutoRefresh() {
    isAutoRefreshEnabled = !isAutoRefreshEnabled;
    const btn = document.getElementById('autoRefreshBtn');
    
    if (isAutoRefreshEnabled) {
        startAutoRefresh();
        if (btn) {
            btn.innerHTML = '<i class="fas fa-pause"></i>';
            btn.title = 'Jeda Auto-Refresh';
        }
    } else {
        stopAutoRefresh();
        if (btn) {
            btn.innerHTML = '<i class="fas fa-play"></i>';
            btn.title = 'Mulai Auto-Refresh';
        }
    }
}

function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
    autoRefreshInterval = setInterval(() => {
        if (isAutoRefreshEnabled && document.hasFocus()) {
            refreshPage();
        }
    }, 30000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

// Start auto-refresh by default
document.addEventListener('DOMContentLoaded', function() {
    startAutoRefresh();
    
    // Add auto-refresh toggle button if not exists
    const sessionInfo = document.querySelector('.session-info');
    if (sessionInfo && !document.getElementById('autoRefreshBtn')) {
        const btn = document.createElement('button');
        btn.id = 'autoRefreshBtn';
        btn.className = 'btn-icon';
        btn.innerHTML = '<i class="fas fa-pause"></i>';
        btn.title = 'Jeda Auto-Refresh';
        btn.onclick = toggleAutoRefresh;
        sessionInfo.appendChild(btn);
    }
});

// Stop auto-refresh when page is hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
    } else {
        if (isAutoRefreshEnabled && !autoRefreshInterval) {
            startAutoRefresh();
        }
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';