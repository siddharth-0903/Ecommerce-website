<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation | Rareblocks</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/order-confirmation.css">
</head>
<body>
<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

if (!isset($_GET['order_id'])) {
    header('Location: index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get order details
$query = "SELECT o.*, u.email, u.first_name, u.last_name, COALESCE(u.phone, '') as phone 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.id = :order_id AND o.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindValue(":order_id", $_GET['order_id']);
$stmt->bindValue(":user_id", getCurrentUserId());
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: index.php');
    exit();
}

// Get order items
$query = "SELECT oi.*, p.name, p.image 
          FROM order_items oi 
          JOIN products p ON oi.product_id = p.id 
          WHERE oi.order_id = :order_id";
$stmt = $db->prepare($query);
$stmt->bindValue(":order_id", $_GET['order_id']);
$stmt->execute();
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="order-confirmation">
        <div class="order-confirmation-header">
            <i class="fas fa-check-circle"></i>
            <h1>Order Confirmed!</h1>
            <p>Thank you for your purchase. Your order has been received.</p>
        </div>

        <div class="order-details">
            <h2>Order Details</h2>
            <div class="order-info">
                <div class="order-info-item">
                    <h3>Order Number</h3>
                    <p>#<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?></p>
                </div>
                <div class="order-info-item">
                    <h3>Date</h3>
                    <p><?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                </div>
                <div class="order-info-item">
                    <h3>Payment Method</h3>
                    <p><?php echo strtoupper($order['payment_method']); ?></p>
                </div>
                <div class="order-info-item">
                    <h3>Payment Status</h3>
                    <p><?php echo ucfirst($order['payment_status']); ?></p>
                </div>
                <div class="order-info-item">
                    <h3>Total</h3>
                    <p>₹<?php echo number_format($order['total_amount'], 2); ?></p>
                </div>
            </div>

            <div class="order-items">
                <h2>Order Items</h2>
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="order-item-image">
                        <div class="order-item-details">
                            <div class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <?php if (!empty($item['size'])): ?>
                                <div class="order-item-size">Size: <?php echo htmlspecialchars($item['size']); ?></div>
                            <?php endif; ?>
                            <div class="order-item-price">₹<?php echo number_format($item['price'], 2); ?></div>
                            <div class="order-item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="order-summary">
                <h2>Order Summary</h2>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span>Free</span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="order-actions">
            <a href="index.php" class="btn btn-outline-primary">Continue Shopping</a>
            <a href="orders.php" class="btn btn-primary">View All Orders</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>