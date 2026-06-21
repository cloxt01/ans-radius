<?php
/**
 * Technician Management - Elegant Dark Minimalis Theme
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Manajemen Teknisi';
$workdir = 'admin/technicians.php';
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        AppLog('TECHNICIAN_CSRF_FAILED', $workdir, "CSRF token tidak valid", json_encode(['ip' => $_SERVER['REMOTE_ADDR']]));
        setFlash('error', 'Invalid CSRF token');
        redirect('technicians.php');
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        AppLog('TECHNICIAN_ACTION_RECEIVED', $workdir, "Menerima aksi teknisi", json_encode(['action' => $action]));

        switch ($action) {
            case 'add':
                $username = sanitize($_POST['username']);
                $password = $_POST['password'] ?? '';
                $name = sanitize($_POST['name']);
                $phone = sanitize($_POST['phone']);
                $logData = ['username' => $username, 'name' => $name, 'phone' => $phone];
                AppLog('TECHNICIAN_ADD_ATTEMPT', $workdir, "Mencoba menambahkan teknisi", json_encode($logData));

                if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                    AppLog('TECHNICIAN_ADD_FAILED', $workdir, "Username tidak valid", json_encode($logData));
                    setFlash('error', 'Username minimal 3 karakter, hanya huruf, angka, dan underscore');
                    redirect('technicians.php');
                }

                if (strlen($password) < 6) {
                    AppLog('TECHNICIAN_ADD_FAILED', $workdir, "Password terlalu pendek", json_encode($logData));
                    setFlash('error', 'Password minimal 6 karakter');
                    redirect('technicians.php');
                }

                $existing = fetchOne("SELECT id FROM technician_users WHERE username = ?", [$username]);
                if ($existing) {
                    AppLog('TECHNICIAN_ADD_FAILED', $workdir, "Username sudah digunakan", json_encode($logData));
                    setFlash('error', 'Username sudah digunakan');
                    redirect('technicians.php');
                }

                $data = [
                        'name' => $name,
                        'username' => $username,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'phone' => $phone,
                        'status' => 'active',
                        'created_at' => date('Y-m-d H:i:s')
                ];

                if (insert('technician_users', $data)) {
                    AppLog('TECHNICIAN_ADD_SUCCESS', $workdir, "Teknisi berhasil ditambahkan", json_encode($logData));
                    setFlash('success', 'Teknisi berhasil ditambahkan');
                    logActivity('ADD_TECHNICIAN', "Name: {$data['name']}");
                } else {
                    AppLog('TECHNICIAN_ADD_FAILED', $workdir, "Gagal menyimpan teknisi ke DB", json_encode($logData));
                    setFlash('error', 'Gagal menambahkan teknisi');
                }
                redirect('technicians.php');
                break;

            case 'edit':
                $id = (int)$_POST['id'];
                $data = [
                        'name' => sanitize($_POST['name']),
                        'phone' => sanitize($_POST['phone']),
                        'status' => sanitize($_POST['status']),
                        'updated_at' => date('Y-m-d H:i:s')
                ];
                $password = $_POST['password'] ?? '';
                $logData = ['id' => $id, 'name' => $data['name'], 'status' => $data['status']];
                AppLog('TECHNICIAN_EDIT_ATTEMPT', $workdir, "Mencoba mengupdate teknisi", json_encode($logData));

                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        AppLog('TECHNICIAN_EDIT_FAILED', $workdir, "Password baru terlalu pendek", json_encode($logData));
                        setFlash('error', 'Password minimal 6 karakter');
                        redirect('technicians.php');
                    }
                    $data['password'] = password_hash($password, PASSWORD_DEFAULT);
                }

                if (update('technician_users', $data, 'id = ?', [$id])) {
                    AppLog('TECHNICIAN_EDIT_SUCCESS', $workdir, "Data teknisi berhasil diperbarui", json_encode($logData));
                    setFlash('success', 'Data teknisi berhasil diperbarui');
                    logActivity('UPDATE_TECHNICIAN', "ID: {$id}");
                } else {
                    AppLog('TECHNICIAN_EDIT_FAILED', $workdir, "Gagal mengupdate teknisi", json_encode($logData));
                    setFlash('error', 'Gagal memperbarui teknisi');
                }
                redirect('technicians.php');
                break;

            case 'delete':
                $id = (int)$_POST['id'];
                AppLog('TECHNICIAN_DELETE_ATTEMPT', $workdir, "Mencoba menghapus teknisi", json_encode(['id' => $id]));

                $activeTickets = fetchOne("SELECT COUNT(*) as total FROM trouble_tickets WHERE technician_id = ? AND status != 'resolved'", [$id]);
                if ($activeTickets['total'] > 0) {
                    AppLog('TECHNICIAN_DELETE_FAILED', $workdir, "Teknisi masih memiliki tugas aktif", json_encode(['id' => $id, 'active_tickets' => $activeTickets['total']]));
                    setFlash('error', 'Teknisi ini masih memiliki tugas aktif. Tidak bisa dihapus.');
                    redirect('technicians.php');
                }

                if (delete('technician_users', 'id = ?', [$id])) {
                    AppLog('TECHNICIAN_DELETE_SUCCESS', $workdir, "Teknisi berhasil dihapus", json_encode(['id' => $id]));
                    setFlash('success', 'Teknisi berhasil dihapus');
                    logActivity('DELETE_TECHNICIAN', "ID: {$id}");
                } else {
                    AppLog('TECHNICIAN_DELETE_FAILED', $workdir, "Gagal menghapus teknisi", json_encode(['id' => $id]));
                    setFlash('error', 'Gagal menghapus teknisi');
                }
                redirect('technicians.php');
                break;

            default:
                AppLog('TECHNICIAN_UNKNOWN_ACTION', $workdir, "Aksi tidak dikenali", json_encode(['action' => $action]));
                setFlash('error', 'Aksi tidak dikenali.');
                redirect('technicians.php');
                break;
        }
    } else {
        AppLog('TECHNICIAN_NO_ACTION', $workdir, "Tidak ada aksi yang dikirim", json_encode([]));
        // optional redirect or set flash? original does not handle, but we can leave as is.
        redirect('technicians.php');
    }
}

$technicians = fetchAll("
    SELECT t.*, 
    (SELECT COUNT(*) FROM trouble_tickets WHERE technician_id = t.id AND status != 'resolved') as active_tickets,
    (SELECT COUNT(*) FROM customers WHERE installed_by = t.id AND status = 'registered') as pending_installs
    FROM technician_users t 
    ORDER BY t.name ASC
");

ob_start();
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo count($technicians); ?></h3>
            <p>Total Teknisi</p>
        </div>
        <div class="stat-icon blue">
            <i class="fas fa-users"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <?php 
            $activeTechs = count(array_filter($technicians, fn($t) => $t['status'] === 'active'));
            ?>
            <h3><?php echo $activeTechs; ?></h3>
            <p>Aktif</p>
        </div>
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <?php 
            $totalTickets = array_sum(array_column($technicians, 'active_tickets'));
            ?>
            <h3><?php echo $totalTickets; ?></h3>
            <p>Total Tiket Aktif</p>
        </div>
        <div class="stat-icon orange">
            <i class="fas fa-ticket-alt"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <?php 
            $totalInstalls = array_sum(array_column($technicians, 'pending_installs'));
            ?>
            <h3><?php echo $totalInstalls; ?></h3>
            <p>Total PSB Pending</p>
        </div>
        <div class="stat-icon purple">
            <i class="fas fa-wrench"></i>
        </div>
    </div>
</div>

<!-- Technicians Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-tools"></i> Daftar Teknisi
        </h3>
        <div class="table-controls">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="searchTechnician" class="form-control" placeholder="Cari teknisi...">
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Tambah Teknisi
            </button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="data-table" id="techniciansTable">
            <thead>
                <tr>
                    <th>Teknisi</th>
                    <th>Kontak</th>
                    <th>Status</th>
                    <th>Beban Kerja</th>
                    <th>Terakhir Login</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($technicians)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Belum ada data teknisi</p>
                            <small>Klik tombol "Tambah Teknisi" untuk menambahkan</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($technicians as $t): ?>
                    <tr>
                        <td data-label="Teknisi">
                            <div class="technician-info">
                                <div class="tech-avatar">
                                    <?php echo strtoupper(substr($t['name'], 0, 2)); ?>
                                </div>
                                <div class="tech-details">
                                    <strong><?php echo htmlspecialchars($t['name']); ?></strong>
                                    <small><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($t['username']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Kontak">
                            <?php if (!empty($t['phone'])): ?>
                                <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $t['phone'])); ?>" 
                                   target="_blank" class="contact-link" title="Chat via WhatsApp">
                                    <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($t['phone']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <?php if ($t['status'] === 'active'): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-circle"></i> Aktif
                                </span>
                            <?php else: ?>
                                <span class="badge badge-muted">
                                    <i class="fas fa-circle"></i> Nonaktif
                                </span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Beban Kerja">
                            <div class="workload-badges">
                                <span class="badge badge-warning" title="Tiket Gangguan Aktif">
                                    <i class="fas fa-ticket-alt"></i> <?php echo $t['active_tickets']; ?> Tiket
                                </span>
                                <span class="badge badge-info" title="Pending Instalasi">
                                    <i class="fas fa-wrench"></i> <?php echo $t['pending_installs']; ?> PSB
                                </span>
                            </div>
                        </td>
                        <td data-label="Login Terakhir">
                            <span class="last-login">
                                <i class="fas fa-clock"></i> 
                                <?php echo $t['last_login'] ? formatDate($t['last_login']) : 'Belum pernah'; ?>
                            </span>
                        </td>
                        <td data-label="Aksi">
                            <div class="action-buttons">
                                <button class="btn-icon" onclick='openEditModal(<?php echo json_encode($t); ?>)' title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($t['active_tickets'] == 0 && $t['pending_installs'] == 0): ?>
                                    <form method="POST" class="inline-form" onsubmit="return confirmDelete('<?php echo htmlspecialchars($t['name']); ?>')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="btn-icon danger" title="Hapus">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn-icon disabled" disabled title="Masih ada tugas aktif (<?php echo $t['active_tickets']; ?> tiket, <?php echo $t['pending_installs']; ?> PSB)">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (!empty($t['phone'])): ?>
                                    <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $t['phone'])); ?>" 
                                       target="_blank" class="btn-icon whatsapp" title="Chat WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Tambah Teknisi Baru</h3>
            <button class="close" onclick="closeAddModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Nama Lengkap
                    </label>
                    <input type="text" name="name" class="form-control" 
                           placeholder="Contoh: Sarmin" minlength="3" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fab fa-whatsapp"></i> No. HP / WhatsApp
                    </label>
                    <input type="tel" name="phone" class="form-control" 
                           placeholder="08xxxxxxxxxx" pattern="[0-9]{10,15}" 
                           title="Masukkan nomor HP 10-15 digit">
                    <small class="form-hint">Nomor untuk notifikasi tugas</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-key"></i> Username
                    </label>
                    <input type="text" name="username" class="form-control" 
                           placeholder="username_teknisi" minlength="3" 
                           pattern="[a-zA-Z0-9_]+" autocomplete="username" required>
                    <small class="form-hint">Hanya huruf, angka, dan underscore</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" name="password" class="form-control" 
                           placeholder="Minimal 6 karakter" minlength="6" autocomplete="new-password" required>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Teknisi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit Teknisi</h3>
            <button class="close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Nama Lengkap
                    </label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-key"></i> Username
                    </label>
                    <input type="text" id="edit_username" class="form-control" readonly 
                           style="background: var(--bg-tertiary); cursor: not-allowed;">
                    <small class="form-hint">Username tidak dapat diubah</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fab fa-whatsapp"></i> No. HP / WhatsApp
                    </label>
                    <input type="tel" name="phone" id="edit_phone" class="form-control" 
                           placeholder="08xxxxxxxxxx" pattern="[0-9]{10,15}">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-toggle-on"></i> Status
                    </label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="active">✅ Aktif</option>
                        <option value="inactive">⛔ Nonaktif</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Password Baru
                    </label>
                    <input type="password" name="password" class="form-control" 
                           placeholder="Kosongkan jika tidak diubah" minlength="6" 
                           autocomplete="new-password">
                    <small class="form-hint">Minimal 6 karakter</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Additional styles for technicians page */
