<?php
/**
 * Admin Login Page
 */

require_once '../includes/auth.php';

// Prevent browser/proxy caching for the login page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Check if already logged in
if (isAdminLoggedIn()) {
    redirect('dashboard.php');
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
    $throttleStatus = getLoginThrottleStatus('admin', $username, 5, 900, 900);
    if ($throttleStatus['blocked']) {
        $retryAfter = max(1, (int) ceil($throttleStatus['retry_after'] / 60));
        setFlash('error', 'Terlalu banyak percobaan login. Coba lagi dalam ' . $retryAfter . ' menit.');
        redirect('login.php');
    }

    if (adminLogin($username, $password)) {
        clearLoginFailures('admin', $username);
        setFlash('success', 'Login berhasil! Selamat datang.');
        redirect('dashboard.php');
    } else {
        addLoginFailure('admin', $username, 5, 900, 900);
        setFlash('error', 'Username atau password salah!');
        redirect('login.php');
    }
}


$appName = getSetting('app_name', 'GEMBOK');
$pageTitle = 'Login Admin';
$content = '';

ob_start();
?>

<div class="login-wrap">
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>
    <div class="bg-orb orb-3"></div>
    
    <div class="login-card">
        <div class="brand">
            <img src="<?php echo APP_URL; ?>/assets/icons/icon.webp" class="login-header-icon" alt="Icon">
            <p class="login-subtitle">Portal Admin</p>
        </div>

        <?php if (hasFlash('error')): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars(getFlash('error')); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-lock"></i> Password
                </label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div class="login-footer">
            <p><i class="fas fa-shield-alt"></i> Ganti password setelah login pertama.</p>
            <a href="forgot_password.php"><i class="fas fa-unlock-alt"></i> Lupa password?</a>
            <a href="../index.php"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
        </div>
    </div>
</div>

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
        --fg-on-emphasis: #ffffff;
        --accent-blue: #2f81f7;
        --accent-blue-hover: #58a6ff;
        --accent-green: #3fb950;
        --accent-red: #f85149;
        --accent-orange: #d29922;
        --accent-red-soft: rgba(248, 81, 73, 0.15);
        --accent-blue-soft: rgba(47, 129, 247, 0.1);
        --shadow-small: 0 0 0 1px rgba(255,255,255,0.05);
        --shadow-medium: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-large: 0 8px 24px rgba(0,0,0,0.4);
        --shadow-blue: 0 4px 12px rgba(47, 129, 247, 0.25);
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
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow-x: hidden;
    }

    /* Animated background orbs */
    .bg-orb {
        position: fixed;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.35;
        pointer-events: none;
        z-index: -1;
    }

    .orb-1 {
        width: 350px;
        height: 350px;
        left: -120px;
        top: -80px;
        background: var(--accent-blue);
        opacity: 0.25;
    }

    .orb-2 {
        width: 450px;
        height: 450px;
        right: -180px;
        bottom: -120px;
        background: var(--accent-green);
        opacity: 0.12;
    }

    .orb-3 {
        width: 280px;
        height: 280px;
        left: 15%;
        bottom: 10%;
        background: var(--accent-orange);
        opacity: 0.1;
    }

    /* Login Container */
    .login-wrap {
        width: 100%;
        max-width: 460px;
        margin: 20px;
        position: relative;
        z-index: 1;
    }

    /* Login Card - GitHub Dark Style */
    .login-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-default);
        border-radius: 24px;
        padding: 44px 36px;
        box-shadow: var(--shadow-large);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .login-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
    }

    /* Brand Section */
    .brand {
        text-align: center;
        margin-bottom: 36px;
    }

    .login-header-icon {
        width: 88px;
        height: 88px;
        margin: 0 auto 16px;
        display: block;
        border-radius: 20px;
        box-shadow: var(--shadow-small);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        padding: 8px;
    }

    .login-header-icon:hover {
        transform: scale(1.02);
        box-shadow: var(--shadow-medium);
    }

    .brand h1 {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        margin-bottom: 8px;
        background: linear-gradient(135deg, var(--accent-blue), var(--accent-green));
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .login-subtitle {
        color: var(--fg-muted);
        font-size: 0.85rem;
        font-weight: 500;
        letter-spacing: 0.02em;
    }

    /* Alert Messages - GitHub Style */
    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 28px;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid;
        animation: slideIn 0.3s ease;
        font-weight: 500;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-error {
        background: var(--accent-red-soft);
        border-color: rgba(248, 81, 73, 0.35);
        color: var(--accent-red);
    }

    .alert-error i {
        color: var(--accent-red);
        font-size: 1rem;
    }

    /* Form Styles - GitHub Dark Inputs */
    .form-group {
        margin-bottom: 22px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        color: var(--fg-muted);
        letter-spacing: 0.01em;
        text-transform: uppercase;
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
        border-radius: 12px;
        color: var(--fg-default);
        font-size: 0.9rem;
        font-family: 'Inter', monospace;
        transition: all 0.2s ease;
    }

    .form-control::placeholder {
        color: var(--fg-subtle);
        font-size: 0.85rem;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px var(--accent-blue-soft);
    }

    /* Login Button - GitHub Primary */
    .btn-login {
        width: 100%;
        padding: 12px 16px;
        background: var(--accent-blue);
        border: none;
        border-radius: 12px;
        color: #fff;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 8px;
    }

    .btn-login:hover {
        background: var(--accent-blue-hover);
        transform: translateY(-2px);
        box-shadow: var(--shadow-blue);
    }

    .btn-login:active {
        transform: translateY(0);
    }

    /* Footer Links */
    .login-footer {
        text-align: center;
        margin-top: 28px;
        padding-top: 20px;
        border-top: 1px solid var(--border-muted);
    }

    .login-footer p {
        color: var(--fg-muted);
        font-size: 0.75rem;
        margin-bottom: 12px;
    }

    .login-footer p i {
        margin-right: 6px;
        font-size: 0.7rem;
        color: var(--accent-blue);
    }

    .login-footer a {
        color: var(--accent-blue);
        text-decoration: none;
        display: inline-block;
        margin: 6px 8px;
        font-size: 0.8rem;
        transition: color 0.2s;
    }

    .login-footer a:hover {
        color: var(--accent-blue-hover);
        text-decoration: underline;
    }

    .login-footer a i {
        margin-right: 6px;
        font-size: 0.75rem;
    }

    /* Responsive */
    @media (max-width: 520px) {
        .login-card {
            padding: 32px 24px;
        }
        
        .login-header-icon {
            width: 72px;
            height: 72px;
        }
        
        .brand h1 {
            font-size: 1.5rem;
        }
        
        .btn-login {
            padding: 11px 14px;
        }
    }

    @media (max-width: 380px) {
        .login-card {
            padding: 28px 20px;
        }
        
        .login-footer a {
            display: block;
            margin: 8px 0;
        }
    }

    /* Reduced motion preference */
    @media (prefers-reduced-motion: reduce) {
        .alert,
        .login-card,
        .btn-login,
        .login-header-icon {
            transition: none;
            animation: none;
        }
        
        .login-card:hover {
            transform: none;
        }
    }
</style>

<?php
$content = ob_get_clean();

// Simple layout without sidebar for login
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo htmlspecialchars($appName); ?></title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#0d1117">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Admin Panel">
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <?php echo $content; ?>

    <script>
        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js?v=git')
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