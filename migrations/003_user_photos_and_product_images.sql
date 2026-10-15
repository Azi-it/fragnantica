-- Upgrade for MP1 (several photos per product) and MP2 (user photos, active/inactive accounts),
-- WITHOUT deleting any data. Run 001 and 002 first. Safe to run more than once.
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
