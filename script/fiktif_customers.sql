INSERT IGNORE INTO cron_schedules (name, task_type, schedule_time, schedule_days, is_active) 
VALUES ("Handle Fiktif Customers", "fiktif_customers", "00:00:01", "daily", 1);

CREATE TABLE IF NOT EXISTS fiktif_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO fiktif_customers (customer_id)
SELECT id
FROM customers
WHERE pppoe_username LIKE '%@ans%' 
  AND status = 'isolated'
  AND NOT EXISTS ( 
      SELECT 1 FROM invoices inv WHERE inv.customer_id = customers.id 
  )
  AND address = 'Area Kasemen'
ORDER BY created_at
LIMIT 1215;