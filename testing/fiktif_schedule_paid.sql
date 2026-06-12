INSERT INTO fiktif_invoices (
    invoice_id,
    late_days,
    scheduled_paid_date,
    status
)
SELECT
    i.id,
    CASE
        WHEN i.status = 'paid'
            THEN DATEDIFF(DATE(i.paid_at), i.due_date)
        ELSE FLOOR(RAND() * 10) + 1
        END AS late_days,

    CASE
        WHEN i.status = 'paid'
            THEN DATE(i.paid_at)
    ELSE DATE_ADD(
    i.due_date,
    INTERVAL FLOOR(RAND() * 10) + 1 DAY
    )
END AS scheduled_paid_date,

    i.status
FROM invoices i
INNER JOIN fiktif_customers fc
    ON fc.customer_id = i.customer_id
LEFT JOIN fiktif_invoices fi
    ON fi.invoice_id = i.id
WHERE fi.invoice_id IS NULL
  AND i.status IN ('unpaid', 'paid');