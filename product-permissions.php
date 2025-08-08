<?php
require_once 'includes/config.php';
requireRole('accountant');

$page_title = 'Product Permission Requests';

$success = '';
$error = '';

// Handle permission request review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = sanitizeInput($_POST['action']);
        $request_id = (int)($_POST['request_id'] ?? 0);
        
        if ($action === 'review_request') {
            $decision = sanitizeInput($_POST['decision'] ?? '');
            $review_notes = sanitizeInput($_POST['review_notes'] ?? '');
            
            if (!in_array($decision, ['approved', 'rejected'])) {
                $error = 'Please select a valid decision.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Get request details
                    $stmt = $pdo->prepare("
                        SELECT * FROM product_permission_requests 
                        WHERE id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$request_id]);
                    $request = $stmt->fetch();
                    
                    if (!$request) {
                        throw new Exception('Request not found or already processed.');
                    }
                    
                    // Update request status
                    $stmt = $pdo->prepare("
                        UPDATE product_permission_requests 
                        SET status = ?, accountant_id = ?, review_notes = ?, reviewed_at = NOW(), updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$decision, $_SESSION['user_id'], $review_notes, $request_id]);
                    
                    // If approved, add product to inventory
                    if ($decision === 'approved') {
                        // Check if product already exists with same code
                        $stmt = $pdo->prepare("
                            SELECT id, name, stock_quantity FROM products 
                            WHERE product_code = ? OR sku = ?
                        ");
                        $stmt->execute([$request['product_code'], $request['product_code']]);
                        $existing_product = $stmt->fetch();
                        
                        if ($existing_product) {
                            // Update existing product quantity
                            $stmt = $pdo->prepare("
                                UPDATE products 
                                SET stock_quantity = stock_quantity + ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([$request['quantity'], $existing_product['id']]);
                            
                            // Log inventory transaction
                            $stmt = $pdo->prepare("
                                INSERT INTO inventory_transactions 
                                (product_id, transaction_type, quantity_change, request_id, notes, created_by)
                                VALUES (?, 'restock', ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $existing_product['id'], 
                                $request['quantity'], 
                                $request_id,
                                "Restocked via delivery request from {$request['supplier_name']}",
                                $_SESSION['user_id']
                            ]);
                            
                            $inventory_action = "Added {$request['quantity']} units to existing product '{$existing_product['name']}'. New total: " . ($existing_product['stock_quantity'] + $request['quantity']);
                        } else {
                            // Add new product to inventory
                            $stmt = $pdo->prepare("
                                INSERT INTO products 
                                (name, category, price, stock_quantity, sku, product_code, description)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $request['product_name'],
                                $request['category'],
                                $request['selling_price'],
                                $request['quantity'],
                                $request['product_code'],
                                $request['product_code'],
                                'Added via delivery request from ' . $request['supplier_name']
                            ]);
                            
                            $new_product_id = $pdo->lastInsertId();
                            
                            // Log inventory transaction for new product
                            $stmt = $pdo->prepare("
                                INSERT INTO inventory_transactions 
                                (product_id, transaction_type, quantity_change, request_id, notes, created_by)
                                VALUES (?, 'restock', ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $new_product_id, 
                                $request['quantity'], 
                                $request_id,
                                "New product added via delivery request from {$request['supplier_name']}",
                                $_SESSION['user_id']
                            ]);
                            
                            $inventory_action = "Added new product '{$request['product_name']}' with {$request['quantity']} units to inventory.";
                        }
                    }
                    
                    $pdo->commit();
                    
                    if ($decision === 'approved') {
                        $success = "Request has been approved successfully! " . $inventory_action;
                    } else {
                        $success = "Request has been rejected successfully!";
                    }
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    $error = 'Error processing request: ' . $e->getMessage();
                } catch (PDOException $e) {
                    $pdo->rollback();
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get filter parameters
$status_filter = sanitizeInput($_GET['status'] ?? '');
$delivery_man_filter = (int)($_GET['delivery_man'] ?? 0);

// Build query for permission requests
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "pr.status = ?";
    $params[] = $status_filter;
}

