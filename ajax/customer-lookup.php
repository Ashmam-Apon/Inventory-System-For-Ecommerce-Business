<?php
require_once '../includes/config.php';
requireRole('moderator');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$phone = sanitizeInput($input['phone'] ?? '');

if (empty($phone)) {
    echo json_encode(['error' => 'Phone number is required']);
    exit;
}

try {
    // Look for customer's previous delivered orders
    $stmt = $pdo->prepare("
        SELECT 
            b.id as booking_id,
            b.customer_name,
            b.customer_phone,
            b.customer_address,
            b.amount,
            b.payment_type,
            b.created_at,
            b.status,
            dd.delivery_date,
            COUNT(bi.id) as total_items
        FROM bookings b
        LEFT JOIN delivery_details dd ON b.id = dd.booking_id
        LEFT JOIN booking_items bi ON b.id = bi.booking_id
        WHERE b.customer_phone = ? AND b.status = 'delivered'
        GROUP BY b.id
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$phone]);
    $orders = $stmt->fetchAll();

    if (empty($orders)) {
        echo json_encode([
            'found' => false,
            'message' => 'No previous orders found for this phone number'
        ]);
        exit;
    }

    // Get detailed information for the most recent order
    $latestOrder = $orders[0];
    
    // Get items for the most recent order
    $stmt = $pdo->prepare("
        SELECT 
            p.name as product_name,
            p.product_code,
            bi.quantity,
            bi.unit_price,
            (bi.quantity * bi.unit_price) as total_price
        FROM booking_items bi
        JOIN products p ON bi.product_id = p.id
        WHERE bi.booking_id = ?
        ORDER BY p.name
    ");
    $stmt->execute([$latestOrder['booking_id']]);
    $orderItems = $stmt->fetchAll();

    // Calculate total order value and count for this customer
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT b.id) as total_orders,
            SUM(b.amount) as total_spent,
            MIN(b.created_at) as first_order_date,
            MAX(b.created_at) as last_order_date
        FROM bookings b
        WHERE b.customer_phone = ? AND b.status = 'delivered'
    ");
    $stmt->execute([$phone]);
    $customerStats = $stmt->fetch();

    echo json_encode([
        'found' => true,
        'customer' => [
            'name' => $latestOrder['customer_name'],
            'phone' => $latestOrder['customer_phone'],
            'address' => $latestOrder['customer_address']
        ],
        'stats' => [
            'total_orders' => (int)$customerStats['total_orders'],
            'total_spent' => (float)$customerStats['total_spent'],
            'first_order_date' => $customerStats['first_order_date'],
            'last_order_date' => $customerStats['last_order_date']
        ],
        'latest_order' => [
            'booking_id' => $latestOrder['booking_id'],
            'amount' => (float)$latestOrder['amount'],
            'payment_type' => $latestOrder['payment_type'],
            'order_date' => $latestOrder['created_at'],
            'delivery_date' => $latestOrder['delivery_date'],
            'total_items' => (int)$latestOrder['total_items'],
            'items' => $orderItems
        ],
        'order_history' => array_map(function($order) {
            return [
                'booking_id' => $order['booking_id'],
                'amount' => (float)$order['amount'],
                'payment_type' => $order['payment_type'],
                'order_date' => $order['created_at'],
                'delivery_date' => $order['delivery_date'],
                'total_items' => (int)$order['total_items']
            ];
        }, $orders)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
?>
