<?php
/**
 * Layout utama untuk halaman statis (privacy, terms, about, dll.)
 * Tema: Light / Clean Professional
 *
 * Gunakan dengan menyertakan file ini setelah mendefinisikan variabel:
 *   $pageTitle       - Judul halaman (untuk <title>)
 *   $pageDescription - Deskripsi halaman (untuk <meta description>)
 *   $mainContent     - Konten utama yang akan ditempatkan di dalam <main>
 *
 * Contoh penggunaan di halaman about.php:
 *   <?php
 *   require_once __DIR__ . '/includes/config.php';
 *   $pageTitle = 'Tentang Kami';
 *   $pageDescription = '...';
 *   ob_start();
 *   ?>
 *   <!-- konten spesifik halaman -->
 *   <section>...</section>
 *   <?php
 *   $mainContent = ob_get_clean();
 *   require __DIR__ . '/layout-page.php';
 *   ?>
 */

$pageTitle = $pageTitle ?? 'Halaman';
$pageDescription = $pageDescription ?? '';
$mainContent = $mainContent ?? '<p>Konten tidak tersedia.</p>';

if (!defined('APP_NAME')) {
    define('APP_NAME', 'ANS Radius');
}
if (!defined('APP_URL')) {
    define('APP_URL', '');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#ffffff">
  <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars(APP_NAME); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">

  <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
  <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/icons/icon.png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    /* ==================== LIGHT THEME ==================== */
    :root {
      --bg-canvas: #f6f8fa;
      --bg-inset: #ffffff;
      --bg-primary: #ffffff;
      --bg-secondary: #f6f8fa;
      --bg-tertiary: #f0f2f5;
      --border-default: #d0d7de;
      --border-muted: #e1e4e8;
      --fg-default: #24292f;
      --fg-muted: #57606a;
      --fg-subtle: #6e7781;
      --fg-on-emphasis: #ffffff;
      --accent-blue: #0969da;
      --accent-blue-hover: #0550ae;
      --accent-green: #1a7f37;
      --accent-red: #cf222e;
      --accent-orange: #9a6700;
      --shadow-small: 0 1px 3px rgba(0,0,0,0.06);
      --shadow-medium: 0 4px 12px rgba(0,0,0,0.08);
      --shadow-large: 0 8px 24px rgba(0,0,0,0.10);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', sans-serif;
      background-color: var(--bg-canvas);
      color: var(--fg-default);
      line-height: 1.5;
      scroll-behavior: smooth;
    }

    h1, h2, h3, h4 { font-weight: 600; letter-spacing: -0.01em; }
    a { color: var(--accent-blue); text-decoration: none; }
    a:hover { text-decoration: underline; }

    .container {
      width: min(1180px, calc(100% - 32px));
      margin: 0 auto;
    }

    /* ==================== GLASS ==================== */
    .glass {
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid var(--border-default);
      border-radius: 16px;
      box-shadow: var(--shadow-small);
    }

    /* ==================== CHIPS ==================== */
    .chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border-radius: 999px;
      padding: 8px 14px;
      font-weight: 600;
      font-size: 0.75rem;
      transition: all 0.2s ease;
    }
    .chip-default {
      background: rgba(9, 105, 218, 0.08);
      color: var(--accent-blue);
      border: 1px solid rgba(9, 105, 218, 0.2);
    }
    .chip-muted {
      background: var(--bg-tertiary);
      color: var(--fg-muted);
      border: 1px solid var(--border-muted);
    }

    /* ==================== BUTTONS ==================== */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: none;
      border-radius: 8px;
      padding: 10px 18px;
      font-weight: 600;
      font-size: 0.85rem;
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
    }
    .btn-primary {
      background: var(--accent-blue);
      color: #fff;
      box-shadow: 0 4px 12px rgba(9, 105, 218, 0.2);
    }
    .btn-primary:hover {
      background: var(--accent-blue-hover);
      transform: translateY(-1px);
      text-decoration: none;
    }
    .btn-secondary {
      border: 1px solid var(--border-default);
      color: var(--fg-default);
      background: var(--bg-primary);
    }
    .btn-secondary:hover {
      background: var(--bg-tertiary);
      border-color: var(--fg-muted);
      text-decoration: none;
    }

    /* ==================== NAVBAR ==================== */
    .navbar {
      position: sticky;
      top: 14px;
      z-index: 1000;
      margin-bottom: 30px;
    }
    .navbar-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      padding: 14px 20px;
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid var(--border-default);
      border-radius: 20px;
      backdrop-filter: blur(12px);
      transition: background 0.3s ease;
      box-shadow: var(--shadow-small);
    }
    .brand {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      font-weight: 700;
      color: var(--fg-default);
      letter-spacing: -0.02em;
    }
    .brand img {
      width: 36px;
      height: 36px;
      border-radius: 8px;
    }
    .nav-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .nav-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border-radius: 999px;
      padding: 8px 14px;
      font-weight: 500;
      font-size: 0.85rem;
      background: rgba(9, 105, 218, 0.06);
      color: var(--fg-default);
      border: 1px solid var(--border-muted);
      transition: all 0.2s;
    }
    .nav-chip:hover {
      background: var(--bg-tertiary);
      border-color: var(--accent-blue);
      text-decoration: none;
      transform: translateY(-1px);
    }
    .nav-chip.active {
      background: rgba(9, 105, 218, 0.1);
      border-color: var(--accent-blue);
      color: var(--accent-blue);
    }
    .nav-chip.primary {
      background: var(--accent-blue);
      color: #fff;
      border: none;
    }
    .nav-chip.primary:hover {
      background: var(--accent-blue-hover);
    }

    /* ==================== HERO ==================== */
    .hero { margin-bottom: 28px; }
    .hero-grid {
      display: grid;
      grid-template-columns: 1.15fr 0.85fr;
      gap: 24px;
      align-items: stretch;
    }
    .hero-panel {
      position: relative;
      overflow: hidden;
      border-radius: 24px;
      padding: 36px;
      background: var(--bg-primary);
      border: 1px solid var(--border-default);
      box-shadow: var(--shadow-small);
    }
    .hero-panel::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(9, 105, 218, 0.04), rgba(255, 255, 255, 0));
      pointer-events: none;
    }
    .hero-copy { position: relative; z-index: 1; }
    .hero-chip { margin-bottom: 20px; }
    h1 {
      font-size: clamp(2rem, 4.5vw, 3.5rem);
      line-height: 1.1;
      letter-spacing: -0.03em;
      margin-bottom: 18px;
      color: var(--fg-default);
    }
    .lead {
      font-size: 1rem;
      color: var(--fg-muted);
      max-width: 60ch;
      margin-bottom: 28px;
    }
    .hero-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    /* ==================== ASIDE / SIDEBAR ==================== */
    .hero-aside { display: grid; gap: 14px; }
    .aside-card {
      border-radius: 24px;
      padding: 28px;
      background: var(--bg-primary);
      border: 1px solid var(--border-default);
      box-shadow: var(--shadow-small);
    }
    .aside-card h2 {
      font-size: 1.35rem;
      margin-bottom: 12px;
    }
    .aside-card p { color: var(--fg-muted); }

    /* ==================== STATS GRID ==================== */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-top: 20px;
    }
    .stat-item {
      padding: 14px 12px;
      border-radius: 14px;
      background: var(--bg-secondary);
      border: 1px solid var(--border-muted);
      text-align: center;
    }
    .stat-item .value {
      font-weight: 700;
      font-size: 1.2rem;
      color: var(--fg-default);
      display: block;
    }
    .stat-item .label {
      font-size: 0.8rem;
      color: var(--fg-muted);
    }

    /* ==================== NOTE ==================== */
    .note {
      margin-top: 16px;
      padding: 14px 16px;
      border-radius: 14px;
      background: rgba(9, 105, 218, 0.04);
      border: 1px solid rgba(9, 105, 218, 0.12);
      font-size: 0.85rem;
      color: var(--fg-muted);
    }

    /* ==================== SECTION ==================== */
    .section {
      margin-top: 28px;
      padding: 32px;
      border-radius: 24px;
      background: var(--bg-primary);
      border: 1px solid var(--border-default);
      box-shadow: var(--shadow-small);
    }
    .section-header { margin-bottom: 28px; }
    .section-badge { margin-bottom: 16px; }
    .section-title {
      font-size: 1.6rem;
      letter-spacing: -0.02em;
      margin-bottom: 12px;
    }
    .section-desc {
      color: var(--fg-muted);
      max-width: 70ch;
    }

    /* ==================== GRID ==================== */
    .grid {
      display: grid;
      gap: 20px;
    }
    .grid-2cols { grid-template-columns: repeat(2, 1fr); }
    .grid-3cols { grid-template-columns: repeat(3, 1fr); }
    .grid-4cols { grid-template-columns: repeat(4, 1fr); }

    /* ==================== CARDS ==================== */
    .card {
      padding: 24px;
      border-radius: 20px;
      background: var(--bg-secondary);
      border: 1px solid var(--border-muted);
      transition: all 0.2s;
    }
    .card:hover {
      border-color: var(--accent-blue);
      transform: translateY(-2px);
      box-shadow: var(--shadow-medium);
    }
    .card i {
      width: 44px;
      height: 44px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 12px;
      margin-bottom: 16px;
      color: #fff;
      background: linear-gradient(135deg, var(--accent-blue), #0550ae);
      font-size: 1.2rem;
    }
    .card h3 {
      font-size: 1rem;
      margin-bottom: 8px;
    }
    .card p {
      color: var(--fg-muted);
      font-size: 0.85rem;
      line-height: 1.5;
    }

    /* ==================== LIST ITEMS ==================== */
    .list-items {
      display: grid;
      gap: 12px;
      margin-top: 24px;
    }
    .list-item {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      padding: 14px 16px;
      border-radius: 14px;
      background: var(--bg-secondary);
      border: 1px solid var(--border-muted);
    }
    .list-item i {
      color: var(--accent-green);
      margin-top: 2px;
      font-size: 0.9rem;
    }
    .list-item strong {
      display: block;
      color: var(--fg-default);
      margin-bottom: 4px;
      font-size: 0.85rem;
    }
    .list-item span {
      color: var(--fg-muted);
      font-size: 0.8rem;
    }

    /* ==================== CALLOUT ==================== */
    .callout {
      margin-top: 28px;
      padding: 24px 28px;
      border-radius: 20px;
      background: linear-gradient(135deg, rgba(9, 105, 218, 0.06), var(--bg-primary));
      border: 1px solid rgba(9, 105, 218, 0.15);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      flex-wrap: wrap;
    }
    .callout strong {
      display: block;
      font-size: 1rem;
      margin-bottom: 6px;
    }
    .callout p {
      color: var(--fg-muted);
      font-size: 0.85rem;
      margin: 0;
    }
    .callout .pill {
      background: var(--bg-primary);
      padding: 10px 18px;
      border-radius: 999px;
      color: var(--fg-default);
      font-size: 0.85rem;
      font-weight: 500;
      border: 1px solid var(--border-default);
      transition: all 0.2s;
    }
    .callout .pill:hover {
      background: var(--accent-blue);
      color: #fff;
      text-decoration: none;
      border-color: var(--accent-blue);
    }

    /* ==================== COMPANY PROFILE (opsional) ==================== */
    .company-profile {
      margin-top: 28px;
      padding: 28px;
      border-radius: 24px;
      background: var(--bg-primary);
      border: 1px solid var(--border-default);
      box-shadow: var(--shadow-small);
    }
    .company-profile h2 {
      font-size: 1.4rem;
      letter-spacing: -0.02em;
      margin-bottom: 12px;
    }
    .company-profile p {
      color: var(--fg-muted);
      max-width: 72ch;
    }
    .company-profile .address {
      margin-top: 16px;
      padding: 16px 20px;
      background: var(--bg-secondary);
      border-radius: 16px;
      border-left: 4px solid var(--accent-blue);
      color: var(--fg-muted);
    }
    .company-profile .address i {
      color: var(--accent-blue);
      margin-right: 8px;
    }

    /* ==================== FOOTER ==================== */
    footer {
      padding: 40px 0 24px;
      margin-top: 40px;
      border-top: 1px solid var(--border-default);
      text-align: center;
      color: var(--fg-muted);
      font-size: 0.8rem;
    }

    /* ==================== REVEAL ANIMATION ==================== */
    .reveal {
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.5s ease, transform 0.5s ease;
    }
    .reveal.show {
      opacity: 1;
      transform: translateY(0);
    }

    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 960px) {
      .hero-grid,
      .grid-2cols,
      .grid-3cols,
      .grid-4cols {
        grid-template-columns: 1fr;
      }
      .hero-panel,
      .section,
      .company-profile {
        padding: 24px;
      }
      .stats-grid {
        grid-template-columns: 1fr 1fr;
      }
    }

    @media (max-width: 768px) {
      .navbar-inner {
        flex-wrap: wrap;
      }
      .nav-actions {
        width: 100%;
        justify-content: flex-start;
        overflow-x: auto;
        padding-bottom: 4px;
      }
      .hero-panel {
        text-align: center;
      }
      .lead {
        margin-left: auto;
        margin-right: auto;
      }
      .hero-actions {
        justify-content: center;
      }
      .section-header {
        text-align: center;
      }
      .section-desc {
        margin-left: auto;
        margin-right: auto;
      }
      .callout {
        flex-direction: column;
        text-align: center;
      }
      .stats-grid {
        grid-template-columns: 1fr;
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

<div class="container">
  <!-- ============ NAVBAR ============ -->
  <nav class="navbar">
    <div class="navbar-inner glass">
      <a class="brand" href="<?php echo APP_URL; ?>/index.php">
        <img src="<?php echo APP_URL; ?>/assets/icons/icon.png" alt="<?php echo htmlspecialchars(APP_NAME); ?>" width="36" height="36">
      </a>
      <div class="nav-actions">
        <a class="nav-chip <?php echo basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/about.php">
          <i class="fa-solid fa-circle-info"></i> Tentang
        </a>
        <a class="nav-chip <?php echo basename($_SERVER['PHP_SELF']) === 'privacy.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/privacy.php">
          <i class="fa-solid fa-shield-halved"></i> Privasi
        </a>
        <a class="nav-chip <?php echo basename($_SERVER['PHP_SELF']) === 'terms.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/terms.php">
          <i class="fa-solid fa-file-contract"></i> Syarat
        </a>
        <a class="nav-chip primary" href="<?php echo APP_URL; ?>/portal/login.php">
          <i class="fa-solid fa-right-to-bracket"></i> Login
        </a>
      </div>
    </div>
  </nav>

  <!-- ============ KONTEN UTAMA ============ -->
  <main>
    <?php echo $mainContent; ?>
  </main>

  <!-- ============ FOOTER ============ -->
  <footer>
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. Semua hak dilindungi.</p>
  </footer>
</div>

<!-- ============ SCRIPTS ============ -->
<script>
  // Reveal animation
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('show');
      }
    });
  }, { threshold: 0.1 });
  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

  // Navbar scroll effect
  const navbarInner = document.querySelector('.navbar-inner');
  window.addEventListener('scroll', () => {
    if (navbarInner) {
      navbarInner.style.background = window.scrollY > 10
        ? 'rgba(255, 255, 255, 0.98)'
        : 'rgba(255, 255, 255, 0.92)';
    }
  });
</script>

</body>
</html>