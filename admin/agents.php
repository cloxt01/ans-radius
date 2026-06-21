<?php
/**
 * Agent Management - Elegant Dark Minimalis Theme
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Manajemen Agen';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Invalid CSRF token');
        redirect('agents.php');
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = sanitize($_POST['username']);
                $phone = sanitize($_POST['phone']);

                if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                    setFlash('error', 'Username minimal 3 karakter, hanya huruf, angka, dan underscore');
                    redirect('agents.php');
                }

                if (strlen($_POST['password']) < 6) {
                    setFlash('error', 'Password minimal 6 karakter');
                    redirect('agents.php');
                }

                $existingUser = fetchOne("SELECT id FROM agents WHERE username = ?", [$username]);
                if ($existingUser) {
                    setFlash('error', 'Username sudah digunakan');
                    redirect('agents.php');
                }

                if (!empty($phone)) {
                    $existingPhone = fetchOne("SELECT id FROM agents WHERE phone = ?", [$phone]);
                    if ($existingPhone) {
                        setFlash('error', 'Nomor HP/Telepon sudah terdaftar pada agen lain');
                        redirect('agents.php');
                    }
                }

                $data = [
                        'name' => sanitize($_POST['name']),
                        'username' => $username,
                        'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                        'phone' => !empty($phone) ? $phone : null,
                        'fee' => !empty($_POST['fee']) ? (float)$_POST['fee'] : 0.00,
                        'lat' => !empty($_POST['lat']) ? (float)$_POST['lat'] : null,
                        'lng' => !empty($_POST['lng']) ? (float)$_POST['lng'] : null,
                        'status' => 'active',
                        'created_at' => date('Y-m-d H:i:s')
                ];

                if (insert('agents', $data)) {
                    setFlash('success', 'Agen berhasil ditambahkan');
                    logActivity('ADD_AGENT', "Name: {$data['name']}");
                } else {
                    setFlash('error', 'Gagal menambahkan agen');
                }
                redirect('agents.php');
                break;

            case 'edit':
                $id = (int)$_POST['id'];
                $phone = sanitize($_POST['phone']);

                if (!empty($phone)) {
                    $existingPhone = fetchOne("SELECT id FROM agents WHERE phone = ? AND id != ?", [$phone, $id]);
                    if ($existingPhone) {
                        setFlash('error', 'Nomor HP/Telepon sudah terdaftar pada agen lain');
                        redirect('agents.php');
                    }
                }

                $data = [
                        'name' => sanitize($_POST['name']),
                        'phone' => !empty($phone) ? $phone : null,
                        'fee' => !empty($_POST['fee']) ? (float)$_POST['fee'] : 0.00,
                        'lat' => !empty($_POST['lat']) ? (float)$_POST['lat'] : null,
                        'lng' => !empty($_POST['lng']) ? (float)$_POST['lng'] : null,
                        'status' => sanitize($_POST['status']),
                    // updated_at otomatis dihandle oleh ON UPDATE CURRENT_TIMESTAMP di MySQL
                ];

                if (!empty($_POST['password'])) {
                    if (strlen($_POST['password']) < 6) {
                        setFlash('error', 'Password minimal 6 karakter');
                        redirect('agents.php');
                    }
                    $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }

                if (update('agents', $data, 'id = ?', [$id])) {
                    setFlash('success', 'Data agen berhasil diperbarui');
                    logActivity('UPDATE_AGENT', "ID: {$id}");
                } else {
                    setFlash('error', 'Gagal memperbarui agen');
                }
                redirect('agents.php');
                break;

            case 'delete':
                $id = (int)$_POST['id'];

                // Asumsi: Periksa apakah agen masih terikat dengan data pelanggan
                // $activeCustomers = fetchOne("SELECT COUNT(*) as total FROM customers WHERE agent_id = ?", [$id]);
                // if ($activeCustomers['total'] > 0) {
                //     setFlash('error', 'Agen ini masih memiliki pelanggan terdaftar. Tidak bisa dihapus.');
                //     redirect('agents.php');
                // }

                if (delete('agents', 'id = ?', [$id])) {
                    setFlash('success', 'Agen berhasil dihapus');
                    logActivity('DELETE_AGENT', "ID: {$id}");
                } else {
                    setFlash('error', 'Gagal menghapus agen');
                }
                redirect('agents.php');
                break;
        }
    }
}

// Fetch all agents
$agents = fetchAll("SELECT * FROM agents ORDER BY name ASC");
$count_c = array_column(fetchAll("SELECT agent_id, COUNT(*) as total FROM customers GROUP BY agent_id"), 'total', 'agent_id');

ob_start();
?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo count($agents); ?></h3>
                <p>Total Agen</p>
            </div>
            <div class="stat-icon blue">
                <i class="fas fa-users"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <?php
                $activeAgents = count(array_filter($agents, fn($a) => $a['status'] === 'active'));
                ?>
                <h3><?php echo $activeAgents; ?></h3>
                <p>Agen Aktif</p>
            </div>
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <?php
                $mappedAgents = count(array_filter($agents, fn($a) => !empty($a['lat']) && !empty($a['lng'])));
                ?>
                <h3><?php echo $mappedAgents; ?></h3>
                <p>Telah Dipetakan (GPS)</p>
            </div>
            <div class="stat-icon orange">
                <i class="fas fa-map-marked-alt"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <?php
                $totalFee = array_sum(array_column($agents, 'fee'));
                ?>
                <h3 style="font-size: 1.2rem;">Rp <?php echo number_format($totalFee, 0, ',', '.'); ?></h3>
                <p>Total Default Fee/Komisi</p>
            </div>
            <div class="stat-icon purple">
                <i class="fas fa-money-bill-wave"></i>
            </div>
        </div>
    </div>

    <!-- Agents Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-store"></i> Daftar Agen / Reseller
            </h3>
            <div class="table-controls">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchAgent" class="form-control" placeholder="Cari agen...">
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Tambah Agen
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="agentsTable">
                <thead>
                <tr>
                    <th>Info Agen</th>
                    <th>Kontak</th>
                    <th>Lokasi GPS</th>
                    <th>Fee/Komisi</th>
                    <th>Total Pelanggan</th>
                    <th>Status & Login</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($agents)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-store-slash"></i>
                            <p>Belum ada data agen</p>
                            <small>Klik tombol "Tambah Agen" untuk menambahkan mitra</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($agents as $a): ?>
                        <tr>
                            <td data-label="Info Agen">
                                <div class="agent-info">
                                    <div class="agent-avatar">
                                        <?php echo strtoupper(substr($a['name'], 0, 2)); ?>
                                    </div>
                                    <div class="agent-details">
                                        <strong><?php echo htmlspecialchars($a['name']); ?></strong>
                                        <small><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($a['username']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Kontak">
                                <?php if (!empty($a['phone'])): ?>
                                    <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $a['phone'])); ?>"
                                       target="_blank" class="contact-link" title="Chat via WhatsApp">
                                        <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($a['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Lokasi GPS">
                                <?php if (!empty($a['lat']) && !empty($a['lng'])): ?>
                                    <a href="https://maps.google.com/?q=<?php echo $a['lat'].','.$a['lng']; ?>"
                                       target="_blank" class="map-link text-info">
                                        <i class="fas fa-map-marker-alt"></i> Lihat Peta
                                    </a>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                        <?php echo $a['lat'] . ', ' . $a['lng']; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-muted">Belum diset</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Fee/Komisi">
                                <strong>Rp <?php echo number_format($a['fee'], 0, ',', '.'); ?></strong>
                            </td>
                            <td data-label="Total Pelanggan">
                                <span class="badge badge-info"><?php echo($count_c[$a['id']]) ?? 0?></span>
                            </td>
                            <td data-label="Status & Login">
                                <div>
                                    <?php if ($a['status'] === 'active'): ?>
                                        <span class="badge badge-success">
                                        <i class="fas fa-circle"></i> Aktif
                                    </span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">
                                        <i class="fas fa-circle"></i> Nonaktif
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="last-login mt-1">
                                    <i class="fas fa-clock"></i>
                                    <?php echo $a['last_login'] ? date('d M Y H:i', strtotime($a['last_login'])) : 'Belum pernah login'; ?>
                                </div>
                            </td>
                            <td data-label="Aksi">
                                <div class="action-buttons">
                                    <button class="btn-icon" onclick='openEditModal(<?php echo json_encode($a); ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <form method="POST" class="inline-form" onsubmit="return confirmDelete('<?php echo htmlspecialchars($a['name']); ?>')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                        <button type="submit" class="btn-icon danger" title="Hapus">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>

                                    <?php if (!empty($a['phone'])): ?>
                                        <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $a['phone'])); ?>"
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
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-store"></i> Tambah Agen Baru</h3>
                <button class="close" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-body">
                    <div class="grid-form">
                        <div class="form-group full-width">
                            <label class="form-label"><i class="fas fa-user"></i> Nama Lengkap / Warung</label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: Agen Pak Budi" minlength="3" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-key"></i> Username</label>
                            <input type="text" name="username" class="form-control" placeholder="username_agen" minlength="3" pattern="[a-zA-Z0-9_]+" autocomplete="username" required>
                            <small class="form-hint">Hanya huruf, angka, underscore</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-lock"></i> Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" minlength="6" autocomplete="new-password" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fab fa-whatsapp"></i> No. HP / WhatsApp</label>
                            <input type="tel" name="phone" class="form-control" placeholder="08xxxxxxxxxx" pattern="[0-9]{10,15}">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-money-bill-wave"></i> Fee / Komisi (Rp)</label>
                            <input type="number" name="fee" class="form-control" placeholder="0" step="0.01" min="0">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-map-marker-alt"></i> Latitude</label>
                            <input type="number" name="lat" class="form-control" placeholder="-6.xxxxxxx" step="any">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-map-marker-alt"></i> Longitude</label>
                            <input type="number" name="lng" class="form-control" placeholder="106.xxxxxxx" step="any">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Agen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Agen</h3>
                <button class="close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="modal-body">
                    <div class="grid-form">
                        <div class="form-group full-width">
                            <label class="form-label"><i class="fas fa-user"></i> Nama Lengkap / Warung</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-key"></i> Username</label>
                            <input type="text" id="edit_username" class="form-control" readonly style="background: var(--bg-tertiary); cursor: not-allowed;">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-toggle-on"></i> Status</label>
                            <select name="status" id="edit_status" class="form-control">
                                <option value="active">✅ Aktif</option>
                                <option value="inactive">⛔ Nonaktif</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fab fa-whatsapp"></i> No. HP / WhatsApp</label>
                            <input type="tel" name="phone" id="edit_phone" class="form-control" placeholder="08xxxxxxxxxx" pattern="[0-9]{10,15}">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-money-bill-wave"></i> Fee / Komisi (Rp)</label>
                            <input type="number" name="fee" id="edit_fee" class="form-control" placeholder="0" step="0.01" min="0">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-map-marker-alt"></i> Latitude</label>
                            <input type="number" name="lat" id="edit_lat" class="form-control" step="any">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-map-marker-alt"></i> Longitude</label>
                            <input type="number" name="lng" id="edit_lng" class="form-control" step="any">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label"><i class="fas fa-lock"></i> Password Baru</label>
                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin diubah" minlength="6" autocomplete="new-password">
                            <small class="form-hint">Minimal 6 karakter</small>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        /* Additional styles for agents page */
        .agent-info { display: flex; align-items: center; gap: 12px; }
        .agent-avatar {
            width: 40px; height: 40px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-blue));
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 14px; color: white;
        }
        .agent-details { display: flex; flex-direction: column; }
        .agent-details strong { font-size: 14px; }
        .agent-details small { font-size: 11px; color: var(--text-muted); }
        .agent-details small i { margin-right: 4px; }

        .contact-link, .map-link {
            display: inline-flex; align-items: center; gap: 6px;
            text-decoration: none; font-size: 13px;
        }
        .contact-link { color: #25D366; }
        .contact-link:hover, .map-link:hover { text-decoration: underline; }

        .last-login { font-size: 11px; color: var(--text-secondary); white-space: nowrap; }
        .mt-1 { margin-top: 5px; }

        .table-controls { display: flex; align-items: center; gap: 12px; }
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 12px; color: var(--text-muted); font-size: 14px; }
        .search-wrapper .form-control { padding-left: 36px; width: 250px; }

        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .inline-form { display: inline; }

        .btn-icon {
            background: var(--bg-tertiary); border: 1px solid var(--border-light);
            color: var(--text-secondary); cursor: pointer; padding: 6px 10px;
            border-radius: var(--radius-sm); transition: all var(--transition-fast); font-size: 12px;
        }
        .btn-icon:hover { background: var(--bg-secondary); border-color: var(--border-color); color: var(--accent-blue); }
        .btn-icon.danger:hover { color: var(--accent-red); border-color: var(--accent-red); }
        .btn-icon.whatsapp:hover { color: #25D366; border-color: #25D366; }

        .grid-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .full-width {
            grid-column: span 2;
        }

        .text-muted { color: var(--text-muted); }
        .empty-state { text-align: center; padding: 60px 20px !important; color: var(--text-muted); }
        .empty-state i { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }
        .empty-state p { margin: 0; font-size: 14px; }
        .empty-state small { font-size: 12px; }

        @media (max-width: 768px) {
            .grid-form { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            .table-controls { flex-direction: column; width: 100%; }
            .search-wrapper, .search-wrapper .form-control { width: 100%; }
            .agent-info { flex-direction: column; align-items: flex-start; }
            .action-buttons { justify-content: flex-start; }
            .last-login { white-space: normal; }
        }
    </style>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(agent) {
            document.getElementById('edit_id').value = agent.id;
            document.getElementById('edit_name').value = agent.name;
            document.getElementById('edit_username').value = agent.username;
            document.getElementById('edit_phone').value = agent.phone || '';
            document.getElementById('edit_status').value = agent.status;
            document.getElementById('edit_fee').value = agent.fee || '';
            document.getElementById('edit_lat').value = agent.lat || '';
            document.getElementById('edit_lng').value = agent.lng || '';

            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmDelete(name) {
            return confirm(`Hapus agen "${name}"?\n\nTindakan ini tidak dapat dibatalkan!`);
        }

        // Search functionality
        document.getElementById('searchAgent')?.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#agentsTable tbody tr');

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