<?php
/**
 * ANS Radius Landing Page Template
 * Modern ISP Landing Page with Fiber Optic Focus
 */

// Prevent direct access
if (!defined('APP_URL')) {
    define('APP_URL', 'https://cloxt.tech');
}

// Configuration
$appName = isset($appName) ? $appName : 'ANS Radius';
$heroTitle = isset($heroTitle) ? $heroTitle : 'Internet Cepat <br>Tanpa Batas';
$heroDesc = isset($heroDesc) ? $heroDesc : 'Nikmati koneksi internet fiber optic super cepat, stabil, dan unlimited untuk kebutuhan rumah maupun bisnis Anda. Gabung sekarang!';

// Features data
$features = isset($features) ? $features : [
    [
        'icon' => 'fas fa-bolt',
        'title' => 'Kecepatan Tinggi',
        'desc' => 'Koneksi fiber optic dengan kecepatan simetris upload dan download.'
    ],
    [
        'icon' => 'fas fa-infinity',
        'title' => 'Unlimited Quota',
        'desc' => 'Akses internet sepuasnya tanpa batasan kuota (FUP).'
    ],
    [
        'icon' => 'fas fa-headset',
        'title' => 'Support 24/7',
        'desc' => 'Tim teknis kami siap membantu Anda kapanpun jika terjadi gangguan.'
    ],
    [
        'icon' => 'fas fa-shield-halved',
        'title' => 'Koneksi Stabil',
        'desc' => 'Arsitektur jaringan kami dirancang untuk latensi rendah dan uptime tinggi.'
    ],
    [
        'icon' => 'fas fa-gauge-high',
        'title' => 'Monitoring Waktu Nyata',
        'desc' => 'Tim NOC memantau performa jaringan 24/7 agar gangguan cepat ditangani.'
    ],
    [
        'icon' => 'fas fa-screwdriver-wrench',
        'title' => 'Instalasi Cepat',
        'desc' => 'Proses survey sampai aktivasi dibuat ringkas agar internet segera aktif.'
    ]
];

// Steps data
$steps = isset($steps) ? $steps : [
    [
        'num' => '01',
        'title' => 'Daftar Online',
        'desc' => 'Isi formulir pendaftaran dari landing page. Tim kami langsung menerima notifikasi.'
    ],
    [
        'num' => '02',
        'title' => 'Survey Lokasi',
        'desc' => 'Teknisi melakukan pengecekan area dan menyiapkan rute kabel terbaik.'
    ],
    [
        'num' => '03',
        'title' => 'Aktivasi',
        'desc' => 'Instalasi selesai, akun aktif, dan Anda langsung bisa menikmati internet.'
    ]
];

// Testimonials
$testimonials = isset($testimonials) ? $testimonials : [
    [
        'quote' => 'Setelah pindah ke layanan ini, live streaming jualan saya jauh lebih stabil.',
        'author' => 'Rina A.',
        'role' => 'Owner Toko Online'
    ],
    [
        'quote' => 'Teknisi datang cepat, pemasangan rapi, dan dukungannya responsif.',
        'author' => 'Arif N.',
        'role' => 'Warga Perumahan'
    ],
    [
        'quote' => 'Upload project besar jadi lancar, nggak drama putus-putus lagi.',
        'author' => 'Dimas K.',
        'role' => 'Freelancer'
    ],
    [
        'quote' => 'Kelas daring jadi nyaman karena koneksinya konsisten tiap hari.',
        'author' => 'Siska M.',
        'role' => 'Guru Online'
    ]
];

// Pricing packages
$packages = isset($packages) ? $packages : [
    [
        'name' => 'Starter',
        'price' => 150000,
        'description' => 'Paket hemat untuk kebutuhan harian.',
        'icon' => 'cloud.svg'
    ],
    [
        'name' => 'Family',
        'price' => 250000,
        'description' => 'Ideal untuk streaming dan belajar online.',
        'icon' => 'cloud.svg',
        'highlight' => true
    ],
    [
        'name' => 'Pro',
        'price' => 400000,
        'description' => 'Untuk produktivitas tinggi dan kebutuhan bisnis.',
        'icon' => 'cloud.svg'
    ]
];

