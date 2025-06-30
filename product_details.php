<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/cart.php';

$database = new Database();
$db = $database->getConnection();

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    header('Location: products.php');
    exit;
}

// Get product details
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.id = :id AND p.status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $product_id);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: products.php');
    exit;
}

// Get related products from same category
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.category_id = :category_id AND p.id != :product_id AND p.status = 'active' 
          ORDER BY RAND() 
          LIMIT 4";
$stmt = $db->prepare($query);
$stmt->bindParam(':category_id', $product['category_id']);
$stmt->bindParam(':product_id', $product_id);
$stmt->execute();
$related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get product images (if you have a product_images table)
$query = "SELECT * FROM product_images WHERE product_id = :product_id ORDER BY is_primary DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':product_id', $product_id);
$stmt->execute();
$product_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no images in product_images table, use the main product image
if (empty($product_images) && $product['image']) {
    $product_images = [['image_path' => $product['image'], 'is_primary' => 1]];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> | Rareblocks</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .product-detail-section {
            padding: 2rem 0;
        }

        .product-image-gallery {
            position: sticky;
            top: 20px;
        }

        .main-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .thumbnail-images {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 10px 0;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.2s;
        }

        .thumbnail.active {
            border-color: #19191b;
        }

        .thumbnail:hover {
            border-color: #ffe942;
        }

        .product-info {
            padding-left: 2rem;
        }

        .product-title {
            font-size: 2rem;
            font-weight: 700;
            color: #19191b;
            margin-bottom: 0.5rem;
        }

        .product-category {
            color: #666;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .rating-stars {
            color: #ffc107;
        }

        .price-section {
            margin: 2rem 0;
        }

        .current-price {
            font-size: 2rem;
            font-weight: 700;
            color: #19191b;
        }

        .original-price {
            font-size: 1.5rem;
            color: #999;
            text-decoration: line-through;
            margin-left: 1rem;
        }

        .discount-badge {
            background: #ff4444;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-left: 1rem;
        }

        .product-description {
            margin: 2rem 0;
            line-height: 1.8;
            color: #555;
        }

        .product-specs {
            margin: 2rem 0;
        }

        .spec-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }

        .spec-label {
            font-weight: 600;
            color: #333;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 2rem 0;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }

        .quantity-btn {
            background: #f8f9fa;
            border: none;
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .quantity-btn:hover {
            background: #e9ecef;
        }

        .quantity-input {
            border: none;
            text-align: center;
            width: 60px;
            padding: 0.75rem 0;
            font-weight: 600;
        }

        .add-to-cart-section {
            margin: 2rem 0;
        }

        .add-to-cart-btn {
            background: #19191b;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.2s;
            width: 100%;
            margin-bottom: 1rem;
        }

        .add-to-cart-btn:hover {
            background: #ffe942;
            color: #19191b;
        }

        .add-to-cart-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .wishlist-btn {
            border: 1px solid #ddd;
            background: white;
            color: #666;
            padding: 1rem;
            border-radius: 8px;
            width: 100%;
            transition: all 0.2s;
        }

        .wishlist-btn:hover {
            border-color: #19191b;
            color: #19191b;
        }

        .stock-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            margin: 1rem 0;
            display: inline-block;
        }

        .in-stock {
            background: #d4edda;
            color: #155724;
        }

        .out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .low-stock {
            background: #fff3cd;
            color: #856404;
        }

        .tabs-section {
            margin: 3rem 0;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #666;
            font-weight: 600;
            padding: 1rem 1.5rem;
        }

        .nav-tabs .nav-link.active {
            color: #19191b;
            background: transparent;
            border-bottom: 2px solid #19191b;
        }

        .tab-content {
            padding: 2rem 0;
        }

        .related-products {
            margin: 3rem 0;
        }

        .product-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            overflow: hidden;
            transition: transform 0.2s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-image-container {
            position: relative;
            padding-top: 100%;
            overflow: hidden;
        }

        .product-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .notification-container .alert {
            margin-bottom: 10px;
            min-width: 300px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 2rem;
        }

        .breadcrumb-item a {
            color: #666;
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: #19191b;
        }

        @media (max-width: 768px) {
            .product-info {
                padding-left: 0;
                margin-top: 2rem;
            }

            .product-title {
                font-size: 1.5rem;
            }

            .current-price {
                font-size: 1.5rem;
            }

            .main-image {
                height: 300px;
            }
        }

        .size-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .size-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .size-btn:hover {
            border-color: #19191b;
        }
        
        .size-btn.selected {
            background: #19191b;
            color: white;
            border-color: #19191b;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="notification-container"></div>

    <div class="container product-detail-section">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                <li class="breadcrumb-item"><a href="category.php?id=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>

        <div class="row">
            <!-- Product Images -->
            <div class="col-lg-6">
                <div class="product-image-gallery">
                    <img src="<?php echo htmlspecialchars($product_images[0]['image_path'] ?? 'assets/images/placeholder.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         class="main-image" id="mainImage">
                    
                    <?php if (count($product_images) > 1): ?>
                        <div class="thumbnail-images">
                            <?php foreach ($product_images as $index => $image): ?>
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                     onclick="changeMainImage(this)">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Info -->
            <div class="col-lg-6">
                <div class="product-info">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                    <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                    
                    <div class="product-rating">
                        <div class="rating-stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
                        </div>
                        <span class="text-muted">(4.0) · 24 reviews</span>
                    </div>

                    <div class="price-section">
                        <?php if ($product['sale_price']): ?>
                            <span class="current-price">₹<?php echo number_format($product['sale_price'], 2); ?></span>
                            <span class="original-price">₹<?php echo number_format($product['price'], 2); ?></span>
                            <span class="discount-badge">
                                <?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>% OFF
                            </span>
                        <?php else: ?>
                            <span class="current-price">₹<?php echo number_format($product['price'], 2); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Stock Status -->
                    <?php if ($product['stock_quantity'] > 10): ?>
                        <div class="stock-status in-stock">
                            <i class="fas fa-check-circle"></i> In Stock (<?php echo $product['stock_quantity']; ?> available)
                        </div>
                    <?php elseif ($product['stock_quantity'] > 0): ?>
                        <div class="stock-status low-stock">
                            <i class="fas fa-exclamation-triangle"></i> Only <?php echo $product['stock_quantity']; ?> left in stock
                        </div>
                    <?php else: ?>
                        <div class="stock-status out-of-stock">
                            <i class="fas fa-times-circle"></i> Out of Stock
                        </div>
                    <?php endif; ?>

                    <!-- Product Description -->
                    <div class="product-description mb-4">
                        <h4>Description</h4>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>

                    <?php if ($product['product_type'] === 'clothing' && !empty($product['sizes'])): ?>
                    <div class="size-selector mt-3">
                        <label>Available Sizes:</label>
                        <div class="size-buttons">
                            <?php
                            $sizes = explode(',', $product['sizes']);
                            foreach ($sizes as $size):
                                $size = trim($size);
                                if (!empty($size)):
                            ?>
                            <button type="button" class="size-btn" data-size="<?php echo htmlspecialchars($size); ?>" onclick="selectSize(this)">
                                <?php echo htmlspecialchars($size); ?>
                            </button>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                        <input type="hidden" id="selected_size" name="size" required>
                        <div class="invalid-feedback">Please select a size</div>
                    </div>
                    <?php endif; ?>

                    <?php if ($product['product_type'] === 'regular'): ?>
                        <?php if (!empty($product['features'])): ?>
                        <!-- Product Features -->
                        <div class="product-features mb-4">
                            <h4>Features</h4>
                            <ul class="list-unstyled">
                                <?php 
                                $features = explode("\n", $product['features']);
                                foreach ($features as $feature):
                                    if (trim($feature) !== ''):
                                ?>
                                    <li><i class="fas fa-check text-success me-2"></i><?php echo htmlspecialchars(trim($feature)); ?></li>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($product['specifications'])): ?>
                        <!-- Product Specifications -->
                        <div class="product-specifications mb-4">
                            <h4>Specifications</h4>
                            <table class="table table-striped">
                                <tbody>
                                    <?php 
                                    $specs = explode("\n", $product['specifications']);
                                    foreach ($specs as $spec):
                                        if (trim($spec) !== ''):
                                            list($key, $value) = array_pad(explode(':', $spec, 2), 2, '');
                                ?>
                                    <tr>
                                        <th scope="row"><?php echo htmlspecialchars(trim($key)); ?></th>
                                        <td><?php echo htmlspecialchars(trim($value)); ?></td>
                                    </tr>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Quantity Selector -->
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <form id="addToCartForm" class="mt-4">
                            <input type="hidden" id="product_id" name="product_id" value="<?php echo $product['id']; ?>">
                            <div class="quantity-selector">
                                <label for="quantity">Quantity:</label>
                                <div class="quantity-controls">
                                    <button type="button" class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" class="quantity-input">
                                    <button type="button" class="quantity-btn" onclick="changeQuantity(1)">+</button>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" class="add-to-cart-btn" id="addToCartBtn" data-product="<?php echo $product['id']; ?>" 
                                    <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                    <?php echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart'; ?>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Product Details Tabs -->
        <div class="product-details-tabs mt-5">
            <div class="tabs-section">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="features-tab" data-bs-toggle="tab" href="#features" role="tab">
                            Features
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="specifications-tab" data-bs-toggle="tab" href="#specifications" role="tab">
                            Specifications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="reviews-tab" data-bs-toggle="tab" href="#reviews" role="tab">
                            Reviews
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="features" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <?php if (!empty($product['features'])): ?>
                                <h4>Features</h4>
                                <ul>
                                    <?php
                                    $features = explode("\n", $product['features']);
                                    foreach ($features as $feature):
                                        if (!empty(trim($feature))):
                                    ?>
                                    <li><?php echo htmlspecialchars(trim($feature)); ?></li>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="specifications" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6">
                                <h4>Technical Specifications</h4>
                                <div class="product-specs">
                                    <?php if ($product['brand']): ?>
                                    <div class="spec-item">
                                        <span class="spec-label">Brand:</span>
                                        <span><?php echo htmlspecialchars($product['brand']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="spec-item">
                                        <span class="spec-label">Category:</span>
                                        <span><?php echo htmlspecialchars($product['category_name']); ?></span>
                                    </div>
                                    <?php if ($product['sku']): ?>
                                    <div class="spec-item">
                                        <span class="spec-label">SKU:</span>
                                        <span><?php echo htmlspecialchars($product['sku']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($product['specifications'])): ?>
                                        <?php
                                        $specs = explode("\n", $product['specifications']);
                                        foreach ($specs as $spec):
                                            if (!empty(trim($spec))):
                                                $parts = explode(':', $spec, 2);
                                                if (count($parts) === 2):
                                        ?>
                                        <div class="spec-item">
                                            <span class="spec-label"><?php echo htmlspecialchars(trim($parts[0])); ?>:</span>
                                            <span><?php echo htmlspecialchars(trim($parts[1])); ?></span>
                                        </div>
                                        <?php
                                                endif;
                                            endif;
                                        endforeach;
                                        ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <h4>Customer Reviews</h4>
                                <div class="review-item mb-4 pb-4 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong>John Doe</strong>
                                            <div class="rating-stars">
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                            </div>
                                        </div>
                                        <small class="text-muted">2 days ago</small>
                                    </div>
                                    <p>Excellent product! Great quality and fast delivery. Highly recommended!</p>
                                </div>
                                <div class="review-item mb-4 pb-4 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong>Jane Smith</strong>
                                            <div class="rating-stars">
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="far fa-star"></i>
                                            </div>
                                        </div>
                                        <small class="text-muted">1 week ago</small>
                                    </div>
                                    <p>Good product overall, but could be improved. Value for money is decent.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
            <div class="related-products">
                <h3 class="mb-4">Related Products</h3>
                <div class="row g-4">
                    <?php foreach ($related_products as $related): ?>
                        <div class="col-md-3">
                            <div class="product-card">
                                <div class="product-image-container">
                                    <a href="product.php?id=<?php echo $related['id']; ?>">
                                        <img src="<?php echo htmlspecialchars($related['image'] ?? 'assets/images/placeholder.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($related['name']); ?>" 
                                             class="product-image">
                                    </a>
                                </div>
                                <div class="p-3">
                                    <h5 class="mb-2">
                                        <a href="product.php?id=<?php echo $related['id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($related['name']); ?>
                                        </a>
                                    </h5>
                                    <div class="text-muted mb-2"><?php echo htmlspecialchars($related['category_name']); ?></div>
                                    <div class="fw-bold">
                                        <?php if ($related['sale_price']): ?>
                                            ₹<?php echo number_format($related['sale_price'], 2); ?>
                                            <small class="text-muted text-decoration-line-through">
                                                ₹<?php echo number_format($related['price'], 2); ?>
                                            </small>
                                        <?php else: ?>
                                            ₹<?php echo number_format($related['price'], 2); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Change main image when thumbnail is clicked
        function changeMainImage(thumbnail) {
            document.getElementById('mainImage').src = thumbnail.src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            thumbnail.classList.add('active');
        }

        // Quantity controls
        function changeQuantity(change) {
            const quantityInput = document.getElementById('quantity');
            const newValue = parseInt(quantityInput.value) + change;
            const maxQuantity = parseInt(quantityInput.max);
            
            if (newValue >= 1 && newValue <= maxQuantity) {
                quantityInput.value = newValue;
            }
        }

        // Notification system
        function showNotification(message, type = 'success') {
            const container = document.querySelector('.notification-container');
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show`;
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            container.appendChild(notification);

            // Auto remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 150);
            }, 3000);
        }

        // Size selection functionality
        function selectSize(button) {
            // Remove selected class from all size buttons
            document.querySelectorAll('.size-btn').forEach(btn => btn.classList.remove('selected'));
            
            // Add selected class to clicked button
            button.classList.add('selected');
            
            // Update hidden input value
            document.getElementById('selected_size').value = button.dataset.size;
        }

        // Add to Cart functionality
        document.getElementById('addToCartForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const productId = document.getElementById('product_id').value;
            const quantity = document.getElementById('quantity').value;
            const size = document.getElementById('selected_size')?.value;
            
            // Validate size for clothing products
            if (document.querySelector('.size-selector') && !size) {
                showNotification('Please select a size', 'danger');
                return;
            }
            
            // Create form data
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            if (size) {
                formData.append('size', size);
            }
            
            // Disable the button and show loading state
            const addToCartBtn = document.getElementById('addToCartBtn');
            const originalBtnText = addToCartBtn.innerHTML;
            addToCartBtn.disabled = true;
            addToCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            
            // Send AJAX request
            fetch('add-to-cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart count
                    const cartBadge = document.querySelector('.cart-badge');
                    if (cartBadge) {
                        cartBadge.textContent = data.cart_count;
                    }
                    
                    // Show success notification
                    showNotification(data.message || 'Product added to cart successfully', 'success');
                } else {
                    if (data.error === 'login_required') {
                        window.location.href = 'login.php';
                    } else {
                        showNotification(data.message || 'Failed to add product to cart', 'danger');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error adding product to cart. Please try again.', 'danger');
            })
            .finally(() => {
                // Re-enable the button and restore original text
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = originalBtnText;
            });
        });

        // Add to Wishlist functionality
        function addToWishlist(productId) {
            // This would typically send an AJAX request to add to wishlist
            showNotification('Product added to wishlist', 'success');
        }

        // Update quantity when input changes
        document.getElementById('quantity').addEventListener('change', function() {
            const maxQuantity = parseInt(this.max);
            const minQuantity = 1;
            
            if (this.value > maxQuantity) {
                this.value = maxQuantity;
            } else if (this.value < minQuantity) {
                this.value = minQuantity;
            }
        });
    </script>
</body>
</html>