-- FastPass System Database Dump
-- Version: 4.2.1-STABLE
-- Build: 2026-02-07

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

-- -----------------------------------------------------
-- Core Tables
-- -----------------------------------------------------

CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    stock_count INT NOT NULL,
    CONSTRAINT c1 CHECK (stock_count >= 0)
);

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    balance DECIMAL(10, 2) NOT NULL,
    CONSTRAINT c2 CHECK (balance >= 0)
);

CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT c3 CHECK (total_amount > 0),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- -----------------------------------------------------
-- System Logic (Guard Rails)
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Production Data
-- -----------------------------------------------------

INSERT INTO products (name, price, stock_count) VALUES 
('VIP Festival Pass', 2500.00, 50),
('Standard Ticket', 1200.00, 200),
('Backstage Add-on', 500.00, 10),
('Camping Spot', 350.00, 150),
('Early Bird Special', 900.00, 0),
('Parking Pass', 150.00, 75),
('Food Voucher Bundle', 450.00, 100),
('Official Merchandise Hoodie', 650.00, 40),
('Poster Limited Edition', 200.00, 25),
('Locker Rental', 100.00, 600000);

INSERT INTO users (username, balance) VALUES 
('kalle_kodare', 3000000.00),
('berit_boss', 10000000.00),
('fattig_student', 1500000.00),
('anna_admin', 5500000.00),
('dev_tester', 1000000.00),
('festival_fanatic', 2500000.00),
('late_comer', 5000000.00),
('vip_guest_1', 50000000.00),
('early_adopter', 1200000.00),
('tech_lead', 85000000.00);

INSERT INTO orders (user_id, product_id, total_amount) VALUES 
(1, 2, 1200.00),
(4, 1, 2500.00),
(8, 1, 2500.00),
(2, 3, 500.00),
(5, 4, 350.00);

SET FOREIGN_KEY_CHECKS = 1;