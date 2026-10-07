<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

date_default_timezone_set('Asia/Jakarta');

$apply = in_array('--apply', $argv, true);

function repairMissingFiktifInvoices(bool $apply): void
{
    $firstDayOfMonth = date('Y-m-01');
    $lastDayOfMonth  = date('Y-m-t');

    $invoices = fetchAll(
        "SELECT
            i.id,
            i.customer_id,
            i.due_date,
            i.status
         FROM invoices i
         INNER JOIN fiktif_customers fc
            ON fc.customer_id = i.customer_id
         LEFT JOIN fiktif_invoices fi
            ON fi.invoice_id = i.id
         WHERE i.due_date BETWEEN ? AND ?
           AND i.status IN ('paid', 'unpaid')
           AND fi.invoice_id IS NULL
         ORDER BY i.id ASC",
        [
            $firstDayOfMonth,
            $lastDayOfMonth
        ]
    );

    $total = count($invoices);
    $success = 0;
    $failed = 0;

    echo "====================================\n";
    echo "Repair Missing Fiktif Invoices\n";
    echo "====================================\n";
    echo "Mode   : " . ($apply ? "APPLY / REAL" : "DRY-RUN / TEST") . "\n";
    echo "Period : {$firstDayOfMonth} s/d {$lastDayOfMonth}\n";
    echo "Found  : {$total}\n";
    echo "====================================\n\n";

    if ($total === 0) {
        echo "Tidak ada fiktif_invoices yang perlu diperbaiki.\n";
        return;
    }

    foreach ($invoices as $invoice) {

        /*
         * Generate nilai simulasi.
         * Pada dry-run, nilai ini TIDAK disimpan.
         */
        $lateDays = generateLateDays();

        $scheduledPaidDate = generateScheduledPaidDate(
            $invoice['due_date'],
            $lateDays
        );

        echo sprintf(
            "[%s] invoice #%d | customer #%d | due=%s | late_days=%d | scheduled=%s\n",
            $apply ? 'APPLY' : 'TEST',
            $invoice['id'],
            $invoice['customer_id'],
            $invoice['due_date'],
            $lateDays,
            $scheduledPaidDate
        );

        /*
         * Dry-run:
         * hanya menampilkan kandidat.
         */
        if (!$apply) {
            $success++;
            continue;
        }

        /*
         * Apply:
         * buat record fiktif_invoices.
         *
         * Cek ulang sebelum INSERT agar lebih aman
         * apabila data berubah setelah query awal.
         */
        $exists = fetchOne(
            "SELECT id
             FROM fiktif_invoices
             WHERE invoice_id = ?
             LIMIT 1",
            [$invoice['id']]
        );

        if ($exists) {
            echo "  -> SKIP: fiktif_invoices sudah ada\n";
            continue;
        }

        $fiktifId = insert(
            'fiktif_invoices',
            [
                'invoice_id'          => $invoice['id'],
                'late_days'           => $lateDays,
                'scheduled_paid_date' => $scheduledPaidDate,
                'status'              => 'unpaid'
            ]
        );

        if (!$fiktifId) {
            $failed++;

            echo "  -> FAILED: gagal INSERT fiktif_invoices\n";

            echo "  -> OK: fiktif_invoices #{$fiktifId} dibuat\n";
            continue;
        }

        $success++;

        echo "  -> OK: fiktif_invoices #{$fiktifId} dibuat\n";

    }

    echo "\n====================================\n";
    echo "Selesai\n";
    echo "====================================\n";
    echo "Mode     : " . ($apply ? "APPLY / REAL" : "DRY-RUN / TEST") . "\n";
    echo "Candidate: {$total}\n";

    if ($apply) {
        echo "Created  : {$success}\n";
        echo "Failed   : {$failed}\n";
    } else {
        echo "Would fix: {$success}\n";
        echo "Database : TIDAK DIUBAH\n";
    }

    echo "====================================\n";
}


/*
 * CLI only.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

repairMissingFiktifInvoices($apply);
