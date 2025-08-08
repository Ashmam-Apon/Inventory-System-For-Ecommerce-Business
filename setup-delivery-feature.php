<?php
require_once 'includes/config.php';

// Only allow accountant to run this setup
requireRole('accountant');

echo "<h2>Database Setup for Delivery Inventory Feature</h2>";

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
    echo "<p style='color: green;'>✅ Product permission requests table created successfully!</p>";
    
    // Create inventory transactions table
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
    echo "<p style='color: green;'>✅ Inventory transactions table created successfully!</p>";
    
    // Add indexes for better performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_status_delivery_man ON product_permission_requests(status, delivery_man_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_status_accountant ON product_permission_requests(status, accountant_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inventory_product ON inventory_transactions(product_id, transaction_type)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inventory_booking ON inventory_transactions(booking_id)");
    echo "<p style='color: green;'>✅ Database indexes created successfully!</p>";
    
    // Add product_code column to products table if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'product_code'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE products ADD COLUMN product_code VARCHAR(100) UNIQUE AFTER sku");
        echo "<p style='color: green;'>✅ Product code column added to products table!</p>";
        
        // Update existing products with product codes based on SKU
        $pdo->exec("UPDATE products SET product_code = sku WHERE product_code IS NULL");
        echo "<p style='color: green;'>✅ Existing products updated with product codes!</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ Product code column already exists in products table.</p>";
    }
    
    echo "<br><h3 style='color: green;'>Database setup completed successfully!</h3>";
    echo "<p>You can now use the delivery inventory check feature with automatic product code generation.</p>";
    echo "<h4>Product Code Format:</h4>";
    echo "<ul style='margin-left: 20px;'>";
    echo "<li><strong>Electronics:</strong> EL + 6 digits (e.g., EL123456)</li>";
    echo "<li><strong>Accessories:</strong> AC + 6 digits (e.g., AC123456)</li>";
    echo "<li><strong>Beauty:</strong> BP + 6 digits (e.g., BP123456)</li>";
    echo "<li><strong>Saree:</strong> SR + 6 digits (e.g., SR123456)</li>";
    echo "<li><strong>Clothing:</strong> CL + 6 digits (e.g., CL123456)</li>";
    echo "<li><strong>Home & Garden:</strong> HG + 6 digits (e.g., HG123456)</li>";
    echo "<li><strong>Sports:</strong> SP + 6 digits (e.g., SP123456)</li>";
    echo "<li><strong>Books:</strong> BK + 6 digits (e.g., BK123456)</li>";
    echo "<li><strong>Toys:</strong> TY + 6 digits (e.g., TY123456)</li>";
    echo "<li><strong>Jewelry:</strong> JW + 6 digits (e.g., JW123456)</li>";
    echo "<li><strong>Shoes:</strong> SH + 6 digits (e.g., SH123456)</li>";
    echo "<li><strong>Bags:</strong> BG + 6 digits (e.g., BG123456)</li>";
    echo "<li><strong>Other:</strong> OT + 6 digits (e.g., OT123456)</li>";
    echo "</ul>";
    echo "<p><a href='delivery-inventory.php'>Go to Delivery Inventory Check</a> | <a href='product-permissions.php'>Go to Product Permissions</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
