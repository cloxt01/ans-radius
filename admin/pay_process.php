<?php
/**
 * Admin - Process Payment
 */

require_once '../includes/auth.php';
requireAdminLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);

if (!$customer) {
    setFlash('error', 'Pelanggan tidak ditemukan.');
    redirect('customers.php');
}

$pageTitle = 'Bayar Tagihan: ' . $customer['name'];
$workdir = 'admin/pay_process.php';

// Get Package Info
$package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);

// Handle Payment
// Handle Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Sesi tidak valid.');
        redirect("pay_process.php?id=$id");
    }

    $selectedMonths = $_POST['selected_months'] ?? [];
    $selectedYear = (int) $_POST['year'];

    // Log awal percobaan pembayaran
    actionLog('PAYMENT_PROCESS_ATTEMPT', $workdir, "Mencoba memproses pembayaran pelanggan", json_encode([
            'customer_id' => $id,
            'selected_months' => $selectedMonths,
            'year' => $selectedYear,
            'package_price' => $package['price'] ?? null
    ]));

    if (empty($selectedMonths)) {
        actionLog('PAYMENT_PROCESS_FAILED', $workdir, "Tidak ada bulan yang dipilih", json_encode(['customer_id' => $id]));
        setFlash('error', 'Pilih minimal 1 bulan.');
        redirect("pay_process.php?id=$id&year=$selectedYear");
    }

    $monthsCount = count($selectedMonths);
    $amountPerMonth = $package['price'];
    $totalBill = $amountPerMonth * $monthsCount;
    $paymentDateTime = date('Y-m-d H:i:s');
    $paymentDate = date('Y-m-d');

    $pdo = getDB();
    try {
        $pdo->beginTransaction();

        $generatedInvoiceIds = [];
        foreach ($selectedMonths as $monthNum) {
            // Get due date based on payment date (same day)
            $day = (int) date('d', strtotime($paymentDate));

            // Adjust if day exceeds month days
            $lastDayOfMonth = (int) date('t', strtotime("$selectedYear-$monthNum-01"));
            if ($day > $lastDayOfMonth) {
                $day = $lastDayOfMonth;
            }

            $dueDate = date('Y-m-d', strtotime("$selectedYear-$monthNum-$day"));

            // Check if invoice already exists for this month/year (unpaid)
            $existingInvoice = fetchOne("SELECT id FROM invoices 
                WHERE customer_id = ? 
                AND MONTH(due_date) = ? 
                AND YEAR(due_date) = ? 
                AND status = 'unpaid'",
                    [$id, $monthNum, $selectedYear]);

            if ($existingInvoice) {
                // Update existing unpaid invoice to paid
                update('invoices', [
                        'status' => 'paid',
                        'paid_at' => $paymentDateTime,
                        'payment_method' => 'manual_admin',
                        'updated_at' => $paymentDateTime
                ], 'id = ?', [$existingInvoice['id']]);

                $generatedInvoiceIds[] = $existingInvoice['id'];
            } else {
                // Check if already paid
                $alreadyPaid = fetchOne("SELECT id FROM invoices 
                    WHERE customer_id = ? 
                    AND MONTH(due_date) = ? 
                    AND YEAR(due_date) = ? 
                    AND status = 'paid'",
                        [$id, $monthNum, $selectedYear]);

                if (!$alreadyPaid) {
                    // Create new invoice
                    $invData = [
                            'invoice_number' => generateInvoiceNumber($id),
                            'customer_id' => $id,
                            'amount' => $amountPerMonth,
                            'status' => 'paid',
                            'due_date' => $dueDate,
                            'paid_at' => $paymentDateTime,
                            'payment_method' => 'manual_admin',
                            'created_at' => $paymentDateTime
                    ];
                    $invId = insert('invoices', $invData);
                    $generatedInvoiceIds[] = $invId;
                }
            }
        }

        // Unisolate customer if they were isolated
        if ($customer['status'] === 'isolated') {
            actionLog('PAYMENT_PROCESS_UNISOLATE', $workdir, "Membuka isolir pelanggan karena pembayaran", json_encode(['customer_id' => $id]));
            unisolateCustomer($id);
        }

        // ========== UPDATE ISOLATION DATE BASED ON PAYMENT ==========
        // Get the LAST month paid
        $maxMonth = max($selectedMonths);
        $maxYear = $selectedYear;

        // Get payment day
        $paymentDay = (int) date('d', strtotime($paymentDate));

        // Create date for last paid month
        $lastDayOfLastMonth = (int) date('t', strtotime("$maxYear-$maxMonth-01"));
        if ($paymentDay > $lastDayOfLastMonth) {
            $paymentDay = $lastDayOfLastMonth;
        }

        $lastPaidFullDate = date('Y-m-d', strtotime("$maxYear-$maxMonth-$paymentDay"));

        // Add 1 month to get next isolation date
        $newIsolationDate = date('Y-m-d', strtotime($lastPaidFullDate . ' +1 month'));

        // Adjust if day changed (e.g., Jan 31 -> Feb 28)
        $newDay = (int) date('d', strtotime($newIsolationDate));
        if ($paymentDay != $newDay) {
            $newIsolationDate = date('Y-m-t', strtotime($newIsolationDate));
        }

        // Update customer's isolation_date
        $pdo->prepare("UPDATE customers SET isolation_date = ?, updated_at = ? WHERE id = ?")
                ->execute([$newIsolationDate, $paymentDateTime, $id]);

        // Update status to active
        if ($customer['status'] === 'isolated') {
            $pdo->prepare("UPDATE customers SET status = 'active' WHERE id = ?")
                    ->execute([$id]);
        }
        // ========== END UPDATE ISOLATION DATE ==========

        $pdo->commit();

        // Send Notification
        if (function_exists('sendWhatsApp') && !empty($customer['phone'])) {
            $msg = "Pembayaran Diterima\n\n";
            $msg .= "Nama: {$customer['name']}\n";
            $msg .= "Total: " . formatCurrency($totalBill) . "\n";
            $msg .= "Periode: $monthsCount bulan\n";
            $msg .= "Tgl. Pembayaran: " . date('d/m/Y', strtotime($paymentDate)) . "\n";
            $msg .= "Tgl. Isolasi Berikutnya: " . date('d/m/Y', strtotime($newIsolationDate)) . "\n";
            $msg .= "Via: Admin\n\n";
            $msg .= "Terima kasih.";
            if (function_exists('getWhatsAppFooter')) {
                $msg .= getWhatsAppFooter();
            }
            sendWhatsApp($customer['phone'], $msg);
        }

        logActivity('RENEW USER', "Customer ID: $id, Months: $monthsCount, Total: $totalBill, Next Isolation: $newIsolationDate");

        // Log sukses
        actionLog('PAYMENT_PROCESS_SUCCESS', $workdir, "Berhasil memproses pembayaran", json_encode([
                'customer_id' => $id,
                'months_count' => $monthsCount,
                'total_bill' => $totalBill,
                'new_isolation_date' => $newIsolationDate,
                'invoice_ids' => $generatedInvoiceIds
        ]));

        setFlash('success', "Pembayaran berhasil untuk $monthsCount bulan. Tanggal isolasi berikutnya: " . date('d/m/Y', strtotime($newIsolationDate)));

        $ids = implode(',', $generatedInvoiceIds);
        redirect("print_invoice.php?ids=$ids");

    } catch (Exception $e) {
        $pdo->rollBack();
        // Log error
        actionLog('PAYMENT_PROCESS_FAILED', $workdir, "Gagal memproses pembayaran", json_encode([
                'customer_id' => $id,
                'error' => $e->getMessage()
        ]));
        logError("GAGAL RENEW: Customer ID: $id, Error: " . $e->getMessage());
        setFlash('error', 'Gagal memproses pembayaran: ' . $e->getMessage());
        redirect("pay_process.php?id=$id&year=$selectedYear");
    }
}