.technician-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.tech-avatar {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    color: white;
}

.tech-details {
    display: flex;
    flex-direction: column;
}

.tech-details strong {
    font-size: 14px;
}

.tech-details small {
    font-size: 11px;
    color: var(--text-muted);
}

.tech-details small i {
    margin-right: 4px;
}

.contact-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #25D366;
    text-decoration: none;
    font-size: 13px;
}

.contact-link:hover {
    text-decoration: underline;
}

.workload-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.last-login {
    font-size: 12px;
    color: var(--text-secondary);
    white-space: nowrap;
}

.last-login i {
    margin-right: 4px;
}

.table-controls {
    display: flex;
    align-items: center;
    gap: 12px;
}

.search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-wrapper i {
    position: absolute;
    left: 12px;
    color: var(--text-muted);
    font-size: 14px;
}

.search-wrapper .form-control {
    padding-left: 36px;
    width: 250px;
}

.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.inline-form {
    display: inline;
}

.btn-icon {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-light);
    color: var(--text-secondary);
    cursor: pointer;
    padding: 6px 10px;
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
    font-size: 12px;
}

.btn-icon:hover {
    background: var(--bg-secondary);
    border-color: var(--border-color);
    color: var(--accent-blue);
}

.btn-icon.danger:hover {
    color: var(--accent-red);
    border-color: var(--accent-red);
}

