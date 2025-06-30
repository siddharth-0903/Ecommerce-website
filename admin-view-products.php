<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management | Rareblocks</title>
    <?php
    // Database connection
    require_once 'config/Database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Initialize variables for filters
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $stock = isset($_GET['stock']) ? $_GET['stock'] : '';

    // Build the query with filters
    $query = "SELECT p.*, c.name as category_name, 
              (SELECT pi.image_path FROM product_images pi 
               WHERE pi.product_id = p.id AND pi.is_primary = 1 
               LIMIT 1) as primary_image
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE 1=1";

    $params = [];

    if (!empty($search)) {
        $query .= " AND (p.name LIKE :search OR p.sku LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if (!empty($category)) {
        $query .= " AND p.category_id = :category";
        $params[':category'] = $category;
    }

    if (!empty($status)) {
        $query .= " AND p.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($stock)) {
        switch ($stock) {
            case 'in':
                $query .= " AND p.stock_quantity > 5";
                break;
            case 'low':
                $query .= " AND p.stock_quantity > 0 AND p.stock_quantity <= 5";
                break;
            case 'out':
                $query .= " AND p.stock_quantity = 0";
                break;
        }
    }

    $query .= " ORDER BY p.created_at DESC";

    // Prepare and execute the query
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
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
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
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

        /* Filter Bar */
        .filter-bar {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .filter-input {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            width: 200px;
        }

        .filter-input:focus {
            border-color: var(--secondary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(255, 233, 66, 0.2);
        }

        .filter-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-btn:hover {
            background: var(--secondary-color);
            color: var(--primary-color);
        }

        .filter-btn.secondary {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }

        /* Products Grid/List */
        .products-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .view-toggle {
            display: flex;
            gap: 0.5rem;
        }

        .view-btn {
            background: none;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.5rem;
            color: var(--text-muted);
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .view-btn.active {
            background: var(--secondary-color);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }

        .add-product-btn {
            background: var(--secondary-color);
            color: var(--primary-color);
            border: none;
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .add-product-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Products Table */
        .products-table {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
        }

        .custom-table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .custom-table th {
            background: #f8f9fa;
            border: none;
            border-bottom: 2px solid var(--border-color);
            padding: 1rem;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .custom-table td {
            border: none;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
            vertical-align: middle;
        }

        .custom-table tbody tr:hover {
            background: rgba(255, 233, 66, 0.05);
        }

        /* Product Card */
        .product-image {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .product-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }

        .product-sku {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .status-draft {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .stock-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .stock-in {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .stock-low {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .stock-out {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        /* Action Buttons */
        .action-btn {
            background: none;
            border: none;
            padding: 0.25rem;
            margin: 0 0.25rem;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            background: var(--secondary-color);
            color: var(--primary-color);
        }

        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .pagination-info {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
        }

        .page-btn {
            background: none;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .page-btn:hover {
            background: var(--secondary-color);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }

        .page-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-input {
                width: 100%;
            }

            .products-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }

        /* Bulk Actions */
        .bulk-actions {
            display: none;
            background: var(--secondary-color);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            align-items: center;
            gap: 1rem;
        }

        .bulk-actions.show {
            display: flex;
        }

        .bulk-text {
            color: var(--primary-color);
            font-weight: 600;
        }

        .bulk-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            cursor: pointer;
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
                    <a href="admin-view-customer.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Customers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-analytics.php" class="nav-link">
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
                    <a href="admin-setting.php" class="nav-link">
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
                    JD
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title">Products Management</h1>
                <a href="add-product.php" class="add-product-btn">
                    <i class="fas fa-plus"></i>
                    Add New Product
                </a>
            </div>

            <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" action="" id="filterForm">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="filter-label">Search</label>
                            <input type="text" class="filter-input" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Category</label>
                            <select class="filter-input" name="category">
                                <option value="">All Categories</option>
                                <?php
                                $catQuery = "SELECT id, name FROM categories WHERE status = 'active' ORDER BY name";
                                $catStmt = $db->query($catQuery);
                                while ($cat = $catStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($category == $cat['id']) ? 'selected' : '';
                                    echo '<option value="' . $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select class="filter-input" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="draft" <?php echo ($status === 'draft') ? 'selected' : ''; ?>>Draft</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Stock</label>
                            <select class="filter-input" name="stock">
                                <option value="">All Stock</option>
                                <option value="in" <?php echo ($stock === 'in') ? 'selected' : ''; ?>>In Stock</option>
                                <option value="low" <?php echo ($stock === 'low') ? 'selected' : ''; ?>>Low Stock</option>
                                <option value="out" <?php echo ($stock === 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                            </select>
                        </div>
                        <div class="filter-group" style="margin-top: 1.5rem;">
                            <button type="submit" class="filter-btn">Apply Filters</button>
                            <button type="button" class="filter-btn secondary" onclick="clearFilters()">Clear</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulkActions">
                <span class="bulk-text">3 items selected</span>
                <button class="bulk-btn">Delete Selected</button>
                <button class="bulk-btn">Bulk Edit</button>
                <button class="bulk-btn">Export Selected</button>
            </div>

            <!-- Products Table -->
            <div class="products-table">
                <div class="products-header">
                    <div class="d-flex align-items-center gap-3">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        <span class="filter-label">Select All</span>
                    </div>
                    <div class="view-toggle">
                        <button class="view-btn active" title="List View">
                            <i class="fas fa-list"></i>
                        </button>
                        <button class="view-btn" title="Grid View">
                            <i class="fas fa-th"></i>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table custom-table">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" onchange="toggleSelectAll()">
                                </th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Featured</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($products as $product):
                                $stockClass = '';
                                $stockText = '';
                                if ($product['stock_quantity'] > 5) {
                                    $stockClass = 'stock-in';
                                    $stockText = 'In Stock (' . $product['stock_quantity'] . ')';
                                } elseif ($product['stock_quantity'] > 0) {
                                    $stockClass = 'stock-low';
                                    $stockText = 'Low Stock (' . $product['stock_quantity'] . ')';
                                } else {
                                    $stockClass = 'stock-out';
                                    $stockText = 'Out of Stock';
                                }
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="product-checkbox">
                                </td>
                                <td>
                                    <div class="product-info">
                                        <div class="product-image">
                                            <?php if (!empty($product['primary_image']) && file_exists($product['primary_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($product['primary_image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            <?php elseif (!empty($product['image']) && file_exists($product['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-box fa-lg"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <div class="product-sku">SKU: <?php echo htmlspecialchars($product['sku']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($product['sku']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                <td class="fw-semibold">
                                    <?php if ($product['sale_price']): ?>
                                        ₹<?php echo number_format($product['sale_price'], 2); ?>
                                        <small class="text-muted text-decoration-line-through">₹<?php echo number_format($product['price'], 2); ?></small>
                                    <?php else: ?>
                                        ₹<?php echo number_format($product['price'], 2); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="stock-badge <?php echo $stockClass; ?>"><?php echo $stockText; ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span>
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input featured-toggle" type="checkbox" 
                                               data-product-id="<?php echo $product['id']; ?>"
                                               <?php echo $product['is_featured'] ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($product['created_at'])); ?></td>
                                <td>
                                    <button class="action-btn" title="View" onclick="viewProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn" title="Edit" onclick="editProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn" title="Duplicate" onclick="duplicateProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button class="action-btn" title="Delete" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-trash text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing 1-5 of 47 products
                    </div>
                    <div class="pagination">
                        <button class="page-btn" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <button class="page-btn">...</button>
                        <button class="page-btn">10</button>
                        <button class="page-btn">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
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

        // Select all functionality
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const productCheckboxes = document.querySelectorAll('.product-checkbox');
            const bulkActions = document.getElementById('bulkActions');
            
            productCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateBulkActions();
        }

        // Update bulk actions visibility
        function updateBulkActions() {
            const selectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const bulkText = bulkActions.querySelector('.bulk-text');
            
            if (selectedCheckboxes.length > 0) {
                bulkActions.classList.add('show');
                bulkText.textContent = `${selectedCheckboxes.length} items selected`;
            } else {
                bulkActions.classList.remove('show');
            }
        }

        // Add event listeners to product checkboxes
        document.querySelectorAll('.product-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActions);
        });

        // Update filter functions
        function applyFilters() {
            document.getElementById('filterForm').submit();
        }

        function clearFilters() {
            window.location.href = window.location.pathname;
        }

        // Update product action functions
        function viewProduct(productId) {
            window.location.href = `view-product.php?id=${productId}`;
        }

        function editProduct(productId) {
            window.location.href = `edit-product.php?id=${productId}`;
        }

        function duplicateProduct(productId) {
            if (confirm('Are you sure you want to duplicate this product?')) {
                window.location.href = `duplicate-product.php?id=${productId}`;
            }
        }

        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                window.location.href = `delete-product.php?id=${productId}`;
            }
        }

        // View toggle functionality
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Here you would implement the actual view switching logic
                if (this.querySelector('.fa-th')) {
                    // Switch to grid view
                    console.log('Switching to grid view');
                } else {
                    // Switch to list view
                    console.log('Switching to list view');
                }
            });
        });

        // Featured product toggle functionality
        document.querySelectorAll('.featured-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const productId = this.dataset.productId;
                const isFeatured = this.checked;
                const originalState = !isFeatured; // Store original state for reverting

                // Show loading state
                this.disabled = true;

                fetch('update-featured.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&is_featured=${isFeatured ? 1 : 0}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Show success message
                        const toast = document.createElement('div');
                        toast.className = 'toast show position-fixed bottom-0 end-0 m-3';
                        toast.innerHTML = `
                            <div class="toast-header bg-success text-white">
                                <strong class="me-auto">Success</strong>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                            </div>
                            <div class="toast-body">
                                Product featured status updated successfully
                            </div>
                        `;
                        document.body.appendChild(toast);
                        setTimeout(() => toast.remove(), 3000);
                    } else {
                        // Revert the checkbox if update failed
                        this.checked = originalState;
                        alert(data.message || 'Failed to update featured status');
                    }
                })
                .catch(error => {
                    // Revert the checkbox if request failed
                    this.checked = originalState;
                    console.error('Error:', error);
                    alert('Error updating featured status. Please try again.');
                })
                .finally(() => {
                    // Re-enable the toggle
                    this.disabled = false;
                });
            });
        });
    </script>
</body>
</html>