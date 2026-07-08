<?php
/**
 * Template: GitHub Dark Theme - Terintegrasi dengan style Bolt
 * Mempertahankan semua function asli: modernUltraPackageServices, modernUltraServiceActive,
 * modernUltraBuildVisibleServiceMap, dll.
 */

$defaultPackageServices = [
];

$defaultPackageServiceTypes = [
    'router_2' => 'router',
    'member_2' => 'pppoe',
    'voucher_5000' => 'voucher',
    'online_250' => 'online',
    'vpn_radius' => 'vpn',
    'vpn_remote' => 'vpn',
    'wa_notif' => 'general',
    'payment_gateway' => 'general',
    'client_area' => 'general',
    'custom_domain' => 'general',
    'annual_12m' => 'general'
];

$packageFeatureList = $defaultPackageServices;
$packageFeatureTypes = $defaultPackageServiceTypes;

if (!function_exists('modernUltraPackageServices')) {
    function modernUltraPackageServices($pkg)
    {
        $services = [];
        $raw = (string) ($pkg['package_services'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $services = array_values(array_filter(array_map('strval', $decoded)));
            }
        }
        return $services;
    }
}

if (!function_exists('modernUltraServiceActive')) {
    function modernUltraServiceActive($pkg, $serviceKey)
    {
        return in_array($serviceKey, modernUltraPackageServices($pkg), true);
    }
}

if (!function_exists('modernUltraNormalizeType')) {
    function modernUltraNormalizeType($rawType)
    {
        $type = strtolower(trim((string) $rawType));
        $type = preg_replace('/[^a-z0-9_]+/', '_', $type);
        $type = trim((string) $type, '_');
        return $type !== '' ? $type : 'general';
    }
}

if (!function_exists('modernUltraResolveServiceType')) {
    function modernUltraResolveServiceType($serviceKey, $serviceTypeMap)
    {
        if (isset($serviceTypeMap[$serviceKey])) {
            return modernUltraNormalizeType($serviceTypeMap[$serviceKey]);
        }
        $parts = explode('_', (string) $serviceKey);
        return modernUltraNormalizeType($parts[0] ?? 'general');
    }
}

if (!function_exists('modernUltraServiceWeight')) {
    function modernUltraServiceWeight($serviceKey, $serviceName)
    {
        $source = (string) $serviceName . ' ' . (string) $serviceKey;
        if (preg_match('/\d[\d\.,]*/', $source, $m)) {
            $num = preg_replace('/[^0-9]/', '', (string) $m[0]);
            return $num !== '' ? (int) $num : 0;
        }
        return 0;
    }
}