// Contact info
$contactPhone = isset($contactPhone) ? $contactPhone : '+62858-6340-9811';
$contactEmail = isset($contactEmail) ? $contactEmail : 'info@ansradius.id';
$contactAddress = isset($contactAddress) ? $contactAddress : 'Serang, Indonesia';

// Social media
$s_fb = isset($s_fb) ? $s_fb : '#';
$s_ig = isset($s_ig) ? $s_ig : '#';
$s_tw = isset($s_tw) ? $s_tw : '#';
$s_yt = isset($s_yt) ? $s_yt : '#';

$footerAbout = isset($footerAbout) ? $footerAbout : 'Penyedia layanan internet terpercaya dengan jaringan fiber optic berkualitas untuk menunjang aktivitas digital Anda.';

// Helper function
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $appName; ?> - Internet Cepat & Stabil</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#050810">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo $appName; ?>">
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    <link rel="icon" href="/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg: #050810;
            --surface: #0c1424;
            --surface-2: #101c32;
            --text: #eef5ff;
            --muted: #a8bbd7;
            --cyan: #00d4ff;
            --blue: #2563eb;
            --teal: #14b8a6;
            --line: rgba(255,255,255,.1);
        }
        /* Product tour styles */
        .tour-overlay {
            position: fixed;
            inset: 0;
            background: rgba(2,8,20,0.6);
            z-index: 2050;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .tour-tooltip {
            position: absolute;
            z-index: 2105;
            max-width: 420px;
            background: linear-gradient(180deg, rgba(12,20,36,0.98), rgba(8,14,26,0.98));
            border: 1px solid rgba(255,255,255,.06);
            padding: 14px;
            border-radius: 10px;
            color: var(--text);
            box-shadow: 0 14px 40px rgba(2,8,20,0.6);
            pointer-events: auto;
        }

        .tour-tooltip:after {
            content: '';
            position: absolute;
            width: 14px; height: 14px;
            transform: rotate(45deg);
            background: inherit;
            border-left: 1px solid rgba(255,255,255,.06);
            border-top: 1px solid rgba(255,255,255,.06);
            box-shadow: inherit;
            display: block;
        }

        .tour-tooltip.arrow-top:after {
            top: -8px; left: calc(50% - 7px);
        }

        .tour-tooltip.arrow-bottom:after {
            bottom: -8px; left: calc(50% - 7px);
        }

        .tour-tooltip h4 { margin-bottom: 6px; font-size: 1rem; }
        .tour-tooltip p { color: var(--muted); font-size: .92rem; margin-bottom: 8px; }
        .tour-controls { display:flex; gap:8px; justify-content:flex-end; }

        .tour-highlight { position: relative; z-index: 2100; box-shadow: 0 0 0 6px rgba(0,212,255,0.06), 0 10px 30px rgba(2,8,20,0.6); border-radius: 12px; }

        .tour-btn { padding: 8px 10px; border-radius: 8px; border: none; cursor: pointer; font-weight:700; }
        .tour-btn.ghost { background: rgba(255,255,255,.03); color: var(--muted); }
        .tour-btn.primary { background: linear-gradient(135deg, var(--cyan), var(--blue)); color:#fff; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Manrope', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif;
            background: radial-gradient(circle at 15% 10%, rgba(0,212,255,.13), transparent 35%),
                        radial-gradient(circle at 85% 20%, rgba(37,99,235,.2), transparent 40%),
                        var(--bg);
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
        }

        h1, h2, h3, .brand { font-family: 'Space Grotesk', 'Manrope', sans-serif; letter-spacing: -.02em; }

        .container { width: min(1120px, 92vw); margin: 0 auto; }

        .glass {
            background: rgba(13, 21, 38, 0.68);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--line);
            border-radius: 16px;
        }

        .gradient-text {
            background: linear-gradient(135deg, var(--cyan), var(--blue), var(--teal));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            border-radius: 12px;
            padding: 12px 18px;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
            transition: .25s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--cyan), var(--blue));
            color: #fff;
            box-shadow: 0 8px 24px rgba(0, 212, 255, 0.25);
        }

        .btn-primary:hover { transform: translateY(-2px); }

        .btn-ghost {
            border: 1px solid rgba(255,255,255,.18);
            color: var(--text);
            background: rgba(255,255,255,.03);
        }

        .btn-login {
            border: 1px solid rgba(255,255,255,.18);
            color: var(--text);
            background: rgba(255,255,255,.03);
        }

        .btn-login:hover {
            border-color: rgba(0,212,255,.35);
            color: #b7f1ff;
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: .25s ease;
        }

        .navbar-inner {
            width: min(1120px, 92vw);
            margin: 10px auto;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            text-decoration: none;
            font-weight: 700;
            font-size: 1.06rem;
        }

        .brand-logo {
            width: clamp(36px, 8vw, 60px);
            height: auto;
            display: block;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .nav-links a {
            color: var(--muted);
            text-decoration: none;
            font-weight: 600;
            font-size: .95rem;
        }

        .nav-links a:hover { color: var(--cyan); }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .menu-toggle {
            display: none;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.18);
            color: var(--text);
            background: rgba(255,255,255,.03);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .mobile-actions {
            display: none;
            width: min(1120px, 92vw);
            margin: 0 auto;
            padding: 10px;
            border-radius: 14px;
        }

        .mobile-actions.open {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        /* Hero Section */
        section { padding: 96px 0; }

        .hero {
            padding-top: 140px;
            padding-bottom: 80px;
            min-height: 95vh;
            display: grid;
            align-items: center;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 42px;
            align-items: center;
        }

        .hero .tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(0,212,255,.35);
            background: rgba(0,212,255,.1);
            color: #9feaff;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: .78rem;
            margin-bottom: 18px;
            font-weight: 700;
        }

        .hero h1 {
            font-size: clamp(2rem, 5.5vw, 4rem);
            line-height: 1.05;
            margin-bottom: 14px;
        }

        .hero p {
            color: var(--muted);
            max-width: 620px;
            margin-bottom: 26px;
        }

        .hero-cta { display: flex; gap: 12px; flex-wrap: wrap; }

        .hero-card {
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        .hero-stat {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 16px;
        }

        .mini {
            padding: 12px;
            border-radius: 12px;
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.08);
        }

        .mini strong { display: block; font-size: 1.2rem; }
        .mini span { color: var(--muted); font-size: .82rem; }

        /* Features */
        .section-head {
            text-align: center;
            margin-bottom: 36px;
        }

        .section-head h2 {
            font-size: clamp(1.8rem, 3.8vw, 3rem);
            margin-top: 10px;
            margin-bottom: 8px;
        }

        .section-head p {
            color: var(--muted);
            max-width: 700px;
            margin: 0 auto;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .feature {
            padding: 20px;
            transition: .25s ease;
        }

        .feature:hover {
            transform: translateY(-4px);
            border-color: rgba(0,212,255,.34);
        }

        .feature i {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            margin-bottom: 14px;
            color: var(--cyan);
            background: rgba(0,212,255,.11);
        }

        .feature h3 { margin-bottom: 8px; font-size: 1.08rem; }
        .feature p { color: var(--muted); font-size: .92rem; }

        /* Steps */
        .steps-wrap {
            display: grid;
            gap: 14px;
        }

        .step {
            display: grid;
            grid-template-columns: 66px 1fr;
            gap: 16px;
            padding: 16px;
        }

        .step .num {
            width: 52px;
            height: 52px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-weight: 800;
            color: #9feaff;
            background: rgba(0,212,255,.14);
            border: 1px solid rgba(0,212,255,.35);
        }

        /* Testimonials */
        .testimonials {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .quote { padding: 18px; }
        .quote p { color: #d7e5ff; margin-bottom: 14px; }
        .quote small { color: var(--muted); }

        /* Pricing */
        .pricing {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            align-items: stretch;
        }

        .plan {
            padding: 22px;
            position: relative;
            text-align: center;
            display: flex;
            gap: 15px;
            flex-direction: column;
            align-items: center;
        }

        .plan:hover {
            border-color: rgb(255 255 255 / 0.82);
            cursor: pointer;
        }

        .plan.highlight {
            border-color: rgba(0,212,255,.45);
            box-shadow: 0 10px 30px rgba(0,212,255,.15);
        }

        .plan .price {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
        }

        .plan ul {
            list-style: none;
            display: grid;
            gap: 8px;
            margin: 0 auto 16px;
            width: 100%;
            max-width: 280px;
        }

        .plan ul li {
            color: #d8e6ff;
            font-size: .9rem;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
        }

        .plan ul li i {
            color: var(--cyan);
            font-size: .75rem;
        }

        /* CTA */
        .cta {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
            text-align: center;
            padding: 34px;
        }

        /* Footer */
        footer {
            padding: 46px 0 28px;
            border-top: 1px solid var(--line);
            margin-top: 68px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.2fr .8fr .8fr;
            gap: 18px;
        }

        .footer-grid ul { list-style: none; display: grid; gap: 8px; }
        .footer-grid a { color: var(--muted); text-decoration: none; }
        .footer-grid a:hover { color: var(--cyan); }

        .footer-bar {
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: var(--muted);
            font-size: .85rem;
        }

        /* Animations */
        .reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity .6s ease, transform .6s ease;
        }

        .reveal.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            border-radius: 16px;
            padding: 28px;
        }

        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
        }

        .login-role-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 1200;
            background: rgba(2, 8, 20, .68);
            backdrop-filter: blur(5px);
        }

        .login-role-modal {
            width: min(520px, 96vw);
            border-radius: 18px;
            border: 1px solid var(--line);
            background: rgba(10, 18, 33, .92);
            overflow: hidden;
        }

        .login-role-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            background: linear-gradient(135deg, rgba(0,212,255,.12), rgba(37,99,235,.08));
        }

        .login-role-body {
            padding: 14px;
            display: grid;
            gap: 10px;
        }

        .login-role-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,.02);
            text-decoration: none;
            color: var(--text);
            transition: .2s ease;
        }

        .login-role-link:hover {
            border-color: rgba(0,212,255,.35);
            background: rgba(0,212,255,.08);
        }

        .login-role-close {
            border: 1px solid var(--line);
            background: rgba(255,255,255,.04);
            border-radius: 8px;
            width: 34px;
            height: 34px;
            cursor: pointer;
            color: var(--text);
        }

        .login-role-close:hover { background: rgba(0,212,255,.2); }

        /* Responsive */
        @media (max-width: 980px) {
            .hero-grid,
            .feature-grid,
            .pricing,
            .footer-grid,
            .testimonials {
                grid-template-columns: 1fr;
            }

            .nav-links { display: none; }
            .nav-actions { display: none; }
            .menu-toggle { display: inline-flex; }
            .hero { min-height: auto; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="navbar-inner glass" style="background: rgba(8, 14, 26, 0.88);">
        <a href="#home" class="brand">
            <img src="<?php echo APP_URL; ?>/images/icon.png" class="brand-logo" alt="Logo">
        </a>
        <div class="nav-links">
            <a href="#features">Fitur</a>
            <a href="#how-it-works">Cara Kerja</a>
            <a href="#pricing">Paket</a>
            <a href="#faq">FAQ</a>
        </div>
        <div class="nav-actions">
            <button type="button" class="btn btn-login" id="openLoginRoleModal">
                <i class="fas fa-right-to-bracket"></i> Masuk
            </button>
            <button type="button" class="btn btn-primary" onclick="openRegisterModal()">
                Daftar Sekarang
            </button>
            <button type="button" class="btn btn-ghost" id="startTourBtn">
                <i class="fas fa-compass"></i> Mulai Tour
            </button>
        </div>
        <button type="button" class="menu-toggle" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    <div class="mobile-actions glass" id="mobileActions">
        <button type="button" class="btn btn-login" id="openLoginRoleModalMobile">
            <i class="fas fa-right-to-bracket"></i> Masuk
        </button>
        <button type="button" class="btn btn-primary" onclick="openRegisterModal()">
            Daftar Sekarang
        </button>
    </div>
</nav>

<main id="home">
    <!-- Hero Section -->
    <section class="hero" data-tour="hero">
        <div class="container hero-grid">
            <div class="reveal show">
                <div class="tag"><i class="fas fa-sparkles"></i> ISP Lokal Terpercaya</div>
                <h1><?php echo $heroTitle; ?></h1>
                <p><?php echo $heroDesc; ?></p>
                <div class="hero-cta">
                    <button type="button" class="btn btn-primary" id="heroRegisterBtn" onclick="openRegisterModal()">
                        Mulai Berlangganan <i class="fas fa-arrow-right"></i>
                    </button>
                    <a class="btn btn-ghost" href="https://wa.me/6285863409811" target="_blank">
                        <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                    </a>
                </div>
            </div>

            <div class="hero-card glass reveal show">
                <h3 style="font-size:1.35rem; margin-bottom:8px;">Koneksi Fiber Siap Tempur</h3>
                <p style="color:var(--muted); font-size:.92rem; margin-bottom:10px;">Kami fokus pada stabilitas, kecepatan, dan respon teknis cepat untuk kebutuhan rumah dan bisnis.</p>
                <div class="hero-stat">
                    <div class="mini"><strong>99.8%</strong><span>Uptime Jaringan</span></div>
                    <div class="mini"><strong>24/7</strong><span>Dukungan</span></div>
                    <div class="mini"><strong>&lt; 24 Jam</strong><span>Estimasi Aktivasi</span></div>
                    <div class="mini"><strong>Fiber</strong><span>Teknologi Akses</span></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" data-tour="features">
        <div class="container">
            <div class="section-head reveal show">
                <span class="tag" style="margin-bottom:0;"><i class="fas fa-star"></i> Layanan Unggulan</span>
                <h2>Layanan <span class="gradient-text">Internet Andal</span></h2>
                <p>Dirancang agar internet bukan sekadar cepat di speedtest, tapi juga stabil untuk aktivitas harian yang penting.</p>
            </div>
            <div class="feature-grid">
                <?php foreach ($features as $feature): ?>
                <article class="feature glass reveal show">
                    <i class="<?php echo $feature['icon']; ?>"></i>
                    <h3><?php echo $feature['title']; ?></h3>
                    <p><?php echo $feature['desc']; ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" data-tour="how-it-works">
        <div class="container">
            <div class="section-head reveal show">
                <span class="tag" style="margin-bottom:0;"><i class="fas fa-list-check"></i> Cara Berlangganan</span>
                <h2>Tiga Langkah <span class="gradient-text">Sampai Online</span></h2>
                <p>Proses ringkas agar Anda bisa segera menikmati internet tanpa ribet.</p>
            </div>
            <div class="steps-wrap">
                <?php foreach ($steps as $step): ?>
                <article class="step glass reveal show">
                    <div class="num"><?php echo $step['num']; ?></div>
                    <div>
                        <h3><?php echo $step['title']; ?></h3>
                        <p><?php echo $step['desc']; ?></p>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section>
        <div class="container">
            <div class="section-head reveal show">
                <span class="tag" style="margin-bottom:0;"><i class="fas fa-quote-left"></i> Apa Kata Pelanggan</span>
                <h2>Dipakai oleh <span class="gradient-text">Banyak Pengguna Aktif</span></h2>
            </div>
            <div class="testimonials">
                <?php foreach ($testimonials as $testimonial): ?>
                <article class="quote glass reveal show">
                    <p>"<?php echo $testimonial['quote']; ?>"</p>
                    <small><strong><?php echo $testimonial['author']; ?></strong> · <?php echo $testimonial['role']; ?></small>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" data-tour="pricing">
        <div class="container">
            <div class="section-head reveal show">
                <span class="tag" style="margin-bottom:0;"><i class="fas fa-tag"></i> Paket Internet</span>
                <h2>Pilih Paket <span class="gradient-text">Sesuai Kebutuhan</span></h2>
                <p>Harga transparan, tanpa biaya tersembunyi.</p>
            </div>

            <div class="pricing">
                <?php foreach ($packages as $package): ?>
                <article class="plan glass reveal show <?php echo isset($package['highlight']) && $package['highlight'] ? 'highlight' : ''; ?>">
                    <h3><?php echo $package['name']; ?></h3>
                    
                    <img src="<?php echo APP_URL; ?>/images/<?php echo $package['icon']; ?>" alt="<?php echo $package['name']; ?>" class="package-icon" loading="lazy" decoding="async">

                    <div class="price"><?php echo formatCurrency($package['price']); ?></div>
                    <p class="desc"><?php echo $package['description']; ?></p>

                    <ul>
                        <li><i class="fas fa-check"></i> Koneksi fiber optic stabil</li>
                        <li><i class="fas fa-check"></i> Dukungan teknis responsif</li>
                        <li><i class="fas fa-check"></i> Monitoring jaringan berkala</li>
                    </ul>

                    <button type="button" class="btn btn-primary" style="width:100%;justify-content:center;" onclick="openRegisterModalWithPackage('<?php echo $package['name']; ?>')">
                        Pilih Paket
                    </button>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="cta" data-tour="cta">
        <div class="container">
            <div class="cta glass reveal show">
                <h2>Siap beralih ke internet yang lebih stabil?</h2>
                <p>Daftar sekarang, tim kami akan bantu dari survey sampai aktivasi.</p>
                <button type="button" class="btn btn-primary" id="ctaRegisterBtn" onclick="openRegisterModal()">
                    Daftar Berlangganan <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </section>
</main>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="footer-grid">
            <div>
                <a href="#home" class="brand" style="margin-bottom:10px; display:inline-flex;">
                    <img src="<?php echo APP_URL; ?>/images/icon.png" class="brand-logo" alt="Logo">
                </a>
                <p><?php echo $footerAbout; ?></p>
            </div>

            <div>
                <h4>Kontak</h4>
                <ul>
                    <li><?php echo $contactPhone; ?></li>
                    <li><?php echo $contactEmail; ?></li>
                    <li><?php echo $contactAddress; ?></li>
                </ul>
            </div>

            <div>
                <h4>Sosial Media</h4>
                <ul>
                    <li><a href="<?php echo $s_fb; ?>" target="_blank">Facebook</a></li>
                    <li><a href="<?php echo $s_ig; ?>" target="_blank">Instagram</a></li>
                    <li><a href="<?php echo $s_tw; ?>" target="_blank">Twitter</a></li>
                    <li><a href="<?php echo $s_yt; ?>" target="_blank">YouTube</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bar">
            <span>© 2026 <?php echo $appName; ?>. All rights reserved.</span>
            <span>Built with fiber-first mindset.</span>
        </div>
    </div>
</footer>

<!-- Login Modal -->
<div class="login-role-overlay" id="loginRoleOverlay">
    <div class="login-role-modal">
        <div class="login-role-head">
            <h3>Pilih Portal Login</h3>
            <button type="button" class="login-role-close" id="closeLoginRoleModal">✕</button>
        </div>
        <div class="login-role-body">
            <a class="login-role-link" href="<?php echo APP_URL; ?>/portal/login.php">
                <i class="fas fa-user"></i>
                <span>Login Pelanggan <br><small>Masuk ke portal tagihan dan status layanan</small></span>
                <i class="fas fa-arrow-right"></i>
            </a>
            <a class="login-role-link" href="<?php echo APP_URL; ?>/technician/login.php">
                <i class="fas fa-screwdriver-wrench"></i>
                <span>Login Teknisi <br><small>Masuk ke dashboard pekerjaan teknis</small></span>
                <i class="fas fa-arrow-right"></i>
            </a>
            <a class="login-role-link" href="<?php echo APP_URL; ?>/sales/login.php">
                <i class="fas fa-chart-line"></i>
                <span>Login Sales <br><small>Masuk ke portal penjualan dan follow up</small></span>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>

<!-- Register Modal -->
<div id="registerModal" class="modal">
    <div class="modal-content glass" style="max-width: 500px; width: 90%;">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3><i class="fas fa-user-plus"></i> Pendaftaran Pelanggan Baru</h3>
            <span class="close" onclick="closeRegisterModal()" style="cursor:pointer; font-size:28px; color: var(--text);">×</span>
        </div>
        <form method="POST" action="<?php echo APP_URL; ?>/register">
            <input type="hidden" name="_token" value="">
            
            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">No HP (WhatsApp)</label>
                <input type="tel" name="phone" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Alamat Lengkap</label>
                <textarea name="address" class="form-control" rows="3" required></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Paket (Opsional)</label>
                <select name="package" id="packageSelect" class="form-control">
                    <option value="">Pilih paket</option>
                    <?php foreach ($packages as $package): ?>
                    <option value="<?php echo $package['name']; ?>"><?php echo $package['name']; ?></option>
                    <?php endforeach; ?>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Catatan (Opsional)</label>
                <input type="text" name="notes" class="form-control" placeholder="Contoh: jam dihubungi, patokan lokasi">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Kirim</button>
        </form>
    </div>
</div>

<script>
    // Scroll animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) entry.target.classList.add('show');
        });
    }, { threshold: 0.15 });
    document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));

    // Navbar background on scroll
    const navbar = document.querySelector('.navbar-inner');
    window.addEventListener('scroll', function() {
        if (navbar) {
            navbar.style.background = window.scrollY > 12 ? 'rgba(8, 14, 26, .88)' : 'rgba(13, 21, 38, 0.68)';
        }
    });

    // Mobile menu
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileActions = document.getElementById('mobileActions');
    if (mobileMenuToggle && mobileActions) {
        mobileMenuToggle.addEventListener('click', function() {
            mobileActions.classList.toggle('open');
            const icon = mobileMenuToggle.querySelector('i');
            if (icon) {
                icon.className = mobileActions.classList.contains('open') ? 'fas fa-xmark' : 'fas fa-bars';
            }
        });
    }

    // Login modal
    const loginOverlay = document.getElementById('loginRoleOverlay');
    const openLoginBtns = document.querySelectorAll('#openLoginRoleModal, #openLoginRoleModalMobile');
    const closeLoginBtn = document.getElementById('closeLoginRoleModal');

    function closeLoginModal() {
        if (loginOverlay) loginOverlay.style.display = 'none';
    }

    openLoginBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            if (loginOverlay) loginOverlay.style.display = 'flex';
            if (mobileActions) mobileActions.classList.remove('open');
        });
    });

    if (closeLoginBtn) closeLoginBtn.addEventListener('click', closeLoginModal);
    if (loginOverlay) loginOverlay.addEventListener('click', (e) => { if (e.target === loginOverlay) closeLoginModal(); });

    // Register modal
    const registerModal = document.getElementById('registerModal');
    function openRegisterModal() { if (registerModal) registerModal.style.display = 'flex'; }
    function closeRegisterModal() { if (registerModal) registerModal.style.display = 'none'; }
    function openRegisterModalWithPackage(pkg) {
        const select = document.getElementById('packageSelect');
        if (select) {
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value === pkg) { select.selectedIndex = i; break; }
            }
        }
        openRegisterModal();
    }

    // Close modal on ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closeRegisterModal(); closeLoginModal(); }
    });

    // Simple Product Tour
    (function(){
        const tourSteps = [
            { sel: '[data-tour="hero"]', title: 'Hero & CTA', text: 'Ringkasan layanan dan tombol cepat untuk mulai berlangganan.' },
            { sel: '[data-tour="features"]', title: 'Fitur Unggulan', text: 'Fitur-fitur yang menjadikan layanan kami andal.' },
            { sel: '[data-tour="how-it-works"]', title: 'Cara Kerja', text: 'Proses singkat dari pendaftaran hingga aktivasi.' },
            { sel: '[data-tour="pricing"]', title: 'Paket & Harga', text: 'Pilih paket yang sesuai untuk kebutuhan Anda.' },
            { sel: '[data-tour="cta"]', title: 'Daftar Sekarang', text: 'Buka form pendaftaran untuk mengirim permintaan aktivasi, atau kontak via WhatsApp.' }
        ];

        let tourIndex = 0;
        let currentEl = null;
        const overlay = document.createElement('div'); overlay.className = 'tour-overlay'; document.body.appendChild(overlay);
        const tooltip = document.createElement('div'); tooltip.className = 'tour-tooltip'; tooltip.style.display='none'; document.body.appendChild(tooltip);

        function showStep(i){
            tourIndex = i;
            const step = tourSteps[i];
            if (!step) return endTour();
            const el = document.querySelector(step.sel);
            if (!el) { showStep(i+1); return; }
            if (currentEl) currentEl.classList.remove('tour-highlight');
            currentEl = el; currentEl.classList.add('tour-highlight');
            overlay.style.display = 'block';
            tooltip.style.display = 'block';
            tooltip.classList.remove('arrow-top','arrow-bottom');
            tooltip.innerHTML = `<h4>${step.title}</h4><p>${step.text}</p><div class="tour-controls">
                ${i>0?'<button class="tour-btn ghost" id="tourPrev">Prev</button>':''}
                ${i<tourSteps.length-1?'<button class="tour-btn primary" id="tourNext">Next</button>':'<button class="tour-btn primary" id="tourDone">Done</button>'}
                <button class="tour-btn ghost" id="tourClose">Close</button>
            </div>`;

            // ensure tooltip measured after render
            tooltip.style.top = '0px'; tooltip.style.left = '0px';
            // smooth scroll to element center
            el.scrollIntoView({behavior:'smooth', block:'center'});

            requestAnimationFrame(()=>{
                // measure after layout
                const rect = el.getBoundingClientRect();
                const ttRect = tooltip.getBoundingClientRect();
                const margin = 12;

                let top, left;
                // Prefer placing above
                if (rect.top >= ttRect.height + margin) {
                    top = window.scrollY + rect.top - ttRect.height - margin;
                    tooltip.classList.add('arrow-bottom');
                } else if ((window.innerHeight - rect.bottom) >= ttRect.height + margin) {
                    // place below
                    top = window.scrollY + rect.bottom + margin;
                    tooltip.classList.add('arrow-top');
                } else {
                    // fallback center vertically near element
                    top = window.scrollY + Math.max(margin, (rect.top + rect.bottom)/2 - ttRect.height/2);
                }

                // center horizontally relative to element
                left = window.scrollX + rect.left + (rect.width - ttRect.width)/2;
                left = Math.max(window.scrollX + margin, Math.min(left, window.scrollX + document.documentElement.clientWidth - ttRect.width - margin));

                tooltip.style.top = Math.round(top) + 'px';
                tooltip.style.left = Math.round(left) + 'px';
            });

            attachTourButtons();
        }

        function attachTourButtons(){
            const prev = document.getElementById('tourPrev');
            const next = document.getElementById('tourNext');
            const done = document.getElementById('tourDone');
            const close = document.getElementById('tourClose');
            if (prev) prev.onclick = ()=> showStep(tourIndex-1);
            if (next) next.onclick = ()=> {
                // if next step is CTA that opens register modal, allow opening
                showStep(tourIndex+1);
            };
            if (done) done.onclick = ()=> { if (document.getElementById('registerModal')) openRegisterModal(); endTour(); };
            if (close) close.onclick = endTour;
            overlay.onclick = endTour;
        }

        function endTour(){
            overlay.style.display = 'none';
            tooltip.style.display = 'none';
            if (currentEl) currentEl.classList.remove('tour-highlight');
            currentEl = null; tourIndex = 0;
        }

        // Start tour button
        const startBtn = document.getElementById('startTourBtn');
        if (startBtn) startBtn.addEventListener('click', ()=> showStep(0));

        // Optional: start from hero register button click
        const heroReg = document.getElementById('heroRegisterBtn');
        if (heroReg) heroReg.addEventListener('click', ()=> endTour());

        // Expose functions for debugging
        window.__startProductTour = ()=> showStep(0);
        window.__endProductTour = endTour;
    })();
</script>
</body>
</html>
