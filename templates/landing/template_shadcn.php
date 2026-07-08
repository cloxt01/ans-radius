<?php
/**
 * Landing Page — shadcn/ui Design System Rebuild
 * ANS Radius ISP Billing System
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
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
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
        $t = trim($t, '_');
        return $t !== '' ? $t : 'general';
    }
}
if (!function_exists('modernUltraResolveServiceType')) {
    function modernUltraResolveServiceType($key, $map) {
        if (isset($map[$key])) return modernUltraNormalizeType($map[$key]);
        $parts = explode('_', (string)$key);
        return modernUltraNormalizeType($parts[0] ?? 'general');
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
    <title><?php echo htmlspecialchars($appName); ?> — Internet Lokal</title>
    <meta name="description" content="<?php echo htmlspecialchars($appName); ?> — ISP lokal dengan koneksi stabil, harga transparan, dukungan 24/7.">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($appName); ?>">
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/icons/icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800;900&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ============================================================
           SHADCN/UI DESIGN SYSTEM — CUSTOM PROPERTIES
           ============================================================ */
        :root {
            /* shadcn/ui semantic tokens — light mode */
            --background:      0 0% 100%;
            --foreground:      222.2 84% 4.9%;
            --card:            0 0% 100%;
            --card-foreground: 222.2 84% 4.9%;
            --popover:         0 0% 100%;
            --popover-fg:      222.2 84% 4.9%;
            --primary:         221.2 83.2% 53.3%;
            --primary-fg:      210 40% 98%;
            --secondary:       210 40% 96.1%;
            --secondary-fg:    222.2 47.4% 11.2%;
            --muted:           210 40% 96.1%;
            --muted-fg:        215.4 16.3% 46.9%;
            --accent:          210 40% 96.1%;
            --accent-fg:       222.2 47.4% 11.2%;
            --destructive:     0 84.2% 60.2%;
            --destructive-fg:  210 40% 98%;
            --border:          214.3 31.8% 91.4%;
            --input:           214.3 31.8% 91.4%;
            --ring:            221.2 83.2% 53.3%;
            --radius:          0.5rem;

            /* Extended palette */
            --success:         142.1 76.2% 36.3%;
            --success-fg:      355.7 100% 97.3%;
            --warning:         38 92% 50%;
            --warning-fg:      48 96% 89%;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --background:      222.2 84% 4.9%;
                --foreground:      210 40% 98%;
                --card:            222.2 84% 4.9%;
                --card-foreground: 210 40% 98%;
                --primary:         217.2 91.2% 59.8%;
                --primary-fg:      222.2 47.4% 11.2%;
                --secondary:       217.2 32.6% 17.5%;
                --secondary-fg:    210 40% 98%;
                --muted:           217.2 32.6% 17.5%;
                --muted-fg:        215 20.2% 65.1%;
                --accent:          217.2 32.6% 17.5%;
                --accent-fg:       210 40% 98%;
                --border:          217.2 32.6% 17.5%;
                --input:           217.2 32.6% 17.5%;
            }
        }

        /* ============================================================
           RESET & BASE
           ============================================================ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; font-size: 16px; }

        body {
            font-family: 'Geist', ui-sans-serif, system-ui, -apple-system, sans-serif;
            background-color: hsl(var(--background));
            color: hsl(var(--foreground));
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        a { color: hsl(var(--primary)); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ============================================================
           LAYOUT UTILITIES
           ============================================================ */
        .container {
            width: min(1100px, 90vw);
            margin-inline: auto;
        }

        .sr-only {
            position: absolute; width: 1px; height: 1px;
            padding: 0; margin: -1px; overflow: hidden;
            clip: rect(0,0,0,0); white-space: nowrap; border: 0;
        }

        /* ============================================================
           SHADCN COMPONENTS
           ============================================================ */

        /* --- Button --- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
            font-size: 0.875rem;
            font-weight: 500;
            font-family: inherit;
            border-radius: var(--radius);
            border: 1px solid transparent;
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: all 150ms ease;
            text-decoration: none;
            line-height: 1;
            outline: none;
        }
        .btn:focus-visible {
            box-shadow: 0 0 0 3px hsl(var(--ring) / 0.5);
        }
        .btn-default {
            background: hsl(var(--primary));
            color: hsl(var(--primary-fg));
            border-color: hsl(var(--primary));
        }
        .btn-default:hover {
            background: hsl(var(--primary) / 0.9);
            text-decoration: none;
        }
        .btn-outline {
            background: transparent;
            color: hsl(var(--foreground));
            border-color: hsl(var(--border));
        }
        .btn-outline:hover {
            background: hsl(var(--accent));
            color: hsl(var(--accent-fg));
            text-decoration: none;
        }
        .btn-ghost {
            background: transparent;
            border-color: transparent;
            color: hsl(var(--foreground));
        }
        .btn-ghost:hover {
            background: hsl(var(--accent));
            text-decoration: none;
        }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }
        .btn-lg { padding: 0.75rem 1.5rem; font-size: 1rem; }
        .btn-icon {
            padding: 0.5rem;
            width: 2.25rem;
            height: 2.25rem;
        }

        /* --- Badge --- */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            border-radius: 9999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid transparent;
            line-height: 1;
        }
        .badge-secondary {
            background: hsl(var(--secondary));
            color: hsl(var(--secondary-fg));
            border-color: hsl(var(--border));
        }
        .badge-outline {
            background: transparent;
            color: hsl(var(--foreground));
            border-color: hsl(var(--border));
        }
        .badge-primary {
            background: hsl(var(--primary) / 0.1);
            color: hsl(var(--primary));
            border-color: hsl(var(--primary) / 0.25);
        }

        /* --- Card --- */
        .card {
            background: hsl(var(--card));
            color: hsl(var(--card-foreground));
            border: 1px solid hsl(var(--border));
            border-radius: calc(var(--radius) + 2px);
        }
        .card-header { padding: 1.5rem 1.5rem 0; }
        .card-content { padding: 1.5rem; }
        .card-footer {
            padding: 0 1.5rem 1.5rem;
            display: flex;
            align-items: center;
        }

        /* --- Separator --- */
        .separator {
            height: 1px;
            background: hsl(var(--border));
            border: none;
            margin: 0;
        }

        /* ============================================================
           HEADER / NAV
           ============================================================ */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: hsl(var(--background) / 0.95);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid hsl(var(--border));
        }
        .header-inner {
            width: min(1100px, 90vw);
            margin-inline: auto;
            height: 3.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            font-size: 0.9375rem;
            font-weight: 700;
            color: hsl(var(--foreground));
            text-decoration: none;
            flex-shrink: 0;
        }
        .brand:hover { text-decoration: none; }
        .brand-logo {
            width: 54px;
            height: 54px;
            border-radius: 6px;
            display: block;
        }
        .nav-primary {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex: 1;
        }
        .nav-primary a {
            font-size: 0.875rem;
            font-weight: 500;
            color: hsl(var(--muted-fg));
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius);
            text-decoration: none;
            transition: color 120ms, background 120ms;
        }
        .nav-primary a:hover {
            color: hsl(var(--foreground));
            background: hsl(var(--accent));
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
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            padding: 0.4375rem;
            cursor: pointer;
            color: hsl(var(--foreground));
            font-size: 1rem;
            align-items: center;
            justify-content: center;
        }
        .mobile-nav {
            display: none;
            border-top: 1px solid hsl(var(--border));
            background: hsl(var(--background));
            padding: 0.75rem;
        }
        .mobile-nav.open { display: flex; flex-direction: column; gap: 0.25rem; }
        .mobile-nav a {
            font-size: 0.875rem;
            font-weight: 500;
            color: hsl(var(--muted-fg));
            padding: 0.625rem 0.75rem;
            border-radius: var(--radius);
            text-decoration: none;
            display: block;
        }
        .mobile-nav a:hover { background: hsl(var(--accent)); color: hsl(var(--foreground)); }
        .mobile-nav .mobile-divider { height: 1px; background: hsl(var(--border)); margin: 0.5rem 0; }
        .mobile-nav-actions { display: flex; gap: 0.5rem; padding: 0.25rem 0; }

        /* ============================================================
           HERO
           ============================================================ */
        .hero {
            padding: 5rem 0 4rem;
        }
        .hero-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }
        .hero-eyebrow {
            margin-bottom: 1rem;
        }
        .hero h1 {
            font-size: clamp(2.25rem, 5vw, 3.75rem);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1.05;
            margin-bottom: 1.25rem;
            color: hsl(var(--foreground));
        }
        .hero-lead {
            font-size: 1.0625rem;
            color: hsl(var(--muted-fg));
            line-height: 1.7;
            margin-bottom: 2rem;
            max-width: 480px;
        }
        .hero-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .hero-trust {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        .hero-trust-item {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8125rem;
            color: hsl(var(--muted-fg));
        }
        .hero-trust-item i { color: hsl(var(--success)); font-size: 0.875rem; }

        /* Terminal card */
        .terminal-card {
            background: hsl(222.2 84% 4.9%);
            border: 1px solid hsl(217 19% 27%);
            border-radius: calc(var(--radius) + 4px);
            overflow: hidden;
            font-family: 'Geist Mono', 'Fira Code', 'Cascadia Code', monospace;
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15);
        }
        .terminal-topbar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: hsl(217 19% 10%);
            border-bottom: 1px solid hsl(217 19% 17%);
        }
        .terminal-dot {
            width: 12px; height: 12px; border-radius: 50%;
        }
        .terminal-title {
            font-size: 0.75rem;
            color: hsl(215 20% 55%);
            margin-left: auto;
            margin-right: auto;
            font-family: inherit;
        }
        .terminal-body {
            padding: 1.25rem 1rem;
            min-height: 220px;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .t-line {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            font-size: 0.8125rem;
            line-height: 1.6;
            white-space: nowrap;
            overflow: hidden;
            opacity: 0;
            animation: tLineIn 0.15s ease forwards;
        }
        .t-line.cmd { color: hsl(210 40% 90%); }
        .t-line.pong { color: hsl(142 76% 60%); }
        .t-line.stat { color: hsl(var(--warning)); }
        .t-line.label { color: hsl(215 20% 55%); font-size: 0.75rem; }
        .t-prompt { color: hsl(var(--primary)); flex-shrink: 0; }
        .t-ms { color: hsl(142 76% 60%); font-weight: 500; }
        .t-blink {
            display: inline-block;
            width: 0.5rem; height: 1rem;
            background: hsl(var(--primary));
            animation: blink 1s step-end infinite;
            margin-left: 2px;
            vertical-align: text-bottom;
            border-radius: 1px;
        }

        @keyframes tLineIn { to { opacity: 1; } }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

        /* ============================================================
           SECTION STRUCTURE
           ============================================================ */
        section { padding: 5rem 0; }

        .section-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: hsl(var(--primary));
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.75rem;
        }
        .section-label::before {
            content: '';
            display: inline-block;
            width: 1.5rem;
            height: 2px;
            background: hsl(var(--primary));
            border-radius: 1px;
            flex-shrink: 0;
        }
        .text-center {
            text-align: center;
        }
        .section-head { margin-bottom: 3rem; }
        .section-head.center { text-align: center; }
        .section-head.center .section-label { justify-content: center; }

        .section-head h2 {
            font-size: clamp(1.75rem, 3.5vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: hsl(var(--foreground));
            margin-bottom: 0.75rem;
        }
        .section-head p {
            font-size: 1rem;
            color: hsl(var(--muted-fg));
            max-width: 540px;
            line-height: 1.7;
        }
        .section-head.center p { margin-inline: auto; }

        /* Muted bg sections */
        .section-muted { background: hsl(var(--muted)); }

        /* ============================================================
           STATS STRIP
           ============================================================ */
        .stats-strip {
            padding: 2.5rem 0;
            border-top: 1px solid hsl(var(--border));
            border-bottom: 1px solid hsl(var(--border));
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
        }
        .stat-item {
            padding: 0 2rem;
            text-align: center;
            position: relative;
        }
        .stat-item + .stat-item::before {
            content: '';
            position: absolute;
            left: 0; top: 10%; bottom: 10%;
            width: 1px;
            background: hsl(var(--border));
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: hsl(var(--foreground));
            line-height: 1;
            margin-bottom: 0.375rem;
        }
        .stat-number span { color: hsl(var(--primary)); }
        .stat-desc {
            font-size: 0.8125rem;
            color: hsl(var(--muted-fg));
            font-weight: 500;
        }

        /* ============================================================
           FEATURES
           ============================================================ */
        .features-bg { background: hsl(var(--muted)); }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: hsl(var(--border));
            border: 1px solid hsl(var(--border));
            border-radius: calc(var(--radius) + 4px);
            overflow: hidden;
        }
        .feature-item {
            background: hsl(var(--card));
            padding: 2rem;
            transition: background 150ms;
        }
        .feature-item:hover { background: hsl(var(--accent)); }
        .feature-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: var(--radius);
            border: 1px solid hsl(var(--border));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            background: hsl(var(--background));
            font-size: 1rem;
            color: hsl(var(--primary));
        }
        .feature-item h3 {
            font-size: 0.9375rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: hsl(var(--foreground));
        }
        .feature-item p {
            font-size: 0.875rem;
            color: hsl(var(--muted-fg));
            line-height: 1.65;
        }

        /* ============================================================
           HOW IT WORKS — TIMELINE STYLE
           ============================================================ */
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            position: relative;
        }
        .steps-grid::before {
            content: '';
            position: absolute;
            top: 1.25rem;
            left: calc(1.25rem + 1rem);
            right: calc(1.25rem + 1rem);
            height: 1px;
            background: hsl(var(--border));
        }
        .step-item { position: relative; }
        .step-number {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 9999px;
            border: 1px solid hsl(var(--border));
            background: hsl(var(--background));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8125rem;
            font-weight: 700;
            color: hsl(var(--primary));
            margin-bottom: 1.25rem;
            position: relative;
            z-index: 1;
            font-family: 'Geist Mono', monospace;
            flex-shrink: 0;
        }
        .step-item h3 {
            font-size: 0.9375rem;
            font-weight: 600;
            color: hsl(var(--foreground));
            margin-bottom: 0.5rem;
        }
        .step-item p {
            font-size: 0.875rem;
            color: hsl(var(--muted-fg));
            line-height: 1.65;
        }

        /* ============================================================
           TESTIMONIALS
           ============================================================ */
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        .quote-card {
            padding: 1.5rem;
        }
        .quote-stars {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 1rem;
            color: hsl(var(--warning));
            font-size: 0.875rem;
        }
        .quote-text {
            font-size: 0.9375rem;
            color: hsl(var(--foreground));
            line-height: 1.7;
            margin-bottom: 1.25rem;
        }
        .quote-author {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .author-avatar {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 9999px;
            background: hsl(var(--primary) / 0.1);
            border: 1px solid hsl(var(--primary) / 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: hsl(var(--primary));
            flex-shrink: 0;
        }
        .author-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: hsl(var(--foreground));
        }
        .author-role {
            font-size: 0.8125rem;
            color: hsl(var(--muted-fg));
        }

        /* ============================================================
           PRICING
           ============================================================ */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            align-items: stretch;
        }
        .plan-card { padding: 1.75rem; transition: box-shadow 150ms; }
        .plan-card:hover { box-shadow: 0 4px 20px -4px hsl(var(--foreground) / 0.08); }
        .plan-card.featured {
            border-color: hsl(var(--primary));
            position: relative;
        }
        .plan-badge {
            position: absolute;
            top: -0.875rem;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
        }
        .plan-name {
            font-size: 1rem;
            font-weight: 600;
            color: hsl(var(--foreground));
            margin-bottom: 0.25rem;
        }
        .plan-price {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: hsl(var(--foreground));
            line-height: 1;
            margin: 1rem 0 0.25rem;
        }
        .plan-price-period {
            font-size: 0.875rem;
            font-weight: 400;
            color: hsl(var(--muted-fg));
            letter-spacing: 0;
        }
        .plan-desc {
            font-size: 0.875rem;
            color: hsl(var(--muted-fg));
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        hr.plan-sep { margin: 1.25rem 0; }
        .plan-features { list-style: none; display: flex; flex-direction: column; gap: 0.625rem; margin-bottom: 1.75rem; }
        .plan-features li {
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            font-size: 0.875rem;
            color: hsl(var(--foreground));
            line-height: 1.5;
        }
        .plan-features li.missing { color: hsl(var(--muted-fg)); }
        .plan-features li i { font-size: 0.875rem; margin-top: 0.125rem; flex-shrink: 0; }
        .plan-features li:not(.missing) i { color: hsl(var(--success)); }
        .plan-features li.missing i { color: hsl(var(--muted-fg)); }
        .plan-icon-wrap { margin: 0.75rem 0 1.25rem; }
        .plan-icon-wrap img { width: 3rem; height: auto; display: block; opacity: 0.85; }

        /* ============================================================
           FAQ
           ============================================================ */
        .faq-list { display: flex; flex-direction: column; }
        .faq-item {
            border-bottom: 1px solid hsl(var(--border));
            padding: 0;
        }
        .faq-item:first-child { border-top: 1px solid hsl(var(--border)); }
        .faq-item summary {
            padding: 1.25rem 0;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9375rem;
            color: hsl(var(--foreground));
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            user-select: none;
        }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-chevron {
            flex-shrink: 0;
            font-size: 0.875rem;
            color: hsl(var(--muted-fg));
            transition: transform 200ms;
        }
        .faq-item[open] .faq-chevron { transform: rotate(180deg); }
        .faq-item p {
            padding: 0 0 1.25rem;
            font-size: 0.9rem;
            color: hsl(var(--muted-fg));
            line-height: 1.75;
        }
        .faq-two-col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0 3rem;
        }

        /* ============================================================
           CTA BANNER
           ============================================================ */
        .cta-section { padding: 5rem 0; }
        .cta-card {
            padding: 3.5rem;
            border-radius: calc(var(--radius) + 6px);
            text-align: center;
            background: hsl(var(--foreground));
            border: none;
        }
        .cta-card h2 {
            font-size: clamp(1.75rem, 3vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: hsl(var(--background));
            margin-bottom: 0.75rem;
        }
        .cta-card p {
            color: hsl(var(--background) / 0.65);
            margin-bottom: 2rem;
            font-size: 1.0625rem;
        }
        .cta-actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        .btn-cta-primary {
            background: hsl(var(--background));
            color: hsl(var(--foreground));
            border-color: hsl(var(--background));
        }
        .btn-cta-primary:hover { background: hsl(var(--background) / 0.9); text-decoration: none; }
        .btn-cta-outline {
            background: transparent;
            color: hsl(var(--background));
            border-color: hsl(var(--background) / 0.35);
        }
        .btn-cta-outline:hover { background: hsl(var(--background) / 0.1); text-decoration: none; }

        /* ============================================================
           FOOTER
           ============================================================ */
        footer {
            padding: 3.5rem 0 0;
            border-top: 1px solid hsl(var(--border));
            margin-top: 2rem;
        }

        .footer-top {
            display: flex;
            align-items: flex-start;
            gap: 2rem;
            padding-bottom: 2rem;
        }

        .footer-about {
            font-size: 0.875rem;
            color: hsl(var(--muted-fg));
            line-height: 1.7;
            max-width: 480px;
            margin: 0;
            /* vertikal align dengan brand text */
            padding-top: 0.125rem;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            padding: 2rem 0;
        }

        .footer-col h4 {
            font-size: 0.8125rem;
            font-weight: 600;
            color: hsl(var(--foreground));
            margin-bottom: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .footer-col ul li,
        .footer-col ul a {
            font-size: 0.875rem;
            color: hsl(var(--muted-fg));
            text-decoration: none;
            transition: color 120ms;
        }

        .footer-col ul a:hover { color: hsl(var(--foreground)); }

        .footer-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 0;
            border-top: 1px solid hsl(var(--border));
            font-size: 0.8125rem;
            color: hsl(var(--muted-fg));
            flex-wrap: wrap;
        }

        .footer-links ul {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .footer-links li + li::before {
            content: '·';
            color: hsl(var(--border));
            padding-right: 0.25rem;
            pointer-events: none;
        }

        .footer-links a {
            font-size: 0.8125rem;
            color: hsl(var(--muted-fg));
            text-decoration: none;
            padding: 0.25rem 0.375rem;
            border-radius: var(--radius);
            transition: color 120ms, background 120ms;
        }

        .footer-links a:hover {
            color: hsl(var(--foreground));
            background: hsl(var(--accent));
        }

        /* ============================================================
           MODALS (shadcn Dialog style)
           ============================================================ */
        .dialog-overlay {
            position: fixed;
            inset: 0;
            background: hsl(var(--foreground) / 0.5);
            backdrop-filter: blur(4px);
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .dialog-overlay.open { display: flex; }
        .dialog-content {
            background: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: calc(var(--radius) + 4px);
            width: min(440px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            animation: dialogIn 0.15s ease;
        }
        .dialog-content.dialog-lg { width: min(560px, 100%); }
        @keyframes dialogIn {
            from { opacity: 0; transform: scale(0.97) translateY(4px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .dialog-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.5rem 1.5rem 0;
        }
        .dialog-title {
            font-size: 1rem;
            font-weight: 600;
            color: hsl(var(--foreground));
        }
        .dialog-desc {
            font-size: 0.875rem;
            color: hsl(var(--muted-fg));
            margin-top: 0.25rem;
        }
        .dialog-close {
            flex-shrink: 0;
            width: 1.75rem; height: 1.75rem;
            border-radius: var(--radius);
            border: 1px solid hsl(var(--border));
            background: transparent;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.875rem;
            color: hsl(var(--muted-fg));
            transition: background 120ms, color 120ms;
        }
        .dialog-close:hover { background: hsl(var(--accent)); color: hsl(var(--foreground)); }
        .dialog-body { padding: 1.5rem; }
        .dialog-footer {
            padding: 0 1.5rem 1.5rem;
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        /* Login role cards */
        .login-role-grid { display: flex; flex-direction: column; gap: 0.625rem; }
        .login-role-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            text-decoration: none;
            color: hsl(var(--foreground));
            background: hsl(var(--background));
            transition: border-color 150ms, background 150ms;
        }
        .login-role-item:hover {
            border-color: hsl(var(--primary));
            background: hsl(var(--primary) / 0.04);
            text-decoration: none;
        }
        .role-icon {
            width: 2.5rem; height: 2.5rem;
            border-radius: var(--radius);
            background: hsl(var(--primary) / 0.1);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            color: hsl(var(--primary));
            flex-shrink: 0;
        }
        .role-text-name { font-size: 0.9375rem; font-weight: 600; }
        .role-text-desc { font-size: 0.8125rem; color: hsl(var(--muted-fg)); }
        .role-arrow { margin-left: auto; color: hsl(var(--muted-fg)); font-size: 0.875rem; }

        /* Form */
        .form-row { margin-bottom: 1rem; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: hsl(var(--foreground));
            margin-bottom: 0.375rem;
        }
        .form-control {
            width: 100%;
            padding: 0.5625rem 0.75rem;
            font-size: 0.875rem;
            font-family: inherit;
            border: 1px solid hsl(var(--input));
            border-radius: var(--radius);
            background: hsl(var(--background));
            color: hsl(var(--foreground));
            transition: border-color 120ms, box-shadow 120ms;
            outline: none;
            line-height: 1.5;
        }
        .form-control:focus {
            border-color: hsl(var(--ring));
            box-shadow: 0 0 0 3px hsl(var(--ring) / 0.15);
        }
        .form-control::placeholder { color: hsl(var(--muted-fg)); }
        textarea.form-control { resize: vertical; min-height: 4.5rem; }

        /* ============================================================
           SCROLL REVEAL
           ============================================================ */
        .reveal {
            opacity: 0;
            transform: translateY(16px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (prefers-reduced-motion: reduce) {
            .reveal { transition: none; opacity: 1; transform: none; }
            .t-line { animation: none; opacity: 1; }
            .t-blink { animation: none; }
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 900px) {
            .hero-inner,
            .feature-grid,
            .footer-grid,
            .testimonials-grid,
            .faq-two-col {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .stat-item:nth-child(3)::before { display: none; }
            .steps-grid {
                grid-template-columns: 1fr;
            }
            .steps-grid::before { display: none; }
            .nav-primary, .header-actions { display: none; }
            .menu-toggle { display: flex; }
        }

        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .pricing-grid { grid-template-columns: 1fr; }
            .cta-card { padding: 2rem 1.5rem; }
            .hero { padding: 3rem 0 2.5rem; }
            section { padding: 3rem 0; }
            .footer-grid { grid-template-columns: 1fr; }
            .footer-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            .terminal-card { font-size: 0.8125rem; }
            .footer-bar { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<!-- ============================================================
     HEADER
     ============================================================ -->
<header class="site-header" id="site-header">
    <div class="header-inner">
        <a href="#home" class="brand">
            <img src="<?php echo APP_URL; ?>/assets/icons/icon.png" class="brand-logo" alt="Logo <?php echo htmlspecialchars($appName); ?>" width="28" height="28">
            <?php echo htmlspecialchars($appName); ?>
        </a>
        <nav class="nav-primary" aria-label="Navigasi utama">
            <a href="#fitur">Fitur</a>
            <a href="#cara-kerja">Cara Kerja</a>
            <a href="#paket">Paket</a>
            <a href="#faq">FAQ</a>
        </nav>
        <div class="header-actions">
            <button type="button" class="btn btn-ghost btn-sm" id="openLoginModal">Masuk</button>
            <button type="button" class="btn btn-default btn-sm" id="openRegisterBtn">Daftar</button>
        </div>
        <button type="button" class="menu-toggle" id="menuToggle" aria-label="Buka menu">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
    </div>
    <div class="mobile-nav" id="mobileNav" aria-hidden="true">
        <a href="#fitur">Fitur</a>
        <a href="#cara-kerja">Cara Kerja</a>
        <a href="#paket">Paket</a>
        <a href="#faq">FAQ</a>
        <div class="mobile-divider"></div>
        <div class="mobile-nav-actions">
            <button type="button" class="btn btn-outline btn-sm" style="flex:1" id="openLoginModalMobile">Masuk</button>
            <button type="button" class="btn btn-default btn-sm" style="flex:1" id="openRegisterMobile">Daftar</button>
        </div>
    </div>
</header>

<main id="home">

    <!-- ============================================================
         HERO
         ============================================================ -->
    <section class="hero">
        <div class="container">
            <div class="hero-inner">
                <div>
                    <div class="hero-eyebrow">
                        <span class="badge badge-secondary">
                            <i class="fas fa-signal" style="font-size:0.625rem" aria-hidden="true"></i>
                            Internet Lokal &bull; RT-RW Net
                        </span>
                    </div>
                    <h1><?php echo strip_tags($heroTitle); ?></h1>
                    <p class="hero-lead"><?php echo htmlspecialchars($heroDesc); ?></p>
                    <div class="hero-actions">
                        <button type="button" class="btn btn-default btn-lg" id="heroRegisterBtn">
                            Berlangganan Sekarang
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </button>
                        <a href="<?php echo htmlspecialchars($s_wa ?? '#'); ?>" target="_blank" rel="noopener" class="btn btn-outline btn-lg">
                            <i class="fab fa-whatsapp" aria-hidden="true"></i>
                            WhatsApp
                        </a>
                    </div>
                    <div class="hero-trust">
                        <span class="hero-trust-item"><i class="fas fa-check-circle" aria-hidden="true"></i> Setup &lt; 24 jam</span>
                        <span class="hero-trust-item"><i class="fas fa-check-circle" aria-hidden="true"></i> Tanpa biaya tersembunyi</span>
                        <span class="hero-trust-item"><i class="fas fa-check-circle" aria-hidden="true"></i> Teknisi lokal</span>
                    </div>
                </div>

                <!-- Terminal card — signature element -->
                <div class="reveal">
                    <div class="terminal-card" role="img" aria-label="Demonstrasi ping menunjukkan koneksi stabil dengan latensi rendah">
                        <div class="terminal-topbar" aria-hidden="true">
                            <span class="terminal-dot" style="background:#ff5f57"></span>
                            <span class="terminal-dot" style="background:#febc2e"></span>
                            <span class="terminal-dot" style="background:#28c840"></span>
                            <span class="terminal-title">network-check — bash</span>
                        </div>
                        <div class="terminal-body" id="terminalBody" aria-hidden="true"></div>
                    </div>
<!--                    <p style="font-size:0.75rem; color:hsl(var(--muted-fg)); margin-top:0.75rem; text-align:center; letter-spacing:0.01em;">-->
<!--                        Jaringan kami dimonitor real-time — latensi rendah, uptime konsisten.-->
<!--                    </p>-->
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         STATS STRIP
         ============================================================ -->
    <div class="stats-strip">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item reveal">
                    <div class="stat-number">99<span>.8%</span></div>
                    <div class="stat-desc">Uptime jaringan</div>
                </div>
                <div class="stat-item reveal">
                    <div class="stat-number">&lt;<span>24</span>j</div>
                    <div class="stat-desc">Estimasi aktivasi</div>
                </div>
                <div class="stat-item reveal">
                    <div class="stat-number">24<span>/7</span></div>
                    <div class="stat-desc">Dukungan teknis</div>
                </div>
                <div class="stat-item reveal">
                    <div class="stat-number"><span>Fiber</span></div>
                    <div class="stat-desc">Teknologi akses</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         FEATURES
         ============================================================ -->
    <section id="fitur" class="features-bg">
        <div class="container">
            <div class="section-head center reveal">
                <div class="section-label">Layanan</div>
                <h2>Dirancang untuk kebutuhan nyata</h2>
                <p>Bukan sekadar cepat di speedtest — infrastruktur yang stabil untuk aktivitas sehari-hari yang penting.</p>
            </div>
            <div class="feature-grid reveal">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-bolt" aria-hidden="true"></i></div>
                    <h3>Kecepatan Simetris</h3>
                    <p>Upload sama cepat dengan download. Ideal untuk video call, work from home, dan upload konten.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-headset" aria-hidden="true"></i></div>
                    <h3>Dukungan Responsif</h3>
                    <p>Teknisi lokal yang tahu persis kondisi jaringan di area Anda. Respon cepat tanpa call center.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></div>
                    <h3>Infrastruktur Aman</h3>
                    <p>Sistem FreeRADIUS dengan autentikasi per-pelanggan, monitoring aktif, dan firewall terpadu.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-credit-card" aria-hidden="true"></i></div>
                    <h3>Tagihan Transparan</h3>
                    <p>Notifikasi otomatis via WhatsApp, invoice digital, dan pilihan payment gateway yang beragam.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-gauge-high" aria-hidden="true"></i></div>
                    <h3>Manajemen Bandwidth</h3>
                    <p>QoS berbasis profil MikroTik — setiap paket mendapat alokasi bandwidth yang dijamin.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></div>
                    <h3>Portal Pelanggan</h3>
                    <p>Cek tagihan, riwayat pemakaian, dan status koneksi kapan saja lewat portal mandiri.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         HOW IT WORKS
         ============================================================ -->
    <section id="cara-kerja">
        <div class="container">
            <div class="section-head reveal">
                <div class="section-label">Cara berlangganan</div>
                <h2>Tiga langkah sampai online</h2>
                <p>Proses singkat — tidak perlu datang ke kantor, cukup daftar online dan tim kami yang datang.</p>
            </div>
            <div class="steps-grid">
                <div class="step-item reveal">
                    <div class="step-number">01</div>
                    <h3>Daftar & Pilih Paket</h3>
                    <p>Isi formulir pendaftaran, pilih paket yang sesuai kebutuhan. Data langsung masuk ke sistem kami.</p>
                </div>
                <div class="step-item reveal">
                    <div class="step-number">02</div>
                    <h3>Verifikasi & Survey</h3>
                    <p>Tim kami menghubungi via WhatsApp untuk konfirmasi data dan survey kondisi lokasi pemasangan.</p>
                </div>
                <div class="step-item reveal">
                    <div class="step-number">03</div>
                    <h3>Instalasi & Aktif</h3>
                    <p>Teknisi datang ke lokasi, instalasi rampung, akun aktif, dan Anda langsung bisa online.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         TESTIMONIALS
         ============================================================ -->
    <section class="features-bg">
        <div class="container">
            <div class="section-head center reveal">
                <div class="section-label">Pelanggan</div>
                <h2>Kata mereka yang sudah pakai</h2>
            </div>
            <div class="testimonials-grid">
                <div class="card quote-card reveal">
                    <div class="quote-stars">
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <i class="fas fa-star" aria-hidden="true"></i>
                    </div>
                    <p class="quote-text">"Sejak pakai internet ini, kerja WFA jadi jauh lebih lancar. Kalau ada gangguan, teknisinya bisa datang hari itu juga."</p>
                    <div class="quote-author">
                        <div class="author-avatar" aria-hidden="true">AW</div>
                        <div>
                            <div class="author-name">Andi Wijaya</div>
                            <div class="author-role">Pengusaha, pengguna 2 tahun</div>
                        </div>
                    </div>
                </div>
                <div class="card quote-card reveal">
                    <div class="quote-stars">
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <i class="fas fa-star" aria-hidden="true"></i>
                    </div>
                    <p class="quote-text">"Harga bersaing, jaringan stabil, notif tagihan WhatsApp sangat membantu. Tidak perlu khawatir tiba-tiba putus karena lupa bayar."</p>
                    <div class="quote-author">
                        <div class="author-avatar" aria-hidden="true">SN</div>
                        <div>
                            <div class="author-name">Siti Nurhaliza</div>
                            <div class="author-role">Freelancer, pengguna 1 tahun</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         PRICING
         ============================================================ -->
    <section id="paket">
        <div class="container">
            <div class="section-head center reveal">
                <div class="section-label">Paket</div>
                <h2>Harga transparan, pilih sesuai kebutuhan</h2>
                <p>Tidak ada biaya setup tersembunyi. Semua paket sudah termasuk instalasi dan perangkat dasar.</p>
            </div>
            <div class="pricing-grid">
                <?php foreach ($packages as $idx => $pkg):
                    $isFeatured = $idx === 1;
                    $visibleMap = modernUltraBuildVisibleServiceMap($pkg, $packageFeatureList, $packageFeatureTypes);
                    ?>
                    <div class="card plan-card reveal <?php echo $isFeatured ? 'featured' : ''; ?>" style="position:relative;">
                        <?php if ($isFeatured): ?>
                            <div class="plan-badge">
                                <span class="badge badge-primary">Paling Populer</span>
                            </div>
                        <?php endif; ?>

                        <div class="plan-name"><?php echo htmlspecialchars($pkg['name']); ?></div>

                        <div class="plan-icon-wrap">
                            <img src="<?php echo APP_URL; ?>/assets/svg/cloud.svg"
                                 alt=""
                                 width="48"
                                 loading="lazy"
                                 decoding="async"
                                 aria-hidden="true">
                        </div>

                        <div class="plan-price">
                            <?php echo formatCurrency($pkg['price']); ?>
                            <span class="plan-price-period">/bulan</span>
                        </div>
                        <p class="plan-desc"><?php echo htmlspecialchars($pkg['description'] ?? 'Koneksi internet stabil untuk kebutuhan rumah dan bisnis.'); ?></p>

                        <hr class="plan-sep separator">

                        <?php if (!empty($packageFeatureList)): ?>
                            <ul class="plan-features">
                                <?php foreach ($packageFeatureList as $key => $name):
                                    if (empty($visibleMap[$key])) continue;
                                    $included = modernUltraServiceActive($pkg, $key);
                                    ?>
                                    <li class="<?php echo $included ? '' : 'missing'; ?>">
                                        <i class="fas <?php echo $included ? 'fa-circle-check' : 'fa-circle-xmark'; ?>" aria-hidden="true"></i>
                                        <?php echo htmlspecialchars($name); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <ul class="plan-features">
                                <li><i class="fas fa-circle-check" aria-hidden="true"></i> Koneksi fiber optic stabil</li>
                                <li><i class="fas fa-circle-check" aria-hidden="true"></i> Dukungan teknis responsif</li>
                                <li><i class="fas fa-circle-check" aria-hidden="true"></i> Monitoring jaringan berkala</li>
                                <li><i class="fas fa-circle-check" aria-hidden="true"></i> Notifikasi tagihan WhatsApp</li>
                                <li><i class="fas fa-circle-check" aria-hidden="true"></i> Portal pelanggan mandiri</li>
                            </ul>
                        <?php endif; ?>

                        <button type="button"
                                class="btn <?php echo $isFeatured ? 'btn-default' : 'btn-outline'; ?>"
                                style="width:100%"
                                onclick="openRegisterWithPackage('<?php echo addslashes(htmlspecialchars($pkg['name'])); ?>')">
                            Pilih Paket
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
         FAQ
         ============================================================ -->
    <?php if (!empty($faqs) && is_array($faqs)): ?>
        <section id="faq" class="features-bg">
            <div class="container">
                <div class="section-head reveal">
                    <div class="section-label">FAQ</div>
                    <h2>Pertanyaan yang sering ditanya</h2>
                </div>
                <div class="faq-two-col">
                    <?php
                    $mid = (int) ceil(count($faqs) / 2);
                    $cols = [array_slice($faqs, 0, $mid), array_slice($faqs, $mid)];
                    foreach ($cols as $col): ?>
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

    <!-- ============================================================
         CTA
         ============================================================ -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-card reveal">
                <h2>Siap beralih ke internet yang lebih stabil?</h2>
                <p>Daftar sekarang — tim kami bantu dari survey hingga aktivasi, gratis.</p>
                <div class="cta-actions">
                    <button type="button" class="btn btn-lg btn-cta-primary" id="ctaRegisterBtn">
                        Daftar Berlangganan
                    </button>
                    <a href="<?php echo htmlspecialchars($s_wa ?? '#'); ?>" target="_blank" rel="noopener" class="btn btn-lg btn-cta-outline">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i>
                        Tanya Dulu via WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </section>

</main>

<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer>
    <div class="container">

        <!-- Baris atas: brand + about -->
        <div class="footer-top">
            <a href="#" class="brand">
                <img src="<?php echo APP_URL; ?>/assets/icons/icon.png" class="brand-logo" alt="Logo" width="28" height="28">
            </a>
            <p class="footer-about"><?php echo htmlspecialchars($footerAbout ?? 'ISP lokal dengan fokus pada stabilitas jaringan dan kepuasan pelanggan.'); ?></p>
        </div>

        <hr class="separator">

        <!-- Baris tengah: kolom-kolom info -->
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
                <h4>Layanan</h4>
                <ul>
                    <li><a href="#fitur">Fitur</a></li>
                    <li><a href="#paket">Paket Internet</a></li>
                    <li><a href="#cara-kerja">Cara Berlangganan</a></li>
                    <li><a href="#faq">FAQ</a></li>
                </ul>
            </div>
        </div>

        <!-- Baris bawah: copyright + policy links -->
        <div class="footer-bar">
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($appName); ?>. Hak cipta dilindungi.</span>
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

<!-- ============================================================
     DIALOG: Login Role
     ============================================================ -->
<div class="dialog-overlay" id="loginDialog" role="dialog" aria-modal="true" aria-labelledby="loginDialogTitle">
    <div class="dialog-content">
        <div class="dialog-header">
            <div>
                <div class="dialog-title" id="loginDialogTitle">Pilih portal masuk</div>
                <div class="dialog-desc">Pilih sesuai peran Anda.</div>
            </div>
            <button class="dialog-close" id="closeLoginDialog" aria-label="Tutup"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="dialog-body">
            <div class="login-role-grid">
                <a class="login-role-item" href="portal/login.php">
                    <div class="role-icon"><i class="fas fa-user" aria-hidden="true"></i></div>
                    <div>
                        <div class="role-text-name">Portal Pelanggan</div>
                        <div class="role-text-desc">Cek tagihan, riwayat, dan status koneksi</div>
                    </div>
                    <i class="fas fa-arrow-right role-arrow" aria-hidden="true"></i>
                </a>
                <a class="login-role-item" href="technician/login.php">
                    <div class="role-icon"><i class="fas fa-screwdriver-wrench" aria-hidden="true"></i></div>
                    <div>
                        <div class="role-text-name">Portal Teknisi</div>
                        <div class="role-text-desc">Manajemen tiket dan jadwal kunjungan</div>
                    </div>
                    <i class="fas fa-arrow-right role-arrow" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     DIALOG: Register
     ============================================================ -->
<div class="dialog-overlay" id="registerDialog" role="dialog" aria-modal="true" aria-labelledby="registerDialogTitle">
    <div class="dialog-content dialog-lg">
        <div class="dialog-header">
            <div>
                <div class="dialog-title" id="registerDialogTitle">Daftar berlangganan</div>
                <div class="dialog-desc">Isi data berikut, tim kami akan menghubungi Anda segera.</div>
            </div>
            <button class="dialog-close" id="closeRegisterDialog" aria-label="Tutup"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="dialog-body">
            <form id="registerForm" novalidate>
                <div class="form-row">
                    <label class="form-label" for="reg-name">Nama lengkap</label>
                    <input type="text" id="reg-name" name="name" class="form-control" placeholder="Nama sesuai KTP" required autocomplete="name">
                </div>
                <div class="form-row">
                    <label class="form-label" for="reg-phone">Nomor WhatsApp</label>
                    <input type="tel" id="reg-phone" name="phone" class="form-control" placeholder="08xx xxxx xxxx" required autocomplete="tel">
                </div>
                <div class="form-row">
                    <label class="form-label" for="reg-address">Alamat pemasangan</label>
                    <textarea id="reg-address" name="address" class="form-control" rows="2" placeholder="Nama jalan, RT/RW, patokan lokasi" required></textarea>
                </div>
                <div class="form-row">
                    <label class="form-label" for="reg-package">Paket yang diminati</label>
                    <select id="reg-package" name="package" class="form-control">
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
                    <label class="form-label" for="reg-notes">Catatan tambahan</label>
                    <input type="text" id="reg-notes" name="notes" class="form-control" placeholder="Jam yang bisa dihubungi, dll. (opsional)">
                </div>
            </form>
        </div>
        <div class="dialog-footer">
            <button type="button" class="btn btn-outline" id="cancelRegister">Batal</button>
            <button type="button" class="btn btn-default" id="submitRegister">
                Kirim Pendaftaran
            </button>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';

        /* ── Terminal animation ── */
        var lines = [
            { cls: 'cmd',  content: '<span class="t-prompt">$</span> ping -c 6 gateway.local' },
            { cls: 'label', content: 'PING gateway.local (192.168.1.1) 56 bytes of data.' },
            { cls: 'pong',  content: '64 bytes from 192.168.1.1: icmp_seq=1 ttl=64 <span class="t-ms">time=1.84 ms</span>' },
            { cls: 'pong',  content: '64 bytes from 192.168.1.1: icmp_seq=2 ttl=64 <span class="t-ms">time=1.91 ms</span>' },
            { cls: 'pong',  content: '64 bytes from 192.168.1.1: icmp_seq=3 ttl=64 <span class="t-ms">time=1.77 ms</span>' },
            { cls: 'pong',  content: '64 bytes from 192.168.1.1: icmp_seq=4 ttl=64 <span class="t-ms">time=1.82 ms</span>' },
            { cls: 'pong',  content: '64 bytes from 192.168.1.1: icmp_seq=5 ttl=64 <span class="t-ms">time=1.89 ms</span>' },
            { cls: 'pong',  content: '64 bytes from 192.168.1.1: icmp_seq=6 ttl=64 <span class="t-ms">time=1.79 ms</span>' },
            { cls: 'label', content: '' },
            { cls: 'stat',  content: '--- gateway.local ping statistics ---' },
            { cls: 'pong',  content: '6 packets transmitted, 6 received, <span class="t-ms">0% packet loss</span>' },
            { cls: 'pong',  content: 'rtt min/avg/max = 1.77/<span class="t-ms">1.84</span>/1.91 ms' },
            { cls: 'cmd',   content: '<span class="t-prompt">$</span> <span class="t-blink"></span>' },
        ];

        function renderTerminal() {
            var body = document.getElementById('terminalBody');
            if (!body) return;
            body.innerHTML = '';
            var delay = 0;
            lines.forEach(function (l, i) {
                var el = document.createElement('div');
                el.className = 't-line ' + l.cls;
                el.innerHTML = l.content;
                el.style.animationDelay = delay + 'ms';
                body.appendChild(el);
                delay += (i < 2) ? 60 : 200;
            });
        }
        renderTerminal();

        /* ── Scroll reveal ── */
        var revealEls = document.querySelectorAll('.reveal');
        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    obs.unobserve(e.target);
                }
            });
        }, { threshold: 0.08 });
        revealEls.forEach(function (el) { obs.observe(el); });

        /* ── Mobile nav ── */
        var menuToggle = document.getElementById('menuToggle');
        var mobileNav  = document.getElementById('mobileNav');
        if (menuToggle && mobileNav) {
            menuToggle.addEventListener('click', function () {
                var open = mobileNav.classList.toggle('open');
                mobileNav.setAttribute('aria-hidden', !open);
                menuToggle.querySelector('i').className = open ? 'fas fa-xmark' : 'fas fa-bars';
            });
            mobileNav.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', function () {
                    mobileNav.classList.remove('open');
                    mobileNav.setAttribute('aria-hidden', 'true');
                    menuToggle.querySelector('i').className = 'fas fa-bars';
                });
            });
        }

        /* ── Dialog helpers ── */
        function openDialog(id) {
            var el = document.getElementById(id);
            if (el) el.classList.add('open');
        }
        function closeDialog(id) {
            var el = document.getElementById(id);
            if (el) el.classList.remove('open');
        }
        document.querySelectorAll('.dialog-overlay').forEach(function (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) overlay.classList.remove('open');
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDialog('loginDialog');
                closeDialog('registerDialog');
            }
        });

        /* ── Login dialog ── */
        ['openLoginModal', 'openLoginModalMobile'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('click', function () { openDialog('loginDialog'); });
        });
        var closeLogin = document.getElementById('closeLoginDialog');
        if (closeLogin) closeLogin.addEventListener('click', function () { closeDialog('loginDialog'); });

        /* ── Register dialog ── */
        function openRegister() { openDialog('registerDialog'); }
        window.openRegisterWithPackage = function (pkgName) {
            var sel = document.getElementById('reg-package');
            if (sel && pkgName) {
                for (var i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === pkgName) { sel.selectedIndex = i; break; }
                }
            }
            openRegister();
        };

        ['openRegisterBtn', 'openRegisterMobile', 'heroRegisterBtn', 'ctaRegisterBtn'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('click', openRegister);
        });
        ['closeRegisterDialog', 'cancelRegister'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('click', function () { closeDialog('registerDialog'); });
        });

        /* ── Form submit ── */
        var submitBtn = document.getElementById('submitRegister');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                var form = document.getElementById('registerForm');
                if (!form) return;
                var required = form.querySelectorAll('[required]');
                var valid = true;
                required.forEach(function (f) {
                    if (!f.value.trim()) {
                        f.style.borderColor = 'hsl(0 84.2% 60.2%)';
                        valid = false;
                    } else {
                        f.style.borderColor = '';
                    }
                });
                if (!valid) return;

                submitBtn.disabled = true;
                submitBtn.textContent = 'Mengirim...';

                fetch('<?php echo APP_URL; ?>/register-handler.php', {
                    method: 'POST',
                    body: new FormData(form),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.success) {
                            closeDialog('registerDialog');
                            form.reset();
                            alert('Pendaftaran berhasil dikirim! Tim kami akan menghubungi Anda segera via WhatsApp.');
                        } else {
                            alert('Gagal: ' + (d.message || 'Terjadi kesalahan, coba lagi.'));
                        }
                    })
                    .catch(function () {
                        alert('Gagal terhubung ke server. Silakan coba lagi.');
                    })
                    .finally(function () {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Kirim Pendaftaran';
                    });
            });
        }

        /* ── Register trigger from mobile nav ── */
        var openRegMobile = document.getElementById('openRegisterMobile');
        if (openRegMobile) openRegMobile.addEventListener('click', function () {
            if (mobileNav) mobileNav.classList.remove('open');
            openRegister();
        });

    })();
</script>
</body>
</html>