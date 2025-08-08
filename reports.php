<?php
require_once 'includes/config.php';
requireRole('accountant');

$page_title = 'Reports & Analytics';

try {
    // Revenue statistics
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN pay.payment_status = 'paid' THEN b.amount ELSE 0 END) as total_revenue,
            SUM(CASE WHEN pay.payment_status = 'paid' AND DATE(pay.payment_date) = CURDATE() THEN b.amount ELSE 0 END) as today_revenue,
            SUM(CASE WHEN pay.payment_status = 'paid' AND WEEK(pay.payment_date) = WEEK(CURDATE()) THEN b.amount ELSE 0 END) as week_revenue,
            SUM(CASE WHEN pay.payment_status = 'paid' AND MONTH(pay.payment_date) = MONTH(CURDATE()) THEN b.amount ELSE 0 END) as month_revenue
        FROM bookings b
        LEFT JOIN payments pay ON b.id = pay.booking_id
    ");
    $revenue_stats = $stmt->fetch();
    
    // Booking statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'not_delivered' THEN 1 ELSE 0 END) as not_delivered,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM bookings
    ");
    $booking_stats = $stmt->fetch();
    
    // Monthly revenue for the last 6 months
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(pay.payment_date, '%Y-%m') as month,
            DATE_FORMAT(pay.payment_date, '%M %Y') as month_name,
            SUM(b.amount) as revenue,
            COUNT(b.id) as orders
        FROM bookings b
        LEFT JOIN payments pay ON b.id = pay.booking_id
        WHERE pay.payment_status = 'paid' 
        AND pay.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(pay.payment_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthly_revenue = $stmt->fetchAll();
    
    // Top products by sales
    $stmt = $pdo->query("
        SELECT 
            p.name,
            p.product_code,
            SUM(bi.quantity) as total_quantity,
            SUM(bi.quantity * bi.unit_price) as total_revenue,
            COUNT(DISTINCT b.id) as order_count
        FROM booking_items bi
        LEFT JOIN products p ON bi.product_id = p.id
        LEFT JOIN bookings b ON bi.booking_id = b.id
        LEFT JOIN payments pay ON b.id = pay.booking_id
        WHERE pay.payment_status = 'paid'
        GROUP BY p.id, p.name, p.product_code
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $top_products = $stmt->fetchAll();
    
    // Recent transactions
    $stmt = $pdo->query("
        SELECT 
            b.id,
            b.customer_name,
            b.amount,
            b.payment_type,
            pay.payment_status,
            pay.payment_date,
            b.created_at
        FROM bookings b
        LEFT JOIN payments pay ON b.id = pay.booking_id
        WHERE pay.payment_status = 'paid'
        ORDER BY pay.payment_date DESC
        LIMIT 10
    ");
    $recent_transactions = $stmt->fetchAll();
    
    // Payment method statistics
    $stmt = $pdo->query("
        SELECT 
            b.payment_type,
            COUNT(*) as count,
            SUM(b.amount) as revenue
        FROM bookings b
        LEFT JOIN payments pay ON b.id = pay.booking_id
        WHERE pay.payment_status = 'paid'
        GROUP BY b.payment_type
    ");
    $payment_methods = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $revenue_stats = [];
    $booking_stats = [];
    $monthly_revenue = [];
    $top_products = [];
    $recent_transactions = [];
    $payment_methods = [];
}

// Prepare data for charts
$chart_labels = [];
$chart_revenue = [];
foreach ($monthly_revenue as $data) {
    $chart_labels[] = $data['month_name'];
    $chart_revenue[] = (float)$data['revenue'];
}

$status_labels = [];
$status_data = [];
foreach ($booking_stats as $key => $value) {
    if ($key !== 'total_bookings' && $value > 0) {
        $status_labels[] = ucfirst(str_replace('_', ' ', $key));
        $status_data[] = (int)$value;
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-chart-bar"></i>
            Reports & Analytics
        </h1>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn btn-outline">
                <i class="fas fa-print"></i>
                Print Report
            </button>
            <button onclick="exportToCSV()" class="btn btn-primary">
                <i class="fas fa-download"></i>
                Export CSV
            </button>
        </div>
    </div>
    
    <!-- Revenue Statistics -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-dollar-sign"></i>
                Revenue Overview
            </h2>
        </div>
        <div class="card-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-chart-line stat-icon" style="color: var(--success-color);"></i>
                    <span class="stat-number" style="color: var(--success-color);"><?php echo formatCurrency($revenue_stats['total_revenue'] ?? 0); ?></span>
                    <span class="stat-label">Total Revenue</span>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-calendar-day stat-icon" style="color: var(--primary-color);"></i>
                    <span class="stat-number"><?php echo formatCurrency($revenue_stats['today_revenue'] ?? 0); ?></span>
                    <span class="stat-label">Today's Revenue</span>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-calendar-week stat-icon" style="color: var(--warning-color);"></i>
                    <span class="stat-number"><?php echo formatCurrency($revenue_stats['week_revenue'] ?? 0); ?></span>
                    <span class="stat-label">This Week</span>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-calendar-alt stat-icon" style="color: var(--secondary-color);"></i>
                    <span class="stat-number"><?php echo formatCurrency($revenue_stats['month_revenue'] ?? 0); ?></span>
                    <span class="stat-label">This Month</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="grid grid-2">
        <!-- Revenue Chart -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    Revenue Trend (Last 6 Months)
                </h2>
            </div>
            <div class="card-content">
                <div style="height: 300px; position: relative;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Booking Status Distribution -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-chart-pie"></i>
                    Booking Status Distribution
                </h2>
            </div>
            <div class="card-content">
                <div style="height: 300px; position: relative;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="grid grid-2">
        <!-- Top Products -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-trophy"></i>
                    Top Selling Products
                </h2>
            </div>
            <div class="card-content">
                <?php if (!empty($top_products)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty Sold</th>
                                <th>Revenue</th>
                                <th>Orders</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $index => $product): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="background: var(--primary-color); color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 600;">
                                            <?php echo $index + 1; ?>
                                        </span>
                                        <div>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <?php if ($product['product_code']): ?>
                                            <br><small style="color: #666;">Code: <?php echo htmlspecialchars($product['product_code']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo number_format($product['total_quantity']); ?></td>
                                <td><?php echo formatCurrency($product['total_revenue']); ?></td>
                                <td><?php echo number_format($product['order_count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 40px;">
                    No sales data available.
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment Methods -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-credit-card"></i>
                    Payment Methods
                </h2>
            </div>
            <div class="card-content">
                <?php if (!empty($payment_methods)): ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($payment_methods as $method): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: var(--background-color); border-radius: 8px;">
                        <div>
                            <strong><?php echo htmlspecialchars($method['payment_type']); ?></strong><br>
                            <small><?php echo number_format($method['count']); ?> transactions</small>
                        </div>
                        <div style="text-align: right;">
                            <strong><?php echo formatCurrency($method['revenue']); ?></strong><br>
                            <small><?php echo number_format(($method['revenue'] / ($revenue_stats['total_revenue'] ?: 1)) * 100, 1); ?>%</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 40px;">
                    No payment data available.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-history"></i>
                Recent Transactions
            </h2>
        </div>
        <div class="card-content">
            <?php if (!empty($recent_transactions)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Payment Type</th>
                            <th>Payment Date</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $transaction): ?>
                        <tr>
                            <td><strong>#<?php echo $transaction['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($transaction['customer_name']); ?></td>
                            <td><?php echo formatCurrency($transaction['amount']); ?></td>
                            <td>
                                <span class="status <?php echo $transaction['payment_type'] === 'Online Paid' ? 'status-approved' : 'status-pending'; ?>">
                                    <?php echo $transaction['payment_type']; ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($transaction['payment_date']); ?></td>
                            <td><?php echo formatDate($transaction['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="text-align: center; color: var(--text-secondary); padding: 40px;">
                No transactions available.
            </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Monthly Report Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-table"></i>
                Monthly Revenue Report
            </h2>
        </div>
        <div class="card-content">
            <?php if (!empty($monthly_revenue)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                            <th>Avg. Order Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_revenue as $month): ?>
                        <tr>
                            <td><?php echo $month['month_name']; ?></td>
                            <td><?php echo number_format($month['orders']); ?></td>
                            <td><?php echo formatCurrency($month['revenue']); ?></td>
                            <td><?php echo formatCurrency($month['revenue'] / ($month['orders'] ?: 1)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="text-align: center; color: var(--text-secondary); padding: 40px;">
                No monthly data available.
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Store chart data
window.chartData = {
    revenueLabels: <?php echo json_encode($chart_labels); ?>,
    revenueData: <?php echo json_encode($chart_revenue); ?>,
    statusLabels: <?php echo json_encode($status_labels); ?>,
    statusData: <?php echo json_encode($status_data); ?>
};

document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
});

function exportToCSV() {
    const csvContent = generateCSVContent();
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `revenue_report_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function generateCSVContent() {
    let csv = 'Month,Orders,Revenue,Avg Order Value\n';
    
    <?php foreach ($monthly_revenue as $month): ?>
    csv += '<?php echo $month['month_name']; ?>,<?php echo $month['orders']; ?>,<?php echo $month['revenue']; ?>,<?php echo $month['revenue'] / ($month['orders'] ?: 1); ?>\n';
    <?php endforeach; ?>
    
    return csv;
}
</script>

<?php 
$extra_js = ['https://cdn.jsdelivr.net/npm/chart.js']; 
include 'includes/footer.php'; 
?>
