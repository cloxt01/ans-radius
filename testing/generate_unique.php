<?php

// 1. Cek apakah file sumber data tersedia
if (!file_exists('nama.txt') || !file_exists('domain.txt')) {
    die("Error: File nama.txt atau domain.txt tidak ditemukan di folder ini.\n");
}

// 2. Baca isi file TXT
// FILE_IGNORE_NEW_LINES mencegah baris baru (enter) ikut terbaca
// FILE_SKIP_EMPTY_LINES mengabaikan baris yang kosong
$poolNama = file('nama.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$poolDomain = file('domain.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Bersihkan spasi berlebih di awal/akhir kata dan jadikan huruf kapital (opsional)
$poolNama = array_map('trim', $poolNama);
$poolNama = array_map('strtoupper', $poolNama);

$poolDomain = array_map('trim', $poolDomain);
$poolDomain = array_map('strtoupper', $poolDomain);

// Pastikan file tidak kosong
if (empty($poolNama) || empty($poolDomain)) {
    die("Error: File nama.txt atau domain.txt kosong!\n");
}

$totalDataGenerate = 1215; // Ubah sesuai jumlah data yang ingin dibuat (misal: 1500)
$namaFile = 'username_baru_fiktif.csv';

// Array memori untuk melacak username yang sudah dipakai
$usernameTerpakai = [];

// Buka file stream untuk menulis CSV
$file = fopen($namaFile, 'w');
fputcsv($file, ['id', 'pppoe_username'], ';');

for ($i = 1; $i <= $totalDataGenerate; $i++) {
    // 3. Ambil acak nama dan domain dari data TXT yang sudah dibaca
    $namaAcak = $poolNama[array_rand($poolNama)];
    $domainAcak = $poolDomain[array_rand($poolDomain)];

    // 4. Format dasar username
    $usernameDasar = $namaAcak . '@' . $domainAcak;
    $usernameFinal = $usernameDasar;

    // 5. Logika Pengecekan Duplikat
    $angka = 1;
    while (isset($usernameTerpakai[$usernameFinal])) {
        // Jika duplikat, tambahkan angka (misal: ANDI1@HARMONI)
        $usernameFinal = $namaAcak . $angka . '@' . $domainAcak;
        $angka++;
    }

    // 6. Simpan username final ke memori
    $usernameTerpakai[$usernameFinal] = true;

    // 7. Tulis ke CSV
    fputcsv($file, [$i, $usernameFinal], ';');
}

fclose($file);

echo "Sukses! {$totalDataGenerate} username telah dibuat dari file TXT dan disimpan ke {$namaFile}\n";
?>