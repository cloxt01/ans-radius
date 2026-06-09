-- ============================================
-- MIGRASI isolation_date dari INT ke DATE
-- (Optimized untuk data dengan paid_at NULL)
-- ============================================

-- STEP 1: Backup data
CREATE TABLE customers_backup_20260602 AS SELECT * FROM customers;

-- STEP 2: Tambah kolom temporary
ALTER TABLE customers ADD COLUMN isolation_date_new DATE NULL;

-- STEP 3: Migrasi untuk customer yang punya invoice 'paid'
-- Jika paid_at ada → paid_at + 1 bulan
-- Jika paid_at NULL → due_date + 1 bulan
UPDATE customers c
SET c.isolation_date_new = (
    SELECT 
        CASE 
            WHEN MAX(i.paid_at) IS NOT NULL AND MAX(i.paid_at) != '0000-00-00 00:00:00' 
                THEN DATE_ADD(DATE(MAX(i.paid_at)), INTERVAL 1 MONTH)
            ELSE DATE_ADD(DATE(MAX(i.due_date)), INTERVAL 1 MONTH)
        END
    FROM invoices i
    WHERE i.customer_id = c.id 
    AND i.status = 'paid'
    AND i.due_date IS NOT NULL
    AND i.due_date != '0000-00-00'
)
WHERE EXISTS (
    SELECT 1 FROM invoices i 
    WHERE i.customer_id = c.id 
    AND i.status = 'paid'
);

-- STEP 4: Untuk customer tanpa invoice 'paid', ambil due_date invoice terbaru
UPDATE customers c
SET c.isolation_date_new = (
    SELECT DATE(i.due_date)
    FROM invoices i
    WHERE i.customer_id = c.id 
    AND i.due_date IS NOT NULL
    AND i.due_date != '0000-00-00'
    ORDER BY i.created_at DESC, i.id DESC
    LIMIT 1
)
WHERE c.isolation_date_new IS NULL
AND EXISTS (
    SELECT 1 FROM invoices i 
    WHERE i.customer_id = c.id 
    AND i.due_date IS NOT NULL
    AND i.due_date != '0000-00-00'
);

-- STEP 5: Customer tanpa invoice sama sekali (pakai isolation_date INT lama)
UPDATE customers 
SET isolation_date_new = DATE_ADD(
    CONCAT(YEAR(NOW()), '-', MONTH(NOW()), '-', isolation_date),
    INTERVAL 1 MONTH
)
WHERE isolation_date_new IS NULL 
AND isolation_date IS NOT NULL 
AND isolation_date > 0 
AND isolation_date <= 31;

-- STEP 6: Default terakhir (tanggal 20 bulan depan)
UPDATE customers 
SET isolation_date_new = DATE_ADD(CONCAT(YEAR(NOW()), '-', MONTH(NOW()), '-20'), INTERVAL 1 MONTH)
WHERE isolation_date_new IS NULL;

-- STEP 7: Hapus kolom lama dan rename
ALTER TABLE customers DROP COLUMN isolation_date;
ALTER TABLE customers CHANGE COLUMN isolation_date_new isolation_date DATE NOT NULL;

-- STEP 8: Tambah index
ALTER TABLE customers ADD INDEX idx_isolation_date (isolation_date);

-- STEP 9: Verifikasi hasil
SELECT 
    c.id,
    c.name,
    c.isolation_date,
    CASE 
        WHEN c.isolation_date < CURDATE() THEN 'SUDAH LEWAT'
        WHEN c.isolation_date = CURDATE() THEN 'HARI INI'
        ELSE 'BELUM LEWAT'
    END AS status_isolasi,
    -- Cek dari mana asalnya
    (SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.id AND i.status = 'paid') AS total_paid,
    (SELECT i.paid_at FROM invoices i WHERE i.customer_id = c.id AND i.status = 'paid' ORDER BY i.id DESC LIMIT 1) AS last_paid_at,
    (SELECT i.due_date FROM invoices i WHERE i.customer_id = c.id AND i.status = 'paid' ORDER BY i.id DESC LIMIT 1) AS last_due_date
FROM customers c
LIMIT 20;