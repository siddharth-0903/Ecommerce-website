<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = $_POST['order_id'];
    
    // Verify order belongs to current user and can be cancelled
    $query = "SELECT status FROM orders WHERE id = :order_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(":order_id", $order_id);
    $stmt->bindValue(":user_id", getCurrentUserId());
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order && in_array($order['status'], ['pending', 'processing'])) {
        // Update order status to cancelled
        $update_query = "UPDATE orders SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = :order_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindValue(":order_id", $order_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Order cancelled successfully.";
        } else {
            $error_message = "Failed to cancel order. Please try again.";
        }
    } else {
        $error_message = "This order cannot be cancelled.";
    }
}

// Get user's orders
$query = "SELECT o.*, COUNT(oi.id) as item_count 
          FROM orders o 
          LEFT JOIN order_items oi ON o.id = oi.order_id 
          WHERE o.user_id = :user_id 
          GROUP BY o.id 
          ORDER BY o.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindValue(":user_id", getCurrentUserId());
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Status badges configuration
$status_badges = [
    'pending' => ['class' => 'badge-warning', 'icon' => 'fas fa-clock'],
    'processing' => ['class' => 'badge-info', 'icon' => 'fas fa-cog'],
    'shipped' => ['class' => 'badge-primary', 'icon' => 'fas fa-shipping-fast'],
    'delivered' => ['class' => 'badge-success', 'icon' => 'fas fa-check-circle'],
    'cancelled' => ['class' => 'badge-danger', 'icon' => 'fas fa-times-circle']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Rareblocks</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .orders-container {
            padding: 2rem 0;
        }
        
        .order-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: transform 0.2s ease;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .order-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .order-body {
            padding: 1.5rem;
        }
        
        .order-number {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        
        .order-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .order-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: #007bff;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-primary {
            background: #cce5ff;
            color: #004085;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-cancel {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: background 0.2s ease;
        }
        
        .btn-cancel:hover {
            background: #c82333;
            color: white;
        }
        
        .btn-view {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            text-decoration: none;
            transition: background 0.2s ease;
        }
        
        .btn-view:hover {
            background: #0056b3;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #ddd;
        }
        
        .order-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .summary-item strong {
            color: #333;
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .filter-tabs {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            background: transparent;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            color: #666;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .filter-tab.active {
            background: #007bff;
            color: white;
        }
        
        .filter-tab:hover:not(.active) {
            background: #e9ecef;
        }
        
        @media (max-width: 768px) {
            .order-summary {
                grid-template-columns: 1fr;
            }
            
            .order-actions {
                flex-direction: column;
            }
            
            .filter-tabs {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container orders-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">My Orders</h1>
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
            </a>
        </div>

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

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-tab active" data-status="all">All Orders</button>
            <button class="filter-tab" data-status="pending">Pending</button>
            <button class="filter-tab" data-status="processing">Processing</button>
            <button class="filter-tab" data-status="shipped">Shipped</button>
            <button class="filter-tab" data-status="delivered">Delivered</button>
            <button class="filter-tab" data-status="cancelled">Cancelled</button>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h3>No orders found</h3>
                <p>You haven't placed any orders yet. Start shopping to see your orders here.</p>
                <a href="index.php" class="btn btn-primary mt-3">
                    <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card" data-status="<?php echo $order['status']; ?>">
                    <div class="order-header">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <div class="order-number">
                                    Order #<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?>
                                </div>
                                <div class="order-date">
                                    <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small">Items</div>
                                <div class="fw-semibold"><?php echo $order['item_count']; ?> item(s)</div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small">Total</div>
                                <div class="order-total">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small">Status</div>
                                <span class="status-badge <?php echo $status_badges[$order['status']]['class']; ?>">
                                    <i class="<?php echo $status_badges[$order['status']]['icon']; ?> me-1"></i>
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                            <div class="col-md-3">
                                <div class="order-actions">
                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn-view">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </a>
                                    <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                                        <button type="button" class="btn-cancel" 
                                                onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-times me-1"></i>Cancel
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-body">
                        <div class="order-summary">
                            <div class="summary-item">
                                <span class="text-muted">Payment Method:</span>
                                <span><?php echo ucfirst($order['payment_method']); ?></span>
                            </div>
                            <div class="summary-item">
                                <span class="text-muted">Payment Status:</span>
                                <span class="<?php echo $order['payment_status'] === 'paid' ? 'text-success' : 'text-warning'; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                            <div class="summary-item">
                                <span class="text-muted">Delivery Address:</span>
                                <span class="text-truncate" style="max-width: 200px;" 
                                      title="<?php echo htmlspecialchars($order['shipping_address']); ?>">
                                    <?php echo htmlspecialchars($order['shipping_address']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
                        <input type="hidden" name="order_id" id="cancelOrderId" value="">
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
            document.getElementById('cancelOrderId').value = orderId;
            const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
            modal.show();
        }

        // Filter functionality
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                // Add active class to clicked tab
                this.classList.add('active');
                
                const status = this.dataset.status;
                const orderCards = document.querySelectorAll('.order-card');
                
                orderCards.forEach(card => {
                    if (status === 'all' || card.dataset.status === status) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

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