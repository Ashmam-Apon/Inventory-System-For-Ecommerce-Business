<?php
require_once 'includes/config.php';
requireRole('storeman'); // Assuming delivery man uses storeman role

$page_title = 'Inventory Check & Product Request';

$success = '';
$error = '';

// Handle inventory check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = sanitizeInput($_POST['action']);
        
        if ($action === 'check_inventory') {
            $product_code = sanitizeInput($_POST['product_code'] ?? '');
            $product_name = sanitizeInput($_POST['product_name'] ?? '');
            
            if (empty($product_code) || empty($product_name)) {
                $error = 'Please provide both product code and product name.';
            } else {
                try {
                    // Check if product exists in inventory
                    $stmt = $pdo->prepare("
                        SELECT id, name, category, price, stock_quantity, sku, product_code 
                        FROM products 
                        WHERE (product_code = ? OR sku = ?) AND LOWER(name) = LOWER(?)
                    ");
                    $stmt->execute([$product_code, $product_code, $product_name]);
                    $existing_product = $stmt->fetch();
                    
                    if ($existing_product) {
                        $success = "Product found in inventory! Current stock: {$existing_product['stock_quantity']} units. You can request additional stock below if needed.";
                        $product_found = true;
                        $show_request_form = true; // Always show form to allow additional stock requests
                    } else {
                        // Product doesn't exist, show request form
                        $product_not_found = true;
                        $show_request_form = true;
                    }
                } catch (PDOException $e) {
                    $error = 'Error checking inventory: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'submit_request') {
            $supplier_name = sanitizeInput($_POST['supplier_name'] ?? '');
            $product_code = sanitizeInput($_POST['product_code'] ?? '');
            $product_name = sanitizeInput($_POST['product_name'] ?? '');
            $category = sanitizeInput($_POST['category'] ?? '');
            $mrp = floatval($_POST['mrp'] ?? 0);
            $selling_price = floatval($_POST['selling_price'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 0);
            
            // Validate required fields (product_code is auto-generated, so not required in validation)
            if (empty($supplier_name) || empty($product_name) || 
                empty($category) || $mrp <= 0 || $selling_price <= 0 || $quantity <= 0) {
                $error = 'Please fill all required fields with valid values.';
            } else {
                try {
                    // Generate product code if not provided or empty
                    if (empty($product_code)) {
                        $product_code = generateProductCode($category, $pdo);
                    }
                    
                    // Verify the generated/provided code is unique
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE product_code = ?");
                    $stmt->execute([$product_code]);
                    if ($stmt->fetchColumn() > 0) {
                        // If code already exists, generate a new one
                        $product_code = generateProductCode($category, $pdo);
                    }
                    
                    // Handle invoice image upload
                    $invoice_image = '';
                    if (isset($_FILES['invoice_image']) && $_FILES['invoice_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = 'uploads/invoices/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['invoice_image']['name'], PATHINFO_EXTENSION);
                        $file_name = 'invoice_' . uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES['invoice_image']['tmp_name'], $upload_path)) {
                            $invoice_image = $upload_path;
                        }
                    }
                    
                    // Insert permission request
                    $stmt = $pdo->prepare("
                        INSERT INTO product_permission_requests 
                        (delivery_man_id, supplier_name, product_code, product_name, category, 
                         mrp, selling_price, quantity, invoice_image) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'], $supplier_name, $product_code, $product_name, 
                        $category, $mrp, $selling_price, $quantity, $invoice_image
                    ]);
                    
                    // Check if this is for an existing product
                    $stmt = $pdo->prepare("
                        SELECT name FROM products 
                        WHERE product_code = ? OR sku = ?
                    ");
                    $stmt->execute([$product_code, $product_code]);
                    $existing_check = $stmt->fetch();
                    
                    if ($existing_check) {
                        $success = "Additional stock request submitted successfully! You've requested {$quantity} more units of '{$product_name}'. The accountant will review your request.";
                    } else {
                        $success = "New product permission request submitted successfully! The accountant will review your request for adding '{$product_name}' to inventory.";
                    }
                    
                    // Clear form data
                    $_POST = [];
                    
                } catch (PDOException $e) {
                    $error = 'Error submitting request: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get user's permission requests
try {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.full_name as accountant_name 
        FROM product_permission_requests pr
        LEFT JOIN users u ON pr.accountant_id = u.id
        WHERE pr.delivery_man_id = ?
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $permission_requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $permission_requests = [];
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-search"></i>
            Inventory Check & Product Request
        </h1>
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

    <!-- Inventory Check Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Check Product in Inventory</h2>
        </div>
        <div class="card-content">
            <form method="POST" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="check_inventory">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="product_code">Product Code/SKU *</label>
                        <input type="text" id="product_code" name="product_code" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['product_code'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="product_name">Product Name *</label>
                        <input type="text" id="product_name" name="product_name" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Check Inventory
                </button>
            </form>
        </div>
    </div>

    <!-- Request Permission Form -->
    <?php if (isset($show_request_form) && $show_request_form): ?>
    <div class="card">
        <div class="card-header">
            <?php if (isset($product_found) && $product_found): ?>
            <h2 class="card-title" style="color: var(--success-color);">
                <i class="fas fa-plus-circle"></i>
                Request Additional Stock for Existing Product
            </h2>
            <?php else: ?>
            <h2 class="card-title" style="color: var(--warning-color);">
                <i class="fas fa-exclamation-triangle"></i>
                Product Not Found - Request Permission to Add
            </h2>
            <?php endif; ?>
        </div>
        <div class="card-content">
            <?php if (isset($product_found) && $product_found): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Product Found in Inventory!</strong>
                <div style="margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.2); border-radius: 5px;">
                    <div><strong>Product Code:</strong> <?php echo htmlspecialchars($existing_product['product_code']); ?></div>
                    <div><strong>Product Name:</strong> <?php echo htmlspecialchars($existing_product['name']); ?></div>
                    <div><strong>Category:</strong> <?php echo htmlspecialchars($existing_product['category']); ?></div>
                    <div><strong>Current Stock:</strong> <?php echo $existing_product['stock_quantity']; ?> units</div>
                    <div><strong>Price:</strong> $<?php echo number_format($existing_product['price'], 2); ?></div>
                </div>
                <p style="margin-top: 10px;"><i class="fas fa-plus"></i> You can request additional stock for this existing product using the form below.</p>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                Product "<?php echo htmlspecialchars($_POST['product_name']); ?>" with code "<?php echo htmlspecialchars($_POST['product_code']); ?>" was not found in inventory. Please fill the form below to request permission to add this product.
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="submit_request">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="supplier_name">Supplier Name *</label>
                        <input type="text" id="supplier_name" name="supplier_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="req_product_code">Product Code</label>
                        <input type="text" id="req_product_code" name="product_code" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['product_code'] ?? (isset($existing_product) ? $existing_product['product_code'] : '')); ?>" 
                               readonly style="background-color: #f5f5f5;">
                        <small class="form-help">Will be auto-generated based on category selection</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="req_product_name">Product Name *</label>
                        <input type="text" id="req_product_name" name="product_name" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['product_name'] ?? (isset($existing_product) ? $existing_product['name'] : '')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" class="form-select" required onchange="generateProductCode()">
                            <option value="">Select Category</option>
                            <option value="Electronics" <?php echo (isset($existing_product) && $existing_product['category'] === 'Electronics') ? 'selected' : ''; ?>>Electronics (EC)</option>
                            <option value="Accessories" <?php echo (isset($existing_product) && $existing_product['category'] === 'Accessories') ? 'selected' : ''; ?>>Accessories (AC)</option>
                            <option value="Beauty" <?php echo (isset($existing_product) && $existing_product['category'] === 'Beauty') ? 'selected' : ''; ?>>Beauty (BP)</option>
                            <option value="Saree" <?php echo (isset($existing_product) && $existing_product['category'] === 'Saree') ? 'selected' : ''; ?>>Saree (SR)</option>
                            <option value="Clothing" <?php echo (isset($existing_product) && $existing_product['category'] === 'Clothing') ? 'selected' : ''; ?>>Clothing (CL)</option>
                            <option value="Home & Garden" <?php echo (isset($existing_product) && $existing_product['category'] === 'Home & Garden') ? 'selected' : ''; ?>>Home & Garden (HG)</option>
                            <option value="Sports" <?php echo (isset($existing_product) && $existing_product['category'] === 'Sports') ? 'selected' : ''; ?>>Sports (SP)</option>
                            <option value="Books" <?php echo (isset($existing_product) && $existing_product['category'] === 'Books') ? 'selected' : ''; ?>>Books (BK)</option>
                            <option value="Toys" <?php echo (isset($existing_product) && $existing_product['category'] === 'Toys') ? 'selected' : ''; ?>>Toys (TY)</option>
                            <option value="Jewelry" <?php echo (isset($existing_product) && $existing_product['category'] === 'Jewelry') ? 'selected' : ''; ?>>Jewelry (JW)</option>
                            <option value="Shoes" <?php echo (isset($existing_product) && $existing_product['category'] === 'Shoes') ? 'selected' : ''; ?>>Shoes (SH)</option>
                            <option value="Bags" <?php echo (isset($existing_product) && $existing_product['category'] === 'Bags') ? 'selected' : ''; ?>>Bags (BG)</option>
                            <option value="Other" <?php echo (isset($existing_product) && $existing_product['category'] === 'Other') ? 'selected' : ''; ?>>Other (OT)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="mrp">MRP (৳) *</label>
                        <input type="number" id="mrp" name="mrp" class="form-input" step="0.01" min="0" 
                               value="<?php echo isset($existing_product) ? $existing_product['price'] : ''; ?>" required>
                        <?php if (isset($existing_product)): ?>
                        <small class="form-help">Current price: ৳<?php echo number_format($existing_product['price'], 2); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="selling_price">Selling Price (৳) *</label>
                        <input type="number" id="selling_price" name="selling_price" class="form-input" step="0.01" min="0" 
                               value="<?php echo isset($existing_product) ? $existing_product['price'] : ''; ?>" required>
                        <?php if (isset($existing_product)): ?>
                        <small class="form-help">Current selling price: ৳<?php echo number_format($existing_product['price'], 2); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity">Quantity to Add *</label>
                        <input type="number" id="quantity" name="quantity" class="form-input" min="1" required>
                        <?php if (isset($existing_product)): ?>
                        <small class="form-help">Current stock: <?php echo $existing_product['stock_quantity']; ?> units</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="invoice_image">Invoice Image</label>
                        <input type="file" id="invoice_image" name="invoice_image" class="form-input" accept="image/*">
                        <small class="form-help">Upload invoice image (optional)</small>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i>
                    <?php if (isset($product_found) && $product_found): ?>
                    Request Additional Stock
                    <?php else: ?>
                    Submit Permission Request
                    <?php endif; ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- My Permission Requests -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">My Permission Requests</h2>
        </div>
        <div class="card-content">
            <?php if (!empty($permission_requests)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Request Date</th>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Reviewed By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permission_requests as $request): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($request['product_name']); ?></strong><br>
                                <small>Code: <?php echo htmlspecialchars($request['product_code']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($request['supplier_name']); ?></td>
                            <td><?php echo $request['quantity']; ?></td>
                            <td>৳<?php echo number_format($request['selling_price'], 2); ?></td>
                            <td>
                                <span class="status status-<?php 
                                    echo $request['status'] === 'approved' ? 'approved' : 
                                        ($request['status'] === 'rejected' ? 'rejected' : 'pending'); 
                                ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $request['accountant_name'] ? htmlspecialchars($request['accountant_name']) : '-'; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-outline btn-sm view-request" 
                                        data-id="<?php echo $request['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-inbox" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 15px;"></i>
                <p style="color: var(--text-secondary);">No permission requests yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Request Details Modal -->
<div id="requestModal" class="modal" style="display: none;">
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

<script>
// View request details
document.querySelectorAll('.view-request').forEach(button => {
    button.addEventListener('click', function() {
        const requestId = this.dataset.id;
        showRequestDetails(requestId);
    });
});

function showRequestDetails(requestId) {
    fetch(`ajax/get-request-details.php?id=${requestId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('requestDetails').innerHTML = html;
            document.getElementById('requestModal').style.display = 'flex';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading request details');
        });
}

// Close modal
document.querySelector('.close-modal').addEventListener('click', function() {
    document.getElementById('requestModal').style.display = 'none';
});

// Close modal when clicking outside
document.getElementById('requestModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});

// Generate product code based on category selection
function generateProductCode() {
    const categorySelect = document.getElementById('category');
    const productCodeInput = document.getElementById('req_product_code');
    
    if (!categorySelect.value) {
        productCodeInput.value = '';
        return;
    }
    
    // Show loading state
    productCodeInput.value = 'Generating...';
    productCodeInput.style.color = '#666';
    
    // Use AJAX to generate unique code from server
    fetch('ajax/generate-product-code.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'category=' + encodeURIComponent(categorySelect.value)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            productCodeInput.value = data.product_code;
            productCodeInput.style.color = '#333';
        } else {
            // Fallback to client-side generation
            generateProductCodeFallback();
        }
    })
    .catch(error => {
        console.error('Error generating product code:', error);
        // Fallback to client-side generation
        generateProductCodeFallback();
    });
}

// Fallback client-side generation
function generateProductCodeFallback() {
    const categorySelect = document.getElementById('category');
    const productCodeInput = document.getElementById('req_product_code');
    
    // Category mapping to prefixes
    const categoryMappings = {
        'Electronics': 'EC',
        'Accessories': 'AC',
        'Beauty': 'BP',
        'Saree': 'SR',
        'Clothing': 'CL',
        'Home & Garden': 'HG',
        'Sports': 'SP',
        'Books': 'BK',
        'Toys': 'TY',
        'Jewelry': 'JW',
        'Shoes': 'SH',
        'Bags': 'BG',
        'Other': 'OT'
    };
    
    const prefix = categoryMappings[categorySelect.value] || 'OT';
    
    // Generate 6 random digits
    const randomDigits = Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
    
    // Set the generated code
    productCodeInput.value = prefix + randomDigits;
    productCodeInput.style.color = '#333';
}

// Auto-generate code when page loads if category is already selected
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.getElementById('category');
    if (categorySelect && categorySelect.value) {
        generateProductCode();
    }
});
</script>

<style>
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
</style>

<?php include 'includes/footer.php'; ?>
