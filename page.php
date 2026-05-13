<?php
/**
 * About, Terms, Privacy Page - GitHub Dark Theme
 * Tema terintegrasi untuk halaman tentang, syarat & ketentuan, dan privasi
 */

require_once __DIR__ . '/includes/config.php';

// Tentukan halaman berdasarkan parameter 'page'
$page = $_GET['page'] ?? 'about';

// Map halaman ke konfigurasi
$pageConfig = [
    'about' => [
        'title' => 'Tentang Kami',
        'description' => 'Profil perusahaan, nilai layanan, dan komitmen ANS RADIUS kepada pelanggan.',
        'icon' => 'fa-circle-info',
        'heroIcon' => 'fa-wave-square',
        'heroTitle' => 'Internet yang stabil, layanan yang jelas, dan dukungan yang cepat.',
        'heroDesc' => 'ANS RADIUS dibangun untuk membantu pelanggan mendapatkan koneksi internet yang andal sekaligus pengalaman layanan yang sederhana, responsif, dan transparan dari awal pemasangan sampai penggunaan harian.',
        'badge' => 'Tentang kami',
        'asideTitle' => 'Ringkasan singkat',
        'asideDesc' => 'Kami memprioritaskan koneksi yang konsisten, proses yang mudah dipahami, dan bantuan teknis yang cepat saat pelanggan membutuhkannya.',
        'sectionBadge' => 'Nilai layanan',
        'sectionTitle' => 'Prinsip yang kami jaga setiap hari',
        'sectionDesc' => 'Halaman ini merangkum cara kami bekerja: membangun jaringan yang dapat diandalkan, memberikan bantuan yang cepat, dan menjaga informasi layanan tetap terbuka.',
        'calloutTitle' => 'Butuh bantuan lebih lanjut?',
        'calloutDesc' => 'Kunjungi halaman kontak di beranda untuk menghubungi tim kami atau meninjau layanan yang tersedia.',
    ],
    'terms' => [
        'title' => 'Syarat & Ketentuan',
        'description' => 'Ketentuan penggunaan layanan, pembayaran, dan tanggung jawab pelanggan.',
        'icon' => 'fa-file-contract',
        'heroIcon' => 'fa-file-contract',
        'heroTitle' => 'Ketentuan dibuat agar penggunaan layanan tetap adil dan jelas.',
        'heroDesc' => 'Ketentuan ini membantu menjaga kualitas layanan, mengatur proses pembayaran, dan memastikan setiap pelanggan memahami batas tanggung jawab dalam penggunaan layanan.',
        'badge' => 'Syarat layanan',
        'asideTitle' => 'Garis besar aturan',
        'asideDesc' => 'Penggunaan layanan berarti pelanggan menyetujui ketentuan berikut: patuh pada aturan, menjaga pembayaran tepat waktu, dan tidak menyalahgunakan jaringan.',
        'sectionBadge' => 'Ringkasan ketentuan',
        'sectionTitle' => 'Hal-hal utama yang perlu dipahami',
        'sectionDesc' => 'Poin-poin berikut membantu pelanggan memahami bagaimana layanan digunakan, dibayar, dan dihentikan bila perlu.',
        'calloutTitle' => 'Perlu penjelasan lebih lanjut?',
        'calloutDesc' => 'Silakan hubungi tim kami melalui halaman kontak di beranda jika ada bagian ketentuan yang ingin dikonfirmasi.',
    ],
    'privacy' => [
        'title' => 'Kebijakan Privasi',
        'description' => 'Kebijakan privasi terkait pengumpulan, penggunaan, dan perlindungan data pelanggan.',
        'icon' => 'fa-shield-halved',
        'heroIcon' => 'fa-shield-heart',
        'heroTitle' => 'Data pelanggan dikelola untuk layanan, bukan untuk disalahgunakan.',
        'heroDesc' => 'Kami menjaga informasi pelanggan tetap relevan, terbatas, dan digunakan hanya untuk kebutuhan operasional yang sah seperti aktivasi layanan, penagihan, dukungan teknis, dan keamanan sistem.',
        'badge' => 'Kebijakan privasi',
        'asideTitle' => 'Prinsip pengelolaan data',
        'asideDesc' => 'Privasi pelanggan tetap menjadi perhatian utama dalam pengumpulan, pemrosesan, dan penyimpanan data yang berhubungan dengan layanan.',
        'sectionBadge' => 'Ringkasan kebijakan',
        'sectionTitle' => 'Bagaimana data pelanggan dipakai',
        'sectionDesc' => 'Daftar berikut menjelaskan area utama yang menjadi dasar kebijakan privasi kami.',
        'calloutTitle' => 'Punya pertanyaan tentang privasi?',
        'calloutDesc' => 'Silakan lihat halaman kontak di beranda untuk menghubungi tim kami.',
    ],
];

