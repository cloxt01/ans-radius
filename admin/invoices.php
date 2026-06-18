<?php
/**
 * Invoices Management - Elegant Dark Minimalis Theme
 */
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
                $generatedCount = generateInvoicesThisMonth();
                setFlash('success', "Berhasil mengenerate {$generatedCount} invoice untuk bulan ini");
                if ($generatedCount > 0) {
                    logActivity('GENERATE_INVOICES', "Generated {$generatedCount} invoices for " . date('F Y'));
                } else {
                }

                redirect('invoices.php');
                break;

            case 'pay':
                $invoiceId = (int)$_POST['invoice_id'];
                $invoice = fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
                
                if ($invoice) {
                    $pdo = getDB();
                    try {
                        $pdo->beginTransaction();
                        
                        // Update invoice to paid
                        update('invoices', [
                            'status' => 'paid',
                            'paid_at' => date('Y-m-d H:i:s'),
                            'payment_method' => sanitize($_POST['payment_method'] ?? 'Manual'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$invoiceId]);
                        
                        // Update isolation date based on latest payment
                        updateCustomerIsolationDateFromPaidInvoices($invoice['customer_id']);
                        
                        // Unisolate if needed
                        if (isCustomerIsolated($invoice['customer_id'])) {
                            unisolateCustomer($invoice['customer_id']);
                        }
                        
                        $pdo->commit();
                        
                        setFlash('success', 'Invoice berhasil dibayar');
                        logActivity('PAY_INVOICE', "Invoice: {$invoice['invoice_number']}");
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        setFlash('error', 'Gagal memproses pembayaran: ' . $e->getMessage());
                    }
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
                    
                    $wasUnpaid = $invoice['status'] !== 'paid';
                    $isNowPaid = $status === 'paid';
                    
                    if ($isNowPaid && $wasUnpaid) {
                        $updateData['paid_at'] = date('Y-m-d H:i:s');
                        $updateData['payment_method'] = 'Manual';
                        
                        update('invoices', $updateData, 'id = ?', [$invoiceId]);
                        
                        // Update isolation date
                        updateCustomerIsolationDateFromPaidInvoices($invoice['customer_id']);
                        
                        if (isCustomerIsolated($invoice['customer_id'])) {
                            unisolateCustomer($invoice['customer_id']);
                        }
                    } else {
                        update('invoices', $updateData, 'id = ?', [$invoiceId]);
                    }
                    
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
                    SELECT i.*, c.id as customer_id, c.name as customer_name, c.phone as customer_phone, p.name as package_name 
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
                        $invoice['customer_id'],
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

// Get data with initial lightweight load.
function loadInitialInvoices($limit = 10, $offset = 0, $whereSql = '', $params = [])
{
    return fetchAll(
        "SELECT i.*, c.name as customer_name, c.pppoe_username, c.phone
         FROM invoices i
         LEFT JOIN customers c ON i.customer_id = c.id
         {$whereSql}
         ORDER BY COALESCE(i.updated_at, i.created_at) DESC, i.id DESC
         LIMIT " . (int) $limit . " OFFSET " . (int) $offset
        , $params
    );
}

function renderInvoiceStatusBadges(array $invoice)
{
    if (($invoice['status'] ?? '') === 'paid') {
        $html = '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Lunas</span>';
        if (!empty($invoice['paid_at'])) {
            $html .= '<small class="date-info">' . date('d/m/Y', strtotime($invoice['paid_at'])) . '</small>';
        }

        return $html;
    }

    if (($invoice['status'] ?? '') === 'cancelled') {
        return '<span class="badge badge-muted">Batal</span>';
    }

    $dueDate = !empty($invoice['due_date']) ? substr((string) $invoice['due_date'], 0, 10) : '';
    $isOverdue = $dueDate !== '' && $dueDate < date('Y-m-d');

    return '<span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> Belum Bayar</span>'
        . ($isOverdue
            ? '<span class="badge badge-danger">Telat</span>'
            : '');
}

/**
 * Load active customers for select dropdowns and manual invoice creation.
 */
function loadActiveCustomers()
{
    return fetchAll("SELECT id, name, pppoe_username, package_id FROM customers WHERE status = 'active' ORDER BY name");
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(500, max(10, (int)($_GET['per_page'] ?? 10)));
$offset = ($page - 1) * $perPage;

$search = trim((string)($_GET['search'] ?? ''));
$filter_status = trim((string)($_GET['filter_status'] ?? ''));
$filter_due_from = trim((string)($_GET['filter_due_from'] ?? ''));
$filter_due_to = trim((string)($_GET['filter_due_to'] ?? ''));
$filter_created_from = trim((string)($_GET['filter_created_from'] ?? ''));
$filter_created_to = trim((string)($_GET['filter_created_to'] ?? ''));
$filter_paid_from = trim((string)($_GET['filter_paid_from'] ?? ''));
$filter_paid_to = trim((string)($_GET['filter_paid_to'] ?? ''));

$whereClauses = ['1=1'];
$whereParams = [];

if ($filter_status !== '') {
    if ($filter_status === 'telat') {
        $whereClauses[] = "i.status = 'unpaid'";
        $whereClauses[] = 'i.due_date < CURDATE()';
    } elseif ($filter_status === 'unpaid') {
        $whereClauses[] = "i.status = 'unpaid'";
        $whereClauses[] = 'i.due_date >= CURDATE()';
    } else {
        $whereClauses[] = 'i.status = ?';
        $whereParams[] = $filter_status;
    }
}
if ($filter_due_from !== '') {
    $whereClauses[] = 'i.due_date >= ?';
    $whereParams[] = $filter_due_from;
}
if ($filter_due_to !== '') {
    $whereClauses[] = 'i.due_date <= ?';
    $whereParams[] = $filter_due_to;
}
if ($filter_created_from !== '') {
    $whereClauses[] = 'i.created_at >= ?';
    $whereParams[] = $filter_created_from . ' 00:00:00';
}
if ($filter_created_to !== '') {
    $whereClauses[] = 'i.created_at <= ?';
    $whereParams[] = $filter_created_to . ' 23:59:59';
}
if ($filter_paid_from !== '') {
    $whereClauses[] = 'i.paid_at >= ?';
    $whereParams[] = $filter_paid_from . ' 00:00:00';
}
if ($filter_paid_to !== '') {
    $whereClauses[] = 'i.paid_at <= ?';
    $whereParams[] = $filter_paid_to . ' 23:59:59';
}
if ($search !== '' && mb_strlen($search) >= 2) {
    $whereClauses[] = '(i.invoice_number LIKE ? OR c.name LIKE ? OR c.pppoe_username LIKE ? OR c.phone LIKE ?)';
    $like = '%' . $search . '%';
    $whereParams[] = $like;
    $whereParams[] = $like;
    $whereParams[] = $like;
    $whereParams[] = $like;
}

$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
$hasActiveFilters = $filter_status !== '' || $filter_due_from !== '' || $filter_due_to !== '' || $filter_created_from !== '' || $filter_created_to !== '' || $filter_paid_from !== '' || $filter_paid_to !== '';

$totalInvoicesFiltered = (int) (fetchOne("SELECT COUNT(*) as total FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id {$whereSql}", $whereParams)['total'] ?? 0);
$totalPages = (int) max(1, ceil($totalInvoicesFiltered / $perPage));

$invoices = loadInitialInvoices($perPage, $offset, $whereSql, $whereParams);
$customers = loadActiveCustomers();

$totalInvoices = (int) (fetchOne("SELECT COUNT(*) as total FROM invoices")['total'] ?? 0);
$paidInvoices = (int) (fetchOne("SELECT COUNT(*) as total FROM invoices WHERE status = 'paid'")['total'] ?? 0);
$unpaidInvoices = (int) (fetchOne("SELECT COUNT(*) as total FROM invoices WHERE status = 'unpaid'")['total'] ?? 0);
$currentMonthKey = date('Y-m');
$monthRevenue = (float) (fetchOne("SELECT COALESCE(SUM(i.amount), 0) as total 
        FROM invoices i 
        WHERE i.status = 'paid' 
          AND i.paid_at IS NOT NULL 
          AND DATE_FORMAT(i.paid_at, '%Y-%m') = ?
          ", [$currentMonthKey])['total'] ?? 0);
// $monthRevenue = (float) (fetchOne("SELECT COALESCE(SUM(i.amount), 0) as total 
//         FROM invoices i 
//         WHERE i.status = 'paid' 
//           AND i.paid_at IS NOT NULL 
//           AND DATE_FORMAT(i.paid_at, '%Y-%m') = ? 
//           AND NOT EXISTS (
//               SELECT 1 
//               FROM fiktif_customers fc 
//               WHERE fc.customer_id = i.customer_id
//           )", [$currentMonthKey])['total'] ?? 0);
$csrfToken = generateCsrfToken();
$paginationQuery = $_GET;
unset($paginationQuery['page']);
$paginationQueryString = http_build_query($paginationQuery);
if ($paginationQueryString !== '') {
    $paginationQueryString = '&' . $paginationQueryString;
}

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
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
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
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px;"><i class="fas fa-history"></i> Riwayat Tagihan</h3>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <select id="perPageSelect" class="form-control" style="width: 110px;">
                <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10 / page</option>
                <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50 / page</option>
                <option value="100" <?php echo $perPage === 100 ? 'selected' : ''; ?>>100 / page</option>
                <option value="250" <?php echo $perPage === 250 ? 'selected' : ''; ?>>250 / page</option>
                <option value="500" <?php echo $perPage === 500 ? 'selected' : ''; ?>>500 / page</option>
            </select>
            <div class="search-wrapper" style="margin: 0;">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInvoice" class="form-control" placeholder="Cari invoice...">
            </div>
            <a href="export_invoices.php?action=export_excel" class="btn btn-primary btn-sm">
                <i class="fas fa-file-excel"></i> Export
            </a>
        </div>
    </div>
    <div class="card-body" style="padding: 12px 16px; border-top: 1px solid rgba(255,255,255,0.04);">
        <div style="display: flex; flex-direction: column; gap: 8px; align-items: center;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: center;">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Status</label>
                    <select id="filterStatus" class="form-control" style="width: 150px;">
                        <option value="">Semua Status</option>
                        <option value="unpaid" <?php echo $filter_status === 'unpaid' ? 'selected' : ''; ?>>Belum Bayar</option>
                        <option value="telat" <?php echo $filter_status === 'telat' ? 'selected' : ''; ?>>Belum Bayar + Telat</option>
                        <option value="paid" <?php echo $filter_status === 'paid' ? 'selected' : ''; ?>>Lunas</option>
                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Batal</option>
                    </select>
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Jatuh Tempo Dari</label>
                    <input type="date" id="filterDueFrom" class="form-control" value="<?php echo htmlspecialchars($filter_due_from); ?>">
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Jatuh Tempo Sampai</label>
                    <input type="date" id="filterDueTo" class="form-control" value="<?php echo htmlspecialchars($filter_due_to); ?>">
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Register From</label>
                    <input type="date" id="filterCreatedFrom" class="form-control" value="<?php echo htmlspecialchars($filter_created_from); ?>">
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Register To</label>
                    <input type="date" id="filterCreatedTo" class="form-control" value="<?php echo htmlspecialchars($filter_created_to); ?>">
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Paid From</label>
                    <input type="date" id="filterPaidFrom" class="form-control" value="<?php echo htmlspecialchars($filter_paid_from); ?>">
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Paid To</label>
                    <input type="date" id="filterPaidTo" class="form-control" value="<?php echo htmlspecialchars($filter_paid_to); ?>">
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: center; margin-top: 8px;">
                <button id="applyFilterBtn" class="btn btn-primary" style="padding: 10px 18px; font-size: 1rem; min-width: 110px; border-radius: 8px;">Filter</button>
                <button id="resetFilterBtn" class="btn btn-secondary" style="padding: 10px 18px; font-size: 1rem; min-width: 110px; border-radius: 8px;">Reset</button>
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="data-table" id="invoiceTable">
            <thead>
                <tr>
                    <th>No. Invoice</th>
                    <th>Pelanggan</th>
                    <th>Tgl dibuat</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>Jatuh Tempo</th>
                    <!-- <th>Online</th> -->
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="invoiceTableBody">
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Belum ada data invoice</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                    <tr data-invoice="<?php echo htmlspecialchars(json_encode($inv), ENT_QUOTES, 'UTF-8'); ?>">
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
                            <?php echo renderInvoiceStatusBadges($inv); ?>
                        </td>
                        <td data-label="Jatuh Tempo"><?php echo formatDate($inv['due_date']); ?></td>
                        <td data-label="Aksi">
                            <div class="action-buttons">
                                <?php if ($inv['status'] === 'unpaid'): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="pay">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                        <input type="hidden" name="payment_method" value="Manual">
                                        <button type="submit" class="btn-icon success" title="Bayar Lunas">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="unisolate_only">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                        <button type="submit" class="btn-icon" title="Buka Isolir">
                                            <i class="fas fa-unlock-alt"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Tunda jatuh tempo invoice ini ke bulan berikutnya?');">
                                        <input type="hidden" name="action" value="defer_next_month">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                        <button type="submit" class="btn-icon" title="Tunda ke Bulan Depan">
                                            <i class="fas fa-calendar-plus"></i>
                                        </button>
                                    </form>

                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="generate_payment_link">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
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
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
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

    <?php if ($totalPages > 1): ?>
    <div id="invoicePagination" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px;">
        <a href="?page=1<?php echo $paginationQueryString; ?>" class="btn btn-secondary btn-sm" <?php echo $page === 1 ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-double-left"></i>
        </a>
        <a href="?page=<?php echo max(1, $page - 1); ?><?php echo $paginationQueryString; ?>" class="btn btn-secondary btn-sm" <?php echo $page === 1 ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-left"></i>
        </a>
        <span style="color: var(--text-secondary); display: inline-block; text-align: center; min-width: 260px;">
            Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?>
            (Total: <?php echo $totalInvoicesFiltered; ?> invoice)
        </span>
        <a href="?page=<?php echo min($totalPages, $page + 1); ?><?php echo $paginationQueryString; ?>" class="btn btn-secondary btn-sm" <?php echo $page === $totalPages ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-right"></i>
        </a>
        <a href="?page=<?php echo $totalPages; ?><?php echo $paginationQueryString; ?>" class="btn btn-secondary btn-sm" <?php echo $page === $totalPages ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-double-right"></i>
        </a>
    </div>
    <?php endif; ?>
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
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
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
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
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
const invoiceTableBody = document.getElementById('invoiceTableBody');
const initialInvoiceTableHtml = invoiceTableBody ? invoiceTableBody.innerHTML : '';
const INVOICE_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatDateLabel(dateStr) {
    if (!dateStr) {
        return '';
    }

    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) {
        return escapeHtml(dateStr);
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    }).format(date);
}

function formatMonthYear(dateStr) {
    if (!dateStr) {
        return '';
    }

    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) {
        return escapeHtml(dateStr);
    }

    return new Intl.DateTimeFormat('id-ID', {
        month: 'long',
        year: 'numeric'
    }).format(date);
}

function formatCurrencyIdr(amount) {
    const number = Number(amount || 0);
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(number);
}

function buildInvoiceStatusBadges(invoice) {
    if (invoice.status === 'paid') {
        return `<span class="badge badge-success"><i class="fas fa-check-circle"></i> Lunas</span>${invoice.paid_at ? `<small class="date-info">${formatDateLabel(invoice.paid_at)}</small>` : ''}`;
    }

    if (invoice.status === 'cancelled') {
        return '<span class="badge badge-muted">Batal</span>';
    }

    const dueDate = invoice.due_date ? String(invoice.due_date).slice(0, 10) : '';
    const today = new Date().toISOString().slice(0, 10);
    const isOverdue = dueDate !== '' && dueDate <= today;

    return `<span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> Belum Bayar</span>${isOverdue ? '<span class="badge badge-danger">Telat</span>' : ''}`;
}

function restoreInitialInvoices() {
    if (!invoiceTableBody) {
        return;
    }

    invoiceTableBody.innerHTML = initialInvoiceTableHtml;

    const pagination = document.getElementById('invoicePagination');
    if (pagination) {
        pagination.style.display = '';
    }
}

function renderFetchedInvoices(invoices) {
    if (!invoiceTableBody) {
        return;
    }

    if (!invoices || invoices.length === 0) {
        invoiceTableBody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Tidak ada data invoice ditemukan</p>
                </td>
            </tr>
        `;
        return;
    }

    invoiceTableBody.innerHTML = invoices.map(invoice => {
        const invoiceJson = JSON.stringify(invoice).replace(/'/g, '&#39;');
        const statusBadge = buildInvoiceStatusBadges(invoice);

        const actionButtons = [];

        if (invoice.status === 'unpaid') {
            actionButtons.push(`
                <form method="POST" class="inline-form">
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="csrf_token" value="${INVOICE_CSRF_TOKEN}">
                    <input type="hidden" name="invoice_id" value="${escapeHtml(invoice.id)}">
                    <input type="hidden" name="payment_method" value="Manual">
                    <button type="submit" class="btn-icon success" title="Bayar Lunas"><i class="fas fa-check"></i></button>
                </form>
            `);
            actionButtons.push(`
                <form method="POST" class="inline-form">
                    <input type="hidden" name="action" value="unisolate_only">
                    <input type="hidden" name="csrf_token" value="${INVOICE_CSRF_TOKEN}">
                    <input type="hidden" name="invoice_id" value="${escapeHtml(invoice.id)}">
                    <button type="submit" class="btn-icon" title="Buka Isolir"><i class="fas fa-unlock-alt"></i></button>
                </form>
            `);
            actionButtons.push(`
                <form method="POST" class="inline-form" onsubmit="return confirm('Tunda jatuh tempo invoice ini ke bulan berikutnya?');">
                    <input type="hidden" name="action" value="defer_next_month">
                    <input type="hidden" name="csrf_token" value="${INVOICE_CSRF_TOKEN}">
                    <input type="hidden" name="invoice_id" value="${escapeHtml(invoice.id)}">
                    <button type="submit" class="btn-icon" title="Tunda ke Bulan Depan"><i class="fas fa-calendar-plus"></i></button>
                </form>
            `);
            actionButtons.push(`
                <form method="POST" class="inline-form">
                    <input type="hidden" name="action" value="generate_payment_link">
                    <input type="hidden" name="csrf_token" value="${INVOICE_CSRF_TOKEN}">
                    <input type="hidden" name="invoice_id" value="${escapeHtml(invoice.id)}">
                    <button type="submit" class="btn-icon" title="Payment Link"><i class="fas fa-link"></i></button>
                </form>
            `);
        }

        actionButtons.push(`<button class="btn-icon" type="button" onclick='editInvoiceFromRow(this)' title="Edit"><i class="fas fa-edit"></i></button>`);

        if (invoice.status !== 'paid') {
            actionButtons.push(`
                <form method="POST" class="inline-form" onsubmit="return confirm('Hapus invoice ini?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="${INVOICE_CSRF_TOKEN}">
                    <input type="hidden" name="invoice_id" value="${escapeHtml(invoice.id)}">
                    <button type="submit" class="btn-icon danger" title="Hapus"><i class="fas fa-trash-alt"></i></button>
                </form>
            `);
        }

        if (invoice.phone) {
            actionButtons.push(`
                <button class="btn-icon whatsapp" type="button" onclick="sendWhatsApp(${JSON.stringify(invoice.phone)}, ${JSON.stringify(invoice.invoice_number)}, ${JSON.stringify(formatCurrencyIdr(invoice.amount))})" title="Kirim WA">
                    <i class="fab fa-whatsapp"></i>
                </button>
            `);
        }

        return `
            <tr data-invoice='${invoiceJson}'>
                <td data-label="No. Invoice"><code class="invoice-code">${escapeHtml(invoice.invoice_number)}</code></td>
                <td data-label="Pelanggan"><div class="customer-info"><strong>${escapeHtml(invoice.customer_name || '-')}</strong><small>${escapeHtml(invoice.pppoe_username || '-')}</small></div></td>
                <td data-label="Periode">${formatMonthYear(invoice.created_at)}</td>
                <td data-label="Jumlah"><strong class="price">${formatCurrencyIdr(invoice.amount)}</strong></td>
                <td data-label="Status">${statusBadge}</td>
                <td data-label="Jatuh Tempo">${formatDateLabel(invoice.due_date)}</td>
                <td data-label="Aksi"><div class="action-buttons">${actionButtons.join('')}</div></td>
            </tr>
        `;
    }).join('');
}

async function fetchInvoiceSearch(search) {
    const status = document.getElementById('filterStatus') ? document.getElementById('filterStatus').value : '';
    const dueFrom = document.getElementById('filterDueFrom') ? document.getElementById('filterDueFrom').value : '';
    const dueTo = document.getElementById('filterDueTo') ? document.getElementById('filterDueTo').value : '';
    const createdFrom = document.getElementById('filterCreatedFrom') ? document.getElementById('filterCreatedFrom').value : '';
    const createdTo = document.getElementById('filterCreatedTo') ? document.getElementById('filterCreatedTo').value : '';
    const paidFrom = document.getElementById('filterPaidFrom') ? document.getElementById('filterPaidFrom').value : '';
    const paidTo = document.getElementById('filterPaidTo') ? document.getElementById('filterPaidTo').value : '';
    const perPage = document.getElementById('perPageSelect') ? document.getElementById('perPageSelect').value : '10';
    const hasFilters = status || dueFrom || dueTo || createdFrom || createdTo || paidFrom || paidTo;

    if ((!search || search.length < 2) && !hasFilters) {
        restoreInitialInvoices();
        return;
    }

    const pagination = document.getElementById('invoicePagination');
    if (pagination) {
        pagination.style.display = 'none';
    }

    if (invoiceTableBody) {
        invoiceTableBody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>Mencari invoice...</p>
                </td>
            </tr>
        `;
    }

    try {
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (status) params.append('filter_status', status);
        if (dueFrom) params.append('filter_due_from', dueFrom);
        if (dueTo) params.append('filter_due_to', dueTo);
        if (createdFrom) params.append('filter_created_from', createdFrom);
        if (createdTo) params.append('filter_created_to', createdTo);
        if (paidFrom) params.append('filter_paid_from', paidFrom);
        if (paidTo) params.append('filter_paid_to', paidTo);
        params.append('per_page', perPage);
        params.append('page', '1');

        const url = `../api/invoices.php?${params.toString()}`;
        console.debug('Fetching invoices:', url);

        // Include cookies/auth (same-origin) so `requireAdminLogin()` allows the request.
        const response = await fetch(url, { credentials: 'same-origin' });

        // Read as text first to handle cases where server returns HTML (redirect/login page)
        const text = await response.text();
        let data = null;

        try {
            data = JSON.parse(text);
        } catch (err) {
            console.warn('Non-JSON response from API (possibly redirect/login or error).', text.substring(0, 100));
            if (invoiceTableBody) {
                invoiceTableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>Respons tidak valid dari server. Cek console (non-JSON) atau login mungkin kadaluarsa.</p>
                        </td>
                    </tr>
                `;
            }
            return;
        }

        if (data.success && data.data && Array.isArray(data.data.invoices)) {
            if (Array.isArray(data.data.invoices) && data.data.invoices.length === 0 && data.message) {
                if (invoiceTableBody) {
                    invoiceTableBody.innerHTML = `
                        <tr>
                            <td colspan="7" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>${escapeHtml(data.message)}</p>
                            </td>
                        </tr>
                    `;
                }
            } else {
                renderFetchedInvoices(data.data.invoices);
            }
        } else if (invoiceTableBody) {
            invoiceTableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>${escapeHtml(data.message || 'Tidak ada data invoice ditemukan')}</p>
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Search invoice error:', error);
        if (invoiceTableBody) {
            invoiceTableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Gagal memuat hasil pencarian</p>
                    </td>
                </tr>
            `;
        }
    }
}

function getInvoiceFilterValue(id) {
    const element = document.getElementById(id);
    return element ? element.value.trim() : '';
}

function editInvoiceFromRow(button) {
    const row = button.closest('tr');
    if (!row || !row.dataset.invoice) {
        return;
    }

    try {
        editInvoice(JSON.parse(row.dataset.invoice));
    } catch (error) {
        console.error('Failed to parse invoice row data:', error);
    }
}

// Search functionality
const searchInput = document.getElementById('searchInvoice');
if (searchInput) {
    let invoiceSearchTimer = null;
    searchInput.addEventListener('input', function(e) {
        const search = e.target.value.trim();
        clearTimeout(invoiceSearchTimer);
        invoiceSearchTimer = setTimeout(() => {
            fetchInvoiceSearch(search);
        }, 350);
    });
}

const applyFilterBtn = document.getElementById('applyFilterBtn');
const resetFilterBtn = document.getElementById('resetFilterBtn');
const perPageSelect = document.getElementById('perPageSelect');

if (applyFilterBtn) {
    applyFilterBtn.addEventListener('click', function() {
        const params = new URLSearchParams(window.location.search);
        params.set('page', '1');
        params.set('search', (document.getElementById('searchInvoice') || { value: '' }).value.trim());

        const filterParamMap = {
            filterStatus: 'filter_status',
            filterDueFrom: 'filter_due_from',
            filterDueTo: 'filter_due_to',
            filterCreatedFrom: 'filter_created_from',
            filterCreatedTo: 'filter_created_to',
            filterPaidFrom: 'filter_paid_from',
            filterPaidTo: 'filter_paid_to'
        };

        Object.keys(filterParamMap).forEach(id => {
            const value = getInvoiceFilterValue(id);
            const paramName = filterParamMap[id];
            if (value) {
                params.set(paramName, value);
            } else {
                params.delete(paramName);
            }
        });

        if (perPageSelect && perPageSelect.value) {
            params.set('per_page', perPageSelect.value);
        }

        window.location.search = params.toString();
    });
}

if (resetFilterBtn) {
    resetFilterBtn.addEventListener('click', function() {
        window.location.href = window.location.pathname;
    });
}

if (perPageSelect) {
    perPageSelect.addEventListener('change', function() {
        const params = new URLSearchParams(window.location.search);
        params.set('page', '1');
        params.set('per_page', perPageSelect.value || '10');
        window.location.search = params.toString();
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