-- Upgrade for user photos and status, product photo galleries, reviews,
-- and the MySQL VIEWS used for order transaction details. Does NOT delete any data.
-- Run 001 and 002 first. Safe to run more than once.
--
-- How to run: phpMyAdmin > select fragrance_store > Import > choose this file > Go
-- Written for MariaDB (the database that ships with XAMPP).

USE fragrance_store;

-- 1. users: a profile photo and an active/inactive status
--    (an admin deactivates an account instead of deleting it, so its orders are kept)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS img_path VARCHAR(255) NULL AFTER password,
    ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER role;

-- 2. extra photos of a product (the main photo stays in product.img_path)
CREATE TABLE IF NOT EXISTS product_images (
    image_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    product_id INT NOT NULL,
    img_path VARCHAR(255) NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES product (product_id) ON DELETE CASCADE
);

-- 3. product reviews: one review per customer per product
CREATE TABLE IF NOT EXISTS review (
    review_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    comment VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE (product_id, user_id),
    CHECK (rating BETWEEN 1 AND 5),
    FOREIGN KEY (product_id) REFERENCES product (product_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (user_id)
);

-- 4. views for the order transaction details

-- one row per product in an order, with its subtotal
CREATE OR REPLACE VIEW order_details_view AS
SELECT oi.order_item_id, oi.order_id, o.user_id, o.status, o.created_at AS order_date,
       oi.product_id, p.name AS product_name, p.brand, p.size, p.unit, p.img_path,
       oi.quantity, oi.price, oi.quantity * oi.price AS subtotal
FROM order_items oi
INNER JOIN orders o USING (order_id)
INNER JOIN product p USING (product_id);

-- the number of items and the grand total of each order
CREATE OR REPLACE VIEW order_totals_view AS
SELECT order_id,
       COUNT(*) AS item_count,
       SUM(quantity) AS total_units,
       SUM(quantity * price) AS grand_total
FROM order_items
GROUP BY order_id;

-- one row per order: the order, its customer and its totals
CREATE OR REPLACE VIEW order_summary_view AS
SELECT o.order_id, o.user_id, u.name AS customer_name, u.email AS customer_email,
       o.status, o.shipping_address, o.payment_method, o.payment_status, o.paid_at,
       o.created_at, o.updated_at,
       COALESCE(t.item_count, 0) AS item_count,
       COALESCE(t.total_units, 0) AS total_units,
       COALESCE(t.grand_total, 0) AS grand_total
FROM orders o
INNER JOIN users u USING (user_id)
LEFT JOIN order_totals_view t USING (order_id);
