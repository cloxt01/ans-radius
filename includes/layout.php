<?php
/**
 * Layout Template - Anthropic Style
 * Base layout for all pages dengan gaya Anthropic
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get page title
$appName = getSetting('app_name', 'ANS RADIUS');
$pageTitle = $pageTitle ?? $appName;
$pageDescription = $pageDescription ?? '';

// Phase 3: Multi-router support
$currentRouter = getMikrotikSettings();
$allRouters = getAllRouters();

// Handle global router switching via GET (optional but convenient)
if (isset($_GET['switch_router'])) {
    $swId = (int)$_GET['switch_router'];
    $_SESSION['active_router_id'] = $swId;
    $currentUrl = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: " . $currentUrl);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($appName); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/icons/favicon.ico">
    <!-- Anthropic Fonts -->
    <link rel="preconnect" href="https://assets.claude.ai">
    <style>
        /* ── Anthropic Fonts ─────────────────────────────────────────── */
        @font-face {
            font-family: "Anthropic Sans";
            src: url("https://assets.claude.ai/Fonts/AnthropicSans-Text-Regular-Static.otf") format("opentype");
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Sans";
            src: url("https://assets.claude.ai/Fonts/AnthropicSans-Text-RegularItalic-Static.otf") format("opentype");
            font-weight: 400;
            font-style: italic;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Sans";
            src: url("https://assets.claude.ai/Fonts/AnthropicSans-Text-Medium-Static.otf") format("opentype");
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Sans";
            src: url("https://assets.claude.ai/Fonts/AnthropicSans-Text-MediumItalic-Static.otf") format("opentype");
            font-weight: 500;
            font-style: italic;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Sans";
            src: url("https://assets.claude.ai/Fonts/AnthropicSans-Text-Semibold-Static.otf") format("opentype");
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Sans";
            src: url("https://assets.claude.ai/Fonts/AnthropicSans-Text-SemiboldItalic-Static.otf") format("opentype");
            font-weight: 600;
            font-style: italic;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Sans";
            src: url("https://assets.claude.ai/Fonts/AnthropicSans-Text-Bold-Static.otf") format("opentype");
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Sans";
            src: url("https://assets.claude.ai/Fonts/AnthropicSans-Text-BoldItalic-Static.otf") format("opentype");
            font-weight: 700;
            font-style: italic;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Serif";
            src: url("https://assets.claude.ai/Fonts/AnthropicSerif-Text-Regular-Static.otf") format("opentype");
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Serif";
            src: url("https://assets.claude.ai/Fonts/AnthropicSerif-Text-RegularItalic-Static.otf") format("opentype");
            font-weight: 400;
            font-style: italic;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Serif";
            src: url("https://assets.claude.ai/Fonts/AnthropicSerif-Text-Medium-Static.otf") format("opentype");
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Serif";
            src: url("https://assets.claude.ai/Fonts/AnthropicSerif-Text-MediumItalic-Static.otf") format("opentype");
            font-weight: 500;
            font-style: italic;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Serif";
            src: url("https://assets.claude.ai/Fonts/AnthropicSerif-Text-Semibold-Static.otf") format("opentype");
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Serif";
            src: url("https://assets.claude.ai/Fonts/AnthropicSerif-Text-SemiboldItalic-Static.otf") format("opentype");
            font-weight: 600;
            font-style: italic;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Serif";
            src: url("https://assets.claude.ai/Fonts/AnthropicSerif-Text-Bold-Static.otf") format("opentype");
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: "Anthropic Serif";
            src: url("https://assets.claude.ai/Fonts/AnthropicSerif-Text-BoldItalic-Static.otf") format("opentype");
            font-weight: 700;
            font-style: italic;
            font-display: swap;
        }
    </style>

    <!-- Tabler Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.42.0/dist/tabler-icons.min.css">

    <!-- Font Awesome (fallback) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Simple DataTables -->
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" type="text/css">

    <style>
        /* ============================================================
           DESIGN TOKENS — GLASS DARK THEME
           ============================================================ */
        :root {
            --bg: #050816;
            --bg2: #0f172a;
            --glass: rgba(255,255,255,.06);
            --glass-border: rgba(255,255,255,.12);
            --text: #ffffff;
            --muted: rgba(255,255,255,.65);
            --primary: #3b82f6;
            --secondary: #06b6d4;
            --radius: 10px;
            --radius-md: 16px;
            --radius-sm: 10px;
            --radius-xs: 6px;
            --font-sans: 'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-mono: 'Geist Mono', 'Fira Code', monospace;
        }

        /* ============================================================
           RESET & BASE
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-sans);
            background:
                    radial-gradient(circle at top left, rgba(59,130,246,.25), transparent 35%),
                    radial-gradient(circle at bottom right, rgba(6,182,212,.20), transparent 35%),
                    radial-gradient(circle at center, rgba(124,58,237,.15), transparent 40%),
                    #050816;
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        /* ============================================================
           SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--glass-border); border-radius: 999px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.2); }

        /* ============================================================
           LINKS
           ============================================================ */
        a { color: var(--primary); text-decoration: none; transition: color 0.2s; }
        a:hover { color: var(--text); }

        /* ============================================================
           SIDEBAR — GLASS
           ============================================================ */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: var(--glass);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-right: 1px solid var(--glass-border);
            z-index: 1000;
            transition: transform 0.25s ease;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 50px rgba(0,0,0,.3);
        }

        .sidebar-header {
            padding: 20px 16px;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .sidebar-header a {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-brand-logo {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--glass-border);
            display: block;
            transition: border-color 0.2s;
        }
        .sidebar-header a:hover .sidebar-brand-logo {
            border-color: rgba(255,255,255,.3);
        }

        .sidebar-brand-text {
            font-family: var(--font-sans);
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .sidebar-nav {
            padding: 16px 12px;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .nav-section { margin-bottom: 24px; }
        .nav-section-title {
            padding: 4px 12px 8px;
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            margin: 2px 0;
            border-radius: var(--radius-sm);
            color: var(--muted);
            transition: all 0.15s ease;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid transparent;
        }

        .menu-item:hover {
            background: rgba(255,255,255,.06);
            color: var(--text);
            border-color: var(--glass-border);
        }

        .menu-item.active {
            background: rgba(59,130,246,.12);
            color: var(--primary);
            border-color: rgba(59,130,246,.2);
        }

        .menu-item i {
            width: 20px;
            font-size: 14px;
            text-align: center;
        }

        .menu-item .submenu-arrow {
            margin-left: auto;
            font-size: 10px;
            transition: transform 0.2s ease;
        }

        .submenu {
            padding-left: 32px;
            display: none;
        }
        .submenu.open { display: block; }
        .submenu .menu-item {
            font-size: 12px;
            padding: 6px 12px;
        }

        /* ── SIDEBAR OVERLAY (mobile) ──────────────────────────────────────── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 999;
        }
        .sidebar-overlay.active { display: block; }

        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
        }

        /* ============================================================
           HEADER — GLASS
           ============================================================ */
        .header {
            position: sticky;
            top: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 24px;
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            z-index: 100;
            box-shadow: 0 4px 30px rgba(0,0,0,.2);
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            color: var(--muted);
            font-size: 20px;
            padding: 4px 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .menu-toggle:hover {
            background: rgba(255,255,255,.06);
            color: var(--text);
        }

        .header-title h1 {
            font-family: var(--font-sans);
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -0.03em;
            color: var(--text);
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Router switcher */
        .router-select {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 999px;
            padding: 4px 14px;
            color: var(--text);
            font-size: 12px;
            font-family: var(--font-sans);
            cursor: pointer;
            transition: all 0.2s;
            backdrop-filter: blur(8px);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' fill='rgba(255,255,255,.5)'%3E%3Cpath d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 28px;
        }
        .router-select:hover {
            border-color: var(--primary);
        }
        .router-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        .router-select option { background: var(--bg2); }

        /* ============================================================
           PAGE CONTENT
           ============================================================ */
        .page-content { padding: 24px; }

        /* ============================================================
           CARDS — GLASS
           ============================================================ */
        .card {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            box-shadow: 0 10px 40px rgba(0,0,0,.25), inset 0 1px 0 rgba(255,255,255,.06);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            margin-bottom: 16px;
            transition: all 0.25s ease;
            overflow: hidden;
        }
        .card:hover {
            border-color: rgba(255,255,255,.2);
            transform: translateY(-2px);
            box-shadow: 0 20px 50px rgba(0,0,0,.4);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            border-bottom: 1px solid var(--glass-border);
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-title i { color: var(--primary); }

        .card-body { padding: 20px; }

        /* ============================================================
           STATS GRID
           ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.25s;
            backdrop-filter: blur(16px);
            box-shadow: 0 10px 30px rgba(0,0,0,.2);
        }
        .stat-card:hover {
            border-color: rgba(255,255,255,.2);
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(0,0,0,.4);
        }

        .stat-info h3 {
            font-family: var(--font-sans);
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--text);
            line-height: 1.2;
        }
        .stat-info p {
            color: var(--muted);
            font-size: 12px;
            font-weight: 500;
            margin-top: 2px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: rgba(255,255,255,.06);
            border: 1px solid var(--glass-border);
            color: var(--primary);
        }
        .stat-icon.blue { color: var(--primary); }
        .stat-icon.green { color: #10b981; }
        .stat-icon.red { color: #ef4444; }
        .stat-icon.orange { color: #f59e0b; }
        .stat-icon.purple { color: #8b5cf6; }
        .stat-icon.cyan { color: var(--secondary); }

        /* ============================================================
           BUTTONS
           ============================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 16px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid var(--glass-border);
            background: var(--glass);
            color: var(--text);
            font-family: var(--font-sans);
            backdrop-filter: blur(8px);
        }
        .btn:hover {
            background: rgba(255,255,255,.08);
            border-color: rgba(255,255,255,.3);
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        .btn-primary:hover {
            background: #2563eb;
            border-color: #2563eb;
        }

        .btn-success {
            background: rgba(16,185,129,.15);
            border-color: rgba(16,185,129,.3);
            color: #10b981;
        }
        .btn-success:hover {
            background: rgba(16,185,129,.25);
        }

        .btn-danger {
            background: rgba(239,68,68,.15);
            border-color: rgba(239,68,68,.3);
            color: #ef4444;
        }
        .btn-danger:hover {
            background: rgba(239,68,68,.25);
        }

        .btn-warning {
            background: rgba(245,158,11,.15);
            border-color: rgba(245,158,11,.3);
            color: #f59e0b;
        }
        .btn-warning:hover {
            background: rgba(245,158,11,.25);
        }

        .btn-sm {
            padding: 4px 12px;
            font-size: 11px;
        }
        .btn-xs {
            padding: 2px 8px;
            font-size: 10px;
        }
        .btn-ghost {
            border-color: transparent;
        }
        .btn-ghost:hover {
            background: rgba(255,255,255,.06);
            border-color: var(--glass-border);
        }

        /* ============================================================
           FORMS
           ============================================================ */
        .form-group { margin-bottom: 16px; }
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 500;
            color: var(--muted);
        }
        .form-control {
            width: 100%;
            padding: 8px 14px;
            background: rgba(255,255,255,.06);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 14px;
            font-family: var(--font-sans);
            transition: all 0.2s;
            outline: none;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }
        .form-control::placeholder { color: var(--muted); }
        textarea.form-control { min-height: 80px; resize: vertical; }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' fill='rgba(255,255,255,.5)'%3E%3Cpath d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 32px;
            cursor: pointer;
        }
        select.form-control option { background: var(--bg2); }

        /* ============================================================
           TABLES
           ============================================================ */
        .table-wrapper { overflow-x: auto; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .data-table thead tr { border-bottom: 1px solid var(--glass-border); }
        .data-table th {
            padding: 10px 14px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(255,255,255,.02);
        }
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid rgba(255,255,255,.04);
            color: var(--text);
            vertical-align: middle;
        }
        .data-table tbody tr:hover td {
            background: rgba(255,255,255,.03);
        }
        .data-table tr:last-child td { border-bottom: none; }

        /* Compact table */
        .data-table.table-compact th,
        .data-table.table-compact td {
            padding: 6px 8px;
            font-size: 12px;
        }

        /* ============================================================
           BADGES
           ============================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 500;
            border: 1px solid transparent;
            background: var(--glass);
            backdrop-filter: blur(8px);
        }
        .badge-success {
            background: rgba(16,185,129,.15);
            color: #10b981;
            border-color: rgba(16,185,129,.2);
        }
        .badge-warning {
            background: rgba(245,158,11,.15);
            color: #f59e0b;
            border-color: rgba(245,158,11,.2);
        }
        .badge-danger {
            background: rgba(239,68,68,.15);
            color: #ef4444;
            border-color: rgba(239,68,68,.2);
        }
        .badge-info {
            background: rgba(59,130,246,.15);
            color: var(--primary);
            border-color: rgba(59,130,246,.2);
        }
        .badge-muted {
            background: rgba(255,255,255,.06);
            color: var(--muted);
            border-color: var(--glass-border);
        }

        /* ============================================================
           ALERTS
           ============================================================ */
        .alert {
            padding: 12px 18px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            border: 1px solid transparent;
            background: var(--glass);
            backdrop-filter: blur(12px);
        }
        .alert-success {
            border-color: rgba(16,185,129,.2);
            color: #10b981;
        }
        .alert-danger {
            border-color: rgba(239,68,68,.2);
            color: #ef4444;
        }
        .alert-info {
            border-color: rgba(59,130,246,.2);
            color: var(--primary);
        }
        .alert-warning {
            border-color: rgba(245,158,11,.2);
            color: #f59e0b;
        }

        /* ============================================================
           MODAL — GLASS
           ============================================================ */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal.open { display: flex; }

        .modal-content {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            box-shadow: 0 30px 60px rgba(0,0,0,.5);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--glass-border);
        }
        .modal-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
        }
        .modal-header .close {
            font-size: 24px;
            cursor: pointer;
            color: var(--muted);
            transition: color 0.2s;
            background: none;
            border: none;
            line-height: 1;
        }
        .modal-header .close:hover { color: var(--text); }

        .modal-body { padding: 20px; }
        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--glass-border);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        /* ============================================================
           LOADING OVERLAY
           ============================================================ */
        .loading-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(5,8,22,0.85);
            backdrop-filter: blur(8px);
            z-index: 99999;
        }
        .loading-overlay.active { display: flex; }

        .loading-box {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(16px);
        }

        .loading-spinner {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--glass-border);
            border-top-color: var(--primary);
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ============================================================
           CODE PILL
           ============================================================ */
        .code-pill {
            font-family: var(--font-mono);
            background: rgba(59,130,246,.1);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            color: var(--primary);
            border: 1px solid rgba(59,130,246,.15);
        }

        /* ============================================================
           UTILITIES
           ============================================================ */
        .container { max-width: 1440px; margin: 0 auto; padding: 0 16px; }
        .text-muted { color: var(--muted); }
        .text-success { color: #10b981; }
        .text-danger { color: #ef4444; }
        .text-warning { color: #f59e0b; }
        .text-info { color: var(--primary); }
        .fw-500 { font-weight: 500; }
        .fw-600 { font-weight: 600; }
        .gap-1 { gap: 4px; }
        .gap-2 { gap: 8px; }
        .flex { display: flex; align-items: center; }
        .flex-wrap { flex-wrap: wrap; }

        /* ============================================================
           ACTION BUTTONS ROW
           ============================================================ */
        .actions-row {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .action-btn {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 999px;
            padding: 10px 18px;
            color: var(--text);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-family: var(--font-sans);
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 15px rgba(0,0,0,.2);
        }
        .action-btn:hover {
            background: rgba(255,255,255,.1);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        .action-btn i { color: var(--muted); }

        /* ── CUSTOMER ACTION GROUP (compact) ───────────────────────────────── */
        .customer-action-group {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
        }

        .btn-xs {
            padding: 4px 6px;
            font-size: 0.7rem;
            border-radius: 4px;
            min-width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0;
        }
        .btn-xs i {
            font-size: 0.8rem;
            margin: 0;
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }

            .menu-toggle { display: block; }

            .stats-grid { grid-template-columns: 1fr; }
            .header-title h1 { font-size: 18px; }

            .page-content { padding: 16px; }
            .header { padding: 10px 16px; flex-wrap: wrap; gap: 8px; }

            .bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                background: var(--glass);
                backdrop-filter: blur(20px);
                border-top: 1px solid var(--glass-border);
                display: flex;
                justify-content: space-around;
                padding: 8px 0;
                z-index: 1000;
            }

            .nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
                font-size: 10px;
                color: var(--muted);
                text-decoration: none;
            }
            .nav-item.active { color: var(--primary); }
            .nav-item i { font-size: 18px; }
        }

        @media (min-width: 769px) {
            .bottom-nav { display: none !important; }
        }

        @media (max-width: 480px) {
            .header-actions { flex-wrap: wrap; gap: 6px; }
            .header-actions .badge { font-size: 10px; padding: 2px 8px; }
            .router-select { font-size: 11px; padding: 2px 10px; padding-right: 24px; }
        }
    </style>
</head>

<body>
    <?php if (isAdminLoggedIn()): ?>
        <!-- Mobile Bottom Navigation -->
        <div class="bottom-nav">
            <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="ti ti-home"></i>
                <span>Home</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/customers.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
                <i class="ti ti-users"></i>
                <span>Pelanggan</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/pay.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) === 'pay.php' || basename($_SERVER['PHP_SELF']) === 'pay_process.php') ? 'active' : ''; ?>">
                <i class="ti ti-credit-card"></i>
                <span>Bayar</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/menu.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'menu.php' ? 'active' : ''; ?>">
                <i class="ti ti-menu-2"></i>
                <span>Menu</span>
            </a>
        </div>

        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <!-- Sidebar -->
        <div class="sidebar" id="mainSidebar">
            <div class="sidebar-header">
                <a href="<?php echo APP_URL; ?>">
                    <img src="<?php echo APP_URL; ?>/assets/icons/icon.png" class="sidebar-brand-logo" alt="Icon">
                </a>
            </div>

            <div class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Overview</div>
                    <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="ti ti-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/customers.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
                        <i class="ti ti-users"></i>
                        <span>Pelanggan</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/packages.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'packages.php' ? 'active' : ''; ?>">
                        <i class="ti ti-box"></i>
                        <span>Paket Layanan</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/invoices.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'invoices.php' ? 'active' : ''; ?>">
                        <i class="ti ti-file-invoice"></i>
                        <span>Invoice</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Network</div>
                    <div class="menu-item" onclick="toggleSubmenu(this)">
                        <i class="ti ti-network"></i>
                        <span>PPPoE</span>
                        <span class="submenu-arrow ti ti-chevron-down"></span>
                    </div>
                    <div class="submenu">
                        <a href="<?php echo APP_URL; ?>/admin/mikrotik.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'mikrotik.php' ? 'active' : ''; ?>">
                            <i class="ti ti-user"></i>
                            <span>PPPoE User</span>
                        </a>
                        <a href="<?php echo APP_URL; ?>/admin/pppoe-profile.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'pppoe-profile.php' ? 'active' : ''; ?>">
                            <i class="ti ti-id"></i>
                            <span>Profiles</span>
                        </a>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="<?php echo APP_URL; ?>/admin/genieacs.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'genieacs.php' ? 'active' : ''; ?>">
                        <i class="ti ti-satellite"></i>
                        <span>GenieACS</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/trouble.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'trouble.php' ? 'active' : ''; ?>">
                        <i class="ti ti-alert-triangle"></i>
                        <span>Gangguan</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Users</div>
                    <div class="menu-item" onclick="toggleSubmenu(this)">
                        <i class="ti ti-network"></i>
                        <span>Users</span>
                        <span class="submenu-arrow ti ti-chevron-down"></span>
                    </div>
                    <div class="submenu">
                        <a href="<?php echo APP_URL; ?>/admin/technicians.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'technicians.php' ? 'active' : ''; ?>">
                            <i class="ti ti-tools"></i>
                            <span>Teknisi</span>
                        </a>
                        <a href="<?php echo APP_URL; ?>/admin/agents.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'agents.php' ? 'active' : ''; ?>">
                            <i class="ti ti-users"></i>
                            <span>Agen</span>
                        </a>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="<?php echo APP_URL; ?>/admin/settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                        <i class="ti ti-settings"></i>
                        <span>Settings</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/update.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'update.php' ? 'active' : ''; ?>">
                        <i class="ti ti-refresh"></i>
                        <span>Update</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/routers.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'routers.php' ? 'active' : ''; ?>">
                        <i class="ti ti-server"></i>
                        <span>Manajemen Router</span>
                    </a>
                </div>

                <div style="margin-top: auto; border-top: 0.5px solid var(--color-border-secondary); margin-top: 16px; padding-top: 8px;">
                    <a href="<?php echo APP_URL; ?>/admin/logout.php" class="menu-item">
                        <i class="ti ti-logout"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (isAdminLoggedIn()): ?>
            <div class="header">
                <div class="header-title">
                    <button class="menu-toggle" onclick="toggleSidebar()" style="background: none; border: none; color: var(--color-text-secondary); cursor: pointer; margin-right: 12px; font-size: 20px; padding: 4px;">
                        <i class="ti ti-menu-2"></i>
                    </button>
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                </div>
                <div class="header-actions">
                    <div class="router-switcher">
                        <?php if (count($allRouters) > 1): ?>
                            <select onchange="window.location.href='?switch_router=' + this.value" class="router-select">
                                <?php foreach ($allRouters as $r): ?>
                                    <option value="<?php echo $r['id']; ?>" <?php echo $currentRouter['id'] == $r['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($r['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    
                    <span class="badge badge-muted">
                        <i class="ti ti-user-circle"></i>
                        <?php echo htmlspecialchars(getCurrentAdmin()['username']); ?>
                    </span>
                    
                    <?php if (count($allRouters) > 0): ?>
                        <span class="badge badge-info">
                            <i class="ti ti-server"></i> <?php echo htmlspecialchars($currentRouter['name'] ?? 'Default'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="page-content">
            <!-- Flash Messages -->
            <?php if (hasFlash('success')): ?>
                <div class="alert alert-success">
                    <i class="ti ti-circle-check"></i>
                    <?php echo htmlspecialchars(getFlash('success')); ?>
                </div>
            <?php endif; ?>

            <?php if (hasFlash('error')): ?>
                <div class="alert alert-danger">
                    <i class="ti ti-alert-circle"></i>
                    <?php echo htmlspecialchars(getFlash('error')); ?>
                </div>
            <?php endif; ?>

            <?php if (hasFlash('info')): ?>
                <div class="alert alert-info">
                    <i class="ti ti-info-circle"></i>
                    <?php echo htmlspecialchars(getFlash('info')); ?>
                </div>
            <?php endif; ?>

            <?php if (hasFlash('warning')): ?>
                <div class="alert alert-warning">
                    <i class="ti ti-alert-triangle"></i>
                    <?php echo htmlspecialchars(getFlash('warning')); ?>
                </div>
            <?php endif; ?>

            <!-- Page Content -->
            <?php echo isset($content) ? $content : ''; ?>
        </div>
    </div>

    <!-- Global Loading -->
    <div id="globalFormLoading" class="loading-overlay">
        <div class="loading-box">
            <div class="loading-spinner"></div>
            <span>Memproses...</span>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/umd/simple-datatables.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function toggleSubmenu(el) {
            const submenu = el.nextElementSibling;
            const arrow = el.querySelector('.submenu-arrow');
            if (submenu) {
                submenu.classList.toggle('open');
                if (arrow) {
                    arrow.style.transform = submenu.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            }
        }

        // Global form loading
        const overlay = document.getElementById('globalFormLoading');
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form instanceof HTMLFormElement && form.dataset.noLoading !== 'true') {
                overlay.classList.add('active');
            }
        });

        // Initialize submenus
        document.querySelectorAll('.submenu').forEach(sub => {
            sub.classList.remove('open');
        });

        // Close sidebar on overlay click
        document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
            document.getElementById('mainSidebar')?.classList.remove('active');
            this.classList.remove('active');
        });

        // Auto-open submenu if active child
        document.querySelectorAll('.submenu').forEach(sub => {
            if (sub.querySelector('.menu-item.active')) {
                sub.classList.add('open');
                const parent = sub.previousElementSibling;
                const arrow = parent?.querySelector('.submenu-arrow');
                if (arrow) {
                    arrow.style.transform = 'rotate(180deg)';
                }
            }
        });
    </script>
</body>

</html>