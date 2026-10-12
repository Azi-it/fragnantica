-- Upgrade for password reset and the admin dashboard, WITHOUT deleting any data.
-- Run 001_order_status_and_payment.sql first. Safe to run more than once.
--
-- How to run: phpMyAdmin > select fragrance_store > Import > choose this file > Go
-- Written for MariaDB (the database that ships with XAMPP).

USE fragrance_store;

-- 1. remember the cost price of each item at checkout, so profit stays right
--    even if a product's cost changes later
ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS cost DECIMAL(10,2) NULL AFTER price;

-- older orders did not record it; use today's cost price as the best estimate
UPDATE order_items oi
INNER JOIN product p USING (product_id)
SET oi.cost = p.cost_price
WHERE oi.cost IS NULL;

-- 2. "forgot password" requests; only a hash of the emailed token is stored
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
);