// Prepare UI Data
$currentYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$monthsList = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Fetch paid invoices for selected year
$paidInvoices = fetchAll("SELECT MONTH(due_date) as month_num FROM invoices 
    WHERE customer_id = ? AND YEAR(due_date) = ? AND status = 'paid'", [$id, $currentYear]);
$paidMonths = array_column($paidInvoices, 'month_num');

// Get latest payment date
$latestPayment = getLatestPaymentDate($id);
$nextIsolation = $latestPayment ? calculateNextIsolationDate($id) : 'Belum ada pembayaran';

ob_start();
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user"></i> Detail Pelanggan</h3>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td width="100">Nama</td>
                        <td>: <strong><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Paket</td>
                        <td>: <?php echo htmlspecialchars($package['name']); ?></td>
                    </tr>
                    <tr>
                        <td>Harga</td>
                        <td>: <?php echo formatCurrency($package['price']); ?>/bln</td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td>: <?php echo htmlspecialchars($customer['address']); ?></td>
                    </tr>
                    <tr>
                        <td>Pembayaran Terakhir</td>
                        <td>: <strong><?php echo $latestPayment ? date('d/m/Y', strtotime($latestPayment)) : '-'; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Isolasi Berikutnya</td>
                        <td>: <strong class="text-warning">
                            <?php 
                            if ($nextIsolation && $nextIsolation != 'Belum ada pembayaran') {
                                echo date('d/m/Y', strtotime($nextIsolation));
                            } else {
                                echo '-';
                            }
                            ?>
                        </strong></td>
                    </tr>
                </table>
                <hr>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Informasi:</strong><br>
                    Tanggal isolasi berikutnya = Tanggal pembayaran terbaru + 1 bulan
                </div>
                <div class="alert alert-success">
                    Total Tagihan: <strong id="totalBillDisplay" style="font-size: 1.2em;">Rp 0</strong>
                </div>
                <button type="button" class="btn btn-success btn-block" onclick="confirmPayment()" style="width: 100%;">
                    <i class="fas fa-check-circle"></i> Proses Pembayaran
                </button>
            </div>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> Riwayat Pembayaran</h3>
            </div>
            <div class="card-body p-0">
                <?php
                $history = fetchAll("SELECT * FROM invoices WHERE customer_id = ? AND status = 'paid' ORDER BY paid_at DESC LIMIT 5", [$id]);
                ?>
                <?php if (empty($history)): ?>
                    <div style="padding: 15px;" class="text-muted">Belum ada riwayat.</div>
                <?php else: ?>
                    <table class="table table-striped mb-0">
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?php echo date('d/m/y', strtotime($h['paid_at'])); ?></td>
                                <td class="text-right text-success"><?php echo formatCurrency($h['amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Pilih Periode Pembayaran</h3>
                <form method="GET" class="float-right">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <select name="year" class="form-control" onchange="this.form.submit()" style="width: 100px;">
                        <?php 
                        $startYear = date('Y') - 1;
                        $endYear = date('Y') + 2;
                        for($y=$startYear; $y<=$endYear; $y++): 
                        ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
            <div class="card-body">
                <form method="POST" id="payForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="year" value="<?php echo $currentYear; ?>">
                    
                    <div class="months-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px;">
                        <?php foreach ($monthsList as $num => $name): ?>
                            <?php 
                                $isPaid = in_array($num, $paidMonths);
                            ?>
                            <div class="month-item" style="
                                border: 1px solid var(--border-color); 
                                border-radius: 8px; 
                                padding: 20px; 
                                text-align: center; 
                                cursor: pointer;
                                background: <?php echo $isPaid ? 'rgba(0, 255, 136, 0.1)' : 'var(--bg-input)'; ?>;
                                border-color: <?php echo $isPaid ? 'var(--neon-green)' : 'var(--border-color)'; ?>;
                                transition: all 0.2s;
                            " onclick="<?php echo $isPaid ? '' : "toggleMonth($num)"; ?>">
                                
                                <?php if ($isPaid): ?>
                                    <div style="color: var(--neon-green); font-size: 1.5rem; margin-bottom: 5px;">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h5 style="margin: 0; color: var(--text-muted); text-decoration: line-through;"><?php echo $name; ?></h5>
                                    <small style="color: var(--neon-green); font-weight: bold;">LUNAS</small>
                                <?php else: ?>
                                    <input type="checkbox" name="selected_months[]" value="<?php echo $num; ?>" id="month_<?php echo $num; ?>" style="display: none;" onchange="updateTotal()">
                                    <div id="icon_<?php echo $num; ?>" style="color: var(--text-secondary); font-size: 1.5rem; margin-bottom: 5px;">
                                        <i class="far fa-square"></i>
                                    </div>
                                    <h5 style="margin: 0; color: var(--text-primary);" id="label_<?php echo $num; ?>"><?php echo $name; ?></h5>
                                    <small style="color: var(--text-secondary);">Belum Bayar</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const pricePerMonth = <?php echo $package['price']; ?>;
    
    function toggleMonth(num) {
        const checkbox = document.getElementById('month_' + num);
        const div = checkbox.parentElement;
        const label = document.getElementById('label_' + num);
        const icon = document.getElementById('icon_' + num);
        
        checkbox.checked = !checkbox.checked;
        
        if (checkbox.checked) {
            div.style.backgroundColor = 'rgba(0, 245, 255, 0.2)';
            div.style.borderColor = 'var(--neon-cyan)';
            div.style.transform = 'scale(1.02)';
            div.style.boxShadow = '0 0 10px rgba(0, 245, 255, 0.2)';
            label.style.color = 'var(--neon-cyan)';
            icon.innerHTML = '<i class="fas fa-check-square"></i>';
            icon.style.color = 'var(--neon-cyan)';
        } else {
            div.style.backgroundColor = 'var(--bg-input)';
            div.style.borderColor = 'var(--border-color)';
            div.style.transform = 'scale(1)';
            div.style.boxShadow = 'none';
            label.style.color = 'var(--text-primary)';
            icon.innerHTML = '<i class="far fa-square"></i>';
            icon.style.color = 'var(--text-secondary)';
        }
        
        updateTotal();
    }
    
    function updateTotal() {
        const checkboxes = document.querySelectorAll('input[name="selected_months[]"]:checked');
        const count = checkboxes.length;
        const totalBill = pricePerMonth * count;
        
        const formatter = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
        
        document.getElementById('totalBillDisplay').innerText = formatter.format(totalBill);
    }

    function confirmPayment() {
        const checkboxes = document.querySelectorAll('input[name="selected_months[]"]:checked');
        if (checkboxes.length === 0) {
            alert('Pilih minimal 1 bulan untuk dibayar.');
            return;
        }
        
        if (confirm('Yakin ingin memproses pembayaran ini?')) {
            document.getElementById('payForm').submit();
        }
    }
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>