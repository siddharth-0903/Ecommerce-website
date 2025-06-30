<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/cart.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$cart = new Cart(getCurrentUserId());
$cart_items = $cart->getCartItems();
$cart_total = $cart->getCartTotal();

$error = '';
$success = '';

// Get user information
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindValue(":user_id", getCurrentUserId());
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($cart_items)) {
        $error = "Your cart is empty";
    } else {
        try {
            $db->beginTransaction();

            // Calculate delivery date (5-7 days from now)
            $delivery_date = date('Y-m-d', strtotime('+5 days'));

            // Get payment method
            $payment_method = $_POST['payment_method'] ?? 'cod';
            $payment_status = $payment_method === 'cod' ? 'pending' : 'paid';
            
            // If it's a Razorpay payment, verify the payment
            if ($payment_method === 'razorpay') {
                $razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
                $razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
                $razorpay_signature = $_POST['razorpay_signature'] ?? '';
                
                if (empty($razorpay_payment_id) || empty($razorpay_order_id) || empty($razorpay_signature)) {
                    throw new Exception("Invalid payment details");
                }
                
                // Verify Razorpay payment signature
                $generated_signature = hash_hmac('sha256', $razorpay_order_id . "|" . $razorpay_payment_id, "WhYHndO3nTzLEU6UdHJc7X9M");
                
                if ($generated_signature !== $razorpay_signature) {
                    error_log("Payment verification failed. Expected: " . $generated_signature . ", Got: " . $razorpay_signature);
                    throw new Exception("Payment verification failed. Please contact support if this persists.");
                }
                
                // Set payment status to paid for successful Razorpay payments
                $payment_status = 'paid';
            }

            // Create order
            $query = "INSERT INTO orders (user_id, total_amount, shipping_address, first_name, last_name, email, phone, payment_method, payment_status, delivery_date) 
                     VALUES (:user_id, :total_amount, :shipping_address, :first_name, :last_name, :email, :phone, :payment_method, :payment_status, :delivery_date)";
            $stmt = $db->prepare($query);
            $stmt->bindValue(":user_id", getCurrentUserId());
            $stmt->bindValue(":total_amount", $cart_total);
            $stmt->bindValue(":shipping_address", $_POST['shipping_address']);
            $stmt->bindValue(":first_name", $_POST['first_name']);
            $stmt->bindValue(":last_name", $_POST['last_name']);
            $stmt->bindValue(":email", $_POST['email']);
            $stmt->bindValue(":phone", $_POST['phone']);
            $stmt->bindValue(":payment_method", $payment_method);
            $stmt->bindValue(":payment_status", $payment_status);
            $stmt->bindValue(":delivery_date", $delivery_date);
            $stmt->execute();
            $order_id = $db->lastInsertId();

            // Add order items
            $query = "INSERT INTO order_items (order_id, product_id, quantity, price, size) 
                     VALUES (:order_id, :product_id, :quantity, :price, :size)";
            $stmt = $db->prepare($query);

            foreach ($cart_items as $item) {
                $stmt->bindValue(":order_id", $order_id);
                $stmt->bindValue(":product_id", $item['product_id']);
                $stmt->bindValue(":quantity", $item['quantity']);
                $stmt->bindValue(":price", $item['price']);
                $stmt->bindValue(":size", $item['size'] ?? null);
                $stmt->execute();


                // Update product stock
                $query = "UPDATE products 
                         SET stock_quantity = stock_quantity - :quantity 
                         WHERE id = :product_id";
                $update_stmt = $db->prepare($query);
                $update_stmt->bindValue(":quantity", $item['quantity']);
                $update_stmt->bindValue(":product_id", $item['product_id']);
                $update_stmt->execute();
            }

            // Clear cart
            $cart->clearCart();

            $db->commit();
            header("Location: order-confirmation.php?order_id=" . $order_id);
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $error = "An error occurred while processing your order: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Rareblocks</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #fafbfc; }
        .confirmation-container { max-width: 700px; margin: 40px auto; }
        .order-steps { font-size: 0.97rem; color: #888; margin-bottom: 1.5rem; }
        .order-steps .step { display: inline-block; margin-right: 0.5rem; }
        .order-steps .step.active { font-weight: 700; color: #19191b; border: 1px solid #bbb; border-radius: 6px; padding: 2px 8px; background: #fff; }
        .checkout-container { max-width: 1200px; margin: 40px auto; }
        .checkout-form-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); padding: 32px 24px; }
        .checkout-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 2rem; }
        .form-label { font-weight: 600; }
        .form-section-title { font-size: 1.1rem; font-weight: 700; margin-top: 2rem; margin-bottom: 1rem; }
        .payment-method-card { border: 2px solid #eee; border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; transition: border 0.2s; }
        .payment-method-card.selected { border: 2px solid #19191b; background: #fafbfc; }
        .order-summary-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); padding: 2rem 1.5rem; min-width: 320px; }
        .order-summary-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 1.5rem; }
        .order-summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; font-size: 1.05rem; }
        .order-summary-row.total { font-size: 1.2rem; font-weight: 700; margin-top: 1.5rem; }
        .order-summary-btn { width: 100%; background: #19191b; color: #fff; font-weight: 700; border-radius: 6px; padding: 0.75rem; border: none; margin-top: 1rem; transition: background 0.2s; }
        .order-summary-btn:hover { background: #ffe942; color: #19191b; }
        .order-summary-item-img { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; margin-right: 12px; }
        .order-summary-item-title { font-weight: 600; font-size: 1rem; }
        .order-summary-item-variant { color: #888; font-size: 0.95rem; }
        @media (max-width: 991.98px) {
            .checkout-container { padding: 0 10px; }
            .order-summary-card { min-width: unset; margin-top: 2rem; }
        }
        .checkout-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .order-summary {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            position: sticky;
            top: 2rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <h1 class="mb-4">Checkout</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

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
                    <form method="POST" id="checkout-form">
                        <div class="checkout-section">
                            <h4 class="mb-4">Shipping Information</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Shipping Address</label>
                                <textarea class="form-control" name="shipping_address" rows="3" required><?php echo htmlspecialchars($user['shipping_address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="checkout-section">
                            <h4 class="mb-4">Payment Method</h4>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="payment_method" id="cod" value="cod" checked>
                                <label class="form-check-label" for="cod">
                                    Cash on Delivery (COD)
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="payment_method" id="razorpay" value="razorpay">
                                <label class="form-check-label" for="razorpay">
                                    Pay with Razorpay
                                </label>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-lg-4">
                    <div class="order-summary">
                        <h4 class="mb-4">Order Summary</h4>
                        <?php foreach ($cart_items as $item): ?>
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                    <?php if (!empty($item['size'])): ?>
                                        <small class="text-muted">Size: <?php echo htmlspecialchars($item['size']); ?></small><br>
                                    <?php endif; ?>
                                    <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                </div>
                                <span>₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal</span>
                            <span>₹<?php echo number_format($cart_total, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Shipping</span>
                            <span>Free</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Expected Delivery</span>
                            <span><?php echo date('M d', strtotime('+5 days')); ?> - <?php echo date('M d, Y', strtotime('+7 days')); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <strong>Total</strong>
                            <strong>₹<?php echo number_format($cart_total, 2); ?></strong>
                        </div>
                        <button type="submit" form="checkout-form" class="btn btn-primary w-100" id="place-order-btn">Place Order</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Add Razorpay Script -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('checkout-form');
    const placeOrderBtn = document.getElementById('place-order-btn');
    const codRadio = document.getElementById('cod');
    const razorpayRadio = document.getElementById('razorpay');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Disable the button to prevent multiple clicks
        placeOrderBtn.disabled = true;
        placeOrderBtn.textContent = 'Processing...';
        
        if (razorpayRadio.checked) {
            // Initialize Razorpay
            const options = {
                key: "rzp_test_RATdjTid0lID7f",
                amount: <?php echo round($cart_total * 100); ?>, // Amount in paise
                currency: "INR",
                name: "Rareblocks",
                description: "Order Payment",
                image: "https://cdn.rareblocks.xyz/collection/clarity-ecommerce/images/logo.svg",
                handler: function (response) {
                    console.log('Payment successful:', response);
                    
                    // Add payment details to form
                    const paymentIdInput = document.createElement('input');
                    paymentIdInput.type = 'hidden';
                    paymentIdInput.name = 'razorpay_payment_id';
                    paymentIdInput.value = response.razorpay_payment_id;
                    form.appendChild(paymentIdInput);

                    const orderIdInput = document.createElement('input');
                    orderIdInput.type = 'hidden';
                    orderIdInput.name = 'razorpay_order_id';
                    orderIdInput.value = response.razorpay_order_id;
                    form.appendChild(orderIdInput);

                    const signatureInput = document.createElement('input');
                    signatureInput.type = 'hidden';
                    signatureInput.name = 'razorpay_signature';
                    signatureInput.value = response.razorpay_signature;
                    form.appendChild(signatureInput);

                    // Submit the form
                    form.submit();
                },
                prefill: {
                    name: "<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>",
                    email: "<?php echo htmlspecialchars($user['email']); ?>",
                    contact: "<?php echo htmlspecialchars($user['phone']); ?>"
                },
                theme: {
                    color: "#19191b"
                },
                modal: {
                    ondismiss: function() {
                        console.log('Payment cancelled');
                        // Re-enable the button
                        placeOrderBtn.disabled = false;
                        placeOrderBtn.textContent = 'Place Order';
                    }
                }
            };

            // Create order on server first
            console.log('Creating Razorpay order...');
            
            fetch('create-razorpay-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    amount: <?php echo round($cart_total * 100); ?>,
                    currency: 'INR'
                })
            })
            .then(response => {
                console.log('Server response status:', response.status);
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                console.log('Parsed response:', data);
                
                if (data.error || !data.success) {
                    console.error('Server Error:', data.error || 'Unknown error');
                    throw new Error(data.error || 'Failed to create order');
                }
                
                if (!data.order || !data.order.id) {
                    console.error('Invalid Response - Missing order ID:', data);
                    throw new Error('Invalid response from server - missing order ID');
                }
                
                console.log('Order created successfully, opening Razorpay...');
                options.order_id = data.order.id;
                const rzp = new Razorpay(options);
                
                rzp.on('payment.failed', function (response) {
                    console.error('Payment failed:', response.error);
                    alert('Payment failed: ' + response.error.description);
                    // Re-enable the button
                    placeOrderBtn.disabled = false;
                    placeOrderBtn.textContent = 'Place Order';
                });
                
                rzp.open();
            })
            .catch(error => {
                console.error('Error details:', error);
                alert('Failed to initialize payment: ' + error.message);
                // Re-enable the button
                placeOrderBtn.disabled = false;
                placeOrderBtn.textContent = 'Place Order';
            });
        } else {
            // For COD, submit the form directly
            form.submit();
        }
    });
});</script>
</body>
</html>
