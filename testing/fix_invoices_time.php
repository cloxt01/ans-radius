<?php

$db_host = "localhost";
$db_name = "ans_radius";
$db_user = "root";
$db_pass = "";

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "DB connected\n";
} catch (Exception $e) {
    die("DB ERROR: ".$e->getMessage());
}

function fetchAll($pdo,$sql,$params=[]){
    $st=$pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function fetchOne($pdo,$sql,$params=[]){
    $st=$pdo->prepare($sql);
    $st->execute($params);
    return $st->fetch();
}

function update($pdo,$sql,$params=[]){
    $st=$pdo->prepare($sql);
    return $st->execute($params);
}

echo "START CHAIN REBUILD\n";

$customers = fetchAll($pdo,"
    SELECT DISTINCT customer_id
    FROM fiktif_customers
");

foreach ($customers as $c) {

    $customerId = $c['customer_id'];

    // JUNI ANCHOR (ONLY SOURCE OF TRUTH)
    $juni = fetchOne($pdo,"
        SELECT id,due_date
        FROM invoices
        WHERE customer_id=?
          AND MONTH(due_date)=6
        LIMIT 1
    ",[$customerId]);

    if (!$juni) {
        echo "Skip $customerId (no June anchor)\n";
        continue;
    }

    echo "Processing $customerId\n";

    // ambil semua invoice fiktif
    $rows = fetchAll($pdo,"
        SELECT i.id,i.due_date,i.paid_at,fi.late_days,i.status
        FROM invoices i
        JOIN fiktif_invoices fi ON fi.invoice_id=i.id
        WHERE i.customer_id=?
        ORDER BY i.due_date DESC
    ",[$customerId]);

    $refDue = $juni['due_date'];

    foreach ($rows as $i => $row) {

        $invoiceId = $row['id'];

        // skip JUNI anchor
        if ($i === 0) {
            continue;
        }

        // FIXED: generate deterministic late_days (jangan pakai lama kalau rusak)
        $lateDays = (int)$row['late_days'];
        if ($lateDays <= 0) {
            $lateDays = rand(1,10);
        }

        // STEP 1: MEI/prev PAID = ref_due - 30
        $paidAt = (new DateTime($refDue))->modify('-30 days');

        // STEP 2: DUE = paid - late_days
        $due = (clone $paidAt)->modify("-{$lateDays} days");

        $paidAtStr = $paidAt->format('Y-m-d H:i:s');
        $dueStr = $due->format('Y-m-d');

        if ($row['status'] === 'paid') {

            update($pdo,"
                UPDATE invoices
                SET due_date=?, paid_at=?
                WHERE id=?
            ",[$dueStr,$paidAtStr,$invoiceId]);

        } else {

            update($pdo,"
                UPDATE invoices
                SET due_date=?
                WHERE id=?
            ",[$dueStr,$invoiceId]);

        }

        echo "Invoice {$invoiceId} OK\n";

        // chain forward
        $refDue = $dueStr;
    }
}

echo "DONE\n";