-- =============================================================
-- products-api — Setup database untuk API Produk
-- Dipakai oleh Praktek 5 (Database Testing) dan Praktek 6 (Performance Testing).
-- Jalankan sekali sebelum mulai praktek lewat phpMyAdmin atau:
--   mysql -u root < setup.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS tokokita_testing
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tokokita_testing;

-- Reset tabel kalau sudah ada
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;

-- Tabel produk
CREATE TABLE products (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    name     VARCHAR(100) NOT NULL,
    price    DECIMAL(10,2) NOT NULL,
    stock    INT NOT NULL DEFAULT 0,
    category VARCHAR(50) NULL,
    INDEX idx_category (category)
);

-- Tabel pesanan (untuk Praktek 5)
CREATE TABLE orders (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    quantity    INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status      ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_product FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Seed data awal agar GET /products langsung ada isinya untuk load test
INSERT INTO products (name, price, stock, category) VALUES
    ('Laptop Asus',        8500000,  10, 'Elektronik'),
    ('Mouse Logitech',      350000,  50, 'Aksesoris'),
    ('Keyboard Mechanical', 750000,  30, 'Aksesoris'),
    ('Monitor 24inch',     3200000,  15, 'Elektronik'),
    ('Headphone Sony',     1200000,  25, 'Elektronik');
