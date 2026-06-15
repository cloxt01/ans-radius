<?php
include '../includes/config.php';

$isDryRun = !in_array('--apply', $argv);
$fileUsername = 'fiktif_username.csv';
$fileId       = 'fiktif_id.csv';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $hUser = fopen($fileUsername, "r");
    $hId   = fopen($fileId, "r");

    if ($hUser && $hId) {
        // Skip header
        fgets($hUser); fgets($hId);

        $stmt = $pdo->prepare("UPDATE customers SET name = :name WHERE id = :id");
        $pdo->beginTransaction();

        $count = 0;
        while (($rowU = fgetcsv($hUser)) !== FALSE && ($rowI = fgetcsv($hId)) !== FALSE) {
            $fullUser = $rowU[0] ?? ''; // [NAMA]@[DOMAIN]
            $id       = $rowI[0] ?? '';

            // Ambil NAMA saja
            $name = explode('@', $fullUser)[0];

            if (!empty($id) && !empty($name)) {
                $stmt->execute([':name' => $name, ':id' => $id]);
                $count += $stmt->rowCount();
            }
        }

        $isDryRun ? $pdo->rollBack() : $pdo->commit();
        echo $isDryRun ? "[DRY RUN] Simulasi: $count nama akan diperbarui.\n" : "Berhasil update $count nama.\n";

        fclose($hUser); fclose($hId);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}