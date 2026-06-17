<?php

// =====================
// DB CONFIG
// =====================
include '../includes/config.php';

$host = DB_HOST;
$db = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = 'utf8';

date_default_timezone_set('Asia/Jakarta');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage() . "\n");
}

// =====================
// MODE: dry-run (default) vs apply
//   php fix_duplicate_invoices.php           -> dry-run
//   php fix_duplicate_invoices.php --apply    -> eksekusi delete sungguhan
// =====================
$apply = in_array('--apply', $argv);

echo $apply
    ? "Mode: APPLY (data akan dihapus)\n\n"
    : "Mode: DRY-RUN (tidak ada perubahan ke database)\n\n";

// =====================
// 1. Ambil semua invoice yang termasuk grup duplikat
//    Duplikat ditentukan dari: customer_id + bulan&tahun due_date (YYYY-MM)
// =====================
$sqlDuplicates = "
    SELECT 
        i.id,
        i.invoice_number,
        i.customer_id,
        c.pppoe_username,
        i.due_date,
        DATE_FORMAT(i.due_date, '%Y-%m') AS due_month,
        i.status,
        i.paid_at,
        i.amount
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    WHERE (i.customer_id, DATE_FORMAT(i.due_date, '%Y-%m')) IN (
        SELECT customer_id, DATE_FORMAT(due_date, '%Y-%m')
        FROM invoices
        GROUP BY customer_id, DATE_FORMAT(due_date, '%Y-%m')
        HAVING COUNT(*) > 1
    )
    ORDER BY i.customer_id, due_month, i.due_date, i.id
";

$rows = $pdo->query($sqlDuplicates)->fetchAll();

if (!$rows) {
    die("Tidak ada invoice duplikat ditemukan.\n");
}

// =====================
// 2. Group by customer_id + due_month (YYYY-MM)
// =====================
$groups = [];
foreach ($rows as $row) {
    $key = $row['customer_id'] . '|' . $row['due_month'];
    $groups[$key][] = $row;
}

echo "Total grup duplikat (per customer + bulan due_date): " . count($groups) . "\n\n";

// =====================
// 3. Proses tiap grup
// =====================
$toDelete       = [];   // invoice id yang akan dihapus
$keepInfo       = [];   // info invoice yang di-keep (untuk log)
$needsReview    = [];   // grup yang tidak bisa ditentukan otomatis

foreach ($groups as $key => $invs) {

    $paidCandidates = array_filter($invs, function ($r) {
        return strtolower($r['status']) === 'paid' && !empty($r['paid_at']);
    });

    if (count($paidCandidates) === 1) {
        // ambil satu-satunya kandidat paid+paid_at -> KEEP
        $keep = array_values($paidCandidates)[0];
        $keepInfo[] = $keep;

        foreach ($invs as $r) {
            if ($r['id'] !== $keep['id']) {
                $toDelete[] = $r;
            }
        }
    } else {
        // 0 atau >1 kandidat paid+paid_at -> butuh review manual, skip
        $needsReview[$key] = $invs;
    }
}

// =====================
// 4. Tampilkan ringkasan KEEP / DELETE
// =====================
echo "=== Invoice yang akan DI-KEEP ===\n";
foreach ($keepInfo as $r) {
    printf(
        "[KEEP]   %-20s id=%-6d %s due=%s (%s) status=%s paid_at=%s\n",
        $r['pppoe_username'],
        $r['id'],
        $r['invoice_number'],
        $r['due_date'],
        $r['due_month'],
        $r['status'],
        $r['paid_at'] ?? 'NULL'
    );
}

echo "\n=== Invoice yang akan DIHAPUS ===\n";
foreach ($toDelete as $r) {
    printf(
        "[DELETE] %-20s id=%-6d %s due=%s (%s) status=%s paid_at=%s\n",
        $r['pppoe_username'],
        $r['id'],
        $r['invoice_number'],
        $r['due_date'],
        $r['due_month'],
        $r['status'],
        $r['paid_at'] ?? 'NULL'
    );
}

