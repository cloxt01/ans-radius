<?php
/**
 * Customer Portal Login
 */

require_once '../includes/auth.php';

// Prevent browser/proxy caching for the login page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Check if already logged in
if (isCustomerLoggedIn()) {
    redirect('dashboard.php');
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Sesi tidak valid atau telah kadaluarsa. Silakan coba lagi.');
        redirect('login.php');
    }

    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $throttleStatus = getLoginThrottleStatus('customer', $phone, 5, 900, 900);
    if ($throttleStatus['blocked']) {
        $retryAfter = max(1, (int) ceil($throttleStatus['retry_after'] / 60));
        setFlash('error', 'Terlalu banyak percobaan login. Coba lagi dalam ' . $retryAfter . ' menit.');
        redirect('login.php');
    }

    if (customerLogin($phone, $password)) {
        clearLoginFailures('customer', $phone);
        setFlash('success', 'Login berhasil! Selamat datang.');
        redirect('dashboard.php');
    } else {
        addLoginFailure('customer', $phone, 5, 900, 900);
        setFlash('error', 'Nomor HP atau password salah!');
        redirect('login.php');
    }
}

$appName = getSetting('app_name', 'GEMBOK');
$pageTitle = 'Login Pelanggan';
$content = '';

ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($pageTitle . ' - ' . $appName); ?></title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#f5f7fb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Portal Pelanggan">
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 0;
        font-family: 'Inter', sans-serif;
        overflow-x: hidden;
        background: #f5f7fb;
    }

    :root {
        --brand-blue: #2563eb;
        --brand-teal: #0891b2;
        --bg-main: radial-gradient(circle at 15% 15%, #ffffff 0%, #f5f7fb 48%, #e8edf8 100%);
        --bg-card: rgba(255, 255, 255, 0.88);
        --bg-input: #ffffff;
        --text-primary: #111827;
        --text-secondary: #4b5563;
        --border-soft: rgba(15, 23, 42, 0.12);
        --brand-gradient: linear-gradient(135deg, #2563eb 0%, #0891b2 100%);
    }

    .login-container {
        min-height: 100vh;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: var(--bg-main);
        overflow: hidden;
    }

    .login-card {
        background: var(--bg-card);
        border: 1px solid var(--border-soft);
        border-radius: 18px;
        padding: 60px;
        width: 100%;
        max-width: 600px;
        box-shadow: 0 16px 40px rgba(37, 99, 235, 0.14);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        position: relative;
        overflow: hidden;
        box-sizing: border-box;
    }

    .login-header-icon {
        font-size: 3rem;
        background: var(--brand-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 15px;
        display: inline-block;
    }

    .login-title {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 5px;
        background: var(--brand-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .login-subtitle {
        color: var(--text-secondary);
        margin: 0;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .form-control {
        width: 100%;
        padding: 12px;
        background: var(--bg-input);
        border: 1px solid var(--border-soft);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 1rem;
        box-sizing: border-box;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--brand-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }

    .btn-login {
        width: 100%;
        padding: 12px 20px;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        color: #ffffff;
        background: var(--brand-gradient);
        transition: all 0.3s;
        box-shadow: 0 8px 24px rgba(37, 99, 235, 0.28);
    }
    
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 28px rgba(37, 99, 235, 0.34);
    }

    .login-help {
        margin-top: 20px;
        padding: 15px;
        background: rgba(37, 99, 235, 0.08);
        border: 1px solid rgba(37, 99, 235, 0.2);
        border-radius: 8px;
        color: #2563eb;
        font-size: 0.9rem;
        text-align: center;
    }

    .login-footer {
        text-align: center;
        margin-top: 20px;
        color: var(--text-secondary);
        font-size: 0.9rem;
        position: relative;
        z-index: 1;
    }
    
    .login-footer a {
        color: #2563eb;
        text-decoration: none;
        display: inline-block;
        margin-top: 10px;
        transition: color 0.3s;
    }
    
    .login-footer a:hover {
        color: #0f766e;
    }

    @media (max-width: 480px) {
        .login-container {
            padding: 0;
        }

        .login-card {
            padding: 30px 25px;
            max-width: 100%;
            border-radius: 0;
            min-height: 100vh;
            border: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-around; /* Spread content vertically */
        }

        .login-title {
            font-size: 2.5rem;
        }

        .login-subtitle {
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 30px;
        }

        .form-label {
            font-size: 1.1rem;
            margin-bottom: 12px;
        }

        .form-control {
            padding: 16px;
            font-size: 1.1rem;
            border-radius: 12px;
        }
        
        .btn-login {
            padding: 16px;
            font-size: 1.2rem;
            border-radius: 14px;
        }

        .login-header-icon {
            font-size: 4rem;
        }

        .login-help, .login-footer {
            font-size: 1rem;
        }
    }
</style>

<div class="login-container">
    <div class="login-card">
        <div style="text-align: center; margin-bottom: 30px; position: relative; z-index: 1;">
            <img src="<?php echo APP_URL; ?>/assets/icons/icon.webp" alt="<?php echo htmlspecialchars($appName); ?>" width="120" height="120" class="login-header-icon">
            <p class="login-subtitle">Portal Pelanggan</p>
        </div>

        <?php if (hasFlash('error')): ?>
            <div class="alert alert-error"
                style="margin-bottom: 20px; background: rgba(255, 71, 87, 0.2); border: 1px solid #ff4757; color: #ff4757; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars(getFlash('error')); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="form-group">
                <label class="form-label">Nomor HP</label>
                <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        

        <div class="login-footer">
            <p style="margin: 0;">Belum punya akun atau Lupa Password? <a href="<?php echo APP_URL; ?>/index.php#contact">Hubungi admin.</a></p>
            <a href="../index.php"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
        </div>
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

<?php
$content = ob_get_clean();
echo $content;
?>
</body>
</html>

