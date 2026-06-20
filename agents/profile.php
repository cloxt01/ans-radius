<?php
/**
 * Agent Portal - Profile Settings
 */

require_once '../includes/auth.php';
requireAgentLogin();

$agentSession = getCurrentAgent();
$agent = fetchOne('SELECT * FROM agents WHERE id = ?', [$agentSession['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone']);

        if (update('agents', ['name' => $name, 'phone' => $phone], 'id = ?', [$agent['id']])) {
            setFlash('success', 'Profil berhasil diperbarui!');
            $_SESSION['agent']['name'] = $name;
        } else {
            setFlash('error', 'Gagal memperbarui profil.');
        }
        redirect('profile.php');
    }

    if ($action === 'update_password') {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];

        if (!password_verify($old_password, $agent['password'])) {
            setFlash('error', 'Password lama tidak sesuai!');
        } elseif (strlen($new_password) < 6) {
            setFlash('error', 'Password baru minimal 6 karakter.');
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            if (update('agents', ['password' => $hashed], 'id = ?', [$agent['id']])) {
                setFlash('success', 'Password berhasil diubah!');
                $_SESSION['agent']['must_change_password'] = false; // Clear warning
            } else {
                setFlash('error', 'Gagal mengubah password.');
            }
        }
        redirect('profile.php');
    }
}

$pageTitle = 'Pengaturan Profil';
ob_start();
?>

    <div style="max-width: 800px; margin: 0 auto;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-edit"></i> Edit Profil</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group">
                    <label class="form-label">Nama Agen / Toko</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($agent['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username Login</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($agent['username']); ?>" readonly style="opacity: 0.5; cursor: not-allowed;">
                    <small style="color:var(--text-muted);">Username tidak dapat diubah.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">No. WhatsApp</label>
                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($agent['phone'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Profil</button>
            </form>
        </div>

        <div class="card" id="password-section">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-lock"></i> Ubah Password</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_password">
                <div class="form-group">
                    <label class="form-label">Password Saat Ini</label>
                    <input type="password" name="old_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-primary" style="background: var(--gradient-warning);"><i class="fas fa-key"></i> Update Password</button>
            </form>
        </div>
    </div>

<?php
$content = ob_get_clean();
require_once '../includes/agent_layout.php';
?>