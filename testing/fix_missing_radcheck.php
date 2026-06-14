<?php
/**
 * fix_missing_radcheck.php
 *
 * Menambahkan row ke radius_db.radcheck untuk semua customer
 * (ans_radius.customers) yang belum punya row sama sekali di radcheck.
 *
 * Setiap customer yang missing akan ditambahkan 2 attribute:
 *   - Cleartext-Password := 1234
 *   - Simultaneous-Use   := 1
 *
 * Jalankan via CLI:
 *   php fix_missing_radcheck.php            -> dry-run (tampilkan saja, tidak insert)
 *   php fix_missing_radcheck.php --apply     -> eksekusi insert sungguhan
 */

// ====== KONFIGURASI DB ======
$DB_HOST = '127.0.0.1';
$DB_USER = 'ans_radius';
$DB_PASS = '95b3783482dc8'; // sesuaikan password
$DB_ANS_RADIUS = 'ans_radius';
$DB_RADIUS     = 'radius_db';

$DEFAULT_PASSWORD = '1234';
// =============================

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
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo $apply
    ? "Mode: APPLY (data akan diinsert)\n\n"
    : "Mode: DRY-RUN (tidak ada perubahan ke database)\n\n";

// ----------------------------------------------------------------------
// 1. Ambil semua customer yang belum punya row di radcheck
// ----------------------------------------------------------------------
$sqlMissing = "
    SELECT 
        c.id,
        c.pppoe_username,
        c.status
    FROM {$DB_ANS_RADIUS}.customers c
    LEFT JOIN {$DB_RADIUS}.radcheck rc 
        ON rc.username COLLATE utf8mb4_unicode_ci = c.pppoe_username COLLATE utf8mb4_unicode_ci
    WHERE rc.username IS NULL
    ORDER BY c.id
";

$missing = $pdo->query($sqlMissing)->fetchAll();

echo "Total customer belum ada di radcheck: " . count($missing) . "\n\n";

if (empty($missing)) {
    echo "Tidak ada yang perlu ditambahkan.\n";
    exit(0);
}

// ----------------------------------------------------------------------
// 2. Insert Cleartext-Password & Simultaneous-Use
// ----------------------------------------------------------------------
$stmtInsert = $pdo->prepare("
    INSERT INTO {$DB_RADIUS}.radcheck (username, attribute, op, value)
    VALUES (:username, :attribute, ':=', :value)
");

$totalInserted = 0;

if ($apply) {
    $pdo->beginTransaction();
}

try {
    foreach ($missing as $row) {
        $username = $row['pppoe_username'];

        printf(
            "[INSERT] %-30s status=%-9s -> Cleartext-Password='%s', Simultaneous-Use='1'\n",
            $username,
            $row['status'],
            $DEFAULT_PASSWORD
        );

        if ($apply) {
            $stmtInsert->execute([
                ':username'  => $username,
                ':attribute' => 'Cleartext-Password',
                ':value'     => $DEFAULT_PASSWORD,
            ]);

            $stmtInsert->execute([
                ':username'  => $username,
                ':attribute' => 'Simultaneous-Use',
                ':value'     => '1',
            ]);
        }

        $totalInserted++;
    }

    if ($apply) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($apply) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "\nError: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\n=== Ringkasan ===\n";
echo "Customer diproses : {$totalInserted}\n";
echo "Total row diinsert: " . ($totalInserted * 2) . " (2 attribute per customer)\n";

if (!$apply) {
    echo "\n(Dry-run) Tidak ada perubahan dilakukan. Jalankan dengan --apply untuk eksekusi.\n";
} else {
    echo "\nSelesai. Data sudah ditambahkan ke radcheck.\n";
}