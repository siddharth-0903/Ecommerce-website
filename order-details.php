<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get order details
$query = "SELECT o.*, u.email, u.first_name, u.last_name 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.id = :order_id AND o.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindValue(":order_id", $_GET['id']);
$stmt->bindValue(":user_id", getCurrentUserId());
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: orders.php');
    exit();
}

// Get order items
$query = "SELECT oi.*, p.name, p.image, p.sku 
          FROM order_items oi 
          JOIN products p ON oi.product_id = p.id 
          WHERE oi.order_id = :order_id";
$stmt = $db->prepare($query);
$stmt->bindValue(":order_id", $_GET['id']);
$stmt->execute();
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    if (in_array($order['status'], ['pending', 'processing'])) {
        $update_query = "UPDATE orders SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = :order_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindValue(":order_id", $_GET['id']);
        
        if ($update_stmt->execute()) {
            $order['status'] = 'cancelled';
            $success_message = "Order cancelled successfully.";
        } else {
            $error_message = "Failed to cancel order. Please try again.";
        }
    } else {
        $error_message = "This order cannot be cancelled.";
    }
}

// Status configuration
$status_config = [
    'pending' => [
        'class' => 'text-warning',
        'bg_class' => 'bg-warning-subtle',
        'icon' => 'fas fa-clock',
        'description' => 'Your order is being prepared for processing.'
    ],
    'processing' => [
        'class' => 'text-info',
        'bg_class' => 'bg-info-subtle',
        'icon' => 'fas fa-cog',
        'description' => 'Your order is currently being processed and will be shipped soon.'
    ],
    'shipped' => [
        'class' => 'text-primary',
        'bg_class' => 'bg-primary-subtle',
        'icon' => 'fas fa-shipping-fast',
        'description' => 'Your order has been shipped and is on its way to you.'
    ],
    'delivered' => [
        'class' => 'text-success',
        'bg_class' => 'bg-success-subtle',
        'icon' => 'fas fa-check-circle',
        'description' => 'Your order has been successfully delivered.'
    ],
    'cancelled' => [
        'class' => 'text-danger',
        'bg_class' => 'bg-danger-subtle',
        'icon' => 'fas fa-times-circle',
        'description' => 'This order has been cancelled.'
    ]
];

