<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Kebijakan Privasi';
$pageDescription = 'Kebijakan privasi terkait pengumpulan, penggunaan, dan perlindungan data pelanggan.';

$privacySections = [
    [
        'icon' => 'fa-folder-open',
        'title' => 'Data yang kami kumpulkan',
        'text' => 'Kami dapat menyimpan nama, kontak, alamat layanan, detail tagihan, dan informasi teknis yang diperlukan untuk aktivasi serta dukungan layanan.'
    ],
    [
        'icon' => 'fa-diagram-project',
        'title' => 'Cara data digunakan',
        'text' => 'Data digunakan untuk administrasi pelanggan, penanganan gangguan, penagihan, dan pengelolaan layanan yang Anda gunakan.'
    ],
    [
        'icon' => 'fa-shield-halved',
        'title' => 'Perlindungan data',
        'text' => 'Kami menerapkan pembatasan akses, praktik keamanan operasional, dan pengelolaan data yang wajar untuk mengurangi risiko penyalahgunaan.'
    ],
    [
        'icon' => 'fa-hand-holding-heart',
        'title' => 'Hak Anda',
        'text' => 'Anda dapat meminta klarifikasi, koreksi, atau pembaruan informasi pelanggan melalui kanal resmi kami jika ada data yang tidak sesuai.'
    ]
];

$extraItems = [
    [
        'title' => 'Pembaruan data',
        'desc' => 'Anda dapat meminta koreksi data pelanggan yang tidak akurat melalui kanal resmi kami.'
    ],
    [
        'title' => 'Akses terbatas',
        'desc' => 'Hanya personel yang berwenang yang dapat mengakses data yang diperlukan untuk menjalankan layanan.'
    ]
];

ob_start();
?>

<section class="hero reveal">
    <div class="hero-grid">
        <div class="hero-panel glass">
            <div class="hero-copy">
                <div class="chip chip-default hero-chip">
                    <i class="fa-solid fa-shield-heart"></i> Kebijakan privasi
                </div>
                <h1>Data pelanggan dikelola untuk layanan, bukan untuk disalahgunakan.</h1>
                <p class="lead">Kami menjaga informasi pelanggan tetap relevan, terbatas, dan digunakan hanya untuk kebutuhan operasional yang sah seperti aktivasi layanan, penagihan, dukungan teknis, dan keamanan sistem.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="terms.php">
                        <i class="fa-solid fa-file-contract"></i> Baca Syarat
                    </a>
                    <a class="btn btn-secondary" href="about.php">
                        <i class="fa-solid fa-arrow-left"></i> Kembali ke Tentang
                    </a>
                </div>
            </div>
        </div>

        <aside class="hero-aside">
            <div class="aside-card glass">
                <h2>Prinsip pengelolaan data</h2>
                <p>Privasi pelanggan tetap menjadi perhatian utama dalam pengumpulan, pemrosesan, dan penyimpanan data yang berhubungan dengan layanan.</p>
                <div class="note">
                    <i class="fa-solid fa-info-circle" style="margin-right: 8px;"></i>
                    Jika diperlukan secara hukum atau untuk proses layanan pihak ketiga yang sah, data dapat dibagikan secara terbatas dan proporsional.
                </div>
            </div>
        </aside>
    </div>
</section>

<section class="section glass reveal">
    <div class="section-header">
        <div class="chip chip-muted section-badge">
            <i class="fa-solid fa-lock"></i> Ringkasan kebijakan
        </div>
        <h2 class="section-title">Bagaimana data pelanggan dipakai</h2>
        <p class="section-desc">Daftar berikut menjelaskan area utama yang menjadi dasar kebijakan privasi kami.</p>
    </div>

    <div class="grid grid-2cols">
        <?php foreach ($privacySections as $section): ?>
            <article class="card">
                <i class="fa-solid <?php echo htmlspecialchars($section['icon']); ?>"></i>
                <h3><?php echo htmlspecialchars($section['title']); ?></h3>
                <p><?php echo htmlspecialchars($section['text']); ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="list-items">
        <?php foreach ($extraItems as $item): ?>
            <div class="list-item">
                <i class="fa-solid fa-circle-check"></i>
                <div>
                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                    <span><?php echo htmlspecialchars($item['desc']); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="callout">
        <div>
            <strong>Punya pertanyaan tentang privasi?</strong>
            <p>Silakan lihat halaman kontak di beranda untuk menghubungi tim kami.</p>
        </div>
        <a class="pill" href="index.php#contact">
            <i class="fa-solid fa-arrow-right"></i> Hubungi kami
        </a>
    </div>
</section>

<?php
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout-page.php';