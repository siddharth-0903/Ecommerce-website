<?php
require_once 'config/Database.php';
require_once 'includes/auth.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Get all categories
$query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $brand = $_POST['brand'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $sale_price = $_POST['sale_price'] ?? null;
    $sku = $_POST['sku'] ?? '';
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
    $category_id = $_POST['category_id'] ?? null;
    $status = $_POST['status'] ?? 'active';
    $featured = isset($_POST['featured']) ? 1 : 0;
    $product_type = $_POST['product_type'] ?? 'regular';
    $features = $_POST['features'] ?? null;
    $specifications = $_POST['specifications'] ?? null;
    $sizes = isset($_POST['sizes']) ? implode(',', $_POST['sizes']) : null;

    // Validate required fields
    if (empty($name) || empty($price) || empty($sku)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            // Generate slug from name
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

            // Check if SKU already exists
            $query = "SELECT COUNT(*) as count FROM products WHERE sku = :sku";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':sku', $sku);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                $error = "SKU already exists. Please use a different SKU.";
            } else {
                // Handle image upload
                $image = '';
                $product_images = [];
                if (isset($_FILES['image']) && is_array($_FILES['image']['name'])) {
                    $upload_dir = 'uploads/products/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    $max_files = 5;
                    $file_count = count($_FILES['image']['name']);

                    if ($file_count > $max_files) {
                        $error = "Maximum {$max_files} images allowed.";
                    } else {
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($_FILES['image']['error'][$i] === UPLOAD_ERR_OK) {
                                $file_extension = strtolower(pathinfo($_FILES['image']['name'][$i], PATHINFO_EXTENSION));

                                if (in_array($file_extension, $allowed_extensions)) {
                                    $file_name = uniqid() . '.' . $file_extension;
                                    $target_path = $upload_dir . $file_name;

                                    if (move_uploaded_file($_FILES['image']['tmp_name'][$i], $target_path)) {
                                        // First image will be the primary image
                                        $is_primary = ($i === 0) ? 1 : 0;
                                        $product_images[] = [
                                            'path' => $target_path,
                                            'is_primary' => $is_primary
                                        ];
                                        // Set the first image as the main product image
                                        if ($i === 0) {
                                            $image = $target_path;
                                        }
                                    } else {
                                        $error = "Error uploading image.";
                                        break;
                                    }
                                } else {
                                    $error = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
                                    break;
                                }
                            }
                        }
                    }
                }

                if (empty($error)) {
                    try {
                        $db->beginTransaction();

                        // Insert product
                        $query = "INSERT INTO products (name, brand, slug, description, price, sale_price, sku, stock_quantity, 
                                 category_id, image, status, featured, product_type, features, specifications, sizes) 
                                 VALUES (:name, :brand, :slug, :description, :price, :sale_price, :sku, :stock_quantity, 
                                 :category_id, :image, :status, :featured, :product_type, :features, :specifications, :sizes)";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':brand', $brand);
                        $stmt->bindParam(':slug', $slug);
                        $stmt->bindParam(':description', $description);
                        $stmt->bindParam(':price', $price);
                        $stmt->bindParam(':sale_price', $sale_price);
                        $stmt->bindParam(':sku', $sku);
                        $stmt->bindParam(':stock_quantity', $stock_quantity);
                        $stmt->bindParam(':category_id', $category_id);
                        $stmt->bindParam(':image', $image);
                        $stmt->bindParam(':status', $status);
                        $stmt->bindParam(':featured', $featured);
                        $stmt->bindParam(':product_type', $product_type);
                        $stmt->bindParam(':features', $features);
                        $stmt->bindParam(':specifications', $specifications);
                        $stmt->bindParam(':sizes', $sizes);

                        if ($stmt->execute()) {
                            $product_id = $db->lastInsertId();

                            // Insert product images
                            if (!empty($product_images)) {
                                $query = "INSERT INTO product_images (product_id, image_path, is_primary) VALUES (:product_id, :image_path, :is_primary)";
                                $stmt = $db->prepare($query);

                                foreach ($product_images as $img) {
                                    $stmt->bindParam(':product_id', $product_id);
                                    $stmt->bindParam(':image_path', $img['path']);
                                    $stmt->bindParam(':is_primary', $img['is_primary']);
                                    $stmt->execute();
                                }
                            }

                            $db->commit();
                            $success = "Product added successfully!";
                            // Clear form data
                            $_POST = array();
                        } else {
                            $db->rollBack();
                            $error = "Error adding product.";
                        }
                    } catch (PDOException $e) {
                        $db->rollBack();
                        $error = "Error: " . $e->getMessage();
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product | Rareblocks</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #19191b;
            --secondary-color: #ffe942;
            --sidebar-bg: #1a1a1c;
            --card-bg: #ffffff;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
        }

        body {
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* Sidebar Styles */
        .sidebar {
            background: var(--sidebar-bg);
            min-height: 100vh;
            width: 280px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #2d2d2f;
        }

        .sidebar-brand {
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }

        .sidebar-brand i {
            color: var(--secondary-color);
            margin-right: 0.75rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            color: #a3a3a3;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-radius: 0;
        }

        .nav-link:hover {
            background: rgba(255, 233, 66, 0.1);
            color: var(--secondary-color);
        }

        .nav-link.active {
            background: var(--secondary-color);
            color: var(--primary-color);
            font-weight: 600;
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 80px;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        .toggle-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-muted);
            cursor: pointer;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-menu .notification-btn {
            background: none;
            border: none;
            font-size: 1.1rem;
            color: var(--text-muted);
            position: relative;
            cursor: pointer;
        }

        .user-menu .notification-btn .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Page Content */
        .page-content {
            padding: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 2rem;
        }

        /* Form Card */
        .form-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .form-label {
            font-weight: 600;
            font-size: 0.97rem;
        }

        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 233, 66, 0.25);
        }

        .form-text {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        /* Image Upload */
        .image-upload {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .image-upload:hover {
            border-color: var(--secondary-color);
            background: rgba(255, 233, 66, 0.05);
        }

        .image-upload.dragover {
            border-color: var(--secondary-color);
            background: rgba(255, 233, 66, 0.1);
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .upload-text {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .upload-hint {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .image-preview {
            display: none;
            margin-top: 1rem;
        }

        .preview-item {
            display: inline-block;
            position: relative;
            margin-right: 1rem;
            margin-bottom: 1rem;
        }

        .preview-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--border-color);
        }

        .remove-img {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Tags Input */
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            min-height: 3rem;
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: text;
        }

        .tag {
            background: var(--secondary-color);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tag-remove {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.9rem;
        }

        .tag-input {
            border: none;
            outline: none;
            flex: 1;
            min-width: 120px;
            font-size: 0.9rem;
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            color: var(--primary-color);
        }

        .btn-secondary {
            background: white;
            border: 2px solid var(--border-color);
            color: var(--primary-color);
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            border-color: var(--secondary-color);
            background: rgba(255, 233, 66, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 2rem;
            border-top: 2px solid var(--border-color);
            margin-top: 2rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }

            .sidebar .nav-link span {
                display: none;
            }

            .main-content {
                margin-left: 80px;
            }

            .top-bar {
                padding: 1rem;
            }

            .page-content {
                padding: 1rem;
            }

            .form-card {
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
            }
        }

        /* Custom Checkbox/Radio */
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(255, 233, 66, 0.25);
        }

        /* Stock Status Indicator */
        .stock-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .stock-in-stock {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .stock-low-stock {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .stock-out-of-stock {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-cube"></i>
                <span>RAREBLOCKS</span>
            </a>
        </div>
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="admin-dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-view-orders.php" class="nav-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-view-products.php" class="nav-link active">
                        <i class="fas fa-box"></i>
                        <span>Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Customers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="category.php" class="nav-link">
                        <i class="fas fa-tag"></i>
                        <span>Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-truck"></i>
                        <span>Shipping</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-percent"></i>
                        <span>Coupons</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="top-bar">
            <button class="toggle-btn" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
            <div class="user-menu">
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </button>
                <div class="user-avatar">
                    AD
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Add New Product</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="#">Products</a></li>
                            <li class="breadcrumb-item active">Add Product</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <div class="mt-3">
                        <a href="admin-view-products.php" class="btn btn-primary me-2">View Products</a>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">Add Another Product</button>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="addProductForm">
                <!-- Basic Information -->
                <div class="form-card">
                    <div class="form-section">
                        <h3 class="section-title">Basic Information</h3>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="name" class="form-label">Product Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                <div class="form-text">Enter a clear, descriptive product name</div>
                            </div>
                            <div class="col-md-4">
                                <label for="sku" class="form-label">SKU *</label>
                                <input type="text" class="form-control" id="sku" name="sku" required
                                       value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>">
                                <div class="form-text">Unique product identifier</div>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="brand" class="form-label">Brand</label>
                                <input type="text" class="form-control" id="brand" name="brand" 
                                       value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>"
                                       placeholder="Enter brand name">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="product_type" class="form-label">Product Type</label>
                            <select class="form-select" id="product_type" name="product_type" required>
                                <option value="regular">Regular Product</option>
                                <option value="clothing">Clothing</option>
                            </select>
                        </div>

                        <div id="clothing-fields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Available Sizes</label>
                                <div class="row">
                                    <?php
                                    $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
                                    foreach ($sizes as $size):
                                    ?>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="sizes[]" 
                                                   value="<?php echo $size; ?>" id="size_<?php echo $size; ?>">
                                            <label class="form-check-label" for="size_<?php echo $size; ?>">
                                                <?php echo $size; ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div id="regular-fields">
                            <div class="mb-3">
                                <label for="features" class="form-label">Features (Optional)</label>
                                <textarea class="form-control" id="features" name="features" rows="4" 
                                          placeholder="Enter product features, one per line"></textarea>
                                <small class="text-muted">Enter each feature on a new line</small>
                            </div>

                            <div class="mb-3">
                                <label for="specifications" class="form-label">Specifications (Optional)</label>
                                <textarea class="form-control" id="specifications" name="specifications" rows="4" 
                                          placeholder="Enter product specifications in format: Key: Value"></textarea>
                                <small class="text-muted">Enter specifications in format: Key: Value (one per line)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="form-card">
                    <div class="form-section">
                        <h3 class="section-title">Pricing</h3>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="price" class="form-label">Regular Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           min="0" step="0.01" required
                                           value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="sale_price" class="form-label">Sale Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="sale_price" name="sale_price" 
                                           min="0" step="0.01"
                                           value="<?php echo htmlspecialchars($_POST['sale_price'] ?? ''); ?>">
                                </div>
                                <div class="form-text">Leave empty if no sale price</div>
                            </div>
                            <div class="col-md-4">
                                <label for="cost_price" class="form-label">Cost Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="cost_price" name="cost_price" 
                                           min="0" step="0.01"
                                           value="<?php echo htmlspecialchars($_POST['cost_price'] ?? ''); ?>">
                                </div>
                                <div class="form-text">For profit calculations</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory -->
                <div class="form-card">
                    <div class="form-section">
                        <h3 class="section-title">Inventory</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" value="<?php echo htmlspecialchars($_POST['stock_quantity'] ?? '0'); ?>" min="0" required>
                                <small class="form-text">Number of items in stock</small>
                            </div>
                            <div class="col-md-6">
                                <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                                <input type="number" class="form-control" id="low_stock_threshold" 
                                       name="low_stock_threshold" min="0" value="5"
                                       value="<?php echo htmlspecialchars($_POST['low_stock_threshold'] ?? '5'); ?>">
                                <div class="form-text">Alert when stock falls below this number</div>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="track_inventory" 
                                           name="track_inventory" checked>
                                    <label class="form-check-label" for="track_inventory">
                                        Track inventory for this product
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="allow_backorders" 
                                           name="allow_backorders">
                                    <label class="form-check-label" for="allow_backorders">
                                        Allow backorders when out of stock
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Images -->
                <div class="form-card">
                    <div class="form-section">
                        <h3 class="section-title">Product Images</h3>
                        <div class="image-upload" id="imageUpload">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">Click to upload or drag and drop</div>
                            <div class="upload-hint">PNG, JPG, GIF up to 10MB each (Max 5 images)</div>
                            <input type="file" id="image" name="image[]" multiple accept="image/*" style="display: none;">
                        </div>
                        <div class="image-preview" id="imagePreview"></div>
                    </div>
                </div>

                <!-- Shipping -->
                <div class="form-card">
                    <div class="form-section">
                        <h3 class="section-title">Shipping</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="shipping_class" class="form-label">Shipping Class</label>
                                <select class="form-select" id="shipping_class" name="shipping_class">
                                    <option value="">Default Shipping</option>
                                    <option value="free">Free Shipping</option>
                                    <option value="express">Express Shipping</option>
                                    <option value="heavy">Heavy Item</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="requires_shipping" 
                                           name="requires_shipping" checked>
                                    <label class="form-check-label" for="requires_shipping">
                                        This product requires shipping
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEO & Tags -->
                <div class="form-card">
                    <div class="form-section">
                        <h3 class="section-title">SEO & Tags</h3>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="tags" class="form-label">Product Tags</label>
                                <div class="tags-container" id="tagsContainer">
                                    <input type="text" class="tag-input" id="tagInput" placeholder="Add tags and press Enter...">
                                </div>
                                <div class="form-text">Add relevant tags to help customers find your product</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Product</button>
                    <a href="admin-view-products.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        // Image upload functionality
        const imageUpload = document.getElementById('imageUpload');
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');

        imageUpload.addEventListener('click', () => imageInput.click());

        imageUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            imageUpload.classList.add('dragover');
        });

        imageUpload.addEventListener('dragleave', () => {
            imageUpload.classList.remove('dragover');
        });

        imageUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            imageUpload.classList.remove('dragover');
            imageInput.files = e.dataTransfer.files;
            handleImageUpload();
        });

        imageInput.addEventListener('change', handleImageUpload);

        function handleImageUpload() {
            imagePreview.style.display = 'block';
            imagePreview.innerHTML = '';

            Array.from(imageInput.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-item';
                    previewItem.innerHTML = `
                        <img src="${e.target.result}" class="preview-img" alt="Preview">
                        <button type="button" class="remove-img" onclick="removeImage(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    imagePreview.appendChild(previewItem);
                };
                reader.readAsDataURL(file);
            });
        }

        function removeImage(index) {
            const dt = new DataTransfer();
            const files = imageInput.files;

            for (let i = 0; i < files.length; i++) {
                if (i !== index) {
                    dt.items.add(files[i]);
                }
            }

            imageInput.files = dt.files;
            handleImageUpload();
        }

        // Tags functionality
        const tagsContainer = document.getElementById('tagsContainer');
        const tagInput = document.getElementById('tagInput');
        const tags = new Set();

        tagInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && tagInput.value.trim()) {
                e.preventDefault();
                addTag(tagInput.value.trim());
                tagInput.value = '';
            }
        });

        function addTag(tag) {
            if (!tags.has(tag)) {
                tags.add(tag);
                const tagElement = document.createElement('div');
                tagElement.className = 'tag';
                tagElement.innerHTML = `
                    ${tag}
                    <button type="button" class="tag-remove" onclick="removeTag('${tag}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                tagsContainer.insertBefore(tagElement, tagInput);
            }
        }

        function removeTag(tag) {
            tags.delete(tag);
            const tagElements = tagsContainer.getElementsByClassName('tag');
            Array.from(tagElements).forEach(element => {
                if (element.textContent.trim() === tag) {
                    element.remove();
                }
            });
        }

        // Stock status indicator
        const stockInput = document.getElementById('stock_quantity');
        const stockStatus = document.getElementById('stockStatus');
        const lowStockThreshold = document.getElementById('low_stock_threshold');

        function updateStockStatus() {
            const stock = parseInt(stockInput.value) || 0;
            const threshold = parseInt(lowStockThreshold.value) || 5;

            if (stock === 0) {
                stockStatus.className = 'stock-indicator stock-out-of-stock';
                stockStatus.innerHTML = '<i class="fas fa-times-circle"></i> Out of Stock';
            } else if (stock <= threshold) {
                stockStatus.className = 'stock-indicator stock-low-stock';
                stockStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Low Stock';
            } else {
                stockStatus.className = 'stock-indicator stock-in-stock';
                stockStatus.innerHTML = '<i class="fas fa-check-circle"></i> In Stock';
            }
        }

        stockInput.addEventListener('input', updateStockStatus);
        lowStockThreshold.addEventListener('input', updateStockStatus);

        // Form reset functionality
        function resetForm() {
            document.getElementById('addProductForm').reset();
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('imagePreview').innerHTML = '';
            tags.clear();
            Array.from(tagsContainer.getElementsByClassName('tag')).forEach(tag => tag.remove());
            updateStockStatus();
            document.querySelector('.alert-success').style.display = 'none';
        }

        // Show/hide fields based on product type
        document.getElementById('product_type').addEventListener('change', function() {
            const clothingFields = document.getElementById('clothing-fields');
            const regularFields = document.getElementById('regular-fields');
            
            if (this.value === 'clothing') {
                clothingFields.style.display = 'block';
                regularFields.style.display = 'none';
            } else {
                clothingFields.style.display = 'none';
                regularFields.style.display = 'block';
            }
        });
    </script>
</body>
</html>