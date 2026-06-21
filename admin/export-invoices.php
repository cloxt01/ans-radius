<?php
/**
 * Export Invoices to Excel (Filter Aman + Perbaikan Format Tanggal)
 * Tampilan Native Anthropic Theme
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Export Invoice';
$workdir = 'admin/export-invoices.php';

// Helper: pastikan string tanggal valid & kembalikan format Y-m-d untuk input date
function formatDateForInput($dateString)
{
    if (empty($dateString)) return '';
    $timestamp = strtotime($dateString);
    return $timestamp ? date('Y-m-d', $timestamp) : '';
}

// Helper: cek apakah tanggal valid (format Y-m-d setelah parsing)
function isValidDate($dateString)
{
    if (empty($dateString)) return true;
    $d = DateTime::createFromFormat('Y-m-d', $dateString);
    return $d && $d->format('Y-m-d') === $dateString;
}

/**
 * Fetch invoices dengan filter
 */
function fetchInvoicesForExport(array $filters = [])
{
    $sql = "
        SELECT 
            i.*, 
            c.name AS customer_name,
            c.phone,
            c.pppoe_username,
            p.name AS package_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN packages p ON c.package_id = p.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filters['status'])) {
        $sql .= " AND i.status = ?";
        $params[] = $filters['status'];
    }
    if (!empty($filters['date_from'])) {
        $sql .= " AND i.due_date >= ?";
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND i.due_date <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    if (!empty($filters['paid_date_from'])) {
        $sql .= " AND i.paid_at >= ?";
        $params[] = $filters['paid_date_from'] . ' 00:00:00';
    }
    if (!empty($filters['paid_date_to'])) {
        $sql .= " AND i.paid_at <= ?";
        $params[] = $filters['paid_date_to'] . ' 23:59:59';
    }
    if (!empty($filters['customer_name'])) {
        $sql .= " AND c.name LIKE ?";
        $params[] = '%' . $filters['customer_name'] . '%';
    }
    if (!empty($filters['package_id'])) {
        $sql .= " AND c.package_id = ?";
        $params[] = (int) $filters['package_id'];
    }

    $sql .= " ORDER BY COALESCE(i.updated_at, i.created_at) DESC, i.id DESC";
    return fetchAll($sql, $params);
}

