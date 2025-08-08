<?php
require_once '../includes/config.php';
requireLogin();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo 'Request ID is required';
    exit;
}

$request_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT pr.*, u1.full_name as delivery_man_name, u2.full_name as accountant_name
        FROM product_permission_requests pr
        LEFT JOIN users u1 ON pr.delivery_man_id = u1.id
        LEFT JOIN users u2 ON pr.accountant_id = u2.id
        WHERE pr.id = ? AND (pr.delivery_man_id = ? OR ? IN (SELECT id FROM users WHERE role = 'accountant'))
    ");
    $stmt->execute([$request_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $request = $stmt->fetch();
    
    if (!$request) {
        http_response_code(404);
        echo 'Request not found';
        exit;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database error';
    exit;
}
?>

<div class="request-details">
    <div class="detail-row">
        <label>Request ID:</label>
        <span>#<?php echo $request['id']; ?></span>
    </div>
    
    <div class="detail-row">
        <label>Delivery Man:</label>
        <span><?php echo htmlspecialchars($request['delivery_man_name']); ?></span>
    </div>
    
    <div class="detail-row">
        <label>Request Date:</label>
        <span><?php echo date('F j, Y g:i A', strtotime($request['created_at'])); ?></span>
    </div>
    
    <hr style="margin: 20px 0;">
    
    <div class="detail-row">
        <label>Supplier Name:</label>
        <span><?php echo htmlspecialchars($request['supplier_name']); ?></span>
    </div>
    
    <div class="detail-row">
        <label>Product Code:</label>
        <span><?php echo htmlspecialchars($request['product_code']); ?></span>
    </div>
    
    <div class="detail-row">
        <label>Product Name:</label>
        <span><?php echo htmlspecialchars($request['product_name']); ?></span>
    </div>
    
    <div class="detail-row">
        <label>Category:</label>
        <span><?php echo htmlspecialchars($request['category']); ?></span>
    </div>
    
    <div class="detail-row">
        <label>MRP:</label>
        <span>৳<?php echo number_format($request['mrp'], 2); ?></span>
    </div>
    
    <div class="detail-row">
        <label>Selling Price:</label>
        <span>৳<?php echo number_format($request['selling_price'], 2); ?></span>
    </div>
    
    <div class="detail-row">
        <label>Quantity:</label>
        <span><?php echo $request['quantity']; ?></span>
    </div>
    
    <?php if ($request['invoice_image']): ?>
    <div class="detail-row">
        <label>Invoice Image:</label>
        <div>
            <img src="<?php echo htmlspecialchars($request['invoice_image']); ?>" 
                 alt="Invoice" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
        </div>
    </div>
    <?php endif; ?>
    
    <hr style="margin: 20px 0;">
    
    <div class="detail-row">
        <label>Status:</label>
        <span class="status status-<?php 
            echo $request['status'] === 'approved' ? 'approved' : 
                ($request['status'] === 'rejected' ? 'rejected' : 'pending'); 
        ?>">
            <?php echo ucfirst($request['status']); ?>
        </span>
    </div>
    
    <?php if ($request['accountant_name']): ?>
    <div class="detail-row">
        <label>Reviewed By:</label>
        <span><?php echo htmlspecialchars($request['accountant_name']); ?></span>
    </div>
    <?php endif; ?>
    
    <?php if ($request['reviewed_at']): ?>
    <div class="detail-row">
        <label>Reviewed At:</label>
        <span><?php echo date('F j, Y g:i A', strtotime($request['reviewed_at'])); ?></span>
    </div>
    <?php endif; ?>
    
    <?php if ($request['review_notes']): ?>
    <div class="detail-row">
        <label>Review Notes:</label>
        <div style="background: #f5f5f5; padding: 10px; border-radius: 4px; margin-top: 5px;">
            <?php echo nl2br(htmlspecialchars($request['review_notes'])); ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.request-details {
    font-size: 14px;
}

.detail-row {
    display: flex;
    margin-bottom: 12px;
    align-items: flex-start;
}

.detail-row label {
    font-weight: bold;
    min-width: 120px;
    margin-right: 15px;
    color: #555;
}

.detail-row span {
    flex: 1;
}

.status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-approved {
    background: #d4edda;
    color: #155724;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}
</style>
