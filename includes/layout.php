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
        /* ── Anthropic Theme Variables ──────────────────────────────────── */
        :root {
            /* Color scheme */
            --color-scheme: dark;
            
            /* Backgrounds */
            --color-background-primary: #0f0f14;
            --color-background-secondary: #14141c;
            --color-background-tertiary: #1a1a24;
            --color-background-card: rgba(26, 26, 36, 0.88);
            --color-background-hover: rgba(255, 255, 255, 0.04);
            --color-background-inverse: #e8e8f0;
            
            /* Text */
            --color-text-primary: #e8e8f0;
            --color-text-secondary: #a0a0b0;
            --color-text-tertiary: #6c6c7a;
            --color-text-inverse: #0f0f14;
            
            /* Borders */
            --color-border-primary: rgba(255, 255, 255, 0.12);
            --color-border-secondary: rgba(255, 255, 255, 0.08);
            --color-border-tertiary: rgba(255, 255, 255, 0.04);
            
            /* Accents - Anthropic palette */
            --color-accent-blue: #58a6ff;
            --color-accent-cyan: #06b6d4;
            --color-accent-green: #10b981;
            --color-accent-orange: #f59e0b;
            --color-accent-red: #ef4444;
            --color-accent-purple: #8b5cf6;
            --color-accent-pink: #ec4899;
            --color-accent-teal: #1D9E75;
            
            /* Status colors */
            --color-success: #10b981;
            --color-warning: #f59e0b;
            --color-danger: #ef4444;
            --color-info: #58a6ff;
            
            /* Fonts */
            --font-sans: "Anthropic Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --font-serif: "Anthropic Serif", Georgia, "Times New Roman", serif;
            --font-mono: ui-monospace, monospace;
            
            /* Radius */
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-full: 9999px;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.5);
            
            /* Transitions */
            --transition-fast: 0.1s ease;
            --transition-base: 0.2s ease;
            --transition-slow: 0.3s ease;
            
            /* Layout */
            --sidebar-width: 260px;
        }

        /* ── Reset ────────────────────────────────────────────────────────── */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ── Scrollbar ────────────────────────────────────────────────────── */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: var(--color-background-primary);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--color-border-secondary);
            border-radius: var(--radius-full);
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--color-text-tertiary);
        }

        /* ── Body ──────────────────────────────────────────────────────────── */
        body {
            font-family: var(--font-sans);
            background: var(--color-background-primary);
            color: var(--color-text-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
            line-height: 1.5;
            font-size: 14px;
        }

        /* ── Links ─────────────────────────────────────────────────────────── */
        a {
            color: var(--color-accent-blue);
            text-decoration: none;
            transition: color var(--transition-fast);
        }
        a:hover {
            text-decoration: none;
            color: var(--color-text-primary);
        }

        /* ── Sidebar ───────────────────────────────────────────────────────── */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--color-background-secondary);
            border-right: 0.5px solid var(--color-border-secondary);
            z-index: 1000;
            transition: transform var(--transition-base);
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .sidebar-header {
            padding: 20px 16px;
            border-bottom: 0.5px solid var(--color-border-secondary);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
        }

        .sidebar-header a {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
        }

        .sidebar-brand-logo {
            width: 48px;
            height: 48px;
            display: block;
            filter: brightness(0) invert(1);
            transition: filter var(--transition-fast);
        }

        .sidebar-header a:hover .sidebar-brand-logo {
            filter: none;
        }

        .sidebar-brand-text {
            font-family: var(--font-serif);
            font-size: 18px;
            font-weight: 500;
            color: var(--color-text-primary);
            letter-spacing: -0.02em;
        }

        .sidebar-nav {
            padding: 16px 12px;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .nav-section {
            margin-bottom: 24px;
        }

        .nav-section-title {
            padding: 4px 12px 8px;
            font-size: 11px;
            font-weight: 600;
            color: var(--color-text-tertiary);
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
            color: var(--color-text-secondary);
            transition: all var(--transition-fast);
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }

        .menu-item:hover {
            background: var(--color-background-hover);
            color: var(--color-text-primary);
        }

        .menu-item.active {
            background: rgba(88, 166, 255, 0.08);
            color: var(--color-accent-blue);
        }

        .menu-item i {
            width: 20px;
            font-size: 14px;
            text-align: center;
        }

        .menu-item .submenu-arrow {
            margin-left: auto;
            font-size: 10px;
            transition: transform var(--transition-fast);
        }

        .submenu {
            padding-left: 32px;
            display: none;
        }

        .submenu.open {
            display: block;
        }

        /* ── Main Content ──────────────────────────────────────────────────── */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        /* ── Header ────────────────────────────────────────────────────────── */
        .header {
            position: sticky;
            top: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: rgba(15, 15, 20, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 0.5px solid var(--color-border-secondary);
            z-index: 100;
        }

        .header-title h1 {
            font-family: var(--font-serif);
            font-size: 20px;
            font-weight: 500;
            letter-spacing: -0.03em;
            color: var(--color-text-primary);
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        /* ── Page Content ──────────────────────────────────────────────────── */
        .page-content {
            padding: 24px;
        }

        /* ── Cards ──────────────────────────────────────────────────────────── */
        .card {
            background: var(--color-background-card);
            border: 0.5px solid var(--color-border-secondary);
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.28);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            margin-bottom: 16px;
            transition: all var(--transition-base);
            overflow: hidden;
        }

        .card:hover {
            border-color: rgba(88, 166, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 0.5px solid var(--color-border-secondary);
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--color-text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: var(--color-accent-blue);
        }

        .card-body {
            padding: 20px;
        }

        /* ── Stats Grid ────────────────────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--color-background-card);
            border: 0.5px solid var(--color-border-secondary);
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all var(--transition-base);
        }

        .stat-card:hover {
            border-color: rgba(88, 166, 255, 0.2);
            transform: translateY(-2px);
        }

        .stat-info h3 {
            font-family: var(--font-serif);
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.03em;
            color: var(--color-text-primary);
            margin-bottom: 2px;
        }

        .stat-info p {
            color: var(--color-text-secondary);
            font-size: 12px;
            font-weight: 500;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.blue { color: var(--color-accent-blue); background: rgba(88, 166, 255, 0.1); }
        .stat-icon.green { color: var(--color-accent-green); background: rgba(16, 185, 129, 0.1); }
        .stat-icon.red { color: var(--color-accent-red); background: rgba(239, 68, 68, 0.1); }
        .stat-icon.orange { color: var(--color-accent-orange); background: rgba(245, 158, 11, 0.1); }
        .stat-icon.purple { color: var(--color-accent-purple); background: rgba(139, 92, 246, 0.1); }

        /* ── Buttons ────────────────────────────────────────────────────────── */
        .btn {
            padding: 6px 16px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 0.5px solid var(--color-border-secondary);
            background: transparent;
            color: var(--color-text-primary);
            font-family: var(--font-sans);
        }

        .btn:hover {
            background: var(--color-background-hover);
            border-color: var(--color-border-primary);
        }

        .btn-primary {
            background: var(--color-accent-blue);
            border-color: var(--color-accent-blue);
            color: var(--color-text-inverse);
        }

        .btn-primary:hover {
            background: #4a8fe0;
            border-color: #4a8fe0;
        }

        .btn-success {
            background: var(--color-accent-green);
            border-color: var(--color-accent-green);
            color: var(--color-text-inverse);
        }

        .btn-danger {
            background: var(--color-accent-red);
            border-color: var(--color-accent-red);
            color: var(--color-text-inverse);
        }

        .btn-sm {
            padding: 4px 12px;
            font-size: 11px;
        }

        .btn-ghost {
            border-color: transparent;
        }

        .btn-ghost:hover {
            background: var(--color-background-hover);
            border-color: var(--color-border-secondary);
        }

        /* ── Forms ──────────────────────────────────────────────────────────── */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 500;
            color: var(--color-text-secondary);
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: var(--color-background-primary);
            border: 0.5px solid var(--color-border-secondary);
            border-radius: var(--radius-sm);
            color: var(--color-text-primary);
            font-size: 14px;
            font-family: var(--font-sans);
            transition: all var(--transition-fast);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-accent-blue);
            box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.15);
        }

        select.form-control {
            cursor: pointer;
            background: var(--color-background-primary);
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        /* ── Tables ─────────────────────────────────────────────────────────── */
        .table-wrapper {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .data-table thead tr {
            border-bottom: 0.5px solid var(--color-border-secondary);
        }

        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: var(--color-text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(255, 255, 255, 0.02);
        }

        .data-table td {
            padding: 12px 16px;
            border-bottom: 0.5px solid var(--color-border-tertiary);
            color: var(--color-text-primary);
        }

        .data-table tbody tr:hover td {
            background: rgba(88, 166, 255, 0.03);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        /* ── Badges ─────────────────────────────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: var(--radius-full);
            font-size: 11px;
            font-weight: 500;
            border: 0.5px solid transparent;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.12);
            color: var(--color-accent-green);
            border-color: rgba(16, 185, 129, 0.2);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.12);
            color: var(--color-accent-orange);
            border-color: rgba(245, 158, 11, 0.2);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.12);
            color: var(--color-accent-red);
            border-color: rgba(239, 68, 68, 0.2);
        }

        .badge-info {
            background: rgba(88, 166, 255, 0.12);
            color: var(--color-accent-blue);
            border-color: rgba(88, 166, 255, 0.2);
        }

        .badge-muted {
            background: var(--color-background-tertiary);
            color: var(--color-text-tertiary);
            border-color: var(--color-border-secondary);
        }

        /* ── Alerts ─────────────────────────────────────────────────────────── */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            border: 0.5px solid transparent;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.2);
            color: var(--color-accent-green);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.2);
            color: var(--color-accent-red);
        }

        .alert-info {
            background: rgba(88, 166, 255, 0.08);
            border-color: rgba(88, 166, 255, 0.2);
            color: var(--color-accent-blue);
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.08);
            border-color: rgba(245, 158, 11, 0.2);
            color: var(--color-accent-orange);
        }

        /* ── Modal ──────────────────────────────────────────────────────────── */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-content {
            background: var(--color-background-card);
            border: 0.5px solid var(--color-border-secondary);
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 0.5px solid var(--color-border-secondary);
        }

        .modal-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--color-text-primary);
        }

        .modal-header .close {
            font-size: 20px;
            cursor: pointer;
            color: var(--color-text-tertiary);
            transition: color var(--transition-fast);
            background: none;
            border: none;
            line-height: 1;
        }

        .modal-header .close:hover {
            color: var(--color-text-primary);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 16px 20px;
            border-top: 0.5px solid var(--color-border-secondary);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        /* ── Loading Overlay ────────────────────────────────────────────────── */
        .loading-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 15, 20, 0.9);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 99999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-box {
            background: var(--color-background-card);
            border: 0.5px solid var(--color-border-secondary);
            border-radius: var(--radius-md);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .loading-spinner {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--color-border-secondary);
            border-top-color: var(--color-accent-blue);
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ── Code / Pill ────────────────────────────────────────────────────── */
        .code-pill {
            font-family: var(--font-mono);
            background: rgba(88, 166, 255, 0.08);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            color: var(--color-accent-blue);
        }

        /* ── Responsive ─────────────────────────────────────────────────────── */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .header-title h1 {
                font-size: 18px;
            }
            .menu-toggle {
                display: block !important;
            }
            
            .bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                background: var(--color-background-secondary);
                border-top: 0.5px solid var(--color-border-secondary);
                display: flex;
                justify-content: space-around;
                padding: 8px 0;
                z-index: 1000;
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
            }
            
            .nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
                font-size: 10px;
                color: var(--color-text-secondary);
                text-decoration: none;
            }
            
            .nav-item.active {
                color: var(--color-accent-blue);
            }
            
            .nav-item i {
                font-size: 18px;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
        }

        @media (min-width: 769px) {
            .bottom-nav,
            .menu-toggle {
                display: none !important;
            }
        }

        /* ── Utilities ──────────────────────────────────────────────────────── */
        .container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .text-muted {
            color: var(--color-text-tertiary);
        }

        .text-success {
            color: var(--color-accent-green);
        }

        .text-danger {
            color: var(--color-accent-red);
        }

        .text-warning {
            color: var(--color-accent-orange);
        }

        .text-info {
            color: var(--color-accent-blue);
        }

        .fw-500 { font-weight: 500; }
        .fw-600 { font-weight: 600; }

        /* ── Router Switcher ────────────────────────────────────────────────── */
        .router-select {
            background: var(--color-background-primary);
            border: 0.5px solid var(--color-border-secondary);
            border-radius: var(--radius-sm);
            padding: 4px 10px;
            color: var(--color-text-primary);
            font-size: 12px;
            font-family: var(--font-sans);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .router-select:hover {
            border-color: var(--color-accent-blue);
        }

        .router-select:focus {
            outline: none;
            border-color: var(--color-accent-blue);
        }

        /* ── Quick Actions ──────────────────────────────────────────────────── */
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }
        
        .quick-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 0.5px solid var(--color-border-secondary);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            text-align: center;
            color: var(--color-text-primary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all var(--transition-base);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: var(--font-sans);
        }
        
        .quick-btn i {
            color: var(--color-accent-blue);
            font-size: 14px;
            transition: color var(--transition-base);
        }
        
        .quick-btn:hover {
            background: rgba(88, 166, 255, 0.08);
            border-color: rgba(88, 166, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .quick-btn:hover i {
            color: var(--color-text-primary);
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
                        <a href="<?php echo APP_URL; ?>/admin/pppoe-active.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'pppoe-active.php' ? 'active' : ''; ?>">
                            <i class="ti ti-plug"></i>
                            <span>Active Sessions</span>
                        </a>
                        <a href="<?php echo APP_URL; ?>/admin/pppoe-profile.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'pppoe-profile.php' ? 'active' : ''; ?>">
                            <i class="ti ti-speedometer"></i>
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
                    <a href="<?php echo APP_URL; ?>/admin/technicians.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'technicians.php' ? 'active' : ''; ?>">
                        <i class="ti ti-tools"></i>
                        <span>Teknisi</span>
                    </a>
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