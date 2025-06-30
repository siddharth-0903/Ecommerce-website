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

// Handle customer status update
if ($_POST && isset($_POST['action']) && isset($_POST['customer_id'])) {
    $customer_id = (int)$_POST['customer_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'toggle_status') {
            $new_status = $_POST['new_status'];
            $query = "UPDATE users SET status = ? WHERE id = ? AND role = 'user'";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_status, $customer_id]);
            
            $message = "Customer status updated successfully!";
            $message_type = "success";
        } elseif ($action === 'delete_customer') {
            // Check if customer has orders
            $check_query = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$customer_id]);
            $order_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['order_count'];
            
            if ($order_count > 0) {
                $message = "Cannot delete customer with existing orders. Deactivate instead.";
                $message_type = "danger";
            } else {
                $delete_query = "DELETE FROM users WHERE id = ? AND role = 'user'";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->execute([$customer_id]);
                
                $message = "Customer deleted successfully!";
                $message_type = "success";
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query with filters
$where_conditions = ["role = 'user'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM users WHERE $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get customers with order statistics
$query = "SELECT 
    u.*,
    COUNT(DISTINCT o.id) as total_orders,
    COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) as total_spent,
    MAX(o.created_at) as last_order_date
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
WHERE $where_clause
GROUP BY u.id
ORDER BY u.created_at DESC
LIMIT $records_per_page OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customer statistics
$stats_query = "SELECT 
    COUNT(*) as total_customers,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_customers,
    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_customers,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_customers_30_days
FROM users WHERE role = 'user'";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management | Rareblocks Admin</title>
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

        /* Badges */
        .badge {
            padding: 0.5em 0.75em;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Action Buttons */
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        /* Customer Avatar */
        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Search and Filter Section */
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }

        /* Statistics Cards */
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
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

            .table-responsive {
                font-size: 0.85rem;
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
                    <a href="admin-view-customer.php" class="nav-link active">
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
                <h1 class="page-title">Customer Management</h1>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $stats['total_customers']; ?></h3>
                                <p class="mb-0 opacity-75">Total Customers</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card success">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-user-check fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $stats['active_customers']; ?></h3>
                                <p class="mb-0 opacity-75">Active Customers</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card warning">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-user-times fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $stats['inactive_customers']; ?></h3>
                                <p class="mb-0 opacity-75">Inactive Customers</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card info">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-user-plus fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $stats['new_customers_30_days']; ?></h3>
                                <p class="mb-0 opacity-75">New (30 days)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Customers</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Search by name, email, or phone..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status Filter</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label d-block">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">&nbsp;</label>
                        <a href="admin-view-customers.php" class="btn btn-outline-secondary">
                            <i class="fas fa-refresh"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Customers Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Customer List
                        <span class="badge bg-primary ms-2"><?php echo $total_records; ?> total</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Contact Info</th>
                                    <th>Order Stats</th>
                                    <th>Total Spent</th>
                                    <th>Last Order</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-users fa-3x mb-3"></i>
                                            <p>No customers found</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="customer-avatar me-3">
                                                <?php echo strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h6>
                                                <small class="text-muted">ID: #<?php echo str_pad($customer['id'], 6, '0', STR_PAD_LEFT); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div><i class="fas fa-envelope text-muted me-1"></i><?php echo htmlspecialchars($customer['email']); ?></div>
                                            <?php if ($customer['phone']): ?>
                                            <div><i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($customer['phone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $customer['total_orders']; ?> orders</span>
                                    </td>
                                    <td>
                                        <strong>₹<?php echo number_format($customer['total_spent'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($customer['last_order_date']): ?>
                                            <?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $customer['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($customer['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" 
                                                    data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" 
                                                       onclick="viewCustomerDetails(<?php echo $customer['id']; ?>)">
                                                        <i class="fas fa-eye me-2"></i>View Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" 
                                                       onclick="toggleCustomerStatus(<?php echo $customer['id']; ?>, '<?php echo $customer['status'] === 'active' ? 'inactive' : 'active'; ?>')">
                                                        <i class="fas fa-<?php echo $customer['status'] === 'active' ? 'ban' : 'check'; ?> me-2"></i>
                                                        <?php echo $customer['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                    </a>
                                                </li>
                                                <?php if ($customer['total_orders'] == 0): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" 
                                                       onclick="deleteCustomer(<?php echo $customer['id']; ?>)">
                                                        <i class="fas fa-trash me-2"></i>Delete Customer
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Customer pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                        <div class="text-center text-muted">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> customers
                        </div>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Details Modal -->
    <div class="modal fade" id="customerDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Customer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="customerDetailsContent">
                    <!-- Customer details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Forms for Actions -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="customer_id" id="statusCustomerId">
        <input type="hidden" name="new_status" id="newStatus">
    </form>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_customer">
        <input type="hidden" name="customer_id" id="deleteCustomerId">
    </form>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        // Toggle customer status
        function toggleCustomerStatus(customerId, newStatus) {
            if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this customer?`)) {
                document.getElementById('statusCustomerId').value = customerId;
                document.getElementById('newStatus').value = newStatus;
                document.getElementById('statusForm').submit();
            }
        }

        // Delete customer
        function deleteCustomer(customerId) {
            if (confirm('Are you sure you want to delete this customer? This action cannot be undone and is only allowed for customers with no orders.')) {
                document.getElementById('deleteCustomerId').value = customerId;
                document.getElementById('deleteForm').submit();
            }
        }

        // View customer details
        function viewCustomerDetails(customerId) {
            // You can implement AJAX call here to fetch customer details
            fetch(`get-customer-details.php?id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const content = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Personal Information</h6>
                                    <p><strong>Name:</strong> ${data.customer.first_name} ${data.customer.last_name}</p>
                                    <p><strong>Email:</strong> ${data.customer.email}</p>
                                    <p><strong>Phone:</strong> ${data.customer.phone || 'Not provided'}</p>
                                    <p><strong>Status:</strong> <span class="badge bg-${data.customer.status === 'active' ? 'success' : 'danger'}">${data.customer.status}</span></p>
                                    <p><strong>Joined:</strong> ${new Date(data.customer.created_at).toLocaleDateString()}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Order Statistics</h6>
                                    <p><strong>Total Orders:</strong> ${data.stats.total_orders}</p>
                                    <p><strong>Total Spent:</strong> ₹${parseFloat(data.stats.total_spent).toFixed(2)}</p>
                                    <p><strong>Average Order Value:</strong> ₹${data.stats.total_orders > 0 ? parseFloat(data.stats.total_spent / data.stats.total_orders).toFixed(2) : '0.00'}</p>
                                    <p><strong>Last Order:</strong> ${data.stats.last_order_date ? new Date(data.stats.last_order_date).toLocaleDateString() : 'Never'}</p>
                                </div>
                            </div>
                            ${data.customer.address ? `
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6>Address Information</h6>
                                    <p>${data.customer.address}</p>
                                </div>
                            </div>
                            ` : ''}
                        `;
                        document.getElementById('customerDetailsContent').innerHTML = content;
                        new bootstrap.Modal(document.getElementById('customerDetailsModal')).show();
                    } else {
                        alert('Error loading customer details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading customer details');
                });
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Search form auto-submit on Enter
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });

        // Responsive table handling
        function makeTableResponsive() {
            const table = document.querySelector('.table-responsive table');
            if (window.innerWidth < 768 && table) {
                table.classList.add('table-sm');
            }
        }

        window.addEventListener('resize', makeTableResponsive);
        makeTableResponsive();
    </script>
</body>
</html>