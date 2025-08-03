<?php
require_once 'includes/config.php';
requireRole('moderator');

$page_title = 'Products';

// Search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$category = sanitizeInput($_GET['category'] ?? '');

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ? OR sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where_conditions[] = "category = ?";
    $params[] = $category;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

try {
    // Get products
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        $where_sql 
        ORDER BY name ASC
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get categories for filter
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $products = [];
    $categories = [];
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-box"></i>
            Products Inventory
        </h1>
        <a href="create-booking.php" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Create Booking
        </a>
    </div>
    
    <!-- Search and Filters -->
    <div class="search-filters">
        <form method="GET" class="search-row">
            <div class="search-input">
                <i class="fas fa-search"></i>
                <input type="text" name="search" id="searchInput" class="form-input" 
                       placeholder="Search products by name, description, or SKU..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" 
                            <?php echo $category === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i>
                Filter
            </button>
        </form>
    </div>
    
    <!-- Products Grid -->
    <?php if (!empty($products)): ?>
    <div class="grid grid-3">
        <?php foreach ($products as $product): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title" style="font-size: 1.1rem;">
                    <?php echo htmlspecialchars($product['name']); ?>
                </h3>
                <span class="status <?php echo $product['stock_quantity'] < 10 ? 'status-rejected' : 'status-approved'; ?>">
                    Stock: <?php echo $product['stock_quantity']; ?>
                </span>
            </div>
            
            <div class="card-content">
                <?php if ($product['description']): ?>
                <p style="margin-bottom: 15px; color: var(--text-secondary);">
                    <?php echo htmlspecialchars($product['description']); ?>
                </p>
                <?php endif; ?>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <span style="font-size: 1.25rem; font-weight: 600; color: var(--primary-color);">
                        <?php echo formatCurrency($product['price']); ?>
                    </span>
                    
                    <?php if ($product['category']): ?>
                    <span style="font-size: 0.875rem; color: var(--text-secondary); background: var(--background-color); padding: 4px 8px; border-radius: 4px;">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($product['sku']): ?>
                <div style="margin-bottom: 15px;">
                    <small style="color: var(--text-secondary);">
                        SKU: <?php echo htmlspecialchars($product['sku']); ?>
                    </small>
                </div>
                <?php endif; ?>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-primary btn-sm select-product" 
                            data-id="<?php echo $product['id']; ?>"
                            data-name="<?php echo htmlspecialchars($product['name']); ?>"
                            data-price="<?php echo $product['price']; ?>"
                            data-stock="<?php echo $product['stock_quantity']; ?>">
                        <i class="fas fa-plus"></i>
                        Select for Booking
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php else: ?>
    <div class="card">
        <div class="card-content" style="text-align: center; padding: 60px 20px;">
            <i class="fas fa-box-open" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 20px;"></i>
            <h3 style="margin-bottom: 10px;">No Products Found</h3>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                <?php if ($search || $category): ?>
                    No products match your search criteria. Try adjusting your filters.
                <?php else: ?>
                    No products available in the inventory.
                <?php endif; ?>
            </p>
            <?php if ($search || $category): ?>
            <a href="products.php" class="btn btn-outline">
                <i class="fas fa-times"></i>
                Clear Filters
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Selected Products Modal -->
<div id="selectedProductsModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 70vh; overflow-y: auto;">
        <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Selected Products</h3>
            <button type="button" id="closeModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        
        <div id="selectedProductsList"></div>
        
        <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: end;">
            <button type="button" id="clearSelection" class="btn btn-secondary">
                Clear All
            </button>
            <button type="button" id="proceedToBooking" class="btn btn-primary">
                Create Booking
            </button>
        </div>
    </div>
</div>

<script>
// Selected products management
let selectedProducts = [];

document.addEventListener('DOMContentLoaded', function() {
    const selectButtons = document.querySelectorAll('.select-product');
    const modal = document.getElementById('selectedProductsModal');
    const closeModal = document.getElementById('closeModal');
    const clearSelection = document.getElementById('clearSelection');
    const proceedToBooking = document.getElementById('proceedToBooking');
    const selectedProductsList = document.getElementById('selectedProductsList');
    
    // Add product to selection
    selectButtons.forEach(button => {
        button.addEventListener('click', function() {
            const product = {
                id: this.dataset.id,
                name: this.dataset.name,
                price: parseFloat(this.dataset.price),
                stock: parseInt(this.dataset.stock),
                quantity: 1
            };
            
            // Check if already selected
            const existingIndex = selectedProducts.findIndex(p => p.id === product.id);
            if (existingIndex >= 0) {
                selectedProducts[existingIndex].quantity++;
            } else {
                selectedProducts.push(product);
            }
            
            updateSelectedProductsList();
            modal.style.display = 'flex';
        });
    });
    
    // Close modal
    closeModal.addEventListener('click', () => {
        modal.style.display = 'none';
    });
    
    // Clear selection
    clearSelection.addEventListener('click', () => {
        selectedProducts = [];
        updateSelectedProductsList();
    });
    
    // Proceed to booking
    proceedToBooking.addEventListener('click', () => {
        if (selectedProducts.length > 0) {
            // Store selected products in session storage
            sessionStorage.setItem('selectedProducts', JSON.stringify(selectedProducts));
            window.location.href = 'create-booking.php';
        }
    });
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    function updateSelectedProductsList() {
        if (selectedProducts.length === 0) {
            selectedProductsList.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">No products selected</p>';
            return;
        }
        
        let html = '<div style="display: flex; flex-direction: column; gap: 10px;">';
        let total = 0;
        
        selectedProducts.forEach((product, index) => {
            const subtotal = product.price * product.quantity;
            total += subtotal;
            
            html += `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px;">
                    <div>
                        <strong>${product.name}</strong><br>
                        <small>$${product.price.toFixed(2)} each</small>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button type="button" onclick="updateQuantity(${index}, -1)" style="background: var(--error-color); color: white; border: none; border-radius: 4px; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer;">-</button>
                        <span style="min-width: 30px; text-align: center;">${product.quantity}</span>
                        <button type="button" onclick="updateQuantity(${index}, 1)" style="background: var(--success-color); color: white; border: none; border-radius: 4px; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer;">+</button>
                        <button type="button" onclick="removeProduct(${index})" style="background: var(--error-color); color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer; margin-left: 10px;">Remove</button>
                    </div>
                </div>
            `;
        });
        
        html += `</div>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color); text-align: right;">
                    <strong>Total: $${total.toFixed(2)}</strong>
                </div>`;
        
        selectedProductsList.innerHTML = html;
    }
    
    // Make functions global for onclick handlers
    window.updateQuantity = function(index, change) {
        selectedProducts[index].quantity += change;
        if (selectedProducts[index].quantity <= 0) {
            selectedProducts.splice(index, 1);
        }
        updateSelectedProductsList();
    };
    
    window.removeProduct = function(index) {
        selectedProducts.splice(index, 1);
        updateSelectedProductsList();
    };
});
</script>

<?php include 'includes/footer.php'; ?>
