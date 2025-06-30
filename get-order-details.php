<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is admin
if (!isAdmin()) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Order ID is required']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Get order details with user information
    $query = "SELECT o.*, u.first_name, u.last_name, u.email 
              FROM orders o 
              JOIN users u ON o.user_id = u.id 
              WHERE o.id = :order_id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':order_id', $_GET['id']);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['error' => 'Order not found']);
        exit();
    }

    // Get order items
    $query = "SELECT oi.*, p.name, p.sku 
              FROM order_items oi 
              JOIN products p ON oi.product_id = p.id 
              WHERE oi.order_id = :order_id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':order_id', $_GET['id']);
    $stmt->execute();
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($order);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 