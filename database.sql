-- Veloura: online fragrance store
-- tables: users, customer, category, product, product_images, stock, orders, order_items, review
-- views:  order_details_view, order_totals_view (order transaction details)
--
-- how to import: phpMyAdmin > Import > choose this file > Go
-- WARNING: importing this file again deletes all data in these tables.
--
-- accounts that come with the database:
--   admin    admin@veloura.test     / admin123
--   customer customer@veloura.test  / customer123

CREATE DATABASE IF NOT EXISTS fragrance_store;
USE fragrance_store;

DROP VIEW IF EXISTS order_totals_view;
DROP VIEW IF EXISTS order_details_view;

-- tables and views from the older version of this project
DROP VIEW IF EXISTS order_summary_view;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS cart;

DROP TABLE IF EXISTS review;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS coupon;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS stock;
DROP TABLE IF EXISTS product;
DROP TABLE IF EXISTS category;
DROP TABLE IF EXISTS customer;
DROP TABLE IF EXISTS users;

-- customers and admins
-- img_path: profile photo (user/images/...)
CREATE TABLE users (
    user_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    img_path VARCHAR(255) NULL,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL
);

-- delivery details, one row per user (filled in on the profile page)
CREATE TABLE customer (
    customer_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    addressline VARCHAR(255) NOT NULL,
    town VARCHAR(100) NOT NULL,
    zipcode VARCHAR(10) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
);

