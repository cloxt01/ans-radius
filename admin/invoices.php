<?php
/**
 * Invoices Management - Elegant Dark Minimalis Theme
 */
ini_set('memory_limit', '512M');
require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Invoice';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Invalid CSRF token');
        redirect('invoices.php');
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate':
                $customers = fetchAll("SELECT * FROM customers WHERE status = 'active'");
                $generatedCount = 0;
                $currentMonth = date('Y-m');
                
                foreach ($customers as $customer) {
                    $existingInvoice = fetchOne("
                        SELECT id FROM invoices 
                        WHERE customer_id = ? 
                        AND DATE_FORMAT(created_at, '%Y-%m') = ?",
                        [$customer['id'], $currentMonth]
                    );
                    
                    if (!$existingInvoice) {
                        $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);
                        
                        if ($package) {
                            $dueDate = getCustomerDueDate($customer, $currentMonth . '-01');
                            $invoiceData = [
                                'invoice_number' => generateInvoiceNumber(),
                                'customer_id' => $customer['id'],
                                'amount' => $package['price'],
                                'status' => 'unpaid',
                                'due_date' => $dueDate,
                                'created_at' => date('Y-m-d H:i:s')
                            ];
                            
                            insert('invoices', $invoiceData);
                            $generatedCount++;
                        }
                    }
                }
                
                setFlash('success', "Invoice berhasil digenerate untuk {$generatedCount} pelanggan aktif");
                logActivity('GENERATE_INVOICES', "Generated {$generatedCount} invoices for " . date('F Y'));
                redirect('invoices.php');
                break;
                
            case 'pay':
                $invoiceId = (int)$_POST['invoice_id'];
                $invoice = fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
                
                if ($invoice) {
                    update('invoices', [
                        'status' => 'paid',
                        'paid_at' => date('Y-m-d H:i:s'),
                        'payment_method' => sanitize($_POST['payment_method'] ?? 'Manual'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$invoiceId]);
                    
                    if (isCustomerIsolated($invoice['customer_id'])) {
                        unisolateCustomer($invoice['customer_id']);
                    }
                    
                    setFlash('success', 'Invoice berhasil dibayar');
                    logActivity('PAY_INVOICE', "Invoice: {$invoice['invoice_number']}");
                } else {
                    setFlash('error', 'Invoice tidak ditemukan');
                }
                redirect('invoices.php');
                break;
                
            case 'unisolate_only':
                $invoiceId = (int)$_POST['invoice_id'];
                $invoice = fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
                
                if ($invoice && $invoice['status'] === 'unpaid') {
                    if (unisolateCustomer($invoice['customer_id'])) {
                        setFlash('success', 'Pelanggan berhasil di-unisolate (tagihan tetap belum lunas)');
                    } else {
                        setFlash('error', 'Gagal meng-unisolate pelanggan');
                    }
                } else {
                    setFlash('error', 'Invoice tidak ditemukan atau sudah lunas');
                }
                redirect('invoices.php');
                break;
            
            case 'defer_next_month':
                $invoiceId = (int)$_POST['invoice_id'];
                $invoice = fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
                
                if ($invoice && $invoice['status'] === 'unpaid') {
                    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$invoice['customer_id']]);
                    
                    if ($customer) {
                        $nextMonthBase = date('Y-m-01', strtotime('+1 month'));
                        $newDueDate = getCustomerDueDate($customer, $nextMonthBase);
                        
                        $description = $invoice['description'] ?? '';
                        $note = 'Ditunda ke bulan berikutnya dari due date ' . $invoice['due_date'];
                        $description .= $description ? ' | ' . $note : $note;
                        
                        update('invoices', [
                            'due_date' => $newDueDate,
                            'description' => $description,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$invoiceId]);
                        
                        if (isCustomerIsolated($invoice['customer_id'])) {
                            unisolateCustomer($invoice['customer_id']);
                        }
                        
                        setFlash('success', 'Invoice ditunda ke bulan berikutnya dan isolir pelanggan dibuka.');
                        logActivity('DEFER_INVOICE', "Invoice: {$invoice['invoice_number']} deferred to {$newDueDate}");
                    } else {
                        setFlash('error', 'Pelanggan tidak ditemukan');
                    }
                } else {
                    setFlash('error', 'Invoice tidak ditemukan atau sudah lunas');
                }
                redirect('invoices.php');
                break;
                
            case 'edit':
                $invoiceId = (int)$_POST['invoice_id'];
                $amount = (float)$_POST['amount'];
                $dueDate = sanitize($_POST['due_date']);
                $status = sanitize($_POST['status']);
                
                $invoice = fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
                
                if ($invoice) {
                    $updateData = [
                        'amount' => $amount,
                        'due_date' => $dueDate,
                        'status' => $status,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    if ($status === 'paid' && $invoice['status'] !== 'paid') {
                        $updateData['paid_at'] = date('Y-m-d H:i:s');
                        $updateData['payment_method'] = 'Manual';
                        
                        if (isCustomerIsolated($invoice['customer_id'])) {
                            unisolateCustomer($invoice['customer_id']);
                        }
                    }
                    
                    update('invoices', $updateData, 'id = ?', [$invoiceId]);
                    setFlash('success', 'Invoice berhasil diperbarui');
                    logActivity('EDIT_INVOICE', "Invoice: {$invoice['invoice_number']}");
                } else {
                    setFlash('error', 'Invoice tidak ditemukan');
                }
                redirect('invoices.php');
                break;
                
            case 'delete':
                $invoiceId = (int)$_POST['invoice_id'];
                $invoice = fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
                
                if ($invoice) {
                    if ($invoice['status'] === 'paid') {
                        setFlash('error', 'Invoice yang sudah lunas tidak dapat dihapus');
                    } else {
                        delete('invoices', 'id = ?', [$invoiceId]);
                        setFlash('success', 'Invoice berhasil dihapus');
                        logActivity('DELETE_INVOICE', "Invoice: {$invoice['invoice_number']}");
                    }
                } else {
                    setFlash('error', 'Invoice tidak ditemukan');
                }
                redirect('invoices.php');
                break;
                
            case 'create_manual':
                $customerId = (int)$_POST['customer_id'];
                $amount = (float)$_POST['manual_amount'];
                $dueDate = sanitize($_POST['manual_due_date']);
                $description = sanitize($_POST['manual_description'] ?? '');
                
                $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
                
                if ($customer) {
                    $invoiceData = [
                        'invoice_number' => generateInvoiceNumber(),
                        'customer_id' => $customerId,
                        'amount' => $amount,
                        'status' => 'unpaid',
                        'due_date' => $dueDate,
                        'description' => $description,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    insert('invoices', $invoiceData);
                    setFlash('success', 'Invoice manual berhasil dibuat');
                    logActivity('CREATE_INVOICE', "Manual invoice for customer: {$customer['name']}");
                } else {
                    setFlash('error', 'Pelanggan tidak ditemukan');
                }
                redirect('invoices.php');
                break;

            case 'generate_payment_link':
                $invoiceId = (int)$_POST['invoice_id'];
                $invoice = fetchOne("
                    SELECT i.*, c.name as customer_name, c.phone as customer_phone, p.name as package_name 
                    FROM invoices i 
                    LEFT JOIN customers c ON i.customer_id = c.id 
                    LEFT JOIN packages p ON c.package_id = p.id 
                    WHERE i.id = ?", [$invoiceId]);

                if ($invoice && $invoice['status'] === 'unpaid') {
                    $defaultGateway = getSetting('DEFAULT_PAYMENT_GATEWAY', 'tripay');
                    if (!in_array($defaultGateway, ['tripay', 'midtrans'], true)) {
                        $defaultGateway = 'tripay';
                    }

                    require_once '../includes/payment.php';

                    $result = generatePaymentLink(
                        $invoice['invoice_number'],
                        $invoice['amount'],
                        $invoice['customer_name'],
                        $invoice['customer_phone'],
                        $invoice['due_date'],
                        $defaultGateway
                    );

                    if (!empty($result['success']) && !empty($result['link'])) {
                        logActivity('PAYMENT_LINK_GENERATED', "Invoice: {$invoice['invoice_number']}, Gateway: {$defaultGateway}");
                        redirect($result['link']);
                    }

                    setFlash('error', $result['message'] ?? 'Gagal generate payment link');
                } else {
                    setFlash('error', 'Invoice tidak ditemukan atau sudah lunas');
                }
                redirect('invoices.php');
                break;
        }
    }
}

