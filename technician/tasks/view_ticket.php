<?php
ini_set('memory_limit', '256M');
require_once '../../includes/auth.php';
requireTechnicianLogin();

$tech = $_SESSION['technician'];
$ticketId = $_GET['id'] ?? 0;

// Fetch Ticket Detail
$ticket = fetchOne("
    SELECT t.*, c.name as customer_name, c.address, c.phone, c.lat, c.lng 
    FROM trouble_tickets t 
    LEFT JOIN customers c ON t.customer_id = c.id 
    WHERE t.id = ? AND t.technician_id = ?
", [$ticketId, $tech['id']]);

if (!$ticket) {
    setFlash('error', 'Tiket tidak ditemukan atau bukan tugas Anda.');
    redirect('index.php');
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $notes  = trim($_POST['notes'] ?? '');
    $photoPath = $ticket['photo_proof'];

    if (empty($status)) {
        setFlash('error', 'Status wajib dipilih.');
        redirect("view_ticket.php?id=$ticketId");
    }

    if ($status === 'resolved') {

        if (empty($notes)) {
            setFlash('error', 'Catatan penyelesaian wajib diisi!');
            redirect("view_ticket.php?id=$ticketId");
        }

        // Handle Photo Upload
        $hasNewPhoto  = !empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK;
        $hasOldPhoto  = !empty($ticket['photo_proof']);

        if ($hasNewPhoto) {
            $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
            $filename = $_FILES['photo']['name'];
            $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                setFlash('error', 'Format foto harus JPG/PNG/WEBP.');
                redirect("view_ticket.php?id=$ticketId");
            }

            $targetDir = "../../uploads/tickets/";

            // Pastikan folder ada
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $newName    = "ticket_{$ticketId}_" . time() . ".jpg";
            $targetFile = $targetDir . $newName;
            $source     = $_FILES['photo']['tmp_name'];

            // Proses resize & compress dengan Imagick
            try {
                $imagick = new Imagick($source);
                $imagick->setImageOrientation(Imagick::ORIENTATION_UNDEFINED); // strip EXIF rotation
                $imagick->autoOrient(); // fix orientasi foto dari HP
                
                // Resize max 800px lebar, pertahankan aspek rasio
                $imagick->resizeImage(800, 0, Imagick::FILTER_LANCZOS, 1);
                
                // Convert ke JPG & compress
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(75);
                $imagick->stripImage(); // hapus metadata EXIF (hemat ukuran)
                
                if ($imagick->writeImage($targetFile)) {
                    $photoPath = "uploads/tickets/" . $newName;
                } else {
                    setFlash('error', 'Gagal menyimpan gambar.');
                    redirect("view_ticket.php?id=$ticketId");
                }
                
                $imagick->clear();
                $imagick->destroy();
                
            } catch (ImagickException $e) {
                setFlash('error', 'Gagal memproses gambar: ' . $e->getMessage());
                redirect("view_ticket.php?id=$ticketId");
            }

        } elseif (!$hasOldPhoto) {
            setFlash('error', 'Wajib upload foto bukti perbaikan!');
            redirect("view_ticket.php?id=$ticketId");
        }
        // Jika tidak ada foto baru tapi ada foto lama → pakai foto lama ($photoPath sudah terisi)
    }

    // Update DB
    $updateData = [
        'status'      => $status,
        'notes'       => $notes,
        'photo_proof' => $photoPath,
        'updated_at'  => date('Y-m-d H:i:s'),
    ];

    if ($status === 'resolved') {
        $updateData['resolved_at'] = date('Y-m-d H:i:s');
    }

    if (update('trouble_tickets', $updateData, 'id = ?', [$ticketId])) {
        setFlash('success', 'Status tiket berhasil diperbarui.');
        redirect('index.php');
    } else {
        setFlash('error', 'Gagal update tiket. Silakan coba lagi.');
        redirect("view_ticket.php?id=$ticketId");
    }
}

// Ambil flash untuk ditampilkan
$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
$flash = $flashSuccess
    ? ['type' => 'success', 'message' => $flashSuccess]
    : ($flashError ? ['type' => 'error', 'message' => $flashError] : null);

