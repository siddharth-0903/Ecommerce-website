<?php
require_once 'config/database.php';

class Cart {
    private $db;
    private $user_id;

    public function __construct($user_id) {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user_id = $user_id;
    }

    public function addItem($product_id, $quantity = 1) {
        try {
            // Check if product exists and has enough stock
            $query = "SELECT stock_quantity FROM products WHERE id = :product_id AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":product_id", $product_id);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                return false;
            }

            if ($product['stock_quantity'] < $quantity) {
                return false;
            }

            // Check if item already exists in cart
            $query = "SELECT id, quantity FROM cart WHERE user_id = :user_id AND product_id = :product_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":user_id", $this->user_id);
            $stmt->bindValue(":product_id", $product_id);
            $stmt->execute();
            $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cart_item) {
                // Update quantity if item exists
                $new_quantity = $cart_item['quantity'] + $quantity;
                if ($new_quantity > $product['stock_quantity']) {
                    return false;
                }

                $query = "UPDATE cart SET quantity = :quantity WHERE id = :cart_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(":quantity", $new_quantity);
                $stmt->bindValue(":cart_id", $cart_item['id']);
                return $stmt->execute();
            } else {
                // Add new item to cart
                $query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(":user_id", $this->user_id);
                $stmt->bindValue(":product_id", $product_id);
                $stmt->bindValue(":quantity", $quantity);
                return $stmt->execute();
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    public function updateQuantity($cart_id, $quantity) {
        try {
            // Get product stock
            $query = "SELECT p.stock_quantity 
                     FROM cart c 
                     JOIN products p ON c.product_id = p.id 
                     WHERE c.id = :cart_id AND c.user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":cart_id", $cart_id);
            $stmt->bindValue(":user_id", $this->user_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || $quantity > $result['stock_quantity']) {
                return false;
            }

            $query = "UPDATE cart SET quantity = :quantity WHERE id = :cart_id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":quantity", $quantity);
            $stmt->bindValue(":cart_id", $cart_id);
            $stmt->bindValue(":user_id", $this->user_id);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function removeItem($cart_id) {
        try {
            $query = "DELETE FROM cart WHERE id = :cart_id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":cart_id", $cart_id);
            $stmt->bindValue(":user_id", $this->user_id);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function clearCart() {
        try {
            $query = "DELETE FROM cart WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":user_id", $this->user_id);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getCartItems() {
        try {
            $query = "SELECT c.*, p.name, p.price, p.sale_price, p.image, p.sku, p.stock_quantity 
                     FROM cart c 
                     JOIN products p ON c.product_id = p.id 
                     WHERE c.user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":user_id", $this->user_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getCartTotal() {
        try {
            $query = "SELECT SUM(IF(p.sale_price > 0, p.sale_price * c.quantity, p.price * c.quantity)) as total 
                     FROM cart c 
                     JOIN products p ON c.product_id = p.id 
                     WHERE c.user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":user_id", $this->user_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    }
}

// Helper functions
function addToCart($product_id) {
    if (!isLoggedIn()) {
        return false;
    }
    $cart = new Cart(getCurrentUserId());
    return $cart->addItem($product_id);
}

function getCartItemCount() {
    if (!isLoggedIn()) {
        return 0;
    }
    try {
        $database = new Database();
        $db = $database->getConnection();
        $query = "SELECT SUM(quantity) as count FROM cart WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(":user_id", getCurrentUserId());
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}
?> 