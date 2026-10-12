-- Upgrade an existing fragrance_store database to the order status and payment version
-- WITHOUT deleting any data. Safe to run more than once.
--
-- Use this only if you already imported an older database.sql and want to keep its data.
-- For a fresh install, import database.sql instead.
--
-- How to run: phpMyAdmin > select fragrance_store > Import > choose this file > Go
-- Written for MariaDB (the database that ships with XAMPP).

USE fragrance_store;

-- 1. orders: new status flow, payment columns and a last-updated time
ALTER TABLE orders
    MODIFY status ENUM('pending', 'processing', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NOT NULL DEFAULT 'cod' AFTER shipping_address,
    ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid' AFTER payment_method,
    ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER payment_status,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL AFTER created_at;

-- the old checkout saved every order as 'completed', so treat those as already paid
UPDATE orders
SET payment_status = 'paid', paid_at = created_at
WHERE status = 'completed' AND payment_status = 'unpaid' AND paid_at IS NULL;

-- 2. history of every status change
CREATE TABLE IF NOT EXISTS order_status_history (
    history_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'completed', 'cancelled') NOT NULL,
    note VARCHAR(255) NULL,
    changed_by INT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users (user_id)
);

-- give each existing order one history entry so its timeline is not empty
INSERT INTO order_status_history (order_id, status, note, changed_by, changed_at)
SELECT o.order_id, o.status, 'Recorded before status tracking was added', NULL, o.created_at
FROM orders o
WHERE NOT EXISTS (SELECT 1 FROM order_status_history h WHERE h.order_id = o.order_id);

-- 3. indexes for the storefront filters and sorting
CREATE INDEX IF NOT EXISTS idx_product_brand ON product (brand);
CREATE INDEX IF NOT EXISTS idx_product_scent ON product (scent_type);
CREATE INDEX IF NOT EXISTS idx_product_price ON product (sell_price);