// Timeline status order
$timeline_statuses = ['pending', 'processing', 'shipped', 'delivered'];
$current_status_index = array_search($order['status'], $timeline_statuses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - #<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?> | Rareblocks</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .order-details-container {
            padding: 2rem 0;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .order-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .order-date {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .status-timeline {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .timeline {
            position: relative;
            padding: 1rem 0;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 25px;
            top: 0;
            height: 100%;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 4rem;
            padding-bottom: 2rem;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-icon {
            position: absolute;
            left: 0;
            top: 0;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: #f8f9fa;
            border: 3px solid #e9ecef;
            color: #6c757d;
        }
        
        .timeline-icon.active {
            background: #007bff;
            border-color: #007bff;
            color: white;
        }
        
        .timeline-icon.completed {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .timeline-content h5 {
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .timeline-content p {
            color: #666;
            margin-bottom: 0;
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 1rem;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .item-sku {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .item-price {
            font-weight: 600;
            color: #007bff;
        }
        
        .item-quantity {
            background: #f8f9fa;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #666;
            margin-left: 1rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.1rem;
            color: #007bff;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-cancel {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-cancel:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .btn-reorder {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn-reorder:hover {
            background: #218838;
            color: white;
            transform: translateY(-1px);
        }
        
        .status-badge {
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .breadcrumb-item a {
            text-decoration: none;
            color: #007bff;
        }
        
        .breadcrumb-item a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .order-details-container {
                padding: 1rem;
            }
            
            .order-header {
                padding: 1.5rem;
            }
            
            .order-number {
                font-size: 1.5rem;
            }
            
            .timeline-item {
                padding-left: 3rem;
            }
            
            .timeline-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .timeline::before {
                left: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: center;
            }
            
            .item-image {
                margin-right: 0;
                margin-bottom: 1rem;
            }
            
            .item-quantity {
                margin-left: 0;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container order-details-container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="orders.php">My Orders</a></li>
                <li class="breadcrumb-item active">Order #<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?></li>
            </ol>
        </nav>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Order Header -->
        <div class="order-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="order-number">Order #<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?></div>
                    <div class="order-date">Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?></div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="status-badge <?php echo $status_config[$order['status']]['bg_class']; ?> <?php echo $status_config[$order['status']]['class']; ?>">
                        <i class="<?php echo $status_config[$order['status']]['icon']; ?>"></i>
                        <?php echo ucfirst($order['status']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Status Timeline -->
        <?php if ($order['status'] !== 'cancelled'): ?>
        <div class="status-timeline">
            <h4 class="mb-4">Order Status</h4>
            <div class="timeline">
                <?php foreach ($timeline_statuses as $index => $status): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon <?php 
                            if ($index < $current_status_index) echo 'completed';
                            elseif ($index == $current_status_index) echo 'active';
                        ?>">
                            <i class="<?php echo $status_config[$status]['icon']; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <h5><?php echo ucfirst($status); ?></h5>
                            <p><?php echo $status_config[$status]['description']; ?></p>
                            <?php if ($index == $current_status_index): ?>
                                <small class="text-muted">Current Status</small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="status-timeline">
            <div class="text-center py-4">
                <i class="fas fa-times-circle text-danger" style="font-size: 3rem;"></i>
                <h4 class="text-danger mt-3">Order Cancelled</h4>
                <p class="text-muted"><?php echo $status_config['cancelled']['description']; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Order Items -->
            <div class="col-md-8">
                <div class="info-card">
                    <h4 class="mb-4">Order Items</h4>
                    <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo !empty($item['image']) ? 'uploads/products/' . $item['image'] : 'assets/images/placeholder.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                            <div class="item-details">
                                <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="item-sku">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                                <div class="item-price">₹<?php echo number_format($item['price'], 2); ?> each</div>
                            </div>
                            <div class="item-quantity">Qty: <?php echo $item['quantity']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Shipping Address -->
                <div class="info-card">
                    <h4 class="mb-3">Shipping Address</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong><br>
                            <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Contact Information</strong><br>
                            Email: <?php echo htmlspecialchars($order['email']); ?><br>
                            <?php if (!empty($order['phone'])): ?>
                                Phone: <?php echo htmlspecialchars($order['phone']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-md-4">
                <div class="info-card">
                    <h4 class="mb-4">Order Summary</h4>
                    
                    <?php 
                    $subtotal = 0;
                    foreach ($order_items as $item) {
                        $subtotal += $item['price'] * $item['quantity'];
                    }
                    $shipping_cost = $order['total_amount'] - $subtotal;
                    ?>
                    
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>₹<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span>₹<?php echo number_format($shipping_cost, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Expected Delivery</span>
                        <span><?php 
                            $delivery_date = new DateTime($order['delivery_date']);
                            $delivery_end = clone $delivery_date;
                            $delivery_end->modify('+2 days');
                            echo $delivery_date->format('M d') . ' - ' . $delivery_end->format('M d, Y');
                        ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Payment Method</span>
                        <span>
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
                        </span>
                    </div>
                    <div class="summary-row">
                        <span>Payment Status</span>
                        <span class="<?php echo $order['payment_status'] === 'paid' ? 'text-success' : 'text-warning'; ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span>
                    </div>
                    <div class="summary-row">
                        <span>Total</span>
                        <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                        <button type="button" class="btn-cancel" onclick="cancelOrder(<?php echo $order['id']; ?>)">
                            <i class="fas fa-times me-2"></i>Cancel Order
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($order['status'] === 'delivered'): ?>
                        <a href="reorder.php?id=<?php echo $order['id']; ?>" class="btn-reorder">
                            <i class="fas fa-shopping-cart me-2"></i>Reorder
                        </a>
                    <?php endif; ?>
                    
                    <a href="orders.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div class="modal fade" id="cancelOrderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this order?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. Once cancelled, you'll need to place a new order.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="cancel_order" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i>Cancel Order
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cancel order functionality
        function cancelOrder(orderId) {
            const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
            modal.show();
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>