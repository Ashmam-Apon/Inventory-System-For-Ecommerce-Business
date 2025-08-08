<?php
require_once 'includes/config.php';
requireRole('storeman');

$page_title = 'Delivery Management';

$success = '';
$error = '';

// Handle delivery status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $action = sanitizeInput($_POST['action']);
        
        if ($action === 'update_delivery') {
            $delivery_company = sanitizeInput($_POST['delivery_company'] ?? '');
            $tracking_number = sanitizeInput($_POST['tracking_number'] ?? '');
            $delivery_date = sanitizeInput($_POST['delivery_date'] ?? '');
            $notes = sanitizeInput($_POST['notes'] ?? '');
            $delivery_status = sanitizeInput($_POST['delivery_status'] ?? '');
            
            if (empty($delivery_status) || !in_array($delivery_status, ['delivered', 'not_delivered'])) {
                $error = 'Please select a valid delivery status.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Check if this is the first time marking as delivered to avoid double deduction
                    $stmt = $pdo->prepare("
                        SELECT status FROM bookings WHERE id = ? AND status = 'approved'
                    ");
                    $stmt->execute([$booking_id]);
                    $current_booking = $stmt->fetch();
                    
                    if (!$current_booking) {
                        throw new Exception('Booking not found or not ready for delivery.');
                    }
                    
                    // If marking as delivered, update inventory
                    if ($delivery_status === 'delivered') {
                        // Check if inventory has already been deducted for this booking
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as transaction_count 
                            FROM inventory_transactions 
                            WHERE booking_id = ? AND transaction_type = 'delivery'
                        ");
                        $stmt->execute([$booking_id]);
                        $existing_transactions = $stmt->fetch()['transaction_count'];
                        
                        if ($existing_transactions == 0) {
                            // Get booking items to update inventory
                            $stmt = $pdo->prepare("
                                SELECT bi.product_id, bi.quantity, p.name as product_name, p.stock_quantity 
                                FROM booking_items bi 
                                JOIN products p ON bi.product_id = p.id
                                WHERE bi.booking_id = ?
                            ");
                            $stmt->execute([$booking_id]);
                            $booking_items = $stmt->fetchAll();
                            
                            $inventory_warnings = [];
                            
                            // Update inventory for each product
                            foreach ($booking_items as $item) {
                                $new_quantity = max(0, $item['stock_quantity'] - $item['quantity']);
                                
                                // Update product quantity
                                $stmt = $pdo->prepare("
                                    UPDATE products 
                                    SET stock_quantity = ?, updated_at = NOW()
                                    WHERE id = ?
                                ");
                                $stmt->execute([$new_quantity, $item['product_id']]);
                                
                                // Log inventory transaction
                                $stmt = $pdo->prepare("
                                    INSERT INTO inventory_transactions 
                                    (product_id, transaction_type, quantity_change, booking_id, notes, created_by)
                                    VALUES (?, 'delivery', ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $item['product_id'], 
                                    -$item['quantity'], 
                                    $booking_id,
                                    "Delivered to customer via booking #{$booking_id}",
                                    $_SESSION['user_id']
                                ]);
                                
                                // Check for low stock warning
                                if ($item['stock_quantity'] < $item['quantity']) {
                                    $inventory_warnings[] = "Warning: {$item['product_name']} had insufficient stock. Delivered: {$item['quantity']}, Available: {$item['stock_quantity']}";
                                }
                            }
                            
                            // Store warnings in session for display
                            if (!empty($inventory_warnings)) {
                                $_SESSION['inventory_warnings'] = $inventory_warnings;
                            }
                        }
                    }
                    
                    // Update booking status
                    $stmt = $pdo->prepare("
                        UPDATE bookings 
                        SET status = ?, storeman_id = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$delivery_status, $_SESSION['user_id'], $booking_id]);
                    
                    // Insert or update delivery details
                    $stmt = $pdo->prepare("
                        INSERT INTO delivery_details (booking_id, delivery_company, tracking_number, delivery_date, notes, updated_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        delivery_company = VALUES(delivery_company),
                        tracking_number = VALUES(tracking_number),
                        delivery_date = VALUES(delivery_date),
                        notes = VALUES(notes),
                        updated_by = VALUES(updated_by),
                        updated_at = NOW()
                    ");
                    $stmt->execute([
                        $booking_id,
                        $delivery_company,
                        $tracking_number,
                        $delivery_date ?: null,
                        $notes,
                        $_SESSION['user_id']
                    ]);
                    
                    $pdo->commit();
                    
                    if ($delivery_status === 'delivered') {
                        $success = "Booking #$booking_id has been delivered and inventory has been updated.";
                    } else {
                        $success = "Booking #$booking_id has been marked as not delivered.";
                    }
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = $e->getMessage();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Failed to update delivery status.';
                }
            }
        }
    }
}

// Search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$status = sanitizeInput($_GET['status'] ?? 'approved');

// Build query
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(b.id LIKE ? OR b.customer_name LIKE ? OR b.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where_conditions[] = "b.status = ?";
    $params[] = $status;
}

$where_sql = implode(' AND ', $where_conditions);

try {
    // Get deliveries with related data
    $stmt = $pdo->prepare("
        SELECT b.*, 
               u_mod.full_name as moderator_name,
               u_acc.full_name as accountant_name,
               GROUP_CONCAT(CONCAT(p.name, ' (', bi.quantity, ')') SEPARATOR ', ') as products,
               dd.delivery_company,
               dd.tracking_number,
               dd.delivery_date,
               dd.notes as delivery_notes
        FROM bookings b
        LEFT JOIN users u_mod ON b.moderator_id = u_mod.id
        LEFT JOIN users u_acc ON b.accountant_id = u_acc.id
        LEFT JOIN booking_items bi ON b.id = bi.booking_id
        LEFT JOIN products p ON bi.product_id = p.id
        LEFT JOIN delivery_details dd ON b.id = dd.booking_id
        WHERE $where_sql AND b.status IN ('approved', 'delivered', 'not_delivered')
        GROUP BY b.id
        ORDER BY 
            CASE WHEN b.status = 'approved' THEN 1 ELSE 2 END,
            b.updated_at DESC
    ");
    $stmt->execute($params);
    $deliveries = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as ready_for_delivery,
            SUM(CASE WHEN status = 'delivered' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) as delivered_today,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as total_delivered,
            SUM(CASE WHEN status = 'not_delivered' THEN 1 ELSE 0 END) as not_delivered
        FROM bookings
    ");
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $deliveries = [];
    $stats = [];
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-truck"></i>
            Delivery Management
        </h1>
    </div>
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['inventory_warnings'])): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Inventory Warnings:</strong>
        <ul style="margin: 10px 0 0 20px;">
            <?php foreach ($_SESSION['inventory_warnings'] as $warning): ?>
            <li><?php echo htmlspecialchars($warning); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php 
    unset($_SESSION['inventory_warnings']); // Clear warnings after displaying
    endif; ?>
    
    <!-- Statistics -->
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
    
    <!-- Search and Filters -->
    <div class="search-filters">
        <form method="GET" class="search-row">
            <div class="search-input">
                <i class="fas fa-search"></i>
                <input type="text" name="search" id="searchInput" class="form-input" 
                       placeholder="Search by booking ID, customer name, or phone..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <select name="status" class="form-select">
                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Ready for Delivery</option>
                    <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="not_delivered" <?php echo $status === 'not_delivered' ? 'selected' : ''; ?>>Not Delivered</option>
                    <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>All</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i>
                Filter
            </button>
        </form>
    </div>
    
    <!-- Deliveries Table -->
    <?php if (!empty($deliveries)): ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer</th>
                        <th>Products</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Delivery Info</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $delivery): ?>
                    <tr>
                        <td>
                            <strong>#<?php echo $delivery['id']; ?></strong><br>
                            <small>by <?php echo htmlspecialchars($delivery['moderator_name']); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($delivery['customer_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($delivery['customer_phone']); ?></small>
                        </td>
                        <td>
                            <div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" 
                                 title="<?php echo htmlspecialchars($delivery['products']); ?>">
                                <?php echo htmlspecialchars($delivery['products']); ?>
                            </div>
                        </td>
                        <td><?php echo formatCurrency($delivery['amount']); ?></td>
                        <td>
                            <span class="status status-<?php echo $delivery['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($delivery['delivery_company']): ?>
                                <strong><?php echo htmlspecialchars($delivery['delivery_company']); ?></strong><br>
                                <?php if ($delivery['tracking_number']): ?>
                                    <small>Tracking: <?php echo htmlspecialchars($delivery['tracking_number']); ?></small><br>
                                <?php endif; ?>
                                <?php if ($delivery['delivery_date']): ?>
                                    <small><?php echo formatDate($delivery['delivery_date']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <small style="color: var(--text-secondary);">Not set</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatDateTime($delivery['updated_at']); ?></td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn btn-sm btn-outline view-details" 
                                        data-booking-id="<?php echo $delivery['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                    View
                                </button>
                                
                                <?php if ($delivery['status'] === 'approved'): ?>
                                <button type="button" class="btn btn-sm btn-primary update-delivery" 
                                        data-booking-id="<?php echo $delivery['id']; ?>">
                                    <i class="fas fa-truck"></i>
                                    Update
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php else: ?>
    <div class="card">
        <div class="card-content" style="text-align: center; padding: 60px 20px;">
            <i class="fas fa-truck" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 20px;"></i>
            <h3 style="margin-bottom: 10px;">No Deliveries Found</h3>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                <?php if ($search || $status): ?>
                    No deliveries match your search criteria. Try adjusting your filters.
                <?php else: ?>
                    No deliveries are ready for processing.
                <?php endif; ?>
            </p>
            <?php if ($search || $status): ?>
            <a href="deliveries.php" class="btn btn-outline">
                <i class="fas fa-times"></i>
                Clear Filters
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Delivery Update Modal -->
<div id="deliveryModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Update Delivery Status</h3>
            <button type="button" id="closeDeliveryModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        
        <form method="POST" id="deliveryForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="update_delivery">
            <input type="hidden" name="booking_id" id="deliveryBookingId">
            
            <div class="form-group">
                <label class="form-label">Delivery Company</label>
                <input type="text" name="delivery_company" class="form-input" 
                       placeholder="Enter delivery company name">
            </div>
            
            <div class="form-group">
                <label class="form-label">Tracking Number</label>
                <input type="text" name="tracking_number" class="form-input" 
                       placeholder="Enter tracking number">
            </div>
            
            <div class="form-group">
                <label class="form-label">Delivery Date</label>
                <input type="date" name="delivery_date" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-textarea" 
                          placeholder="Additional notes about the delivery"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Delivery Status *</label>
                <select name="delivery_status" class="form-select" required>
                    <option value="">Select Status</option>
                    <option value="delivered">Delivered</option>
                    <option value="not_delivered">Not Delivered</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: end; margin-top: 20px;">
                <button type="button" id="cancelDelivery" class="btn btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Booking Details Modal -->
<div id="bookingModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Booking Details</h3>
            <button type="button" id="closeBookingModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        
        <div id="bookingDetails">
            <!-- Details will be loaded here -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deliveryModal = document.getElementById('deliveryModal');
    const bookingModal = document.getElementById('bookingModal');
    const closeDeliveryModal = document.getElementById('closeDeliveryModal');
    const closeBookingModal = document.getElementById('closeBookingModal');
    const cancelDelivery = document.getElementById('cancelDelivery');
    const deliveryForm = document.getElementById('deliveryForm');
    const bookingDetails = document.getElementById('bookingDetails');
    
    const updateButtons = document.querySelectorAll('.update-delivery');
    const viewButtons = document.querySelectorAll('.view-details');
    
    // Update delivery
    updateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const bookingId = this.dataset.bookingId;
            document.getElementById('deliveryBookingId').value = bookingId;
            deliveryModal.style.display = 'flex';
        });
    });
    
    // View booking details  
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const bookingId = this.dataset.bookingId;
            loadBookingDetails(bookingId);
            bookingModal.style.display = 'flex';
        });
    });
    
    // Close modals
    closeDeliveryModal.addEventListener('click', () => {
        deliveryModal.style.display = 'none';
        deliveryForm.reset();
    });
    
    closeBookingModal.addEventListener('click', () => {
        bookingModal.style.display = 'none';
    });
    
    cancelDelivery.addEventListener('click', () => {
        deliveryModal.style.display = 'none';
        deliveryForm.reset();
    });
    
    // Close modals when clicking outside
    deliveryModal.addEventListener('click', function(e) {
        if (e.target === deliveryModal) {
            deliveryModal.style.display = 'none';
            deliveryForm.reset();
        }
    });
    
    bookingModal.addEventListener('click', function(e) {
        if (e.target === bookingModal) {
            bookingModal.style.display = 'none';
        }
    });
    
    function loadBookingDetails(bookingId) {
        bookingDetails.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        
        fetch(`ajax/booking-details.php?id=${bookingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayBookingDetails(data.booking);
                } else {
                    bookingDetails.innerHTML = '<div class="alert alert-error">Failed to load booking details.</div>';
                }
            })
            .catch(error => {
                bookingDetails.innerHTML = '<div class="alert alert-error">Failed to load booking details.</div>';
            });
    }
    
    function displayBookingDetails(booking) {
        let html = `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <h4>Customer Information</h4>
                    <p><strong>Name:</strong> ${booking.customer_name}</p>
                    <p><strong>Phone:</strong> ${booking.customer_phone}</p>
                    <p><strong>Address:</strong><br>${booking.customer_address}</p>
                </div>
                <div>
                    <h4>Booking Information</h4>
                    <p><strong>Booking ID:</strong> #${booking.id}</p>
                    <p><strong>Status:</strong> <span class="status status-${booking.status}">${booking.status.replace('_', ' ').toUpperCase()}</span></p>
                    <p><strong>Payment Type:</strong> ${booking.payment_type}</p>
                    <p><strong>Total Amount:</strong> $${parseFloat(booking.amount).toFixed(2)}</p>
                    <p><strong>Created:</strong> ${new Date(booking.created_at).toLocaleString()}</p>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h4>Products</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        booking.items.forEach(item => {
            html += `
                <tr>
                    <td>
                        <strong>${item.product_name}</strong>
                        ${item.product_code ? `<br><small style="color: #666;">Code: ${item.product_code}</small>` : ''}
                    </td>
                    <td>${item.quantity}</td>
                    <td>$${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td>$${(parseFloat(item.unit_price) * parseInt(item.quantity)).toFixed(2)}</td>
                </tr>
            `;
        });
        
        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        if (booking.delivery_details) {
            html += `
                <div style="margin-bottom: 20px;">
                    <h4>Delivery Information</h4>
                    <p><strong>Company:</strong> ${booking.delivery_details.delivery_company || 'Not set'}</p>
                    <p><strong>Tracking Number:</strong> ${booking.delivery_details.tracking_number || 'Not set'}</p>
                    <p><strong>Delivery Date:</strong> ${booking.delivery_details.delivery_date ? new Date(booking.delivery_details.delivery_date).toLocaleDateString() : 'Not set'}</p>
                    ${booking.delivery_details.notes ? `<p><strong>Notes:</strong> ${booking.delivery_details.notes}</p>` : ''}
                </div>
            `;
        }
        
        bookingDetails.innerHTML = html;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
