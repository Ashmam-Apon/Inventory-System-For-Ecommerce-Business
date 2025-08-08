<?php
require_once '../includes/config.php';
requireRole('accountant');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo 'Request ID is required';
    exit;
}

$request_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.full_name as delivery_man_name, u.username as delivery_man_username
        FROM product_permission_requests pr
        JOIN users u ON pr.delivery_man_id = u.id
        WHERE pr.id = ? AND pr.status = 'pending'
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        http_response_code(404);
        echo 'Request not found or already processed';
        exit;
    }
    
    // Check if product with same code already exists
    $stmt = $pdo->prepare("
        SELECT id, name, stock_quantity FROM products 
        WHERE product_code = ? OR sku = ?
    ");
    $stmt->execute([$request['product_code'], $request['product_code']]);
    $existing_product = $stmt->fetch();
    
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database error';
    exit;
}
?>

<div class="summary-header">
    <h4 style="margin: 0 0 15px 0;">
        Request Summary 
        <?php if ($existing_product): ?>
        <span style="color: var(--primary-color); font-size: 0.9rem;">(Additional Stock)</span>
        <?php else: ?>
        <span style="color: var(--warning-color); font-size: 0.9rem;">(New Product)</span>
        <?php endif; ?>
    </h4>
</div>

<div class="summary-grid">
    <div class="summary-item">
        <label>Request Type:</label>
        <span style="font-weight: bold; color: <?php echo $existing_product ? 'var(--primary-color)' : 'var(--warning-color)'; ?>;">
            <?php echo $existing_product ? '📦 Additional Stock Request' : '🆕 New Product Request'; ?>
        </span>
    </div>
    
    <div class="summary-item">
        <label>Requested By:</label>
        <span><?php echo htmlspecialchars($request['delivery_man_name']); ?></span>
    </div>
    
    <div class="summary-item">
        <label>Request Date:</label>
        <span><?php echo date('F j, Y', strtotime($request['created_at'])); ?></span>
    </div>
    
    <div class="summary-item">
        <label>Supplier:</label>
        <span><?php echo htmlspecialchars($request['supplier_name']); ?></span>
    </div>
    
    <div class="summary-item">
        <label>Product Code:</label>
        <span><?php echo htmlspecialchars($request['product_code']); ?></span>
    </div>
    
    <div class="summary-item">
        <label>Product Name:</label>
        <span><?php echo htmlspecialchars($request['product_name']); ?></span>
    </div>
    
    <div class="summary-item">
        <label>Category:</label>
        <span><?php echo htmlspecialchars($request['category']); ?></span>
    </div>
    
    <div class="summary-item">
        <label>MRP:</label>
        <span>৳<?php echo number_format($request['mrp'], 2); ?></span>
    </div>
    
    <div class="summary-item">
        <label>Selling Price:</label>
        <span>৳<?php echo number_format($request['selling_price'], 2); ?></span>
    </div>
    
    <div class="summary-item">
        <label>Quantity:</label>
        <span><?php echo $request['quantity']; ?></span>
    </div>
</div>

<?php if ($existing_product): ?>
<div class="alert alert-info" style="margin-top: 15px;">
    <i class="fas fa-info-circle"></i>
    <strong>Additional Stock Request:</strong> This product already exists in inventory: 
    "<?php echo htmlspecialchars($existing_product['name']); ?>" 
    (Current stock: <?php echo $existing_product['stock_quantity']; ?>). 
    If approved, <?php echo $request['quantity']; ?> units will be added to the existing stock, 
    making the new total: <?php echo $existing_product['stock_quantity'] + $request['quantity']; ?> units.
</div>
<?php else: ?>
<div class="alert alert-warning" style="margin-top: 15px;">
    <i class="fas fa-plus-circle"></i>
    <strong>New Product Request:</strong> This is a completely new product. 
    If approved, it will be added to the inventory with an initial stock of <?php echo $request['quantity']; ?> units.
</div>
<?php endif; ?>

<?php if ($request['invoice_image']): ?>
<div style="margin-top: 15px;">
    <label style="font-weight: bold; display: block; margin-bottom: 5px;">Invoice Image:</label>
    <img src="<?php echo htmlspecialchars($request['invoice_image']); ?>" 
         alt="Invoice" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
</div>
<?php endif; ?>

<style>
.summary-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.summary-item label {
    font-weight: bold;
    color: #555;
}

.summary-item span {
    color: #333;
}

.alert {
    padding: 12px;
    border-radius: 4px;
    font-size: 14px;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

@media (max-width: 600px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>