if (!function_exists('modernUltraBuildVisibleServiceMap')) {
    function modernUltraBuildVisibleServiceMap($pkg, $featureList, $featureTypes)
    {
        $pickMode = 'max';
        $groups = [];

        foreach ($featureList as $serviceKey => $serviceName) {
            $serviceType = modernUltraResolveServiceType($serviceKey, $featureTypes);
            $isIncluded = modernUltraServiceActive($pkg, $serviceKey);

            $groups[$serviceType][] = [
                'key' => (string) $serviceKey,
                'name' => (string) $serviceName,
                'weight' => modernUltraServiceWeight($serviceKey, $serviceName),
                'included' => $isIncluded
            ];
        }

        $visible = [];
        foreach ($groups as $category => $items) {
            $pool = array_values(array_filter($items, function ($item) {
                return !empty($item['included']);
            }));
            if (empty($pool)) {
                $pool = $items;
            }

            usort($pool, function ($a, $b) use ($pickMode) {
                $aw = (int) ($a['weight'] ?? 0);
                $bw = (int) ($b['weight'] ?? 0);
                if ($aw === $bw) {
                    return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                }
                return $pickMode === 'min' ? ($aw <=> $bw) : ($bw <=> $aw);
            });

            $chosen = $pool[0] ?? null;
            if (!empty($chosen['key'])) {
                $visible[(string) $chosen['key']] = true;
            }
        }

        return $visible;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $appName; ?> - Billing System</title>

    <!-- PWA Meta Tags -->
    <meta name="description" content="<?php echo $appName; ?> adalah sistem billing untuk ISP lokal dengan fitur lengkap dan kemudahan penggunaan.">
    <meta name="theme-color" content="#0d1117">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="<?php echo $appName; ?>">
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/icons/icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet">

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
            --shadow-small: 0 0 0 1px rgba(255,255,255,0.05);
            --cyan: #2f81f7;
            --teal: #3fb950;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', sans-serif;
            background-color: var(--bg-canvas);
            color: var(--fg-default);
            line-height: 1.5;
            scroll-behavior: smooth;
        }

        /* Typography */
        h1, h2, h3, h4 {
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        a {
            color: var(--accent-blue);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        /* Container */
        .container {
            width: min(1120px, 92vw);
            margin: 0 auto;
        }

        /* Glass effect */
        .glass {
            background: rgba(22, 27, 34, 0.88);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-default);
            border-radius: 16px;
        }

        /* Gradient Text */
        .gradient-text {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-green));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            border-radius: 8px;
            padding: 10px 18px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: var(--accent-blue);
            color: #fff;
            box-shadow: 0 4px 12px rgba(47, 129, 247, 0.25);
        }

        .btn-primary:hover {
            background: var(--accent-blue-hover);
            transform: translateY(-1px);
        }

        .btn-ghost {
            border: 1px solid var(--border-default);
            color: var(--fg-default);
            background: transparent;
        }

        .btn-ghost:hover {
            background: var(--bg-tertiary);
            border-color: var(--fg-muted);
        }

        .btn-login {
            border: 1px solid var(--border-default);
            color: var(--fg-default);
            background: transparent;
        }

        .btn-login:hover {
            border-color: var(--accent-blue);
            color: var(--accent-blue);
        }

        /* Tag */
        .tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(47, 129, 247, 0.35);
            background: rgba(47, 129, 247, 0.1);
            color: #9feaff;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* ==================== NAVBAR ==================== */
        .navbar {
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 12px 0;
            background: rgba(13, 17, 23, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-default);
        }

        .navbar-inner {
            width: min(1120px, 92vw);
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--fg-default);
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .brand-logo {
            width: 36px;
            height: auto;
            display: block;
            border-radius: 8px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .nav-links a {
            color: var(--fg-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .nav-links a:hover {
            color: var(--accent-blue);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .menu-toggle {
            display: none;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--border-default);
            color: var(--fg-default);
            background: transparent;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .mobile-actions {
            display: none;
            width: min(1120px, 92vw);
            margin: 8px auto 0;
            padding: 12px;
            border-radius: 12px;
            gap: 10px;
        }

        .mobile-actions.open {
            display: flex;
        }

        /* ==================== HERO SECTION ==================== */
        section {
            padding: 80px 0;
        }

        .hero {
            padding-top: 40px;
            padding-bottom: 60px;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 42px;
            align-items: center;
        }

        .hero h1 {
            font-size: clamp(2rem, 5vw, 3.5rem);
            line-height: 1.1;
            margin: 16px 0;
        }

        .hero p {
            color: var(--fg-muted);
            margin-bottom: 28px;
        }

        .hero-cta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .hero-card {
            padding: 24px;
        }

        .hero-stat {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 20px;
        }

        .mini {
            padding: 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border-muted);
        }

        .mini strong {
            display: block;
            font-size: 1.2rem;
            color: var(--accent-blue);
        }

        .mini span {
            color: var(--fg-muted);
            font-size: 0.75rem;
        }

        /* ==================== FEATURES ==================== */
        .section-head {
            text-align: center;
            margin-bottom: 48px;
        }

        .section-head h2 {
            font-size: clamp(1.8rem, 3.5vw, 2.5rem);
            margin: 12px 0 8px;
        }

        .section-head p {
            color: var(--fg-muted);
            max-width: 600px;
            margin: 0 auto;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .feature {
            padding: 24px;
            border-radius: 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            transition: all 0.2s;
        }

        .feature:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
        }

        .feature i {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            margin-bottom: 16px;
            color: var(--accent-blue);
            background: rgba(47, 129, 247, 0.1);
            font-size: 1.3rem;
        }

        .feature h3 {
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .feature p {
            color: var(--fg-muted);
            font-size: 0.85rem;
        }

        /* ==================== STEPS ==================== */
        .steps-wrap {
            display: grid;
            gap: 16px;
        }

        .step {
            display: grid;
            grid-template-columns: 66px 1fr;
            gap: 16px;
            padding: 20px;
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: 12px;
        }

        .step .num {
            width: 50px;
            height: 50px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-weight: 800;
            color: var(--accent-blue);
            background: rgba(47, 129, 247, 0.1);
            border: 1px solid rgba(47, 129, 247, 0.35);
            font-size: 1.2rem;
        }

        .step h3 {
            font-size: 1.05rem;
            margin-bottom: 6px;
        }

        .step p {
            color: var(--fg-muted);
            font-size: 0.85rem;
        }

        /* ==================== TESTIMONIALS ==================== */
        .testimonials {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .quote {
            padding: 20px;
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: 12px;
        }

        .quote p {
            color: #d7e5ff;
            margin-bottom: 12px;
            font-style: italic;
        }

        .quote small {
            color: var(--fg-muted);
            font-size: 0.8rem;
        }

        /* ==================== PRICING / PACKAGES ==================== */
        .pricing {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            align-items: stretch;
        }

        .plan {
            padding: 28px 24px;
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: 16px;
            text-align: center;
            transition: all 0.2s;
        }

        .plan:hover {
            border-color: var(--accent-blue);
            transform: translateY(-4px);
        }

        .plan.highlight {
            border-color: var(--accent-blue);
            box-shadow: 0 8px 24px rgba(47, 129, 247, 0.15);
        }

        .plan h3 {
            font-size: 1.4rem;
            margin-bottom: 12px;
        }

        .package-icon {
            width: 80px;
            height: auto;
            margin: 12px auto;
            display: block;
            filter: drop-shadow(0 2px 4px rgba(47, 129, 247, 0.3));
        }

        .price {
            font-size: 1.8rem;
            font-weight: 800;
            margin: 16px 0 8px;
        }

        .price span {
            font-size: 0.85rem;
            font-weight: 400;
            color: var(--fg-muted);
        }

        .plan ul {
            list-style: none;
            margin: 20px 0;
            text-align: left;
        }

        .plan ul li {
            padding: 6px 0;
            color: var(--fg-muted);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .plan ul li i {
            color: var(--accent-green);
            width: 18px;
        }

        .package-service-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            padding: 5px 0;
        }

        .package-service-item.is-included i {
            color: var(--accent-green);
        }

        .package-service-item.is-missing {
            opacity: 0.5;
        }

        .package-service-item.is-missing i {
            color: var(--fg-subtle);
        }

        .desc {
            color: var(--fg-muted);
            font-size: 0.85rem;
            margin: 12px 0;
        }

        /* ==================== FAQ ==================== */
        .faq {
            display: grid;
            gap: 12px;
        }

        .faq details {
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid var(--border-default);
            background: var(--bg-primary);
        }

        .faq summary {
            cursor: pointer;
            font-weight: 600;
            color: var(--fg-default);
        }

        .faq p {
            color: var(--fg-muted);
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid var(--border-muted);
            font-size: 0.9rem;
        }

        /* ==================== CTA ==================== */
        .cta {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            text-align: center;
            padding: 48px 32px;
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
            border-radius: 20px;
        }

        .cta h2 {
            font-size: 1.8rem;
        }

        .cta p {
            color: var(--fg-muted);
        }

        /* ==================== FOOTER ==================== */
        footer {
            padding: 48px 0 32px;
            border-top: 1px solid var(--border-default);
            margin-top: 40px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 32px;
        }

        .footer-grid ul {
            list-style: none;
            display: grid;
            gap: 8px;
        }

        .footer-grid a {
            color: var(--fg-muted);
            text-decoration: none;
            font-size: 0.85rem;
        }

        .footer-grid a:hover {
            color: var(--accent-blue);
        }

        .footer-grid h4 {
            margin-bottom: 12px;
            font-size: 0.9rem;
        }

        .footer-bar {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid var(--border-muted);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            color: var(--fg-muted);
            font-size: 0.75rem;
        }

        /* ==================== MODAL ==================== */
        .login-role-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1200;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
        }

        .login-role-modal {
            width: min(480px, 92vw);
            border-radius: 16px;
            border: 1px solid var(--border-default);
            background: var(--bg-primary);
            overflow: hidden;
        }

        .login-role-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-default);
            background: rgba(47, 129, 247, 0.05);
        }

        .login-role-body {
            padding: 16px;
            display: grid;
            gap: 12px;
        }

        .login-role-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--border-default);
            background: rgba(255,255,255,0.02);
            text-decoration: none;
            color: var(--fg-default);
            transition: all 0.2s;
        }

        .login-role-link:hover {
            border-color: var(--accent-blue);
            background: rgba(47, 129, 247, 0.08);
        }

        .login-role-close {
            border: 1px solid var(--border-default);
            background: transparent;
            border-radius: 8px;
            width: 34px;
            height: 34px;
            cursor: pointer;
            color: var(--fg-muted);
        }

        .login-role-close:hover {
            background: rgba(47, 129, 247, 0.1);
            color: var(--accent-blue);
        }

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
            max-width: 500px;
            width: 90%;
            background: var(--bg-primary);
            border: 1px solid var(--border-default);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--fg-muted);
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-canvas);
            border: 1px solid var(--border-default);
            border-radius: 8px;
            color: var(--fg-default);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-blue);
        }

        /* ==================== ANIMATIONS ==================== */
        .reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .reveal.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 980px) {
            .hero-grid,
            .feature-grid,
            .pricing,
            .footer-grid,
            .testimonials {
                grid-template-columns: 1fr;
            }

            .nav-links,
            .nav-actions {
                display: none;
            }

            .menu-toggle {
                display: inline-flex;
            }

            .hero {
                padding-top: 20px;
            }
        }

        @media (max-width: 768px) {
            section {
                padding: 50px 0;
            }

            .hero-stat {
                grid-template-columns: 1fr 1fr;
            }

            .step {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .step .num {
                margin: 0 auto;
            }

            .footer-bar {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .reveal {
                transition: none;
                opacity: 1;
                transform: none;
            }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-inner">
        <a href="#home" class="brand">
            <img src="<?php echo APP_URL; ?>/assets/icons/icon.png" class="brand-logo" alt="Logo" width="58" height="58">
        </a>
        <div class="nav-links">
            <a href="#features">Fitur</a>
            <a href="#how-it-works">Cara Kerja</a>
            <a href="#packages">Paket</a>
            <a href="#faq">FAQ</a>
        </div>
        <div class="nav-actions">
            <button type="button" class="btn btn-login" id="openLoginRoleModal">
                <i class="fas fa-right-to-bracket"></i> Masuk
            </button>
            <button type="button" class="btn btn-primary" onclick="window.__gembokOpenRegisterModal && window.__gembokOpenRegisterModal()">
                Daftar Sekarang
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
        <button type="button" class="btn btn-primary" onclick="window.__gembokOpenRegisterModal && window.__gembokOpenRegisterModal()">
            Daftar Sekarang
        </button>
    </div>
</nav>

<main id="home">
    <!-- Hero Section -->
    <section class="hero">
        <div class="container hero-grid">
            <div class="reveal">
                <div class="tag"><i class="fas fa-sparkles"></i> ISP Lokal Terpercaya</div>
                <h1><?php echo strip_tags($heroTitle); ?></h1>
                <p><?php echo $heroDesc; ?></p>
                <div class="hero-cta">
                    <button type="button" class="btn btn-primary" onclick="window.__gembokOpenRegisterModal && window.__gembokOpenRegisterModal()">
                        Mulai Berlangganan <i class="fas fa-arrow-right"></i>
                    </button>
                    <a class="btn btn-ghost" href="#" target="_blank">
                        <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                    </a>
                </div>
            </div>

            <div class="hero-card glass reveal">
                <h3 style="font-size:1.25rem; margin-bottom:8px;">Koneksi Fiber Siap Tempur</h3>
                <p style="color:var(--fg-muted); font-size:0.85rem; margin-bottom:10px;">Kami fokus pada stabilitas, kecepatan, dan respon teknis cepat untuk kebutuhan rumah dan bisnis.</p>
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
    <section id="features">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag" style="margin-bottom:0;">Layanan Unggulan</span>
                <h2>Layanan <span class="gradient-text">Internet Andal</span></h2>
                <p>Dirancang agar internet bukan sekadar cepat di speedtest, tapi juga stabil untuk aktivitas harian yang penting.</p>
            </div>
            <div class="feature-grid">
                <div class="feature reveal">
                    <i class="fas fa-tachometer-alt"></i>
                    <h3>Kecepatan Maksimal</h3>
                    <p>Koneksi simetris untuk download dan upload, cocok untuk work from home & streaming.</p>
                </div>
                <div class="feature reveal">
                    <i class="fas fa-headset"></i>
                    <h3>Dukungan 24/7</h3>
                    <p>Tim teknisi siap membantu kapan saja melalui telepon, chat, atau datang ke lokasi.</p>
                </div>
                <div class="feature reveal">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Keamanan Terjamin</h3>
                    <p>Infrastruktur dengan enkripsi dan sistem monitoring threat prevention modern.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag" style="margin-bottom:0;">Cara Berlangganan</span>
                <h2>Tiga Langkah <span class="gradient-text">Sampai Online</span></h2>
                <p>Proses ringkas agar Anda bisa segera menikmati internet tanpa ribet.</p>
            </div>
            <div class="steps-wrap">
                <div class="step reveal">
                    <div class="num">1</div>
                    <div>
                        <h3>Daftar & Pilih Paket</h3>
                        <p>Isi formulir pendaftaran dan pilih paket internet sesuai kebutuhan.</p>
                    </div>
                </div>
                <div class="step reveal">
                    <div class="num">2</div>
                    <div>
                        <h3>Verifikasi & Survey</h3>
                        <p>Tim kami akan menghubungi untuk verifikasi data dan survey lokasi.</p>
                    </div>
                </div>
                <div class="step reveal">
                    <div class="num">3</div>
                    <div>
                        <h3>Instalasi & Aktivasi</h3>
                        <p>Instalasi dilakukan oleh teknisi profesional, jaringan siap digunakan.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section>
        <div class="container">
            <div class="section-head reveal">
                <span class="tag" style="margin-bottom:0;">Apa Kata Pelanggan</span>
                <h2>Dipakai oleh <span class="gradient-text">Banyak Pengguna Aktif</span></h2>
            </div>
            <div class="testimonials">
                <div class="quote reveal">
                    <p>"Sejak pakai internet ini, kerjaan WFA jadi lebih lancar. Supportnya cepat banget."</p>
                    <small><strong>Andi Wijaya</strong> · Pengusaha</small>
                </div>
                <div class="quote reveal">
                    <p>"Jaringan stabil, harga bersaing, teknisi ramah. Highly recommended!"</p>
                    <small><strong>Siti Nurhaliza</strong> · Freelancer</small>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing / Packages Section -->
    <section id="packages">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag" style="margin-bottom:0;">Paket Internet</span>
                <h2>Pilih Paket <span class="gradient-text">Sesuai Kebutuhan</span></h2>
                <p>Harga transparan, tanpa biaya tersembunyi.</p>
            </div>

            <div class="pricing">
                <?php foreach ($packages as $idx => $pkg): ?>
                    <?php $isHighlight = $idx === 1; ?>
                    <div class="plan reveal <?php echo $isHighlight ? 'highlight' : ''; ?>">
                        <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>

                        <img src="<?php echo APP_URL; ?>/assets/svg/cloud.svg"
                             alt="<?php echo htmlspecialchars($pkg['name']); ?>"
                             class="package-icon"
                             loading="lazy"
                             decoding="async">

                        <div class="price">
                            <?php echo formatCurrency($pkg['price']); ?>
                            <span>/bulan</span>
                        </div>
                        <p class="desc"><?php echo htmlspecialchars($pkg['description'] ?? 'Paket internet dengan performa stabil.'); ?></p>

                        <!-- Dynamic services dari packageFeatureList -->
                        <?php if (!empty($packageFeatureList)): ?>
                            <?php $visibleServiceMap = modernUltraBuildVisibleServiceMap($pkg, $packageFeatureList, $packageFeatureTypes); ?>
                            <ul>
                                <?php foreach ($packageFeatureList as $serviceKey => $serviceName): ?>
                                    <?php if (empty($visibleServiceMap[$serviceKey])) { continue; } ?>
                                    <?php $serviceIncluded = modernUltraServiceActive($pkg, $serviceKey); ?>
                                    <li class="<?php echo $serviceIncluded ? 'is-included' : 'is-missing'; ?>">
                                        <i class="fas <?php echo $serviceIncluded ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                        <span><?php echo htmlspecialchars($serviceName); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> Koneksi fiber optic stabil</li>
                                <li><i class="fas fa-check-circle"></i> Dukungan teknis responsif</li>
                                <li><i class="fas fa-check-circle"></i> Monitoring jaringan berkala</li>
                            </ul>
                        <?php endif; ?>

                        <button type="button" class="btn btn-primary" style="width:100%; justify-content:center;"
                                onclick="window.__gembokOpenRegisterModalWithPackage && window.__gembokOpenRegisterModalWithPackage('<?php echo addslashes($pkg['name']); ?>')">
                            Pilih Paket
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <?php if (!empty($faqs) && is_array($faqs)): ?>
    <section id="faq">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag" style="margin-bottom:0;">Pertanyaan Umum</span>
                <h2>Tanya Jawab</h2>
            </div>
            <div class="faq reveal">
                <?php foreach ($faqs as $f): ?>
                    <details>
                        <summary><?php echo htmlspecialchars($f['question'] ?? ''); ?></summary>
                        <p><?php echo nl2br(htmlspecialchars($f['answer'] ?? '')); ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section>
        <div class="container">
            <div class="cta reveal">
                <h2>Siap beralih ke internet yang lebih stabil?</h2>
                <p>Daftar sekarang, tim kami akan bantu dari survey sampai aktivasi.</p>
                <button type="button" class="btn btn-primary" onclick="window.__gembokOpenRegisterModal && window.__gembokOpenRegisterModal()">
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
                <a href="#" class="brand" style="margin-bottom:10px; display:inline-flex;">
                    <img src="<?php echo APP_URL; ?>/assets/icons/icon.png" class="brand-logo" alt="Logo" width="90">
                </a>
                <p><?php echo $footerAbout; ?></p>
            </div>

            <div>
                <h4>Kontak</h4>
                <ul>
                    <li><?php echo $contactPhone; ?></li>
                    <li><?php echo $contactEmail; ?></li>
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
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($appName); ?>. All rights reserved.</span>
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
            <a class="login-role-link" href="portal/login.php">
                <span><i class="fas fa-user"></i> Login Pelanggan</span>
                <i class="fas fa-arrow-right"></i>
            </a>
            <a class="login-role-link" href="technician/login.php">
                <span><i class="fas fa-screwdriver-wrench"></i> Login Teknisi</span>
                <i class="fas fa-arrow-right"></i>
            </a>
            <!-- <a class="login-role-link" href="sales/login.php">
                <span><i class="fas fa-chart-line"></i> Login Sales</span>
                <i class="fas fa-arrow-right"></i>
            </a> -->
        </div>
    </div>
</div>

<!-- Register Modal -->
<div id="registerModal" class="modal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3><i class="fas fa-user-plus"></i> Pendaftaran Pelanggan Baru</h3>
            <span onclick="window.__gembokCloseRegisterModal && window.__gembokCloseRegisterModal()" style="cursor:pointer; font-size:28px; color:var(--fg-muted);">&times;</span>
        </div>
        <form method="POST" action="#" id="registerForm">
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
                    <?php foreach ($packages as $pkg): ?>
                        <option value="<?php echo htmlspecialchars($pkg['name']); ?>"><?php echo htmlspecialchars($pkg['name']); ?></option>
                    <?php endforeach; ?>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Catatan (Opsional)</label>
                <input type="text" name="notes" class="form-control" placeholder="Contoh: jam dihubungi, patokan lokasi">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Kirim</button>
        </form>
    </div>
</div>

<script>
    // ==================== SCROLL ANIMATIONS ====================
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) entry.target.classList.add('show');
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));

    // ==================== NAVBAR SCROLL ====================
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', function() {
        if (navbar) {
            navbar.style.background = window.scrollY > 50 ? 'rgba(13, 17, 23, 0.98)' : 'rgba(13, 17, 23, 0.92)';
        }
    });

    // ==================== MOBILE MENU ====================
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

    // ==================== LOGIN MODAL ====================
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

    // ==================== REGISTER MODAL ====================
    const registerModal = document.getElementById('registerModal');

    window.__gembokOpenRegisterModal = function() {
        if (registerModal) registerModal.style.display = 'flex';
    };

    window.__gembokCloseRegisterModal = function() {
        if (registerModal) registerModal.style.display = 'none';
    };

    window.__gembokOpenRegisterModalWithPackage = function(pkg) {
        const select = document.getElementById('packageSelect');
        if (select) {
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value === pkg) {
                    select.selectedIndex = i;
                    break;
                }
            }
        }
        window.__gembokOpenRegisterModal();
    };

    // Close modal on ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            window.__gembokCloseRegisterModal();
            closeLoginModal();
        }
    });

    // ==================== FORM SUBMIT HANDLER ====================
    document.getElementById('registerForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('<?php echo APP_URL; ?>/register-handler.php', {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(data => {
            if (data.success) {
                alert('Pendaftaran berhasil! Tim kami akan menghubungi Anda.');
                window.__gembokCloseRegisterModal();
            } else {
                alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
            }
        }).catch(err => {
            alert('Terjadi kesalahan jaringan.');
        });
    });
</script>
</body>
</html>