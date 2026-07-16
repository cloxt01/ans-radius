<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Syarat & Ketentuan';
$pageDescription = 'Ketentuan penggunaan layanan, pembayaran, dan tanggung jawab pelanggan ANS RADIUS.';

$termsSections = [
    [
        'icon' => 'fa-user-check',
        'title' => 'Penggunaan layanan',
        'text' => 'Pelanggan wajib menggunakan layanan sesuai hukum yang berlaku dan tidak menyalahgunakannya untuk aktivitas yang merugikan pihak lain.'
    ],
    [
        'icon' => 'fa-wallet',
        'title' => 'Pembayaran dan tagihan',
        'text' => 'Pembayaran dilakukan sesuai jatuh tempo. Keterlambatan dapat memicu pembatasan sementara sampai tagihan diselesaikan.'
    ],
    [
        'icon' => 'fa-network-wired',
        'title' => 'Penggunaan wajar',
        'text' => 'Kami dapat menerapkan kebijakan penggunaan wajar untuk menjaga kualitas layanan dan kestabilan jaringan pelanggan lainnya.'
    ],
    [
        'icon' => 'fa-triangle-exclamation',
        'title' => 'Penghentian layanan',
        'text' => 'Layanan dapat dihentikan jika terjadi pelanggaran ketentuan, penyalahgunaan, atau tunggakan yang tidak diselesaikan setelah pemberitahuan.'
    ]
];

$extraItems = [
    [
        'title' => 'Keterlambatan tagihan',
        'desc' => 'Layanan dapat dibatasi sementara jika tagihan melewati jatuh tempo.'
    ],
    [
        'title' => 'Pelanggaran penggunaan',
        'desc' => 'Aktivitas ilegal, penyalahgunaan jaringan, atau tindakan yang merugikan pihak lain tidak diperbolehkan.'
    ]
];

ob_start();
?>

<section class="hero reveal">
    <div class="hero-grid">
        <div class="hero-panel glass">
            <div class="hero-copy">
                <div class="chip chip-default hero-chip">
                    <i class="fa-solid fa-file-contract"></i> Syarat layanan
                </div>
                <h1>Ketentuan dibuat agar penggunaan layanan tetap adil dan jelas.</h1>
                <p class="lead">Ketentuan ini membantu menjaga kualitas layanan, mengatur proses pembayaran, dan memastikan setiap pelanggan memahami batas tanggung jawab dalam penggunaan layanan ANS RADIUS.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="privacy.php">
                        <i class="fa-solid fa-shield-halved"></i> Lihat Privasi
                    </a>
                    <a class="btn btn-secondary" href="about.php">
                        <i class="fa-solid fa-arrow-left"></i> Kembali ke Tentang
                    </a>
                </div>
            </div>
        </div>

        <aside class="hero-aside">
            <div class="aside-card glass">
                <h2>Garis besar aturan</h2>
                <p>Penggunaan layanan berarti pelanggan menyetujui ketentuan berikut: patuh pada aturan, menjaga pembayaran tepat waktu, dan tidak menyalahgunakan jaringan.</p>
                <div class="note">
                    <i class="fa-solid fa-info-circle" style="margin-right: 8px;"></i>
                    Aturan ini dapat diperbarui saat dibutuhkan untuk menyesuaikan operasional, regulasi, atau peningkatan kualitas layanan.
                </div>
            </div>
        </aside>
    </div>
</section>

<section class="section glass reveal">
    <div class="section-header">
        <div class="chip chip-muted section-badge">
            <i class="fa-solid fa-scale-balanced"></i> Ringkasan ketentuan
        </div>
        <h2 class="section-title">Hal-hal utama yang perlu dipahami</h2>
        <p class="section-desc">Poin-poin berikut membantu pelanggan memahami bagaimana layanan digunakan, dibayar, dan dihentikan bila perlu.</p>
    </div>

    <div class="grid grid-2cols">
        <?php foreach ($termsSections as $section): ?>
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
            <strong>Perlu penjelasan lebih lanjut?</strong>
            <p>Silakan hubungi tim kami melalui halaman kontak di beranda jika ada bagian ketentuan yang ingin dikonfirmasi.</p>
        </div>
        <a class="pill" href="index.php#contact">
            <i class="fa-solid fa-arrow-right"></i> Hubungi kami
        </a>
    </div>
</section>

<?php
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout-page.php';