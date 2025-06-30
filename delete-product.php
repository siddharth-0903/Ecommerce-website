<?php
require_once 'config/database.php';

// Check if product ID is provided
if (!isset($_GET['id'])) {
    header('Location: admin-view-products.php');
    exit;
}

$productId = $_GET['id'];

try {
    // Begin transaction
    $db->beginTransaction();

    // Delete product images first
    $query = "DELETE FROM product_images WHERE product_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$productId]);

    // Delete product
    $query = "DELETE FROM products WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$productId]);

    // Commit transaction
    $db->commit();

    // Redirect back with success message
    header('Location: admin-view-products.php?message=Product deleted successfully');
    exit;

} catch (PDOException $e) {
    // Rollback transaction on error
    $db->rollBack();
    
    // Redirect back with error message
    header('Location: admin-view-products.php?error=Failed to delete product');
    exit;
}
?> 