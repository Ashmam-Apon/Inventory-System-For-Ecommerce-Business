<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'trackit');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_NAME', 'TrackIt');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/trackit');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: dashboard.php');
        exit();
    }
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y H:i', strtotime($datetime));
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate unique product code based on category
 * Format: [2-letter category code][6 random digits]
 */
function generateProductCode($category, $pdo) {
    // Category mapping to 2-letter codes
    $categoryMappings = [
        'Electronics' => 'EC',
        'Accessories' => 'AC',
        'Beauty' => 'BP',
        'Saree' => 'SR',
        'Clothing' => 'CL',
        'Home & Garden' => 'HG',
        'Sports' => 'SP',
        'Books' => 'BK',
        'Toys' => 'TY',
        'Jewelry' => 'JW',
        'Shoes' => 'SH',
        'Bags' => 'BG',
        'Other' => 'OT'
    ];
    
    // Get category prefix
    $categoryPrefix = $categoryMappings[$category] ?? 'OT';
    
    // Generate unique 6-digit number
    $maxAttempts = 100;
    $attempts = 0;
    
    do {
        // Generate 6 random digits
        $randomDigits = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $productCode = $categoryPrefix . $randomDigits;
        
        // Check if this code already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE product_code = ?");
        $stmt->execute([$productCode]);
        $exists = $stmt->fetchColumn() > 0;
        
        $attempts++;
    } while ($exists && $attempts < $maxAttempts);
    
    if ($attempts >= $maxAttempts) {
        throw new Exception('Unable to generate unique product code after multiple attempts');
    }
    
    return $productCode;
}
?>
