<?php
include '../includes/config.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

function fixHourByDistribution(string $dateTimeStr): string {
    $date = new DateTime($dateTimeStr);
    
    // Distribusi jam sesuai permintaan
    $roll = mt_rand(1, 100);
    if ($roll <= 30) {
        $hour = mt_rand(9, 10);
    } elseif ($roll <= 65) {
        $hour = mt_rand(11, 12);
    } elseif ($roll <= 90) {
        $hour = mt_rand(13, 16);
    } else {
        $hour = mt_rand(17, 18);
    }

    $date->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));
    return $date->format('Y-m-d H:i:s');
}

// Mengambil semua data yang jamnya ada di 00:00 - 04:00
$sql = "
    SELECT i.id, i.paid_at, fi.scheduled_paid_date
    FROM invoices i
    INNER JOIN fiktif_invoices fi ON i.id = fi.invoice_id
    WHERE (TIME(i.paid_at) BETWEEN '00:00:00' AND '04:00:00')
       OR (TIME(fi.scheduled_paid_date) BETWEEN '00:00:00' AND '04:00:00')
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$pdo->beginTransaction();

try {
    $stmtUpdateInv = $pdo->prepare("UPDATE invoices SET paid_at = ? WHERE id = ?");
    $stmtUpdateFi  = $pdo->prepare("UPDATE fiktif_invoices SET scheduled_paid_date = ? WHERE invoice_id = ?");

    foreach ($rows as $row) {
        // Kondisi 1: Jika paid_at ada isinya, kita buat waktu baru berdasarkan paid_at
        if ($row['paid_at'] !== null) {
            $newTime = fixHourByDistribution($row['paid_at']);
            
            // Update keduanya dan samakan
            $stmtUpdateInv->execute([$newTime, $row['id']]);
            $stmtUpdateFi->execute([$newTime, $row['id']]);
            echo "Sync (Paid) ID {$row['id']} -> $newTime\n";
        } 
        // Kondisi 2: Jika paid_at null, hanya update scheduled_paid_date
        else {
            $newTime = fixHourByDistribution($row['scheduled_paid_date']);
            
            $stmtUpdateFi->execute([$newTime, $row['id']]);
            echo "Sync (Unpaid) ID {$row['id']} -> $newTime\n";
        }
    }

    $pdo->commit();
    echo "Sinkronisasi selesai. Jam 00:00-04:00 telah diperbaiki sesuai status.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
