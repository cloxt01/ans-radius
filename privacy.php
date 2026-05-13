<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Kebijakan Privasi';
$pageDescription = 'Kebijakan privasi terkait pengumpulan, penggunaan, dan perlindungan data pelanggan.';

$privacySections = [
  [
    'icon' => 'fa-folder-open',
    'title' => 'Data yang kami kumpulkan',
    'text' => 'Kami dapat menyimpan nama, kontak, alamat layanan, detail tagihan, dan informasi teknis yang diperlukan untuk aktivasi serta dukungan layanan.',
  ],
  [
    'icon' => 'fa-diagram-project',
    'title' => 'Cara data digunakan',
    'text' => 'Data digunakan untuk administrasi pelanggan, penanganan gangguan, penagihan, dan pengelolaan layanan yang Anda gunakan.',
  ],
  [
    'icon' => 'fa-shield-halved',
    'title' => 'Perlindungan data',
    'text' => 'Kami menerapkan pembatasan akses, praktik keamanan operasional, dan pengelolaan data yang wajar untuk mengurangi risiko penyalahgunaan.',
  ],
  [
    'icon' => 'fa-hand-holding-heart',
    'title' => 'Hak Anda',
    'text' => 'Anda dapat meminta klarifikasi, koreksi, atau pembaruan informasi pelanggan melalui kanal resmi kami jika ada data yang tidak sesuai.',
  ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0ea5e9">
  <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars(APP_NAME); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
  <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #0ea5e9;
      --primary-dark: #0284c7;
      --dark: #0f172a;
      --surface: #f8fbff;
      --surface-card: rgba(255, 255, 255, 0.9);
      --surface-border: rgba(148, 163, 184, 0.2);
      --text: #111827;
      --muted: #475569;
      --soft: #64748b;
      --shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
      --shadow-strong: 0 24px 60px rgba(14, 165, 233, 0.16);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background:
        radial-gradient(circle at 12% 10%, rgba(14, 165, 233, 0.14) 0, rgba(14, 165, 233, 0) 38%),
        radial-gradient(circle at 85% 18%, rgba(37, 99, 235, 0.1) 0, rgba(37, 99, 235, 0) 34%),
        linear-gradient(180deg, #f9fcff 0%, var(--surface) 100%);
      color: var(--text);
      line-height: 1.6;
      overflow-x: hidden;
    }

    a { color: inherit; text-decoration: none; }

    .bg-orb {
      position: fixed;
      inset: auto;
      border-radius: 999px;
      filter: blur(28px);
      opacity: 0.42;
      pointer-events: none;
      z-index: -2;
    }

    .orb-1 { width: 280px; height: 280px; left: -60px; top: 120px; background: rgba(14, 165, 233, 0.18); }
    .orb-2 { width: 360px; height: 360px; right: -120px; top: 50px; background: rgba(245, 158, 11, 0.09); }
    .orb-3 { width: 240px; height: 240px; right: 12%; bottom: -80px; background: rgba(56, 189, 248, 0.12); }

    .page-shell { width: min(1180px, calc(100% - 32px)); margin: 0 auto; padding: 22px 0 56px; }

    .navbar {
      position: sticky;
      top: 14px;
      z-index: 20;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      padding: 16px 20px;
      margin-bottom: 30px;
      background: rgba(255, 255, 255, 0.82);
      border: 1px solid rgba(148, 163, 184, 0.18);
      border-radius: 22px;
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
    }

    .brand { display: inline-flex; align-items: center; gap: 12px; font-weight: 800; color: var(--dark); letter-spacing: -0.02em; }
    .brand img { width: 42px; height: 42px; border-radius: 12px; box-shadow: 0 8px 18px rgba(14, 165, 233, 0.2); }
    .brand span { font-size: 1.02rem; }
    .nav-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }

    .nav-chip,
    .hero-chip,
    .pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border-radius: 999px;
      padding: 10px 16px;
      font-weight: 600;
      transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
    }

    .nav-chip { background: rgba(14, 165, 233, 0.08); color: var(--primary-dark); border: 1px solid rgba(14, 165, 233, 0.14); }
    .nav-chip:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(14, 165, 233, 0.12); }
    .nav-chip.primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; box-shadow: 0 10px 22px rgba(14, 165, 233, 0.24); }

    .hero { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 24px; align-items: stretch; }
    .glass { background: var(--surface-card); border: 1px solid var(--surface-border); box-shadow: var(--shadow); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); }

    .hero-panel { position: relative; overflow: hidden; border-radius: 30px; padding: 42px; }
    .hero-panel::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(14, 165, 233, 0.08), rgba(255, 255, 255, 0)); pointer-events: none; }
    .hero-copy { position: relative; z-index: 1; }
    .hero-chip { background: rgba(15, 23, 42, 0.04); color: var(--muted); border: 1px solid rgba(148, 163, 184, 0.16); margin-bottom: 18px; }
    h1 { font-size: clamp(2.2rem, 5vw, 4.4rem); line-height: 1.02; letter-spacing: -0.04em; margin-bottom: 18px; color: var(--dark); }
    .lead { font-size: 1.06rem; color: var(--muted); max-width: 62ch; margin-bottom: 26px; }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px; padding: 13px 18px; border-radius: 16px; font-weight: 700; transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease; }
    .btn:hover { transform: translateY(-2px); }
    .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; box-shadow: 0 14px 28px rgba(14, 165, 233, 0.24); }
    .btn-secondary { background: rgba(255, 255, 255, 0.76); color: var(--dark); border: 1px solid rgba(148, 163, 184, 0.18); }

    .hero-aside { display: grid; gap: 14px; }
    .aside-card { border-radius: 26px; padding: 24px; }
    .aside-card h2, .section-title { font-size: 1.45rem; line-height: 1.2; letter-spacing: -0.03em; margin-bottom: 12px; color: var(--dark); }
    .aside-card p, .section-copy, .card p, .note p { color: var(--muted); }

    .grid { display: grid; gap: 18px; }
    .grid.features { grid-template-columns: repeat(2, minmax(0, 1fr)); }

    .card {
      border-radius: 22px;
      padding: 22px;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    .card:hover { transform: translateY(-4px); box-shadow: var(--shadow-strong); border-color: rgba(14, 165, 233, 0.24); }
    .card i { width: 48px; height: 48px; display: inline-flex; align-items: center; justify-content: center; border-radius: 16px; margin-bottom: 14px; color: #fff; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); box-shadow: 0 12px 22px rgba(14, 165, 233, 0.2); }
    .card h3 { font-size: 1.08rem; margin-bottom: 8px; color: var(--dark); }

    .section { margin-top: 22px; padding: 28px; border-radius: 28px; }
    .section-header { margin-bottom: 18px; }
    .section-copy { max-width: 70ch; }

    .list { display: grid; gap: 12px; margin-top: 16px; }
    .list-item { display: flex; gap: 12px; align-items: flex-start; padding: 16px 18px; border-radius: 18px; background: rgba(255, 255, 255, 0.74); border: 1px solid rgba(148, 163, 184, 0.16); }
    .list-item i { color: var(--primary); margin-top: 3px; }

    .callout { margin-top: 22px; border-radius: 26px; padding: 24px; background: linear-gradient(135deg, rgba(14, 165, 233, 0.12), rgba(255, 255, 255, 0.9)); border: 1px solid rgba(14, 165, 233, 0.16); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .callout .pill { background: rgba(255, 255, 255, 0.85); color: var(--dark); border: 1px solid rgba(148, 163, 184, 0.16); }

    .note { margin-top: 14px; padding: 18px 20px; border-radius: 18px; background: rgba(14, 165, 233, 0.06); border: 1px solid rgba(14, 165, 233, 0.12); }

    footer { padding: 30px 4px 8px; color: var(--soft); text-align: center; font-size: 0.95rem; }

    @media (max-width: 960px) {
      .hero, .grid.features { grid-template-columns: 1fr; }
      .hero-panel, .section { padding: 24px; }
    }

    @media (max-width: 640px) {
      .page-shell { width: min(100% - 18px, 1180px); }
      .navbar { padding: 14px 16px; border-radius: 20px; }
      .brand span { font-size: 0.98rem; }
      .nav-actions { gap: 8px; }
      .nav-chip { padding: 9px 13px; }
      .hero-panel, .section, .aside-card { padding: 20px; }
      h1 { font-size: 2.15rem; }
      .lead { font-size: 0.98rem; }
      .btn { width: 100%; }
      .hero-actions { width: 100%; }
      .nav-actions { width: 100%; justify-content: flex-start; }
    }
  </style>
</head>
<body>
  <div class="bg-orb orb-1"></div>
  <div class="bg-orb orb-2"></div>
  <div class="bg-orb orb-3"></div>

  <div class="page-shell">
    <nav class="navbar glass" aria-label="Navigasi utama">
      <a class="brand" href="index.php">
        <img src="<?php echo APP_URL; ?>/assets/icons/icon.png" alt="<?php echo htmlspecialchars(APP_NAME); ?>" width="42" height="42">
        <span><?php echo htmlspecialchars(APP_NAME); ?></span>
      </a>
      <div class="nav-actions">
        <a class="nav-chip" href="about.php"><i class="fa-solid fa-circle-info"></i> Tentang</a>
        <a class="nav-chip" href="terms.php"><i class="fa-solid fa-file-contract"></i> Syarat</a>
        <a class="nav-chip primary" href="portal/login.php"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
      </div>
    </nav>

    <main>
      <section class="hero">
        <div class="hero-panel glass">
          <div class="hero-copy">
            <span class="hero-chip"><i class="fa-solid fa-shield-heart"></i> Kebijakan privasi</span>
            <h1>Data pelanggan dikelola untuk layanan, bukan untuk disalahgunakan.</h1>
            <p class="lead">Kami menjaga informasi pelanggan tetap relevan, terbatas, dan digunakan hanya untuk kebutuhan operasional yang sah seperti aktivasi layanan, penagihan, dukungan teknis, dan keamanan sistem.</p>
            <div class="hero-actions">
              <a class="btn btn-primary" href="terms.php"><i class="fa-solid fa-file-contract"></i> Baca Syarat</a>
              <a class="btn btn-secondary" href="about.php"><i class="fa-solid fa-arrow-left"></i> Kembali ke Tentang</a>
            </div>
          </div>
        </div>

        <aside class="hero-aside">
          <div class="aside-card glass">
            <h2>Prinsip pengelolaan data</h2>
            <p>Privasi pelanggan tetap menjadi perhatian utama dalam pengumpulan, pemrosesan, dan penyimpanan data yang berhubungan dengan layanan.</p>
            <div class="note">
              <p>Jika diperlukan secara hukum atau untuk proses layanan pihak ketiga yang sah, data dapat dibagikan secara terbatas dan proporsional.</p>
            </div>
          </div>
        </aside>
      </section>

      <section class="section glass">
        <div class="section-header">
          <span class="hero-chip"><i class="fa-solid fa-lock"></i> Ringkasan kebijakan</span>
          <h2 class="section-title">Bagaimana data pelanggan dipakai</h2>
          <p class="section-copy">Daftar berikut menjelaskan area utama yang menjadi dasar kebijakan privasi kami.</p>
        </div>

        <div class="grid features">
          <?php foreach ($privacySections as $section): ?>
            <article class="card glass">
              <i class="fa-solid <?php echo htmlspecialchars($section['icon']); ?>"></i>
              <h3><?php echo htmlspecialchars($section['title']); ?></h3>
              <p><?php echo htmlspecialchars($section['text']); ?></p>
            </article>
          <?php endforeach; ?>
        </div>

        <div class="list">
          <div class="list-item">
            <i class="fa-solid fa-circle-check"></i>
            <div>
              <strong style="display:block; color:var(--dark); margin-bottom:4px;">Pembaruan data</strong>
              <span>Anda dapat meminta koreksi data pelanggan yang tidak akurat melalui kanal resmi kami.</span>
            </div>
          </div>
          <div class="list-item">
            <i class="fa-solid fa-circle-check"></i>
            <div>
              <strong style="display:block; color:var(--dark); margin-bottom:4px;">Akses terbatas</strong>
              <span>Hanya personel yang berwenang yang dapat mengakses data yang diperlukan untuk menjalankan layanan.</span>
            </div>
          </div>
        </div>

        <div class="callout">
          <div>
            <strong style="display:block; font-size:1.06rem; color:var(--dark); margin-bottom:6px;">Punya pertanyaan tentang privasi?</strong>
            <p style="margin:0;">Silakan lihat halaman kontak di beranda untuk menghubungi tim kami.</p>
          </div>
          <a class="pill" href="index.php#contact"><i class="fa-solid fa-arrow-right"></i> Hubungi kami</a>
        </div>
      </section>
    </main>

    <footer>
      &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. Semua hak dilindungi.
    </footer>
  </div>
</body>
</html>
