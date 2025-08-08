<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Meta -->
    <meta name="description" content="TrackIt - Inventory Management System">
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <i class="fas fa-boxes"></i>
                <span><?php echo APP_NAME; ?></span>
            </div>
            
            <div class="nav-menu" id="navMenu">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                
                <a href="products.php" class="nav-link">
                    <i class="fas fa-box"></i> Products
                </a>
                
                <?php if ($_SESSION['role'] === 'moderator'): ?>
                <a href="create-booking.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i> New Booking
                </a>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] === 'accountant'): ?>
                <a href="bookings.php" class="nav-link">
                    <i class="fas fa-clipboard-list"></i> Bookings
                </a>
                <a href="payments.php" class="nav-link">
                    <i class="fas fa-credit-card"></i> Payments
                </a>
                <a href="product-permissions.php" class="nav-link">
                    <i class="fas fa-clipboard-check"></i> Product Permissions
                </a>
                <a href="inventory-history.php" class="nav-link">
                    <i class="fas fa-history"></i> Inventory History
                </a>
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] === 'storeman'): ?>
                <a href="deliveries.php" class="nav-link">
                    <i class="fas fa-truck"></i> Deliveries
                </a>
                <a href="delivery-inventory.php" class="nav-link">
                    <i class="fas fa-search"></i> Inventory Check
                </a>
                <?php endif; ?>
            </div>
            
            <div class="nav-user">
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                    <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                </div>
                <a href="logout.php" class="nav-link logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
            
            <div class="nav-toggle" id="navToggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <main class="main-content <?php echo isLoggedIn() ? 'with-nav' : ''; ?>">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