CREATE TABLE category (
    category_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- img_path: the main photo (product/images/...)
CREATE TABLE product (
    product_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    size VARCHAR(30) NOT NULL,
    description VARCHAR(255) NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL,
    sell_price DECIMAL(10,2) NOT NULL,
    img_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (category_id) REFERENCES category (category_id)
);

-- more photos of a product, many per product
CREATE TABLE product_images (
    image_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    product_id INT NOT NULL,
    img_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES product (product_id) ON DELETE CASCADE
);

CREATE TABLE stock (
    product_id INT PRIMARY KEY NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES product (product_id)
);

-- status: pending -> processing -> shipped -> completed, or cancelled
CREATE TABLE orders (
    order_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    date_placed DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (user_id)
);

-- the products in each order. price is the selling price at the time of the order
CREATE TABLE order_items (
    order_item_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders (order_id),
    FOREIGN KEY (product_id) REFERENCES product (product_id)
);

-- one review per customer per product
CREATE TABLE review (
    review_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL,
    comment VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE (product_id, user_id),
    FOREIGN KEY (product_id) REFERENCES product (product_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (user_id)
);

-- one row per product in an order: the customer, the order and the product, with its subtotal
CREATE VIEW order_details_view AS
SELECT o.order_id, o.user_id, u.name, u.email, c.addressline, c.town, c.zipcode, c.phone,
       o.status, o.date_placed, p.product_id, p.name AS product_name, p.brand, p.size,
       oi.quantity, oi.price, oi.quantity * oi.price AS subtotal
FROM orders o
INNER JOIN users u ON u.user_id = o.user_id
LEFT JOIN customer c ON c.user_id = o.user_id
INNER JOIN order_items oi ON oi.order_id = o.order_id
INNER JOIN product p ON p.product_id = oi.product_id;

-- one row per order, with its grand total
CREATE VIEW order_totals_view AS
SELECT o.order_id, o.user_id, u.name, u.email, o.status, o.date_placed,
       SUM(oi.quantity) AS items, SUM(oi.quantity * oi.price) AS total
FROM orders o
INNER JOIN users u ON u.user_id = o.user_id
INNER JOIN order_items oi ON oi.order_id = o.order_id
GROUP BY o.order_id, o.user_id, u.name, u.email, o.status, o.date_placed;

INSERT INTO users (user_id, name, email, password, role, status, created_at) VALUES
(1, 'Store Admin', 'admin@veloura.test', '$2y$10$SjZh2EyG8FJAFWVTnCOFlucQPci7cYpqx.urgWtL.Xv2IaZwH7mi2', 'admin', 'active', NOW()),
(2, 'Juan Dela Cruz', 'customer@veloura.test', '$2y$10$/uZECPDeaLq1aZg/YIOSjOg72Ypl.BC2TRcPlPTaC3jpcaoBlYm/a', 'customer', 'active', NOW());

INSERT INTO customer (user_id, addressline, town, zipcode, phone) VALUES
(2, '123 Rizal St., Brgy. San Antonio', 'Pasig City', '1600', '09171234567');

INSERT INTO category (category_id, name) VALUES
(1, 'Perfumes'),
(2, 'Perfume Oils'),
(3, 'Scented Candles'),
(4, 'Reed Diffusers'),
(5, 'Aroma Diffusers'),
(6, 'Room Sprays'),
(7, 'Wax Melts'),
(8, 'Car Fragrances'),
(9, 'Essential Oils');

-- sample products (photos are in product/images, see product/images/CREDITS.txt)
INSERT INTO product (product_id, category_id, name, brand, size, description, cost_price, sell_price, img_path) VALUES
(1, 1, 'No. 5 Eau de Parfum', 'Chanel', '100 ml', 'Classic floral with rose and jasmine.', 7200.00, 9950.00, 'images/sample_chanel_no5.jpg'),
(2, 1, 'Sauvage', 'Dior', '100 ml', 'Fresh and spicy scent with bergamot and pepper.', 5300.00, 7450.00, 'images/sample_dior_sauvage.jpg'),
(3, 1, 'Acqua di Gio', 'Giorgio Armani', '100 ml', 'Light marine scent with citrus notes.', 4400.00, 6300.00, 'images/sample_acqua_di_gio.jpg'),
(4, 1, '1 Million', 'Paco Rabanne', '100 ml', 'Warm spicy scent with cinnamon and leather.', 3900.00, 5600.00, 'images/sample_1_million.jpg'),
(5, 1, 'Eros', 'Versace', '100 ml', 'Mint, green apple and vanilla.', 3400.00, 4950.00, 'images/sample_versace_eros.jpg'),
(6, 1, 'Coco Mademoiselle', 'Chanel', '100 ml', 'Fresh floral with orange and patchouli.', 6900.00, 9450.00, 'images/sample_coco_mademoiselle.jpg'),
(7, 1, 'Le Male', 'Jean Paul Gaultier', '125 ml', 'Lavender, mint and vanilla.', 3600.00, 5200.00, 'images/sample_le_male.jpg'),
(8, 2, 'Oud Attar Roll-On Oil', 'Aroma House', '6 ml', 'Alcohol-free perfume oil in a roll-on bottle.', 120.00, 249.00, 'images/sample_attar_oil.jpg'),
(9, 3, 'Mahogany Teakwood Candle', 'White Barn', '200 g', 'Glass jar candle with warm woody notes.', 650.00, 1150.00, 'images/sample_scented_candle.jpg'),
(10, 4, 'Lavender Reed Diffuser', 'Aroma House', '100 ml', 'Lavender oil with reed sticks, lasts up to 2 months.', 280.00, 499.00, 'images/sample_reed_diffuser.jpg'),
(11, 5, 'Ultrasonic Aroma Diffuser', 'Aroma House', '1 pc', '100 ml water tank with color-changing light.', 550.00, 999.00, 'images/sample_aroma_diffuser.jpg'),
(12, 6, 'Freshmatic Room Spray', 'Air Wick', '250 ml', 'Automatic spray dispenser with refill can.', 420.00, 699.00, 'images/sample_room_spray.jpg'),
(13, 7, 'Vanilla & Spiced Apple Wax Melts', 'Aroma House', '80 g', 'Soy wax melts for use with a wax warmer.', 120.00, 199.00, 'images/sample_wax_melts.jpg'),
(14, 8, 'Strawberry Car Fragrance', 'Aroma House', '8 ml', 'Hanging glass bottle, lasts about 30 days.', 60.00, 129.00, 'images/sample_car_fragrance.jpg'),
(15, 9, 'Essential Oil Starter Set', 'Young Living', '11 pcs', 'Set of 11 x 5 ml oils including lavender and lemon.', 2200.00, 3499.00, 'images/sample_essential_oils.jpg');

INSERT INTO stock (product_id, quantity) VALUES
(1, 10), (2, 15), (3, 12), (4, 20), (5, 18), (6, 8), (7, 3), (8, 30),
(9, 12), (10, 20), (11, 6), (12, 14), (13, 40), (14, 50), (15, 5);
