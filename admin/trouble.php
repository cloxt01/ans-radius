<?php
/**
 * Trouble Tickets Management - Elegant Dark Minimalis Theme
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Laporan Gangguan';
$workdir = 'admin/trouble.php';
// Get technicians
$technicians = fetchAll("SELECT * FROM technician_users WHERE status = 'active' ORDER BY name ASC");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        AppLog('TROUBLE_CSRF_FAILED', $workdir, "CSRF token tidak valid", json_encode(['ip' => $_SERVER['REMOTE_ADDR']]));
        setFlash('error', 'Invalid CSRF token');
        redirect('trouble.php');
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        AppLog('TROUBLE_ACTION_RECEIVED', $workdir, "Menerima aksi trouble ticket", json_encode(['action' => $action]));

        switch ($action) {
            case 'add':
                $customerId = (int)$_POST['customer_id'];
                $description = sanitize($_POST['description']);
                $priority = sanitize($_POST['priority']);
                $technicianId = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
                $logData = ['customer_id' => $customerId, 'priority' => $priority, 'technician_id' => $technicianId];
                AppLog('TROUBLE_ADD_ATTEMPT', $workdir, "Mencoba menambahkan tiket gangguan", json_encode($logData));

                $ticketData = [
                        'customer_id' => $customerId,
                        'description' => $description,
                        'priority' => $priority,
                        'status' => 'pending',
                        'technician_id' => $technicianId,
                        'created_at' => date('Y-m-d H:i:s')
                ];

                if (insert('trouble_tickets', $ticketData)) {
                    $pdo = getDB();
                    $ticketId = $pdo->lastInsertId();
                    AppLog('TROUBLE_ADD_SUCCESS', $workdir, "Tiket gangguan berhasil ditambahkan", json_encode(['ticket_id' => $ticketId] + $logData));

                    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
                    if ($customer && $customer['phone']) {
                        $message = "Halo {$customer['name']},\n\nLaporan gangguan Anda telah kami terima:\n\nTicket ID: #{$ticketId}\nMasalah: " . substr($description, 0, 100) . "...\n\nTim kami akan segera menindaklanjuti. Terima kasih.";
                        if (function_exists('sendWhatsApp')) {
                            sendWhatsApp($customer['phone'], $message);
                        } elseif (function_exists('sendWhatsAppMessage')) {
                            sendWhatsAppMessage($customer['phone'], $message);
                        } else {
                            require_once '../includes/whatsapp.php';
                            sendWhatsAppMessage($customer['phone'], $message);
                        }
                    }

                    if ($technicianId) {
                        $tech = fetchOne("SELECT phone, name FROM technician_users WHERE id = ?", [$technicianId]);
                        if ($tech && !empty($tech['phone'])) {
                            require_once '../includes/whatsapp.php';
                            $msg = "🚨 *TUGAS GANGGUAN BARU*\n\n";
                            $msg .= "Ticket: #{$ticketId}\n";
                            $msg .= "Pelanggan: " . ($customer['name'] ?? 'N/A') . "\n";
                            $msg .= "Masalah: {$description}\n";
                            $msg .= "Prioritas: " . strtoupper($priority) . "\n\n";
                            $msg .= "Mohon segera dicek.";
                            sendWhatsAppMessage($tech['phone'], $msg);
                        }
                    }

                    setFlash('success', 'Laporan gangguan berhasil ditambahkan');
                    logActivity('ADD_TROUBLE_TICKET', "Ticket #{$ticketId}");
                } else {
                    AppLog('TROUBLE_ADD_FAILED', $workdir, "Gagal menambahkan tiket gangguan ke DB", json_encode($logData));
                    setFlash('error', 'Gagal menambahkan laporan');
                }
                redirect('trouble.php');
                break;

            case 'update_status':
                $ticketId = (int)$_POST['ticket_id'];
                $status = sanitize($_POST['status']);
                $notes = sanitize($_POST['notes'] ?? '');
                $technicianId = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
                $logData = ['ticket_id' => $ticketId, 'status' => $status, 'technician_id' => $technicianId];
                AppLog('TROUBLE_UPDATE_STATUS_ATTEMPT', $workdir, "Mencoba mengupdate status tiket", json_encode($logData));

                $ticket = fetchOne("SELECT * FROM trouble_tickets WHERE id = ?", [$ticketId]);

                if ($ticket) {
                    $updateData = [
                            'status' => $status,
                            'notes' => $notes,
                            'technician_id' => $technicianId,
                            'updated_at' => date('Y-m-d H:i:s')
                    ];

                    if ($status === 'resolved') {
                        $updateData['resolved_at'] = date('Y-m-d H:i:s');
                    }

                    update('trouble_tickets', $updateData, 'id = ?', [$ticketId]);
                    AppLog('TROUBLE_UPDATE_STATUS_SUCCESS', $workdir, "Status tiket berhasil diperbarui", json_encode($logData));

                    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$ticket['customer_id']]);
                    if ($customer && $customer['phone']) {
                        $statusText = [
                                'pending' => 'Menunggu',
                                'in_progress' => 'Sedang Diproses',
                                'resolved' => 'Selesai'
                        ];

                        $message = "Halo {$customer['name']},\n\nStatus laporan gangguan Anda (Ticket #{$ticketId}) telah diperbarui:\n\nStatus: {$statusText[$status]}\n";
                        if ($notes) {
                            $message .= "Catatan: {$notes}\n";
                        }
                        if ($status === 'resolved') {
                            $message .= "\nTerima kasih telah menggunakan layanan kami.";
                        }

                        if (function_exists('sendWhatsApp')) {
                            sendWhatsApp($customer['phone'], $message);
                        } elseif (function_exists('sendWhatsAppMessage')) {
                            sendWhatsAppMessage($customer['phone'], $message);
                        } else {
                            require_once '../includes/whatsapp.php';
                            sendWhatsAppMessage($customer['phone'], $message);
                        }
                    }

                    setFlash('success', 'Status tiket berhasil diperbarui');
                    logActivity('UPDATE_TROUBLE_TICKET', "Ticket #{$ticketId} - Status: {$status}");
                } else {
                    AppLog('TROUBLE_UPDATE_STATUS_FAILED', $workdir, "Tiket tidak ditemukan", json_encode($logData));
                    setFlash('error', 'Tiket tidak ditemukan');
                }
                redirect('trouble.php');
                break;

            case 'delete':
                $ticketId = (int)$_POST['ticket_id'];
                AppLog('TROUBLE_DELETE_ATTEMPT', $workdir, "Mencoba menghapus tiket", json_encode(['ticket_id' => $ticketId]));

                delete('trouble_tickets', 'id = ?', [$ticketId]);
                AppLog('TROUBLE_DELETE_SUCCESS', $workdir, "Tiket berhasil dihapus", json_encode(['ticket_id' => $ticketId]));
                setFlash('success', 'Tiket berhasil dihapus');
                logActivity('DELETE_TROUBLE_TICKET', "Ticket #{$ticketId}");
                redirect('trouble.php');
                break;

            default:
                AppLog('TROUBLE_UNKNOWN_ACTION', $workdir, "Aksi tidak dikenali", json_encode(['action' => $action]));
                setFlash('error', 'Aksi tidak dikenali.');
                redirect('trouble.php');
                break;
        }
    } else {
        AppLog('TROUBLE_NO_ACTION', $workdir, "Tidak ada aksi yang dikirim", json_encode([]));
        // optional redirect? original does not redirect; we can leave as is.
        redirect('trouble.php');
    }
}

$tickets = fetchAll("
    SELECT t.*, c.name as customer_name, c.phone as customer_phone, c.pppoe_username,
           p.name as package_name
    FROM trouble_tickets t 
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN packages p ON c.package_id = p.id
    ORDER BY 
        CASE t.priority 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
        END,
        t.created_at DESC
");

$customers = fetchAll("SELECT id, name, pppoe_username FROM customers WHERE status = 'active' ORDER BY name");

$totalTickets = count($tickets);
$pendingTickets = count(array_filter($tickets, fn($t) => $t['status'] === 'pending'));
$inProgressTickets = count(array_filter($tickets, fn($t) => $t['status'] === 'in_progress'));
$resolvedTickets = count(array_filter($tickets, fn($t) => $t['status'] === 'resolved'));

ob_start();
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $totalTickets; ?></h3>
            <p>Total Laporan</p>
        </div>
        <div class="stat-icon blue">
            <i class="fas fa-ticket-alt"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $pendingTickets; ?></h3>
            <p>Pending</p>
        </div>
        <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $inProgressTickets; ?></h3>
            <p>In Progress</p>
        </div>
        <div class="stat-icon purple">
            <i class="fas fa-tools"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $resolvedTickets; ?></h3>
            <p>Selesai</p>
        </div>
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
    </div>
</div>

<!-- Add Ticket Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-plus-circle"></i> Tambah Laporan Gangguan
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Pelanggan
                    </label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">-- Pilih Pelanggan --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['pppoe_username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-flag"></i> Prioritas
                    </label>
                    <select name="priority" class="form-control" required>
                        <option value="low">Low - Tidak Urgent</option>
                        <option value="medium" selected>Medium - Normal</option>
                        <option value="high">High - Urgent</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-comment-alt"></i> Deskripsi Masalah
                </label>
                <textarea name="description" class="form-control" rows="3" required 
                          placeholder="Jelaskan masalah yang dialami pelanggan secara detail..."></textarea>
                <small class="form-hint">Semakin detail deskripsi, semakin cepat penanganan</small>
            </div>
            
            <?php if (!empty($technicians)): ?>
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-user-cog"></i> Assign Teknisi (Opsional)
                </label>
                <select name="technician_id" class="form-control">
                    <option value="">-- Belum Ditugaskan --</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?php echo $tech['id']; ?>">
                            <?php echo htmlspecialchars($tech['name']); ?> (<?php echo htmlspecialchars($tech['phone'] ?? '-'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Laporan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tickets Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-exclamation-triangle"></i> Daftar Laporan Gangguan
        </h3>
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="searchTicket" class="form-control" placeholder="Cari laporan...">
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="data-table" id="ticketTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Pelanggan</th>
                    <th>Masalah</th>
                    <th>Status</th>
                    <th>Prioritas</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>Tidak ada laporan gangguan</p>
                            <small>Semua sistem berjalan normal</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <?php
                        $statusClass = 'warning';
                        $statusIcon = 'clock';
                        $statusText = 'Pending';
                        if ($ticket['status'] === 'resolved') {
                            $statusClass = 'success';
                            $statusIcon = 'check-circle';
                            $statusText = 'Selesai';
                        } elseif ($ticket['status'] === 'in_progress') {
                            $statusClass = 'info';
                            $statusIcon = 'tools';
                            $statusText = 'Proses';
                        }
                        
                        $priorityClass = 'info';
                        $priorityIcon = 'info-circle';
                        if ($ticket['priority'] === 'high') {
                            $priorityClass = 'danger';
                            $priorityIcon = 'exclamation-triangle';
                        } elseif ($ticket['priority'] === 'medium') {
                            $priorityClass = 'warning';
                            $priorityIcon = 'exclamation-circle';
                        }
                        ?>
                        <tr>
                            <td data-label="ID">
                                <span class="ticket-id">#<?php echo $ticket['id']; ?></span>
                            </td>
                            <td data-label="Pelanggan">
                                <div class="customer-info">
                                    <strong><?php echo htmlspecialchars($ticket['customer_name'] ?? 'N/A'); ?></strong>
                                    <small><?php echo htmlspecialchars($ticket['pppoe_username'] ?? ''); ?></small>
                                </div>
                            </td>
                            <td data-label="Masalah">
                                <div class="issue-preview">
                                    <?php echo htmlspecialchars(substr($ticket['description'], 0, 60)); ?>
                                    <?php if (strlen($ticket['description']) > 60): ?>...<?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Status">
                                <span class="badge badge-<?php echo $statusClass; ?>">
                                    <i class="fas fa-<?php echo $statusIcon; ?>"></i> <?php echo $statusText; ?>
                                </span>
                             </td>
                            <td data-label="Prioritas">
                                <span class="badge badge-<?php echo $priorityClass; ?> priority-<?php echo $ticket['priority']; ?>">
                                    <i class="fas fa-<?php echo $priorityIcon; ?>"></i> 
                                    <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                             </td>
                            <td data-label="Tanggal">
                                <span class="date-info">
                                    <i class="fas fa-calendar-alt"></i> <?php echo formatDate($ticket['created_at']); ?>
                                </span>
                             </td>
                            <td data-label="Aksi">
                                <div class="action-buttons">
                                    <button class="btn-icon" onclick='viewTicket(<?php echo json_encode($ticket); ?>)' title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if ($ticket['status'] !== 'resolved'): ?>
                                        <button class="btn-icon" onclick='editTicket(<?php echo json_encode($ticket); ?>)' title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="inline-form" onsubmit="return confirmDelete(<?php echo $ticket['id']; ?>)">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        <button type="submit" class="btn-icon danger" title="Hapus">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    
                                    <?php if (!empty($ticket['customer_phone'])): ?>
                                        <button class="btn-icon whatsapp" onclick="sendWhatsAppTicket('<?php echo $ticket['customer_phone']; ?>', <?php echo $ticket['id']; ?>)" title="Kirim WA">
                                            <i class="fab fa-whatsapp"></i>
                                        </button>
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

<!-- View Ticket Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <h3><i class="fas fa-ticket-alt"></i> Detail Tiket #<span id="view_id">-</span></h3>
            <button class="close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="ticket-details">
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user"></i> Pelanggan:</span>
                    <span class="detail-value" id="view_customer">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-tag"></i> Username:</span>
                    <span class="detail-value" id="view_username">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-flag"></i> Prioritas:</span>
                    <span class="detail-value" id="view_priority">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-chart-line"></i> Status:</span>
                    <span class="detail-value" id="view_status">-</span>
                </div>
                <div class="detail-row full-width">
                    <span class="detail-label"><i class="fas fa-comment-alt"></i> Deskripsi:</span>
                    <div class="detail-box" id="view_description">-</div>
                </div>
                <div class="detail-row full-width">
                    <span class="detail-label"><i class="fas fa-sticky-note"></i> Catatan Teknisi:</span>
                    <div class="detail-box" id="view_notes">-</div>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar-plus"></i> Dibuat:</span>
                    <span class="detail-value" id="view_created">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar-check"></i> Selesai:</span>
                    <span class="detail-value" id="view_resolved">-</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeViewModal()">Tutup</button>
        </div>
    </div>
</div>

<!-- Edit/Update Status Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Update Status Tiket</h3>
            <button class="close" onclick="closeStatusModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="ticket_id" id="status_ticket_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="status_select" class="form-control" required>
                        <option value="pending">🟡 Pending - Menunggu</option>
                        <option value="in_progress">🔵 In Progress - Sedang Diproses</option>
                        <option value="resolved">🟢 Resolved - Selesai</option>
                    </select>
                </div>
                
                <?php if (!empty($technicians)): ?>
                <div class="form-group">
                    <label class="form-label">Assign Teknisi</label>
                    <select name="technician_id" id="technician_id" class="form-control">
                        <option value="">-- Pilih Teknisi --</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?php echo $tech['id']; ?>">
                                <?php echo htmlspecialchars($tech['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Catatan Penanganan</label>
                    <textarea name="notes" id="status_notes" class="form-control" rows="3" 
                              placeholder="Masukkan catatan tentang penanganan masalah..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Additional styles for trouble tickets */
