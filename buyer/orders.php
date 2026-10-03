<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('buyer');
$user = current_user();
$db = get_db();

// Fetch buyer record
$stmtBuyer = $db->prepare("SELECT buyer_id FROM buyers WHERE user_id = ?");
$stmtBuyer->execute([$user['user_id']]);
$buyerId = (int)$stmtBuyer->fetchColumn();

// Status filter
$filter = trim($_GET['status'] ?? 'all');

$sql = "
    SELECT o.*, f.farm_name, f.farmer_id, f.verification_status,
           r.review_id,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items_count,
           (SELECT GROUP_CONCAT(CONCAT(p.product_name, ' (', oi.quantity, ' ', p.unit, ')') SEPARATOR ', ')
            FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as items_summary
    FROM orders o
    JOIN farmers f ON o.farmer_id = f.farmer_id
    LEFT JOIN reviews r ON o.order_id = r.order_id AND r.buyer_id = o.buyer_id
    WHERE o.buyer_id = :buyer_id
";

$params = [':buyer_id' => $buyerId];

if ($filter === 'pending') {
    $sql .= " AND o.order_status = 'pending'";
} elseif ($filter === 'active') {
    $sql .= " AND o.order_status IN ('accepted', 'preparing', 'ready_for_delivery', 'out_for_delivery', 'delivered')";
} elseif ($filter === 'completed') {
    $sql .= " AND o.order_status = 'completed'";
} elseif ($filter === 'cancelled') {
    $sql .= " AND o.order_status IN ('cancelled', 'rejected')";
}

$sql .= " ORDER BY o.order_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'My Order History & Deliveries';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/buyer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>My Orders & Deliveries</h1>
                <p>View purchase history, milestone tracking, and receipt confirmations</p>
            </div>
            <a href="<?= url('browse.php') ?>" class="btn btn-primary btn-sm">Order Fresh Produce</a>
        </div>

        <!-- Filter Tabs -->
        <div style="display:flex; gap:8px; margin-bottom:24px; flex-wrap:wrap;">
            <a href="<?= url('buyer/orders.php?status=all') ?>" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All Orders</a>
            <a href="<?= url('buyer/orders.php?status=pending') ?>" class="btn <?= $filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Pending Confirmation</a>
            <a href="<?= url('buyer/orders.php?status=active') ?>" class="btn <?= $filter === 'active' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">In Transit / Active</a>
            <a href="<?= url('buyer/orders.php?status=completed') ?>" class="btn <?= $filter === 'completed' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Completed Deliveries</a>
            <a href="<?= url('buyer/orders.php?status=cancelled') ?>" class="btn <?= $filter === 'cancelled' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Cancelled / Rejected</a>
        </div>

        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">Orders List (<?= count($orders) ?>)</span>
            </div>

            <?php if (empty($orders)): ?>
                <div style="padding:50px; text-align:center; color:var(--gray-500);">
                    No orders found matching this filter criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Order Code</th>
                                <th>Farm</th>
                                <th>Items</th>
                                <th>Order Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $ord): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('buyer/order-tracking.php?id=' . $ord['order_id']) ?>" style="font-weight:700; color:var(--primary-800);">
                                            <?= e($ord['order_code']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?= e($ord['farm_name']) ?></div>
                                        <?= get_verification_badge($ord['verification_status']) ?>
                                    </td>
                                    <td style="max-width:240px; font-size:13px; color:var(--gray-600);">
                                        <?= e($ord['items_summary']) ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($ord['order_date'])) ?></td>
                                    <td><strong><?= format_price($ord['total_amount']) ?></strong></td>
                                    <td><?= get_order_badge($ord['order_status']) ?></td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <a href="<?= url('buyer/order-tracking.php?id=' . $ord['order_id']) ?>" class="btn btn-secondary btn-sm" title="View timeline tracking">
                                                Track
                                            </a>

                                            <?php if ($ord['order_status'] === 'delivered'): ?>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="confirmOrderReceived(<?= $ord['order_id'] ?>);">
                                                    Confirm Received
                                                </button>
                                            <?php elseif ($ord['order_status'] === 'completed' && empty($ord['review_id'])): ?>
                                                <a href="<?= url('buyer/order-tracking.php?id=' . $ord['order_id'] . '&review=1') ?>" class="btn btn-accent btn-sm">
                                                    Rate Farmer
                                                </a>
                                            <?php elseif ($ord['order_status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-secondary btn-sm" style="color:var(--danger-500);" onclick="if(confirm('Cancel this pending purchase request?')) updateOrderStatus(<?= $ord['order_id'] ?>, 'cancelled');">
                                                    Cancel
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
