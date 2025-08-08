<?php
require_once 'includes/config.php';
requireRole('accountant');

$page_title = 'Payment Management';

$success = '';
$error = '';

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $action = sanitizeInput($_POST['action']);
        
        if ($action === 'update_payment') {
            $payment_status = sanitizeInput($_POST['payment_status'] ?? '');
            $payment_method = sanitizeInput($_POST['payment_method'] ?? '');
            $transaction_id = sanitizeInput($_POST['transaction_id'] ?? '');
            $payment_date = sanitizeInput($_POST['payment_date'] ?? '');
            $notes = sanitizeInput($_POST['notes'] ?? '');
            
            if (empty($payment_status) || !in_array($payment_status, ['pending', 'paid', 'failed'])) {
                $error = 'Please select a valid payment status.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE payments 
                        SET payment_status = ?, payment_method = ?, transaction_id = ?, 
                            payment_date = ?, notes = ?, updated_by = ?, updated_at = NOW()
                        WHERE booking_id = ?
                    ");
                    $stmt->execute([
                        $payment_status,
                        $payment_method,
                        $transaction_id,
                        $payment_date ?: null,
                        $notes,
                        $_SESSION['user_id'],
                        $booking_id
                    ]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = "Payment status updated successfully for booking #$booking_id.";
                    } else {
                        $error = 'Payment record not found.';
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to update payment status.';
                }
            }
        }
    }
}

// Search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$payment_status = sanitizeInput($_GET['payment_status'] ?? '');
$booking_status = sanitizeInput($_GET['booking_status'] ?? '');

