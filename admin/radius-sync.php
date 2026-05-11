<?php
/**
 * RADIUS Synchronization Management
 * Manage RADIUS user timeout synchronization and provisioning
 */

require_once '../includes/auth.php';
require_once '../includes/radius.php';
requireAdminLogin();

ob_start();

$pageTitle = 'RADIUS Management';
$action = isset($_GET['action']) ? (string) $_GET['action'] : 'dashboard';

// Handle manual sync trigger
$syncResult = null;
$syncMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Security check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = 'Security token mismatch';
    } else {
        $syncType = isset($_POST['sync_type']) ? $_POST['sync_type'] : 'radius';
        
        if ($syncType === 'customers') {
            // Sync all customers' timeouts to radreply
            echo "<h3>Syncing All Customers RADIUS Timeout...</h3>";
            echo "<pre>";
            
            $syncResult = syncAllCustomersRadiusTimeout();
            
            echo "Total customers: " . $syncResult['total'] . "\n";
            echo "Updated: " . $syncResult['updated'] . " users\n";
            echo "Failed: " . $syncResult['failed'] . " users\n";
            echo "Skipped: " . $syncResult['skipped'] . " users\n";
            
            echo "</pre>";
            
            // Log the sync operation
            logActivity('RADIUS_SYNC_CUSTOMERS', sprintf(
                "Total: %d, Updated: %d, Failed: %d, Skipped: %d",
                $syncResult['total'],
                $syncResult['updated'],
                $syncResult['failed'],
                $syncResult['skipped']
            ));
            
            $syncMessage = sprintf('Synced %d out of %d customers successfully!', $syncResult['updated'], $syncResult['total']);
        } else {
            // Sync existing RADIUS users (original behavior)
            echo "<h3>Running RADIUS Timeout Sync...</h3>";
            echo "<pre>";
            
            // Run sync
            $syncResult = radiusUpdateAllSessionTimeoutsFromIsolationDates();
            
            echo "Updated: " . $syncResult['updated'] . " users\n";
            echo "Failed: " . $syncResult['failed'] . " users\n";
            echo "Skipped: " . $syncResult['skipped'] . " users\n";
            echo "Runtime: " . $syncResult['runtime_seconds'] . "s\n\n";
            
            if (!empty($syncResult['messages'])) {
                echo "Messages:\n";
                foreach ($syncResult['messages'] as $msg) {
                    echo "  - " . htmlspecialchars($msg) . "\n";
                }
            }
            
            echo "</pre>";
            
            // Log the sync operation
            if ($syncResult['updated'] > 0 || $syncResult['failed'] > 0) {
                logActivity('RADIUS_MANUAL_SYNC', sprintf(
                    "Updated: %d, Failed: %d, Skipped: %d, Runtime: %.2fs",
                    $syncResult['updated'],
                    $syncResult['failed'],
                    $syncResult['skipped'],
                    $syncResult['runtime_seconds']
                ));
            }
            
            $syncMessage = 'Sync completed successfully!';
        }
    }
}

// Check RADIUS connectivity
$radiusReady = false;
$radiusStatus = '';
$radiusDbStats = [];

try {
    if (function_exists('radiusUserProvisioningReady')) {
        $radiusReady = radiusUserProvisioningReady();
        
        if ($radiusReady) {
            $radiusStatus = '✓ Connected and ready';
            
            // Get database stats
            try {
                $pdo = radiusDbConnection();
                
                $stats = [
                    'radcheck' => (int) $pdo->query("SELECT COUNT(*) as cnt FROM radcheck")->fetch()['cnt'] ?? 0,
                    'radreply' => (int) $pdo->query("SELECT COUNT(*) as cnt FROM radreply")->fetch()['cnt'] ?? 0,
                    'radusergroup' => (int) $pdo->query("SELECT COUNT(*) as cnt FROM radusergroup")->fetch()['cnt'] ?? 0,
                    'radgroupreply' => (int) $pdo->query("SELECT COUNT(*) as cnt FROM radgroupreply")->fetch()['cnt'] ?? 0,
                ];
                
                $radiusDbStats = $stats;
            } catch (Exception $e) {
                $radiusStatus = '⚠ Connected but cannot fetch stats: ' . $e->getMessage();
            }
        } else {
            $radiusStatus = '✗ RADIUS tables not found - provisioning disabled';
        }
    }
} catch (Exception $e) {
    $radiusStatus = '✗ Connection error: ' . $e->getMessage();
}