.ticket-id {
    font-family: monospace;
    font-weight: 600;
    color: var(--accent-blue);
    background: var(--bg-tertiary);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.customer-info {
    display: flex;
    flex-direction: column;
}

.customer-info small {
    font-size: 11px;
    color: var(--text-muted);
}

.issue-preview {
    font-size: 13px;
    color: var(--text-secondary);
    max-width: 250px;
}

.priority-high {
    background: rgba(248, 81, 73, 0.15);
    border-color: rgba(248, 81, 73, 0.3);
}

.priority-medium {
    background: rgba(210, 153, 34, 0.15);
    border-color: rgba(210, 153, 34, 0.3);
}

.priority-low {
    background: rgba(88, 166, 255, 0.15);
    border-color: rgba(88, 166, 255, 0.3);
}

.date-info {
    font-size: 12px;
    color: var(--text-muted);
    white-space: nowrap;
}

.date-info i {
    margin-right: 4px;
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

/* Ticket Details Modal */
.ticket-details {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.detail-row {
    display: flex;
    align-items: baseline;
    gap: 12px;
}

.detail-row.full-width {
    flex-direction: column;
    gap: 8px;
}

.detail-label {
    width: 100px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
}

.detail-value {
    font-size: 13px;
    color: var(--text-primary);
}

.detail-box {
    background: var(--bg-tertiary);
    padding: 12px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.5;
}

@media (max-width: 768px) {
    .detail-row {
        flex-direction: column;
        gap: 4px;
    }
    
    .detail-label {
        width: auto;
    }
    
    .search-wrapper {
        width: 100%;
        margin-top: 12px;
    }
    
    .search-wrapper .form-control {
        width: 100%;
    }
    
    .action-buttons {
        justify-content: flex-start;
    }
    
    .issue-preview {
        max-width: 200px;
    }
}
</style>

<script>
function viewTicket(ticket) {
    document.getElementById('view_id').textContent = ticket.id;
    document.getElementById('view_customer').textContent = ticket.customer_name || 'N/A';
    document.getElementById('view_username').textContent = ticket.pppoe_username || '-';
    
    const priorityMap = {
        'low': '<span class="badge badge-info priority-low">Low</span>',
        'medium': '<span class="badge badge-warning priority-medium">Medium</span>',
        'high': '<span class="badge badge-danger priority-high">High</span>'
    };
    document.getElementById('view_priority').innerHTML = priorityMap[ticket.priority] || ticket.priority;
    
    const statusMap = {
        'pending': '<span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>',
        'in_progress': '<span class="badge badge-info"><i class="fas fa-tools"></i> In Progress</span>',
        'resolved': '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Resolved</span>'
    };
    document.getElementById('view_status').innerHTML = statusMap[ticket.status] || ticket.status;
    
    document.getElementById('view_description').textContent = ticket.description || '-';
    document.getElementById('view_notes').textContent = ticket.notes || 'Belum ada catatan';
    document.getElementById('view_created').textContent = ticket.created_at ? formatDate(ticket.created_at) : '-';
    document.getElementById('view_resolved').textContent = ticket.resolved_at ? formatDate(ticket.resolved_at) : '-';
    
    document.getElementById('viewModal').style.display = 'flex';
}

function editTicket(ticket) {
    document.getElementById('status_ticket_id').value = ticket.id;
    document.getElementById('status_select').value = ticket.status;
    document.getElementById('status_notes').value = ticket.notes || '';
    
    const techSelect = document.getElementById('technician_id');
    if (techSelect) {
        techSelect.value = ticket.technician_id || '';
    }
    
    document.getElementById('statusModal').style.display = 'flex';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function confirmDelete(ticketId) {
    return confirm(`Hapus tiket #${ticketId}?\n\nTindakan ini tidak dapat dibatalkan!`);
}

function sendWhatsAppTicket(phone, ticketId) {
    let cleanPhone = phone.replace(/[^0-9]/g, '');
    if (cleanPhone.startsWith('0')) {
        cleanPhone = '62' + cleanPhone.substring(1);
    }
    
    const message = `Halo,\n\nKami ingin mengkonfirmasi status tiket gangguan Anda (Ticket #${ticketId}).\n\nApakah masalah sudah teratasi? Jika masih ada kendala, silakan informasikan kepada kami.\n\nTerima kasih.`;
    
    window.open(`https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`, '_blank');
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

// Search functionality
document.getElementById('searchTicket')?.addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#ticketTable tbody tr');
    
    rows.forEach(row => {
        if (row.querySelector('.empty-state')) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Close modals on outside click
document.getElementById('viewModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeViewModal();
});

document.getElementById('statusModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeStatusModal();
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewModal();
        closeStatusModal();
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';