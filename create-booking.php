<?php
require_once 'includes/config.php';
requireRole('moderator');

$page_title = 'Create Booking';

$success = '';
$error = '';

// Get products for selection
try {
    $stmt = $pdo->query("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY name ASC");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
    $error = 'Failed to load products.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $customer_name = sanitizeInput($_POST['customer_name'] ?? '');
        $customer_phone = sanitizeInput($_POST['customer_phone'] ?? '');
        $customer_address = sanitizeInput($_POST['customer_address'] ?? '');
        $payment_type = sanitizeInput($_POST['payment_type'] ?? '');
        $items = $_POST['items'] ?? [];
        
        // Validation
        if (empty($customer_name) || empty($customer_phone) || empty($customer_address) || empty($payment_type)) {
            $error = 'Please fill in all required customer details.';
        } elseif (empty($items) || !is_array($items)) {
            $error = 'Please select at least one product.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Calculate total amount
                $total_amount = 0;
                $valid_items = [];
                
                foreach ($items as $item) {
                    if (empty($item['product_id']) || empty($item['quantity']) || $item['quantity'] <= 0) {
                        continue;
                    }
                    
                    // Get product details and check stock
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                    $stmt->execute([$item['product_id']]);
                    $product = $stmt->fetch();
                    
                    if (!$product) {
                        throw new Exception('Invalid product selected.');
                    }
                    
                    if ($product['stock_quantity'] < $item['quantity']) {
                        throw new Exception("Insufficient stock for {$product['name']}. Available: {$product['stock_quantity']}, Requested: {$item['quantity']}");
                    }
                    
                    $unit_price = $product['price'];
                    $amount = $unit_price * $item['quantity'];
                    $total_amount += $amount;
                    
                    $valid_items[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $unit_price,
                        'amount' => $amount
                    ];
                }
                
                if (empty($valid_items)) {
                    throw new Exception('No valid items in the booking.');
                }
                
                // Create booking
                $stmt = $pdo->prepare("
                    INSERT INTO bookings (customer_name, customer_phone, customer_address, payment_type, amount, moderator_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $customer_name,
                    $customer_phone,
                    $customer_address,
                    $payment_type,
                    $total_amount,
                    $_SESSION['user_id']
                ]);
                
                $booking_id = $pdo->lastInsertId();
                
                // Add booking items
                $stmt = $pdo->prepare("
                    INSERT INTO booking_items (booking_id, product_id, quantity, unit_price)
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($valid_items as $item) {
                    $stmt->execute([
                        $booking_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_price']
                    ]);
                }
                
                // Create payment record
                $payment_status = $payment_type === 'Online Paid' ? 'paid' : 'pending';
                $stmt = $pdo->prepare("
                    INSERT INTO payments (booking_id, payment_status, updated_by)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$booking_id, $payment_status, $_SESSION['user_id']]);
                
                $pdo->commit();
                
                $_SESSION['success'] = "Booking #$booking_id created successfully and sent for approval.";
                redirect('dashboard.php');
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Failed to create booking. Please try again.';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-plus-circle"></i>
            Create New Booking
        </h1>
        <a href="products.php" class="btn btn-outline">
            <i class="fas fa-search"></i>
            Browse Products
        </a>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" id="bookingForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <!-- Existing Customer Lookup -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-search"></i>
                    Existing Customer Lookup
                </h2>
                <button type="button" id="toggleCustomerLookup" class="btn btn-outline btn-sm">
                    <i class="fas fa-eye"></i>
                    Check Existing Customer
                </button>
            </div>
            <div class="card-content" id="customerLookupSection" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Customer Phone Number</label>
                    <div style="display: flex; gap: 10px; align-items: end;">
                        <input type="tel" id="lookupPhone" class="form-input" 
                               placeholder="Enter phone number to search..." style="flex: 1;">
                        <button type="button" id="searchCustomer" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </div>
                </div>
                
                <div id="customerLookupResults" style="display: none; margin-top: 20px;">
                    <!-- Customer lookup results will be displayed here -->
                </div>
            </div>
        </div>
        
        <div class="grid grid-2">
            <!-- Customer Information -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-user"></i>
                        Customer Information
                    </h2>
                </div>
                <div class="card-content">
                    <div class="form-group">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" name="customer_name" class="form-input" required
                               value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                               placeholder="Enter customer name">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" name="customer_phone" class="form-input" required
                               value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>"
                               placeholder="Enter phone number">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address *</label>
                        <textarea name="customer_address" class="form-textarea" required
                                  placeholder="Enter complete address"><?php echo htmlspecialchars($_POST['customer_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Payment Type *</label>
                        <select name="payment_type" class="form-select" required>
                            <option value="">Select Payment Type</option>
                            <option value="Online Paid" <?php echo ($_POST['payment_type'] ?? '') === 'Online Paid' ? 'selected' : ''; ?>>
                                Online Paid
                            </option>
                            <option value="Cash on Delivery" <?php echo ($_POST['payment_type'] ?? '') === 'Cash on Delivery' ? 'selected' : ''; ?>>
                                Cash on Delivery
                            </option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-calculator"></i>
                        Order Summary
                    </h2>
                </div>
                <div class="card-content">
                    <div id="orderSummary">
                        <p style="text-align: center; color: var(--text-secondary); padding: 40px 20px;">
                            <i class="fas fa-shopping-cart" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                            Add products to see order summary
                        </p>
                    </div>
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 1.25rem; font-weight: 600;">
                            <span>Total Amount:</span>
                            <span id="totalDisplay">$0.00</span>
                        </div>
                        <input type="hidden" name="total_amount" id="totalAmount" value="0">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Products Selection -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-box"></i>
                    Select Products
                </h2>
                <button type="button" id="addItem" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i>
                    Add Product
                </button>
            </div>
            <div class="card-content">
                <div id="itemsContainer">
                    <!-- Product items will be added here -->
                    <div class="item-row">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Product *</label>
                                <div class="searchable-select-container">
                                    <input type="text" class="form-input product-search" placeholder="Search products..." autocomplete="off">
                                    <select name="items[0][product_id]" class="form-select product-select" required>
                                        <option value="">Select Product</option>
                                        <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['id']; ?>" 
                                                data-price="<?php echo $product['price']; ?>"
                                                data-stock="<?php echo $product['stock_quantity']; ?>"
                                                data-search-text="<?php echo strtolower(($product['product_code'] ? '[' . $product['product_code'] . '] ' : '') . $product['name']); ?>">
                                            <?php if ($product['product_code']): ?>
                                            [<?php echo htmlspecialchars($product['product_code']); ?>] 
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($product['name']); ?> - 
                                            <?php echo formatCurrency($product['price']); ?>
                                            (Stock: <?php echo $product['stock_quantity']; ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="search-dropdown" style="display: none;">
                                        <div class="search-results"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Quantity *</label>
                                <input type="number" name="items[0][quantity]" class="form-input quantity-input" 
                                       min="1" required placeholder="Qty">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Unit Price</label>
                                <input type="number" name="items[0][unit_price]" class="form-input unit-price" 
                                       step="0.01" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Amount</label>
                                <input type="number" name="items[0][amount]" class="form-input item-amount" 
                                       step="0.01" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-paper-plane"></i>
                Submit Booking Request
            </button>
            <a href="dashboard.php" class="btn btn-secondary btn-lg" style="margin-left: 15px;">
                <i class="fas fa-times"></i>
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
// Store product data for JavaScript
window.productPrices = {};
<?php foreach ($products as $product): ?>
window.productPrices[<?php echo $product['id']; ?>] = <?php echo $product['price']; ?>;
<?php endforeach; ?>

document.addEventListener('DOMContentLoaded', function() {
    let itemCount = 1;
    const itemsContainer = document.getElementById('itemsContainer');
    const addItemButton = document.getElementById('addItem');
    
    // Customer lookup functionality
    const toggleCustomerLookup = document.getElementById('toggleCustomerLookup');
    const customerLookupSection = document.getElementById('customerLookupSection');
    const searchCustomerBtn = document.getElementById('searchCustomer');
    const lookupPhoneInput = document.getElementById('lookupPhone');
    const lookupResults = document.getElementById('customerLookupResults');
    
    // Toggle customer lookup section
    toggleCustomerLookup.addEventListener('click', function() {
        const isVisible = customerLookupSection.style.display !== 'none';
        customerLookupSection.style.display = isVisible ? 'none' : 'block';
        this.innerHTML = isVisible ? 
            '<i class="fas fa-eye"></i> Check Existing Customer' : 
            '<i class="fas fa-eye-slash"></i> Hide Customer Lookup';
    });
    
    // Search customer
    searchCustomerBtn.addEventListener('click', searchCustomer);
    lookupPhoneInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchCustomer();
        }
    });
    
    function searchCustomer() {
        const phone = lookupPhoneInput.value.trim();
        if (!phone) {
            alert('Please enter a phone number');
            return;
        }
        
        // Show loading state
        searchCustomerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
        searchCustomerBtn.disabled = true;
        lookupResults.style.display = 'none';
        
        // Make AJAX request
        fetch('ajax/customer-lookup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ phone: phone })
        })
        .then(response => response.json())
        .then(data => {
            displayCustomerResults(data);
        })
        .catch(error => {
            console.error('Error:', error);
            lookupResults.innerHTML = `
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    An error occurred while searching. Please try again.
                </div>
            `;
            lookupResults.style.display = 'block';
        })
        .finally(() => {
            // Reset button state
            searchCustomerBtn.innerHTML = '<i class="fas fa-search"></i> Search';
            searchCustomerBtn.disabled = false;
        });
    }
    
    function displayCustomerResults(data) {
        if (!data.found) {
            lookupResults.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    ${data.message}
                </div>
            `;
        } else {
            lookupResults.innerHTML = generateCustomerResultsHTML(data);
        }
        lookupResults.style.display = 'block';
    }
    
    function generateCustomerResultsHTML(data) {
        const latestOrder = data.latest_order;
        const customer = data.customer;
        const stats = data.stats;
        
        let itemsHTML = latestOrder.items.map(item => `
            <tr>
                <td>${item.product_code ? '[' + item.product_code + '] ' : ''}${item.product_name}</td>
                <td style="text-align: center;">${item.quantity}</td>
                <td style="text-align: right;">$${parseFloat(item.unit_price).toFixed(2)}</td>
                <td style="text-align: right;">$${parseFloat(item.total_price).toFixed(2)}</td>
            </tr>
        `).join('');
        
        return `
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Existing Customer Found!</strong>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user"></i>
                            Customer Information
                        </h3>
                    </div>
                    <div class="card-content">
                        <p><strong>Name:</strong> ${customer.name}</p>
                        <p><strong>Phone:</strong> ${customer.phone}</p>
                        <p><strong>Address:</strong> ${customer.address}</p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="fillCustomerInfo('${customer.name}', '${customer.phone}', '${customer.address.replace(/'/g, "\\'")}')">
                            <i class="fas fa-copy"></i>
                            Use This Information
                        </button>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-bar"></i>
                            Customer Statistics
                        </h3>
                    </div>
                    <div class="card-content">
                        <p><strong>Total Orders:</strong> ${stats.total_orders}</p>
                        <p><strong>Total Spent:</strong> $${parseFloat(stats.total_spent).toFixed(2)}</p>
                        <p><strong>First Order:</strong> ${new Date(stats.first_order_date).toLocaleDateString()}</p>
                        <p><strong>Last Order:</strong> ${new Date(stats.last_order_date).toLocaleDateString()}</p>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-receipt"></i>
                        Latest Order Details (ID: #${latestOrder.booking_id})
                    </h3>
                </div>
                <div class="card-content">
                    <div style="margin-bottom: 15px;">
                        <p><strong>Order Date:</strong> ${new Date(latestOrder.order_date).toLocaleDateString()}</p>
                        <p><strong>Delivery Date:</strong> ${latestOrder.delivery_date ? new Date(latestOrder.delivery_date).toLocaleDateString() : 'N/A'}</p>
                        <p><strong>Payment Type:</strong> ${latestOrder.payment_type}</p>
                        <p><strong>Total Amount:</strong> $${parseFloat(latestOrder.amount).toFixed(2)}</p>
                    </div>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th style="text-align: center;">Quantity</th>
                                <th style="text-align: right;">Unit Price</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHTML}
                        </tbody>
                    </table>
                </div>
            </div>
            
            ${data.order_history.length > 1 ? `
                <div class="card" style="margin-top: 15px;">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            Order History (${data.order_history.length} orders)
                        </h3>
                    </div>
                    <div class="card-content">
                        <div style="max-height: 200px; overflow-y: auto;">
                            ${data.order_history.map(order => `
                                <div style="padding: 10px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;">
                                    <span>Order #${order.booking_id} - ${new Date(order.order_date).toLocaleDateString()}</span>
                                    <span><strong>$${parseFloat(order.amount).toFixed(2)}</strong> (${order.total_items} items)</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            ` : ''}
        `;
    }
    
    // Global function to fill customer information
    window.fillCustomerInfo = function(name, phone, address) {
        document.querySelector('input[name="customer_name"]').value = name;
        document.querySelector('input[name="customer_phone"]').value = phone;
        document.querySelector('textarea[name="customer_address"]').value = address;
        
        // Scroll to customer form
        document.querySelector('input[name="customer_name"]').scrollIntoView({ behavior: 'smooth' });
        
        // Show success message
        const successMsg = document.createElement('div');
        successMsg.className = 'alert alert-success';
        successMsg.innerHTML = '<i class="fas fa-check"></i> Customer information filled successfully!';
        successMsg.style.position = 'fixed';
        successMsg.style.top = '20px';
        successMsg.style.right = '20px';
        successMsg.style.zIndex = '9999';
        document.body.appendChild(successMsg);
        
        setTimeout(() => {
            successMsg.remove();
        }, 3000);
    };
    
    // Check for pre-selected products from session storage
    const selectedProducts = sessionStorage.getItem('selectedProducts');
    if (selectedProducts) {
        const products = JSON.parse(selectedProducts);
        // Clear session storage
        sessionStorage.removeItem('selectedProducts');
        
        // Clear existing item row
        itemsContainer.innerHTML = '';
        itemCount = 0;
        
        // Add each selected product
        products.forEach(product => {
            addItemRow(product);
        });
        
        updateOrderSummary();
    } else {
        // Initialize searchable select for the default first item
        const firstSearchContainer = itemsContainer.querySelector('.searchable-select-container');
        if (firstSearchContainer) {
            initializeSearchableSelect(firstSearchContainer);
        }
    }
    
    // Add item button
    addItemButton.addEventListener('click', function() {
        addItemRow();
    });
    
    // Handle form submission validation
    const bookingForm = document.querySelector('#bookingForm');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            // Validate customer information
            const customerName = document.querySelector('input[name="customer_name"]').value.trim();
            const customerPhone = document.querySelector('input[name="customer_phone"]').value.trim();
            const customerAddress = document.querySelector('textarea[name="customer_address"]').value.trim();
            const paymentType = document.querySelector('select[name="payment_type"]').value;
            
            if (!customerName || !customerPhone || !customerAddress || !paymentType) {
                e.preventDefault();
                alert('Please fill in all customer information fields.');
                return false;
            }
            
            // Check if there are valid items
            const validItems = hasValidItems();
            if (!validItems) {
                e.preventDefault();
                alert('Please add at least one product to your booking.');
                return false;
            }
        });
    }
    
    // Handle item changes
    itemsContainer.addEventListener('change', function(e) {
        if (e.target.classList.contains('product-select')) {
            updateProductPriceFromSelect(e.target);
        }
        updateItemAmount(e.target.closest('.item-row'));
        updateOrderSummary();
    });
    
    // Handle remove item
    itemsContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item')) {
            if (itemsContainer.children.length > 1) {
                e.target.closest('.item-row').remove();
                updateOrderSummary();
            }
        }
    });
    
    // Initial calculation
    updateOrderSummary();
    
    function initializeSearchableSelect(container) {
        const searchInput = container.querySelector('.product-search');
        const select = container.querySelector('.product-select');
        const dropdown = container.querySelector('.search-dropdown');
        const resultsContainer = container.querySelector('.search-results');
        
        // Handle search input
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            if (searchTerm.length > 0) {
                // Show search results
                filterProducts(searchTerm, select, resultsContainer, dropdown);
                dropdown.style.display = 'block';
                
                // Clear dropdown selection if search is active
                if (select.value && !select.querySelector(`option[value="${select.value}"]`).textContent.toLowerCase().includes(searchTerm)) {
                    select.value = '';
                    updateProductPriceFromSelect(select);
                }
            } else {
                // Hide search dropdown when search is empty
                dropdown.style.display = 'none';
            }
        });
        
        // Handle search input focus
        searchInput.addEventListener('focus', function() {
            const searchTerm = this.value.toLowerCase();
            if (searchTerm.length > 0) {
                filterProducts(searchTerm, select, resultsContainer, dropdown);
                dropdown.style.display = 'block';
            }
        });
        
        // Handle dropdown selection change
        select.addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.querySelector(`option[value="${this.value}"]`);
                if (selectedOption) {
                    searchInput.value = selectedOption.textContent.trim();
                }
            } else {
                searchInput.value = '';
            }
            dropdown.style.display = 'none';
        });
        
        // Handle clicks outside to close dropdown
        document.addEventListener('click', function(e) {
            if (!container.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Handle result selection
        resultsContainer.addEventListener('click', function(e) {
            const resultItem = e.target.closest('.search-result-item');
            if (resultItem) {
                const optionValue = resultItem.dataset.value;
                const optionText = resultItem.textContent.trim();
                
                // Update hidden select
                select.value = optionValue;
                
                // Update search input
                searchInput.value = optionText;
                
                // Hide dropdown
                dropdown.style.display = 'none';
                
                // Trigger change event on select
                const changeEvent = new Event('change', { bubbles: true });
                select.dispatchEvent(changeEvent);
            }
        });
        
        // Handle escape key to close dropdown
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dropdown.style.display = 'none';
                this.blur();
            }
        });
        
        // Clear search when dropdown selection is made manually
        select.addEventListener('focus', function() {
            dropdown.style.display = 'none';
        });
    }
    
    // Update the existing updateProductPrice function to work with the new setup
    function updateProductPriceFromSelect(selectElement) {
        const selectedOption = selectElement.selectedOptions[0];
        const unitPriceInput = selectElement.closest('.item-row').querySelector('.unit-price');
        
        if (selectedOption && selectedOption.dataset.price) {
            unitPriceInput.value = selectedOption.dataset.price;
        } else {
            unitPriceInput.value = '';
        }
    }
    
    function filterProducts(searchTerm, select, resultsContainer, dropdown) {
        const options = select.querySelectorAll('option[value!=""]');
        let matchingOptions = [];
        
        // If search term is empty, show all options
        if (!searchTerm) {
            matchingOptions = Array.from(options);
        } else {
            // Filter options based on search term
            matchingOptions = Array.from(options).filter(option => {
                const searchText = option.dataset.searchText || option.textContent.toLowerCase();
                return searchText.includes(searchTerm);
            });
        }
        
        // Create dropdown items
        let resultsHtml = '';
        if (matchingOptions.length === 0) {
            resultsHtml = '<div class="search-no-results">No products found</div>';
        } else {
            matchingOptions.slice(0, 10).forEach(option => { // Limit to 10 results
                resultsHtml += `
                    <div class="search-result-item" data-value="${option.value}">
                        ${option.textContent}
                    </div>
                `;
            });
            
            if (matchingOptions.length > 10) {
                resultsHtml += '<div class="search-more-results">... and ' + (matchingOptions.length - 10) + ' more results</div>';
            }
        }
        
        resultsContainer.innerHTML = resultsHtml;
        dropdown.style.display = 'block';
    }
    
    function addItemRow(preselectedProduct = null) {
        const div = document.createElement('div');
        div.className = 'item-row';
        div.innerHTML = `
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Product *</label>
                    <div class="searchable-select-container">
                        <input type="text" class="form-input product-search" placeholder="Search products..." autocomplete="off">
                        <select name="items[${itemCount}][product_id]" class="form-select product-select" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" 
                                    data-price="<?php echo $product['price']; ?>"
                                    data-stock="<?php echo $product['stock_quantity']; ?>"
                                    data-search-text="<?php echo strtolower(($product['product_code'] ? '[' . $product['product_code'] . '] ' : '') . $product['name']); ?>">
                                <?php if ($product['product_code']): ?>
                                [<?php echo htmlspecialchars($product['product_code']); ?>] 
                                <?php endif; ?>
                                <?php echo htmlspecialchars($product['name']); ?> - 
                                <?php echo formatCurrency($product['price']); ?>
                                (Stock: <?php echo $product['stock_quantity']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="search-dropdown" style="display: none;">
                            <div class="search-results"></div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity *</label>
                    <input type="number" name="items[${itemCount}][quantity]" class="form-input quantity-input" 
                           min="1" required placeholder="Qty">
                </div>
                <div class="form-group">
                    <label class="form-label">Unit Price</label>
                    <input type="number" name="items[${itemCount}][unit_price]" class="form-input unit-price" 
                           step="0.01" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount</label>
                    <div style="display: flex; align-items: end; gap: 10px;">
                        <input type="number" name="items[${itemCount}][amount]" class="form-input item-amount" 
                               step="0.01" readonly>
                        ${itemsContainer.children.length > 0 ? '<button type="button" class="btn btn-error btn-sm remove-item"><i class="fas fa-trash"></i></button>' : ''}
                    </div>
                </div>
            </div>
        `;
        
        itemsContainer.appendChild(div);
        
        // Initialize searchable dropdown for this new item
        initializeSearchableSelect(div.querySelector('.searchable-select-container'));
        
        // If preselected product, set values
        if (preselectedProduct) {
            const select = div.querySelector('.product-select');
            const searchInput = div.querySelector('.product-search');
            const quantityInput = div.querySelector('.quantity-input');
            
            select.value = preselectedProduct.id;
            const selectedOption = select.querySelector(`option[value="${preselectedProduct.id}"]`);
            if (selectedOption) {
                searchInput.value = selectedOption.textContent.trim();
            }
            quantityInput.value = preselectedProduct.quantity;
            
            updateProductPriceFromSelect(select);
            updateItemAmount(div);
        }
        
        itemCount++;
    }
    
    function updateItemAmount(itemRow) {
        const quantityInput = itemRow.querySelector('.quantity-input');
        const unitPriceInput = itemRow.querySelector('.unit-price');
        const amountInput = itemRow.querySelector('.item-amount');
        
        const quantity = parseFloat(quantityInput.value) || 0;
        const unitPrice = parseFloat(unitPriceInput.value) || 0;
        const amount = quantity * unitPrice;
        
        amountInput.value = amount.toFixed(2);
    }
    
    function updateOrderSummary() {
        const orderSummary = document.getElementById('orderSummary');
        const totalDisplay = document.getElementById('totalDisplay');
        const totalAmount = document.getElementById('totalAmount');
        
        const itemRows = itemsContainer.querySelectorAll('.item-row');
        let total = 0;
        let summaryHtml = '';
        
        if (itemRows.length === 0 || !hasValidItems()) {
            orderSummary.innerHTML = `
                <p style="text-align: center; color: var(--text-secondary); padding: 40px 20px;">
                    <i class="fas fa-shopping-cart" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    Add products to see order summary
                </p>
            `;
        } else {
            summaryHtml = '<div style="display: flex; flex-direction: column; gap: 10px;">';
            
            itemRows.forEach(row => {
                const select = row.querySelector('.product-select');
                const quantityInput = row.querySelector('.quantity-input');
                const amountInput = row.querySelector('.item-amount');
                
                if (select.value && quantityInput.value && amountInput.value) {
                    const productName = select.selectedOptions[0].textContent.split(' - ')[0];
                    const quantity = parseInt(quantityInput.value);
                    const amount = parseFloat(amountInput.value);
                    
                    total += amount;
                    
                    summaryHtml += `
                        <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border-color);">
                            <span>${productName} (x${quantity})</span>
                            <span>$${amount.toFixed(2)}</span>
                        </div>
                    `;
                }
            });
            
            summaryHtml += '</div>';
            orderSummary.innerHTML = summaryHtml;
        }
        
        totalDisplay.textContent = '$' + total.toFixed(2);
        totalAmount.value = total.toFixed(2);
    }
    
    function hasValidItems() {
        const itemRows = itemsContainer.querySelectorAll('.item-row');
        for (let row of itemRows) {
            const select = row.querySelector('.product-select');
            const quantityInput = row.querySelector('.quantity-input');
            if (select.value && quantityInput.value) {
                return true;
            }
        }
        return false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
