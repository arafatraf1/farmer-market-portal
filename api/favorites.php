<?php
/**
 * API: Favorites Toggle
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if (!is_logged_in() || user_role() !== 'buyer') {
    echo json_encode(['success' => false, 'error' => 'Please log in as a buyer to favorite products.']);
    exit;
}

$user = current_user();
$productId = (int)($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product.']);
    exit;
}

try {
    $db = get_db();
    $stmtBuyer = $db->prepare("SELECT buyer_id FROM buyers WHERE user_id = ?");
    $stmtBuyer->execute([$user['user_id']]);
    $buyerId = (int)$stmtBuyer->fetchColumn();

    $stmtCheck = $db->prepare("SELECT favorite_id FROM favorites WHERE buyer_id = ? AND product_id = ?");
    $stmtCheck->execute([$buyerId, $productId]);
    $existing = $stmtCheck->fetchColumn();

    if ($existing) {
        $db->prepare("DELETE FROM favorites WHERE favorite_id = ?")->execute([$existing]);
        echo json_encode(['success' => true, 'is_favorite' => false, 'message' => 'Removed from favorites']);
    } else {
        $db->prepare("INSERT INTO favorites (buyer_id, product_id) VALUES (?, ?)")->execute([$buyerId, $productId]);
        echo json_encode(['success' => true, 'is_favorite' => true, 'message' => 'Saved to favorites!']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
