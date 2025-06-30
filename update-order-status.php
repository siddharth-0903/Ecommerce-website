<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is admin
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['order_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "UPDATE orders SET status = :status WHERE id = :order_id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':status', $data['status']);
    $stmt->bindValue(':order_id', $data['order_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 