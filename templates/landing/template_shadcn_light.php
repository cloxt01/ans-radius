<?php
/**
 * Landing Page — Elegant Dark Theme
 * Monochromatic, minimal, shadcn-style tokens
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> — Internet Lokal</title>
    <meta name="description" content="<?php echo APP_NAME; ?> — ISP lokal, koneksi stabil, harga transparan.">
    <meta name="theme-color" content="#0a0a0a">
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
           DESIGN TOKENS — LIGHT THEME
           ============================================================ */
        :root {
            /* Surface layers */
            --base: #ffffff;
            --surface-1: #f8f9fa;
            --surface-2: #f1f3f5;
            --surface-3: #e9ecef;
            --surface-4: #dee2e6;

            /* Borders — soft */
            --border-1: rgba(0,0,0,0.06);
            --border-2: rgba(0,0,0,0.10);
            --border-3: rgba(0,0,0,0.16);

            /* Text */
            --text-1: #212529;
            --text-2: #495057;
            --text-3: #6c757d;

            /* Accent — dark */
            --accent: #212529;
            --accent-dim: rgba(0,0,0,0.05);

            /* Semantic */
            --ok: #198754;
            --ok-dim: rgba(25,135,84,0.1);
            --warn: #ffc107;

            /* Radius */
            --r-sm: 4px;
            --r-md: 8px;
            --r-lg: 12px;

            /* Mono font */
            --mono: 'Geist Mono', 'Fira Code', monospace;
        }

        /* ============================================================
           RESET & BASE
           ============================================================ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Geist', ui-sans-serif, system-ui, sans-serif;
            background: var(--base);
            color: var(--text-1);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        a { color: var(--text-1); text-decoration: none; }
        a:hover { color: var(--accent); }
        img { display: block; }

        .container {
            width: min(1080px, 90vw);
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
            font-weight: 500;
            line-height: 1;
            padding: 0.5625rem 1rem;
            border-radius: var(--r-md);
            border: 1px solid transparent;
            cursor: pointer;
            transition: background 120ms, border-color 120ms, color 120ms;
            white-space: nowrap;
            outline: none;
            text-decoration: none;
        }
        .btn:focus-visible {
            box-shadow: 0 0 0 2px var(--base), 0 0 0 4px var(--border-3);
        }

        /* Primary — dark fill, white text */
        .btn-primary {
            background: var(--accent);
            color: #ffffff;
            border-color: var(--accent);
        }
        .btn-primary:hover {
            background: #000000;
            border-color: #000000;
            color: #ffffff;
        }

        /* Secondary — outlined */
        .btn-secondary {
            background: transparent;
            color: var(--text-2);
            border-color: var(--border-2);
        }
        .btn-secondary:hover {
            background: var(--surface-2);
            color: var(--text-1);
            border-color: var(--border-3);
        }

        /* Ghost */
        .btn-ghost {
            background: transparent;
            color: var(--text-2);
            border-color: transparent;
        }
        .btn-ghost:hover {
            background: var(--surface-2);
            color: var(--text-1);
        }

        .btn-sm  { padding: 0.4375rem 0.75rem; font-size: 0.8125rem; }
        .btn-lg  { padding: 0.6875rem 1.375rem; font-size: 0.9375rem; }
        .btn-full { width: 100%; }

        /* ============================================================
           BADGE
           ============================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            border: 1px solid var(--border-2);
            background: var(--surface-2);
            color: var(--text-2);
            line-height: 1;
        }
        .badge-ok {
            background: var(--ok-dim);
            border-color: rgba(25,135,84,0.2);
            color: var(--ok);
        }

        /* ============================================================
           CARD
           ============================================================ */
        .card {
            background: var(--surface-1);
            border: 1px solid var(--border-1);
            border-radius: var(--r-lg);
        }
        .card-p { padding: 1.5rem; }

        /* ============================================================
           SEPARATOR
           ============================================================ */
        .sep {
            height: 1px;
            background: var(--border-1);
            border: none;
        }

        /* ============================================================
           HEADER
           ============================================================ */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-1);
        }
        .header-inner {
            width: min(1080px, 90vw);
            margin-inline: auto;
            height: 3.25rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-1);
            flex-shrink: 0;
            letter-spacing: -0.01em;
        }
        .brand-logo {
            width: 54px;
            height: 54px;

        }
        .brand-icon {
            width: 22px;
            height: 22px;
            border-radius: 5px;
            border: 1px solid var(--border-2);
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.125rem;
            flex: 1;
        }
        .nav-links a {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-3);
            padding: 0.375rem 0.625rem;
            border-radius: var(--r-sm);
            transition: color 120ms, background 120ms;
        }
        .nav-links a:hover {
            color: var(--text-1);
            background: var(--surface-2);
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
            border: 1px solid var(--border-2);
            border-radius: var(--r-sm);
            width: 2rem; height: 2rem;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-2);
            font-size: 0.875rem;
        }
        .mobile-nav {
            display: none;
            border-top: 1px solid var(--border-1);
            background: var(--base);
            padding: 0.625rem;
            flex-direction: column;
            gap: 0.125rem;
        }
        .mobile-nav.open { display: flex; }
        .mobile-nav a {
            font-size: 0.875rem;
            color: var(--text-2);
            padding: 0.5625rem 0.75rem;
            border-radius: var(--r-sm);
            display: block;
            transition: color 120ms, background 120ms;
        }
        .mobile-nav a:hover { color: var(--text-1); background: var(--surface-2); }
        .mobile-nav .mob-sep { height: 1px; background: var(--border-1); margin: 0.5rem 0; }
        .mobile-nav-btns { display: flex; gap: 0.5rem; padding: 0.25rem 0; }

        /* ============================================================
           HERO
           ============================================================ */
        .hero {
            padding: 6rem 0 5rem;
        }
        .hero-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5rem;
            align-items: center;
        }
        .hero-eyebrow { margin-bottom: 1.25rem; }
        .hero h1 {
            font-size: clamp(2.25rem, 5vw, 3.75rem);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1.05;
            color: var(--text-1);
            margin-bottom: 1.25rem;
        }
        .hero-lead {
            font-size: 1rem;
            color: var(--text-2);
            line-height: 1.75;
            margin-bottom: 2rem;
            max-width: 440px;
        }
        .hero-actions {
            display: flex;
            gap: 0.625rem;
            flex-wrap: wrap;
        }
        .hero-trust {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-top: 1.75rem;
            flex-wrap: wrap;
        }
        .trust-item {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8125rem;
            color: var(--text-3);
            font-family: var(--mono);
        }
        .trust-item i { color: var(--ok); font-size: 0.75rem; }

        /* Terminal */
        .terminal {
            background: var(--surface-1);
            border: 1px solid var(--border-2);
            border-radius: var(--r-lg);
            overflow: hidden;
        }
        .terminal-bar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6875rem 1rem;
            background: var(--surface-2);
            border-bottom: 1px solid var(--border-1);
        }
        .t-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: var(--surface-4);
        }
        .terminal-title {
            font-size: 0.6875rem;
            color: var(--text-3);
            font-family: var(--mono);
            margin: 0 auto;
            letter-spacing: 0.04em;
        }
        .terminal-body {
            padding: 1.125rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.1875rem;
            min-height: 200px;
        }
        .t-line {
            font-family: var(--mono);
            font-size: 0.75rem;
            line-height: 1.65;
            color: var(--text-3);
            opacity: 0;
            animation: fadeIn 0.1s ease forwards;
        }
        .t-line.t-cmd  { color: var(--text-2); }
        .t-line.t-ok   { color: var(--text-2); }
        .t-line.t-stat { color: var(--text-3); }
        .t-ms  { color: var(--ok); }
        .t-cursor {
            display: inline-block;
            width: 6px; height: 0.85em;
            background: var(--text-2);
            vertical-align: text-bottom;
            border-radius: 1px;
            animation: blink 1.1s step-end infinite;
            margin-left: 1px;
        }
        @keyframes fadeIn { to { opacity: 1; } }
        @keyframes blink  { 0%,100% { opacity:1; } 50% { opacity:0; } }

        /* ============================================================
           STATS
           ============================================================ */
        .stats-row {
            border-top: 1px solid var(--border-1);
            border-bottom: 1px solid var(--border-1);
            padding: 2.25rem 0;
        }
        .stats-inner {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
        }
        .stat-cell {
            padding: 0 2rem;
            text-align: center;
            position: relative;
        }
        .stat-cell + .stat-cell::before {
            content: '';
            position: absolute;
            left: 0; top: 15%; bottom: 15%;
            width: 1px;
            background: var(--border-1);
        }
        .stat-n {
            font-size: 1.875rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--text-1);
            line-height: 1;
            margin-bottom: 0.375rem;
            font-family: var(--mono);
        }
        .stat-n em { color: var(--text-3); font-style: normal; font-weight: 400; font-size: 1.125rem; }
        .stat-label { font-size: 0.75rem; color: var(--text-3); letter-spacing: 0.02em; }

        /* ============================================================
           SECTION STRUCTURE
           ============================================================ */
        section { padding: 5rem 0; }
        .section-muted { background: var(--surface-1); }

        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-3);
            margin-bottom: 1rem;
            font-family: var(--mono);
        }
        .section-tag::before {
            content: '';
            width: 1.25rem;
            height: 1px;
            background: var(--border-3);
            flex-shrink: 0;
        }
        .section-head { margin-bottom: 3rem; }
        .section-head.center { text-align: center; }
        .section-head.center .section-tag { justify-content: center; }
        .section-head h2 {
            font-size: clamp(1.625rem, 3.5vw, 2.375rem);
            font-weight: 800;
            letter-spacing: -0.035em;
            line-height: 1.1;
            color: var(--text-1);
            margin-bottom: 0.75rem;
        }
        .section-head p {
            font-size: 0.9375rem;
            color: var(--text-2);
            max-width: 500px;
            line-height: 1.75;
        }
        .section-head.center p { margin-inline: auto; }

        /* ============================================================
           FEATURES
           ============================================================ */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border: 1px solid var(--border-1);
            border-radius: var(--r-lg);
            overflow: hidden;
            gap: 1px;
            background: var(--border-1);
        }
        .feature-cell {
            background: var(--surface-1);
            padding: 1.75rem;
            transition: background 150ms;
        }
        .feature-cell:hover { background: var(--surface-2); }
        .f-icon {
            width: 2rem; height: 2rem;
            border: 1px solid var(--border-2);
            border-radius: var(--r-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.875rem;
            color: var(--text-2);
            margin-bottom: 1.125rem;
            background: var(--surface-2);
        }
        .feature-cell h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-1);
            margin-bottom: 0.5rem;
            letter-spacing: -0.01em;
        }
        .feature-cell p {
            font-size: 0.8125rem;
            color: var(--text-3);
            line-height: 1.7;
        }

        /* ============================================================
           STEPS
           ============================================================ */
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--border-1);
            border: 1px solid var(--border-1);
            border-radius: var(--r-lg);
            overflow: hidden;
        }
        .step-cell {
            background: var(--surface-1);
            padding: 2rem 1.75rem;
        }
        .step-num {
            font-family: var(--mono);
            font-size: 0.6875rem;
            font-weight: 500;
            color: var(--text-3);
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
            background: var(--border-2);
        }
        .step-cell h3 {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-1);
            margin-bottom: 0.5rem;
            letter-spacing: -0.01em;
        }
        .step-cell p {
            font-size: 0.8125rem;
            color: var(--text-3);
            line-height: 1.7;
        }

        /* ============================================================
           TESTIMONIALS
           ============================================================ */
        .testi-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        .quote-card { padding: 1.5rem; }
        .quote-text {
            font-size: 0.9375rem;
            color: var(--text-2);
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
            width: 2rem; height: 2rem;
            border-radius: 50%;
            background: var(--surface-3);
            border: 1px solid var(--border-2);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.6875rem;
            font-weight: 700;
            color: var(--text-2);
            font-family: var(--mono);
            flex-shrink: 0;
        }
        .author-name {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text-1);
        }
        .author-role { font-size: 0.75rem; color: var(--text-3); }

        /* ============================================================
           PRICING
           ============================================================ */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1px;
            background: var(--border-1);
            border: 1px solid var(--border-1);
            border-radius: var(--r-lg);
            overflow: hidden;
        }
        .plan-cell {
            background: var(--surface-1);
            padding: 2rem 1.75rem;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .plan-cell.featured {
            background: var(--surface-2);
        }
        .plan-featured-tag {
            font-family: var(--mono);
            font-size: 0.625rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-3);
            margin-bottom: 1rem;
        }
        .plan-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-1);
            letter-spacing: -0.02em;
            margin-bottom: 0.25rem;
        }
        .plan-price {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.05em;
            color: var(--text-1);
            line-height: 1;
            margin: 1rem 0 0.375rem;
            font-family: var(--mono);
        }
        .plan-per {
            font-size: 0.75rem;
            font-weight: 400;
            color: var(--text-3);
            letter-spacing: 0;
            font-family: inherit;
        }
        .plan-desc {
            font-size: 0.8125rem;
            color: var(--text-3);
            line-height: 1.65;
            margin-bottom: 1.5rem;
        }
        .plan-sep { margin: 1.25rem 0; background: var(--border-1); }
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
            color: var(--text-2);
            line-height: 1.5;
        }
        .plan-features li.f-miss { color: var(--text-3); }
        .plan-features li i { font-size: 0.75rem; margin-top: 0.2rem; flex-shrink: 0; }
        .plan-features li:not(.f-miss) i { color: var(--ok); }
        .plan-features li.f-miss i { color: var(--surface-4); }

        /* ============================================================
           FAQ
           ============================================================ */
        .faq-cols {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0 3rem;
        }
        .faq-list { display: flex; flex-direction: column; }
        .faq-item {
            border-bottom: 1px solid var(--border-1);
        }
        .faq-item:first-child { border-top: 1px solid var(--border-1); }
        .faq-item summary {
            padding: 1.125rem 0;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-1);
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            user-select: none;
            letter-spacing: -0.01em;
        }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-chevron {
            font-size: 0.75rem;
            color: var(--text-3);
            transition: transform 200ms;
            flex-shrink: 0;
        }
        .faq-item[open] .faq-chevron { transform: rotate(180deg); }
        .faq-item p {
            padding: 0 0 1.125rem;
            font-size: 0.875rem;
            color: var(--text-3);
            line-height: 1.75;
        }

        /* ============================================================
           CTA
           ============================================================ */
        .cta-wrap { padding: 5rem 0; }
        .cta-box {
            background: var(--surface-1);
            border: 1px solid var(--border-2);
            border-radius: var(--r-lg);
            padding: 4rem 3rem;
            text-align: center;
        }
        .cta-box h2 {
            font-size: clamp(1.75rem, 3vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--text-1);
            margin-bottom: 0.75rem;
        }
        .cta-box p {
            color: var(--text-3);
            font-size: 0.9375rem;
            margin-bottom: 2rem;
        }
        .cta-actions {
            display: flex;
            gap: 0.625rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* ============================================================
           FOOTER
           ============================================================ */
        footer {
            border-top: 1px solid var(--border-1);
            padding: 3rem 0 0;
            margin-top: 2rem;
        }
        .footer-top {
            display: flex;
            align-items: flex-start;
            gap: 2rem;
            padding-bottom: 2.5rem;
        }
        .footer-about {
            font-size: 0.8125rem;
            color: var(--text-3);
            line-height: 1.75;
            max-width: 460px;
            padding-top: 0.125rem;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            padding: 2rem 0;
            border-top: 1px solid var(--border-1);
        }
        .footer-col h4 {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-3);
            margin-bottom: 1rem;
            font-family: var(--mono);
        }
        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 0.5rem; }
        .footer-col ul li, .footer-col ul a {
            font-size: 0.8125rem;
            color: var(--text-3);
            text-decoration: none;
            transition: color 120ms;
        }
        .footer-col ul a:hover { color: var(--text-1); }
        .footer-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 0;
            border-top: 1px solid var(--border-1);
            flex-wrap: wrap;
        }
        .footer-copy {
            font-size: 0.75rem;
            color: var(--text-3);
            font-family: var(--mono);
        }
        .footer-links ul {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .footer-links li + li::before {
            content: '·';
            color: var(--text-3);
            padding-right: 0.25rem;
            pointer-events: none;
            opacity: 0.4;
        }
        .footer-links a {
            font-size: 0.75rem;
            color: var(--text-3);
            text-decoration: none;
            padding: 0.25rem 0.375rem;
            border-radius: var(--r-sm);
            transition: color 120ms, background 120ms;
        }
        .footer-links a:hover { color: var(--text-1); background: var(--surface-2); }

        /* ============================================================
           DIALOG
           ============================================================ */
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .overlay.open { display: flex; }
        .dialog {
            background: var(--surface-1);
            border: 1px solid var(--border-2);
            border-radius: var(--r-lg);
            width: min(440px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            animation: dlgIn 0.15s ease;
        }
        .dialog.dialog-lg { width: min(520px, 100%); }
        @keyframes dlgIn {
            from { opacity: 0; transform: translateY(6px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .dlg-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.375rem 1.5rem;
            border-bottom: 1px solid var(--border-1);
        }
        .dlg-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-1);
            letter-spacing: -0.02em;
        }
        .dlg-sub {
            font-size: 0.8125rem;
            color: var(--text-3);
            margin-top: 0.25rem;
        }
        .dlg-close {
            width: 1.75rem; height: 1.75rem;
            border: 1px solid var(--border-2);
            border-radius: var(--r-sm);
            background: transparent;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem;
            color: var(--text-3);
            transition: background 120ms, color 120ms;
            flex-shrink: 0;
        }
        .dlg-close:hover { background: var(--surface-3); color: var(--text-1); }
        .dlg-body { padding: 1.375rem 1.5rem; }
        .dlg-foot {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            padding: 0 1.5rem 1.5rem;
        }

        /* Login role items */
        .role-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .role-item {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border-1);
            border-radius: var(--r-md);
            text-decoration: none;
            color: var(--text-1);
            background: var(--surface-2);
            transition: border-color 150ms, background 150ms;
        }
        .role-item:hover {
            border-color: var(--border-3);
            background: var(--surface-3);
        }
        .role-ico {
            width: 2.25rem; height: 2.25rem;
            border-radius: var(--r-sm);
            background: var(--surface-3);
            border: 1px solid var(--border-2);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.875rem;
            color: var(--text-2);
            flex-shrink: 0;
        }
        .role-name { font-size: 0.875rem; font-weight: 600; }
        .role-desc { font-size: 0.75rem; color: var(--text-3); }
        .role-arr { margin-left: auto; color: var(--text-3); font-size: 0.75rem; }

        /* Form */
        .form-row { margin-bottom: 1rem; }
        .form-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-2);
            margin-bottom: 0.375rem;
        }
        .form-ctrl {
            width: 100%;
            padding: 0.5625rem 0.75rem;
            font-size: 0.875rem;
            font-family: inherit;
            border: 1px solid var(--border-2);
            border-radius: var(--r-md);
            background: var(--surface-2);
            color: var(--text-1);
            transition: border-color 120ms, box-shadow 120ms;
            outline: none;
            line-height: 1.5;
        }
        .form-ctrl:focus {
            border-color: var(--border-3);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.04);
        }
        .form-ctrl::placeholder { color: var(--text-3); }
        textarea.form-ctrl { resize: vertical; min-height: 4.5rem; }
        select.form-ctrl option { background: var(--surface-2); }

        /* ============================================================
           REVEAL
           ============================================================ */
        .reveal {
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 0.45s ease, transform 0.45s ease;
        }
        .reveal.on { opacity: 1; transform: none; }

        @media (prefers-reduced-motion: reduce) {
            .reveal { transition: none; opacity: 1; transform: none; }
            .t-line { animation: none; opacity: 1; }
            .t-cursor { animation: none; }
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 900px) {
            .hero-inner, .testi-grid, .faq-cols, .footer-grid { grid-template-columns: 1fr; }
            .stats-inner { grid-template-columns: repeat(2, 1fr); }
            .stat-cell:nth-child(3)::before { display: none; }
            .steps-grid, .feature-grid { grid-template-columns: 1fr; }
            .nav-links, .header-actions { display: none; }
            .menu-toggle { display: flex; }
        }
        @media (max-width: 600px) {
            .pricing-grid { grid-template-columns: 1fr; gap: 1px; }
            .cta-box { padding: 2.5rem 1.5rem; }
            .hero { padding: 3.5rem 0 3rem; }
            section { padding: 3.5rem 0; }
            .footer-bar { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .footer-top { flex-direction: column; gap: 0.75rem; }
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
        <div class="container">
            <div class="hero-inner">
                <div>
                    <div class="hero-eyebrow">
                    <span class="badge badge-ok">
                        <i class="fas fa-circle" style="font-size:0.4rem" aria-hidden="true"></i>
                        Jaringan Aktif
                    </span>
                    </div>
                    <h1><?php echo strip_tags($heroTitle); ?></h1>
                    <p class="hero-lead"><?php echo htmlspecialchars($heroDesc); ?></p>
                    <div class="hero-actions">
                        <button type="button" class="btn btn-primary btn-lg" id="heroReg">
                            Berlangganan
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </button>
                        <a href="<?php echo htmlspecialchars($s_wa ?? '#'); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-lg">
                            <i class="fab fa-whatsapp" aria-hidden="true"></i>
                            WhatsApp
                        </a>
                    </div>
                    <div class="hero-trust">
                        <span class="trust-item"><i class="fas fa-check" aria-hidden="true"></i> Setup &lt;24 jam</span>
                        <span class="trust-item"><i class="fas fa-check" aria-hidden="true"></i> No hidden fee</span>
                        <span class="trust-item"><i class="fas fa-check" aria-hidden="true"></i> Teknisi lokal</span>
                    </div>
                </div>

                <!-- Terminal -->
                <div class="reveal">
                    <div class="terminal">
                        <div class="terminal-bar" aria-hidden="true">
                            <span class="t-dot"></span>
                            <span class="t-dot"></span>
                            <span class="t-dot"></span>
                            <span class="terminal-title">network — ping diagnostic</span>
                        </div>
                        <div class="terminal-body" id="termBody" aria-hidden="true"></div>
                    </div>
                    <p style="font-size:0.6875rem;color:var(--text-3);margin-top:0.625rem;text-align:right;font-family:var(--mono);letter-spacing:0.03em;">
                        monitored 24/7 · latensi rata-rata &lt;2ms
                    </p>
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

    <!-- FEATURES -->
    <section id="fitur" class="section-muted">
        <div class="container">
            <div class="section-head center reveal">
                <div class="section-tag">Layanan</div>
                <h2>Dirancang untuk kebutuhan nyata</h2>
                <p>Bukan sekadar angka di speedtest — infrastruktur yang stabil untuk aktivitas harian yang penting.</p>
            </div>
            <div class="feature-grid reveal">
                <div class="feature-cell">
                    <div class="f-icon"><i class="fas fa-bolt" aria-hidden="true"></i></div>
                    <h3>Kecepatan Simetris</h3>
                    <p>Upload sama cepat dengan download. Ideal untuk video call, WFA, dan upload konten.</p>
                </div>
                <div class="feature-cell">
                    <div class="f-icon"><i class="fas fa-headset" aria-hidden="true"></i></div>
                    <h3>Dukungan Responsif</h3>
                    <p>Teknisi lokal yang tahu kondisi jaringan di area Anda. Respon cepat tanpa call center.</p>
                </div>
                <div class="feature-cell">
                    <div class="f-icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></div>
                    <h3>Infrastruktur Aman</h3>
                    <p>FreeRADIUS dengan autentikasi per-pelanggan, monitoring aktif, firewall terpadu.</p>
                </div>
                <div class="feature-cell">
                    <div class="f-icon"><i class="fas fa-receipt" aria-hidden="true"></i></div>
                    <h3>Tagihan Transparan</h3>
                    <p>Notifikasi WhatsApp otomatis, invoice digital, payment gateway beragam.</p>
                </div>
                <div class="feature-cell">
                    <div class="f-icon"><i class="fas fa-gauge-high" aria-hidden="true"></i></div>
                    <h3>Manajemen Bandwidth</h3>
                    <p>QoS berbasis profil MikroTik — alokasi bandwidth dijamin per paket.</p>
                </div>
                <div class="feature-cell">
                    <div class="f-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></div>
                    <h3>Portal Pelanggan</h3>
                    <p>Cek tagihan, riwayat, dan status koneksi kapan saja lewat portal mandiri.</p>
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
                <div class="card quote-card reveal">
                    <p class="quote-text">"Sejak pakai internet ini, kerja WFA jadi jauh lebih lancar. Kalau ada gangguan, teknisinya bisa datang hari itu juga."</p>
                    <div class="quote-author">
                        <div class="author-init" aria-hidden="true">AW</div>
                        <div>
                            <div class="author-name">Andi Wijaya</div>
                            <div class="author-role">Pengusaha · 2 tahun</div>
                        </div>
                    </div>
                </div>
                <div class="card quote-card reveal">
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
                        <hr class="plan-sep sep">
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

        /* Terminal */
        var lines = [
            {c:'t-cmd',  h:'<span style="color:var(--text-3)">$</span> ping -c 5 gateway.local'},
            {c:'t-stat', h:'PING gateway.local (192.168.1.1) 56 bytes of data.'},
            {c:'t-ok',   h:'64 bytes icmp_seq=1 ttl=64 <span class="t-ms">time=1.84 ms</span>'},
            {c:'t-ok',   h:'64 bytes icmp_seq=2 ttl=64 <span class="t-ms">time=1.91 ms</span>'},
            {c:'t-ok',   h:'64 bytes icmp_seq=3 ttl=64 <span class="t-ms">time=1.77 ms</span>'},
            {c:'t-ok',   h:'64 bytes icmp_seq=4 ttl=64 <span class="t-ms">time=1.82 ms</span>'},
            {c:'t-ok',   h:'64 bytes icmp_seq=5 ttl=64 <span class="t-ms">time=1.79 ms</span>'},
            {c:'t-stat', h:''},
            {c:'t-stat', h:'5 packets transmitted, 5 received, <span class="t-ms">0% packet loss</span>'},
            {c:'t-stat', h:'rtt min/avg/max = 1.77/<span class="t-ms">1.83</span>/1.91 ms'},
            {c:'t-cmd',  h:'<span style="color:var(--text-3)">$</span> <span class="t-cursor"></span>'},
        ];
        var body = document.getElementById('termBody');
        if (body) {
            var delay = 0;
            lines.forEach(function(l, i){
                var el = document.createElement('div');
                el.className = 't-line ' + l.c;
                el.innerHTML = l.h;
                el.style.animationDelay = delay + 'ms';
                body.appendChild(el);
                delay += i < 2 ? 80 : 220;
            });
        }

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