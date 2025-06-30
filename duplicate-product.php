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

    // Get the original product
    $query = "SELECT * FROM products WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception('Product not found');
    }

    // Create a copy of the product
    $query = "INSERT INTO products (name, slug, description, price, sale_price, sku, stock_quantity, 
              category_id, image, status, featured) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $newSlug = $product['slug'] . '-copy';
    $newSku = $product['sku'] . '-copy';
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $product['name'] . ' (Copy)',
        $newSlug,
        $product['description'],
        $product['price'],
        $product['sale_price'],
        $newSku,
        $product['stock_quantity'],
        $product['category_id'],
        $product['image'],
        'draft',
        $product['featured']
    ]);

    $newProductId = $db->lastInsertId();

    // Copy product images
    $query = "SELECT * FROM product_images WHERE product_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll();

    if (!empty($images)) {
        $query = "INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        
        foreach ($images as $image) {
            $stmt->execute([$newProductId, $image['image_path'], $image['is_primary']]);
        }
    }

    // Commit transaction
    $db->commit();

    // Redirect to edit page of the new product
    header('Location: edit-product.php?id=' . $newProductId . '&message=Product duplicated successfully');
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    
    // Redirect back with error message
    header('Location: admin-view-products.php?error=Failed to duplicate product');
    exit;
}
?> 