if ($delivery_man_filter) {
    $where_conditions[] = "pr.delivery_man_id = ?";
    $params[] = $delivery_man_filter;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

try {
    // Get permission requests
    $stmt = $pdo->prepare("
        SELECT pr.*, u.full_name as delivery_man_name, u.username as delivery_man_username
        FROM product_permission_requests pr
        JOIN users u ON pr.delivery_man_id = u.id
        $where_sql
        ORDER BY 
            CASE pr.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                WHEN 'rejected' THEN 3 
            END,
            pr.created_at DESC
    ");
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
    
    // Get delivery men for filter
    $stmt = $pdo->query("
        SELECT DISTINCT u.id, u.full_name 
        FROM users u 
        JOIN product_permission_requests pr ON u.id = pr.delivery_man_id
        WHERE u.role = 'storeman'
        ORDER BY u.full_name
    ");
    $delivery_men = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $requests = [];
    $delivery_men = [];
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-clipboard-check"></i>
            Product Permission Requests
        </h1>
        <div class="stats">
            <?php
            $pending_count = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
            $approved_count = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
            $rejected_count = count(array_filter($requests, fn($r) => $r['status'] === 'rejected'));
            ?>
            <div class="stat">
                <span class="stat-value"><?php echo $pending_count; ?></span>
                <span class="stat-label">Pending</span>
            </div>
            <div class="stat">
                <span class="stat-value"><?php echo $approved_count; ?></span>
                <span class="stat-label">Approved</span>
            </div>
            <div class="stat">
                <span class="stat-value"><?php echo $rejected_count; ?></span>
                <span class="stat-label">Rejected</span>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card">
        <div class="card-content">
            <form method="GET" class="search-row">
                <div class="form-group">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <select name="delivery_man" class="form-select">
                        <option value="">All Delivery Men</option>
                        <?php foreach ($delivery_men as $dm): ?>
                        <option value="<?php echo $dm['id']; ?>" 
                                <?php echo $delivery_man_filter === $dm['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dm['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    Filter
                </button>
                <?php if ($status_filter || $delivery_man_filter): ?>
                <a href="product-permissions.php" class="btn btn-outline">
                    <i class="fas fa-times"></i>
                    Clear
                </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Requests List -->
    <div class="card">
        <div class="card-content">
            <?php if (!empty($requests)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Request Date</th>
                            <th>Delivery Man</th>
                            <th>Product Details</th>
                            <th>Supplier</th>
                            <th>Price & Qty</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>#<?php echo $request['id']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($request['delivery_man_name']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($request['product_name']); ?></strong><br>
                                <small>Code: <?php echo htmlspecialchars($request['product_code']); ?></small><br>
                                <small>Category: <?php echo htmlspecialchars($request['category']); ?></small><br>
                                <?php 
                                // Check if product already exists
                                $check_stmt = $pdo->prepare("SELECT id, stock_quantity FROM products WHERE product_code = ? OR sku = ?");
                                $check_stmt->execute([$request['product_code'], $request['product_code']]);
                                $existing_product_check = $check_stmt->fetch();
                                if ($existing_product_check): ?>
                                    <small style="color: var(--primary-color); font-weight: bold;">📦 Additional Stock (Current: <?php echo $existing_product_check['stock_quantity']; ?>)</small>
                                <?php else: ?>
                                    <small style="color: var(--warning-color); font-weight: bold;">🆕 New Product</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($request['supplier_name']); ?></td>
                            <td>
                                MRP: ৳<?php echo number_format($request['mrp'], 2); ?><br>
                                Selling: ৳<?php echo number_format($request['selling_price'], 2); ?><br>
                                Qty: <?php echo $request['quantity']; ?>
                            </td>
                            <td>
                                <span class="status status-<?php 
                                    echo $request['status'] === 'approved' ? 'approved' : 
                                        ($request['status'] === 'rejected' ? 'rejected' : 'pending'); 
                                ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-outline btn-sm view-details" 
                                            data-id="<?php echo $request['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                        View
                                    </button>
                                    <?php if ($request['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-success btn-sm review-request" 
                                            data-id="<?php echo $request['id']; ?>"
                                            data-action="approve">
                                        <i class="fas fa-check"></i>
                                        Approve
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm review-request" 
                                            data-id="<?php echo $request['id']; ?>"
                                            data-action="reject">
                                        <i class="fas fa-times"></i>
                                        Reject
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-clipboard-list" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 20px;"></i>
                <h3 style="margin-bottom: 10px;">No Requests Found</h3>
                <p style="color: var(--text-secondary);">
                    <?php if ($status_filter || $delivery_man_filter): ?>
                        No requests match your filter criteria.
                    <?php else: ?>
                        No product permission requests have been submitted yet.
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Request Details Modal -->
<div id="detailsModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Request Details</h3>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body" id="requestDetails">
            <!-- Details will be loaded here -->
        </div>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="reviewModalTitle">Review Request</h3>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="reviewForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="review_request">
                <input type="hidden" name="request_id" id="reviewRequestId">
                <input type="hidden" name="decision" id="reviewDecision">
                
                <div id="requestSummary" class="request-summary">
                    <!-- Request summary will be loaded here -->
                </div>
                
                <div class="form-group">
                    <label for="review_notes">Review Notes</label>
                    <textarea id="review_notes" name="review_notes" class="form-input" rows="4" 
                              placeholder="Add notes for your decision (optional)"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                    <button type="submit" class="btn" id="reviewSubmitBtn">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// View request details
document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', function() {
        const requestId = this.dataset.id;
        showRequestDetails(requestId);
    });
});

// Review request
document.querySelectorAll('.review-request').forEach(button => {
    button.addEventListener('click', function() {
        const requestId = this.dataset.id;
        const action = this.dataset.action;
        showReviewModal(requestId, action);
    });
});

function showRequestDetails(requestId) {
    fetch(`ajax/get-request-details.php?id=${requestId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('requestDetails').innerHTML = html;
            document.getElementById('detailsModal').style.display = 'flex';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading request details');
        });
}

function showReviewModal(requestId, action) {
    fetch(`ajax/get-request-summary.php?id=${requestId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('requestSummary').innerHTML = html;
            document.getElementById('reviewRequestId').value = requestId;
            document.getElementById('reviewDecision').value = action === 'approve' ? 'approved' : 'rejected';
            
            const title = action === 'approve' ? 'Approve Request' : 'Reject Request';
            document.getElementById('reviewModalTitle').textContent = title;
            
            const submitBtn = document.getElementById('reviewSubmitBtn');
            submitBtn.textContent = action === 'approve' ? 'Approve' : 'Reject';
            submitBtn.className = action === 'approve' ? 'btn btn-success' : 'btn btn-danger';
            
            document.getElementById('reviewModal').style.display = 'flex';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading request details');
        });
}

// Close modals
document.querySelectorAll('.close-modal').forEach(button => {
    button.addEventListener('click', function() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
    });
});

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>

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

.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.request-summary {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.modal-body {
    padding: 20px;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #999;
}

.close-modal:hover {
    color: #333;
}

@media (max-width: 768px) {
    .action-buttons {
        flex-direction: column;
    }
    
    .stats {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
