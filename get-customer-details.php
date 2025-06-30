<?php
require_once 'config/Database.php';
require_once 'includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if customer ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid customer ID']);
    exit();
}

$customer_id = (int)$_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get customer details
    $customer_query = "SELECT u.*, 
        (SELECT phone FROM orders WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as phone 
        FROM users u WHERE u.id = ? AND u.role = 'user'";
    $customer_stmt = $db->prepare($customer_query);
    $customer_stmt->execute([$customer_id]);
    $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Customer not found']);
        exit();
    }
    
    // Get customer order statistics
    $stats_query = "SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) as total_spent,
        MAX(o.created_at) as last_order_date,
        COUNT(CASE WHEN o.status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN o.status = 'processing' THEN 1 END) as processing_orders,
        COUNT(CASE WHEN o.status = 'shipped' THEN 1 END) as shipped_orders,
        COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) as delivered_orders,
        COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) as cancelled_orders
    FROM orders o 
    WHERE o.user_id = ?";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$customer_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent orders (last 5)
    $recent_orders_query = "SELECT 
        o.id,
        o.total_amount,
        o.status,
        o.payment_status,
        o.created_at,
        o.delivery_date,
        COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5";
    
    $recent_orders_stmt = $db->prepare($recent_orders_query);
    $recent_orders_stmt->execute([$customer_id]);
    $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get customer's favorite categories (based on order history)
    $categories_query = "SELECT 
        c.name as category_name,
        COUNT(*) as order_count,
        SUM(oi.quantity) as total_quantity
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE o.user_id = ?
    GROUP BY c.id, c.name
    ORDER BY order_count DESC
    LIMIT 3";
    
    $categories_stmt = $db->prepare($categories_query);
    $categories_stmt->execute([$customer_id]);
    $favorite_categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'customer' => $customer,
        'stats' => $stats,
        'recent_orders' => $recent_orders,
        'favorite_categories' => $favorite_categories
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>