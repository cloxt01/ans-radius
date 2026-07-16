<?php
/**
 * Landing Page — Premium Glass Theme
 * Modern, dark, glassmorphism, startup-style
 */

$defaultPackageServices = [];
$defaultPackageServiceTypes = [
        'router_2'       => 'router',
        'member_2'       => 'pppoe',
        'voucher_5000'   => 'voucher',
        'online_250'     => 'online',
        'vpn_radius'     => 'vpn',
        'vpn_remote'     => 'vpn',
        'wa_notif'       => 'general',
        'payment_gateway'=> 'general',
        'client_area'    => 'general',
        'custom_domain'  => 'general',
        'annual_12m'     => 'general',
];
$packageFeatureList  = $defaultPackageServices;
$packageFeatureTypes = $defaultPackageServiceTypes;

if (!function_exists('modernUltraPackageServices')) {
    function modernUltraPackageServices($pkg) {
        $raw = (string)($pkg['package_services'] ?? '');
        if ($raw === '') return [];
        $d = json_decode($raw, true);
        return is_array($d) ? array_values(array_filter(array_map('strval', $d))) : [];
    }
}
if (!function_exists('modernUltraServiceActive')) {
    function modernUltraServiceActive($pkg, $key) {
        return in_array($key, modernUltraPackageServices($pkg), true);
    }
}
if (!function_exists('modernUltraNormalizeType')) {
    function modernUltraNormalizeType($raw) {
        $t = strtolower(trim((string)$raw));
        $t = preg_replace('/[^a-z0-9_]+/', '_', $t);
        return trim($t, '_') ?: 'general';
    }
}
if (!function_exists('modernUltraResolveServiceType')) {
    function modernUltraResolveServiceType($key, $map) {
        if (isset($map[$key])) return modernUltraNormalizeType($map[$key]);
        $p = explode('_', (string)$key);
        return modernUltraNormalizeType($p[0] ?? 'general');
    }
}
if (!function_exists('modernUltraServiceWeight')) {
    function modernUltraServiceWeight($key, $name) {
        $src = $name . ' ' . $key;
        if (preg_match('/\d[\d\.,]*/', $src, $m)) {
            $n = preg_replace('/[^0-9]/', '', $m[0]);
            return $n !== '' ? (int)$n : 0;
        }
        return 0;
    }
}
if (!function_exists('modernUltraBuildVisibleServiceMap')) {
    function modernUltraBuildVisibleServiceMap($pkg, $featureList, $featureTypes) {
        $groups = [];
        foreach ($featureList as $key => $name) {
            $type = modernUltraResolveServiceType($key, $featureTypes);
            $groups[$type][] = [
                    'key'      => (string)$key,
                    'name'     => (string)$name,
                    'weight'   => modernUltraServiceWeight($key, $name),
                    'included' => modernUltraServiceActive($pkg, $key),
            ];
        }
        $visible = [];
        foreach ($groups as $items) {
            $pool = array_values(array_filter($items, fn($i) => !empty($i['included'])));
            if (empty($pool)) $pool = $items;
            usort($pool, fn($a, $b) => ($b['weight'] <=> $a['weight']) ?: strcmp($a['name'], $b['name']));
            if (!empty($pool[0]['key'])) $visible[$pool[0]['key']] = true;
        }
        return $visible;
    }
}
$settings = [];
try {
    $settingsData = fetchAll("SELECT * FROM settings");
    foreach ($settingsData as $s) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }
} catch (Exception $e) {
    logError('Failed to load settings: ' . $e->getMessage());
    setFlash('error', 'Gagal memuat pengaturan');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> — Internet Lokal</title>
    <meta name="description" content="<?php echo APP_NAME; ?> — ISP lokal, koneksi stabil, harga transparan.">
    <meta name="theme-color" content="#050816">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?php echo APP_NAME; ?>">
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/icons/icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
    
        /* ============================================================
        DESIGN TOKENS — PREMIUM LIGHT SAAS THEME
        ============================================================ */
        :root {
            --bg: #ffffff;
            --bg2: #f8fafc;

            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.03);

            --text: #0f172a;
            --muted: #475569;

            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #dbeafe;

            --radius: 20px;
            --radius-sm: 12px;

            --transition: 0.2s ease;
        }

        /* ============================================================
        RESET & BASE
        ============================================================ */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        html {
            scroll-behavior: smooth;
            -webkit-text-size-adjust: 100%;
        }
        body {
            font-family: 'Geist', sans-serif;
            color: var(--text);
            background: var(--bg);
            overflow-x: hidden;
            min-height: 100vh;
            line-height: 1.6;
        }
        a {
            color: var(--text);
            text-decoration: none;
        }
        a:hover {
            color: var(--primary);
        }
        img {
            display: block;
            max-width: 100%;
            height: auto;
        }

        .container {
            width: min(1100px, 92vw);
            margin-inline: auto;
        }

        /* ============================================================
        BUTTONS
        ============================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1;
            padding: 0.5625rem 1.25rem;
            border-radius: 40px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all var(--transition);
            white-space: nowrap;
            outline: none;
            min-height: 44px;
            min-width: 44px;
            touch-action: manipulation;
        }
        .btn:focus-visible {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.2);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text);
            border-color: var(--card-border);
        }
        .btn-secondary:hover {
            background: var(--bg2);
            border-color: #cbd5e1;
        }

        .btn-ghost {
            background: transparent;
            color: var(--muted);
            border-color: transparent;
        }
        .btn-ghost:hover {
            background: var(--bg2);
            color: var(--text);
        }

        .btn-sm {
            padding: 0.375rem 1rem;
            font-size: 0.8125rem;
            min-height: 36px;
            min-width: 36px;
        }
        .btn-lg {
            padding: 0.75rem 2rem;
            font-size: 0.9375rem;
            min-height: 52px;
        }
        .btn-full {
            width: 100%;
        }

        /* ============================================================
        BADGE
        ============================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            border: 1px solid var(--card-border);
            background: var(--bg2);
            color: var(--muted);
            line-height: 1;
        }
        .badge-ok {
            background: var(--primary-light);
            border-color: #93c5fd;
            color: var(--primary);
        }

        /* ============================================================
        CARD (replacing glass-card)
        ============================================================ */
        .glass-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
            transition: transform 0.25s, box-shadow 0.25s;
        }
        .glass-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.06);
        }
        .glass-card.large {
            min-height: 200px;
        }

        /* ============================================================
        HEADER — CLEAN WHITE
        ============================================================ */
        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 999;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.02);
        }

        .header-inner {
            max-width: 1100px;
            margin: 0 auto;
            height: 3.75rem;
            justify-content: space-between;
            width: 100%;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 0 1.5rem;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text);
            flex-shrink: 0;
            letter-spacing: -0.01em;
        }
        .brand-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            flex-shrink: 0;
        }
        .brand-logo {
            width: 54px;
            height: 54px;
            flex-shrink: 0;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex: 1;
        }
        .nav-links a {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--muted);
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            transition: all var(--transition);
        }
        .nav-links a:hover {
            color: var(--text);
            background: var(--bg2);
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: auto;
        }
        .menu-toggle {
            display: none;
            background: transparent;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            width: 2rem;
            height: 2rem;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--muted);
            font-size: 0.875rem;
            flex-shrink: 0;
            margin-left: auto;
            touch-action: manipulation;
        }
        .mobile-nav {
            display: none;
            background: #ffffff;
            border-top: 1px solid var(--card-border);
            padding: 0.75rem 1.5rem 1.25rem;
            flex-direction: column;
            gap: 0.25rem;
        }
        .mobile-nav.open {
            display: flex;
        }
        .mobile-nav a {
            font-size: 0.875rem;
            color: var(--muted);
            padding: 0.5rem 0;
            display: block;
            transition: color var(--transition);
        }
        .mobile-nav a:hover {
            color: var(--text);
        }
        .mobile-nav-btns {
            display: flex;
            gap: 0.5rem;
            padding-top: 0.75rem;
            flex-wrap: wrap;
        }
        .mobile-nav-btns .btn {
            flex: 1;
            min-width: 80px;
            justify-content: center;
        }

        /* ============================================================
        HERO
        ============================================================ */
        .hero {
            overflow: hidden;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            padding: 120px 0 4rem;
            background: linear-gradient(180deg, #f0f7ff 0%, #ffffff 60%);
        }
        .hero-bg-glow {
            position: absolute;
            width: 700px;
            height: 700px;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            background: radial-gradient(circle, rgba(37, 99, 235, 0.06), transparent 70%);
            pointer-events: none;
        }
        .hero-content {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 60px;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        .hero h1 {
            font-size: clamp(2.8rem, 7vw, 4.5rem);
            line-height: 1.05;
            font-weight: 800;
            margin-bottom: 24px;
            letter-spacing: -0.03em;
            color: var(--text);
        }
        .hero p {
            font-size: clamp(1rem, 1.5vw, 1.2rem);
            color: var(--muted);
            max-width: 600px;
            line-height: 1.8;
        }
        .hero-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
        .hero-trust {
            display: flex;
            gap: 2.5rem;
            margin-top: 2.5rem;
            flex-wrap: wrap;
        }
        .hero-trust div {
            text-align: left;
        }
        .hero-trust h3 {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text);
        }
        .hero-trust span {
            font-size: 0.8125rem;
            color: var(--muted);
            display: block;
            margin-top: 0.25rem;
        }

        .hero-right {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .hero-right .glass-card.large {
            grid-row: span 2;
        }
        .hero-right .glass-card h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .hero-right .glass-card p {
            font-size: 0.875rem;
            color: var(--muted);
        }
        .hero-right .glass-card h2 {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1.1;
            color: var(--text);
        }
        .hero-right .glass-card span {
            font-size: 0.875rem;
            color: var(--muted);
        }

        /* ============================================================
        STATS
        ============================================================ */
        .stats-row {
            padding: 2.5rem 0;
            border-top: 1px solid var(--card-border);
            border-bottom: 1px solid var(--card-border);
            background: var(--bg2);
        }
        .stats-inner {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        .stat-cell {
            padding: 0 1rem;
            text-align: center;
            position: relative;
        }
        .stat-cell+.stat-cell::before {
            content: '';
            position: absolute;
            left: 0;
            top: 15%;
            bottom: 15%;
            width: 1px;
            background: var(--card-border);
        }
        .stat-n {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text);
            line-height: 1;
            margin-bottom: 0.375rem;
            font-family: 'Geist Mono', monospace;
        }
        .stat-n em {
            color: var(--muted);
            font-style: normal;
            font-weight: 400;
            font-size: 1.125rem;
        }
        .stat-label {
            font-size: 0.75rem;
            color: var(--muted);
            letter-spacing: 0.02em;
        }

        /* ============================================================
        SECTION STRUCTURE
        ============================================================ */
        section {
            padding: 5rem 0;
        }
        .section-muted {
            background: var(--bg2);
        }

        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 0.75rem;
        }
        .section-tag::before {
            content: '';
            width: 1.5rem;
            height: 2px;
            background: var(--primary);
            flex-shrink: 0;
        }
        .section-head {
            margin-bottom: 3rem;
        }
        .section-head.center {
            text-align: center;
        }
        .section-head.center .section-tag {
            justify-content: center;
        }
        .section-head h2 {
            font-size: clamp(1.8rem, 3.5vw, 2.6rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.1;
            color: var(--text);
            margin-bottom: 0.75rem;
        }
        .section-head p {
            font-size: 1rem;
            color: var(--muted);
            max-width: 520px;
            line-height: 1.7;
        }
        .section-head.center p {
            margin-inline: auto;
        }

        /* ============================================================
        FEATURES — BENTO GRID
        ============================================================ */
        .bento-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 24px;
        }
        .bento {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 32px 28px;
            min-height: 180px;
            box-shadow: var(--shadow);
            transition: all var(--transition);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .bento:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.05);
        }
        .bento.large {
            grid-row: span 2;
        }
        .bento.wide {
            grid-column: span 2;
        }
        .bento h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .bento p {
            color: var(--muted);
            line-height: 1.6;
        }

        /* ============================================================
        STEPS
        ============================================================ */
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        .step-cell {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 2rem 1.75rem;
            box-shadow: var(--shadow);
            transition: all var(--transition);
        }
        .step-cell:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.05);
        }
        .step-num {
            font-family: 'Geist Mono', monospace;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: 0.08em;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }
        .step-num::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--card-border);
        }
        .step-cell h3 {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        .step-cell p {
            font-size: 0.8125rem;
            color: var(--muted);
            line-height: 1.7;
        }

        /* ============================================================
        TESTIMONIALS
        ============================================================ */
        .testi-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        .quote-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all var(--transition);
        }
        .quote-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.05);
        }
        .quote-text {
            font-size: 0.9375rem;
            color: var(--text);
            line-height: 1.75;
            margin-bottom: 1.25rem;
            font-style: italic;
        }
        .quote-author {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .author-init {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background: var(--primary-light);
            border: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--primary);
            font-family: 'Geist Mono', monospace;
            flex-shrink: 0;
        }
        .author-name {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text);
        }
        .author-role {
            font-size: 0.75rem;
            color: var(--muted);
        }

        /* ============================================================
        PRICING
        ============================================================ */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }
        .plan-cell {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 2rem 1.75rem;
            box-shadow: var(--shadow);
            position: relative;
            display: flex;
            flex-direction: column;
            transition: all var(--transition);
        }
        .plan-cell:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.06);
        }
        .plan-cell.featured {
            border-color: var(--primary);
            background: #f8faff;
        }
        .plan-featured-tag {
            font-family: 'Geist Mono', monospace;
            font-size: 0.6rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .plan-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text);
            letter-spacing: -0.02em;
            margin-bottom: 0.25rem;
        }
        .plan-price {
            font-size: clamp(1.8rem, 4vw, 2.2rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text);
            line-height: 1;
            margin: 1rem 0 0.375rem;
            font-family: 'Geist Mono', monospace;
        }
        .plan-per {
            font-size: 0.75rem;
            font-weight: 400;
            color: var(--muted);
            letter-spacing: 0;
            font-family: inherit;
        }
        .plan-desc {
            font-size: 0.8125rem;
            color: var(--muted);
            line-height: 1.65;
            margin-bottom: 1.5rem;
        }
        .plan-sep {
            margin: 1.25rem 0;
            border: 0;
            height: 1px;
            background: var(--card-border);
        }
        .plan-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1.75rem;
            flex: 1;
        }
        .plan-features li {
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            font-size: 0.8125rem;
            color: var(--text);
            line-height: 1.5;
        }
        .plan-features li.f-miss {
            color: var(--muted);
        }
        .plan-features li i {
            font-size: 0.75rem;
            margin-top: 0.2rem;
            flex-shrink: 0;
        }
        .plan-features li:not(.f-miss) i {
            color: var(--primary);
        }
        .plan-features li.f-miss i {
            color: #cbd5e1;
        }

        /* ============================================================
        FAQ
        ============================================================ */
        .faq-cols {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0 2.5rem;
        }
        .faq-list {
            display: flex;
            flex-direction: column;
        }
        .faq-item {
            border-bottom: 1px solid var(--card-border);
        }
        .faq-item:first-child {
            border-top: 1px solid var(--card-border);
        }
        .faq-item summary {
            padding: 1.125rem 0;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text);
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            user-select: none;
        }
        .faq-item summary::-webkit-details-marker {
            display: none;
        }
        .faq-chevron {
            font-size: 0.75rem;
            color: var(--muted);
            transition: transform 200ms;
            flex-shrink: 0;
        }
        .faq-item[open] .faq-chevron {
            transform: rotate(180deg);
        }
        .faq-item p {
            padding: 0 0 1.125rem;
            font-size: 0.875rem;
            color: var(--muted);
            line-height: 1.75;
        }

        /* ============================================================
        CTA
        ============================================================ */
        .cta-wrap {
            padding: 5rem 0;
        }
        .cta-box {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 4rem 3rem;
            text-align: center;
            box-shadow: var(--shadow);
        }
        .cta-box h2 {
            font-size: clamp(1.75rem, 3vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text);
            margin-bottom: 0.75rem;
        }
        .cta-box p {
            color: var(--muted);
            font-size: 0.9375rem;
            margin-bottom: 2rem;
        }
        .cta-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* ============================================================
        FOOTER
        ============================================================ */
        footer {
            border-top: 1px solid var(--card-border);
            padding: 3rem 0 2rem;
            margin-top: 2rem;
            background: var(--bg2);
        }
        .footer-top {
            display: flex;
            align-items: flex-start;
            gap: 2rem;
            padding-bottom: 2.5rem;
            flex-wrap: wrap;
        }
        .footer-about {
            font-size: 0.8125rem;
            color: var(--muted);
            line-height: 1.75;
            max-width: 460px;
            padding-top: 0.125rem;
            flex: 1;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 2rem;
            padding: 2rem 0;
            border-top: 1px solid var(--card-border);
        }
        .footer-col h4 {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 1rem;
        }
        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .footer-col ul li,
        .footer-col ul a {
            font-size: 0.8125rem;
            color: var(--muted);
            transition: color var(--transition);
        }
        .footer-col ul a:hover {
            color: var(--text);
        }
        .footer-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 0 1.5rem;
            border-top: 1px solid var(--card-border);
            flex-wrap: wrap;
        }
        .footer-copy {
            font-size: 0.75rem;
            color: var(--muted);
        }
        .footer-links ul {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        .footer-links li+li::before {
            content: '·';
            color: var(--muted);
            padding-right: 0.25rem;
            pointer-events: none;
            opacity: 0.4;
        }
        .footer-links a {
            font-size: 0.75rem;
            color: var(--muted);
            padding: 0.25rem 0.375rem;
            border-radius: 8px;
            transition: all var(--transition);
        }
        .footer-links a:hover {
            color: var(--text);
            background: rgba(0, 0, 0, 0.04);
        }

        /* ============================================================
        DIALOG
        ============================================================ */
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .overlay.open {
            display: flex;
        }
        .dialog {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            width: min(440px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            animation: dlgIn 0.2s ease;
        }
        .dialog.dialog-lg {
            width: min(520px, 100%);
        }
        @keyframes dlgIn {
            from {
                opacity: 0;
                transform: translateY(8px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .dlg-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--card-border);
        }
        .dlg-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text);
        }
        .dlg-sub {
            font-size: 0.8125rem;
            color: var(--muted);
            margin-top: 0.25rem;
        }
        .dlg-close {
            width: 1.75rem;
            height: 1.75rem;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            background: transparent;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: var(--muted);
            transition: all var(--transition);
            flex-shrink: 0;
        }
        .dlg-close:hover {
            background: var(--bg2);
            color: var(--text);
        }
        .dlg-body {
            padding: 1.25rem 1.5rem;
        }
        .dlg-foot {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            padding: 0 1.5rem 1.5rem;
            flex-wrap: wrap;
        }

        .role-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .role-item {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.875rem 1rem;
            border: 1px solid var(--card-border);
            border-radius: 12px;
            text-decoration: none;
            color: var(--text);
            background: var(--card-bg);
            transition: all var(--transition);
        }
        .role-item:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        .role-ico {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 8px;
            background: var(--bg2);
            border: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            color: var(--muted);
            flex-shrink: 0;
        }
        .role-name {
            font-size: 0.875rem;
            font-weight: 600;
        }
        .role-desc {
            font-size: 0.75rem;
            color: var(--muted);
        }
        .role-arr {
            margin-left: auto;
            color: var(--muted);
            font-size: 0.75rem;
        }

        /* Form */
        .form-row {
            margin-bottom: 1rem;
        }
        .form-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 0.375rem;
        }
        .form-ctrl {
            width: 100%;
            padding: 0.5625rem 0.75rem;
            font-size: 0.875rem;
            font-family: inherit;
            border: 1px solid var(--card-border);
            border-radius: 12px;
            background: var(--card-bg);
            color: var(--text);
            transition: all var(--transition);
            outline: none;
            line-height: 1.5;
        }
        .form-ctrl:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .form-ctrl::placeholder {
            color: #94a3b8;
        }
        textarea.form-ctrl {
            resize: vertical;
            min-height: 4.5rem;
        }
        select.form-ctrl option {
            background: var(--card-bg);
        }

        /* ============================================================
        REVEAL
        ============================================================ */
        .reveal {
            opacity: 0;
            transform: translateY(16px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        .reveal.on {
            opacity: 1;
            transform: none;
        }
        @media (prefers-reduced-motion: reduce) {
            .reveal {
                transition: none;
                opacity: 1;
                transform: none;
            }
        }

        /* ============================================================
        RESPONSIVE
        ============================================================ */
        @media (max-width: 1024px) {
            .hero-content {
                grid-template-columns: 1fr;
                gap: 40px;
                text-align: center;
            }
            .hero p {
                margin-inline: auto;
            }
            .hero-actions {
                justify-content: center;
            }
            .hero-trust {
                justify-content: center;
            }
            .hero-trust div {
                text-align: center;
            }
            .hero-right {
                grid-template-columns: 1fr 1fr;
                max-width: 600px;
                margin-inline: auto;
            }
            .hero-right .glass-card.large {
                grid-row: span 2;
            }
            .bento-grid {
                grid-template-columns: 1fr 1fr;
            }
            .bento.large {
                grid-row: span 1;
            }
            .bento.wide {
                grid-column: span 2;
            }
            .steps-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .faq-cols {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        @media (max-width: 768px) {
            .header-inner {
                height: 3.25rem;
                padding: 0 1rem;
            }
            .nav-links,
            .header-actions {
                display: none;
            }
            .menu-toggle {
                display: flex;
            }
            .brand {
                font-size: 0.85rem;
            }
            .brand-icon {
                width: 28px;
                height: 28px;
            }

            .hero {
                padding: 100px 0 3rem;
                min-height: auto;
            }
            .hero h1 {
                font-size: clamp(2.4rem, 10vw, 3.6rem);
            }
            .hero-right {
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }
            .hero-right .glass-card.large {
                grid-row: span 1;
            }
            .hero-right .glass-card {
                padding: 20px;
            }
            .hero-right .glass-card h2 {
                font-size: 2rem;
            }

            .stats-inner {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }
            .stat-cell+.stat-cell::before {
                display: none;
            }
            .stat-cell {
                padding: 0.5rem;
            }

            .bento-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .bento.wide {
                grid-column: span 1;
            }
            .bento {
                padding: 24px 20px;
                min-height: auto;
            }

            .steps-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .testi-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .pricing-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .cta-box {
                padding: 2.5rem 1.5rem;
            }
            section {
                padding: 3rem 0;
            }
            .section-head {
                margin-bottom: 2rem;
            }
            .footer-top {
                flex-direction: column;
                gap: 0.75rem;
                align-items: flex-start;
            }
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }
            .footer-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .overlay {
                padding: 0;
                align-items: flex-end;
            }
            .dialog {
                width: 100% !important;
                max-height: 90vh;
                border-radius: 20px 20px 0 0; 
                margin: 0;
                border: none;
                border-top: 1px solid var(--card-border);
            }
            .dialog.dialog-lg {
                width: 100% !important;
            }
            .dlg-head {
                padding: 1rem 1.25rem;
            }
            .dlg-body {
                padding: 1rem 1.25rem;
                max-height: 60vh;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
            .dlg-foot {
                padding: 0 1.25rem 1.25rem;
                flex-direction: column;
                gap: 0.5rem;
            }
            .dlg-foot .btn {
                width: 100%;
                justify-content: center;
            }
            .dlg-close {
                width: 2.2rem;
                height: 2.2rem;
                font-size: 1rem;
                border-radius: 50%;
                background: var(--bg2);
            }

            .menu-toggle {
                width: 2.5rem;
                height: 2.5rem;
                font-size: 1.1rem;
                border-radius: 10px;
                background: var(--bg2);
                border-color: var(--card-border);
            }
            .site-header {
                padding: 0 0.5rem;
            }
            .header-inner {
                padding: 0 0.75rem;
                height: 3rem;
                gap: 0.5rem;
                width: 100%;
            }

            .mobile-nav {
                padding: 0.75rem 1rem 1.25rem;
                gap: 0.125rem;
                max-height: 70vh;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
            .mobile-nav a {
                padding: 0.75rem 0.5rem;
                font-size: 1rem;
                border-bottom: 1px solid var(--card-border);
            }
            .mobile-nav-btns {
                padding-top: 0.75rem;
                gap: 0.75rem;
            }
            .mobile-nav-btns .btn {
                padding: 0.75rem;
                font-size: 1rem;
                min-height: 48px;
            }
            .header-inner {
                padding: 0 0.75rem;
                height: 3rem;
                gap: 0.5rem;
            }
            .brand {
                font-size: 0.75rem;
            }
            .brand-icon {
                width: 24px;
                height: 24px;
            }
            .menu-toggle {
                width: 1.75rem;
                height: 1.75rem;
                font-size: 0.75rem;
            }
            .btn {
                font-size: 0.8125rem;
                padding: 0.5rem 0.75rem;
                min-height: 40px;
                min-width: 40px;
            }
            .btn-lg {
                font-size: 0.875rem;
                padding: 0.5625rem 1rem;
                min-height: 48px;
            }
            .hero {
                padding: 80px 0 2rem;
            }
            .hero h1 {
                font-size: clamp(2rem, 12vw, 2.8rem);
            }
            .hero p {
                font-size: 0.9375rem;
            }
            .hero-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .hero-actions .btn {
                justify-content: center;
            }
            .hero-trust {
                gap: 1.5rem;
            }
            .hero-trust h3 {
                font-size: 1.25rem;
            }
            .hero-right {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .hero-right .glass-card.large {
                grid-row: auto;
            }
            .hero-right .glass-card {
                padding: 16px;
            }
            .hero-right .glass-card h2 {
                font-size: 1.8rem;
            }

            .stats-inner {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            .stat-n {
                font-size: 1.3rem;
            }
            .stat-n em {
                font-size: 1rem;
            }

            .bento-grid {
                gap: 12px;
            }
            .bento {
                padding: 20px 16px;
                border-radius: var(--radius-sm);
            }
            .bento h3 {
                font-size: 1.1rem;
            }

            .step-cell {
                padding: 1.5rem 1.25rem;
                border-radius: var(--radius-sm);
            }
            .plan-cell {
                padding: 1.5rem 1.25rem;
                border-radius: var(--radius-sm);
            }
            .quote-card {
                padding: 1.25rem;
                border-radius: var(--radius-sm);
            }
            .cta-box {
                padding: 2rem 1.25rem;
                border-radius: var(--radius-sm);
            }
            .cta-box h2 {
                font-size: 1.5rem;
            }
            .cta-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .cta-actions .btn {
                justify-content: center;
            }

            footer {
                padding: 2rem 0 1.5rem;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            .footer-bar {
                padding: 1rem 0 1.25rem;
                align-items: center;
                text-align: center;
            }
            .footer-links ul {
                justify-content: center;
            }

            .dialog {
                border-radius: var(--radius-sm);
                width: 100%;
            }
            .dlg-head,
            .dlg-body,
            .dlg-foot {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .dlg-foot {
                flex-direction: column;
                align-items: stretch;
            }
            .dlg-foot .btn {
                justify-content: center;
            }
            .role-item {
                padding: 0.75rem;
                border-radius: 10px;
            }
            .role-ico {
                width: 2rem;
                height: 2rem;
                font-size: 0.75rem;
            }
        }

        @media (min-width: 1400px) {
            .container {
                width: min(1200px, 85vw);
            }
            .hero-content {
                gap: 80px;
            }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="site-header">
    <div class="header-inner">
        <a href="#home" class="brand">
            <img src="<?php echo APP_URL; ?>/assets/icons/icon.png" class="brand-icon" alt="">
        </a>
        <nav class="nav-links" aria-label="Utama">
            <a href="#fitur">Fitur</a>
            <a href="#cara-kerja">Cara Kerja</a>
            <a href="#paket">Paket</a>
            <a href="#faq">FAQ</a>
        </nav>
        <div class="header-actions">
            <button type="button" class="btn btn-ghost btn-sm" id="openLogin">Masuk</button>
            <button type="button" class="btn btn-primary btn-sm" id="openReg">Daftar</button>
        </div>
        <button type="button" class="menu-toggle" id="menuToggle" aria-label="Menu">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
    </div>
    <div class="mobile-nav" id="mobileNav" aria-hidden="true">
        <a href="#fitur">Fitur</a>
        <a href="#cara-kerja">Cara Kerja</a>
        <a href="#paket">Paket</a>
        <a href="#faq">FAQ</a>
        <div class="mob-sep"></div>
        <div class="mobile-nav-btns">
            <button type="button" class="btn btn-secondary btn-sm" style="flex:1" id="openLoginMob">Masuk</button>
            <button type="button" class="btn btn-primary btn-sm" style="flex:1" id="openRegMob">Daftar</button>
        </div>
    </div>
</header>

<main id="home">

    <!-- HERO -->
    <section class="hero">
        <div class="hero-bg-glow"></div>
        <div class="container">
            <div class="hero-content">
                <div class="hero-left">
                    <div class="badge badge-ok" style="margin-bottom:1.5rem;">
                        <i class="fas fa-circle" style="font-size:0.4rem" aria-hidden="true"></i>
                        Active
                    </div>
                    <h1><?php echo strip_tags($heroTitle); ?></h1>
                    <p>
                        Streaming 4K, gaming, meeting, dan kebutuhan bisnis
                        tanpa gangguan dengan jaringan fiber generasi terbaru.
                    </p>
                    <div class="hero-actions">
                        <button class="btn btn-primary btn-lg" id="heroReg">
                            Berlangganan Sekarang
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </button>
                        <a href="<?php echo htmlspecialchars((isset($settings['WHATSAPP_ADMIN_NUMBER']) ? "https://wa.me/".$settings['WHATSAPP_ADMIN_NUMBER'] : false) ?? ''); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-lg">
                            Whatsapp
                            <i class="fab fa-whatsapp" aria-hidden="true"></i>
                        </a>
                    </div>
                    <div class="hero-trust">
                        <div>
                            <h3>99.9%</h3>
                            <span>Uptime</span>
                        </div>
                        <div>
                            <h3>5000+</h3>
                            <span>Pelanggan</span>
                        </div>
                        <div>
                            <h3>24/7</h3>
                            <span>Support</span>
                        </div>
                    </div>
                </div>

                <div class="hero-right">
                    <div class="glass-card large">
                        <h3>Coverage Area</h3>
                        <p>Jaringan Fiber Optic</p>
                        <div style="margin-top:1rem;font-size:0.875rem;color:var(--muted);">
                            <i class="fas fa-check-circle" style="color:var(--primary);"></i> 20+ kota
                        </div>
                    </div>
                    <div class="glass-card">
                        <h2>99.9%</h2>
                        <span>Network Availability</span>
                    </div>
                    <div class="glass-card">
                        <h2>24/7</h2>
                        <span>Technical Support</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- STATS -->
    <div class="stats-row">
        <div class="container">
            <div class="stats-inner">
                <div class="stat-cell reveal">
                    <div class="stat-n">99<em>.8%</em></div>
                    <div class="stat-label">uptime jaringan</div>
                </div>
                <div class="stat-cell reveal">
                    <div class="stat-n">&lt;24<em>j</em></div>
                    <div class="stat-label">estimasi aktivasi</div>
                </div>
                <div class="stat-cell reveal">
                    <div class="stat-n">24<em>/7</em></div>
                    <div class="stat-label">dukungan teknis</div>
                </div>
                <div class="stat-cell reveal">
                    <div class="stat-n">Fiber</div>
                    <div class="stat-label">teknologi akses</div>
                </div>
            </div>
        </div>
    </div>

    <!-- FEATURES — BENTO GRID -->
    <section id="fitur" class="section-muted">
        <div class="container">
            <div class="section-head center reveal">
                <div class="section-tag">Layanan</div>
                <h2>Dirancang untuk kebutuhan nyata</h2>
                <p>Bukan sekadar angka di speedtest — infrastruktur yang stabil untuk aktivitas harian yang penting.</p>
            </div>
            <div class="bento-grid reveal">
                <div class="bento large">
                    <h3>Fiber Optic Berkecepatan Tinggi</h3>
                    <p>Koneksi simetris dengan latensi rendah, ideal untuk streaming 4K, gaming, dan work from home.</p>
                </div>
                <div class="bento">
                    <h3>24/7 Support</h3>
                    <p>Teknisi lokal siap membantu kapan pun Anda butuh.</p>
                </div>
                <div class="bento">
                    <h3>99.9% Uptime</h3>
                    <p>Jaringan stabil dengan monitoring aktif 24 jam.</p>
                </div>
                <div class="bento wide">
                    <h3>Coverage Luas</h3>
                    <p>Jangkauan fiber optic terus diperluas ke berbagai wilayah.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="cara-kerja">
        <div class="container">
            <div class="section-head reveal">
                <div class="section-tag">Cara Berlangganan</div>
                <h2>Tiga langkah sampai online</h2>
                <p>Daftar online, kami yang datang. Tidak perlu ke kantor.</p>
            </div>
            <div class="steps-grid reveal">
                <div class="step-cell">
                    <div class="step-num">01</div>
                    <h3>Daftar & Pilih Paket</h3>
                    <p>Isi formulir pendaftaran, pilih paket yang sesuai. Data langsung masuk ke sistem kami.</p>
                </div>
                <div class="step-cell">
                    <div class="step-num">02</div>
                    <h3>Verifikasi & Survey</h3>
                    <p>Tim kami menghubungi via WhatsApp untuk konfirmasi data dan survey lokasi pemasangan.</p>
                </div>
                <div class="step-cell">
                    <div class="step-num">03</div>
                    <h3>Instalasi & Aktif</h3>
                    <p>Teknisi datang, instalasi selesai, akun aktif. Langsung online.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section class="section-muted">
        <div class="container">
            <div class="section-head center reveal">
                <div class="section-tag">Pelanggan</div>
                <h2>Kata mereka yang sudah pakai</h2>
            </div>
            <div class="testi-grid">
                <div class="quote-card reveal">
                    <p class="quote-text">"Sejak pakai internet ini, kerja WFA jadi jauh lebih lancar. Kalau ada gangguan, teknisinya bisa datang hari itu juga."</p>
                    <div class="quote-author">
                        <div class="author-init" aria-hidden="true">AW</div>
                        <div>
                            <div class="author-name">Andi Wijaya</div>
                            <div class="author-role">Pengusaha · 2 tahun</div>
                        </div>
                    </div>
                </div>
                <div class="quote-card reveal">
                    <p class="quote-text">"Harga bersaing, jaringan stabil, notif tagihan WhatsApp sangat membantu. Tidak pernah tiba-tiba putus karena lupa bayar."</p>
                    <div class="quote-author">
                        <div class="author-init" aria-hidden="true">SN</div>
                        <div>
                            <div class="author-name">Siti Nurhaliza</div>
                            <div class="author-role">Freelancer · 1 tahun</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PRICING -->
    <section id="paket">
        <div class="container">
            <div class="section-head center reveal">
                <div class="section-tag">Paket</div>
                <h2>Harga transparan</h2>
                <p>Tidak ada biaya setup tersembunyi. Semua paket sudah termasuk instalasi dan perangkat dasar.</p>
            </div>
            <div class="pricing-grid reveal">
                <?php foreach ($packages as $idx => $pkg):
                    $featured = $idx === 1;
                    $vm = modernUltraBuildVisibleServiceMap($pkg, $packageFeatureList, $packageFeatureTypes);
                    ?>
                    <div class="plan-cell <?php echo $featured ? 'featured' : ''; ?>">
                        <?php if ($featured): ?>
                            <div class="plan-featured-tag">— Paling Populer</div>
                        <?php endif; ?>
                        <div class="plan-name"><?php echo htmlspecialchars($pkg['name']); ?></div>
                        <div class="plan-price">
                            <?php echo formatCurrency($pkg['price']); ?>
                            <span class="plan-per">/bln</span>
                        </div>
                        <p class="plan-desc"><?php echo htmlspecialchars($pkg['description'] ?? 'Koneksi stabil untuk rumah dan bisnis.'); ?></p>
                        <hr class="plan-sep">
                        <?php if (!empty($packageFeatureList)): ?>
                            <ul class="plan-features">
                                <?php foreach ($packageFeatureList as $key => $name):
                                    if (empty($vm[$key])) continue;
                                    $inc = modernUltraServiceActive($pkg, $key);
                                    ?>
                                    <li class="<?php echo $inc ? '' : 'f-miss'; ?>">
                                        <i class="fas <?php echo $inc ? 'fa-check' : 'fa-minus'; ?>" aria-hidden="true"></i>
                                        <?php echo htmlspecialchars($name); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <ul class="plan-features">
                                <li><i class="fas fa-check" aria-hidden="true"></i> Koneksi fiber optic stabil</li>
                                <li><i class="fas fa-check" aria-hidden="true"></i> Dukungan teknis responsif</li>
                                <li><i class="fas fa-check" aria-hidden="true"></i> Notifikasi tagihan WhatsApp</li>
                                <li><i class="fas fa-check" aria-hidden="true"></i> Portal pelanggan mandiri</li>
                                <li><i class="fas fa-check" aria-hidden="true"></i> Monitoring berkala</li>
                            </ul>
                        <?php endif; ?>
                        <button type="button"
                                class="btn btn-full <?php echo $featured ? 'btn-primary' : 'btn-secondary'; ?>"
                                onclick="openRegWithPkg('<?php echo addslashes(htmlspecialchars($pkg['name'])); ?>')">
                            Pilih Paket
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <?php if (!empty($faqs) && is_array($faqs)): ?>
        <section id="faq" class="section-muted">
            <div class="container">
                <div class="section-head reveal">
                    <div class="section-tag">FAQ</div>
                    <h2>Pertanyaan yang sering ditanya</h2>
                </div>
                <div class="faq-cols">
                    <?php
                    $mid = (int)ceil(count($faqs) / 2);
                    foreach ([array_slice($faqs, 0, $mid), array_slice($faqs, $mid)] as $col): ?>
                        <div class="faq-list">
                            <?php foreach ($col as $f): ?>
                                <details class="faq-item">
                                    <summary>
                                        <?php echo htmlspecialchars($f['question'] ?? ''); ?>
                                        <i class="fas fa-chevron-down faq-chevron" aria-hidden="true"></i>
                                    </summary>
                                    <p><?php echo nl2br(htmlspecialchars($f['answer'] ?? '')); ?></p>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- CTA -->
    <div class="cta-wrap">
        <div class="container">
            <div class="cta-box reveal">
                <h2>Siap beralih ke internet yang lebih stabil?</h2>
                <p>Daftar sekarang — survey hingga aktivasi gratis, tim kami yang urus semuanya.</p>
                <div class="cta-actions">
                    <button type="button" class="btn btn-primary btn-lg" id="ctaReg">Daftar Berlangganan</button>
                    <a href="<?php echo htmlspecialchars($s_wa ?? '#'); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-lg">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i>
                        Tanya via WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- FOOTER -->
<footer>
    <div class="container">
        <div class="footer-top">
            <a href="#" class="brand">
                <img src="<?php echo APP_URL; ?>/assets/icons/icon.png" class="brand-logo" alt="">
            </a>
            <p class="footer-about"><?php echo htmlspecialchars($footerAbout ?? 'ISP lokal dengan fokus pada stabilitas jaringan dan kepuasan pelanggan.'); ?></p>
        </div>
        <div class="footer-grid">
            <div class="footer-col">
                <h4>Kontak</h4>
                <ul>
                    <li><?php echo htmlspecialchars($contactPhone ?? ''); ?></li>
                    <li><?php echo htmlspecialchars($contactEmail ?? ''); ?></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Media Sosial</h4>
                <ul>
                    <?php if (!empty($s_fb)): ?><li><a href="<?php echo htmlspecialchars($s_fb); ?>" target="_blank" rel="noopener">Facebook</a></li><?php endif; ?>
                    <?php if (!empty($s_ig)): ?><li><a href="<?php echo htmlspecialchars($s_ig); ?>" target="_blank" rel="noopener">Instagram</a></li><?php endif; ?>
                    <?php if (!empty($s_tw)): ?><li><a href="<?php echo htmlspecialchars($s_tw); ?>" target="_blank" rel="noopener">Twitter / X</a></li><?php endif; ?>
                    <?php if (!empty($s_yt)): ?><li><a href="<?php echo htmlspecialchars($s_yt); ?>" target="_blank" rel="noopener">YouTube</a></li><?php endif; ?>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Navigasi</h4>
                <ul>
                    <li><a href="#fitur">Fitur</a></li>
                    <li><a href="#paket">Paket Internet</a></li>
                    <li><a href="#cara-kerja">Cara Berlangganan</a></li>
                    <li><a href="#faq">FAQ</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bar">
            <span class="footer-copy">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</span>
            <nav class="footer-links" aria-label="Tautan kebijakan">
                <ul>
                    <li><a href="terms.php">Syarat & Ketentuan</a></li>
                    <li><a href="privacy.php">Privasi</a></li>
                    <li><a href="about.php">Tentang</a></li>
                </ul>
            </nav>
        </div>
    </div>
</footer>

<!-- DIALOG: Login -->
<div class="overlay" id="loginOverlay" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
    <div class="dialog">
        <div class="dlg-head">
            <div>
                <div class="dlg-title" id="loginTitle">Pilih portal masuk</div>
                <div class="dlg-sub">Pilih sesuai peran Anda.</div>
            </div>
            <button class="dlg-close" id="closeLogin" aria-label="Tutup"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="dlg-body">
            <div class="role-list">
                <a class="role-item" href="portal/login.php">
                    <div class="role-ico"><i class="fas fa-user" aria-hidden="true"></i></div>
                    <div>
                        <div class="role-name">Portal Pelanggan</div>
                        <div class="role-desc">Tagihan, riwayat, dan status koneksi</div>
                    </div>
                    <i class="fas fa-arrow-right role-arr" aria-hidden="true"></i>
                </a>
                <a class="role-item" href="technician/login.php">
                    <div class="role-ico"><i class="fas fa-screwdriver-wrench" aria-hidden="true"></i></div>
                    <div>
                        <div class="role-name">Portal Teknisi</div>
                        <div class="role-desc">Tiket dan jadwal kunjungan</div>
                    </div>
                    <i class="fas fa-arrow-right role-arr" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- DIALOG: Register -->
<div class="overlay" id="regOverlay" role="dialog" aria-modal="true" aria-labelledby="regTitle">
    <div class="dialog dialog-lg">
        <div class="dlg-head">
            <div>
                <div class="dlg-title" id="regTitle">Daftar berlangganan</div>
                <div class="dlg-sub">Isi data berikut, kami hubungi via WhatsApp segera.</div>
            </div>
            <button class="dlg-close" id="closeReg" aria-label="Tutup"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="dlg-body">
            <form id="regForm" novalidate>
                <div class="form-row">
                    <label class="form-label" for="r-name">Nama lengkap</label>
                    <input type="text" id="r-name" name="name" class="form-ctrl" placeholder="Nama sesuai KTP" required autocomplete="name">
                </div>
                <div class="form-row">
                    <label class="form-label" for="r-phone">Nomor WhatsApp</label>
                    <input type="tel" id="r-phone" name="phone" class="form-ctrl" placeholder="08xx xxxx xxxx" required autocomplete="tel">
                </div>
                <div class="form-row">
                    <label class="form-label" for="r-address">Alamat pemasangan</label>
                    <textarea id="r-address" name="address" class="form-ctrl" rows="2" placeholder="Nama jalan, RT/RW, patokan lokasi" required></textarea>
                </div>
                <div class="form-row">
                    <label class="form-label" for="r-pkg">Paket yang diminati</label>
                    <select id="r-pkg" name="package" class="form-ctrl">
                        <option value="">Pilih paket (opsional)</option>
                        <?php foreach ($packages as $pkg): ?>
                            <option value="<?php echo htmlspecialchars($pkg['name']); ?>">
                                <?php echo htmlspecialchars($pkg['name']); ?> — <?php echo formatCurrency($pkg['price']); ?>/bln
                            </option>
                        <?php endforeach; ?>
                        <option value="Lainnya">Belum tahu, minta rekomendasi</option>
                    </select>
                </div>
                <div class="form-row" style="margin-bottom:0">
                    <label class="form-label" for="r-notes">Catatan (opsional)</label>
                    <input type="text" id="r-notes" name="notes" class="form-ctrl" placeholder="Jam bisa dihubungi, dll.">
                </div>
            </form>
        </div>
        <div class="dlg-foot">
            <button type="button" class="btn btn-ghost" id="cancelReg">Batal</button>
            <button type="button" class="btn btn-primary" id="submitReg">Kirim Pendaftaran</button>
        </div>
    </div>
</div>

<script>
    (function(){
        'use strict';

        /* Scroll reveal */
        var obs = new IntersectionObserver(function(entries){
            entries.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('on'); obs.unobserve(e.target); } });
        }, {threshold: 0.07});
        document.querySelectorAll('.reveal').forEach(function(el){ obs.observe(el); });

        /* Mobile nav */
        var toggle = document.getElementById('menuToggle');
        var mob    = document.getElementById('mobileNav');
        if (toggle && mob) {
            toggle.addEventListener('click', function(){
                var open = mob.classList.toggle('open');
                mob.setAttribute('aria-hidden', !open);
                toggle.querySelector('i').className = open ? 'fas fa-xmark' : 'fas fa-bars';
            });
            mob.querySelectorAll('a').forEach(function(a){
                a.addEventListener('click', function(){ mob.classList.remove('open'); mob.setAttribute('aria-hidden','true'); toggle.querySelector('i').className='fas fa-bars'; });
            });
        }

        /* Dialog utils */
        function open(id){ var el=document.getElementById(id); if(el) el.classList.add('open'); }
        function close(id){ var el=document.getElementById(id); if(el) el.classList.remove('open'); }
        document.querySelectorAll('.overlay').forEach(function(o){
            o.addEventListener('click', function(e){ if(e.target===o) o.classList.remove('open'); });
        });
        document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ close('loginOverlay'); close('regOverlay'); } });

        /* Login */
        ['openLogin','openLoginMob'].forEach(function(id){ var el=document.getElementById(id); if(el) el.addEventListener('click', function(){ open('loginOverlay'); }); });
        var cl=document.getElementById('closeLogin'); if(cl) cl.addEventListener('click', function(){ close('loginOverlay'); });

        /* Register */
        function openReg(){ open('regOverlay'); }
        window.openRegWithPkg = function(pkg){
            var sel=document.getElementById('r-pkg');
            if(sel && pkg){ for(var i=0;i<sel.options.length;i++){ if(sel.options[i].value===pkg){ sel.selectedIndex=i; break; } } }
            openReg();
        };
        ['openReg','openRegMob','heroReg','ctaReg'].forEach(function(id){ var el=document.getElementById(id); if(el) el.addEventListener('click', openReg); });
        ['closeReg','cancelReg'].forEach(function(id){ var el=document.getElementById(id); if(el) el.addEventListener('click', function(){ close('regOverlay'); }); });

        /* Form submit */
        var sb = document.getElementById('submitReg');
        if (sb) {
            sb.addEventListener('click', function(){
                var form = document.getElementById('regForm');
                var ok = true;
                form.querySelectorAll('[required]').forEach(function(f){
                    if(!f.value.trim()){ f.style.borderColor='rgba(239,68,68,0.6)'; ok=false; }
                    else f.style.borderColor='';
                });
                if(!ok) return;
                sb.disabled=true; sb.textContent='Mengirim...';
                fetch('<?php echo APP_URL; ?>/register-handler.php', {method:'POST', body:new FormData(form)})
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if(d.success){ close('regOverlay'); form.reset(); alert('Pendaftaran berhasil! Tim kami akan menghubungi Anda via WhatsApp.'); }
                        else { alert('Gagal: '+(d.message||'Terjadi kesalahan.')); }
                    })
                    .catch(function(){ alert('Gagal terhubung ke server.'); })
                    .finally(function(){ sb.disabled=false; sb.textContent='Kirim Pendaftaran'; });
            });
        }
    })();
</script>
</body>
</html>