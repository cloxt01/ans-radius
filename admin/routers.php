<?php
/**
 * Router Management - Elegant Dark Minimalis Theme
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Manajemen Router';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Invalid CSRF token');
        redirect('routers.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $data = [
            'name' => $_POST['name'],
            'host' => $_POST['host'],
            'username' => $_POST['username'],
            'password' => $_POST['password'],
            'port' => (int) ($_POST['port'] ?: 8728),
            'description' => $_POST['description'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        if ($data['is_active']) {
            query("UPDATE routers SET is_active = 0");
        }

        if ($action === 'add') {
            $routerCount = fetchOne("SELECT COUNT(*) as total FROM routers")['total'] ?? 0;
            
            if ($routerCount === 0) {
                $data['is_active'] = 1;
            }
            
            insert('routers', $data);
            setFlash('success', 'Router berhasil ditambahkan.');
        } else {
            $id = $_POST['id'];
            update('routers', $data, "id = ?", [$id]);
            setFlash('success', 'Router berhasil diperbarui.');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        query("DELETE FROM routers WHERE id = ?", [$id]);
        setFlash('success', 'Router berhasil dihapus.');
    } elseif ($action === 'switch') {
        $id = $_POST['id'];
        $_SESSION['active_router_id'] = $id;
        setFlash('success', 'Berhasil beralih ke router lain.');
    }

    redirect('routers.php');
}

$routers = getAllRouters();

ob_start();
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo count($routers); ?></h3>
            <p>Total Router</p>
        </div>
        <div class="stat-icon blue">
            <i class="fas fa-server"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <?php 
            $activeRouter = array_filter($routers, fn($r) => $r['is_active'] == 1);
            ?>
            <h3><?php echo count($activeRouter); ?></h3>
            <p>Default Router</p>
        </div>
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <?php 
            $currentRouter = $_SESSION['active_router_id'] ?? null;
            $currentName = '-';
            foreach ($routers as $r) {
                if ($r['id'] == $currentRouter) {
                    $currentName = $r['name'];
                    break;
                }
            }
            ?>
            <h3 style="font-size: 16px;"><?php echo htmlspecialchars($currentName); ?></h3>
            <p>Router Aktif</p>
        </div>
        <div class="stat-icon purple">
            <i class="fas fa-plug"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo count($routers); ?></h3>
            <p>Terhubung</p>
        </div>
        <div class="stat-icon orange">
            <i class="fas fa-network-wired"></i>
        </div>
    </div>
</div>

<!-- Add Router Button -->
<div class="card-actions">
    <button onclick="showAddModal()" class="btn btn-primary">
        <i class="fas fa-plus"></i> Tambah Router
    </button>
</div>

<!-- Routers Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-network-wired"></i> Daftar Router
        </h3>
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="searchRouter" class="form-control" placeholder="Cari router...">
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="data-table" id="routersTable">
            <thead>
                <tr>
                    <th>Router</th>
                    <th>Host</th>
                    <th>Kredensial</th>
                    <th>Port</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($routers)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Belum ada router ditambahkan</p>
                            <small>Klik tombol "Tambah Router" untuk menambahkan</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($routers as $r): 
                        $isActive = ($_SESSION['active_router_id'] ?? '') == $r['id'];
                        $isDefault = $r['is_active'] == 1;
                    ?>
                        <tr class="<?php echo $isActive ? 'active-row' : ''; ?>">
                            <td data-label="Router">
                                <div class="router-info">
                                    <div class="router-avatar <?php echo $isActive ? 'active' : ($isDefault ? 'default' : ''); ?>">
                                        <i class="fas fa-router"></i>
                                    </div>
                                    <div class="router-details">
                                        <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                                        <?php if (!empty($r['description'])): ?>
                                            <small><?php echo htmlspecialchars($r['description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Host">
                                <code class="host-address"><?php echo htmlspecialchars($r['host']); ?></code>
                             </td>
                            <td data-label="Kredensial">
                                <div class="credential-info">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($r['username']); ?></span>
                                    <span class="password-dots">••••••••</span>
                                </div>
                             </td>
                            <td data-label="Port">
                                <span class="port-badge"><?php echo htmlspecialchars($r['port']); ?></span>
                             </td>
                            <td data-label="Status">
                                <div class="status-badges">
                                    <?php if ($isDefault): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-star"></i> Default
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($isActive): ?>
                                        <span class="badge badge-info">
                                            <i class="fas fa-plug"></i> Active
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!$isDefault && !$isActive): ?>
                                        <span class="badge badge-muted">
                                            <i class="fas fa-circle"></i> Standby
                                        </span>
                                    <?php endif; ?>
                                </div>
                             </td>
                            <td data-label="Aksi">
                                <div class="action-buttons">
                                    <?php if (!$isActive): ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="action" value="switch">
                                            <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                            <button type="submit" class="btn-icon switch" title="Switch ke router ini">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button onclick='editRouter(<?php echo json_encode($r); ?>)' class="btn-icon" title="Edit Router">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <form method="POST" class="inline-form" onsubmit="return confirmDelete('<?php echo htmlspecialchars($r['name']); ?>')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <button type="submit" class="btn-icon danger" title="Hapus Router">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                             </td>
                         </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="routerModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-plus-circle"></i> Tambah Router</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="routerForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="routerId">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tag"></i> Nama Router
                    </label>
                    <input type="text" name="name" id="routerName" class="form-control" required
                           placeholder="Contoh: Router Pusat">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-globe"></i> Host (IP / URL)
                    </label>
                    <input type="text" name="host" id="routerHost" class="form-control" required
                           placeholder="192.168.1.1">
                    <small class="form-hint">Alamat IP atau domain router MikroTik</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Username
                        </label>
                        <input type="text" name="username" id="routerUser" class="form-control" 
                               placeholder="admin" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" name="password" id="routerPass" class="form-control" 
                               placeholder="Masukkan password">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-plug"></i> API Port
                        </label>
                        <input type="number" name="port" id="routerPort" class="form-control" value="8728">
                        <small class="form-hint">Default: 8728</small>
                    </div>
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" id="routerActive">
                            <span><i class="fas fa-star"></i> Set sebagai Default</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-align-left"></i> Keterangan
                    </label>
                    <textarea name="description" id="routerDesc" class="form-control" rows="2" 
                              placeholder="Deskripsi router (opsional)"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Router
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Additional styles for routers page */
.card-actions {
    margin-bottom: 20px;
    text-align: right;
}

