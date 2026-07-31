<?php
/**
 * cleanup_radcheck.php
 *
 * Menghapus semua username di radius_db.radcheck
 * yang sudah tidak ada di ans_radius.customers.
 *
 * Jalankan:
 *   php cleanup_radcheck.php          -> dry-run
 *   php cleanup_radcheck.php --apply  -> hapus sungguhan
 */

include '../includes/config.php';

$DB_HOST = DB_HOST;
$DB_USER = DB_USER;
$DB_PASS = DB_PASS;
$DB_ANS  = DB_NAME;
$DB_RAD  = RADIUS_DB_NAME;

$apply = in_array('--apply', $argv);

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die($e->getMessage());
}

echo $apply
    ? "Mode: APPLY (data akan dihapus)\n\n"
    : "Mode: DRY-RUN (tidak ada perubahan)\n\n";

/*
|--------------------------------------------------------------------------
| Cari username yang tidak ada di customers
|--------------------------------------------------------------------------
*/

$sql = "
SELECT
    rc.username,
    COUNT(*) AS total_attribute
FROM {$DB_RAD}.radcheck rc
LEFT JOIN {$DB_ANS}.customers c
    ON c.pppoe_username COLLATE utf8mb4_unicode_ci =
       rc.username COLLATE utf8mb4_unicode_ci
WHERE c.id IS NULL
GROUP BY rc.username
ORDER BY rc.username
";

$rows = $pdo->query($sql)->fetchAll();

echo "Username orphan : " . count($rows) . PHP_EOL . PHP_EOL;

if (!$rows) {
    echo "Tidak ada data yang perlu dihapus.\n";
    exit;
}

$stmtDelete = $pdo->prepare("
    DELETE FROM {$DB_RAD}.radcheck
    WHERE username = ?
");

$totalRowDeleted = 0;

if ($apply) {
    $pdo->beginTransaction();
}

try {

    foreach ($rows as $row) {

        printf(
            "[DELETE] %-30s (%d attribute)\n",
            $row['username'],
            $row['total_attribute']
        );

        if ($apply) {

            $stmtDelete->execute([
                $row['username']
            ]);

            $totalRowDeleted += $stmtDelete->rowCount();
        }
    }

    if ($apply) {
        $pdo->commit();
    }

} catch (Throwable $e) {

    if ($apply) {
        $pdo->rollBack();
    }

    throw $e;
}

echo PHP_EOL;
echo "==============================" . PHP_EOL;
echo "Username diproses : " . count($rows) . PHP_EOL;
echo "Row dihapus       : " . $totalRowDeleted . PHP_EOL;
echo "==============================" . PHP_EOL;

if (!$apply) {
    echo PHP_EOL;
    echo "Dry-run selesai. Jalankan dengan --apply untuk menghapus.\n";
}
