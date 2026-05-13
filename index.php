<?php
/**
 * GEMBOK ISP - Modern Landing Page
 */

// Check for installation
if (!file_exists(__DIR__ . '/includes/installed.lock')) {
    header("Location: install.php");
    exit;
}

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'public_register') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        redirect('index.php?reg=csrf');
    }

    $name = trim((string) sanitize($_POST['name'] ?? ''));
    $phoneRaw = trim((string) sanitize($_POST['phone'] ?? ''));
    $address = trim((string) sanitize($_POST['address'] ?? ''));
    $package = trim((string) sanitize($_POST['package'] ?? ''));
    $notes = trim((string) sanitize($_POST['notes'] ?? ''));

    if (mb_strlen($name) < 3 || mb_strlen($phoneRaw) < 8 || mb_strlen($address) < 6) {
        redirect('index.php?reg=invalid');
    }

    $digits = preg_replace('/\D+/', '', $phoneRaw);
    if ($digits === '') {
        redirect('index.php?reg=invalid');
    }
    if (strpos($digits, '0') === 0) {
        $digits = '62' . substr($digits, 1);
    } elseif (strpos($digits, '62') !== 0) {
        $digits = '62' . $digits;
    }

    $appNameForMsg = trim((string) getSetting('app_name', defined('APP_NAME') ? APP_NAME : ''));
    if ($appNameForMsg === '') {
        $appNameForMsg = 'GEMBOK';
    }
    $adminWa = trim((string) getSetting('WHATSAPP_ADMIN_NUMBER', ''));

    $adminMsg = "Pendaftaran Pelanggan Baru\n\n";
    $adminMsg .= "Nama: {$name}\n";
    $adminMsg .= "No HP: {$digits}\n";
    $adminMsg .= "Alamat: {$address}\n";
    if ($package !== '') {
        $adminMsg .= "Paket: {$package}\n";
    }
    if ($notes !== '') {
        $adminMsg .= "Catatan: {$notes}\n";
    }
    $adminMsg .= "\nSumber: Landing Page";
    if (function_exists('getWhatsAppFooter')) {
        $adminMsg .= getWhatsAppFooter();
    }

    $customerMsg = "Halo {$name},\n\n";
    $customerMsg .= "Terima kasih, pendaftaran Anda sudah kami terima.\n";
    $customerMsg .= "Tim {$appNameForMsg} akan menghubungi Anda untuk proses selanjutnya.\n";
    if ($adminWa !== '') {
        $adminDigits = preg_replace('/\D+/', '', $adminWa);
        if ($adminDigits !== '') {
            if (strpos($adminDigits, '0') === 0) {
                $adminDigits = '62' . substr($adminDigits, 1);
            } elseif (strpos($adminDigits, '62') !== 0) {
                $adminDigits = '62' . $adminDigits;
            }
            $customerMsg .= "\nCS/WA: {$adminDigits}";
        }
    }
    if (function_exists('getWhatsAppFooter')) {
        $customerMsg .= getWhatsAppFooter();
    }

    $adminDigits = preg_replace('/\D+/', '', $adminWa);
    if ($adminDigits !== '') {
        if (strpos($adminDigits, '0') === 0) {
            $adminDigits = '62' . substr($adminDigits, 1);
        } elseif (strpos($adminDigits, '62') !== 0) {
            $adminDigits = '62' . $adminDigits;
        }
        sendWhatsApp($adminDigits, $adminMsg);
    }

    $techs = fetchAll("SELECT phone FROM technician_users WHERE status = 'active' AND phone IS NOT NULL AND phone <> '' LIMIT 1");
    foreach ($techs as $t) {
        $tDigits = preg_replace('/\D+/', '', (string) ($t['phone'] ?? ''));
        if ($tDigits === '') {
            continue;
        }
        if (strpos($tDigits, '0') === 0) {
            $tDigits = '62' . substr($tDigits, 1);
        } elseif (strpos($tDigits, '62') !== 0) {
            $tDigits = '62' . $tDigits;
        }
        sendWhatsApp($tDigits, $adminMsg);
    }

    sendWhatsApp($digits, $customerMsg);

    redirect('index.php?reg=success');
}

