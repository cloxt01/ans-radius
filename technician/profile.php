<?php
require_once '../includes/auth.php';
requireTechnicianLogin();

$tech = $_SESSION['technician'];
$pageTitle = 'Profil Saya';

// Handle Update Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    
    $updateData = [
        'name' => $name,
        'phone' => $phone,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if (!empty($password)) {
        $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    if (update('technician_users', $updateData, 'id = ?', [$tech['id']])) {
        // Update Session
        $_SESSION['technician']['name'] = $name;
        $_SESSION['technician']['phone'] = $phone;
        
        setFlash('success', 'Profil berhasil diperbarui');
        redirect('profile.php');
    } else {
        setFlash('error', 'Gagal memperbarui profil');
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
    <title>Profil - <?php echo htmlspecialchars($tech['name']); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==================== GITHUB DARK THEME - TEKNISI PROFILE ==================== */
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
            --accent-purple: #a371f7;
            --shadow-small: 0 0 0 1px rgba(255,255,255,0.05);
            --shadow-medium: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-large: 0 8px 24px rgba(0,0,0,0.4);
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
            border-radius: 8px;
            transition: background 0.2s;
        }

        .back-btn:hover {
            background: var(--bg-tertiary);
        }

        .header h2 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        /* ==================== CONTAINER ==================== */
        .container {
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* ==================== PROFILE HEADER ==================== */
        .profile-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            color: white;
            margin: 0 auto 16px;
            box-shadow: var(--shadow-medium);
        }

        .profile-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .profile-header p {
            font-size: 0.8rem;
            color: var(--fg-muted);
        }

        /* ==================== CARD ==================== */
        .card {
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .card h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--accent-blue);
            margin-bottom: 20px;
            letter-spacing: 0.02em;
        }

        /* ==================== FORM ==================== */
        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--fg-muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .form-label i {
            margin-right: 6px;
            font-size: 0.7rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            background: var(--bg-canvas);
            border: 1px solid var(--border-default);
            border-radius: 10px;
            color: var(--fg-default);
            font-size: 0.85rem;
            font-family: 'Inter', monospace;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(47, 129, 247, 0.1);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* ==================== BUTTONS ==================== */
        .btn-submit {
            width: 100%;
            padding: 12px 16px;
            background: var(--accent-blue);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }

        .btn-submit:hover {
            background: var(--accent-blue-hover);
            transform: translateY(-1px);
        }

        .btn-logout {
            display: block;
            width: 100%;
            padding: 12px 16px;
            background: transparent;
            color: var(--accent-red);
            border: 1px solid rgba(248, 81, 73, 0.3);
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            margin-top: 16px;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: rgba(248, 81, 73, 0.1);
            border-color: var(--accent-red);
        }

        /* ==================== ALERT ==================== */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(63, 185, 80, 0.1);
            border-color: rgba(63, 185, 80, 0.3);
            color: var(--accent-green);
        }

        .alert-error {
            background: rgba(248, 81, 73, 0.1);
            border-color: rgba(248, 81, 73, 0.3);
            color: var(--accent-red);
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 480px) {
            .container {
                padding: 16px;
            }
            
            .card {
                padding: 20px;
            }
            
            .avatar {
                width: 80px;
                height: 80px;
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2>Profil Saya</h2>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="avatar">
                <i class="fas fa-user"></i>
            </div>
            <h3><?php echo htmlspecialchars($tech['name']); ?></h3>
            <p>@<?php echo htmlspecialchars($tech['username']); ?></p>
        </div>

        <!-- Edit Profile Card -->
        <div class="card">
            <h3>
                <i class="fas fa-user-edit"></i> Edit Profil
            </h3>
            
            <?php if (hasFlash('success')): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo getFlash('success'); ?>
                </div>
            <?php endif; ?>
            
            <?php if (hasFlash('error')): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo getFlash('error'); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Nama Lengkap
                    </label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($tech['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-at"></i> Username
                    </label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($tech['username']); ?>" readonly disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fab fa-whatsapp"></i> No. HP / WA
                    </label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($tech['phone'] ?? ''); ?>" placeholder="Contoh: 081234567890">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Ganti Password (Opsional)
                    </label>
                    <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin ubah">
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
            
            <a href="logout.php" class="btn-logout" onclick="return confirm('Keluar dari aplikasi?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <?php require_once 'includes/bottom_nav.php'; ?>
</body>
</html>