-- TrackIt Database Schema
CREATE DATABASE IF NOT EXISTS trackit;
USE trackit;

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('moderator', 'accountant', 'storeman') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table for inventory
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    sku VARCHAR(50) UNIQUE,
    product_code VARCHAR(100) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Booking requests table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_address TEXT NOT NULL,
    payment_type ENUM('Online Paid', 'Cash on Delivery') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'delivered', 'not_delivered') DEFAULT 'pending',
    moderator_id INT,
    accountant_id INT,
    storeman_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (moderator_id) REFERENCES users(id),
    FOREIGN KEY (accountant_id) REFERENCES users(id),
    FOREIGN KEY (storeman_id) REFERENCES users(id)
);

-- Booking items table (products in each booking)
CREATE TABLE booking_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Delivery details table
CREATE TABLE delivery_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNIQUE NOT NULL,
    delivery_company VARCHAR(100),
    tracking_number VARCHAR(100),
    delivery_date DATE,
    notes TEXT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Payment tracking table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNIQUE NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    payment_date DATE,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    notes TEXT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Product permission requests table for delivery feature
CREATE TABLE product_permission_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_man_id INT NOT NULL,
    supplier_name VARCHAR(200) NOT NULL,
    product_code VARCHAR(100) NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    category VARCHAR(100) NOT NULL,
    mrp DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    invoice_image VARCHAR(500),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    accountant_id INT NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (delivery_man_id) REFERENCES users(id),
    FOREIGN KEY (accountant_id) REFERENCES users(id)
);

-- Inventory transactions table for tracking stock changes
CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    transaction_type ENUM('delivery', 'restock', 'adjustment', 'return') NOT NULL,
    quantity_change INT NOT NULL,
    booking_id INT NULL,
    request_id INT NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (request_id) REFERENCES product_permission_requests(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Create indexes for better performance
CREATE INDEX idx_status_delivery_man ON product_permission_requests(status, delivery_man_id);
CREATE INDEX idx_status_accountant ON product_permission_requests(status, accountant_id);
CREATE INDEX idx_inventory_product ON inventory_transactions(product_id, transaction_type);
CREATE INDEX idx_inventory_booking ON inventory_transactions(booking_id);

-- Insert default users
INSERT INTO users (username, password, role, full_name, email) VALUES
('admin_mod', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderator', 'Admin Moderator', 'mod@trackit.com'),
('admin_acc', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'accountant', 'Admin Accountant', 'acc@trackit.com'),
('admin_store', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'storeman', 'Admin Storeman', 'store@trackit.com');

-- Insert sample products
INSERT INTO products (name, description, category, price, stock_quantity, product_code) VALUES
('Wireless Headphones', 'High-quality wireless headphones with noise cancellation', 'Electronics', 99.99, 50, 'PRD0001'),
('Smartphone Case', 'Protective case for smartphones', 'Accessories', 19.99, 100, 'PRD0002'),
('Bluetooth Speaker', 'Portable bluetooth speaker with premium sound', 'Electronics', 79.99, 30, 'PRD0003'),
('USB Cable', 'High-speed USB charging cable', 'Accessories', 9.99, 200, 'PRD0004'),
('Power Bank', '10000mAh portable power bank', 'Electronics', 39.99, 75, 'PRD0005');
