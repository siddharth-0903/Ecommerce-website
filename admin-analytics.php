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

// Get date range filter
$date_filter = isset($_GET['period']) ? $_GET['period'] : '30';
$custom_start = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$custom_end = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Set date conditions based on filter
if ($date_filter === 'custom' && !empty($custom_start) && !empty($custom_end)) {
    $date_condition = "DATE(created_at) BETWEEN '$custom_start' AND '$custom_end'";
    $order_date_condition = "DATE(o.created_at) BETWEEN '$custom_start' AND '$custom_end'";
} else {
    $days = (int)$date_filter;
    $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    $order_date_condition = "o.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
}

// Revenue Analytics
$revenue_query = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as orders,
    SUM(total_amount) as revenue,
    AVG(total_amount) as avg_order_value
FROM orders 
WHERE payment_status = 'paid' AND $date_condition
GROUP BY DATE(created_at)
ORDER BY date ASC";
$revenue_stmt = $db->prepare($revenue_query);
$revenue_stmt->execute();
$revenue_data = $revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

// Product Performance
$product_query = "SELECT 
    p.name,
    p.sku,
    SUM(oi.quantity) as total_sold,
    SUM(oi.quantity * oi.price) as revenue,
    COUNT(DISTINCT oi.order_id) as orders
FROM products p
JOIN order_items oi ON p.id = oi.product_id
JOIN orders o ON oi.order_id = o.id
WHERE o.payment_status = 'paid' AND $order_date_condition
GROUP BY p.id, p.name, p.sku
ORDER BY total_sold DESC
LIMIT 10";
$product_stmt = $db->prepare($product_query);
$product_stmt->execute();
$top_products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);

// Customer Analytics
$customer_query = "SELECT 
    COUNT(DISTINCT user_id) as total_customers,
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_order_value
FROM orders 
WHERE payment_status = 'paid' AND $date_condition";
$customer_stmt = $db->prepare($customer_query);
$customer_stmt->execute();
$customer_stats = $customer_stmt->fetch(PDO::FETCH_ASSOC);

// Order Status Distribution
$status_query = "SELECT 
    status,
    COUNT(*) as count,
    ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM orders WHERE $date_condition)), 2) as percentage
FROM orders 
WHERE $date_condition
GROUP BY status
ORDER BY count DESC";
$status_stmt = $db->prepare($status_query);
$status_stmt->execute();
$order_status = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly Comparison (Previous Period)
$prev_days = $date_filter === 'custom' ? 30 : (int)$date_filter;
$prev_condition = "created_at >= DATE_SUB(NOW(), INTERVAL " . ($prev_days * 2) . " DAY) AND created_at < DATE_SUB(NOW(), INTERVAL $prev_days DAY)";

$comparison_query = "SELECT 
    COUNT(*) as prev_orders,
    COALESCE(SUM(total_amount), 0) as prev_revenue,
    COUNT(DISTINCT user_id) as prev_customers
FROM orders 
WHERE payment_status = 'paid' AND $prev_condition";
$comparison_stmt = $db->prepare($comparison_query);
$comparison_stmt->execute();
$prev_stats = $comparison_stmt->fetch(PDO::FETCH_ASSOC);

// Current period stats
$current_query = "SELECT 
    COUNT(*) as current_orders,
    COALESCE(SUM(total_amount), 0) as current_revenue,
    COUNT(DISTINCT user_id) as current_customers
FROM orders 
WHERE payment_status = 'paid' AND $date_condition";
$current_stmt = $db->prepare($current_query);
$current_stmt->execute();
$current_stats = $current_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate growth percentages
$revenue_growth = $prev_stats['prev_revenue'] > 0 ? 
    (($current_stats['current_revenue'] - $prev_stats['prev_revenue']) / $prev_stats['prev_revenue']) * 100 : 0;
$orders_growth = $prev_stats['prev_orders'] > 0 ? 
    (($current_stats['current_orders'] - $prev_stats['prev_orders']) / $prev_stats['prev_orders']) * 100 : 0;
$customers_growth = $prev_stats['prev_customers'] > 0 ? 
    (($current_stats['current_customers'] - $prev_stats['prev_customers']) / $prev_stats['prev_customers']) * 100 : 0;

