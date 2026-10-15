-- Upgrade for MP4 (product reviews), WITHOUT deleting any data.
-- Run 001, 002 and 003 first. Safe to run more than once.
--
-- How to run: phpMyAdmin > select fragrance_store > Import > choose this file > Go
-- Written for MariaDB (the database that ships with XAMPP).

USE fragrance_store;

-- one review per customer per product.
-- only a customer with a completed order of the product may write one (checked in PHP)
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
