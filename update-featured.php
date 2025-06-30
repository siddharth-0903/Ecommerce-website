<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is admin
if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $is_featured = isset($_POST['is_featured']) ? intval($_POST['is_featured']) : 0;

    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit();
    }

    try {
        // First verify the product exists
        $check_query = "SELECT id FROM products WHERE id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindValue(":id", $product_id, PDO::PARAM_INT);
        $check_stmt->execute();

        if (!$check_stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit();
        }

        // Update the product
        $query = "UPDATE products SET is_featured = :is_featured WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(":is_featured", $is_featured, PDO::PARAM_BOOL);
        $stmt->bindValue(":id", $product_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Product updated successfully',
                'product_id' => $product_id,
                'is_featured' => $is_featured
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $stmt->errorInfo()
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error',
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method',
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
} 