// Category Performance
$category_query = "SELECT 
    c.name as category,
    COUNT(DISTINCT oi.product_id) as products_sold,
    SUM(oi.quantity) as total_quantity,
    SUM(oi.quantity * oi.price) as revenue
FROM categories c
JOIN products p ON c.id = p.category_id
JOIN order_items oi ON p.id = oi.product_id
JOIN orders o ON oi.order_id = o.id
WHERE o.payment_status = 'paid' AND $order_date_condition
GROUP BY c.id, c.name
ORDER BY revenue DESC";
$category_stmt = $db->prepare($category_query);
$category_stmt->execute();
$category_performance = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for JavaScript charts
$revenue_chart_data = [];
$revenue_labels = [];
foreach ($revenue_data as $row) {
    $revenue_labels[] = date('M d', strtotime($row['date']));
    $revenue_chart_data[] = (float)$row['revenue'];
}

$product_labels = [];
$product_sales = [];
foreach ($top_products as $product) {
    $product_labels[] = substr($product['name'], 0, 15) . (strlen($product['name']) > 15 ? '...' : '');
    $product_sales[] = (int)$product['total_sold'];
}

$status_labels = [];
$status_data = [];
foreach ($order_status as $status) {
    $status_labels[] = ucfirst($status['status']);
    $status_data[] = (int)$status['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard | Rareblocks Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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

        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .card-body h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        /* Statistics Cards */
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .stat-card.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-card.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-card.info {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }

        .stat-card.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .growth-indicator {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .growth-up {
            color: #10b981;
        }

        .growth-down {
            color: #ef4444;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            padding: 1rem;
        }

        .chart-container canvas {
            max-height: 300px;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }

        /* Tables */
        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-top: none;
            background: #f8f9fa;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.95rem;
        }

        /* Progress Bars */
        .progress {
            height: 8px;
            border-radius: 4px;
        }

        .progress-bar {
            border-radius: 4px;
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

            .chart-container {
                height: 250px;
            }
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
                    <a href="admin-view-products.php" class="nav-link">
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
                    <a href="admin-analytics.php" class="nav-link active">
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
                    <a href="admin-settings.php" class="nav-link">
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
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title">Analytics Dashboard</h1>
            </div>

            <!-- Date Filter Section -->
            <div class="filter-section">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="period" class="form-label">Time Period</label>
                        <select class="form-select" id="period" name="period" onchange="toggleCustomDates()">
                            <option value="7" <?php echo $date_filter === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="30" <?php echo $date_filter === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="90" <?php echo $date_filter === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                            <option value="365" <?php echo $date_filter === '365' ? 'selected' : ''; ?>>Last year</option>
                            <option value="custom" <?php echo $date_filter === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="customDates" style="display: <?php echo $date_filter === 'custom' ? 'block' : 'none'; ?>;">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $custom_start; ?>">
                    </div>
                    <div class="col-md-3" id="customDatesEnd" style="display: <?php echo $date_filter === 'custom' ? 'block' : 'none'; ?>;">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $custom_end; ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Key Metrics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-1 opacity-75">Total Revenue</h6>
                                <h3 class="mb-0">₹<?php echo number_format($current_stats['current_revenue'], 2); ?></h3>
                                <div class="growth-indicator mt-1">
                                    <i class="fas fa-<?php echo $revenue_growth >= 0 ? 'arrow-up growth-up' : 'arrow-down growth-down'; ?>"></i>
                                    <span class="<?php echo $revenue_growth >= 0 ? 'growth-up' : 'growth-down'; ?>">
                                        <?php echo abs(round($revenue_growth, 1)); ?>%
                                    </span>
                                </div>
                            </div>
                            <div>
                                <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card success">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-1 opacity-75">Total Orders</h6>
                                <h3 class="mb-0"><?php echo $current_stats['current_orders']; ?></h3>
                                <div class="growth-indicator mt-1">
                                    <i class="fas fa-<?php echo $orders_growth >= 0 ? 'arrow-up growth-up' : 'arrow-down growth-down'; ?>"></i>
                                    <span class="<?php echo $orders_growth >= 0 ? 'growth-up' : 'growth-down'; ?>">
                                        <?php echo abs(round($orders_growth, 1)); ?>%
                                    </span>
                                </div>
                            </div>
                            <div>
                                <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card info">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-1 opacity-75">Active Customers</h6>
                                <h3 class="mb-0"><?php echo $current_stats['current_customers']; ?></h3>
                                <div class="growth-indicator mt-1">
                                    <i class="fas fa-<?php echo $customers_growth >= 0 ? 'arrow-up growth-up' : 'arrow-down growth-down'; ?>"></i>
                                    <span class="<?php echo $customers_growth >= 0 ? 'growth-up' : 'growth-down'; ?>">
                                        <?php echo abs(round($customers_growth, 1)); ?>%
                                    </span>
                                </div>
                            </div>
                            <div>
                                <i class="fas fa-users fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card warning">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-1 opacity-75">Avg Order Value</h6>
                                <h3 class="mb-0">₹<?php echo $customer_stats['avg_order_value'] ? number_format($customer_stats['avg_order_value'], 2) : '0.00'; ?></h3>
                                <div class="growth-indicator mt-1">
                                    <i class="fas fa-chart-line opacity-75"></i>
                                    <span class="opacity-75">Per Order</span>
                                </div>
                            </div>
                            <div>
                                <i class="fas fa-chart-line fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <!-- Revenue Trend Chart -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>Revenue Trend
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Status Distribution -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Order Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Tables Row -->
            <div class="row g-4">
                <!-- Top Products -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-trophy me-2"></i>Top Selling Products
                            </h5>
                            <span class="badge bg-primary"><?php echo count($top_products); ?> products</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Product</th>
                                            <th>Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_products as $index => $product): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                    <small class="text-muted">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo $product['total_sold']; ?></span></td>
                                            <td><strong>₹<?php echo number_format($product['revenue'], 2); ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Performance -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-tags me-2">
                                <i class="fas fa-tags me-2"></i>Category Performance
                            </h5>
                            <span class="badge bg-success"><?php echo count($category_performance); ?> categories</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Category</th>
                                            <th>Products</th>
                                            <th>Quantity</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($category_performance as $index => $category): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($category['category']); ?></h6>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo $category['products_sold']; ?></span></td>
                                            <td><span class="badge bg-info"><?php echo $category['total_quantity']; ?></span></td>
                                            <td><strong>₹<?php echo number_format($category['revenue'], 2); ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Analytics Row -->
            <div class="row g-4 mt-4">
                <!-- Product Performance Chart -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Top Products Sales
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="productChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar toggle functionality
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        // Custom date range toggle
        function toggleCustomDates() {
            const period = document.getElementById('period').value;
            const customDates = document.getElementById('customDates');
            const customDatesEnd = document.getElementById('customDatesEnd');
            
            if (period === 'custom') {
                customDates.style.display = 'block';
                customDatesEnd.style.display = 'block';
            } else {
                customDates.style.display = 'none';
                customDatesEnd.style.display = 'none';
            }
        }

        // Chart configurations
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        Chart.defaults.color = '#6c757d';

        // Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($revenue_labels); ?>,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: <?php echo json_encode($revenue_chart_data); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                elements: {
                    point: {
                        hoverBackgroundColor: '#3b82f6'
                    }
                }
            }
        });

        // Order Status Pie Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_data); ?>,
                    backgroundColor: [
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#3b82f6',
                        '#8b5cf6',
                        '#f97316'
                    ],
                    borderWidth: 0,
                    hoverBorderWidth: 2,
                    hoverBorderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Top Products Bar Chart
        const productCtx = document.getElementById('productChart').getContext('2d');
        const productChart = new Chart(productCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($product_labels); ?>,
                datasets: [{
                    label: 'Units Sold',
                    data: <?php echo json_encode($product_sales); ?>,
                    backgroundColor: 'rgba(255, 233, 66, 0.8)',
                    borderColor: '#ffe942',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        // Auto-refresh charts every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);

        // Add smooth animations on load
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
</body>
</html>