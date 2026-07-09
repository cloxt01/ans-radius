<?php

// =====================
// DB CONFIG
// =====================
include '../includes/config.php';

$host    = DB_HOST;
$db      = DB_NAME;
$user    = DB_USER;
$pass    = DB_PASS;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Koneksi gagal : ".$e->getMessage().PHP_EOL);
}

$apply = in_array('--apply', $_SERVER['argv'] ?? []);

$targetActive = 2000;

// =====================
// Active Customer
// =====================

$active = (int)$pdo->query("
    SELECT COUNT(*)
    FROM customers
    WHERE status='active'
")->fetchColumn();

echo "=========================================\n";
echo "Mode            : ".($apply ? "APPLY" : "DRY RUN").PHP_EOL;
echo "Active sekarang : {$active}".PHP_EOL;
echo "Target Active   : {$targetActive}".PHP_EOL;
echo "=========================================\n\n";

if ($active >= $targetActive) {
    exit("Target sudah tercapai.\n");
}

$need = $targetActive - $active;

echo "Perlu dipercepat : {$need}\n\n";

// =====================
// Ambil invoice yang masih unpaid
// =====================

$stmt = $pdo->query("
    SELECT
        fi.id,
        fi.late_days,
        fi.scheduled_paid_date,
        i.due_date,
        i.invoice_number,
        i.customer_id
    FROM fiktif_invoices fi
    INNER JOIN invoices i
        ON i.id = fi.invoice_id
    WHERE fi.status='unpaid'
      AND i.status='unpaid'
      AND fi.scheduled_paid_date > NOW()
    ORDER BY fi.scheduled_paid_date ASC
    LIMIT {$need}
");

$rows = $stmt->fetchAll();

echo "Invoice ditemukan : ".count($rows).PHP_EOL.PHP_EOL;

if (!$rows) {
    exit("Tidak ada invoice yang bisa dipercepat.\n");
}

$update = $pdo->prepare("
    UPDATE fiktif_invoices
    SET
        late_days=?,
        scheduled_paid_date=?
    WHERE id=?
");

$totalUpdated = 0;

foreach ($rows as $row) {

    $oldSchedule = new DateTime($row['scheduled_paid_date']);

    // percepat 1-5 hari
    $reduceDays = mt_rand(1, 5);

    $newSchedule = clone $oldSchedule;
    $newSchedule->modify("-{$reduceDays} days");

    // jangan sampai lebih awal dari due_date
    $dueDate = new DateTime($row['due_date']);

    if ($newSchedule < $dueDate) {
        $newSchedule = clone $dueDate;

        // random jam pembayaran
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

        $newSchedule->setTime(
            $hour,
            mt_rand(0,59),
            mt_rand(0,59)
        );
    }

    // hitung ulang late_days
    $lateDays = max(
        0,
        floor(
            (
                $newSchedule->getTimestamp()
                -
                $dueDate->getTimestamp()
            ) / 86400
        )
    );

    echo sprintf(
        "[%s] Customer #%d | %s\n",
        $apply ? "UPDATE" : "DRY",
        $row['customer_id'],
        $row['invoice_number']
    );

    echo "    Lama  : {$row['scheduled_paid_date']} ({$row['late_days']} hari)\n";
    echo "    Baru  : ".$newSchedule->format('Y-m-d H:i:s')." ({$lateDays} hari)\n";
    echo "    Maju  : {$reduceDays} hari\n\n";

    if ($apply) {

        $update->execute([
            $lateDays,
            $newSchedule->format('Y-m-d H:i:s'),
            $row['id']
        ]);

        $totalUpdated++;
    }
}

echo "=========================================\n";

if ($apply) {
    echo "Berhasil mengupdate {$totalUpdated} invoice.\n";
} else {
    echo "DRY RUN selesai.\n";
    echo "Tidak ada perubahan pada database.\n";
}

echo "=========================================\n";