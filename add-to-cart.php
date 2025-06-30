<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Log the incoming request
error_log("Add to Cart Request: " . print_r($_POST, true));

if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'error' => 'login_required',
        'message' => 'Please login to add items to cart'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $product_id = $_POST['product_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 1;
        $size = $_POST['size'] ?? null;

        error_log("Processing request - Product ID: $product_id, Quantity: $quantity, Size: $size");

        // Validate product
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            error_log("Product not found - ID: $product_id");
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }

        error_log("Product found: " . print_r($product, true));

        // Check stock
        if ($product['stock_quantity'] < $quantity) {
            error_log("Insufficient stock - Available: {$product['stock_quantity']}, Requested: $quantity");
            echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
            exit;
        }

        // Validate size for clothing products
        if ($product['product_type'] === 'clothing') {
            if (empty($size)) {
                error_log("Size not selected for clothing product");
                echo json_encode(['success' => false, 'message' => 'Please select a size']);
                exit;
            }
            
            $sizes = explode(',', $product['sizes']);
            $sizes = array_map('trim', $sizes);
            if (!in_array($size, $sizes)) {
                error_log("Invalid size selected: $size");
                echo json_encode(['success' => false, 'message' => 'Invalid size selected']);
                exit;
            }
        }

        // Get user ID from session
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            error_log("User ID not found in session");
            echo json_encode(['success' => false, 'message' => 'Please login to add items to cart']);
            exit;
        }

        // Check if product already in cart
        $stmt = $db->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ? AND (size = ? OR (size IS NULL AND ? IS NULL))");
        $stmt->execute([$user_id, $product_id, $size, $size]);
        $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_item) {
            error_log("Updating existing cart item");
            // Update quantity if product exists in cart
            $new_quantity = $existing_item['quantity'] + $quantity;
            if ($new_quantity > $product['stock_quantity']) {
                error_log("Insufficient stock for update - Available: {$product['stock_quantity']}, New Total: $new_quantity");
                echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
                exit;
            }
            
            $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $result = $stmt->execute([$new_quantity, $existing_item['id']]);
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("Database operation failed: " . implode(", ", $errorInfo));
                echo json_encode([
                    'success' => false,
                    'message' => 'Error adding product to cart. Please try again.',
                    'error_info' => $errorInfo
                ]);
                exit;
            }
            error_log("Update result: success");
        } else {
            error_log("Adding new item to cart");
            // Add new item to cart
            $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantity, size) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$user_id, $product_id, $quantity, $size]);
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("Database operation failed: " . implode(", ", $errorInfo));
                echo json_encode([
                    'success' => false,
                    'message' => 'Error adding product to cart. Please try again.',
                    'error_info' => $errorInfo
                ]);
                exit;
            }
            error_log("Insert result: success");
        }

        // Get updated cart count
        $stmt = $db->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cart_count = $stmt->fetchColumn();

        error_log("Cart update successful - New count: $cart_count");

        echo json_encode([
            'success' => true,
            'message' => 'Product added to cart successfully',
            'cart_count' => $cart_count
        ]);

    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false,
            'message' => 'Error adding product to cart. Please try again.',
            'error_info' => $e->getMessage()
        ]);
    } catch (Exception $e) {
        error_log("General Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false,
            'message' => 'Error adding product to cart. Please try again.',
            'error_info' => $e->getMessage()
        ]);
    }
} else {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