// --- HANDLE EXPORT REQUEST ---
if (isset($_GET['action']) && $_GET['action'] === 'export_excel') {
    // Log awal percobaan export
    $logFilters = array_filter([
            'status'          => $_GET['status'] ?? null,
            'date_from'       => $_GET['date_from'] ?? null,
            'date_to'         => $_GET['date_to'] ?? null,
            'paid_date_from'  => $_GET['paid_date_from'] ?? null,
            'paid_date_to'    => $_GET['paid_date_to'] ?? null,
            'customer_name'   => $_GET['customer_name'] ?? null,
            'package_id'      => $_GET['package_id'] ?? null,
    ]);
    AppLog('EXPORT_INVOICES_ATTEMPT', $workdir, "Mencoba export invoice ke Excel", json_encode($logFilters));

    // Validasi tanggal dari URL
    $errors = [];
    $dateFields = ['date_from', 'date_to', 'paid_date_from', 'paid_date_to'];
    foreach ($dateFields as $field) {
        if (!empty($_GET[$field]) && !isValidDate($_GET[$field])) {
            $errors[] = "Format tanggal untuk " . ucfirst(str_replace('_', ' ', $field)) . " tidak valid.";
        }
    }

    if (!empty($errors)) {
        AppLog('EXPORT_INVOICES_FAILED', $workdir, "Validasi tanggal gagal", json_encode(['errors' => $errors, 'filters' => $logFilters]));
        setFlash('error', implode('<br>', $errors));
        header('Location: export-invoices.php?' . http_build_query(array_diff_key($_GET, ['action' => ''])));
        exit;
    }

    $filters = [
            'status'          => $_GET['status'] ?? null,
            'date_from'       => $_GET['date_from'] ?? null,
            'date_to'         => $_GET['date_to'] ?? null,
            'paid_date_from'  => $_GET['paid_date_from'] ?? null,
            'paid_date_to'    => $_GET['paid_date_to'] ?? null,
            'customer_name'   => $_GET['customer_name'] ?? null,
            'package_id'      => $_GET['package_id'] ?? null,
    ];
    $invoices = fetchInvoicesForExport($filters);
    $count = count($invoices);

    // Log sukses sebelum output
    AppLog('EXPORT_INVOICES_SUCCESS', $workdir, "Export invoice berhasil", json_encode([
            'total_invoices' => $count,
            'filters' => $filters
    ]));
    logActivity('EXPORT_INVOICES', "Exported {$count} invoices");

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="invoices_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<head><meta charset="UTF-8"></head>' . "\n";
    echo '<body>' . "\n";
    echo '<table border="1">' . "\n";

    $headers = [
            'ID', 'No. Invoice', 'Pelanggan', 'No HP', 'PPPoE Username',
            'Paket', 'Jumlah', 'Status', 'Jatuh Tempo', 'Paid At',
            'Payment Method', 'Keterangan', 'Created At', 'Updated At'
    ];

    echo '<tr>' . "\n";
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>' . "\n";
    }
    echo '</tr>' . "\n";

    foreach ($invoices as $invoice) {
        echo '<tr>' . "\n";
        echo '<td>' . htmlspecialchars((string)($invoice['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string)($invoice['invoice_number'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string)($invoice['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string)($invoice['phone'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string)($invoice['pppoe_username'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string)($invoice['package_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string) number_format((float)($invoice['amount'] ?? 0), 0, ',', '.'), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string)($invoice['status'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars(($invoice['due_date'] ? date('d/m/Y H:i', strtotime($invoice['due_date'])) : ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars(($invoice['paid_at'] ? date('d/m/Y H:i', strtotime($invoice['paid_at'])) : ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string)($invoice['payment_method'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string)($invoice['description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars(($invoice['created_at'] ? date('d/m/Y H:i', strtotime($invoice['created_at'])) : ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars(($invoice['updated_at'] ? date('d/m/Y H:i', strtotime($invoice['updated_at'])) : ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '</tr>' . "\n";
    }

    echo '</table>' . "\n";
    echo '</body></html>' . "\n";
    exit;
}

// --- TAMPILAN HALAMAN ---
ob_start();


$packages = fetchAll("SELECT id, name FROM packages ORDER BY name");

// Format semua input date ke Y-m-d agar aman
$dateFrom       = formatDateForInput($_GET['date_from'] ?? '');
$dateTo         = formatDateForInput($_GET['date_to'] ?? '');
$paidDateFrom   = formatDateForInput($_GET['paid_date_from'] ?? '');
$paidDateTo     = formatDateForInput($_GET['paid_date_to'] ?? '');

// Cek apakah filter aktif (untuk menampilkan pratinjau)
$filterActive = !empty($_GET['status']) || !empty($dateFrom) || !empty($dateTo)
        || !empty($paidDateFrom) || !empty($paidDateTo)
        || !empty($_GET['customer_name']) || !empty($_GET['package_id']);
?>
    <div style="text-align: center; margin-bottom: 40px; position: relative;">

        <!-- Partikel latar (dekorasi) -->
        <div style="position: absolute; top: 10%; left: 50%; transform: translateX(-50%); width: 400px; height: 200px; pointer-events: none; opacity: 0.08;">
            <div style="position: absolute; top: 20px; left: 10%; width: 8px; height: 8px; background: var(--color-accent-blue); border-radius: 50%; animation: floatParticle 3s infinite ease-in-out;"></div>
            <div style="position: absolute; bottom: 30px; right: 15%; width: 12px; height: 12px; background: var(--color-accent-cyan); border-radius: 50%; animation: floatParticle 4s 0.5s infinite ease-in-out;"></div>
            <div style="position: absolute; top: 60px; right: 25%; width: 6px; height: 6px; background: var(--color-accent-green); border-radius: 50%; animation: floatParticle 3.5s 1s infinite ease-in-out;"></div>
        </div>

        <!-- Garis koneksi putus-putus (SVG) -->
        <svg width="100%" height="120" style="position: absolute; top: 10px; left: 0; pointer-events: none; opacity: 0.15;">
            <line x1="50%" y1="60" x2="40%" y2="60" stroke="var(--color-accent-blue)" stroke-width="2" stroke-dasharray="6,4" transform="translate(-120, 0)" />
            <line x1="50%" y1="60" x2="60%" y2="60" stroke="var(--color-accent-green)" stroke-width="2" stroke-dasharray="6,4" transform="translate(120, 0)" />
            <!-- Titik-titik di ujung -->
            <circle cx="calc(50% - 120px)" cy="60" r="4" fill="var(--color-accent-blue)" />
            <circle cx="calc(50% + 120px)" cy="60" r="4" fill="var(--color-accent-green)" />
        </svg>

        <!-- Konten utama -->
        <div style="display: flex; align-items: center; justify-content: center; gap: 32px; padding: 20px 0;">

            <!-- ╔══ BLOK DATABASE BERTUMPUK ════════════════ -->
            <div style="position: relative; width: 90px; height: 90px; perspective: 200px;">
                <!-- Bayangan dasar -->
                <div style="position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%); width: 70px; height: 10px; background: rgba(0,0,0,0.5); border-radius: 50%; filter: blur(6px);"></div>

                <!-- Balok paling belakang -->
                <div style="position: absolute; top: 0; left: 8px; width: 74px; height: 74px;
                        background: linear-gradient(135deg, rgba(88,166,255,0.25), rgba(88,166,255,0.05));
                        border: 1px solid rgba(88,166,255,0.25);
                        border-radius: 10px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                        transform: rotateX(5deg) rotateY(-5deg);
                        display: flex; align-items: center; justify-content: center; font-size: 26px;
                        color: var(--color-accent-blue); opacity: 0.5;
                        animation: stackFloating 4s infinite ease-in-out;">
                    <i class="fas fa-database"></i>
                </div>

                <!-- Balok tengah -->
                <div style="position: absolute; top: 6px; left: 4px; width: 74px; height: 74px;
                        background: linear-gradient(135deg, rgba(88,166,255,0.35), rgba(88,166,255,0.1));
                        border: 1px solid rgba(88,166,255,0.4);
                        border-radius: 10px;
                        box-shadow: 0 6px 16px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.1);
                        transform: rotateX(2deg) rotateY(-2deg);
                        display: flex; align-items: center; justify-content: center; font-size: 26px;
                        color: var(--color-accent-blue); opacity: 0.8;
                        animation: stackFloating 4s 0.2s infinite ease-in-out;">
                    <i class="fas fa-database"></i>
                </div>

                <!-- Balok depan (fokus) -->
                <div style="position: absolute; top: 12px; left: 0; width: 74px; height: 74px;
                        background: linear-gradient(135deg, rgba(88,166,255,0.45), rgba(88,166,255,0.15));
                        border: 1px solid rgba(88,166,255,0.6);
                        border-radius: 10px;
                        box-shadow: 0 8px 24px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.15);
                        display: flex; align-items: center; justify-content: center; font-size: 26px;
                        color: var(--color-accent-blue);
                        animation: stackFloating 4s 0.4s infinite ease-in-out;
                        z-index: 2;">
                    <i class="fas fa-database"></i>
                </div>

                <!-- Glow effect -->
                <div style="position: absolute; top: 12px; left: 0; width: 74px; height: 74px;
                        border-radius: 10px;
                        box-shadow: 0 0 25px rgba(88,166,255,0.3);
                        z-index: 1; pointer-events: none;"></div>
            </div>

            <!-- ╔══ PANAH ANIMASI ════════════════════════════ -->
            <div style="position: relative;">
                <!-- Panah utama -->
                <div style="font-size: 3.5rem; color: var(--color-accent-blue);
                        filter: drop-shadow(0 0 8px rgba(88,166,255,0.4));
                        animation: arrowPulse 1.8s infinite ease-in-out;">
                    <i class="fas fa-arrow-right"></i>
                </div>
                <!-- Trail panah -->
                <div style="position: absolute; top: 50%; left: -10px; transform: translateY(-50%);
                        width: 40px; height: 2px; background: linear-gradient(to right, transparent, rgba(88,166,255,0.4));
                        opacity: 0.5; animation: arrowTrail 1.8s infinite ease-in-out;"></div>
            </div>

            <!-- ╔══ OUTPUT EXCEL ════════════════════════════ -->
            <div style="position: relative; width: 90px; height: 90px;">
                <!-- Bayangan -->
                <div style="position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%); width: 60px; height: 10px; background: rgba(0,0,0,0.5); border-radius: 50%; filter: blur(6px);"></div>

                <!-- Card Excel -->
                <div style="width: 80px; height: 80px; margin: 5px auto;
                        background: linear-gradient(135deg, rgba(16,185,129,0.35), rgba(16,185,129,0.1));
                        border: 1px solid rgba(16,185,129,0.5);
                        border-radius: 12px;
                        box-shadow: 0 8px 24px rgba(16,185,129,0.2), 0 0 30px rgba(16,185,129,0.1);
                        display: flex; align-items: center; justify-content: center;
                        font-size: 32px; color: var(--color-accent-green);
                        transform: perspective(400px) rotateY(-5deg);
                        transition: transform 0.3s ease;
                        animation: excelGlow 3s infinite ease-in-out;">
                    <i class="fas fa-file-excel"></i>
                </div>
            </div>
        </div>

        <!-- Teks deskripsi -->
        <p style="margin-top: 20px; color: var(--color-text-secondary); font-size: 14px; font-weight: 500; letter-spacing: 0.3px;">
            Pilih filter untuk mengekspor data invoice ke <span style="color: var(--color-accent-green); font-weight: 600;">Excel</span>
        </p>
    </div>

    <!-- Animasi CSS (tambahkan di <style> di halaman atau layout) -->
    <style>
        @keyframes floatParticle {
            0%, 100% { transform: translateY(0) scale(1); opacity: 0.6; }
            50% { transform: translateY(-15px) scale(1.5); opacity: 1; }
        }
        @keyframes stackFloating {
            0%, 100% { transform: translateY(0) rotateX(2deg) rotateY(-2deg); }
            50% { transform: translateY(-4px) rotateX(1deg) rotateY(0deg); }
        }
        @keyframes arrowPulse {
            0%, 100% { transform: translateX(0); opacity: 1; }
            50% { transform: translateX(6px); opacity: 0.8; }
        }
        @keyframes arrowTrail {
            0%, 100% { width: 20px; opacity: 0.2; }
            50% { width: 60px; opacity: 0.6; }
        }
        @keyframes excelGlow {
            0%, 100% { box-shadow: 0 8px 24px rgba(16,185,129,0.2), 0 0 30px rgba(16,185,129,0.1); }
            50% { box-shadow: 0 12px 32px rgba(16,185,129,0.35), 0 0 50px rgba(16,185,129,0.2); }
        }
    </style>
    <!-- ═══════════════════════════════════════════════════ -->

    <div style="max-width: 900px; margin: 0 auto;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-export"></i> Export Invoice</h3>
            </div>
            <div class="card-body">
                <p style="margin-bottom: 20px; color: var(--color-text-secondary);">
                    Filter data invoice yang ingin di-export. Gunakan format <strong>YYYY-MM-DD</strong> (contoh: 2026-01-31).
                    Klik <strong>Download Excel</strong> setelah memilih filter.
                </p>

                <!-- FORM FILTER -->
                <form method="get" style="margin-bottom: 24px;">
                    <input type="hidden" name="action" value="export_excel">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <!-- Status -->
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">Semua</option>
                                <option value="paid" <?= ($_GET['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Lunas</option>
                                <option value="unpaid" <?= ($_GET['status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>Belum Bayar</option>
                            </select>
                        </div>

                        <!-- Paket -->
                        <div class="form-group">
                            <label class="form-label">Paket</label>
                            <select name="package_id" class="form-control">
                                <option value="">Semua Paket</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?= $pkg['id'] ?>" <?= ($_GET['package_id'] ?? '') == $pkg['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pkg['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Jatuh Tempo Dari -->
                        <div class="form-group">
                            <label class="form-label">Jatuh Tempo Dari <span style="font-weight: normal; color: var(--color-text-muted);">(YYYY-MM-DD)</span></label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-control" placeholder="2026-01-01">
                        </div>

                        <!-- Jatuh Tempo Sampai -->
                        <div class="form-group">
                            <label class="form-label">Jatuh Tempo Sampai <span style="font-weight: normal; color: var(--color-text-muted);">(YYYY-MM-DD)</span></label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="form-control" placeholder="2026-12-31">
                        </div>

                        <!-- Tanggal Bayar Dari -->
                        <div class="form-group">
                            <label class="form-label">Tanggal Bayar Dari <span style="font-weight: normal; color: var(--color-text-muted);">(YYYY-MM-DD)</span></label>
                            <input type="date" name="paid_date_from" value="<?= htmlspecialchars($paidDateFrom) ?>" class="form-control" placeholder="2026-01-01">
                        </div>

                        <!-- Tanggal Bayar Sampai -->
                        <div class="form-group">
                            <label class="form-label">Tanggal Bayar Sampai <span style="font-weight: normal; color: var(--color-text-muted);">(YYYY-MM-DD)</span></label>
                            <input type="date" name="paid_date_to" value="<?= htmlspecialchars($paidDateTo) ?>" class="form-control" placeholder="2026-12-31">
                        </div>

                        <!-- Nama Pelanggan -->
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Nama Pelanggan</label>
                            <input type="text" name="customer_name" value="<?= htmlspecialchars($_GET['customer_name'] ?? '') ?>" class="form-control" placeholder="Cari nama...">
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 8px; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-file-excel"></i> Download Excel</button>
                        <a href="invoices.php" class="btn"><i class="fas fa-arrow-left"></i> Kembali</a>
                        <a href="export-invoices.php" class="btn"><i class="fas fa-sync-alt"></i> Reset Filter</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- PRATINJAU DATA -->
        <?php if ($filterActive): ?>
            <?php
            $previewFilters = [
                    'status'          => $_GET['status'] ?? null,
                    'date_from'       => $dateFrom,
                    'date_to'         => $dateTo,
                    'paid_date_from'  => $paidDateFrom,
                    'paid_date_to'    => $paidDateTo,
                    'customer_name'   => $_GET['customer_name'] ?? null,
                    'package_id'      => $_GET['package_id'] ?? null,
            ];
            $previewInvoices = fetchInvoicesForExport($previewFilters);
            ?>
            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-eye"></i> Pratinjau Data (<?= count($previewInvoices) ?> invoice)</h3>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>No. Invoice</th>
                            <th>Pelanggan</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Jatuh Tempo</th>
                            <th>Tgl Bayar</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($previewInvoices as $inv): ?>
                            <tr>
                                <td><span class="code-pill"><?= htmlspecialchars($inv['invoice_number']) ?></span></td>
                                <td><?= htmlspecialchars($inv['customer_name'] ?? '-') ?></td>
                                <td><?= formatCurrency($inv['amount']) ?></td>
                                <td>
                                    <span class="badge <?= $inv['status'] === 'paid' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= $inv['status'] === 'paid' ? 'Lunas' : 'Belum Bayar' ?>
                                    </span>
                                </td>
                                <td><?= $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '-' ?></td>
                                <td><?= $inv['paid_at'] ? date('d/m/Y', strtotime($inv['paid_at'])) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($previewInvoices)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 24px; color: var(--color-text-muted);">
                                    Tidak ada data ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';