// Data konten per halaman
$contentData = [
    'about' => [
        'highlights' => [
            ['label' => 'Fokus', 'value' => 'Internet stabil untuk rumah, UMKM, dan bisnis lokal.'],
            ['label' => 'Layanan', 'value' => 'Dukungan cepat, alur administrasi jelas, dan komunikasi yang responsif.'],
            ['label' => 'Komitmen', 'value' => 'Kami menjaga pengalaman layanan yang konsisten, aman, dan transparan.'],
        ],
        'principles' => [
            ['icon' => 'fa-signal', 'title' => 'Koneksi andal', 'text' => 'Mengutamakan kestabilan jaringan agar aktivitas harian pelanggan tetap lancar.'],
            ['icon' => 'fa-headset', 'title' => 'Support responsif', 'text' => 'Tim kami membantu menelusuri gangguan dan menyelesaikan kendala dengan cepat.'],
            ['icon' => 'fa-scale-balanced', 'title' => 'Transparansi', 'text' => 'Biaya, proses, dan ketentuan layanan dijelaskan dengan bahasa yang mudah dipahami.'],
        ],
        'extraItems' => null,
        'extraList' => null,
        'note' => null,
    ],
    'terms' => [
        'highlights' => null,
        'principles' => [
            ['icon' => 'fa-user-check', 'title' => 'Penggunaan layanan', 'text' => 'Pelanggan wajib menggunakan layanan sesuai hukum yang berlaku dan tidak menyalahgunakannya untuk aktivitas yang merugikan pihak lain.'],
            ['icon' => 'fa-wallet', 'title' => 'Pembayaran dan tagihan', 'text' => 'Pembayaran dilakukan sesuai jatuh tempo. Keterlambatan dapat memicu pembatasan sementara sampai tagihan diselesaikan.'],
            ['icon' => 'fa-network-wired', 'title' => 'Penggunaan wajar', 'text' => 'Kami dapat menerapkan kebijakan penggunaan wajar untuk menjaga kualitas layanan dan kestabilan jaringan pelanggan lainnya.'],
            ['icon' => 'fa-triangle-exclamation', 'title' => 'Penghentian layanan', 'text' => 'Layanan dapat dihentikan jika terjadi pelanggaran ketentuan, penyalahgunaan, atau tunggakan yang tidak diselesaikan setelah pemberitahuan.'],
        ],
        'extraItems' => [
            ['title' => 'Keterlambatan tagihan', 'desc' => 'Layanan dapat dibatasi sementara jika tagihan melewati jatuh tempo.'],
            ['title' => 'Pelanggaran penggunaan', 'desc' => 'Aktivitas ilegal, penyalahgunaan jaringan, atau tindakan yang merugikan pihak lain tidak diperbolehkan.'],
        ],
        'extraList' => null,
        'note' => ['text' => 'Aturan ini dapat diperbarui saat dibutuhkan untuk menyesuaikan operasional, regulasi, atau peningkatan kualitas layanan.'],
    ],
    'privacy' => [
        'highlights' => null,
        'principles' => [
            ['icon' => 'fa-folder-open', 'title' => 'Data yang kami kumpulkan', 'text' => 'Kami dapat menyimpan nama, kontak, alamat layanan, detail tagihan, dan informasi teknis yang diperlukan untuk aktivasi serta dukungan layanan.'],
            ['icon' => 'fa-diagram-project', 'title' => 'Cara data digunakan', 'text' => 'Data digunakan untuk administrasi pelanggan, penanganan gangguan, penagihan, dan pengelolaan layanan yang Anda gunakan.'],
            ['icon' => 'fa-shield-halved', 'title' => 'Perlindungan data', 'text' => 'Kami menerapkan pembatasan akses, praktik keamanan operasional, dan pengelolaan data yang wajar untuk mengurangi risiko penyalahgunaan.'],
            ['icon' => 'fa-hand-holding-heart', 'title' => 'Hak Anda', 'text' => 'Anda dapat meminta klarifikasi, koreksi, atau pembaruan informasi pelanggan melalui kanal resmi kami jika ada data yang tidak sesuai.'],
        ],
        'extraItems' => [
            ['title' => 'Pembaruan data', 'desc' => 'Anda dapat meminta koreksi data pelanggan yang tidak akurat melalui kanal resmi kami.'],
            ['title' => 'Akses terbatas', 'desc' => 'Hanya personel yang berwenang yang dapat mengakses data yang diperlukan untuk menjalankan layanan.'],
        ],
        'extraList' => null,
        'note' => ['text' => 'Jika diperlukan secara hukum atau untuk proses layanan pihak ketiga yang sah, data dapat dibagikan secara terbatas dan proporsional.'],
    ],
];