// Get PPPoE users with their status
$pppoePUsers = [];
try {
    $pppoUsers = fetchAll("
        SELECT c.id, c.name, c.pppoe_username, c.isolation_date, c.status
        FROM customers 
        WHERE pppoe_username IS NOT NULL AND pppoe_username != ''
        ORDER BY c.name ASC
    ");
    
    if ($radiusReady && !empty($pppoeUsers)) {
        $pdo = radiusDbConnection();
        
        foreach ($pppoeUsers as $user) {
            // Check RADIUS status
            $radiusUser = $pdo->query(
                "SELECT username FROM radcheck WHERE username = ? LIMIT 1",
                [$user['pppoe_username']]
            )->fetch();
            
            $radreplyTimeout = $pdo->query(
                "SELECT value FROM radreply WHERE username = ? AND attribute = 'Session-Timeout' LIMIT 1",
                [$user['pppoe_username']]
            )->fetch();
            
            $pppoePUsers[] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['pppoe_username'],
                'isolation_date' => $user['isolation_date'],
                'status' => $user['status'],
                'in_radius' => $radiusUser ? 'Yes' : 'No',
                'timeout_seconds' => $radreplyTimeout ? (int) $radreplyTimeout['value'] : 0,
                'timeout_hours' => $radreplyTimeout ? round((int) $radreplyTimeout['value'] / 3600, 1) : 0,
            ];
        }
    }
} catch (Exception $e) {
    // Continue without RADIUS details if connection fails
    if (!empty($pppoeUsers)) {
        foreach ($pppoeUsers as $user) {
            $pppoePUsers[] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['pppoe_username'],
                'isolation_date' => $user['isolation_date'],
                'status' => $user['status'],
                'in_radius' => 'N/A',
                'timeout_seconds' => 0,
                'timeout_hours' => 0,
            ];
        }
    }
}

?>

