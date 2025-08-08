<?php
// Quick fix script to create missing tables
// Run this script directly to fix the inventory_transactions error

require_once 'includes/config.php';

echo "<h2>Fixing Missing Tables</h2>\n";

try {
    // Create product_permission_requests table
    $sql = "
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
    )";
    
    $pdo->exec($sql);
    echo "<p style='color: green;'>✅ Product permission requests table created successfully!</p>\n";
    
    // Create inventory_transactions table
    $sql = "
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
    )";
    
    $pdo->exec($sql);
    echo "<p style='color: green;'>✅ Inventory transactions table created successfully!</p>\n";
    
    // Add product_code column if it doesn't exist
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'product_code'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE products ADD COLUMN product_code VARCHAR(100) UNIQUE AFTER sku");
            echo "<p style='color: green;'>✅ Product code column added to products table!</p>\n";
            
            // Update existing products with product codes
            $pdo->exec("UPDATE products SET product_code = CONCAT('PRD', LPAD(id, 4, '0')) WHERE product_code IS NULL OR product_code = ''");
            echo "<p style='color: green;'>✅ Existing products updated with product codes!</p>\n";
        } else {
            echo "<p style='color: blue;'>ℹ️ Product code column already exists in products table.</p>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ Product code column may already exist or there's a minor issue: " . $e->getMessage() . "</p>\n";
    }
    
    // Add indexes for better performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_status_delivery_man ON product_permission_requests(status, delivery_man_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_status_accountant ON product_permission_requests(status, accountant_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inventory_product ON inventory_transactions(product_id, transaction_type)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inventory_booking ON inventory_transactions(booking_id)");
    echo "<p style='color: green;'>✅ Database indexes created successfully!</p>\n";
    
    echo "<br><h3 style='color: green;'>✅ All missing tables have been created successfully!</h3>\n";
    echo "<p><strong>The inventory_transactions error should now be resolved.</strong></p>\n";
    echo "<p>The delivery man should now be able to update status after product approval by the Accountant without any errors.</p>\n";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Error occurred:</h3>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    echo "<p>Please check your database connection and try again.</p>\n";
}
?>
