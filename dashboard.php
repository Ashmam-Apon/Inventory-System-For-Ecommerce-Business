<?php
require_once 'includes/config.php';
requireLogin();

$page_title = 'Dashboard';

// Get dashboard statistics based on user role
$stats = [];

try {
    if ($_SESSION['role'] === 'moderator') {
        // Total products
        $stmt = $pdo->query("SELECT COUNT(*) FROM products");
        $stats['total_products'] = $stmt->fetchColumn();
        
        // Low stock products (less than 10)
        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity < 10");
        $stats['low_stock'] = $stmt->fetchColumn();
        
        // My bookings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE moderator_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['my_bookings'] = $stmt->fetchColumn();
        
        // Pending bookings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE moderator_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['pending_bookings'] = $stmt->fetchColumn();
        
        // Recent bookings
        $stmt = $pdo->prepare("
            SELECT b.*, GROUP_CONCAT(p.name SEPARATOR ', ') as products
            FROM bookings b
            LEFT JOIN booking_items bi ON b.id = bi.booking_id
            LEFT JOIN products p ON bi.product_id = p.id
            WHERE b.moderator_id = ?
            GROUP BY b.id
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $recent_bookings = $stmt->fetchAll();
        
    } elseif ($_SESSION['role'] === 'accountant') {
        // Total bookings
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
        $stats['total_bookings'] = $stmt->fetchColumn();
        
        // Pending approval
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
        $stats['pending_approval'] = $stmt->fetchColumn();
        
        // Delivered orders
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'delivered'");
        $stats['delivered_orders'] = $stmt->fetchColumn();
        
        // Total revenue
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM bookings WHERE status = 'delivered'");
        $stats['total_revenue'] = $stmt->fetchColumn();
        
        // Pending bookings for approval
        $stmt = $pdo->query("
            SELECT b.*, u.full_name as moderator_name, GROUP_CONCAT(p.name SEPARATOR ', ') as products
            FROM bookings b
            LEFT JOIN users u ON b.moderator_id = u.id
            LEFT JOIN booking_items bi ON b.id = bi.booking_id
            LEFT JOIN products p ON bi.product_id = p.id
            WHERE b.status = 'pending'
            GROUP BY b.id
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        $pending_bookings = $stmt->fetchAll();
        
    } elseif ($_SESSION['role'] === 'storeman') {
        // Approved orders (ready for delivery)
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'approved'");
        $stats['ready_for_delivery'] = $stmt->fetchColumn();
        
        // Delivered today
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'delivered' AND DATE(updated_at) = CURDATE()");
        $stats['delivered_today'] = $stmt->fetchColumn();
        
        // Total delivered
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'delivered'");
        $stats['total_delivered'] = $stmt->fetchColumn();
        
        // Not delivered
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'not_delivered'");
        $stats['not_delivered'] = $stmt->fetchColumn();
        
        // Orders ready for delivery
        $stmt = $pdo->query("
            SELECT b.*, u.full_name as moderator_name, GROUP_CONCAT(p.name SEPARATOR ', ') as products
            FROM bookings b
            LEFT JOIN users u ON b.moderator_id = u.id
            LEFT JOIN booking_items bi ON b.id = bi.booking_id
            LEFT JOIN products p ON bi.product_id = p.id
            WHERE b.status = 'approved'
            GROUP BY b.id
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        $ready_orders = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $stats = [];
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
        </h1>
        <p>Welcome back, <?php echo $_SESSION['full_name']; ?>!</p>
    </div>
    
    <?php if ($_SESSION['role'] === 'moderator'): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-box stat-icon"></i>
            <span class="stat-number"><?php echo number_format($stats['total_products'] ?? 0); ?></span>
            <span class="stat-label">Total Products</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-exclamation-triangle stat-icon" style="color: var(--warning-color);"></i>
            <span class="stat-number" style="color: var(--warning-color);"><?php echo number_format($stats['low_stock'] ?? 0); ?></span>
            <span class="stat-label">Low Stock Items</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-clipboard-list stat-icon"></i>
            <span class="stat-number"><?php echo number_format($stats['my_bookings'] ?? 0); ?></span>
            <span class="stat-label">My Bookings</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-clock stat-icon" style="color: var(--warning-color);"></i>
            <span class="stat-number" style="color: var(--warning-color);"><?php echo number_format($stats['pending_bookings'] ?? 0); ?></span>
            <span class="stat-label">Pending Approval</span>
        </div>
    </div>
    
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-plus-circle"></i>
                    Quick Actions
                </h2>
            </div>
            <div class="card-content">
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <a href="create-booking.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Create New Booking
                    </a>
                    <a href="products.php" class="btn btn-outline">
                        <i class="fas fa-search"></i>
                        Browse Products
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-history"></i>
                    Recent Bookings
                </h2>
            </div>
            <div class="card-content">
                <?php if (!empty($recent_bookings)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $booking['id']; ?></strong><br>
                                    <small><?php echo $booking['customer_name']; ?></small>
                                </td>
                                <td>
                                    <span class="status status-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo formatCurrency($booking['amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                    No bookings yet. <a href="create-booking.php">Create your first booking</a>.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php elseif ($_SESSION['role'] === 'accountant'): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-clipboard-list stat-icon"></i>
            <span class="stat-number"><?php echo number_format($stats['total_bookings'] ?? 0); ?></span>
            <span class="stat-label">Total Bookings</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-clock stat-icon" style="color: var(--warning-color);"></i>
            <span class="stat-number" style="color: var(--warning-color);"><?php echo number_format($stats['pending_approval'] ?? 0); ?></span>
            <span class="stat-label">Pending Approval</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-check-circle stat-icon" style="color: var(--success-color);"></i>
            <span class="stat-number" style="color: var(--success-color);"><?php echo number_format($stats['delivered_orders'] ?? 0); ?></span>
            <span class="stat-label">Delivered Orders</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-dollar-sign stat-icon" style="color: var(--success-color);"></i>
            <span class="stat-number" style="color: var(--success-color);"><?php echo formatCurrency($stats['total_revenue'] ?? 0); ?></span>
            <span class="stat-label">Total Revenue</span>
        </div>
    </div>
    
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-tasks"></i>
                    Quick Actions
                </h2>
            </div>
            <div class="card-content">
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <a href="bookings.php" class="btn btn-primary">
                        <i class="fas fa-clipboard-check"></i>
                        Review Bookings
                    </a>
                    <a href="payments.php" class="btn btn-outline">
                        <i class="fas fa-credit-card"></i>
                        Manage Payments
                    </a>
                    <a href="reports.php" class="btn btn-outline">
                        <i class="fas fa-chart-bar"></i>
                        View Reports
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-hourglass-half"></i>
                    Pending Approvals
                </h2>
            </div>
            <div class="card-content">
                <?php if (!empty($pending_bookings)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                            <?php foreach ($pending_bookings as $booking): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $booking['id']; ?></strong><br>
                                    <small>by <?php echo $booking['moderator_name']; ?></small>
                                </td>
                                <td>
                                    <?php echo $booking['customer_name']; ?><br>
                                    <small><?php echo formatCurrency($booking['amount']); ?></small>
                                </td>
                                <td>
                                    <a href="bookings.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-primary">
                                        Review
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                    No pending approvals.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php elseif ($_SESSION['role'] === 'storeman'): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-box-open stat-icon" style="color: var(--warning-color);"></i>
            <span class="stat-number" style="color: var(--warning-color);"><?php echo number_format($stats['ready_for_delivery'] ?? 0); ?></span>
            <span class="stat-label">Ready for Delivery</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-truck stat-icon" style="color: var(--primary-color);"></i>
            <span class="stat-number"><?php echo number_format($stats['delivered_today'] ?? 0); ?></span>
            <span class="stat-label">Delivered Today</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-check-circle stat-icon" style="color: var(--success-color);"></i>
            <span class="stat-number" style="color: var(--success-color);"><?php echo number_format($stats['total_delivered'] ?? 0); ?></span>
            <span class="stat-label">Total Delivered</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-times-circle stat-icon" style="color: var(--error-color);"></i>
            <span class="stat-number" style="color: var(--error-color);"><?php echo number_format($stats['not_delivered'] ?? 0); ?></span>
            <span class="stat-label">Not Delivered</span>
        </div>
    </div>
    
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-shipping-fast"></i>
                    Quick Actions
                </h2>
            </div>
            <div class="card-content">
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <a href="deliveries.php" class="btn btn-primary">
                        <i class="fas fa-truck"></i>
                        Manage Deliveries
                    </a>
                    <a href="deliveries.php?status=approved" class="btn btn-outline">
                        <i class="fas fa-box-open"></i>
                        Ready for Delivery
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list-ul"></i>
                    Orders Ready for Delivery
                </h2>
            </div>
            <div class="card-content">
                <?php if (!empty($ready_orders)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                            <?php foreach ($ready_orders as $order): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $order['id']; ?></strong><br>
                                    <small><?php echo $order['customer_name']; ?></small>
                                </td>
                                <td>
                                    <?php echo formatCurrency($order['amount']); ?><br>
                                    <small><?php echo formatDate($order['created_at']); ?></small>
                                </td>
                                <td>
                                    <a href="deliveries.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                        Process
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                    No orders ready for delivery.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
