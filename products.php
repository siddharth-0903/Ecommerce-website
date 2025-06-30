<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/cart.php';

$database = new Database();
$db = $database->getConnection();

// Get categories for filter
$query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$stmt = $db->query($query);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build product query
$where_conditions = ["p.status = 'active'"];
$params = [];

// Category filter
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $where_conditions[] = "p.category_id = :category_id";
    $params[':category_id'] = $_GET['category'];
}

// Price range filter
if (isset($_GET['min_price']) && !empty($_GET['min_price'])) {
    $where_conditions[] = "p.price >= :min_price";
    $params[':min_price'] = $_GET['min_price'];
}
if (isset($_GET['max_price']) && !empty($_GET['max_price'])) {
    $where_conditions[] = "p.price <= :max_price";
    $params[':max_price'] = $_GET['max_price'];
}

// Search filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(p.name LIKE :search OR p.description LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

// Sort options
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$order_by = match($sort) {
    'price_low' => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'name_asc' => 'p.name ASC',
    'name_desc' => 'p.name DESC',
    default => 'p.created_at DESC'
};

// Build final query
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE " . implode(' AND ', $where_conditions) . " 
          ORDER BY " . $order_by;

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products | Rareblocks</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/products.css">
    <style>
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
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .product-overlay:hover {
            opacity: 1;
        }

        .add-to-cart-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .add-to-cart-btn:disabled:hover {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <div class="row">
        <!-- Filters Sidebar -->
        <div class="col-lg-3">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Filters</h5>
                    <form method="GET" id="filterForm">
                        <!-- Search -->
                        <div class="mb-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>

                        <!-- Categories -->
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Price Range -->
                        <div class="mb-3">
                            <label class="form-label">Price Range</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control" name="min_price" 
                                           placeholder="Min" value="<?php echo htmlspecialchars($_GET['min_price'] ?? ''); ?>">
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control" name="max_price" 
                                           placeholder="Max" value="<?php echo htmlspecialchars($_GET['max_price'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Sort -->
                        <div class="mb-3">
                            <label for="sort" class="form-label">Sort By</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                                <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Products</h1>
                <div class="text-muted">
                    <?php echo count($products); ?> products found
                </div>
            </div>

            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-3x mb-3 text-muted"></i>
                    <h3>No products found</h3>
                    <p class="text-muted">Try adjusting your filters or search terms</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($products as $product): ?>
                        <div class="col-md-4">
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
                                    <div class="product-price">
                                        <?php if ($product['sale_price']): ?>
                                            <span class="current-price">₹<?php echo number_format($product['sale_price'], 2); ?></span>
                                            <span class="original-price">₹<?php echo number_format($product['price'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="current-price">₹<?php echo number_format($product['price'], 2); ?></span>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-submit form when sort or category changes
document.getElementById('sort').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});

document.getElementById('category').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});

// Add to Cart functionality
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
            if (data.success) {
                // Update cart count
                const cartBadge = document.querySelector('.cart-badge');
                if (cartBadge) {
                    cartBadge.textContent = data.cart_count;
                }
                
                // Show success notification
                showNotification(data.message, 'success');
            } else {
                if (data.error === 'login_required') {
                    window.location.href = 'login.php';
                } else {
                    showNotification(data.message, 'danger');
                }
            }
        })
        .catch(error => {
            showNotification('Error adding product to cart', 'danger');
        });
    });
});

// Notification function
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification alert-${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>