<?php
/**
 * PPPoE Profile Management
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'PPPoE Profiles';

function pppoeProfileBuildPayloadFromPost()
{
    $name = sanitize($_POST['name'] ?? '');
    $rate = sanitize($_POST['rate_limit'] ?? '');
    $local = sanitize($_POST['local_address'] ?? '');
    $pool = sanitize($_POST['remote_pool'] ?? 'none');
    $dns = sanitize($_POST['dns_server'] ?? '');

    $payload = [
        'name' => $name,
    ];

    if ($rate !== '') {
        $payload['rate-limit'] = $rate;
    }
    if ($local !== '') {
        $payload['local-address'] = $local;
    }
    if ($pool !== '') {
        $payload['remote-address'] = $pool;
    }
    if ($dns !== '') {
        $payload['dns-server'] = $dns;
    }

    return $payload;
}

function pppoeProfileResolveIdFromPost()
{
    $id = sanitize($_POST['id'] ?? '');
    if ($id !== '') {
        return $id;
    }

    return sanitize($_POST['name'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
        case 'edit':
            $data = pppoeProfileBuildPayloadFromPost();
            $name = $data['name'] ?? '';

            if ($name === '') {
                setFlash('error', 'Nama profile wajib diisi.');
                redirect('pppoe-profile.php');
            }

            if ($action === 'add') {
                $ok = mikrotikAddPppoeProfile($data);
                setFlash(
                    $ok ? 'success' : 'error',
                    $ok
                        ? "Profile {$name} berhasil ditambahkan."
                        : 'Gagal menambahkan profile (pastikan mikrotik terhubung dan konfigurasi benar).'
                );
                redirect('pppoe-profile.php');
            }

            $id = pppoeProfileResolveIdFromPost();
            $ok = ($id !== '') ? mikrotikUpdatePppoeProfile($id, $data) : false;
            setFlash(
                $ok ? 'success' : 'error',
                $ok
                    ? "Profile {$name} berhasil diperbarui."
                    : 'Gagal memperbarui profile (pastikan mikrotik terhubung dan konfigurasi benar).'
            );
            redirect('pppoe-profile.php');
            break;

        case 'delete':
            $id = pppoeProfileResolveIdFromPost();
            $ok = ($id !== '') ? mikrotikDeletePppoeProfile($id) : false;
            setFlash($ok ? 'success' : 'error', $ok ? 'Profile berhasil dihapus.' : 'Gagal menghapus profile.');
            redirect('pppoe-profile.php');
            break;

        default:
            if ($action !== '') {
                setFlash('error', 'Aksi tidak dikenali.');
                redirect('pppoe-profile.php');
            }
            break;
    }
}

$profiles = function_exists('pppoeGetProfiles') ? pppoeGetProfiles() : mikrotikGetProfiles();
$addressPools = mikrotikGetAddressPools();
$isMikrotikConnected = mikrotikConnect();

ob_start();
?>

<!-- Display status connection mikrotik -->
<?php if (!$isMikrotikConnected): ?>
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


<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle"></i> Tambah/Edit Profile</h3>
    </div>
    <form method="POST" id="profileForm">
        <input type="hidden" name="action" value="add" id="formAction">
        <input type="hidden" name="id" id="profileId">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" id="pName" class="form-control" placeholder="Voucher 2000 (Basic)" required>
            </div>
            <div class="form-group">
                <label class="form-label">Rate Limit</label>
                <input type="text" name="rate_limit" id="pRate" class="form-control" placeholder="10M/10M">
            </div>
            <div class="form-group">
                <label class="form-label">Local Address (optional)</label>
                <input type="text" name="local_address" id="pLocal" class="form-control" placeholder="10.10.10.1">
            </div>
            <div class="form-group">
                <label class="form-label">Remote Address Pool (optional)</label>
                <select name="remote_pool" id="pPool" class="form-control">
                    <option value="none">none</option>
                    <?php foreach ($addressPools as $pool): ?>
                        <option value="<?php echo htmlspecialchars($pool['name']); ?>">
                            <?php echo htmlspecialchars($pool['name'] . ' - ' . $pool['ranges']); ?>
                        </option>
                        
                    <?php endforeach; ?>
                
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">DNS Server (optional)</label>
                <input type="text" name="dns_server" id="pDns" class="form-control" placeholder="8.8.8.8">
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 10px;">
            <button type="submit" class="btn btn-primary" <?php echo !$isMikrotikConnected ? 'style="cursor: not-allowed;" disabled' : ''; ?>>
                <i class="fas fa-save"></i> Simpan Profile
            </button>
            <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list"></i> Daftar PPPoE Profile</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Rate Limit</th>
                    <th>Local</th>
                    <th>Remote Pool</th>
                    <th>DNS</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                
                <?php
                if (empty($profiles)) {
                    echo '<tr><td colspan="6" style="text-align: center; color: var(--text-muted);"><i class="fas fa-network-wired" style="font-size: 2rem; margin: 12px 0; display: block;"></i> Belum ada profile PPPoE</td></tr>';
                } else {
                foreach ($profiles as $p): ?>
                    <tr>
                        <td data-label="Name"><strong><?php echo htmlspecialchars($p['name'] ?? ''); ?></strong></td>
                        <td data-label="Rate Limit"><?php echo htmlspecialchars($p['rate-limit'] ?? ''); ?></td>
                        <td data-label="Local"><?php echo htmlspecialchars($p['local-address'] ?? ''); ?></td>
                        <td data-label="Remote Pool"><?php echo htmlspecialchars($p['remote-address'] ?? ''); ?></td>
                        <td data-label="DNS"><?php echo htmlspecialchars($p['dns-server'] ?? ''); ?></td>
                        <td data-label="Aksi">
                            <div style="display: flex; gap: 5px;">
                                <button onclick='editProfile(<?php echo json_encode($p); ?>)'
                                    class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus profile ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($p['.id'] ?? ($p['name'] ?? '')); ?>">
                                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($p['name'] ?? ''); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" <?php echo !$isMikrotikConnected ? 'style="cursor: not-allowed;" disabled' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function editProfile(p) {
        document.getElementById('formAction').value = 'edit';
        document.getElementById('profileId').value = p['.id'] || '';
        document.getElementById('pName').value = p['name'] || '';
        document.getElementById('pRate').value = p['rate-limit'] || '';
        document.getElementById('pLocal').value = p['local-address'] || '';
        document.getElementById('pPool').value = p['remote-address'] || 'none';
        document.getElementById('pDns').value = p['dns-server'] || '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('profileForm').reset();
        document.getElementById('formAction').value = 'add';
        document.getElementById('profileId').value = '';
    }
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';

