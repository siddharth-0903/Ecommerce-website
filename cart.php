<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/cart.php';

requireLogin();

$cart = new Cart(getCurrentUserId());
$cart_items = $cart->getCartItems();
$cart_total = $cart->getCartTotal();

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update':
                if (isset($_POST['cart_id']) && isset($_POST['quantity'])) {
                    $cart->updateQuantity($_POST['cart_id'], $_POST['quantity']);
                }
                break;
            case 'remove':
                if (isset($_POST['cart_id'])) {
                    $cart->removeItem($_POST['cart_id']);
                }
                break;
            case 'clear':
                $cart->clearCart();
                break;
        }
        // Refresh page to show updated cart
        header('Location: cart.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart | Rareblocks</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 1rem 0;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .cart-item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        .quantity-input {
            width: 80px;
        }
        .cart-summary {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <h1 class="mb-4">Shopping Cart</h1>

        <?php if (empty($cart_items)): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-3x mb-3 text-muted"></i>
                <h3>Your cart is empty</h3>
                <p class="text-muted">Add some products to your cart to continue shopping</p>
                <a href="index.php" class="btn btn-primary mt-3">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-lg-8">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image">
                                </div>
                                <div class="col-md-4">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h5>
                                    <?php if (!empty($item['size'])): ?>
                                        <p class="text-muted mb-0">Size: <?php echo htmlspecialchars($item['size']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-muted mb-0">SKU: <?php echo htmlspecialchars($item['sku']); ?></p>
                                </div>
                                <div class="col-md-2">
                                    <p class="mb-0">₹<?php echo number_format($item['price'], 2); ?></p>
                                </div>
                                <div class="col-md-2">
                                    <form method="POST" class="d-flex align-items-center">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                               min="1" max="<?php echo $item['stock_quantity']; ?>" 
                                               class="form-control quantity-input" onchange="this.form.submit()">
                                    </form>
                                </div>
                                <div class="col-md-2 text-end">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="mt-4">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to clear your cart?')">
                                Clear Cart
                            </button>
                        </form>
                        <a href="index.php" class="btn btn-outline-primary ms-2">Continue Shopping</a>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="cart-summary">
                        <h4 class="mb-4">Order Summary</h4>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal</span>
                            <span>₹<?php echo number_format($cart_total, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Shipping</span>
                            <span>Free</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <strong>Total</strong>
                            <strong>₹<?php echo number_format($cart_total, 2); ?></strong>
                        </div>
                        <a href="checkout.php" class="btn btn-primary w-100">Proceed to Checkout</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
