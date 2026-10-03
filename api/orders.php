<?php
/**
 * API: Order Status & Delivery Milestones Management
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$user = current_user();
$orderId = (int)($_POST['order_id'] ?? 0);
$newStatus = trim($_POST['status'] ?? '');
$reason = trim($_POST['cancellation_reason'] ?? '');
$notes = trim($_POST['tracking_notes'] ?? '');

$allowedStatuses = [
    'pending', 'accepted', 'preparing', 'ready_for_delivery', 
    'out_for_delivery', 'delivered', 'completed', 'cancelled', 'rejected'
];

if (!in_array($newStatus, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid order status specified.']);
    exit;
}

try {
    $db = get_db();

    // Fetch order details with buyer and farmer info
    $stmt = $db->prepare("SELECT o.*, f.user_id as farmer_user_id, b.user_id as buyer_user_id 
                          FROM orders o 
                          JOIN farmers f ON o.farmer_id = f.farmer_id 
                          JOIN buyers b ON o.buyer_id = b.buyer_id 
                          WHERE o.order_id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Order not found.');
    }

    $isFarmer = ($user['role'] === 'farmer' && $order['farmer_user_id'] == $user['user_id']);
    $isBuyer = ($user['role'] === 'buyer' && $order['buyer_user_id'] == $user['user_id']);
    $isAdmin = ($user['role'] === 'admin');

    if (!$isFarmer && !$isBuyer && !$isAdmin) {
        throw new Exception('Unauthorized to modify this order.');
    }

    // Role-specific transition rules
    if ($isBuyer) {
        // Buyer can cancel only if pending
        if ($newStatus === 'cancelled') {
            if ($order['order_status'] !== 'pending') {
                throw new Exception('You can only cancel an order that is still pending confirmation.');
            }
            // Increment buyer's cancelled_orders
            $db->prepare("UPDATE buyers SET cancelled_orders = cancelled_orders + 1 WHERE user_id = ?")->execute([$user['user_id']]);
        } elseif ($newStatus === 'completed') {
            // Buyer confirms delivery receipt
            if (!in_array($order['order_status'], ['delivered', 'out_for_delivery'])) {
                throw new Exception('Order must be delivered before confirming completion.');
            }
            // Increment buyer's completed_orders
            $db->prepare("UPDATE buyers SET completed_orders = completed_orders + 1 WHERE user_id = ?")->execute([$user['user_id']]);
        } else {
            throw new Exception('Buyers can only cancel pending requests or confirm completed delivery.');
        }
    }

    if ($isFarmer) {
        // Farmer cannot arbitrarily mark completed without delivery
        if ($newStatus === 'completed' && $order['order_status'] !== 'delivered') {
            throw new Exception('Buyer must confirm delivery receipt to complete order.');
        }
    }

    // If order is cancelled or rejected, restore product quantities back to stock!
    if (in_array($newStatus, ['cancelled', 'rejected'], true) && !in_array($order['order_status'], ['cancelled', 'rejected'], true)) {
        $stmtItems = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$orderId]);
        $items = $stmtItems->fetchAll();

        $stmtRestore = $db->prepare("UPDATE products SET quantity = quantity + ? WHERE product_id = ?");
        foreach ($items as $item) {
            $stmtRestore->execute([$item['quantity'], $item['product_id']]);
        }
    }

    // Update order status
    $stmtUpdate = $db->prepare("UPDATE orders SET order_status = :status, cancellation_reason = :reason, updated_at = NOW() WHERE order_id = :id");
    $stmtUpdate->execute([
        ':status' => $newStatus,
        ':reason' => $reason ?: $order['cancellation_reason'],
        ':id' => $orderId
    ]);

    // Upsert delivery tracking record
    $actualDeliverySql = ($newStatus === 'delivered' || $newStatus === 'completed') ? 'NOW()' : 'actual_delivery';
    $stmtDel = $db->prepare("INSERT INTO delivery (order_id, status, tracking_notes, actual_delivery, updated_at)
        VALUES (:id, :status, :notes, " . ($newStatus === 'delivered' ? 'NOW()' : 'NULL') . ", NOW())
        ON DUPLICATE KEY UPDATE status = :status_update, tracking_notes = COALESCE(:notes_update, tracking_notes), updated_at = NOW()");
    $stmtDel->execute([
        ':id' => $orderId,
        ':status' => $newStatus,
        ':notes' => $notes ?: "Status updated to " . ucfirst(str_replace('_', ' ', $newStatus)),
        ':status_update' => $newStatus,
        ':notes_update' => $notes ?: null
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Order status updated to ' . ucfirst(str_replace('_', ' ', $newStatus)),
        'new_status' => $newStatus
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
