<?php
/**
 * Database Setup Script
 * Run this file once to initialize the database
 * Then delete this file for security
 */

// Database configuration
$db_host = 'localhost';
$db_name = 'trackit';
$db_user = 'root';
$db_pass = '';

try {
    // First, connect to MySQL without selecting a database
    $pdo = new PDO("mysql:host=" . $db_host, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Read the schema file
    $schema = file_get_contents('database/schema.sql');
    
    // Execute the schema
    $pdo->exec($schema);
    
    echo "<div style='font-family: Arial, sans-serif; padding: 40px; background: #f0f8ff; border: 2px solid #28a745; border-radius: 8px; margin: 50px auto; max-width: 600px;'>";
    echo "<h1 style='color: #28a745;'>✓ Database Setup Successful!</h1>";
    echo "<p style='font-size: 16px;'><strong>Database:</strong> trackit</p>";
    echo "<p style='font-size: 16px; margin-bottom: 30px;'><strong>Default Users Created:</strong></p>";
    echo "<ul style='font-size: 14px;'>";
    echo "<li><strong>Moderator:</strong> admin_mod / password</li>";
    echo "<li><strong>Accountant:</strong> admin_acc / password</li>";
    echo "<li><strong>Storeman:</strong> admin_store / password</li>";
    echo "</ul>";
    echo "<p style='color: #d32f2f; font-weight: bold; margin-top: 30px;'>⚠️ IMPORTANT: Delete this file (setup.php) after setup for security!</p>";
    echo "<p style='margin-top: 20px;'><a href='login.php' style='padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;'>Go to Login</a></p>";
    echo "</div>";
    
} catch(PDOException $e) {
    echo "<div style='font-family: Arial, sans-serif; padding: 40px; background: #ffebee; border: 2px solid #d32f2f; border-radius: 8px; margin: 50px auto; max-width: 600px;'>";
    echo "<h1 style='color: #d32f2f;'>✗ Database Setup Failed</h1>";
    echo "<p style='font-size: 16px; margin-bottom: 20px;'><strong>Error:</strong></p>";
    echo "<pre style='background: #fff; padding: 15px; border-radius: 5px; overflow-x: auto;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p style='font-size: 14px; color: #666; margin-top: 20px;'>";
    echo "<strong>Troubleshooting:</strong><br>";
    echo "1. Make sure MySQL is running<br>";
    echo "2. Check that the database user 'root' exists and has a blank password<br>";
    echo "3. Ensure you have permission to create databases<br>";
    echo "4. If using a different database user/password, update 'includes/config.php' first";
    echo "</p>";
    echo "</div>";
}
?>
