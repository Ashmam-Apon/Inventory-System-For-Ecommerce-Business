-- Migration to add missing tables for delivery feature
-- Run this SQL script to fix the "inventory_transactions doesn't exist" error

USE trackit;

-- Create product_permission_requests table if it doesn't exist
CREATE TABLE IF NOT EXISTS product_permission_requests (
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

-- Create inventory_transactions table if it doesn't exist
CREATE TABLE IF NOT EXISTS inventory_transactions (
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

-- Add product_code column to products table if it doesn't exist
ALTER TABLE products ADD COLUMN IF NOT EXISTS product_code VARCHAR(100) UNIQUE AFTER sku;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_status_delivery_man ON product_permission_requests(status, delivery_man_id);
CREATE INDEX IF NOT EXISTS idx_status_accountant ON product_permission_requests(status, accountant_id);
CREATE INDEX IF NOT EXISTS idx_inventory_product ON inventory_transactions(product_id, transaction_type);
CREATE INDEX IF NOT EXISTS idx_inventory_booking ON inventory_transactions(booking_id);

-- Update existing products with product codes if they don't have them
UPDATE products SET product_code = CONCAT('PRD', LPAD(id, 4, '0')) WHERE product_code IS NULL OR product_code = '';

SELECT 'Migration completed successfully!' as message;
