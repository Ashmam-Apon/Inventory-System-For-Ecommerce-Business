<?php
require_once 'includes/config.php';
requireRole('accountant');

$page_title = 'Bookings Management';

$success = '';
$error = '';

// Handle booking approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $action = sanitizeInput($_POST['action']);
        
        if ($action === 'approve' || $action === 'reject') {
            try {
                $new_status = $action === 'approve' ? 'approved' : 'rejected';
                
                $stmt = $pdo->prepare("
                    UPDATE bookings 
                    SET status = ?, accountant_id = ?, updated_at = NOW() 
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$new_status, $_SESSION['user_id'], $booking_id]);
                
                if ($stmt->rowCount() > 0) {
                    $success = "Booking #$booking_id has been " . ($action === 'approve' ? 'approved' : 'rejected') . '.';
                } else {
                    $error = 'Booking not found or already processed.';
                }
            } catch (PDOException $e) {
                $error = 'Failed to update booking status.';
            }
        }
    }
}

// Search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');

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
    // Get bookings with related data
    $stmt = $pdo->prepare("
        SELECT b.*, 
               u_mod.full_name as moderator_name,
               u_acc.full_name as accountant_name,
               u_store.full_name as storeman_name,
               GROUP_CONCAT(CONCAT(p.name, ' (', bi.quantity, ')') SEPARATOR ', ') as products,
               pay.payment_status
        FROM bookings b
        LEFT JOIN users u_mod ON b.moderator_id = u_mod.id
        LEFT JOIN users u_acc ON b.accountant_id = u_acc.id
        LEFT JOIN users u_store ON b.storeman_id = u_store.id
        LEFT JOIN booking_items bi ON b.id = bi.booking_id
        LEFT JOIN products p ON bi.product_id = p.id
        LEFT JOIN payments pay ON b.id = pay.booking_id
        WHERE $where_sql
        GROUP BY b.id
        ORDER BY 
            CASE WHEN b.status = 'pending' THEN 1 ELSE 2 END,
            b.created_at DESC
    ");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM bookings
    ");
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $bookings = [];
    $stats = [];
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-clipboard-list"></i>
            Bookings Management
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
    
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-clipboard-list stat-icon"></i>
            <span class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></span>
            <span class="stat-label">Total Bookings</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-clock stat-icon" style="color: var(--warning-color);"></i>
            <span class="stat-number" style="color: var(--warning-color);"><?php echo number_format($stats['pending'] ?? 0); ?></span>
            <span class="stat-label">Pending Approval</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-check-circle stat-icon" style="color: var(--success-color);"></i>
            <span class="stat-number" style="color: var(--success-color);"><?php echo number_format($stats['approved'] ?? 0); ?></span>
            <span class="stat-label">Approved</span>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-truck stat-icon" style="color: var(--primary-color);"></i>
            <span class="stat-number"><?php echo number_format($stats['delivered'] ?? 0); ?></span>
            <span class="stat-label">Delivered</span>
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
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="not_delivered" <?php echo $status === 'not_delivered' ? 'selected' : ''; ?>>Not Delivered</option>
                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i>
                Filter
            </button>
        </form>
    </div>
    
    <!-- Bookings Table -->
    <?php if (!empty($bookings)): ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer</th>
                        <th>Products</th>
                        <th>Amount</th>
                        <th>Payment Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td>
                            <strong>#<?php echo $booking['id']; ?></strong><br>
                            <small>by <?php echo htmlspecialchars($booking['moderator_name']); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($booking['customer_phone']); ?></small>
                        </td>
                        <td>
                            <div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" 
                                 title="<?php echo htmlspecialchars($booking['products']); ?>">
                                <?php echo htmlspecialchars($booking['products']); ?>
                            </div>
                        </td>
                        <td><?php echo formatCurrency($booking['amount']); ?></td>
                        <td>
                            <span class="status <?php echo $booking['payment_type'] === 'Online Paid' ? 'status-paid' : 'status-pending'; ?>">
                                <?php echo $booking['payment_type']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status status-<?php echo $booking['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo formatDateTime($booking['created_at']); ?></td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn btn-sm btn-outline view-details" 
                                        data-booking-id="<?php echo $booking['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                    View
                                </button>
                                
                                <?php if ($booking['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success" 
                                            onclick="return confirm('Are you sure you want to approve this booking?')">
                                        <i class="fas fa-check"></i>
                                        Approve
                                    </button>
                                </form>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-sm btn-error" 
                                            onclick="return confirm('Are you sure you want to reject this booking?')">
                                        <i class="fas fa-times"></i>
                                        Reject
                                    </button>
                                </form>
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
            <i class="fas fa-clipboard-list" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 20px;"></i>
            <h3 style="margin-bottom: 10px;">No Bookings Found</h3>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                <?php if ($search || $status): ?>
                    No bookings match your search criteria. Try adjusting your filters.
                <?php else: ?>
                    No bookings have been created yet.
                <?php endif; ?>
            </p>
            <?php if ($search || $status): ?>
            <a href="bookings.php" class="btn btn-outline">
                <i class="fas fa-times"></i>
                Clear Filters
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
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
    const modal = document.getElementById('bookingModal');
    const closeModal = document.getElementById('closeBookingModal');
    const bookingDetails = document.getElementById('bookingDetails');
    const viewButtons = document.querySelectorAll('.view-details');
    
    // View booking details
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const bookingId = this.dataset.bookingId;
            loadBookingDetails(bookingId);
            modal.style.display = 'flex';
        });
    });
    
    // Close modal
    closeModal.addEventListener('click', () => {
        modal.style.display = 'none';
    });
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
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
                    <td>${item.product_name}</td>
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
