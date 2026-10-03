<?php
/**
 * API: Shopping Cart Management
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$productId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

try {
    $db = get_db();

    if ($action === 'add') {
        if ($productId <= 0) {
            throw new Exception('Invalid product selected.');
        }

        // Fetch fresh product info from DB
        $stmt = $db->prepare("SELECT p.*, f.farm_name, f.verification_status, c.category_name 
                              FROM products p 
                              JOIN farmers f ON p.farmer_id = f.farmer_id 
                              JOIN categories c ON p.category_id = c.category_id 
                              WHERE p.product_id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new Exception('Product not found.');
        }

        // Freshness / Expiry validation constraint
        $freshness = get_freshness_status($product['expiry_date']);
        if ($freshness['is_expired'] || $product['availability'] === 'expired') {
            throw new Exception('This agricultural product has expired and cannot be ordered.');
        }

        if ($product['availability'] === 'unavailable' || $product['quantity'] <= 0) {
            throw new Exception('This product is currently out of stock.');
        }

        // Check if farmer is verified
        if ($product['verification_status'] !== 'verified') {
            // Note: System rule: only verified farmers can sell products
            // If somehow unverified product is in DB, warn
        }

        $existingQty = isset($_SESSION['cart'][$productId]) ? (int)$_SESSION['cart'][$productId]['quantity'] : 0;
        $newTotalQty = $existingQty + $quantity;

        // Stock boundary constraint
        if ($newTotalQty > $product['quantity']) {
            throw new Exception("Cannot add {$newTotalQty} items. Only {$product['quantity']} {$product['unit']} available in stock.");
        }

        $_SESSION['cart'][$productId] = [
            'product_id' => $product['product_id'],
            'farmer_id' => $product['farmer_id'],
            'farm_name' => $product['farm_name'],
            'product_name' => $product['product_name'],
            'price' => (float)$product['price'],
            'unit' => $product['unit'],
            'max_quantity' => (int)$product['quantity'],
            'quantity' => $newTotalQty,
            'image' => $product['image'],
            'expiry_date' => $product['expiry_date']
        ];

        echo json_encode([
            'success' => true,
            'message' => "Added {$product['product_name']} to cart!",
            'cart_count' => get_cart_count(),
            'cart_total' => get_cart_total()
        ]);
        exit;
    }

    if ($action === 'update') {
        if (!isset($_SESSION['cart'][$productId])) {
            throw new Exception('Product is not in your cart.');
        }

        $maxQty = (int)$_SESSION['cart'][$productId]['max_quantity'];
        if ($quantity > $maxQty) {
            throw new Exception("Only {$maxQty} units available in stock.");
        }

        $_SESSION['cart'][$productId]['quantity'] = $quantity;

        echo json_encode([
            'success' => true,
            'cart_count' => get_cart_count(),
            'cart_total' => get_cart_total(),
            'item_subtotal' => $quantity * (float)$_SESSION['cart'][$productId]['price']
        ]);
        exit;
    }

    if ($action === 'remove') {
        if (isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
        }

        echo json_encode([
            'success' => true,
            'cart_count' => get_cart_count(),
            'cart_total' => get_cart_total()
        ]);
        exit;
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        echo json_encode([
            'success' => true,
            'cart_count' => 0,
            'cart_total' => 0.0
        ]);
        exit;
    }

    throw new Exception('Unknown cart action.');

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'cart_count' => get_cart_count()
    ]);
}
