<?php
/**
 * sync_radusergroup.php
 *
 * Sinkronisasi radius_db.radusergroup.groupname berdasarkan
 * ans_radius.customers.status (active/isolated) dan mapping
 * ans_radius.packages (profile_normal / profile_isolir).
 *
 * Logic:
 *  - status = 'active'   -> groupname harus = packages.profile_normal
 *  - status = 'isolated' -> groupname harus = packages.profile_isolir
 *
 *  - Jika row radusergroup sudah ada tapi groupname salah -> UPDATE (replace)
 *  - Jika row radusergroup belum ada sama sekali -> INSERT baru
 *  - Customer dengan package_id tidak valid / packages.id IS NULL -> dilewati,
 *    ditampilkan sebagai warning untuk dicek manual.
 *
 * Jalankan via CLI:
 *   php sync_radusergroup.php            -> dry-run (tampilkan saja, tidak update)
 *   php sync_radusergroup.php --apply     -> eksekusi update/insert sungguhan
 *   php sync_radusergroup.php --apply --no-backup  -> apply tanpa backup tabel
 */

// ====== KONFIGURASI DB ======
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = ''; // sesuaikan password
// Nama database
$DB_ANS_RADIUS = 'ans_radius';
$DB_RADIUS     = 'radius_db';
// =============================

$apply     = in_array('--apply', $argv);
$noBackup  = in_array('--no-backup', $argv);

try {
    // Koneksi tanpa dbname spesifik, akses lintas database via prefix db.table
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
    ? "Mode: APPLY (data akan diupdate/insert)\n\n"
    : "Mode: DRY-RUN (tidak ada perubahan ke database)\n\n";

// ----------------------------------------------------------------------
// 0. Backup tabel radusergroup (hanya saat --apply, kecuali --no-backup)
// ----------------------------------------------------------------------
if ($apply && !$noBackup) {
    $backupTable = "radusergroup_backup_" . date('Ymd_His');
    echo "Membuat backup tabel: {$DB_RADIUS}.{$backupTable} ...\n";
    $pdo->exec("CREATE TABLE {$DB_RADIUS}.`{$backupTable}` AS SELECT * FROM {$DB_RADIUS}.radusergroup");
    echo "Backup selesai.\n\n";
}

// ----------------------------------------------------------------------
// 1. Cek customer dengan package_id tidak valid (p.id IS NULL)
// ----------------------------------------------------------------------
$sqlInvalidPackage = "
    SELECT c.id, c.pppoe_username, c.status, c.package_id
    FROM {$DB_ANS_RADIUS}.customers c
    LEFT JOIN {$DB_ANS_RADIUS}.packages p ON p.id = c.package_id
    WHERE p.id IS NULL
    ORDER BY c.id
";

$invalidPackages = $pdo->query($sqlInvalidPackage)->fetchAll();

if (!empty($invalidPackages)) {
    echo "=== PERINGATAN: Customer dengan package_id tidak valid (dilewati) ===\n";
    foreach ($invalidPackages as $row) {
        printf(
            "  id=%d | %s | status=%s | package_id=%s\n",
            $row['id'],
            $row['pppoe_username'],
            $row['status'],
            $row['package_id'] ?? 'NULL'
        );
    }
    echo "Total: " . count($invalidPackages) . " customer dilewati.\n\n";
}

// ----------------------------------------------------------------------
// 2. Ambil semua mismatch (existing row salah groupname) + yang belum ada row
// ----------------------------------------------------------------------
$sqlMismatch = "
    SELECT
        c.id,
        c.pppoe_username,
        c.status,
        c.package_id,
        p.profile_normal,
        p.profile_isolir,
        r.username AS radius_username,
        r.groupname AS current_radius_group,
        CASE
            WHEN c.status = 'active'   THEN p.profile_normal
            WHEN c.status = 'isolated' THEN p.profile_isolir
        END AS expected_radius_group
    FROM {$DB_ANS_RADIUS}.customers c
    INNER JOIN {$DB_ANS_RADIUS}.packages p ON p.id = c.package_id
    LEFT JOIN {$DB_RADIUS}.radusergroup r
        ON r.username COLLATE utf8mb4_unicode_ci = c.pppoe_username COLLATE utf8mb4_unicode_ci
    WHERE r.username IS NULL
       OR r.groupname COLLATE utf8mb4_unicode_ci <> CASE
            WHEN c.status = 'active'   THEN p.profile_normal
            WHEN c.status = 'isolated' THEN p.profile_isolir
          END COLLATE utf8mb4_unicode_ci
    ORDER BY c.id
";

$mismatches = $pdo->query($sqlMismatch)->fetchAll();

echo "Total mismatch ditemukan: " . count($mismatches) . "\n\n";

// ----------------------------------------------------------------------
// 3. Proses tiap mismatch: UPDATE atau INSERT
// ----------------------------------------------------------------------
$stmtUpdate = $pdo->prepare("
    UPDATE {$DB_RADIUS}.radusergroup
    SET groupname = :groupname
    WHERE username COLLATE utf8mb4_unicode_ci = :username COLLATE utf8mb4_unicode_ci
");

$stmtInsert = $pdo->prepare("
    INSERT INTO {$DB_RADIUS}.radusergroup (username, groupname, priority)
    VALUES (:username, :groupname, 1)
");

$totalUpdate = 0;
$totalInsert = 0;

if ($apply) {
    $pdo->beginTransaction();
}

try {
    foreach ($mismatches as $row) {
        $username = $row['pppoe_username'];
        $expected = $row['expected_radius_group'];

        if ($row['radius_username'] === null) {
            // belum ada row -> INSERT
            printf(
                "[INSERT] %-30s status=%-9s -> groupname='%s'\n",
                $username,
                $row['status'],
                $expected
            );

            if ($apply) {
                $stmtInsert->execute([
                    ':username'  => $username,
                    ':groupname' => $expected,
                ]);
            }
            $totalInsert++;
        } else {
            // sudah ada row tapi groupname salah -> UPDATE
            printf(
                "[UPDATE] %-30s status=%-9s : '%s' -> '%s'\n",
                $username,
                $row['status'],
                $row['current_radius_group'],
                $expected
            );

            if ($apply) {
                $stmtUpdate->execute([
                    ':username'  => $username,
                    ':groupname' => $expected,
                ]);
            }
            $totalUpdate++;
        }
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
echo "Update : {$totalUpdate}\n";
echo "Insert : {$totalInsert}\n";
echo "Dilewati (package_id invalid): " . count($invalidPackages) . "\n";

if (!$apply) {
    echo "\n(Dry-run) Tidak ada perubahan dilakukan. Jalankan dengan --apply untuk eksekusi.\n";
} else {
    echo "\nSelesai. Perubahan sudah diterapkan ke database.\n";
}