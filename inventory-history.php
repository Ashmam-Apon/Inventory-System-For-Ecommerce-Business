<?php
require_once 'includes/config.php';
requireRole('accountant');

$page_title = 'Inventory History';

// Get filter parameters
$product_filter = (int)($_GET['product'] ?? 0);
$transaction_type_filter = sanitizeInput($_GET['transaction_type'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');

// Build query for inventory transactions
$where_conditions = ["1=1"];
$params = [];

if ($product_filter) {
    $where_conditions[] = "it.product_id = ?";
    $params[] = $product_filter;
}

if ($transaction_type_filter) {
    $where_conditions[] = "it.transaction_type = ?";
    $params[] = $transaction_type_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(it.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(it.created_at) <= ?";
    $params[] = $date_to;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Get inventory transactions
    $stmt = $pdo->prepare("
        SELECT 
            it.*,
            p.name as product_name,
            p.product_code,
            u.full_name as created_by_name,
            b.customer_name,
            pr.supplier_name
        FROM inventory_transactions it
        JOIN products p ON it.product_id = p.id
        JOIN users u ON it.created_by = u.id
        LEFT JOIN bookings b ON it.booking_id = b.id
        LEFT JOIN product_permission_requests pr ON it.request_id = pr.id
        $where_sql
        ORDER BY it.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // Get products for filter
    $stmt = $pdo->query("
        SELECT DISTINCT p.id, p.name, p.product_code 
        FROM products p 
        JOIN inventory_transactions it ON p.id = it.product_id
        ORDER BY p.name
    ");
    $products = $stmt->fetchAll();
    
    // Get transaction summary
    $stmt = $pdo->query("
        SELECT 
            transaction_type,
            COUNT(*) as count,
            SUM(ABS(quantity_change)) as total_quantity
        FROM inventory_transactions
        GROUP BY transaction_type
        ORDER BY transaction_type
    ");
    $summary = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $transactions = [];
    $products = [];
    $summary = [];
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-history"></i>
            Inventory History
        </h1>
        <div class="stats">
            <?php foreach ($summary as $stat): ?>
            <div class="stat">
                <span class="stat-value"><?php echo $stat['count']; ?></span>
                <span class="stat-label"><?php echo ucfirst($stat['transaction_type']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-content">
            <form method="GET" class="search-row">
                <div class="form-group">
                    <select name="product" class="form-select">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>" 
                                <?php echo $product_filter === $product['id'] ? 'selected' : ''; ?>>
                            [<?php echo htmlspecialchars($product['product_code']); ?>] 
                            <?php echo htmlspecialchars($product['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <select name="transaction_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="delivery" <?php echo $transaction_type_filter === 'delivery' ? 'selected' : ''; ?>>Delivery</option>
                        <option value="restock" <?php echo $transaction_type_filter === 'restock' ? 'selected' : ''; ?>>Restock</option>
                        <option value="adjustment" <?php echo $transaction_type_filter === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                        <option value="return" <?php echo $transaction_type_filter === 'return' ? 'selected' : ''; ?>>Return</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="date" name="date_from" class="form-input" 
                           value="<?php echo htmlspecialchars($date_from); ?>" placeholder="From Date">
                </div>
                <div class="form-group">
                    <input type="date" name="date_to" class="form-input" 
                           value="<?php echo htmlspecialchars($date_to); ?>" placeholder="To Date">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    Filter
                </button>
                <?php if ($product_filter || $transaction_type_filter || $date_from || $date_to): ?>
                <a href="inventory-history.php" class="btn btn-outline">
                    <i class="fas fa-times"></i>
                    Clear
                </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Transactions List -->
    <div class="card">
        <div class="card-content">
            <?php if (!empty($transactions)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Quantity Change</th>
                            <th>Reference</th>
                            <th>Created By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($transaction['product_name']); ?></strong><br>
                                <small>Code: <?php echo htmlspecialchars($transaction['product_code']); ?></small>
                            </td>
                            <td>
                                <span class="status status-<?php 
                                    echo $transaction['transaction_type'] === 'delivery' ? 'rejected' : 
                                        ($transaction['transaction_type'] === 'restock' ? 'approved' : 'pending'); 
                                ?>">
                                    <?php echo ucfirst($transaction['transaction_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: <?php echo $transaction['quantity_change'] > 0 ? 'var(--success-color)' : 'var(--error-color)'; ?>; font-weight: bold;">
                                    <?php echo $transaction['quantity_change'] > 0 ? '+' : ''; ?><?php echo $transaction['quantity_change']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($transaction['booking_id']): ?>
                                <small>Booking #<?php echo $transaction['booking_id']; ?></small>
                                <?php if ($transaction['customer_name']): ?>
                                <br><small><?php echo htmlspecialchars($transaction['customer_name']); ?></small>
                                <?php endif; ?>
                                <?php elseif ($transaction['request_id']): ?>
                                <small>Request #<?php echo $transaction['request_id']; ?></small>
                                <?php if ($transaction['supplier_name']): ?>
                                <br><small><?php echo htmlspecialchars($transaction['supplier_name']); ?></small>
                                <?php endif; ?>
                                <?php else: ?>
                                <small>-</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['created_by_name']); ?></td>
                            <td>
                                <?php if ($transaction['notes']): ?>
                                <small><?php echo htmlspecialchars($transaction['notes']); ?></small>
                                <?php else: ?>
                                <small>-</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($transactions) >= 100): ?>
            <div style="text-align: center; padding: 20px; color: var(--text-secondary);">
                <i class="fas fa-info-circle"></i>
                Showing latest 100 transactions. Use filters to narrow down results.
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-history" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 20px;"></i>
                <h3 style="margin-bottom: 10px;">No Transactions Found</h3>
                <p style="color: var(--text-secondary);">
                    <?php if ($product_filter || $transaction_type_filter || $date_from || $date_to): ?>
                        No transactions match your filter criteria.
                    <?php else: ?>
                        No inventory transactions have been recorded yet.
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.stats {
    display: flex;
    gap: 20px;
}

.stat {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-color);
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.search-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: end;
}

.search-row .form-group {
    min-width: 150px;
    flex: 1;
}

@media (max-width: 768px) {
    .stats {
        flex-direction: column;
        gap: 10px;
    }
    
    .search-row {
        flex-direction: column;
    }
    
    .search-row .form-group {
        min-width: auto;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