<div class="container mt-4">
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    
    <!-- Status Alert -->
    <?php if ($syncMessage): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($syncMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($errorMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- RADIUS Status Card -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">RADIUS Database Status</h5>
        </div>
        <div class="card-body">
            <p><strong>Status:</strong> <span class="badge <?= strpos($radiusStatus, '✓') === 0 ? 'bg-success' : 'bg-danger' ?>">
                <?= htmlspecialchars($radiusStatus) ?>
            </span></p>
            
            <?php if ($radiusReady && !empty($radiusDbStats)): ?>
            <div class="row mt-3">
                <div class="col-md-3">
                    <div class="text-center">
                        <h6>Users (radcheck)</h6>
                        <h4><?= $radiusDbStats['radcheck'] ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h6>User Attributes (radreply)</h6>
                        <h4><?= $radiusDbStats['radreply'] ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h6>User Groups</h6>
                        <h4><?= $radiusDbStats['radusergroup'] ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h6>Group Attributes (radgroupreply)</h6>
                        <h4><?= $radiusDbStats['radgroupreply'] ?></h4>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sync Actions -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">Manual Synchronization</h5>
        </div>
        <div class="card-body">
            <p>Recalculate and sync RADIUS Session-Timeout for all PPPoE users based on their isolation_date.</p>
            
            <?php if ($radiusReady): ?>
            <form method="POST" action="?action=sync" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Run RADIUS timeout sync now? This may take a few seconds.');">
                    <i class="fas fa-sync"></i> Run Sync Now
                </button>
            </form>
            <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> RADIUS is not ready. Enable RADIUS provisioning to use this feature.
            </div>
            <?php endif; ?>
            
            <div class="mt-3">
                <h6>Cron Job Setup:</h6>
                <p class="text-muted small">For automatic daily syncing, add this cron schedule to the database:</p>
                <pre class="bg-light p-3"><code>INSERT INTO cron_schedules (name, task_type, schedule_time, schedule_frequency, is_active)
VALUES ('Sync RADIUS Timeouts', 'sync_radius_timeouts', '03:00:00', 'daily', 1);</code></pre>
            </div>
        </div>
    </div>
    
    <?php if ($syncResult): ?>
    <!-- Sync Result Details -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Last Sync Result</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center border-end">
                        <h6>Updated</h6>
                        <h4 class="text-success"><?= $syncResult['updated'] ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center border-end">
                        <h6>Failed</h6>
                        <h4 class="text-danger"><?= $syncResult['failed'] ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center border-end">
                        <h6>Skipped</h6>
                        <h4 class="text-warning"><?= $syncResult['skipped'] ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h6>Runtime</h6>
                        <h4><?= $syncResult['runtime_seconds'] ?>s</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- PPPoE Users Table -->
    <div class="card">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">PPPoE Users Status (<?= count($pppoePUsers) ?>)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>PPPoE Username</th>
                        <th>Service Status</th>
                        <th>Isolation Day</th>
                        <?php if ($radiusReady): ?>
                        <th>In RADIUS</th>
                        <th>Session Timeout</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pppoePUsers as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><code><?= htmlspecialchars($user['username']) ?></code></td>
                        <td>
                            <span class="badge <?= $user['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                <?= htmlspecialchars($user['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($user['isolation_date']) ?></td>
                        <?php if ($radiusReady): ?>
                        <td><?= htmlspecialchars($user['in_radius']) ?></td>
                        <td>
                            <?php if ($user['timeout_seconds'] > 0): ?>
                                <code><?= number_format($user['timeout_seconds']) ?> sec</code>
                                <small class="text-muted">(<?= $user['timeout_hours'] ?> hrs)</small>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($pppoePUsers)): ?>
                    <tr>
                        <td colspan="<?= $radiusReady ? '6' : '4' ?>" class="text-center text-muted py-4">
                            No PPPoE users found
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-muted small">
            Total PPPoE users: <?= count($pppoePUsers) ?>
        </div>
    </div>
    
    <!-- Documentation -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Documentation</h5>
        </div>
        <div class="card-body">
            <h6>About RADIUS Session Timeout</h6>
            <p>The Session-Timeout RADIUS attribute controls how long a user can stay connected before automatic disconnection. This system calculates timeout based on the customer's <code>isolation_date</code> field:</p>
            <ul>
                <li><strong>isolation_date:</strong> Day of month (1-28) when the customer's service is due/isolated</li>
                <li><strong>Calculated timeout:</strong> Seconds until that day at 23:59:59 next month</li>
                <li><strong>Example:</strong> If today is May 11 and isolation_date is 3, timeout = until June 3, 23:59:59</li>
            </ul>
            
            <h6 class="mt-3">How It Works</h6>
            <ol>
                <li>Customer isolation_date determines their billing/isolation cycle (e.g., every 3rd of month)</li>
                <li>When customer is created with PPPoE, RADIUS user is provisioned with initial timeout</li>
                <li>Daily cron job syncs all user timeouts to ensure they're fresh</li>
                <li>When payment received and customer unisolated, timeout is recalculated</li>
                <li>RADIUS server reads Session-Timeout and disconnects user after X seconds</li>
            </ol>
            
            <h6 class="mt-3">Troubleshooting</h6>
            <ul>
                <li><strong>Users not in RADIUS:</strong> Check if radiusProvisionUser() is being called during customer creation</li>
                <li><strong>Timeout not updating:</strong> Run manual sync or check cron job logs</li>
                <li><strong>No timeout value:</strong> Check if isolation_date is set correctly in customers table</li>
            </ul>
        </div>
    </div>
</div>

<style>
    code {
        background-color: #f5f5f5;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.9em;
    }
</style>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
