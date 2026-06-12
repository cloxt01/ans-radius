INSERT INTO fiktif_invoices (
    invoice_id,
    late_days,
    scheduled_paid_date,
    status
)
SELECT
    t.id,
    t.late_days,
    CASE
        WHEN t.status = 'paid'
            THEN DATE(t.paid_at)
    ELSE DATE_ADD(t.due_date, INTERVAL t.late_days DAY)
END,
    t.status
FROM (
    SELECT
        i.id,
        i.due_date,
        i.paid_at,
        i.status,
        CASE
            WHEN i.status = 'paid'
                THEN DATEDIFF(DATE(i.paid_at), i.due_date)
            ELSE FLOOR(RAND() * 10) + 1
        END AS late_days
    FROM invoices i
    INNER JOIN fiktif_customers fc
        ON fc.customer_id = i.customer_id
    LEFT JOIN fiktif_invoices fi
        ON fi.invoice_id = i.id
    WHERE fi.invoice_id IS NULL
      AND i.status IN ('unpaid','paid')
) t;