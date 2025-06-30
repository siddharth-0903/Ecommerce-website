<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/cart.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get featured products
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.is_featured = 1 AND p.status = 'active' 
          ORDER BY p.created_at DESC 
          LIMIT 8";
$stmt = $db->prepare($query);
$stmt->execute();
$featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get latest products
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.status = 'active' 
          ORDER BY p.created_at DESC 
          LIMIT 8";
$stmt = $db->query($query);
$latestProducts = $stmt->fetchAll();

// Get categories
$query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$stmt = $db->query($query);
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAREBLOCKS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <!-- Add this right after the opening body tag -->
    <div class="notification-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <!-- Background Image (Desktop only) -->
        <img class="hero-bg d-none d-lg-block" src="https://cdn.rareblocks.xyz/collection/clarity-ecommerce/images/hero/4/background.png" alt="">

        <!-- Desktop Navigation -->
        <div class="hero-nav d-none d-lg-block">
            <div class="container">
                <nav class="d-flex gap-4">
                    <?php
                    foreach ($categories as $category) {
                        echo '<a href="category.php?id=' . $category['id'] . '" class="nav-link">' . htmlspecialchars($category['name']) . '</a>';
                    }
                    ?>
                </nav>
            </div>
        </div>

        <!-- Hero Content -->
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <p class="coupon-text">Use "Save10" coupon to get 10% flat discount</p>
                        <h1 class="hero-title">Your one-stop shop for everything you love.</h1>
                        <div class="mt-4">
                            <a href="#featured-products" class="cta-button">Start shopping</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 d-lg-none">
                    <img class="hero-image" src="https://cdn.rareblocks.xyz/collection/clarity-ecommerce/images/hero/4/bg.png" alt="Hero Image">
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section class="featured-products py-5">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="section-title mb-0">Featured Products</h2>
                <a href="products.php" class="btn btn-outline-primary">
                    View All Products <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
            <div class="row g-4">
                <?php foreach ($featured_products as $product): ?>
                    <div class="col-md-3">
                        <div class="product-card">
                        <div class="product-image-container">
                                    <img src="<?php echo htmlspecialchars($product['image'] ?? 'assets/images/placeholder.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="product-image">
                                <?php if ($product['sale_price']): ?>
                                    <div class="product-badge sale-badge">
                                        -<?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>%
                                    </div>
                                <?php endif; ?>
                                <?php if ($product['stock_quantity'] <= 0): ?>
                                    <div class="product-badge out-of-stock-badge">
                                        Out of Stock
                                    </div>
                                <?php endif; ?>
                                <div class="product-overlay">
                                    <a href="product_details.php?id=<?php echo $product['id']; ?>" class="quick-view-btn">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="wishlist-btn" data-product="<?php echo $product['id']; ?>">
                                        <i class="far fa-heart"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="product-info">
                                <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="product-category text-muted mb-2">
                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                </div>
                                <div class="product-rating mb-2">
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="far fa-star text-warning"></i>
                                    <span class="text-muted ms-1">(4.0)</span>
                                </div>
                                <div class="product-price">
                                    <?php if ($product['sale_price']): ?>
                                        <span class="current-price">$<?php echo number_format($product['sale_price'], 2); ?></span>
                                        <span class="original-price">$<?php echo number_format($product['price'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="current-price">$<?php echo number_format($product['price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                <button class="add-to-cart-btn" data-product="<?php echo $product['id']; ?>" 
                                    <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                    <?php echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <style>
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
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.1);
        }

        .product-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1;
        }

        .sale-badge {
            background: #ff4444;
            color: white;
        }

        .out-of-stock-badge {
            background: #6c757d;
            color: white;
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }

        .product-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .product-card:hover .product-overlay {
            opacity: 1;
        }

        .quick-view-btn, .wishlist-btn {
            background: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .quick-view-btn:hover, .wishlist-btn:hover {
            background: #19191b;
            color: white;
        }

        .product-info {
            padding: 1rem;
        }

        .product-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .product-category {
            font-size: 0.85rem;
        }

        .product-rating {
            font-size: 0.9rem;
        }

        .product-price {
            margin: 0.5rem 0;
        }

        .current-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: #19191b;
        }

        .original-price {
            font-size: 0.9rem;
            color: #999;
            text-decoration: line-through;
            margin-left: 0.5rem;
        }

        .add-to-cart-btn {
            width: 100%;
            background: #19191b;
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .add-to-cart-btn:hover {
            background: #ffe942;
            color: #19191b;
        }

        .add-to-cart-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .add-to-cart-btn:disabled:hover {
            background: #6c757d;
            color: white;
        }

        .btn-outline-primary {
            border-color: #19191b;
            color: #19191b;
            font-weight: 600;
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .btn-outline-primary:hover {
            background: #19191b;
            color: white;
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

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    </style>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="assets/main.js"></script>
    <script>
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

    // Add to Cart functionality
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.add-to-cart-btn').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.product;
                
                // Create form data
                const formData = new FormData();
                formData.append('product_id', productId);
                
                // Send AJAX request
                fetch('add-to-cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Response:', data); // Debug log
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
                    console.error('Error:', error); // Debug log
                    showNotification('Error adding product to cart', 'danger');
                });
            });
        });
    });
    </script>
</body>
</html>
