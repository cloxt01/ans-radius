START TRANSACTION;

-- Tampilkan sample data sebelum update
SELECT i.id, i.customer_id, i.amount, i.due_date
FROM invoices i
         INNER JOIN fiktif_customers f ON i.customer_id = f.customer_id
WHERE i.due_date BETWEEN '2026-06-01' AND '2026-06-30';

-- Lakukan update
UPDATE invoices i
    INNER JOIN fiktif_customers f ON i.customer_id = f.customer_id
    SET i.amount = 150000.00
WHERE i.due_date BETWEEN '2026-04-01' AND '2026-06-30';

-- Cek hasil update
SELECT i.id, i.customer_id, i.amount, i.due_date
FROM invoices i
         INNER JOIN fiktif_customers f ON i.customer_id = f.customer_id
WHERE i.due_date BETWEEN '2026-04-01' AND '2026-06-30'
    LIMIT 10;

-- Jika sudah benar, commit
COMMIT;
-- Jika salah, rollback
-- ROLLBACK;