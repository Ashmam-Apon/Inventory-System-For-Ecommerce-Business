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
    // Display user-friendly error
    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>Database Connection Error - " . APP_NAME . "</title>";
    echo "<style>";
    echo "body { font-family: Arial, sans-serif; background: #f0f0f0; margin: 0; padding: 20px; }";
    echo ".error-container { background: white; max-width: 600px; margin: 50px auto; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
    echo ".error-container h1 { color: #d32f2f; }";
    echo ".error-message { background: #ffebee; padding: 15px; border-left: 4px solid #d32f2f; border-radius: 4px; margin: 20px 0; }";
    echo ".solution { background: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50; border-radius: 4px; margin: 20px 0; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    echo "<div class='error-container'>";
    echo "<h1>⚠️ Database Connection Failed</h1>";
    echo "<div class='error-message'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
    echo "<div class='solution'>";
    echo "<strong>Solution:</strong><br>";
    echo "1. First time setup? Run the <a href='setup.php' style='color: #2563eb;'>setup.php</a> file to initialize the database.<br>";
    echo "2. Make sure MySQL is running on localhost<br>";
    echo "3. Check that the database user 'root' exists with a blank password<br>";
    echo "4. If your database credentials are different, update 'includes/config.php'";
    echo "</div>";
    echo "</div>";
    echo "</body>";
    echo "</html>";
    exit();
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
?>
