<?php
/**
 * Export Invoices to Excel
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Export Invoice';

function fetchInvoicesForExport()
{
    return fetchAll("
        SELECT 
            i.*, 
            c.name as customer_name,
            c.phone,
            c.pppoe_username,
            p.name as package_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN packages p ON c.package_id = p.id
        ORDER BY COALESCE(i.updated_at, i.created_at) DESC, i.id DESC
    ");
}

if (isset($_GET['action']) && $_GET['action'] === 'export_excel') {
    $invoices = fetchInvoicesForExport();

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="invoices_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<head><meta charset="UTF-8"></head>' . "\n";
    echo '<body>' . "\n";
    echo '<table border="1">' . "\n";

    $headers = [
        'ID',
        'No. Invoice',
        'Pelanggan',
        'No HP',
        'PPPoE Username',
        'Paket',
        'Jumlah',
        'Status',
        'Jatuh Tempo',
        'Paid At',
        'Payment Method',
        'Keterangan',
        'Created At',
        'Updated At'
    ];

    echo '<tr>' . "\n";
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>' . "\n";
    }
    echo '</tr>' . "\n";

    foreach ($invoices as $invoice) {
        echo '<tr>' . "\n";
        echo '<td>' . htmlspecialchars((string) ($invoice['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string) ($invoice['invoice_number'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string) ($invoice['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string) ($invoice['phone'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string) ($invoice['pppoe_username'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string) ($invoice['package_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string) number_format((float) ($invoice['amount'] ?? 0), 0, ',', '.'), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string) ($invoice['status'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars(($invoice['due_date'] ? date('d/m/Y H:i', strtotime($invoice['due_date'])) : ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars(($invoice['paid_at'] ? date('d/m/Y H:i', strtotime($invoice['paid_at'])) : ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string) ($invoice['payment_method'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars((string) ($invoice['description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars(($invoice['created_at'] ? date('d/m/Y H:i', strtotime($invoice['created_at'])) : ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars(($invoice['updated_at'] ? date('d/m/Y H:i', strtotime($invoice['updated_at'])) : ''), ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '</tr>' . "\n";
    }

    echo '</table>' . "\n";
    echo '</body></html>' . "\n";
    exit;
}

ob_start();
?>

<div style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-download"></i> Export Invoice</h3>
        </div>

        <p style="margin-bottom: 20px; color: var(--text-secondary);">
            Download data invoice dalam format Excel.
        </p>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="invoices.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <a href="?action=export_excel" class="btn btn-primary">
                <i class="fas fa-file-excel"></i> Download Excel
            </a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
