<?php
require_once 'includes/config.php';

// Simple test to check if form submission is working
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Data Received:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h2>CSRF Token Check:</h2>";
    echo "Token in session: " . ($_SESSION['csrf_token'] ?? 'NOT SET') . "<br>";
    echo "Token in POST: " . ($_POST['csrf_token'] ?? 'NOT SET') . "<br>";
    echo "Token valid: " . (verifyCSRFToken($_POST['csrf_token'] ?? '') ? 'YES' : 'NO') . "<br>";
    
    echo "<h2>Customer Data:</h2>";
    echo "Name: " . ($_POST['customer_name'] ?? 'NOT SET') . "<br>";
    echo "Phone: " . ($_POST['customer_phone'] ?? 'NOT SET') . "<br>";
    echo "Address: " . ($_POST['customer_address'] ?? 'NOT SET') . "<br>";
    echo "Payment Type: " . ($_POST['payment_type'] ?? 'NOT SET') . "<br>";
    
    echo "<h2>Items Data:</h2>";
    echo "<pre>";
    print_r($_POST['items'] ?? []);
    echo "</pre>";
    
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Booking Form</title>
</head>
<body>
    <h1>Test Booking Form Submission</h1>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <label>Customer Name:</label><br>
        <input type="text" name="customer_name" value="Test Customer" required><br><br>
        
        <label>Phone:</label><br>
        <input type="tel" name="customer_phone" value="1234567890" required><br><br>
        
        <label>Address:</label><br>
        <textarea name="customer_address" required>Test Address</textarea><br><br>
        
        <label>Payment Type:</label><br>
        <select name="payment_type" required>
            <option value="Cash on Delivery">Cash on Delivery</option>
        </select><br><br>
        
        <label>Product ID:</label><br>
        <input type="number" name="items[0][product_id]" value="1" required><br><br>
        
        <label>Quantity:</label><br>
        <input type="number" name="items[0][quantity]" value="1" required><br><br>
        
        <button type="submit">Test Submit</button>
    </form>
</body>
</html>
