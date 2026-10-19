-- Fresh — database schema (polished)
-- Import this file in phpMyAdmin, or run: mysql -u root -p < schema.sql
--
-- This is a CLEAN install — it drops any existing `fresh_grocery` database first
-- so the new tables (categories, addresses, coupons, order_status_history, etc.)
-- are guaranteed to be created with the right columns and indexes.
-- Back up any data you need before re-importing.

DROP DATABASE IF EXISTS fresh_grocery;
CREATE DATABASE fresh_grocery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fresh_grocery;

-- ---------------------------------------------------------------
-- Users (customers + admins)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
);

-- Demo accounts
-- Customer -> customer@demo.com / demo123
-- Admin    -> admin@demo.com    / admin123
-- (passwords below are already hashed with PHP's password_hash())
INSERT INTO users (name, email, password, role) VALUES
('Demo Customer', 'customer@demo.com', '$2y$10$yh19LjtE7RLUCa7BMUmW9.QIVqWFbyYDnrGd8.qZrlIBN2Hn2DtKG', 'customer'),
('Store Admin',   'admin@demo.com',    '$2y$10$qU4fpCp9fpT38N1C9RHSnuXRUiSEiMi33LziE4H94NJHx76QfBnb.', 'admin');

-- ---------------------------------------------------------------
-- Categories (replaces free-text category strings)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(10) DEFAULT '🛒',
    sort_order INT DEFAULT 0
);

INSERT INTO categories (name, icon, sort_order) VALUES
('Fruits & Veg', '🥕', 1),
('Dairy & Eggs', '🥛', 2),
('Bakery',       '🍞', 3),
('Pantry',       '🍚', 4),
('Beverages',    '🧃', 5),
('Snacks',       '🥜', 6);

-- ---------------------------------------------------------------
-- Products (used by the dashboard stat cards, and later the shop)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    icon VARCHAR(10) DEFAULT '🛒',
    image VARCHAR(255) DEFAULT NULL,
    description VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    unit VARCHAR(30) DEFAULT 'each',
    stock INT NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_category (category),
    INDEX idx_products_stock (stock),
    INDEX idx_products_featured (is_featured),
    INDEX idx_products_deleted (deleted_at)
);

INSERT INTO products (name, category, icon, description, price, unit, stock) VALUES
('Vine Tomatoes', 'Fruits & Veg', '🍅', 'Juicy, vine-ripened tomatoes.', 320, 'per kg', 42),
('Cavendish Bananas', 'Fruits & Veg', '🍌', 'Sweet ripe bananas.', 180, 'per kg', 60),
('Baby Spinach', 'Fruits & Veg', '🥬', 'Tender washed spinach leaves.', 210, 'per bunch', 8),
('Full Cream Milk', 'Dairy & Eggs', '🥛', 'Farm fresh full cream milk.', 410, '1L', 35),
('Free-range Eggs', 'Dairy & Eggs', '🥚', 'Free-range eggs, tray of 10.', 590, 'tray of 10', 20),
('Cheddar Block', 'Dairy & Eggs', '🧀', 'Matured cheddar cheese block.', 940, '200g', 0),
('Sourdough Loaf', 'Bakery', '🍞', 'Freshly baked sourdough loaf.', 450, 'each', 14),
('Butter Croissants', 'Bakery', '🥐', 'Flaky butter croissants.', 150, 'each', 26),
('Basmati Rice', 'Pantry', '🍚', 'Aged long-grain basmati rice.', 980, '5kg bag', 18),
('Olive Oil', 'Pantry', '🫒', 'Extra virgin cold-pressed olive oil.', 1450, '1L', 6),
('Orange Juice', 'Beverages', '🧃', '100% pure orange juice, no added sugar.', 390, '1L', 22),
('Roasted Cashews', 'Snacks', '🥜', 'Lightly salted roasted cashews.', 680, '200g', 30);

-- ---------------------------------------------------------------
-- Saved addresses for customers (reusable at checkout)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(40) DEFAULT 'Home',
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(80) NOT NULL,
    postal VARCHAR(20) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_addresses_user (user_id)
);

-- ---------------------------------------------------------------
-- Orders + order items
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id VARCHAR(30) PRIMARY KEY,
    user_id INT NOT NULL,
    customer_name VARCHAR(100),
    address VARCHAR(255),
    phone VARCHAR(30),
    subtotal DECIMAL(10,2) NOT NULL,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 250,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    coupon_code VARCHAR(40) DEFAULT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Processing',
    admin_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_orders_user (user_id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_created (created_at)
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(30) NOT NULL,
    product_id INT,
    product_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_product (product_id)
);

-- ---------------------------------------------------------------
-- Order status history (audit trail of every status change)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    changed_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_osh_order (order_id)
);

-- ---------------------------------------------------------------
-- Stock movements (audit trail for inventory changes)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    delta INT NOT NULL,
    reason VARCHAR(30) NOT NULL DEFAULT 'manual',
    note VARCHAR(255) DEFAULT NULL,
    changed_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_sm_product (product_id)
);

-- ---------------------------------------------------------------
-- Coupons / promo codes
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    type ENUM('percent','fixed') NOT NULL DEFAULT 'fixed',
    value DECIMAL(10,2) NOT NULL,
    min_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_uses INT DEFAULT NULL,
    uses INT NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_coupons_code (code),
    INDEX idx_coupons_active (active)
);

INSERT INTO coupons (code, type, value, min_subtotal, max_uses) VALUES
('FRESH100', 'fixed',   100, 1000, 100),
('WELCOME10', 'percent', 10,  500,  NULL);

-- ---------------------------------------------------------------
-- Contact form messages (customer -> store admin)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(150),
    body TEXT NOT NULL,
    status ENUM('open','replied','closed') NOT NULL DEFAULT 'open',
    admin_reply TEXT NULL,
    replied_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_messages_status (status)
);

-- ---------------------------------------------------------------
-- Password reset tokens
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pr_token (token)
);

-- ---------------------------------------------------------------
-- Login attempts (rate limiting / brute-force protection)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_la_email (email),
    INDEX idx_la_ip (ip),
    INDEX idx_la_created (created_at)
);
