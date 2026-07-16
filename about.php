<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Tentang ANS Radius';
$pageDescription = 'Profil ANS Radius, penyedia internet di Kabupaten Serang sejak 2016. Layanan PPPoE dan Hotspot dengan jaringan stabil dan dukungan teknis responsif.';

$aboutHighlights = [
    [
        'label' => 'Berdiri',
        'value' => 'Sejak 2016'
    ],
    [
        'label' => 'Layanan',
        'value' => 'Internet PPPoE & Hotspot'
    ],
    [
        'label' => 'Wilayah',
        'value' => 'Kramatwatu, Kabupaten Serang'
    ],
];

$aboutSections = [
    [
        'icon' => 'fa-network-wired',
        'title' => 'Jaringan Stabil',
        'text' => 'Kami terus melakukan pemeliharaan dan pengembangan jaringan agar pelanggan memperoleh koneksi internet yang konsisten.'
    ],
    [
        'icon' => 'fa-headset',
        'title' => 'Dukungan Responsif',
        'text' => 'Tim kami siap membantu proses instalasi maupun penanganan gangguan secara cepat dan profesional.'
    ],
    [
        'icon' => 'fa-shield-halved',
        'title' => 'Layanan Terpercaya',
        'text' => 'Sejak tahun 2016 kami berkomitmen memberikan layanan internet yang aman, transparan, dan dapat dipercaya.'
    ],
    [
        'icon' => 'fa-location-dot',
        'title' => 'Fokus Wilayah Lokal',
        'text' => 'Berpusat di Kecamatan Kramatwatu, Kabupaten Serang, kami memahami kebutuhan pelanggan di wilayah sekitar secara lebih dekat.'
    ],
];

ob_start();
?>

<section class="hero reveal">
  <div class="hero-grid">
    <div class="hero-panel glass">
      <div class="hero-copy">
        <div class="chip chip-default hero-chip">
          <i class="fa-solid fa-circle-info"></i> Tentang ANS Radius
        </div>
        <h1>Internet yang stabil untuk rumah, UMKM, dan bisnis sejak 2016.</h1>
        <p class="lead">
          ANS Radius merupakan penyedia layanan internet yang beroperasi sejak tahun 2016 di Kabupaten Serang. Kami menyediakan akses internet berbasis PPPoE dan Hotspot dengan fokus pada kestabilan jaringan, pelayanan yang responsif, serta dukungan teknis yang cepat agar pelanggan dapat tetap terhubung untuk bekerja, belajar, maupun menjalankan usaha.
        </p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="index.php#contact">
            <i class="fa-solid fa-headset"></i> Hubungi Kami
          </a>
          <a class="btn btn-secondary" href="index.php">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
          </a>
        </div>
      </div>
    </div>

    <aside class="hero-aside">
      <div class="aside-card glass">
        <h2>Sekilas Tentang Kami</h2>
        <p>Selama bertahun-tahun kami terus mengembangkan jaringan dan meningkatkan kualitas layanan agar pelanggan memperoleh koneksi internet yang andal dengan proses layanan yang mudah dan transparan.</p>
        <div class="stats-grid">
          <?php foreach ($aboutHighlights as $stat): ?>
            <div class="stat-item">
              <span class="value"><?php echo htmlspecialchars($stat['value']); ?></span>
              <span class="label"><?php echo htmlspecialchars($stat['label']); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>
  </div>
</section>

<section class="section glass reveal">
  <div class="section-header">
    <div class="chip chip-muted section-badge">
      <i class="fa-solid fa-star"></i> Mengapa ANS Radius
    </div>
    <h2 class="section-title">Komitmen kami dalam memberikan layanan internet yang dapat diandalkan.</h2>
    <p class="section-desc">
      Kami percaya bahwa kualitas layanan tidak hanya ditentukan oleh kecepatan internet, tetapi juga oleh kestabilan jaringan, transparansi informasi, serta dukungan teknis yang siap membantu pelanggan ketika dibutuhkan.
    </p>
  </div>

  <div class="grid grid-2cols">
    <?php foreach ($aboutSections as $section): ?>
      <article class="card">
        <i class="fa-solid <?php echo htmlspecialchars($section['icon']); ?>"></i>
        <h3><?php echo htmlspecialchars($section['title']); ?></h3>
        <p><?php echo htmlspecialchars($section['text']); ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<div class="callout reveal">
  <div>
    <strong>Siap menggunakan layanan ANS Radius?</strong>
    <p>Hubungi tim kami untuk mendapatkan informasi paket internet, proses pemasangan, maupun konsultasi kebutuhan jaringan.</p>
  </div>
  <a class="pill" href="index.php#contact">
    <i class="fa-solid fa-arrow-right"></i> Hubungi Kami
  </a>
</div>

<section class="section glass reveal" style="margin-top: 28px;">
  <h2 style="font-size: 1.6rem; letter-spacing: -0.02em; margin-bottom: 12px;">
    <i class="fa-solid fa-building" style="color: var(--accent-blue); margin-right: 10px;"></i> Profil Perusahaan
  </h2>
  <p style="color: var(--fg-muted); max-width: 72ch;">
    <strong>ANS Radius</strong> — Didirikan pada tahun 2016, ANS Radius merupakan penyedia layanan internet yang melayani wilayah Kramatwatu dan sekitarnya di Kabupaten Serang, Banten. Kami menyediakan layanan Internet berbasis PPPoE dan Hotspot dengan mengutamakan kualitas jaringan, pelayanan yang responsif, serta kepuasan pelanggan sebagai prioritas utama.
  </p>
  <div style="margin-top: 16px; padding: 16px 20px; background: rgba(47, 129, 247, 0.05); border-radius: 16px; border-left: 4px solid var(--accent-blue); color: var(--fg-muted);">
    <i class="fa-solid fa-location-dot" style="color: var(--accent-blue); margin-right: 8px;"></i>
    <strong>Alamat:</strong><br>
    Jl. Tonjong–Terate, Pamengkang,<br>
    Kecamatan Kramatwatu,<br>
    Kabupaten Serang,<br>
    Banten 42616.
  </div>
</section>

<?php
$mainContent = ob_get_clean();

require __DIR__ . '/includes/layout-page.php';
?>