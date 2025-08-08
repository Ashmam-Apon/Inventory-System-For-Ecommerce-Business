<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$booking_id = (int)($_GET['id'] ?? 0);

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

try {
    // Get booking details
    $stmt = $pdo->prepare("
        SELECT b.*, 
               u_mod.full_name as moderator_name,
               u_acc.full_name as accountant_name,
               u_store.full_name as storeman_name,
               pay.payment_status
        FROM bookings b
        LEFT JOIN users u_mod ON b.moderator_id = u_mod.id
        LEFT JOIN users u_acc ON b.accountant_id = u_acc.id
        LEFT JOIN users u_store ON b.storeman_id = u_store.id
        LEFT JOIN payments pay ON b.id = pay.booking_id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }
    
    // Get booking items
    $stmt = $pdo->prepare("
        SELECT bi.*, p.name as product_name, p.product_code, p.sku
        FROM booking_items bi
        LEFT JOIN products p ON bi.product_id = p.id
        WHERE bi.booking_id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking['items'] = $stmt->fetchAll();
    
    // Get delivery details if exists
    $stmt = $pdo->prepare("SELECT * FROM delivery_details WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $booking['delivery_details'] = $stmt->fetch();
    
    echo json_encode(['success' => true, 'booking' => $booking]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
