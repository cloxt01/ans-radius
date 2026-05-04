<?php
/**
 * Forgot Password Page
 */

require_once '../includes/auth.php';

// Check if already logged in
if (isAdminLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = 'Lupa Password';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        setFlash('error', 'Email harus diisi!');
        redirect('forgot_password.php');
    }
    
    // Check if email exists
    $admin = fetchOne("SELECT * FROM admin_users WHERE email = ?", [$email]);
    
    if (!$admin) {
        // Don't reveal if email exists or not (security)
        setFlash('success', 'Jika email terdaftar, instruksi reset password akan dikirim.');
        redirect('login.php');
    }
    
    // Generate reset token
    $resetToken = bin2hex(random_bytes(32));
    $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Save reset token to database (via settings table)
    update('admin_users', [
        'reset_token' => $resetToken,
        'reset_expiry' => $resetExpiry
    ], 'id = ?', [$admin['id']]);
    
    // Generate reset link
    $resetLink = APP_URL . '/admin/reset_password.php?token=' . $resetToken;
    
    // Send email with reset link (simulated)
    // In production, use actual email sending
    $subject = 'Reset Password - ' . APP_NAME;
    $message = "Halo {$admin['username']},\n\n";
    $message .= "Anda meminta reset password untuk akun admin.\n\n";
    $message .= "Klik link berikut untuk reset password:\n";
    $message .= $resetLink . "\n\n";
    $message .= "Link ini akan expired dalam 1 jam.\n\n";
    $message .= "Jika Anda tidak meminta reset password, abaikan email ini.\n\n";
    $message .= "Terima kasih.";
    
    // Log the reset request
    logActivity('PASSWORD_RESET_REQUEST', "Email: {$email}");
    
    setFlash('success', 'Instruksi reset password telah dikirim ke email Anda.');
    redirect('login.php');
}

ob_start();
?>

<div class="login-wrap">
    <div class="login-card">
        <div class="brand">
            <img src="<?php echo APP_URL; ?>/assets/icons/icon.webp" class="login-header-icon" alt="Icon">
            <h1>Lupa Password</h1>
            <p class="login-subtitle">Masukkan email admin untuk reset password</p>
        </div>
        
        <?php if (hasFlash('error')): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars(getFlash('error')); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Email Admin</label>
                <input type="email" name="email" class="form-control" placeholder="email@contoh.com" required autofocus>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-paper-plane"></i> Kirim Link Reset
            </button>
        </form>
        
        <div class="login-footer">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
        </div>
    </div>
</div>

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
    }

    .login-wrap {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: var(--bg-main);
        font-family: 'Inter', sans-serif;
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

    .brand {
        text-align: center;
        margin-bottom: 28px;
    }

    .login-header-icon {
        width: 92px;
        height: 92px;
        margin: 0 auto 12px;
        display: block;
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

    .btn-login {
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

    .btn-login:hover {
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

<?php
$content = ob_get_clean();
echo $content;
