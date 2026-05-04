<?php
/**
 * Sales Login Page
 */

require_once '../includes/auth.php';

// Prevent browser/proxy caching for the login page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Check if already logged in
if (isSalesLoggedIn()) {
    redirect('dashboard.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Sesi tidak valid atau telah kadaluarsa. Silakan coba lagi.');
        redirect('login.php');
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $loginResult = salesLogin($username, $password);

    if ($loginResult === true) {
        setFlash('success', 'Login berhasil! Selamat datang.');
        redirect('dashboard.php');
    } elseif ($loginResult === 'inactive') {
        setFlash('error', 'Akun Anda dinonaktifkan. Hubungi Admin.');
        redirect('login.php');
    } else {
        setFlash('error', 'Username atau password salah!');
        redirect('login.php');
    }
}

$appName = getSetting('app_name', 'GEMBOK');
$pageTitle = 'Login Sales';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo htmlspecialchars($appName); ?></title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#f5f7fb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Sales Portal">
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: radial-gradient(circle at 15% 15%, #ffffff 0%, #f5f7fb 48%, #e8edf8 100%);
            --bg-card: rgba(255, 255, 255, 0.88);
            --bg-input: #ffffff;
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --border-soft: rgba(15, 23, 42, 0.12);
            --brand-gradient: linear-gradient(135deg, #2563eb 0%, #0891b2 100%);
            --danger: #dc2626;
            --success: #16a34a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 18px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 16px 40px rgba(37, 99, 235, 0.14);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .login-header-icon {
            width: 92px;
            height: 92px;
            margin: 0 auto 12px;
            display: block;
        }
        .brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand h1 {
            font-size: 1.7rem;
            margin-bottom: 4px;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .login-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .form-control {
            width: 100%;
            padding: 12px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: var(--brand-gradient);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.28);
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error {
            background: rgba(255, 71, 87, 0.18);
            border: 1px solid rgba(255, 71, 87, 0.55);
            color: var(--danger);
        }
        .alert-success {
            background: rgba(46, 213, 115, 0.18);
            border: 1px solid rgba(46, 213, 115, 0.55);
            color: var(--success);
        }
        .login-footer {
            text-align: center;
            margin-top: 22px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .login-footer a {
            color: #2563eb;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
            transition: color 0.2s;
        }

        .login-footer a:hover {
            color: #0f766e;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 30px 22px;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <img src="<?php echo APP_URL; ?>/assets/icons/icon.webp" alt="<?php echo htmlspecialchars($appName); ?>" class="login-header-icon">
            <p class="login-subtitle">Portal Sales</p>
        </div>

        <?php if (hasFlash('error')): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars(getFlash('error')); ?>
            </div>
        <?php endif; ?>

        <?php if (hasFlash('success')): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars(getFlash('success')); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
            </div>

            

            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        <div class="login-footer">
            <p style="margin: 0;">Belum punya akun atau Lupa Password? <a href="<?php echo APP_URL; ?>/index.php#contact">Hubungi admin.</a></p>
            <a style="margin-top: 20px;" href="../index.php">
                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
            </a>
        </div>

        
    </div>

    <script>
        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    })
                    .catch(function(error) {
                        console.log('ServiceWorker registration failed: ', error);
                    });
            });
        }
    </script>
</body>
</html>

