<?php

$pdo = new PDO("mysql:host=localhost;dbname=ans_radius", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

function generatePaidAtFromDistribution(string $paidAt): string
{
    $date = new DateTime($paidAt);

    $roll = rand(1, 100);

    if ($roll <= 32) {
        // 09:00 - 11:00
        $start = 9 * 3600;
        $end   = 11 * 3600;

    } elseif ($roll <= 50) {
        // 11:00 - 13:00
        $start = 11 * 3600;
        $end   = 13 * 3600;

    } elseif ($roll <= 70) {
        // 13:00 - 15:00
        $start = 13 * 3600;
        $end   = 15 * 3600;

    } elseif ($roll <= 90) {
        // 15:00 - 17:00
        $start = 15 * 3600;
        $end   = 17 * 3600;

    } else {
        // 17:00 - 19:35
        $start = 17 * 3600;
        $end   = (19 * 3600) + (35 * 60);
    }

    $seconds = rand($start, $end);

    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;

    $date->setTime($h, $m, $s);

    return $date->format('Y-m-d H:i:s');
}

// ambil hanya invoice customer fiktif + jam rusak
$sql = "
    SELECT i.id, i.paid_at
    FROM invoices i
    INNER JOIN fiktif_customers fc 
        ON fc.customer_id = i.customer_id
    WHERE i.paid_at IS NOT NULL
      AND TIME(i.paid_at) <= '00:00:59'
";

$rows = $pdo->query($sql)->fetchAll();

$update = $pdo->prepare("
    UPDATE invoices
    SET paid_at = ?
    WHERE id = ?
");

$pdo->beginTransaction();

foreach ($rows as $row) {

    $newPaidAt = generatePaidAtFromDistribution($row['paid_at']);

    $update->execute([
        $newPaidAt,
        $row['id']
    ]);

    echo "FIX {$row['id']} -> $newPaidAt\n";
}

$pdo->commit();

echo "DONE\n";