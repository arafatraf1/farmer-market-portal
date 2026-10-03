<?php
/**
 * API: Farmer & Product Review Submission
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if (!is_logged_in() || user_role() !== 'buyer') {
    echo json_encode(['success' => false, 'error' => 'Only registered buyers can submit reviews.']);
    exit;
}

$user = current_user();
$orderId = (int)($_POST['order_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 5);
$reviewText = trim($_POST['review_text'] ?? '');
$productId = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5 stars.']);
    exit;
}

if (strlen($reviewText) < 5) {
    echo json_encode(['success' => false, 'error' => 'Please provide a constructive review of at least 5 characters.']);
    exit;
}

try {
    $db = get_db();

    // 1. Get buyer ID
    $stmtBuyer = $db->prepare("SELECT buyer_id FROM buyers WHERE user_id = ?");
    $stmtBuyer->execute([$user['user_id']]);
    $buyerId = (int)$stmtBuyer->fetchColumn();

    if (!$buyerId) {
        throw new Exception('Buyer profile not initialized.');
    }

    // 2. Verify order belongs to this buyer, is completed, and get farmer_id
    $stmtOrder = $db->prepare("SELECT order_id, farmer_id, order_status FROM orders WHERE order_id = ? AND buyer_id = ?");
    $stmtOrder->execute([$orderId, $buyerId]);
    $order = $stmtOrder->fetch();

    if (!$order) {
        throw new Exception('Order not found or does not belong to your account.');
    }

    // Rule: Cannot review incomplete/cancelled order
    if ($order['order_status'] !== 'completed') {
        throw new Exception('You can only review farmers for completed deliveries.');
    }

    // Rule: Check if order already reviewed
    $stmtCheck = $db->prepare("SELECT review_id FROM reviews WHERE buyer_id = ? AND order_id = ?");
    $stmtCheck->execute([$buyerId, $orderId]);
    if ($stmtCheck->fetch()) {
        throw new Exception('You have already submitted a review for this completed order.');
    }

    $farmerId = (int)$order['farmer_id'];

    // 3. Insert review
    $stmtInsert = $db->prepare("INSERT INTO reviews (buyer_id, farmer_id, product_id, order_id, rating, review_text, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')");
    $stmtInsert->execute([
        $buyerId,
        $farmerId,
        $productId,
        $orderId,
        $rating,
        $reviewText
    ]);

    // 4. Recalculate farmer average rating and total reviews
    $stmtStats = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM reviews WHERE farmer_id = ? AND status = 'active'");
    $stmtStats->execute([$farmerId]);
    $stats = $stmtStats->fetch();

    $newAvg = round((float)($stats['avg_rating'] ?? 0.0), 2);
    $newCount = (int)($stats['cnt'] ?? 0);

    $stmtUpdateFarmer = $db->prepare("UPDATE farmers SET farmer_rating = ?, total_reviews = ? WHERE farmer_id = ?");
    $stmtUpdateFarmer->execute([$newAvg, $newCount, $farmerId]);

    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your feedback has been verified and published.',
        'avg_rating' => $newAvg,
        'total_reviews' => $newCount
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