// Build query
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(b.id LIKE ? OR b.customer_name LIKE ? OR b.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($payment_status) {
    $where_conditions[] = "pay.payment_status = ?";
    $params[] = $payment_status;
}

if ($booking_status) {
    $where_conditions[] = "b.status = ?";
    $params[] = $booking_status;
}

$where_sql = implode(' AND ', $where_conditions);

try {
    // Get payments with related data
    $stmt = $pdo->prepare("
        SELECT b.*, 
               u_mod.full_name as moderator_name,
               pay.payment_status,
               pay.payment_method,
               pay.transaction_id,
               pay.payment_date,
               pay.notes as payment_notes,
               GROUP_CONCAT(CONCAT(p.name, ' (', bi.quantity, ')') SEPARATOR ', ') as products
        FROM bookings b
        LEFT JOIN users u_mod ON b.moderator_id = u_mod.id
        LEFT JOIN payments pay ON b.id = pay.booking_id
        LEFT JOIN booking_items bi ON b.id = bi.booking_id
        LEFT JOIN products p ON bi.product_id = p.id
        WHERE $where_sql
        GROUP BY b.id
        ORDER BY 
            CASE WHEN pay.payment_status = 'pending' THEN 1 ELSE 2 END,
            b.created_at DESC
    ");
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    // Get payment statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN pay.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
            SUM(CASE WHEN pay.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_payments,
            SUM(CASE WHEN pay.payment_status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
            SUM(CASE WHEN pay.payment_status = 'paid' THEN b.amount ELSE 0 END) as total_revenue
        FROM bookings b
        LEFT JOIN payments pay ON b.id = pay.booking_id
    ");
    $payment_stats = $stmt->fetch();
    
    // Get revenue by month for chart (last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(pay.payment_date, '%Y-%m') as month,
            SUM(b.amount) as revenue
        FROM bookings b
        LEFT JOIN payments pay ON b.id = pay.booking_id
        WHERE pay.payment_status = 'paid' 
        AND pay.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(pay.payment_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $revenue_data = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $payments = [];
    $payment_stats = [];
    $revenue_data = [];
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-credit-card"></i>
            Payment Management
        </h1>
        <a href="reports.php" class="btn btn-outline">
            <i class="fas fa-chart-bar"></i>
            View Reports
        </a>
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
    
    <!-- Payment Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-receipt stat-icon"></i>
            <span class="stat-number"><?php echo number_format($payment_stats['total_payments'] ?? 0); ?></span>
            <span class="stat-label">Total Payments</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-clock stat-icon" style="color: var(--warning-color);"></i>
            <span class="stat-number" style="color: var(--warning-color);"><?php echo number_format($payment_stats['pending_payments'] ?? 0); ?></span>
            <span class="stat-label">Pending Payments</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-check-circle stat-icon" style="color: var(--success-color);"></i>
            <span class="stat-number" style="color: var(--success-color);"><?php echo number_format($payment_stats['paid_payments'] ?? 0); ?></span>
            <span class="stat-label">Paid Payments</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-dollar-sign stat-icon" style="color: var(--success-color);"></i>
            <span class="stat-number" style="color: var(--success-color);"><?php echo formatCurrency($payment_stats['total_revenue'] ?? 0); ?></span>
            <span class="stat-label">Total Revenue</span>
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
                <select name="payment_status" class="form-select">
                    <option value="">All Payment Status</option>
                    <option value="pending" <?php echo $payment_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="failed" <?php echo $payment_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>
            <div class="form-group">
                <select name="booking_status" class="form-select">
                    <option value="">All Booking Status</option>
                    <option value="pending" <?php echo $booking_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $booking_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="delivered" <?php echo $booking_status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i>
                Filter
            </button>
        </form>
    </div>
    
    <!-- Payments Table -->
    <?php if (!empty($payments)): ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment Type</th>
                        <th>Payment Status</th>
                        <th>Booking Status</th>
                        <th>Payment Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td>
                            <strong>#<?php echo $payment['id']; ?></strong><br>
                            <small>by <?php echo htmlspecialchars($payment['moderator_name']); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($payment['customer_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($payment['customer_phone']); ?></small>
                        </td>
                        <td><?php echo formatCurrency($payment['amount']); ?></td>
                        <td>
                            <span class="status <?php echo $payment['payment_type'] === 'Online Paid' ? 'status-approved' : 'status-pending'; ?>">
                                <?php echo $payment['payment_type']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status status-<?php echo $payment['payment_status'] ?? 'pending'; ?>">
                                <?php echo ucfirst($payment['payment_status'] ?? 'pending'); ?>
                            </span>
                            <?php if ($payment['payment_method']): ?>
                                <br><small><?php echo htmlspecialchars($payment['payment_method']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status status-<?php echo $payment['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $payment['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($payment['payment_date']): ?>
                                <?php echo formatDate($payment['payment_date']); ?>
                            <?php else: ?>
                                <small style="color: var(--text-secondary);">Not set</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn btn-sm btn-outline view-details" 
                                        data-booking-id="<?php echo $payment['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                    View
                                </button>
                                
                                <button type="button" class="btn btn-sm btn-primary update-payment" 
                                        data-booking-id="<?php echo $payment['id']; ?>"
                                        data-payment-status="<?php echo $payment['payment_status'] ?? 'pending'; ?>"
                                        data-payment-method="<?php echo htmlspecialchars($payment['payment_method'] ?? ''); ?>"
                                        data-transaction-id="<?php echo htmlspecialchars($payment['transaction_id'] ?? ''); ?>"
                                        data-payment-date="<?php echo $payment['payment_date'] ?? ''; ?>"
                                        data-notes="<?php echo htmlspecialchars($payment['payment_notes'] ?? ''); ?>">
                                    <i class="fas fa-edit"></i>
                                    Update
                                </button>
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
            <i class="fas fa-credit-card" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 20px;"></i>
            <h3 style="margin-bottom: 10px;">No Payments Found</h3>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                <?php if ($search || $payment_status || $booking_status): ?>
                    No payments match your search criteria. Try adjusting your filters.
                <?php else: ?>
                    No payments are available.
                <?php endif; ?>
            </p>
            <?php if ($search || $payment_status || $booking_status): ?>
            <a href="payments.php" class="btn btn-outline">
                <i class="fas fa-times"></i>
                Clear Filters
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Payment Update Modal -->
<div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Update Payment</h3>
            <button type="button" id="closePaymentModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        
        <form method="POST" id="paymentForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="update_payment">
            <input type="hidden" name="booking_id" id="paymentBookingId">
            
            <div class="form-group">
                <label class="form-label">Payment Status *</label>
                <select name="payment_status" id="paymentStatus" class="form-select" required>
                    <option value="">Select Status</option>
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Payment Method</label>
                <input type="text" name="payment_method" id="paymentMethod" class="form-input" 
                       placeholder="e.g., Credit Card, Bank Transfer, Cash">
            </div>
            
            <div class="form-group">
                <label class="form-label">Transaction ID</label>
                <input type="text" name="transaction_id" id="transactionId" class="form-input" 
                       placeholder="Enter transaction ID">
            </div>
            
            <div class="form-group">
                <label class="form-label">Payment Date</label>
                <input type="date" name="payment_date" id="paymentDate" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" id="paymentNotes" class="form-textarea" 
                          placeholder="Additional notes about the payment"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: end; margin-top: 20px;">
                <button type="button" id="cancelPayment" class="btn btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Update Payment
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
    const paymentModal = document.getElementById('paymentModal');
    const bookingModal = document.getElementById('bookingModal');
    const closePaymentModal = document.getElementById('closePaymentModal');
    const closeBookingModal = document.getElementById('closeBookingModal');
    const cancelPayment = document.getElementById('cancelPayment');
    const paymentForm = document.getElementById('paymentForm');
    const bookingDetails = document.getElementById('bookingDetails');
    
    const updateButtons = document.querySelectorAll('.update-payment');
    const viewButtons = document.querySelectorAll('.view-details');
    
    // Update payment
    updateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const bookingId = this.dataset.bookingId;
            document.getElementById('paymentBookingId').value = bookingId;
            document.getElementById('paymentStatus').value = this.dataset.paymentStatus;
            document.getElementById('paymentMethod').value = this.dataset.paymentMethod;
            document.getElementById('transactionId').value = this.dataset.transactionId;
            document.getElementById('paymentDate').value = this.dataset.paymentDate;
            document.getElementById('paymentNotes').value = this.dataset.notes;
            
            paymentModal.style.display = 'flex';
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
    closePaymentModal.addEventListener('click', () => {
        paymentModal.style.display = 'none';
        paymentForm.reset();
    });
    
    closeBookingModal.addEventListener('click', () => {
        bookingModal.style.display = 'none';
    });
    
    cancelPayment.addEventListener('click', () => {
        paymentModal.style.display = 'none';
        paymentForm.reset();
    });
    
    // Close modals when clicking outside
    paymentModal.addEventListener('click', function(e) {
        if (e.target === paymentModal) {
            paymentModal.style.display = 'none';
            paymentForm.reset();
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
        
        bookingDetails.innerHTML = html;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
