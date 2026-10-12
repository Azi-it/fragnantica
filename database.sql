-- Online Fragrance & Scent Products Store
-- Database: users, customer, category, product, stock, cart, orders, order_items
--
-- How to import: open phpMyAdmin > Import > choose this file > Go
-- WARNING: importing this file again deletes all data in these tables.
--
-- Default admin account (created at the bottom of this file):
--   email:    admin@fragrancestore.com
--   password: admin123

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS fragrance_store;
USE fragrance_store;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS stock;
DROP TABLE IF EXISTS product;
DROP TABLE IF EXISTS category;
DROP TABLE IF EXISTS customer;
DROP TABLE IF EXISTS users;

-- customers and admins
CREATE TABLE users (
    user_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at DATETIME NOT NULL
);

-- customer delivery details (one row per user, filled in on the profile page)
CREATE TABLE customer (
    customer_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    addressline VARCHAR(255) NOT NULL,
    town VARCHAR(100) NOT NULL,
    zipcode VARCHAR(10) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (user_id)
);

-- product categories (perfumes, scented candles, reed diffusers, ...)
CREATE TABLE category (
    category_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- fragrance and scent products
-- size + unit: 100 ml (perfume), 200 g (candle), 1 pcs (car fragrance)
CREATE TABLE product (
    product_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    scent_type VARCHAR(50) NOT NULL,
    size INT NOT NULL,
    unit ENUM('ml', 'g', 'pcs') NOT NULL DEFAULT 'ml',
    description VARCHAR(255) NULL,
    cost_price DECIMAL(10,2) NOT NULL,
    sell_price DECIMAL(10,2) NOT NULL,
    img_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (category_id) REFERENCES category (category_id)
);

-- how many pieces of each product are available
CREATE TABLE stock (
    product_id INT PRIMARY KEY NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES product (product_id)
);

-- shopping cart items
CREATE TABLE cart (
    cart_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users (user_id),
    FOREIGN KEY (product_id) REFERENCES product (product_id) ON DELETE CASCADE
);

-- customer orders
CREATE TABLE orders (
    order_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'completed',
    shipping_address VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (user_id)
);

-- individual items in an order (snapshot of price at checkout)
CREATE TABLE order_items (
    order_item_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES product (product_id)
);

-- default admin account (password is admin123, hashed with password_hash)
INSERT INTO users (name, email, password, role, created_at)
VALUES ('Administrator', 'admin@fragrancestore.com', '$2y$10$SIs15BU7A1.aVRjndlosaOEif6SkHizhqse5v90PqKeYel2Y/W8YC', 'admin', now());

-- categories
INSERT INTO category (category_id, name) VALUES
(1, 'Perfumes'),
(2, 'Perfume Oils'),
(3, 'Fragrance Oils'),
(4, 'Scented Candles'),
(5, 'Reed Diffusers'),
(6, 'Aroma Diffusers'),
(7, 'Room Sprays'),
(8, 'Linen Sprays'),
(9, 'Wax Melts'),
(10, 'Car Fragrances'),
(11, 'Essential Oils'),
(12, 'Other Fragrance Products');

-- sample products (photos are in product/images, see product/images/CREDITS.txt)
INSERT INTO product (product_id, category_id, name, brand, scent_type, size, unit, description, cost_price, sell_price, img_path) VALUES
(1, 1, 'No. 5 Eau de Parfum', 'Chanel', 'Floral', 100, 'ml', 'Classic aldehydic floral with rose and jasmine.', 7200.00, 9950.00, 'images/sample_chanel_no5.jpg'),
(2, 1, 'Sauvage', 'Dior', 'Fresh', 100, 'ml', 'Fresh and spicy scent with bergamot and pepper.', 5300.00, 7450.00, 'images/sample_dior_sauvage.jpg'),
(3, 1, 'Acqua di Giò', 'Giorgio Armani', 'Aquatic', 100, 'ml', 'Light marine scent with citrus notes.', 4400.00, 6300.00, 'images/sample_acqua_di_gio.jpg'),
(4, 1, '1 Million', 'Paco Rabanne', 'Spicy', 100, 'ml', 'Warm spicy scent with cinnamon and leather.', 3900.00, 5600.00, 'images/sample_1_million.jpg'),
(5, 1, 'Eros', 'Versace', 'Fresh', 100, 'ml', 'Mint, green apple and vanilla.', 3400.00, 4950.00, 'images/sample_versace_eros.jpg'),
(6, 1, 'Coco Mademoiselle', 'Chanel', 'Floral', 100, 'ml', 'Fresh oriental floral with orange and patchouli.', 6900.00, 9450.00, 'images/sample_coco_mademoiselle.jpg'),
(7, 1, 'Le Male', 'Jean Paul Gaultier', 'Oriental', 125, 'ml', 'Lavender, mint and vanilla.', 3600.00, 5200.00, 'images/sample_le_male.jpg'),
(8, 2, 'Oud Attar Roll-On Oil', 'Aroma House', 'Woody', 6, 'ml', 'Alcohol-free concentrated perfume oil in a roll-on bottle.', 120.00, 249.00, 'images/sample_attar_oil.jpg'),
(9, 4, 'Mahogany Teakwood Scented Candle', 'White Barn', 'Woody', 200, 'g', 'Glass jar candle with warm woody notes, burn time about 45 hours.', 650.00, 1150.00, 'images/sample_scented_candle.jpg'),
(10, 5, 'Lavender Reed Diffuser', 'Aroma House', 'Herbal', 100, 'ml', 'Lavender oil with natural reed sticks, lasts up to 2 months.', 280.00, 499.00, 'images/sample_reed_diffuser.jpg'),
(11, 6, 'Ultrasonic Aroma Diffuser', 'Aroma House', 'Fresh', 1, 'pcs', '100 ml water tank with color-changing light. Use with essential oils.', 550.00, 999.00, 'images/sample_aroma_diffuser.jpg'),
(12, 7, 'Freshmatic Automatic Room Spray', 'Air Wick', 'Floral', 250, 'ml', 'Automatic spray dispenser with refill can.', 420.00, 699.00, 'images/sample_room_spray.jpg'),
(13, 9, 'Vanilla & Spiced Apple Wax Melts', 'Aroma House', 'Gourmand', 80, 'g', 'Soy wax melts for use with a wax warmer.', 120.00, 199.00, 'images/sample_wax_melts.jpg'),
(14, 10, 'Strawberry Hanging Car Fragrance', 'Aroma House', 'Fruity', 8, 'ml', 'Hanging glass bottle with wooden cap, lasts about 30 days.', 60.00, 129.00, 'images/sample_car_fragrance.jpg'),
(15, 11, 'Essential Oil Starter Set', 'Young Living', 'Herbal', 11, 'pcs', 'Set of 11 x 5 ml oils including lavender, peppermint and lemon.', 2200.00, 3499.00, 'images/sample_essential_oils.jpg');

INSERT INTO stock (product_id, quantity) VALUES
(1, 10),
(2, 15),
(3, 12),
(4, 20),
(5, 18),
(6, 8),
(7, 3),
(8, 30),
(9, 12),
(10, 20),
(11, 6),
(12, 14),
(13, 40),
(14, 50),
(15, 5);
