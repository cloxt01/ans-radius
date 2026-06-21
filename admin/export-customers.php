<?php
/**
 * Export Customers to Excel/CSV
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Export Pelanggan';
$workdir = 'admin/export-customers.php';
// ==================== EXPORT EXCEL ====================
if (isset($_GET['action']) && $_GET['action'] === 'export_excel') {
    AppLog('EXPORT_CUSTOMERS_EXCEL_ATTEMPT', $workdir, "Mencoba export pelanggan ke Excel", json_encode([]));

    try {
        $customers = fetchAll("
            SELECT 
                c.id,
                c.name,
                c.phone,
                c.pppoe_username,
                (SELECT paid_at FROM invoices WHERE customer_id = c.id AND status = 'paid' ORDER BY paid_at DESC LIMIT 1) as paid_at,
                c.package_id,
                p.name as package_name,
                p.price as package_price,
                c.status,
                c.isolation_date,
                c.address,
                c.lat,
                c.lng,
                c.created_at,
                c.updated_at
            FROM customers c
            LEFT JOIN packages p ON c.package_id = p.id
            ORDER BY c.created_at DESC
        ");

        $count = count($customers);
        AppLog('EXPORT_CUSTOMERS_EXCEL_SUCCESS', $workdir, "Export pelanggan ke Excel berhasil", json_encode(['total_customers' => $count]));
        logActivity('EXPORT_CUSTOMERS_EXCEL', "Exported {$count} customers");

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d_H-i-s') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        echo '<Worksheet ss:Name="Pelanggan">' . "\n";
        echo '<Table>' . "\n";

        // Header row
        echo '<Row>' . "\n";
        $headers = ['ID', 'Nama', 'No HP', 'PPPoE Username', 'Last Paid', 'Paket', 'Status', 'Register Date', 'Tgl Isolir', 'Alamat', 'Latitude', 'Longitude'];
        foreach ($headers as $header) {
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>' . "\n";
        }
        echo '</Row>' . "\n";

        // Data rows
        foreach ($customers as $customer) {
            echo '<Row>' . "\n";
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['id']) . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['name']) . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['phone']) . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['pppoe_username']) . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . ($customer['paid_at'] ? date('d/m/Y H:i:s', strtotime($customer['paid_at'])) : '') . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['package_name'] ?? 'Tanpa Paket') . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . ($customer['status'] == 'active' ? 'Aktif' : 'Isolir') . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . ($customer['created_at'] ? date('d/m/Y H:i:s', strtotime($customer['created_at'])) : 'N/A') . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . $customer['isolation_date'] . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['address'] ?? '') . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . ($customer['lat'] ?? '') . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . ($customer['lng'] ?? '') . '</Data></Cell>' . "\n";
            echo '</Row>' . "\n";
        }

        echo '</Table>' . "\n";
        echo '</Worksheet>' . "\n";
        echo '</Workbook>' . "\n";
        exit;

    } catch (Exception $e) {
        AppLog('EXPORT_CUSTOMERS_EXCEL_FAILED', $workdir, "Gagal export pelanggan ke Excel", json_encode(['error' => $e->getMessage()]));
        setFlash('error', 'Gagal export Excel: ' . $e->getMessage());
        header('Location: customers.php');
        exit;
    }
}

// ==================== EXPORT CSV ====================
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    AppLog('EXPORT_CUSTOMERS_CSV_ATTEMPT', $workdir, "Mencoba export pelanggan ke CSV", json_encode([]));

    try {
        $customers = fetchAll("
            SELECT 
                c.id,
                c.name,
                c.phone,
                c.pppoe_username,
                (SELECT paid_at FROM invoices WHERE customer_id = c.id AND status = 'paid' ORDER BY paid_at DESC LIMIT 1) as paid_at,
                c.package_id,
                p.name as package_name,
                p.price as package_price,
                c.status,
                c.isolation_date,
                c.address,
                c.lat,
                c.lng,
                c.created_at,
                c.updated_at
            FROM customers c
            LEFT JOIN packages p ON c.package_id = p.id
            ORDER BY c.created_at DESC
        ");

        $count = count($customers);
        AppLog('EXPORT_CUSTOMERS_CSV_SUCCESS', $workdir, "Export pelanggan ke CSV berhasil", json_encode(['total_customers' => $count]));
        logActivity('EXPORT_CUSTOMERS_CSV', "Exported {$count} customers");

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Write CSV header
        fputcsv($output, [
                'ID',
                'Nama',
                'No HP',
                'PPPoE Username',
                'Paket',
                'Last Paid',
                'Status',
                'Register Date',
                'Tgl Isolir',
                'Alamat',
                'Latitude',
                'Longitude'
        ]);

        // Write data rows
        foreach ($customers as $customer) {
            fputcsv($output, [
                    $customer['id'],
                    $customer['name'],
                    $customer['phone'],
                    $customer['pppoe_username'],
                    $customer['package_name'] ?? 'Tanpa Paket',
                    $customer['paid_at'] ? date('d M Y', strtotime($customer['paid_at'])) : '',
                    $customer['status'] == 'active' ? 'Aktif' : 'Isolir',
                    $customer['created_at'] ? date('d M Y', strtotime($customer['created_at'])) : 'N/A',
                    $customer['isolation_date'],
                    $customer['address'] ?? '',
                    $customer['lat'] ?? '',
                    $customer['lng'] ?? '',
            ]);
        }

        fclose($output);
        exit;

    } catch (Exception $e) {
        AppLog('EXPORT_CUSTOMERS_CSV_FAILED', $workdir, "Gagal export pelanggan ke CSV", json_encode(['error' => $e->getMessage()]));
        setFlash('error', 'Gagal export CSV: ' . $e->getMessage());
        header('Location: customers.php');
        exit;
    }
}

ob_start();
?>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- HERO: Database Pelanggan → Export / Import              -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div style="text-align: center; margin-bottom: 40px;">
        <!-- Partikel latar -->
        <div style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 400px; height: 200px; pointer-events: none; opacity: 0.06;">
            <div style="position: absolute; top: 10px; left: 10%; width: 8px; height: 8px; background: var(--color-accent-blue); border-radius: 50%; animation: floatParticle 3s infinite ease-in-out;"></div>
            <div style="position: absolute; bottom: 20px; right: 15%; width: 10px; height: 10px; background: var(--color-accent-purple); border-radius: 50%; animation: floatParticle 4s 0.5s infinite ease-in-out;"></div>
            <div style="position: absolute; top: 40%; right: 25%; width: 6px; height: 6px; background: var(--color-accent-green); border-radius: 50%; animation: floatParticle 3.5s 1s infinite ease-in-out;"></div>
        </div>

        <!-- Konten Hero -->
        <div style="display: flex; align-items: center; justify-content: center; gap: 24px; padding: 20px 0;">
            <!-- Tumpukan Kartu Pelanggan (database) -->
            <div style="position: relative; width: 90px; height: 90px; perspective: 200px;">
                <div style="position: absolute; bottom: -6px; left: 50%; transform: translateX(-50%); width: 60px; height: 8px; background: rgba(0,0,0,0.5); border-radius: 50%; filter: blur(5px);"></div>
                <!-- Lapis belakang -->
                <div style="position: absolute; top: 0; left: 8px; width: 72px; height: 72px; background: linear-gradient(135deg, rgba(139,92,246,0.25), rgba(139,92,246,0.05)); border: 1px solid rgba(139,92,246,0.25); border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); transform: rotateX(5deg) rotateY(-5deg); display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--color-accent-purple); opacity: 0.6; animation: stackFloating 4s infinite ease-in-out;">
                    <i class="fas fa-users"></i>
                </div>
                <!-- Lapis tengah -->
                <div style="position: absolute; top: 5px; left: 4px; width: 72px; height: 72px; background: linear-gradient(135deg, rgba(139,92,246,0.35), rgba(139,92,246,0.1)); border: 1px solid rgba(139,92,246,0.4); border-radius: 10px; box-shadow: 0 6px 16px rgba(0,0,0,0.4); transform: rotateX(2deg) rotateY(-2deg); display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--color-accent-purple); opacity: 0.8; animation: stackFloating 4s 0.2s infinite ease-in-out;">
                    <i class="fas fa-address-card"></i>
                </div>
                <!-- Lapis depan -->
                <div style="position: absolute; top: 10px; left: 0; width: 72px; height: 72px; background: linear-gradient(135deg, rgba(139,92,246,0.45), rgba(139,92,246,0.15)); border: 1px solid rgba(139,92,246,0.6); border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--color-accent-purple); z-index: 2; animation: stackFloating 4s 0.4s infinite ease-in-out;">
                    <i class="fas fa-database"></i>
                </div>
            </div>

            <!-- Panah animasi -->
            <div style="font-size: 3rem; color: var(--color-accent-blue); filter: drop-shadow(0 0 6px rgba(88,166,255,0.4)); animation: arrowPulse 1.8s infinite ease-in-out;">
                <i class="fas fa-arrow-right"></i>
            </div>

            <!-- Output: Excel & CSV -->
            <div style="display: flex; gap: 12px;">
                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, rgba(16,185,129,0.35), rgba(16,185,129,0.1)); border: 1px solid rgba(16,185,129,0.5); border-radius: 12px; box-shadow: 0 8px 24px rgba(16,185,129,0.15); display: flex; align-items: center; justify-content: center; font-size: 28px; color: var(--color-accent-green); animation: excelGlow 3s infinite ease-in-out;">
                    <i class="fas fa-file-excel"></i>
                </div>
                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, rgba(6,182,212,0.35), rgba(6,182,212,0.1)); border: 1px solid rgba(6,182,212,0.5); border-radius: 12px; box-shadow: 0 8px 24px rgba(6,182,212,0.15); display: flex; align-items: center; justify-content: center; font-size: 28px; color: var(--color-accent-cyan); animation: excelGlow 3s 0.5s infinite ease-in-out;">
                    <i class="fas fa-file-csv"></i>
                </div>
            </div>
        </div>
        <p style="margin-top: 12px; color: var(--color-text-secondary); font-size: 14px; font-weight: 500;">
            Export & Import data pelanggan dengan mudah
        </p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- CARD: Export Pelanggan                                   -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="card" style="max-width: 900px; margin: 0 auto 20px auto;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-download"></i> Export Pelanggan</h3>
        </div>
        <div class="card-body">
            <p style="margin-bottom: 20px; color: var(--color-text-secondary);">
                Download data pelanggan dalam format Excel atau CSV.
            </p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="customers.php" class="btn"><i class="fas fa-arrow-left"></i> Kembali</a>
                <a href="?action=export_excel" class="btn btn-primary"><i class="fas fa-file-excel"></i> Download Excel</a>
                <a href="?action=export_csv" class="btn btn-success"><i class="fas fa-file-csv"></i> Download CSV</a>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- CARD: Import Pelanggan                                   -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="card" style="max-width: 900px; margin: 0 auto 20px auto;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-upload"></i> Import Pelanggan</h3>
        </div>
        <div class="card-body">
            <p style="margin-bottom: 20px; color: var(--color-text-secondary);">
                Upload file Excel atau CSV untuk import pelanggan secara massal.
            </p>
            <form id="importForm" method="POST" enctype="multipart/form-data" style="margin-bottom: 0;">
                <div class="form-group">
                    <label class="form-label">Pilih File (Excel/CSV)</label>
                    <input type="file" id="importFile" name="importFile" class="form-control" accept=".csv,.xls,.xlsx" required>
                    <small style="color: var(--color-text-muted);">Format yang didukung: CSV, XLS, XLSX</small>
                </div>
                <!-- Progress Bar (hidden by default) -->
                <div id="importProgress" style="display: none; margin-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; color: var(--color-accent-blue);">
                        <div style="width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--color-border-secondary); border-top-color: var(--color-accent-blue); animation: spin 0.8s linear infinite;"></div>
                        <span>Sedang mengimport...</span>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload & Import</button>
                    <a href="?action=export_excel" class="btn"><i class="fas fa-download"></i> Download Template</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- CARD: Format File                                        -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="card" style="max-width: 900px; margin: 0 auto 20px auto;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-info-circle"></i> Format File</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <p style="padding: 20px 20px 0 20px; color: var(--color-text-secondary);">
                File harus memiliki kolom-kolom berikut (baris pertama sebagai header):
            </p>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Kolom</th>
                        <th>Deskripsi</th>
                        <th>Contoh</th>
                        <th>Wajib</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>Nama</td>
                        <td>Nama lengkap pelanggan</td>
                        <td>John Doe</td>
                        <td><span class="badge badge-success">Ya</span></td>
                    </tr>
                    <tr>
                        <td>No HP</td>
                        <td>Nomor WhatsApp</td>
                        <td>08123456789</td>
                        <td><span class="badge badge-success">Ya</span></td>
                    </tr>
                    <tr>
                        <td>PPPoE Username</td>
                        <td>Username PPPoE di MikroTik</td>
                        <td>pelanggan01</td>
                        <td><span class="badge badge-success">Ya</span></td>
                    </tr>
                    <tr>
                        <td>Paket</td>
                        <td>Nama paket (harus sama dengan di sistem)</td>
                        <td>Paket 10 Mbps</td>
                        <td><span class="badge badge-success">Ya</span></td>
                    </tr>
                    <tr>
                        <td>Status</td>
                        <td>Status pelanggan (Aktif / Isolir)</td>
                        <td>Aktif</td>
                        <td><span class="badge badge-info">Opsional</span></td>
                    </tr>
                    <tr>
                        <td>Register Date</td>
                        <td>Tanggal registrasi (YYYY-MM-DD)</td>
                        <td>2024-06-01</td>
                        <td><span class="badge badge-info">Opsional</span></td>
                    </tr>
                    <tr>
                        <td>Tgl Isolir</td>
                        <td>Tanggal isolir (1-28)</td>
                        <td>20</td>
                        <td><span class="badge badge-info">Opsional</span></td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td>Alamat lengkap</td>
                        <td>Jl. Contoh No. 123</td>
                        <td><span class="badge badge-info">Opsional</span></td>
                    </tr>
                    <tr>
                        <td>Latitude</td>
                        <td>Titik koordinat</td>
                        <td>-6.200000</td>
                        <td><span class="badge badge-info">Opsional</span></td>
                    </tr>
                    <tr>
                        <td>Longitude</td>
                        <td>Titik koordinat</td>
                        <td>106.816666</td>
                        <td><span class="badge badge-info">Opsional</span></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- ANIMASI & SCRIPT                                         -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <style>
        @keyframes floatParticle {
            0%, 100% { transform: translateY(0) scale(1); opacity: 0.6; }
            50% { transform: translateY(-12px) scale(1.3); opacity: 1; }
        }
        @keyframes stackFloating {
            0%, 100% { transform: translateY(0) rotateX(2deg) rotateY(-2deg); }
            50% { transform: translateY(-3px) rotateX(1deg) rotateY(0deg); }
        }
        @keyframes arrowPulse {
            0%, 100% { transform: translateX(0); opacity: 1; }
            50% { transform: translateX(5px); opacity: 0.8; }
        }
        @keyframes excelGlow {
            0%, 100% { box-shadow: 0 8px 24px rgba(16,185,129,0.15); }
            50% { box-shadow: 0 12px 32px rgba(16,185,129,0.3); }
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const importForm = document.getElementById('importForm');
            const fileInput = document.getElementById('importFile');
            const progressDiv = document.getElementById('importProgress');

            importForm.addEventListener('submit', function(e) {
                e.preventDefault();

                if (fileInput.files.length === 0) {
                    alert('Silakan pilih file terlebih dahulu!');
                    return;
                }

                const file = fileInput.files[0];
                const validTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'];
                if (!validTypes.includes(file.type) && !file.name.match(/\.(csv|xls|xlsx)$/i)) {
                    alert('Format file tidak didukung! Gunakan CSV, XLS, atau XLSX.');
                    return;
                }

                if (confirm('Anda yakin ingin mengimport data ini?')) {
                    const formData = new FormData(importForm);
                    progressDiv.style.display = 'block';

                    fetch('import.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            progressDiv.style.display = 'none';
                            if (data.success) {
                                alert('Import berhasil! ' + data.message);
                                location.reload();
                            } else {
                                alert('Import gagal: ' + data.message);
                            }
                        })
                        .catch(error => {
                            progressDiv.style.display = 'none';
                            alert('Terjadi kesalahan: ' + error.message);
                        });
                }
            });
        });
    </script>
<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