// Hitung URL foto bukti
$photoProofRaw = trim((string)($ticket['photo_proof'] ?? ''));
$photoProofUrl = '';
if ($photoProofRaw !== '') {
    $p = str_replace('\\', '/', $photoProofRaw);
    $p = ltrim(preg_replace('#^(\./|\.\./)+#', '', $p), '/');
    $photoProofUrl = (strpos($p, 'uploads/') === 0)
        ? '../../' . $p
        : '../../uploads/tickets/' . basename($p);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Tiket #<?php echo (int)$ticketId; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00f5ff;
            --bg-dark: #0a0a12;
            --bg-card: #161628;
            --text-primary: #ffffff;
            --text-secondary: #b0b0c0;
            --success: #00ff88;
            --danger: #ff4757;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }

        body {
            background: var(--bg-dark);
            color: var(--text-primary);
            padding-bottom: 80px;
        }

        .header {
            background: var(--bg-card);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .back-btn { color: var(--text-primary); font-size: 1.2rem; text-decoration: none; }

        .container { padding: 20px; }

        /* Flash Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .alert-success {
            background: rgba(0, 255, 136, 0.12);
            border: 1px solid var(--success);
            color: var(--success);
        }
        .alert-error {
            background: rgba(255, 71, 87, 0.12);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        .card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .label { font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 5px; display: block; }
        .value  { font-size: 1rem; margin-bottom: 15px; display: block; }

        .map-btn {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(0, 245, 255, 0.1); color: var(--primary);
            padding: 8px 15px; border-radius: 8px; text-decoration: none;
            font-size: 0.9rem; margin-top: 5px;
        }

        .wa-btn {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(0, 255, 136, 0.1); color: var(--success);
            padding: 8px 15px; border-radius: 8px; text-decoration: none;
            font-size: 0.9rem; margin-top: 5px;
        }

        .form-control {
            width: 100%; padding: 12px;
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px; color: var(--text-primary); margin-bottom: 15px;
        }

        .btn-submit {
            width: 100%; padding: 15px;
            background: var(--primary); border: none;
            border-radius: 10px; color: #000;
            font-weight: bold; font-size: 1rem; cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-submit:active { opacity: 0.8; }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .photo-preview {
            width: 100%; height: 200px;
            background: rgba(0,0,0,0.3); border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 15px; overflow: hidden;
            border: 2px dashed rgba(255,255,255,0.15);
            cursor: pointer;
        }
        .photo-preview img { width: 100%; height: 100%; object-fit: cover; }

        .status-options {
            display: grid; grid-template-columns: 1fr 1fr 1fr;
            gap: 10px; margin-bottom: 20px;
        }
        .status-opt input { display: none; }
        .status-opt label {
            display: block; padding: 10px; text-align: center;
            background: rgba(255,255,255,0.05); border-radius: 8px;
            font-size: 0.8rem; cursor: pointer; border: 1px solid transparent;
            transition: all 0.2s;
        }
        .status-opt input:checked + label {
            background: rgba(0, 245, 255, 0.2);
            border-color: var(--primary); color: var(--primary);
        }

        #loading-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 999;
            align-items: center; justify-content: center;
            flex-direction: column; gap: 12px;
        }
        #loading-overlay.active { display: flex; }
        .spinner {
            width: 40px; height: 40px;
            border: 4px solid rgba(255,255,255,0.2);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<!-- Loading Overlay saat submit -->
<div id="loading-overlay">
    <div class="spinner"></div>
    <p style="color: var(--primary); font-size: 0.9rem;">Menyimpan...</p>
</div>

<div class="header">
    <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
    <h2>Detail Tiket #<?php echo (int)$ticketId; ?></h2>
</div>

<div class="container">

    <!-- Flash Message -->
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Customer Info -->
    <div class="card">
        <h3 style="margin-bottom: 15px; color: var(--primary);">Data Pelanggan</h3>

        <span class="label">Nama Pelanggan</span>
        <span class="value"><?php echo htmlspecialchars($ticket['customer_name']); ?></span>

        <span class="label">Alamat</span>
        <span class="value"><?php echo htmlspecialchars($ticket['address']); ?></span>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if ($ticket['lat'] && $ticket['lng']): ?>
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($ticket['lat'] . ',' . $ticket['lng']); ?>"
                   target="_blank" class="map-btn">
                    <i class="fas fa-directions"></i> Petunjuk Arah
                </a>
            <?php endif; ?>

            <?php if ($ticket['phone']): ?>
                <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $ticket['phone'])); ?>"
                   target="_blank" class="wa-btn">
                    <i class="fab fa-whatsapp"></i> Chat WA
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Issue Detail -->
    <div class="card">
        <h3 style="margin-bottom: 15px; color: var(--danger);">Masalah</h3>
        <p style="color: var(--text-secondary); line-height: 1.6;">
            <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
        </p>
    </div>

    <!-- Foto Bukti (tampil jika sudah resolved) -->
    <?php if ($ticket['status'] === 'resolved' && $photoProofUrl !== ''): ?>
    <div class="card">
        <h3 style="margin-bottom: 15px; color: var(--success);">
            <i class="fas fa-check-circle" style="margin-right: 8px;"></i>Foto Bukti Perbaikan
        </h3>
        <div style="width: 100%; border-radius: 10px; overflow: hidden; border: 1px solid rgba(0,255,136,0.2);">
            <img src="<?php echo htmlspecialchars($photoProofUrl, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo time(); ?>"
                 alt="Foto bukti perbaikan"
                 id="foto-bukti"
                 style="width: 100%; display: block; object-fit: cover;">
            <p id="foto-error" style="display:none; padding:20px; text-align:center; color:var(--text-secondary);">
                <i class="fas fa-exclamation-circle"></i> Foto tidak dapat dimuat
            </p>
        </div>
        <?php if (!empty($ticket['resolved_at'])): ?>
        <p style="margin-top: 10px; font-size: 0.8rem; color: var(--text-secondary);">
            <i class="fas fa-clock" style="margin-right: 5px;"></i>
            Diselesaikan: <?php echo date('d M Y, H:i', strtotime($ticket['resolved_at'])); ?> WIB
        </p>
        <?php endif; ?>
    </div>
    <script>
        document.getElementById('foto-bukti').addEventListener('error', function () {
            this.style.display = 'none';
            document.getElementById('foto-error').style.display = 'block';
            console.error('Gagal load foto:', this.src);
        });
    </script>
    <?php endif; ?>

    <!-- Action Form -->
    <div class="card">
        <h3 style="margin-bottom: 15px;">Update Status</h3>

        <form method="POST" enctype="multipart/form-data" id="ticketForm">
            <div class="status-options">
                <div class="status-opt">
                    <input type="radio" name="status" id="st_pending" value="pending"
                        <?php echo $ticket['status'] === 'pending' ? 'checked' : ''; ?>>
                    <label for="st_pending">Pending</label>
                </div>
                <div class="status-opt">
                    <input type="radio" name="status" id="st_progress" value="in_progress"
                        <?php echo $ticket['status'] === 'in_progress' ? 'checked' : ''; ?>>
                    <label for="st_progress">Dikerjakan</label>
                </div>
                <div class="status-opt">
                    <input type="radio" name="status" id="st_resolved" value="resolved"
                        <?php echo $ticket['status'] === 'resolved' ? 'checked' : ''; ?>>
                    <label for="st_resolved">Selesai</label>
                </div>
            </div>

            <span class="label">Catatan Penyelesaian</span>
            <textarea name="notes" class="form-control" rows="3"
                placeholder="Tulis tindakan yang dilakukan..."><?php echo htmlspecialchars($ticket['notes'] ?? ''); ?></textarea>

            <div id="photo-section" style="display: <?php echo $ticket['status'] === 'resolved' ? 'block' : 'none'; ?>;">
                <span class="label">Foto Bukti (Wajib jika Selesai)</span>
                <div class="photo-preview" onclick="document.getElementById('photo-input').click()">
                    <?php if (!empty($ticket['photo_proof'])): ?>
                        <img src="../../<?php echo htmlspecialchars($ticket['photo_proof']); ?>" id="preview-img" alt="Foto bukti">
                    <?php else: ?>
                        <div id="placeholder" style="text-align: center; color: var(--text-secondary); pointer-events: none;">
                            <i class="fas fa-camera" style="font-size: 2rem; margin-bottom: 10px; display:block;"></i>
                            Klik untuk ambil foto
                        </div>
                        <img id="preview-img" style="display: none;" alt="Preview foto">
                    <?php endif; ?>
                </div>
                <input type="file" name="photo" id="photo-input"
                    accept="image/*" capture="environment"
                    style="display: none;" onchange="previewImage(this)">
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-save" style="margin-right: 6px;"></i>Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<script>
    const statusRadios  = document.getElementsByName('status');
    const photoSection  = document.getElementById('photo-section');
    const photoInput    = document.getElementById('photo-input');
    const form          = document.getElementById('ticketForm');
    const submitBtn     = document.getElementById('submitBtn');
    const loadingOverlay = document.getElementById('loading-overlay');

    // Toggle foto saat pilih status
    statusRadios.forEach(radio => {
        radio.addEventListener('change', function () {
            photoSection.style.display = this.value === 'resolved' ? 'block' : 'none';
        });
    });

    // Compress gambar di browser sebelum upload (max 1200px, quality 80%)
    // Sehingga PHP hanya terima ~200-400KB, bukan foto mentah 5-10MB dari kamera HP
    document.getElementById('photo-input').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const MAX_WIDTH = 1200;
        const QUALITY   = 0.80;

        const reader = new FileReader();
        reader.onload = function (ev) {
            const img = new Image();
            img.onload = function () {
                let w = img.width, h = img.height;
                if (w > MAX_WIDTH) {
                    h = Math.round(h * MAX_WIDTH / w);
                    w = MAX_WIDTH;
                }

                const canvas = document.createElement('canvas');
                canvas.width  = w;
                canvas.height = h;

                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);

                canvas.toBlob(function (blob) {
                    const compressed = new File([blob], 'photo.jpg', { type: 'image/jpeg' });
                    const dt = new DataTransfer();
                    dt.items.add(compressed);
                    document.getElementById('photo-input').files = dt.files;

                    // Preview setelah kompresi
                    const previewReader = new FileReader();
                    previewReader.onload = function (pe) {
                        const previewImg   = document.getElementById('preview-img');
                        const placeholder  = document.getElementById('placeholder');
                        previewImg.src     = pe.target.result;
                        previewImg.style.display = 'block';
                        if (placeholder) placeholder.style.display = 'none';
                    };
                    previewReader.readAsDataURL(compressed);

                }, 'image/jpeg', QUALITY);
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    // Preview foto (fallback, dipanggil jika onchange di input masih aktif)
    function previewImage(input) {
        // Sudah ditangani oleh event listener di atas (compress + preview)
        // Fungsi ini dibiarkan kosong sebagai fallback agar tidak error
    }

    // Validasi sebelum submit
    form.addEventListener('submit', function (e) {
        const selectedStatus = document.querySelector('input[name="status"]:checked');

        if (!selectedStatus) {
            e.preventDefault();
            alert('Pilih status terlebih dahulu!');
            return;
        }

        if (selectedStatus.value === 'resolved') {
            const notes = document.querySelector('textarea[name="notes"]').value.trim();
            if (!notes) {
                e.preventDefault();
                alert('Catatan penyelesaian wajib diisi!');
                return;
            }

            const hasOldPhoto  = <?php echo !empty($ticket['photo_proof']) ? 'true' : 'false'; ?>;
            const hasNewPhoto  = photoInput.files.length > 0;

            if (!hasOldPhoto && !hasNewPhoto) {
                e.preventDefault();
                alert('Wajib upload foto bukti perbaikan!');
                return;
            }
        }

        // Tampilkan loading
        submitBtn.disabled = true;
        loadingOverlay.classList.add('active');
    });
</script>

<?php require_once '../includes/bottom_nav.php'; ?>
</body>
</html>