$config = $pageConfig[$page];
$data = $contentData[$page];

// Set page title dan description
$pageTitle = $config['title'];
$pageDescription = $config['description'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0d1117">
  <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars(APP_NAME); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">

  <!-- PWA Meta Tags -->
  <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
  <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/icons/icon.png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
      --shadow-medium: 0 4px 12px rgba(0,0,0,0.3);
      --shadow-large: 0 8px 24px rgba(0,0,0,0.4);
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
      width: min(1180px, calc(100% - 32px));
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
      text-decoration: none;
    }

    .btn-secondary {
      border: 1px solid var(--border-default);
      color: var(--fg-default);
      background: transparent;
    }

    .btn-secondary:hover {
      background: var(--bg-tertiary);
      border-color: var(--fg-muted);
      text-decoration: none;
    }

    /* Chips / Tags */
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
      background: rgba(47, 129, 247, 0.1);
      color: var(--accent-blue);
      border: 1px solid rgba(47, 129, 247, 0.35);
    }

    .chip-muted {
      background: rgba(255, 255, 255, 0.03);
      color: var(--fg-muted);
      border: 1px solid var(--border-muted);
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
      background: rgba(22, 27, 34, 0.92);
      border: 1px solid var(--border-default);
      border-radius: 20px;
      backdrop-filter: blur(12px);
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
      background: rgba(47, 129, 247, 0.08);
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
      background: rgba(47, 129, 247, 0.15);
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

    /* ==================== HERO SECTION ==================== */
    .hero {
      margin-bottom: 28px;
    }

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
    }

    .hero-panel::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(47, 129, 247, 0.08), rgba(255, 255, 255, 0));
      pointer-events: none;
    }

    .hero-copy {
      position: relative;
      z-index: 1;
    }

    .hero-chip {
      margin-bottom: 20px;
    }

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

    .hero-aside {
      display: grid;
      gap: 14px;
    }

    .aside-card {
      border-radius: 24px;
      padding: 28px;
    }

    .aside-card h2 {
      font-size: 1.35rem;
      margin-bottom: 12px;
    }

    .aside-card p {
      color: var(--fg-muted);
    }

    .stat-list {
      display: grid;
      gap: 12px;
      margin-top: 20px;
    }

    .stat-item {
      padding: 14px 16px;
      border-radius: 14px;
      background: rgba(47, 129, 247, 0.05);
      border: 1px solid rgba(47, 129, 247, 0.12);
    }

    .stat-item strong {
      display: block;
      font-size: 0.9rem;
      margin-bottom: 4px;
      color: var(--fg-default);
    }

    .stat-item span {
      color: var(--fg-muted);
      font-size: 0.85rem;
    }

    .note {
      margin-top: 16px;
      padding: 14px 16px;
      border-radius: 14px;
      background: rgba(47, 129, 247, 0.05);
      border: 1px solid rgba(47, 129, 247, 0.12);
      font-size: 0.85rem;
      color: var(--fg-muted);
    }

    /* ==================== SECTION ==================== */
    .section {
      margin-top: 28px;
      padding: 32px;
      border-radius: 24px;
    }

    .section-header {
      margin-bottom: 28px;
    }

    .section-badge {
      margin-bottom: 16px;
    }

    .section-title {
      font-size: 1.6rem;
      letter-spacing: -0.02em;
      margin-bottom: 12px;
    }

    .section-desc {
      color: var(--fg-muted);
      max-width: 70ch;
    }

    /* Grid */
    .grid {
      display: grid;
      gap: 20px;
    }

    .grid-2cols {
      grid-template-columns: repeat(2, 1fr);
    }

    .grid-3cols {
      grid-template-columns: repeat(3, 1fr);
    }

    .grid-4cols {
      grid-template-columns: repeat(4, 1fr);
    }

    /* Cards */
    .card {
      padding: 24px;
      border-radius: 20px;
      background: var(--bg-primary);
      border: 1px solid var(--border-default);
      transition: all 0.2s;
    }

    .card:hover {
      border-color: var(--accent-blue);
      transform: translateY(-2px);
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
      background: linear-gradient(135deg, var(--accent-blue), #1a5fb4);
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

    /* List Items */
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
      background: var(--bg-primary);
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

    /* Callout */
    .callout {
      margin-top: 28px;
      padding: 24px 28px;
      border-radius: 20px;
      background: linear-gradient(135deg, rgba(47, 129, 247, 0.08), rgba(22, 27, 34, 0.95));
      border: 1px solid rgba(47, 129, 247, 0.2);
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
      background: var(--bg-tertiary);
      padding: 10px 18px;
      border-radius: 999px;
      color: var(--fg-default);
      font-size: 0.85rem;
      font-weight: 500;
    }

    .callout .pill:hover {
      background: var(--accent-blue);
      color: #fff;
      text-decoration: none;
    }

    /* Footer */
    footer {
      padding: 40px 0 24px;
      margin-top: 40px;
      border-top: 1px solid var(--border-default);
      text-align: center;
      color: var(--fg-muted);
      font-size: 0.8rem;
    }

    /* Animations */
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
      .section {
        padding: 24px;
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
  <!-- Navbar -->
  <nav class="navbar">
    <div class="navbar-inner glass">
      <a class="brand" href="index.php">
        <img src="<?php echo APP_URL; ?>/assets/icons/icon.png" alt="<?php echo htmlspecialchars(APP_NAME); ?>" width="36" height="36">
        <span><?php echo htmlspecialchars(APP_NAME); ?></span>
      </a>
      <div class="nav-actions">
        <a class="nav-chip <?php echo $page === 'about' ? 'active' : ''; ?>" href="?page=about">
          <i class="fa-solid fa-circle-info"></i> Tentang
        </a>
        <a class="nav-chip <?php echo $page === 'terms' ? 'active' : ''; ?>" href="?page=terms">
          <i class="fa-solid fa-file-contract"></i> Syarat
        </a>
        <a class="nav-chip <?php echo $page === 'privacy' ? 'active' : ''; ?>" href="?page=privacy">
          <i class="fa-solid fa-shield-halved"></i> Privasi
        </a>
        <a class="nav-chip primary" href="portal/login.php">
          <i class="fa-solid fa-right-to-bracket"></i> Login
        </a>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-grid">
      <div class="hero-panel glass reveal">
        <div class="hero-copy">
          <div class="chip chip-default hero-chip">
            <i class="fa-solid <?php echo $config['heroIcon']; ?>"></i>
            <?php echo $config['badge']; ?>
          </div>
          <h1><?php echo $config['heroTitle']; ?></h1>
          <p class="lead"><?php echo $config['heroDesc']; ?></p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="index.php#packages">
              <i class="fa-solid fa-layer-group"></i> Lihat Paket
            </a>
            <a class="btn btn-secondary" href="index.php#contact">
              <i class="fa-solid fa-headset"></i> Hubungi Kami
            </a>
          </div>
        </div>
      </div>

      <aside class="hero-aside reveal">
        <div class="aside-card glass">
          <h2><?php echo $config['asideTitle']; ?></h2>
          <p><?php echo $config['asideDesc']; ?></p>

          <?php if ($data['highlights']): ?>
            <div class="stat-list">
              <?php foreach ($data['highlights'] as $item): ?>
                <div class="stat-item">
                  <strong><?php echo htmlspecialchars($item['label']); ?></strong>
                  <span><?php echo htmlspecialchars($item['value']); ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($data['note']): ?>
            <div class="note">
              <i class="fa-solid fa-info-circle" style="margin-right: 8px;"></i>
              <?php echo htmlspecialchars($data['note']['text']); ?>
            </div>
          <?php endif; ?>
        </div>
      </aside>
    </div>
  </section>

  <!-- Main Content Section -->
  <section class="section glass reveal">
    <div class="section-header">
      <div class="chip chip-muted section-badge">
        <i class="fa-solid <?php echo $config['icon']; ?>"></i>
        <?php echo $config['sectionBadge']; ?>
      </div>
      <h2 class="section-title"><?php echo $config['sectionTitle']; ?></h2>
      <p class="section-desc"><?php echo $config['sectionDesc']; ?></p>
    </div>

    <!-- Principles/Features Grid -->
    <?php
    $principlesCount = count($data['principles']);
    $gridClass = $principlesCount <= 2 ? 'grid-2cols' : ($principlesCount <= 3 ? 'grid-3cols' : 'grid-4cols');
    ?>
    <div class="grid <?php echo $gridClass; ?>">
      <?php foreach ($data['principles'] as $principle): ?>
        <article class="card">
          <i class="fa-solid <?php echo htmlspecialchars($principle['icon']); ?>"></i>
          <h3><?php echo htmlspecialchars($principle['title']); ?></h3>
          <p><?php echo htmlspecialchars($principle['text']); ?></p>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- Extra Items (for terms & privacy) -->
    <?php if ($data['extraItems']): ?>
      <div class="list-items">
        <?php foreach ($data['extraItems'] as $item): ?>
          <div class="list-item">
            <i class="fa-solid fa-circle-check"></i>
            <div>
              <strong><?php echo htmlspecialchars($item['title']); ?></strong>
              <span><?php echo htmlspecialchars($item['desc']); ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Callout -->
    <div class="callout">
      <div>
        <strong><?php echo $config['calloutTitle']; ?></strong>
        <p><?php echo $config['calloutDesc']; ?></p>
      </div>
      <a class="pill" href="index.php#contact">
        <i class="fa-solid fa-arrow-right"></i> Hubungi kami
      </a>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. Semua hak dilindungi.</p>
  </footer>
</div>

<script>
  // Scroll reveal animations
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
        ? 'rgba(13, 17, 23, 0.98)'
        : 'rgba(22, 27, 34, 0.92)';
    }
  });
</script>

</body>
</html>