// =====================
// 5. Tampilkan grup yang butuh review manual
// =====================
if (!empty($needsReview)) {
    echo "\n=== Grup BUTUH REVIEW MANUAL (dilewati) ===\n";
    foreach ($needsReview as $key => $invs) {
        [$cid, $dueMonth] = explode('|', $key);
        $username = $invs[0]['pppoe_username'];

        printf("Customer %-20s (id=%s) due_month=%s:\n", $username, $cid, $dueMonth);
        foreach ($invs as $r) {
            printf(
                "    id=%-6d %s due=%s status=%s paid_at=%s\n",
                $r['id'],
                $r['invoice_number'],
                $r['due_date'],
                $r['status'],
                $r['paid_at'] ?? 'NULL'
            );
        }
    }
}

// =====================
// 6. Cek apakah invoice yang akan dihapus punya referensi di fiktif_invoices
// =====================
$deleteIds = array_column($toDelete, 'id');
$fiktifRefs = [];

if (!empty($deleteIds)) {
    $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
    $stmtFiktif = $pdo->prepare("
        SELECT fi.*, c.pppoe_username
        FROM fiktif_invoices fi
        JOIN invoices i ON i.id = fi.invoice_id
        JOIN customers c ON c.id = i.customer_id
        WHERE fi.invoice_id IN ($placeholders)
    ");
    $stmtFiktif->execute($deleteIds);
    $fiktifRefs = $stmtFiktif->fetchAll();

    if (!empty($fiktifRefs)) {
        echo "\n=== Referensi di fiktif_invoices (akan ikut dihapus) ===\n";
        foreach ($fiktifRefs as $r) {
            printf(
                "[DELETE-FI] %-20s fiktif_invoices.id=%-6d invoice_id=%d\n",
                $r['pppoe_username'],
                $r['id'],
                $r['invoice_id']
            );
        }
    }
}

// =====================
// 7. Ringkasan
// =====================
echo "\n====================\n";
echo "RINGKASAN\n";
echo "Total grup duplikat        : " . count($groups) . "\n";
echo "Grup ter-resolve (auto)     : " . count($keepInfo) . "\n";
echo "Grup butuh review manual    : " . count($needsReview) . "\n";
echo "Invoice akan dihapus        : " . count($toDelete) . "\n";
echo "Referensi fiktif_invoices   : " . count($fiktifRefs) . "\n";

if (!$apply) {
    echo "\n(Dry-run) Tidak ada perubahan dilakukan. Jalankan dengan --apply untuk eksekusi.\n";
    exit(0);
}

if (empty($toDelete)) {
    echo "\nTidak ada yang perlu dihapus.\n";
    exit(0);
}

// =====================
// 8. APPLY: backup, hapus fiktif_invoices, hapus invoices
// =====================
$pdo->beginTransaction();

try {
    // 8a. Backup invoice yang akan dihapus
    $backupTable = "invoices_backup_dup_" . date('Ymd_His');
    $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));

    $pdo->exec("CREATE TABLE `{$backupTable}` LIKE invoices");
    $stmtBackup = $pdo->prepare("
        INSERT INTO `{$backupTable}`
        SELECT * FROM invoices WHERE id IN ($placeholders)
    ");
    $stmtBackup->execute($deleteIds);

    echo "\nBackup invoice yang dihapus -> tabel `{$backupTable}`\n";

    // 8b. Hapus referensi di fiktif_invoices
    if (!empty($fiktifRefs)) {
        $stmtDelFi = $pdo->prepare("
            DELETE FROM fiktif_invoices WHERE invoice_id IN ($placeholders)
        ");
        $stmtDelFi->execute($deleteIds);
        echo "Hapus " . count($fiktifRefs) . " row dari fiktif_invoices\n";
    }

    // 8c. Hapus invoice duplikat
    $stmtDelInv = $pdo->prepare("
        DELETE FROM invoices WHERE id IN ($placeholders)
    ");
    $stmtDelInv->execute($deleteIds);
    echo "Hapus " . count($deleteIds) . " row dari invoices\n";

    $pdo->commit();

    echo "\nSELESAI. Total invoice dihapus: " . count($deleteIds) . "\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("\nERROR: " . $e->getMessage() . "\n");
}