// Fetch Packages
$packages = [];
try {
    $pdo = getDB();
    $packages = $pdo->query("SELECT * FROM packages ORDER BY price ASC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fail silently
}

// Fetch FAQs
$faqs = [];
try {
    $faqs = fetchAll("SELECT id, question, answer FROM faqs WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    if (!is_array($faqs)) {
        $faqs = [];
    }
} catch (Exception $e) {
    // Table might not exist yet
    $faqs = [];
}

// App settings
$appName = getSetting('app_name', 'ANS Radius');

// Landing settings
$heroTitle = getSiteSetting('hero_title', 'Internet Cepat <br>Tanpa Batas');
$heroDesc = getSiteSetting('hero_description', 'Nikmati koneksi internet fiber optic super cepat, stabil, dan unlimited untuk kebutuhan rumah maupun bisnis Anda. Gabung sekarang!');
$contactPhone = getSiteSetting('contact_phone', '+62 812-3456-7890');
$contactEmail = getSiteSetting('contact_email', 'info@gembok.net');
$contactAddress = getSiteSetting('contact_address', 'Jakarta, Indonesia');
$footerAbout = getSiteSetting('footer_about', 'Penyedia layanan internet terpercaya dengan jaringan fiber optic berkualitas untuk menunjang aktivitas digital Anda.');

// Feature settings
$f1_title = getSiteSetting('feature_1_title', 'Kecepatan Tinggi');
$f1_desc = getSiteSetting('feature_1_desc', 'Koneksi fiber optic dengan kecepatan simetris upload dan download.');

$f2_title = getSiteSetting('feature_2_title', 'Unlimited Quota');
$f2_desc = getSiteSetting('feature_2_desc', 'Akses internet sepuasnya tanpa batasan kuota (FUP).');

$f3_title = getSiteSetting('feature_3_title', 'Support 24/7');
$f3_desc = getSiteSetting('feature_3_desc', 'Tim teknis kami siap membantu Anda kapanpun jika terjadi gangguan.');

// Social settings
$s_fb = getSiteSetting('social_facebook', '#');
$s_ig = getSiteSetting('social_instagram', '#');
$s_tw = getSiteSetting('social_twitter', '#');
$s_yt = getSiteSetting('social_youtube', '#');

// Theme settings
$themeColor = getSiteSetting('theme_color', 'neon');

// Landing template settings
$landingTemplate = getSiteSetting('landing_template', 'neon');

// Map template names to file paths
$templateFiles = [
    'neon' => 'templates/landing/template_neon.php',
    'modern' => 'templates/landing/template_modern.php',
    'corporate' => 'templates/landing/template_corporate.php',
    'minimal' => 'templates/landing/template_minimal.php',
    'dark' => 'templates/landing/template_dark.php',
    'glassmorphism' => 'templates/landing/template_glassmorphism.php',
    'neumorphism' => 'templates/landing/template_neumorphism.php',
    'bento' => 'templates/landing/template_bento.php',
    'modern_ultra' => 'templates/landing/template_modern_ultra.php'
];

// Validate template selection
$templateFile = isset($templateFiles[$landingTemplate]) ? $templateFiles[$landingTemplate] : $templateFiles['neon'];

$voucherOrderUrl = rtrim(APP_URL, '/') . '/voucher-order.php';

ob_start();
if (file_exists(__DIR__ . '/' . $templateFile)) {
    include __DIR__ . '/' . $templateFile;
} else {
    include __DIR__ . '/templates/landing/template_neon.php';
}
$html = ob_get_clean();

$showPublicButtons = !isAdminLoggedIn() && !isSalesLoggedIn() && !isTechnicianLoggedIn() && !isCustomerLoggedIn();
$voucherButton = '';
if ($showPublicButtons) {
    $voucherButton = '<a href="' . htmlspecialchars($voucherOrderUrl, ENT_QUOTES, 'UTF-8') . '" style="position:fixed;right:16px;bottom:16px;z-index:9999;background:#22d3ee;color:#0f172a;padding:10px 14px;border-radius:999px;font-weight:700;text-decoration:none;box-shadow:0 8px 20px rgba(0,0,0,.25);font-family:Arial,sans-serif;font-size:13px;">Order Voucher</a>';
}

$csrf = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
$pkgOptions = '<option value="" style="color: var(--text-muted);">Pilih paket (opsional)</option>';
foreach ($packages as $p) {
    $pName = trim((string) ($p['name'] ?? ''));
    if ($pName === '') continue;
    $pkgOptions .= '<option value="' . htmlspecialchars($pName, ENT_QUOTES, 'UTF-8') . '" style="color: var(--text-muted);">' . htmlspecialchars($pName, ENT_QUOTES, 'UTF-8') . '</option>';
}
$pkgOptions .= '<option value="Lainnya">Lainnya</option>';

$registerModal = '
<div id="gembokRegOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:10000;display:none;align-items:center;justify-content:center;padding:20px;">
  <div style="width:100%;max-width:520px;background:var(--bg-primary, #161b22);border:1px solid var(--border-default, #30363d);border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);overflow:hidden;font-family:\'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif;">
    
    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px;background:linear-gradient(135deg, rgba(47,129,247,0.12), rgba(63,185,80,0.05));border-bottom:1px solid var(--border-default, #30363d);">
      <div style="display:flex;align-items:center;gap:10px;">
        <i class="fa-solid fa-user-plus" style="color:var(--accent-blue, #2f81f7);font-size:1.2rem;"></i>
        <span style="font-weight:700;font-size:1.1rem;color:var(--fg-default, #e6edf3);">Pendaftaran Pelanggan Baru</span>
      </div>
      <button type="button" onclick="window.__gembokCloseRegisterModal && window.__gembokCloseRegisterModal()" style="background:transparent;border:none;color:var(--fg-muted, #7d8590);font-size:22px;cursor:pointer;line-height:1;transition:color 0.2s;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;" onmouseover="this.style.color=\'var(--fg-default)\'" onmouseout="this.style.color=\'var(--fg-muted)\'">×</button>
    </div>
    
    <!-- Form -->
    <form method="POST" action="' . htmlspecialchars(rtrim(APP_URL, '/') . '/index.php', ENT_QUOTES, 'UTF-8') . '" style="padding:20px;">
      <input type="hidden" name="action" value="public_register">
      <input type="hidden" name="csrf_token" value="' . $csrf . '">
      
      <!-- Nama & No HP Row -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div>
          <label style="display:block;font-size:0.75rem;color:var(--fg-muted, #7d8590);margin-bottom:6px;font-weight:600;">
            <i class="fa-regular fa-user" style="margin-right:4px;font-size:0.7rem;"></i> Nama Lengkap
          </label>
          <input name="name" required minlength="3" placeholder="Masukkan nama lengkap" 
                 style="width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--border-default, #30363d);background:var(--bg-canvas, #0d1117);color:var(--fg-default, #e6edf3);font-size:0.85rem;transition:border-color 0.2s, box-shadow 0.2s;"
                 onfocus="this.style.borderColor=\'#2f81f7\';this.style.outline=\'none\'" 
                 onblur="this.style.borderColor=\'var(--border-default, #30363d)\'">
        </div>
        <div>
          <label style="display:block;font-size:0.75rem;color:var(--fg-muted, #7d8590);margin-bottom:6px;font-weight:600;">
            <i class="fa-brands fa-whatsapp" style="margin-right:4px;font-size:0.7rem;"></i> No HP (WA)
          </label>
          <input name="phone" required minlength="8" placeholder="08xxxx atau 62xxxx" 
                 style="width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--border-default, #30363d);background:var(--bg-canvas, #0d1117);color:var(--fg-default, #e6edf3);font-size:0.85rem;transition:border-color 0.2s, box-shadow 0.2s;"
                 onfocus="this.style.borderColor=\'#2f81f7\';this.style.outline=\'none\'" 
                 onblur="this.style.borderColor=\'var(--border-default, #30363d)\'">
        </div>
      </div>
      
      <!-- Alamat -->
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:0.75rem;color:var(--fg-muted, #7d8590);margin-bottom:6px;font-weight:600;">
          <i class="fa-regular fa-location-dot" style="margin-right:4px;font-size:0.7rem;"></i> Alamat
        </label>
        <textarea name="address" required minlength="6" rows="2" placeholder="Masukkan alamat lengkap" 
                  style="width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--border-default, #30363d);background:var(--bg-canvas, #0d1117);color:var(--fg-default, #e6edf3);font-size:0.85rem;resize:vertical;font-family:inherit;transition:border-color 0.2s;"
                  onfocus="this.style.borderColor=\'#2f81f7\';this.style.outline=\'none\'" 
                  onblur="this.style.borderColor=\'var(--border-default, #30363d)\'"></textarea>
      </div>
      
      <!-- Paket Options -->
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:0.75rem;color:var(--fg-muted, #7d8590);margin-bottom:6px;font-weight:600;">
          <i class="fa-regular fa-layer-group" style="margin-right:4px;font-size:0.7rem;"></i> Paket (opsional)
        </label>
        <select name="package" id="gembokRegPackage" 
                style="width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--border-default, #30363d);background:var(--bg-canvas, #0d1117);color:var(--fg-default, #e6edf3);font-size:0.85rem;cursor:pointer;transition:border-color 0.2s;"
                onfocus="this.style.borderColor=\'#2f81f7\';this.style.outline=\'none\'" 
                onblur="this.style.borderColor=\'var(--border-default, #30363d)\'">
          ' . $pkgOptions . '
        </select>
      </div>
      
      <!-- Catatan -->
      <div style="margin-bottom:20px;">
        <label style="display:block;font-size:0.75rem;color:var(--fg-muted, #7d8590);margin-bottom:6px;font-weight:600;">
          <i class="fa-regular fa-pen" style="margin-right:4px;font-size:0.7rem;"></i> Catatan (opsional)
        </label>
        <input name="notes" placeholder="Contoh: lokasi, patokan rumah, jam dihubungi" 
               style="width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--border-default, #30363d);background:var(--bg-canvas, #0d1117);color:var(--fg-default, #e6edf3);font-size:0.85rem;transition:border-color 0.2s;"
               onfocus="this.style.borderColor=\'#2f81f7\';this.style.outline=\'none\'" 
               onblur="this.style.borderColor=\'var(--border-default, #30363d)\'">
      </div>
      
      <!-- Buttons -->
      <div style="display:flex;gap:12px;justify-content:flex-end;">
        <button type="button" onclick="window.__gembokCloseRegisterModal && window.__gembokCloseRegisterModal()" 
                style="background:transparent;border:1px solid var(--border-default, #30363d);color:var(--fg-muted, #7d8590);padding:10px 18px;border-radius:10px;cursor:pointer;font-weight:500;font-size:0.85rem;transition:all 0.2s;"
                onmouseover="this.style.background=\'var(--bg-tertiary, #21262d)\';this.style.color=\'var(--fg-default)\'" 
                onmouseout="this.style.background=\'transparent\';this.style.color=\'var(--fg-muted)\'">
          Batal
        </button>
        <button type="submit" 
                style="background:linear-gradient(135deg, var(--accent-blue, #2f81f7), #1a5fb4);border:none;color:#ffffff;padding:10px 20px;border-radius:10px;cursor:pointer;font-weight:600;font-size:0.85rem;transition:all 0.2s;box-shadow:0 2px 8px rgba(47,129,247,0.3);"
                onmouseover="this.style.transform=\'translateY(-1px)\';this.style.boxShadow=\'0 4px 12px rgba(47,129,247,0.4)\'" 
                onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 2px 8px rgba(47,129,247,0.3)\'">
          <i class="fa-regular fa-paper-plane" style="margin-right:6px;"></i> Kirim Pendaftaran
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
    const overlay = document.getElementById("gembokRegOverlay");
    const packageSelect = document.getElementById("gembokRegPackage");
    
    // Fungsi open modal
    window.__gembokOpenRegisterModal = function() { 
        if (overlay) {
            overlay.style.display = "flex";
            document.body.style.overflow = "hidden";
        }
    };
    
    // Fungsi close modal
    window.__gembokCloseRegisterModal = function() { 
        if (overlay) {
            overlay.style.display = "none";
            document.body.style.overflow = "";
        }
    };
    
    // Fungsi open modal dengan paket tertentu
    window.__gembokOpenRegisterModalWithPackage = function(pkg) {
        if (packageSelect && typeof pkg === "string" && pkg !== "") {
            let found = false;
            for (let i = 0; i < packageSelect.options.length; i++) {
                if (packageSelect.options[i].value === pkg) {
                    packageSelect.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            if (!found && packageSelect.options.length > 0) {
                packageSelect.value = "Lainnya";
            }
        }
        window.__gembokOpenRegisterModal();
    };
    
    // Tutup modal jika klik overlay
    if (overlay) {
        overlay.addEventListener("click", function(e) { 
            if (e.target === overlay) {
                window.__gembokCloseRegisterModal();
            }
        });
    }
    
    // Tutup modal dengan tombol ESC
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") {
            window.__gembokCloseRegisterModal();
        }
    });
    
    // Handle URL parameter untuk auto-open modal
    const p = new URLSearchParams(window.location.search);
    const reg = p.get("reg");
    
    if (reg === "success") {
        alert("✓ Pendaftaran berhasil dikirim! Tim kami akan segera menghubungi Anda.");
    }
    if (reg === "invalid") {
        alert("⚠️ Data belum lengkap. Mohon cek kembali Nama, No HP, dan Alamat.");
    }
    if (reg === "csrf") {
        alert("⚠️ Sesi tidak valid. Silakan refresh halaman dan coba lagi.");
    }
    if (reg === "open") {
        const pkg = p.get("pkg") || "";
        setTimeout(function() {
            window.__gembokOpenRegisterModalWithPackage(pkg);
        }, 300);
    }
    
    // Prevent form double submission
    const form = document.querySelector("#gembokRegOverlay form");
    if (form) {
        form.addEventListener("submit", function(e) {
            const submitBtn = form.querySelector("button[type=\'submit\']");
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = \'<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...\';
            }
        });
    }
})();
</script>
';
$footerLinks = '<div style="position:fixed;left:16px;bottom:18px;z-index:9999;color:var(--text-muted);font-size:13px;display:flex;gap:12px;align-items:center;">'
  . '<a href="page.php?page=about" style="color:var(--text-muted);">Tentang Kami</a>'
  . '<a href="page.php?page=terms" style="color:var(--text-muted);">Syarat & Ketentuan</a>'
  . '<a href="page.php?page=privacy" style="color:var(--text-muted);">Kebijakan Privasi</a>'
  . '</div>';

$inject = $voucherButton . $footerLinks . $registerModal;
if (stripos($html, '</body>') !== false) {
    $html = preg_replace('/<\/body>/i', $inject . '</body>', $html, 1);
} else {
    $html .= $inject;
}
echo $html;
?>
