<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check if product ID is provided
if (!isset($_GET['id'])) {
    header('Location: admin-view-products.php');
    exit;
}

$productId = $_GET['id'];

// Get product details
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.id = :product_id";
$stmt = $db->prepare($query);
$stmt->bindValue(":product_id", $_GET['id']);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// If product not found, redirect back
if (!$product) {
    header('Location: admin-view-products.php');
    exit;
}

// Get product images
$imageQuery = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC";
$imageStmt = $db->prepare($imageQuery);
$imageStmt->execute([$productId]);
$images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Product | Rareblocks</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .product-image {
            max-width: 300px;
            height: auto;
        }
        .gallery-image {
            max-width: 100px;
            height: auto;
            margin: 5px;
            cursor: pointer;
        }
        .gallery-image:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12 mb-4">
                <a href="admin-view-products.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Products
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h2>
                        
                        <!-- Main Product Image -->
                        <div class="mb-4">
                            <?php if ($product['image']): ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                            <?php else: ?>
                                <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Product Gallery -->
                        <?php if (!empty($images)): ?>
                        <div class="mb-4">
                            <h5>Product Gallery</h5>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($images as $image): ?>
                                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                         alt="Product Image" 
                                         class="gallery-image"
                                         onclick="updateMainImage(this.src)">
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Product Details</h4>
                        
                        <div class="mb-3">
                            <strong>SKU:</strong>
                            <span><?php echo htmlspecialchars($product['sku']); ?></span>
                        </div>

                        <div class="mb-3">
                            <strong>Category:</strong>
                            <span><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                        </div>

                        <div class="mb-3">
                            <strong>Price:</strong>
                            <span>
                                <?php if ($product['sale_price']): ?>
                                    ₹<?php echo number_format($product['sale_price'], 2); ?>
                                    <small class="text-muted text-decoration-line-through">₹<?php echo number_format($product['price'], 2); ?></small>
                                <?php else: ?>
                                    ₹<?php echo number_format($product['price'], 2); ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <strong>Stock Quantity:</strong>
                            <span><?php echo $product['stock_quantity']; ?></span>
                        </div>

                        <div class="mb-3">
                            <strong>Status:</strong>
                            <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : ($product['status'] === 'inactive' ? 'danger' : 'warning'); ?>">
                                <?php echo ucfirst($product['status']); ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <strong>Featured:</strong>
                            <span><?php echo $product['featured'] ? 'Yes' : 'No'; ?></span>
                        </div>

                        <div class="mb-3">
                            <strong>Created At:</strong>
                            <span><?php echo date('F j, Y', strtotime($product['created_at'])); ?></span>
                        </div>

                        <div class="mb-3">
                            <strong>Last Updated:</strong>
                            <span><?php echo date('F j, Y', strtotime($product['updated_at'])); ?></span>
                        </div>

                        <div class="mb-3">
                            <strong>Description:</strong>
                            <p class="mt-2"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        </div>

                        <div class="mt-4">
                            <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Product
                            </a>
                            <button onclick="deleteProduct(<?php echo $product['id']; ?>)" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Product
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateMainImage(src) {
            document.querySelector('.product-image').src = src;
        }

        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                window.location.href = `delete-product.php?id=${productId}`;
            }
        }
    </script>
</body>
</html> 