.router-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.router-avatar {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-fast);
}

.router-avatar i {
    font-size: 20px;
    color: var(--text-secondary);
}

.router-avatar.active {
    background: rgba(88, 166, 255, 0.15);
    box-shadow: 0 0 0 2px rgba(88, 166, 255, 0.2);
}

.router-avatar.active i {
    color: var(--accent-blue);
}

.router-avatar.default {
    background: rgba(63, 185, 80, 0.15);
}

.router-avatar.default i {
    color: var(--accent-green);
}

.router-details {
    display: flex;
    flex-direction: column;
}

.router-details strong {
    font-size: 14px;
}

.router-details small {
    font-size: 11px;
    color: var(--text-muted);
}

.active-row {
    background: rgba(88, 166, 255, 0.05);
}

.host-address {
    font-family: monospace;
    font-size: 13px;
    background: var(--bg-tertiary);
    padding: 4px 8px;
    border-radius: 4px;
    color: var(--accent-blue);
}

.credential-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.credential-info span {
    font-size: 13px;
}

.password-dots {
    font-family: monospace;
    letter-spacing: 2px;
    color: var(--text-muted);
}

.port-badge {
    display: inline-block;
    background: var(--bg-tertiary);
    padding: 4px 10px;
    border-radius: 20px;
    font-family: monospace;
    font-size: 12px;
    font-weight: 600;
}

.status-badges {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
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

.btn-icon.switch:hover {
    color: var(--accent-green);
    border-color: var(--accent-green);
}

.btn-icon.danger:hover {
    color: var(--accent-red);
    border-color: var(--accent-red);
}

.checkbox-group {
    display: flex;
    align-items: flex-end;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 10px 0;
}

.checkbox-label input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent-blue);
}

.checkbox-label span {
    font-size: 13px;
    color: var(--text-secondary);
}

.checkbox-label span i {
    margin-right: 4px;
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
    .card-actions {
        text-align: center;
    }
    
    .search-wrapper {
        width: 100%;
        margin-top: 12px;
    }
    
    .search-wrapper .form-control {
        width: 100%;
    }
    
    .router-info {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .status-badges {
        flex-direction: column;
        gap: 4px;
    }
    
    .action-buttons {
        justify-content: flex-start;
    }
    
    .checkbox-group {
        align-items: flex-start;
        margin-top: 8px;
    }
}
</style>

<script>
function showAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Tambah Router';
    document.getElementById('formAction').value = 'add';
    document.getElementById('routerForm').reset();
    document.getElementById('routerActive').checked = false;
    document.getElementById('routerPort').value = '8728';
    document.getElementById('routerModal').style.display = 'flex';
}

function editRouter(data) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Router';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('routerId').value = data.id;
    document.getElementById('routerName').value = data.name;
    document.getElementById('routerHost').value = data.host;
    document.getElementById('routerUser').value = data.username;
    document.getElementById('routerPass').value = data.password;
    document.getElementById('routerPort').value = data.port;
    document.getElementById('routerActive').checked = data.is_active == 1;
    document.getElementById('routerDesc').value = data.description || '';
    document.getElementById('routerModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('routerModal').style.display = 'none';
}

function confirmDelete(name) {
    return confirm(`Hapus router "${name}"?\n\nTindakan ini tidak dapat dibatalkan!`);
}

// Search functionality
document.getElementById('searchRouter')?.addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#routersTable tbody tr');
    
    rows.forEach(row => {
        if (row.querySelector('.empty-state')) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('routerModal');
    if (event.target == modal) {
        closeModal();
    }
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>