// Get data
$invoices = fetchAll("
    SELECT i.*, c.name as customer_name, c.pppoe_username, c.phone 
    FROM invoices i 
    LEFT JOIN customers c ON i.customer_id = c.id 
    ORDER BY i.updated_at DESC
");

$customers = fetchAll("SELECT id, name, pppoe_username, package_id FROM customers WHERE status = 'active' ORDER BY name");
$activeSessions = mikrotikGetActiveSessionsAllRouter();
$totalInvoices = count($invoices);
$paidInvoices = count(array_filter($invoices, fn($i) => $i['status'] === 'paid'));
$unpaidInvoices = $totalInvoices - $paidInvoices;
$currentMonthKey = date('Y-m');
$paidThisMonth = array_filter($invoices, fn($i) => $i['status'] === 'paid' && !empty($i['paid_at']) && date('Y-m', strtotime($i['paid_at'])) === $currentMonthKey);
$monthRevenue = array_sum(array_column($paidThisMonth, 'amount'));

ob_start();
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $totalInvoices; ?></h3>
            <p>Total Invoice</p>
        </div>
        <div class="stat-icon purple">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $paidInvoices; ?></h3>
            <p>Lunas</p>
        </div>
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $unpaidInvoices; ?></h3>
            <p>Belum Bayar</p>
        </div>
        <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo formatCurrency($monthRevenue); ?></h3>
            <p>Pendapatan Bulan Ini</p>
        </div>
        <div class="stat-icon blue">
            <i class="fas fa-chart-line"></i>
        </div>
    </div>
</div>

<!-- Action Cards -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-magic"></i> Generate & Manual Invoice</h3>
    </div>
    <div class="card-body">
        <div class="action-grid">
            <!-- Auto Generate -->
            <div class="action-card">
                <i class="fas fa-calendar-alt"></i>
                <h4>Generate Otomatis</h4>
                <p>Buat invoice untuk semua pelanggan aktif bulan ini</p>
                <form method="POST" data-no-loading="true">
                    <input type="hidden" name="action" value="generate">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-magic"></i> Generate
                    </button>
                </form>
                <small class="info-text">
                    <?php 
                    $existingThisMonth = fetchOne("SELECT COUNT(*) as count FROM invoices WHERE DATE_FORMAT(created_at, '%Y-%m') = ?", [date('Y-m')]);
                    echo $existingThisMonth['count'] . " invoice bulan ini";
                    ?>
                </small>
            </div>
            
            <!-- Manual Invoice -->
            <div class="action-card">
                <i class="fas fa-pen-alt"></i>
                <h4>Invoice Manual</h4>
                <p>Buat invoice khusus untuk pelanggan tertentu</p>
                <button type="button" class="btn btn-secondary" onclick="openManualInvoiceModal()">
                    <i class="fas fa-plus"></i> Buat Manual
                </button>
                <small class="info-text">Untuk tagihan khusus atau tambahan</small>
            </div>
        </div>
    </div>
</div>

<!-- Invoices Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> Riwayat Tagihan</h3>
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInvoice" class="form-control" placeholder="Cari invoice...">
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="data-table" id="invoiceTable">
            <thead>
                <tr>
                    <th>No. Invoice</th>
                    <th>Pelanggan</th>
                    <th>Periode</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>Jatuh Tempo</th>
                    <th>Online</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Belum ada data invoice</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td data-label="No. Invoice">
                            <code class="invoice-code"><?php echo htmlspecialchars($inv['invoice_number']); ?></code>
                        </td>
                        <td data-label="Pelanggan">
                            <div class="customer-info">
                                
                                <strong><?php echo htmlspecialchars($inv['customer_name'] ?? '-'); ?></strong>
                                <small><?php echo htmlspecialchars($inv['pppoe_username'] ?? '-'); ?></small>
                            </div>
                        </td>
                        <td data-label="Periode"><?php echo date('F Y', strtotime($inv['created_at'])); ?></td>
                        <td data-label="Jumlah">
                            <strong class="price"><?php echo formatCurrency($inv['amount']); ?></strong>
                        </td>
                        <td data-label="Status">
                            <?php if ($inv['status'] === 'paid'): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i> Lunas
                                </span>
                                <?php if ($inv['paid_at']): ?>
                                    <small class="date-info"><?php echo date('d/m/Y', strtotime($inv['paid_at'])); ?></small>
                                <?php endif; ?>
                            <?php elseif ($inv['status'] === 'cancelled'): ?>
                                <span class="badge badge-muted">Batal</span>
                            <?php else: ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-hourglass-half"></i> Belum Bayar
                                </span>
                                <?php if (strtotime($inv['due_date']) < time()): ?>
                                    <span class="badge badge-danger">Telat</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Jatuh Tempo"><?php echo formatDate($inv['due_date']); ?></td>
                        <td data-label="Online">
                            <?php
                            foreach ($activeSessions as $session) {
                                if ($session['name'] === $inv['pppoe_username']) {
                                    echo '<span class="badge badge-success">Ya</span>';
                                    break;
                                }
                            }
                            ?>
                            <span class="badge badge-secondary">Tidak</span>
                        </td>
                        <td data-label="Aksi">
                            <div class="action-buttons">
                                <?php if ($inv['status'] === 'unpaid'): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="pay">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                        <input type="hidden" name="payment_method" value="Manual">
                                        <button type="submit" class="btn-icon success" title="Bayar Lunas">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="unisolate_only">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                        <button type="submit" class="btn-icon" title="Buka Isolir">
                                            <i class="fas fa-unlock-alt"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Tunda jatuh tempo invoice ini ke bulan berikutnya?');">
                                        <input type="hidden" name="action" value="defer_next_month">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                        <button type="submit" class="btn-icon" title="Tunda ke Bulan Depan">
                                            <i class="fas fa-calendar-plus"></i>
                                        </button>
                                    </form>

                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="generate_payment_link">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                        <button type="submit" class="btn-icon" title="Payment Link">
                                            <i class="fas fa-link"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button class="btn-icon" onclick="editInvoice(<?php echo htmlspecialchars(json_encode($inv)); ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($inv['status'] !== 'paid'): ?>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Hapus invoice ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                        <button type="submit" class="btn-icon danger" title="Hapus">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if (!empty($inv['phone'])): ?>
                                    <button class="btn-icon whatsapp" onclick="sendWhatsApp('<?php echo htmlspecialchars($inv['phone']); ?>', '<?php echo htmlspecialchars($inv['invoice_number']); ?>', '<?php echo htmlspecialchars(formatCurrency($inv['amount'])); ?>')" title="Kirim WA">
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

<!-- Edit Invoice Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Invoice</h3>
            <button class="close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="invoice_id" id="edit_invoice_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">No. Invoice</label>
                    <input type="text" id="edit_invoice_number" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Jumlah (Rp)</label>
                    <input type="number" name="amount" id="edit_amount" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Jatuh Tempo</label>
                    <input type="date" name="due_date" id="edit_due_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit_status" class="form-control" required>
                        <option value="unpaid">Belum Bayar</option>
                        <option value="paid">Lunas</option>
                        <option value="cancelled">Batal</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Manual Invoice Modal -->
<div id="manualModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Invoice Manual</h3>
            <button class="close" onclick="closeManualModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_manual">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Pelanggan</label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">Pilih Pelanggan</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['pppoe_username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Jumlah (Rp)</label>
                    <input type="number" name="manual_amount" class="form-control" required placeholder="Contoh: 150000">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Jatuh Tempo</label>
                    <input type="date" name="manual_due_date" class="form-control" required value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="manual_description" class="form-control" placeholder="Contoh: Tagihan tambahan">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeManualModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Buat Invoice
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Additional styles for invoices page */
.action-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.action-card {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 20px;
    text-align: center;
    transition: all var(--transition-fast);
}

.action-card:hover {
    border-color: var(--border-color);
}

.action-card i {
    font-size: 32px;
    color: var(--accent-blue);
    margin-bottom: 12px;
}

.action-card h4 {
    font-size: 16px;
    margin-bottom: 8px;
}

.action-card p {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 16px;
}

.info-text {
    display: block;
    margin-top: 10px;
    font-size: 11px;
    color: var(--text-muted);
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

.invoice-code {
    background: var(--bg-tertiary);
    padding: 4px 8px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    color: var(--accent-blue);
}

.customer-info {
    display: flex;
    flex-direction: column;
}

.customer-info small {
    font-size: 11px;
    color: var(--text-muted);
}

.date-info {
    display: block;
    font-size: 10px;
    color: var(--text-muted);
    margin-top: 4px;
}

.price {
    color: var(--accent-green);
    font-weight: 600;
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
}

.btn-icon:hover {
    background: var(--bg-secondary);
    border-color: var(--border-color);
    color: var(--accent-blue);
}

.btn-icon.success:hover {
    color: var(--accent-green);
    border-color: var(--accent-green);
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

@media (max-width: 768px) {
    .action-grid {
        grid-template-columns: 1fr;
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
}
</style>

<script>
// Search functionality
const searchInput = document.getElementById('searchInvoice');
if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#invoiceTable tbody tr');
        
        rows.forEach(row => {
            if (row.querySelector('.empty-state')) return;
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(search) ? '' : 'none';
        });
    });
}

// Edit modal
function editInvoice(invoice) {
    document.getElementById('edit_invoice_id').value = invoice.id;
    document.getElementById('edit_invoice_number').value = invoice.invoice_number;
    document.getElementById('edit_amount').value = invoice.amount;
    document.getElementById('edit_due_date').value = invoice.due_date;
    document.getElementById('edit_status').value = invoice.status;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function openManualInvoiceModal() {
    document.getElementById('manualModal').style.display = 'flex';
}

function closeManualModal() {
    document.getElementById('manualModal').style.display = 'none';
}

// Send WhatsApp
function sendWhatsApp(phone, invoiceNumber, amount) {
    if (!phone) {
        alert('Nomor HP pelanggan tidak tersedia');
        return;
    }
    
    let cleanPhone = phone.replace(/[^0-9]/g, '');
    if (cleanPhone.startsWith('0')) {
        cleanPhone = '62' + cleanPhone.substring(1);
    }
    
    const message = `Halo,\n\nBerikut adalah informasi tagihan internet Anda:\n\nInvoice: ${invoiceNumber}\nJumlah: ${amount}\n\nMohon lakukan pembayaran sebelum jatuh tempo.\n\nTerima kasih.`;
    
    window.open(`https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`, '_blank');
}

// Close modals on outside click
document.getElementById('editModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

document.getElementById('manualModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeManualModal();
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
        closeManualModal();
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';