.btn-icon.whatsapp:hover {
    color: #25D366;
    border-color: #25D366;
}

.btn-icon.disabled,
.btn-icon:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-icon.disabled:hover {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
}

.text-muted {
    color: var(--text-muted);
}

.empty-state {
    text-align: center;
    padding: 60px 20px !important;
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

@media (max-width: 768px) {
    .table-controls {
        flex-direction: column;
        width: 100%;
    }
    
    .search-wrapper {
        width: 100%;
    }
    
    .search-wrapper .form-control {
        width: 100%;
    }
    
    .technician-info {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .workload-badges {
        flex-direction: column;
        gap: 4px;
    }
    
    .action-buttons {
        justify-content: flex-start;
    }
    
    .last-login {
        white-space: normal;
    }
}
</style>

<script>
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function openEditModal(tech) {
    document.getElementById('edit_id').value = tech.id;
    document.getElementById('edit_name').value = tech.name;
    document.getElementById('edit_username').value = tech.username;
    document.getElementById('edit_phone').value = tech.phone || '';
    document.getElementById('edit_status').value = tech.status;
    
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function confirmDelete(name) {
    return confirm(`Hapus teknisi "${name}"?\n\nTindakan ini tidak dapat dibatalkan!`);
}

// Search functionality
document.getElementById('searchTechnician')?.addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#techniciansTable tbody tr');
    
    rows.forEach(row => {
        if (row.querySelector('.empty-state')) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Close modal on outside click
window.onclick = function(event) {
    if (event.target.classList && event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(function(modal) {
            modal.style.display = 'none';
        });
    }
});
</script>

<?php 
$content = ob_get_clean();
require_once '../includes/layout.php';
?>