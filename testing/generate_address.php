<?php

// ==========================================
// 1. KONFIGURASI FILE
// ==========================================
$usernameFile = 'fiktif_username.csv';
$mappingFile  = 'address_mapping.csv';
$outputFile   = 'fiktif_update.csv';

// ==========================================
// 2. FUNGSI GENERATOR RANDOM ALAMAT
// ==========================================
function generateRandomAddress($template) {
    // A. Parse format [START-END] (misal: [A-Z], [1-80], [A- Z])
    $template = preg_replace_callback('/\[\s*([A-Za-z0-9]+)\s*-\s*([A-Za-z0-9]+)\s*\]/', function($matches) {
        $start = trim($matches[1]);
        $end   = trim($matches[2]);

        // Jika range adalah angka (misal 1-80)
        if (is_numeric($start) && is_numeric($end)) {
            return rand((int)$start, (int)$end);
        }
        // Jika range adalah huruf (misal A-Z)
        elseif (ctype_alpha($start) && ctype_alpha($end)) {
            $range = range(strtoupper($start), strtoupper($end));
            return $range[array_rand($range)];
        }

        return $matches[0]; // Kembalikan string asli jika tidak cocok
    }, $template);

    // B. Parse format xxx (random 001 - 012)
    $template = preg_replace_callback('/xxx/', function() {
        return str_pad(rand(1, 12), 3, '0', STR_PAD_LEFT);
    }, $template);

    // C. Parse format xx (random 01 - 12)
    $template = preg_replace_callback('/xx/', function() {
        return str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
    }, $template);

    // D. Parse format x tunggal (jika ada seperti RT/RW : x/x) - Random 1-9
    // Regex ini memastikan 'x' berdiri sendiri, tidak berada di dalam sebuah kata
    $template = preg_replace_callback('/(?<![a-zA-Z])x(?![a-zA-Z])/', function() {
        return rand(1, 9);
    }, $template);

    return trim($template);
}

// ==========================================
// 3. BACA DATA MAPPING ADDRESS
// ==========================================
$mapping = [];
if (($handle = fopen($mappingFile, "r")) !== FALSE) {
    $header = fgetcsv($handle); // Lewati header pertama

    while (($row = fgetcsv($handle)) !== FALSE) {
        $domain = trim($row[0]);
        $addressOptionsStr = $row[1] ?? '';

        // Pecah berdasarkan koma, lalu hapus spasi di awal/akhir tiap opsi
        $options = array_map('trim', explode(',', $addressOptionsStr));

        // Hapus opsi yang kosong (jika ada koma berlebih)
        $options = array_filter($options);

        $mapping[$domain] = array_values($options);
    }
    fclose($handle);
} else {
    die("Error: Tidak dapat membaca file $mappingFile\n");
}

// ==========================================
// 4. BACA USERNAME & GENERATE OUTPUT CSV
// ==========================================
if (($in = fopen($usernameFile, "r")) !== FALSE && ($out = fopen($outputFile, "w")) !== FALSE) {
    $header = fgetcsv($in); // Baca header file asli

    // Tulis header untuk file hasil update
    fputcsv($out, ['username', 'address']);

    $successCount = 0;
    $notFoundCount = 0;

    while (($row = fgetcsv($in)) !== FALSE) {
        // Asumsi format CSV Anda: kolom index 0 = id, index 1 = username
        $username = $row[1] ?? '';
        $generatedAddress = '';

        // Ekstrak domain dari username (misal budi@PURIDELTA -> PURIDELTA)
        $parts = explode('@', $username);

        if (count($parts) == 2) {
            $domain = trim($parts[1]);

            // Cek apakah domain tersebut punya mapping alamat
            if (isset($mapping[$domain]) && count($mapping[$domain]) > 0) {
                // 1. Pilih salah satu opsi alamat secara acak
                $options = $mapping[$domain];
                $selectedTemplate = $options[array_rand($options)];

                // 2. Generate string berdasarkan template (replace xx, xxx, [A-Z])
                $generatedAddress = generateRandomAddress($selectedTemplate);
                $successCount++;
            } else {
                $notFoundCount++; // Domain tidak ada di address_mapping.csv
            }
        }

        // Tulis ke file CSV baru
        fputcsv($out, [$username, $generatedAddress]);
    }

    fclose($in);
    fclose($out);

    echo "Proses Generate Selesai!\n";
    echo "- Berhasil generate alamat: $successCount baris\n";
    echo "- Domain tidak ditemukan (alamat kosong): $notFoundCount baris\n";
    echo "- Silakan cek file: $outputFile\n";

} else {
    die("Error: Tidak dapat membaca $usernameFile atau membuat $outputFile\n");
}

?>