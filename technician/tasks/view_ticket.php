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

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $newName    = "ticket_{$ticketId}_" . time() . ".jpg";
            $targetFile = $targetDir . $newName;
            $source     = $_FILES['photo']['tmp_name'];

            try {
                $imagick = new Imagick($source);
                $imagick->setImageOrientation(Imagick::ORIENTATION_UNDEFINED);
                $imagick->autoOrient();
                $imagick->resizeImage(800, 0, Imagick::FILTER_LANCZOS, 1);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(75);
                $imagick->stripImage();
                
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d1117">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <title>Detail Tiket #<?php echo (int)$ticketId; ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==================== GITHUB DARK THEME ==================== */
        :root {
            --bg-canvas: #0d1117;
            --bg-inset: #010409;
            --bg-primary: #161b22;
            --bg-secondary: #0d1117;
            --bg-tertiary: #21262d;
            --border-default: #30363d;
            --border-muted: #21262d;
            --fg-default: #e6edf3;
            --fg-muted: #7d8590;
            --fg-subtle: #6e7681;
            --accent-blue: #2f81f7;
            --accent-green: #3fb950;
            --accent-red: #f85149;
            --accent-orange: #d29922;
            --accent-cyan: #79c0ff;
            --shadow-small: 0 0 0 1px rgba(255,255,255,0.05);
            --shadow-medium: 0 4px 12px rgba(0,0,0,0.3);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --transition-fast: 0.15s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', sans-serif;
            background: var(--bg-canvas);
            color: var(--fg-default);
            line-height: 1.5;
            padding-bottom: 76px;
        }

        /* Header */
        .header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-default);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .back-btn {
            color: var(--fg-default);
            font-size: 1.2rem;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            transition: background var(--transition-fast);
        }

        .back-btn:hover {
            background: var(--bg-tertiary);
        }

        .header h2 {
            font-size: 1rem;
            font-weight: 600;
        }

        /* Container */
        .container {
            padding: 20px;
            max-width: 700px;
            margin: 0 auto;
        }

        /* Cards */
        .card {
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
        }

        .card h3 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card h3 i {
            font-size: 0.9rem;
        }

        /* Labels & Values */
        .label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--fg-muted);
            margin-bottom: 4px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .value {
            font-size: 0.9rem;
            margin-bottom: 14px;
            display: block;
            color: var(--fg-default);
        }

        /* Action Buttons */
        .map-btn, .wa-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-size: 0.8rem;
            transition: all var(--transition-fast);
        }

        .map-btn {
            background: rgba(47, 129, 247, 0.1);
            color: var(--accent-blue);
        }

        .map-btn:hover {
            background: rgba(47, 129, 247, 0.2);
        }

        .wa-btn {
            background: rgba(63, 185, 80, 0.1);
            color: var(--accent-green);
        }

        .wa-btn:hover {
            background: rgba(63, 185, 80, 0.2);
        }

        /* Issue Description */
        .issue-text {
            color: var(--fg-muted);
            line-height: 1.6;
            font-size: 0.85rem;
        }

        /* Status Options */
        .status-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .status-opt input { display: none; }

        .status-opt label {
            display: block;
            padding: 10px;
            text-align: center;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border-default);
            transition: all var(--transition-fast);
        }

        .status-opt input:checked + label {
            border-color: var(--accent-blue);
            color: var(--accent-blue);
        }

        /* Form Controls */
        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-canvas);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            color: var(--fg-default);
            font-size: 0.85rem;
            margin-bottom: 14px;
            transition: all var(--transition-fast);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(47, 129, 247, 0.1);
        }

        /* Photo Preview */
        .photo-preview {
            width: 100%;
            height: 200px;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            overflow: hidden;
            border: 2px dashed var(--border-default);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .photo-preview:hover {
            border-color: var(--accent-blue);
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-placeholder {
            text-align: center;
            color: var(--fg-muted);
        }

        .photo-placeholder i {
            font-size: 2rem;
            margin-bottom: 8px;
            opacity: 0.5;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 12px 16px;
            background: var(--accent-blue);
            border: none;
            border-radius: var(--radius-md);
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #1f6feb;
            transform: translateY(-1px);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }

        .alert-success {
            background: rgba(63, 185, 80, 0.1);
            border: 1px solid rgba(63, 185, 80, 0.3);
            color: var(--accent-green);
        }

        .alert-error {
            background: rgba(248, 81, 73, 0.1);
            border: 1px solid rgba(248, 81, 73, 0.3);
            color: var(--accent-red);
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(47, 129, 247, 0.2);
            border-top-color: var(--accent-blue);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .container {
                padding: 16px;
            }

            .card {
                padding: 16px;
            }

            .status-options {
                gap: 8px;
            }

            .status-opt label {
                padding: 8px;
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="spinner"></div>
    <p style="color: var(--accent-blue); font-size: 0.85rem;">Menyimpan...</p>
</div>

<!-- Header -->
<div class="header">
    <a href="index.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
    </a>
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

    <!-- Customer Info Card -->
    <div class="card">
        <h3>
            <i class="fas fa-user-circle" style="color: var(--accent-blue);"></i> Data Pelanggan
        </h3>

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

    <!-- Issue Detail Card -->
    <div class="card">
        <h3>
            <i class="fas fa-exclamation-triangle" style="color: var(--accent-red);"></i> Masalah
        </h3>
        <div class="issue-text">
            <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
        </div>
    </div>

    <!-- Photo Proof (if resolved) -->
    <?php if ($ticket['status'] === 'resolved' && $photoProofUrl !== ''): ?>
    <div class="card">
        <h3>
            <i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Foto Bukti Perbaikan
        </h3>
        <div style="width: 100%; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-default);">
            <img src="<?php echo htmlspecialchars($photoProofUrl, ENT_QUOTES, 'UTF-8'); ?>" 
                 alt="Foto bukti perbaikan"
                 id="fotoBukti"
                 style="width: 100%; display: block;">
        </div>
        <?php if (!empty($ticket['resolved_at'])): ?>
            <p style="margin-top: 10px; font-size: 0.7rem; color: var(--fg-muted);">
                <i class="fas fa-clock"></i> Diselesaikan: <?php echo date('d M Y, H:i', strtotime($ticket['resolved_at'])); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Action Form -->
    <div class="card">
        <h3>
            <i class="fas fa-edit"></i> Update Status
        </h3>

        <form method="POST" enctype="multipart/form-data" id="ticketForm">
            <div class="status-options">
                <div class="status-opt">
                    <input type="radio" name="status" id="statusPending" value="pending"
                        <?php echo $ticket['status'] === 'pending' ? 'checked' : ''; ?>>
                    <label for="statusPending">🟡 Pending</label>
                </div>
                <div class="status-opt">
                    <input type="radio" name="status" id="statusProgress" value="in_progress"
                        <?php echo $ticket['status'] === 'in_progress' ? 'checked' : ''; ?>>
                    <label for="statusProgress">🔵 Dikerjakan</label>
                </div>
                <div class="status-opt">
                    <input type="radio" name="status" id="statusResolved" value="resolved"
                        <?php echo $ticket['status'] === 'resolved' ? 'checked' : ''; ?>>
                    <label for="statusResolved">✅ Selesai</label>
                </div>
            </div>

            <span class="label">
                <i class="fas fa-pen"></i> Catatan Penyelesaian
            </span>
            <textarea name="notes" class="form-control" rows="3"
                placeholder="Tulis tindakan yang dilakukan..."><?php echo htmlspecialchars($ticket['notes'] ?? ''); ?></textarea>

            <div id="photoSection" style="display: <?php echo $ticket['status'] === 'resolved' ? 'block' : 'none'; ?>;">
                <span class="label">
                    <i class="fas fa-camera"></i> Foto Bukti (Wajib jika Selesai)
                </span>
                <div class="photo-preview" onclick="document.getElementById('photoInput').click()">
                    <div id="photoPlaceholder" class="photo-placeholder" style="<?php echo !empty($ticket['photo_proof']) ? 'display: none;' : ''; ?>">
                        <i class="fas fa-camera"></i><br>
                        Klik untuk ambil foto
                    </div>
                    <img id="previewImg" src="<?php echo !empty($ticket['photo_proof']) ? '../../' . htmlspecialchars($ticket['photo_proof']) : ''; ?>" 
                         style="<?php echo empty($ticket['photo_proof']) ? 'display: none;' : 'display: block; width: 100%; height: 100%; object-fit: cover;'; ?>">
                </div>
                <input type="file" name="photo" id="photoInput" accept="image/*" capture="environment" style="display: none;">
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-save"></i> Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<!-- Bottom Navigation -->
<?php require_once '../includes/bottom_nav.php'; ?>

<script>
    // DOM Elements
    const statusRadios = document.querySelectorAll('input[name="status"]');
    const photoSection = document.getElementById('photoSection');
    const photoInput = document.getElementById('photoInput');
    const photoPlaceholder = document.getElementById('photoPlaceholder');
    const previewImg = document.getElementById('previewImg');
    const form = document.getElementById('ticketForm');
    const submitBtn = document.getElementById('submitBtn');
    const loadingOverlay = document.getElementById('loadingOverlay');

    // Toggle photo section based on status
    statusRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            photoSection.style.display = this.value === 'resolved' ? 'block' : 'none';
        });
    });

    // Compress image before upload
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const MAX_WIDTH = 1200;
        const QUALITY = 0.80;

        const reader = new FileReader();
        reader.onload = function(ev) {
            const img = new Image();
            img.onload = function() {
                let w = img.width, h = img.height;
                if (w > MAX_WIDTH) {
                    h = Math.round(h * MAX_WIDTH / w);
                    w = MAX_WIDTH;
                }

                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;

                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);

                canvas.toBlob(function(blob) {
                    const compressed = new File([blob], 'photo.jpg', { type: 'image/jpeg' });
                    const dt = new DataTransfer();
                    dt.items.add(compressed);
                    photoInput.files = dt.files;

                    // Preview
                    const previewReader = new FileReader();
                    previewReader.onload = function(pe) {
                        previewImg.src = pe.target.result;
                        previewImg.style.display = 'block';
                        if (photoPlaceholder) photoPlaceholder.style.display = 'none';
                    };
                    previewReader.readAsDataURL(compressed);
                }, 'image/jpeg', QUALITY);
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    // Validation before submit
    form.addEventListener('submit', function(e) {
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

            const hasOldPhoto = <?php echo !empty($ticket['photo_proof']) ? 'true' : 'false'; ?>;
            const hasNewPhoto = photoInput.files.length > 0;

            if (!hasOldPhoto && !hasNewPhoto) {
                e.preventDefault();
                alert('Wajib upload foto bukti perbaikan!');
                return;
            }
        }

        // Show loading overlay
        submitBtn.disabled = true;
        loadingOverlay.classList.add('active');
    });
</script>

</body>
</html>