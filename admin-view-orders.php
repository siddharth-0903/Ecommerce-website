<?php
require_once 'config/database.php';
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

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = $_POST['order_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    if ($order_id && $status) {
        try {
            $query = "UPDATE orders SET status = :status WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $order_id);
            
            if ($stmt->execute()) {
                $success = "Order status updated successfully";
            } else {
                $error = "Error updating order status";
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Build query for orders
$where_conditions = [];
$params = [];

// Status filter
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_conditions[] = "o.status = :status";
    $params[':status'] = $_GET['status'];
}

// Date range filter
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $where_conditions[] = "o.created_at >= :start_date";
    $params[':start_date'] = $_GET['start_date'] . ' 00:00:00';
}
if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $where_conditions[] = "o.created_at <= :end_date";
    $params[':end_date'] = $_GET['end_date'] . ' 23:59:59';
}

// Search filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(o.id LIKE :search OR u.email LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

// Build final query
$query = "SELECT o.*, u.first_name, u.last_name, u.email, u.phone, u.shipping_address 
          FROM orders o 
          JOIN users u ON o.user_id = u.id";

// Add WHERE clause if there are conditions
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

$query .= " ORDER BY o.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management | Rareblocks</title>
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

        /* Form Controls */
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 233, 66, 0.25);
        }

        /* Buttons */
        .btn {
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
            color: var(--primary-color);
        }

        .btn-secondary {
            background: #f8f9fa;
            border-color: var(--border-color);
            color: var(--text-muted);
        }

        .btn-secondary:hover {
            background: #e9ecef;
            border-color: #dee2e6;
            color: var(--primary-color);
        }

        /* Modal */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.5rem;
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
                    <a href="admin-view-orders.php" class="nav-link active">
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
                    AD
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="page-title">Orders Management</h1>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                                           placeholder="Order ID, Customer...">
                                </div>
                                <div class="col-md-2">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo (isset($_GET['status']) && $_GET['status'] === 'processing') ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo (isset($_GET['status']) && $_GET['status'] === 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo (isset($_GET['status']) && $_GET['status'] === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                    <a href="admin-view-orders.php" class="btn btn-secondary">Clear</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Orders Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Payment Method</th>
                                            <th>Payment Status</th>
                                            <th>Order Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?></td>
                                            <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                            <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($order['payment_method']) {
                                                        'razorpay' => 'primary',
                                                        'cod' => 'info',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php 
                                                    echo match($order['payment_method']) {
                                                        'razorpay' => 'Razorpay',
                                                        'cod' => 'Cash on Delivery',
                                                        default => ucfirst($order['payment_method'])
                                                    };
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($order['payment_status']) {
                                                        'paid' => 'success',
                                                        'pending' => 'warning',
                                                        'failed' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($order['status']) {
                                                        'pending' => 'warning',
                                                        'processing' => 'info',
                                                        'shipped' => 'primary',
                                                        'delivered' => 'success',
                                                        'cancelled' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary me-1" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" onclick="updateStatus(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" id="order_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Order Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Customer Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>Name:</th>
                                    <td id="customer-name"></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td id="customer-email"></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td id="customer-phone"></td>
                                </tr>
                                <tr>
                                    <th>Shipping Address:</th>
                                    <td id="customer-address"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Order Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>Order ID:</th>
                                    <td id="order-id"></td>
                                </tr>
                                <tr>
                                    <th>Order Date:</th>
                                    <td id="order-date"></td>
                                </tr>
                                <tr>
                                    <th>Expected Delivery:</th>
                                    <td id="delivery-date"></td>
                                </tr>
                                <tr>
                                    <th>Payment Method:</th>
                                    <td id="payment-method"></td>
                                </tr>
                                <tr>
                                    <th>Payment Status:</th>
                                    <td id="payment-status"></td>
                                </tr>
                                <tr>
                                    <th>Order Status:</th>
                                    <td id="order-status"></td>
                                </tr>
                                <tr>
                                    <th>Total Amount:</th>
                                    <td id="order-amount"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h6 class="mb-3">Order Items</h6>
                        <div id="order-items"></div>
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

        function viewOrderDetails(orderId) {
            // Fetch order details via AJAX
            fetch(`get-order-details.php?id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('customer-name').textContent = `${data.first_name} ${data.last_name}`;
                    document.getElementById('customer-email').textContent = data.email;
                    document.getElementById('customer-phone').textContent = data.phone || 'Not provided';
                    document.getElementById('customer-address').textContent = data.shipping_address || 'Not provided';
                    document.getElementById('order-id').textContent = `#${String(data.id).padStart(8, '0')}`;
                    document.getElementById('order-date').textContent = new Date(data.created_at).toLocaleString();
                    
                    // Format delivery date
                    if (data.delivery_date) {
                        const delivery_date = new Date(data.delivery_date);
                        const delivery_end = new Date(data.delivery_date);
                        delivery_end.setDate(delivery_end.getDate() + 2);
                        document.getElementById('delivery-date').textContent = 
                            `${delivery_date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - 
                             ${delivery_end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
                    } else {
                        document.getElementById('delivery-date').textContent = 'Not set';
                    }
                    
                    document.getElementById('payment-method').textContent = data.payment_method.toUpperCase();
                    document.getElementById('payment-status').textContent = data.payment_status.charAt(0).toUpperCase() + data.payment_status.slice(1);
                    document.getElementById('order-status').textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                    document.getElementById('order-amount').textContent = `₹${parseFloat(data.total_amount).toFixed(2)}`;
                    
                    // Display order items
                    let itemsHtml = '<table class="table"><thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th></tr></thead><tbody>';
                    data.items.forEach(item => {
                        itemsHtml += `
                            <tr>
                                <td>${item.name}</td>
                                <td>${item.quantity}</td>
                                <td>₹${parseFloat(item.price).toFixed(2)}</td>
                                <td>₹${(parseFloat(item.price) * item.quantity).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    itemsHtml += '</tbody></table>';
                    document.getElementById('order-items').innerHTML = itemsHtml;
                    
                    new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
                })
                .catch(error => console.error('Error:', error));
        }

        function updateStatus(orderId, currentStatus) {
            document.getElementById('order_id').value = orderId;
            document.getElementById('status').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }

        // Add form submission handler
        document.querySelector('#updateStatusModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('update-order-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: formData.get('order_id'),
                    status: formData.get('status')
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating status: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating status');
            });
        });
    </script>
</body>
</html>