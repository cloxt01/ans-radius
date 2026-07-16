<?php

require_once __DIR__ . '/../includes/db.php';

$dryRun = true;

/*
|--------------------------------------------------------------------------
| Konfigurasi
|--------------------------------------------------------------------------
|
| Script ini HANYA memindahkan customer fiktif yang:
| - ada di fiktif_customers
| - sudah pernah memiliki fiktif_invoice
|
| Customer lain tidak disentuh.
|
*/

$MAX_DIFF = 30; // stop jika selisih distribusi <= 30

$pdo = getDB();

/*
|--------------------------------------------------------------------------
| Distribusi awal
|--------------------------------------------------------------------------
*/

$distribution = [];

$stmt = $pdo->query("
    SELECT billing_day, COUNT(*) total
    FROM customers
    GROUP BY billing_day
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $distribution[(int)$row['billing_day']] = (int)$row['total'];

}

for ($i=1;$i<=31;$i++){

    if(!isset($distribution[$i])){
        $distribution[$i]=0;
    }

}

echo "=============== BEFORE ===============\n";

$tmp=$distribution;
ksort($tmp);

foreach($tmp as $d=>$c){

    printf("%02d => %d\n",$d,$c);

}

$totalCustomer=array_sum($distribution);

echo "\n";
echo "Total Customer : {$totalCustomer}\n";
echo "Stop Diff      : {$MAX_DIFF}\n";
echo "\n";

/*
|--------------------------------------------------------------------------
| Ambil pool customer yang BOLEH dipindah
|--------------------------------------------------------------------------
*/

$pool = fetchAll("

SELECT
    c.billing_day,
    COUNT(*) total
FROM customers c
JOIN fiktif_customers fc
    ON fc.customer_id = c.id
WHERE EXISTS (
    SELECT 1
    FROM invoices i
    JOIN fiktif_invoices fi
        ON fi.invoice_id = i.id
    WHERE i.customer_id = c.id
)
GROUP BY c.billing_day

");

shuffle($pool);

echo "Pool Customer  : ".count($pool).PHP_EOL;
echo PHP_EOL;

$poolMap=[];

foreach($pool as $row){

    $poolMap[$row['id']]=$row['billing_day'];

}

$moved=0;
/*
|--------------------------------------------------------------------------
| Mulai balancing
|--------------------------------------------------------------------------
*/

if (!$dryRun) {
    $pdo->beginTransaction();
}

try {

    while (true) {

        $maxDay = null;
        $maxCount = -1;

        $minDay = null;
        $minCount = PHP_INT_MAX;

        foreach ($distribution as $day => $count) {

            if ($count > $maxCount) {
                $maxCount = $count;
                $maxDay = $day;
            }

            if ($count < $minCount) {
                $minCount = $count;
                $minDay = $day;
            }
        }

        $diff = $maxCount - $minCount;

        if ($diff <= $MAX_DIFF) {
            break;
        }

        /*
        |--------------------------------------------------------------------------
        | Cari customer yang masih berada di hari terpadat
        |--------------------------------------------------------------------------
        */

        $candidate = null;

        foreach ($pool as $key => $row) {

            if ($poolMap[$row['id']] == $maxDay) {

                $candidate = $row;

                unset($pool[$key]);

                break;
            }
        }

        if (!$candidate) {

            echo "Tidak ada kandidat lagi pada billing day {$maxDay}\n";

            $distribution[$maxDay] = -1;

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Hitung isolation_date baru
        |--------------------------------------------------------------------------
        */

        $today = new DateTime();

        $targetDay = min(
            $minDay,
            (int)$today->format('t')
        );

        $isolation = new DateTime(
            $today->format('Y-m-') .
            str_pad($targetDay, 2, '0', STR_PAD_LEFT)
        );

        if ($isolation < $today) {

            $isolation->modify('+1 month');

            $targetDay = min(
                $minDay,
                (int)$isolation->format('t')
            );

            $isolation->setDate(
                (int)$isolation->format('Y'),
                (int)$isolation->format('m'),
                $targetDay
            );
        }

        printf(
            "[%s] #%d | %02d -> %02d | diff=%d\n",
            $dryRun ? "DRY" : "MOVE",
            $candidate['id'],
            $maxDay,
            $minDay,
            $diff
        );

        if (!$dryRun) {

            update(
                'customers',
                [
                    'billing_day' => $minDay,
                    'isolation_date' => $isolation->format('Y-m-d')
                ],
                'id=?',
                [$candidate['id']]
            );
        }

        $distribution[$maxDay]--;
        $distribution[$minDay]++;

        $poolMap[$candidate['id']] = $minDay;

        $moved++;
    }
    /*
|--------------------------------------------------------------------------
| Commit / Rollback
|--------------------------------------------------------------------------
*/

    if (!$dryRun) {
        $pdo->commit();
    }

} catch (Throwable $e) {

    if (!$dryRun && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

/*
|--------------------------------------------------------------------------
| Distribusi akhir
|--------------------------------------------------------------------------
*/

echo PHP_EOL;
echo "=============== AFTER =================" . PHP_EOL;

ksort($distribution);

foreach ($distribution as $day => $count) {

    printf("%02d => %d\n", $day, $count);

}

echo PHP_EOL;

$max = max($distribution);
$min = min($distribution);

echo "========================================" . PHP_EOL;
echo "Dry Run      : " . ($dryRun ? "YES" : "NO") . PHP_EOL;
echo "Max          : {$max}" . PHP_EOL;
echo "Min          : {$min}" . PHP_EOL;
echo "Selisih      : " . ($max - $min) . PHP_EOL;
echo "Dipindahkan  : {$moved}" . PHP_EOL;
echo "========================================" . PHP_EOL;

/*
|--------------------------------------------------------------------------
| Statistik customer yang dipindahkan
|--------------------------------------------------------------------------
*/

echo PHP_EOL;
echo "Pool Customer : " . count($poolMap) . PHP_EOL;
echo "Moved         : {$moved}" . PHP_EOL;
echo "Remaining Gap : " . ($max - $min) . PHP_EOL;