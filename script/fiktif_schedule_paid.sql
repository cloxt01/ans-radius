DROP TEMPORARY TABLE IF EXISTS tmp_fiktif_invoices;

CREATE TEMPORARY TABLE tmp_fiktif_invoices AS
SELECT
    i.id AS invoice_id,
    i.due_date,
    i.paid_at,
    i.status,
    CASE
        WHEN i.status = 'paid'
            THEN DATEDIFF(DATE(i.paid_at), i.due_date)
        ELSE FLOOR(RAND() * 5) + 1
        END AS late_days
FROM invoices i
         INNER JOIN fiktif_customers fc
                    ON fc.customer_id = i.customer_id
         LEFT JOIN fiktif_invoices fi
                   ON fi.invoice_id = i.id
WHERE fi.invoice_id IS NULL
  AND i.status IN ('unpaid','paid');

INSERT INTO fiktif_invoices (
    invoice_id,
    late_days,
    scheduled_paid_date,
    status
)
SELECT
    invoice_id,
    late_days,
    CASE
        WHEN status = 'paid'
            THEN DATE(paid_at)
    ELSE DATE_ADD(due_date, INTERVAL late_days DAY)
END AS scheduled_paid_date,
    status
FROM tmp_fiktif_invoices;

DROP TEMPORARY TABLE tmp_fiktif_invoices;