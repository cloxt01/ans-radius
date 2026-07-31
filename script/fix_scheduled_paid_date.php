<?php

function generateLateDays(): int
{
    $roll = mt_rand(1, 100);

    // 60% cepat bayar
    if ($roll <= 60) {
        return mt_rand(0, 1);
    }

    // 25% telat ringan
    if ($roll <= 85) {
        return mt_rand(2, 5);
    }

    // 10% telat sedang
    if ($roll <= 95) {
        return mt_rand(6, 10);
    }

    // 5% telat berat
    return mt_rand(11, 20);
}

function generateScheduledPaidDate(string $dueDate, int $lateDays): string
{
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

    $minute = mt_rand(0, 59);
    $second = mt_rand(0, 59);

    $date = (new DateTime($dueDate))
        ->modify("+{$lateDays} days");

    $date->setTime($hour, $minute, $second);

    return $date->format('Y-m-d H:i:s');
}

$dryRun = in_array('--dry-run', $argv);

include '../includes/config.php';
$host = DB_HOST;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = 'utf8mb4';

date_default_timezone_set('Asia/Jakarta');

// [PERBAIKAN] Instansiasi PDO yang benar
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$pdo->beginTransaction();

try {
    // [PERBAIKAN] Tambahkan i.status dan i.paid_at ke dalam SELECT
    $sql = "
        SELECT
            i.id,
            i.invoice_number,
            i.due_date,
            i.status,
            i.paid_at
        FROM invoices i
        INNER JOIN customers c
            ON c.id = i.customer_id
        INNER JOIN fiktif_customers fc
            ON fc.customer_id = c.id
        LEFT JOIN fiktif_invoices fi
            ON fi.invoice_id = i.id
        WHERE fi.invoice_id IS NULL
        ORDER BY i.due_date ASC
    ";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    echo "Total invoice yang belum ada di fiktif_invoices : "
        . count($rows)
        . PHP_EOL . PHP_EOL;

    // [PERBAIKAN] Status dibuat dinamis (?) bukan hardcode 'unpaid'
    $insert = $pdo->prepare("
        INSERT INTO fiktif_invoices
        (
            invoice_id,
            late_days,
            scheduled_paid_date,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?
        )
    ");

    foreach ($rows as $row) {
        
        // [PERBAIKAN] Logika pengecekan status paid vs unpaid
        if ($row['status'] === 'paid' && $row['paid_at'] !== null) {
            // Jika sudah lunas, hitung selisih hari aslinya (opsional, untuk kerapian data)
            $dueDateObj = new DateTime($row['due_date']);
            $paidAtObj = new DateTime($row['paid_at']);
            
            // Set ke 0 jika bayar sebelum jatuh tempo
            $lateDays = $paidAtObj > $dueDateObj ? $dueDateObj->diff($paidAtObj)->days : 0;
            
            // Samakan jadwal dengan waktu bayar asli
            $scheduledPaidDate = $row['paid_at'];
            $fiktifStatus = 'paid';
        } else {
            // Jika belum lunas, generate secara acak
            $lateDays = generateLateDays();
            $scheduledPaidDate = generateScheduledPaidDate($row['due_date'], $lateDays);
            $fiktifStatus = 'unpaid';
        }

        echo sprintf(
            "[%s] Invoice: %s | Status: %s | Late: %d hari | Scheduled: %s\n",
            $dryRun ? 'DRY' : 'INSERT',
            $row['invoice_number'],
            $fiktifStatus,
            $lateDays,
            $scheduledPaidDate
        );

        if (!$dryRun) {
            $insert->execute([
                $row['id'],
                $lateDays,
                $scheduledPaidDate,
                $fiktifStatus // Memasukkan status yang sesuai
            ]);
        }
    }

    if ($dryRun) {
        $pdo->rollBack();
        echo PHP_EOL . "Dry Run selesai. Tidak ada perubahan database." . PHP_EOL;
    } else {
        $pdo->commit();
        echo PHP_EOL . "Selesai. Berhasil mengisi fiktif_invoices." . PHP_EOL;
    }

} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

