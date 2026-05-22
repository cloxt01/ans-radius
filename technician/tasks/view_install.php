<?php
require_once '../../includes/auth.php';
requireTechnicianLogin();

$tech = $_SESSION['technician'];
$customerId = $_GET['id'] ?? 0;

// Fetch Customer Detail
$customer = fetchOne("
    SELECT c.*, p.name as package_name 
    FROM customers c 
    LEFT JOIN packages p ON c.package_id = p.id 
    WHERE c.id = ? AND c.installed_by = ?
", [$customerId, $tech['id']]);

if (!$customer) {
    setFlash('error', 'Data pelanggan tidak ditemukan atau bukan tugas Anda.');
    redirect('index.php?type=install');
}

// Handle Activation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serialNumber = trim($_POST['serial_number']);
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];
    $photoPath = $customer['installation_photo'];
    
    // Validasi
    if (empty($serialNumber)) {
        setFlash('error', 'Serial Number ONT wajib diisi!');
        redirect("view_install.php?id=$customerId");
    }
    
    // Handle Photo Upload (Wajib)
    if (!empty($_FILES['photo']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newName = "install_{$customerId}_" . time() . ".jpg";
            $targetDir = "../../uploads/installations/";
            
            // Create directory if not exists
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $targetFile = $targetDir . $newName;
            
            // Resize Image
            $source = $_FILES['photo']['tmp_name'];
            list($width, $height) = getimagesize($source);
            
            $newWidth = 800;
            $newHeight = ($height / $width) * $newWidth;
            
            $tmpImg = imagecreatetruecolor($newWidth, $newHeight);
            
            switch ($ext) {
                case 'jpg': 
                case 'jpeg': 
                    $sourceImg = imagecreatefromjpeg($source); 
                    break;
                case 'png': 
                    $sourceImg = imagecreatefrompng($source); 
                    break;
                case 'webp': 
                    $sourceImg = imagecreatefromwebp($source); 
                    break;
                default:
                    $sourceImg = imagecreatefromjpeg($source);
                    break;
            }
            
            imagecopyresampled($tmpImg, $sourceImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            
            if (imagejpeg($tmpImg, $targetFile, 70)) {
                $photoPath = "uploads/installations/" . $newName;
                imagedestroy($tmpImg);
                imagedestroy($sourceImg);
            } else {
                setFlash('error', 'Gagal memproses gambar.');
                redirect("view_install.php?id=$customerId");
            }
        } else {
            setFlash('error', 'Format foto harus JPG/PNG/WEBP.');
            redirect("view_install.php?id=$customerId");
        }
    } elseif (empty($customer['installation_photo'])) {
        setFlash('error', 'Wajib upload foto bukti instalasi!');
        redirect("view_install.php?id=$customerId");
    }
    
    // Update DB: Activate Customer
    $updateData = [
        'status' => 'active',
        'serial_number' => $serialNumber,
        'installation_photo' => $photoPath,
        'installation_date' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Update Lat/Lng if provided
    if (!empty($lat) && !empty($lng)) {
        $updateData['lat'] = str_replace(',', '.', $lat);
        $updateData['lng'] = str_replace(',', '.', $lng);
    }
    
    if (update('customers', $updateData, 'id = ?', [$customerId])) {
        // Log Activity
        logActivity('INSTALL_COMPLETE', "Customer #$customerId activated by Tech #{$tech['id']}");
        setFlash('success', 'Instalasi selesai! Pelanggan kini Aktif.');
        redirect('index.php?type=install');
    } else {
        setFlash('error', 'Gagal menyimpan data.');
    }
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
    <title>Proses Instalasi - <?php echo htmlspecialchars($customer['name']); ?></title>
    
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
            --accent-blue-hover: #58a6ff;
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

        /* ==================== HEADER ==================== */
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

        /* ==================== CONTAINER ==================== */
        .container {
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* ==================== CARD ==================== */
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
            color: var(--accent-blue);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card h3 i {
            font-size: 0.9rem;
        }

        /* ==================== INFO LABELS ==================== */
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

        /* ==================== MAP BUTTON ==================== */
        .map-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(47, 129, 247, 0.1);
            color: var(--accent-blue);
            padding: 8px 14px;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-size: 0.8rem;
            margin-top: 4px;
            transition: all var(--transition-fast);
        }

        .map-btn:hover {
            background: rgba(47, 129, 247, 0.2);
        }

        /* ==================== FORM CONTROLS ==================== */
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
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(47, 129, 247, 0.1);
        }

        .coord-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* ==================== GPS BUTTON ==================== */
        .gps-btn {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            margin-bottom: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all var(--transition-fast);
        }

        .gps-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* ==================== PHOTO PREVIEW ==================== */
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

        /* ==================== BUTTON SUBMIT ==================== */
        .btn-submit {
            width: 100%;
            padding: 12px 16px;
            background: var(--accent-green);
            border: none;
            border-radius: var(--radius-md);
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            margin-top: 8px;
        }

        .btn-submit:hover {
            background: #2ea043;
            transform: translateY(-1px);
        }

        /* ==================== SUCCESS STATE ==================== */
        .success-card {
            text-align: center;
            border-color: rgba(63, 185, 80, 0.3);
        }

        .success-card i {
            font-size: 3rem;
            color: var(--accent-green);
            margin-bottom: 16px;
        }

        .success-card h3 {
            color: var(--accent-green);
            justify-content: center;
        }

        .install-photo {
            margin-top: 16px;
            border-radius: var(--radius-md);
            max-width: 100%;
        }

        /* ==================== ALERT ==================== */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }

        .alert-error {
            background: rgba(248, 81, 73, 0.1);
            border: 1px solid rgba(248, 81, 73, 0.3);
            color: var(--accent-red);
        }

        .alert-success {
            background: rgba(63, 185, 80, 0.1);
            border: 1px solid rgba(63, 185, 80, 0.3);
            color: var(--accent-green);
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 480px) {
            .container {
                padding: 16px;
            }

            .card {
                padding: 16px;
            }

            .coord-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <a href="index.php?type=install" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2>Instalasi #C<?php echo $customerId; ?></h2>
    </div>

    <div class="container">
        <!-- Customer Info Card -->
        <div class="card">
            <h3>
                <i class="fas fa-user-circle"></i> Data Pelanggan
            </h3>
            
            <span class="label">Nama Pelanggan</span>
            <span class="value"><?php echo htmlspecialchars($customer['name']); ?></span>
            
            <span class="label">Alamat</span>
            <span class="value"><?php echo htmlspecialchars($customer['address']); ?></span>
            
            <span class="label">Paket Internet</span>
            <span class="value"><?php echo htmlspecialchars($customer['package_name']); ?></span>
            
            <?php if ($customer['lat'] && $customer['lng']): ?>
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $customer['lat'] . ',' . $customer['lng']; ?>" target="_blank" class="map-btn">
                    <i class="fas fa-directions"></i> Petunjuk Arah
                </a>
            <?php endif; ?>
        </div>

        <!-- Flash Messages -->
        <?php if (hasFlash('error')): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo getFlash('error'); ?>
            </div>
        <?php endif; ?>

        <?php if (hasFlash('success')): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo getFlash('success'); ?>
            </div>
        <?php endif; ?>

        <!-- Status Card -->
        <?php if ($customer['status'] === 'active'): ?>
            <div class="card success-card">
                <i class="fas fa-check-circle"></i>
                <h3>Instalasi Selesai</h3>
                <p style="color: var(--fg-muted); margin-top: 8px;">Pelanggan ini sudah aktif</p>
                <?php if ($customer['installation_photo']): ?>
                    <img src="../../<?php echo htmlspecialchars($customer['installation_photo']); ?>" class="install-photo">
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Activation Form -->
            <div class="card">
                <h3>
                    <i class="fas fa-check-circle"></i> Form Aktivasi
                </h3>
                
                <form method="POST" enctype="multipart/form-data">
                    <span class="label">
                        <i class="fas fa-microchip"></i> Serial Number (SN) ONT
                    </span>
                    <input type="text" name="serial_number" class="form-control" placeholder="Contoh: ZTEGC8E12345" required>
                    
                    <span class="label">
                        <i class="fas fa-map-marker-alt"></i> Koordinat Lokasi
                    </span>
                    <button type="button" class="gps-btn" onclick="getLocation()">
                        <i class="fas fa-location-dot"></i> Ambil Lokasi Saya
                    </button>
                    <div class="coord-grid">
                        <input type="text" name="lat" id="lat" class="form-control" placeholder="Latitude" value="<?php echo htmlspecialchars($customer['lat'] ?? ''); ?>">
                        <input type="text" name="lng" id="lng" class="form-control" placeholder="Longitude" value="<?php echo htmlspecialchars($customer['lng'] ?? ''); ?>">
                    </div>
                    
                    <span class="label">
                        <i class="fas fa-camera"></i> Foto Bukti Instalasi (Wajib)
                    </span>
                    <div class="photo-preview" onclick="document.getElementById('photo-input').click()">
                        <div id="placeholder" class="photo-placeholder">
                            <i class="fas fa-camera"></i><br>
                            Klik untuk mengambil foto
                        </div>
                        <img id="preview-img" style="display: none;">
                    </div>
                    <input type="file" name="photo" id="photo-input" accept="image/*" capture="environment" style="display: none;" onchange="previewImage(this)" required>
                    
                    <button type="submit" class="btn-submit" onclick="return confirm('Pastikan semua data sudah benar. Aktifkan pelanggan?');">
                        <i class="fas fa-save"></i> Simpan & Aktifkan
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <?php require_once '../includes/bottom_nav.php'; ?>

    <script>
        // Image Preview
        function previewImage(input) {
            const previewImg = document.getElementById('preview-img');
            const placeholder = document.getElementById('placeholder');
            
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                    placeholder.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // GPS Location
        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showPosition, showError, { 
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            } else {
                alert("Geolocation tidak didukung browser ini.");
            }
        }

        function showPosition(position) {
            document.getElementById("lat").value = position.coords.latitude.toFixed(6);
            document.getElementById("lng").value = position.coords.longitude.toFixed(6);
            
            // Optional: show success feedback
            const gpsBtn = document.querySelector('.gps-btn');
            const originalText = gpsBtn.innerHTML;
            gpsBtn.innerHTML = '<i class="fas fa-check"></i> Lokasi Terambil!';
            setTimeout(() => {
                gpsBtn.innerHTML = originalText;
            }, 2000);
        }

        function showError(error) {
            let message = '';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message = "Izin lokasi ditolak. Silakan izinkan akses lokasi.";
                    break;
                case error.POSITION_UNAVAILABLE:
                    message = "Informasi lokasi tidak tersedia.";
                    break;
                case error.TIMEOUT:
                    message = "Waktu permintaan lokasi habis.";
                    break;
                default:
                    message = "Terjadi kesalahan saat mengambil lokasi.";
                    break;
            }
            alert(message);
        }
    </script>
</body>
</html>