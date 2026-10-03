<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('farmer');
$user = current_user();
$db = get_db();

// Fetch farmer ID
$stmtFarmer = $db->prepare("SELECT farmer_id, farm_name FROM farmers WHERE user_id = ?");
$stmtFarmer->execute([$user['user_id']]);
$farmer = $stmtFarmer->fetch();
$farmerId = (int)$farmer['farmer_id'];

$filter = trim($_GET['status'] ?? 'all');
$highlight = (int)($_GET['highlight'] ?? 0);

$sql = "
    SELECT o.*, u.name as buyer_name, u.phone as buyer_phone, u.profile_image as buyer_avatar,
           b.completed_orders as buyer_completed, b.cancelled_orders as buyer_cancelled,
           d.tracking_notes, d.estimated_delivery,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items_count,
           (SELECT GROUP_CONCAT(CONCAT(p.product_name, ' (', oi.quantity, ' ', p.unit, ')') SEPARATOR ', ')
            FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as items_summary
    FROM orders o
    JOIN buyers b ON o.buyer_id = b.buyer_id
    JOIN users u ON b.user_id = u.user_id
    LEFT JOIN delivery d ON o.order_id = d.order_id
    WHERE o.farmer_id = :farmer_id
";
$params = [':farmer_id' => $farmerId];

if ($filter === 'pending') {
    $sql .= " AND o.order_status = 'pending'";
} elseif ($filter === 'active') {
    $sql .= " AND o.order_status IN ('accepted', 'preparing', 'ready_for_delivery', 'out_for_delivery')";
} elseif ($filter === 'delivered') {
    $sql .= " AND o.order_status IN ('delivered', 'completed')";
} elseif ($filter === 'cancelled') {
    $sql .= " AND o.order_status IN ('cancelled', 'rejected')";
}

$sql .= " ORDER BY o.order_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Customer Orders & Purchase Requests';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/farmer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Customer Purchase Requests & Orders</h1>
                <p>Verify buyer trust reliability, accept requests, and update delivery milestones</p>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div style="display:flex; gap:8px; margin-bottom:24px; flex-wrap:wrap;">
            <a href="<?= url('farmer/orders.php?status=all') ?>" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All Orders</a>
            <a href="<?= url('farmer/orders.php?status=pending') ?>" class="btn <?= $filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Pending Confirmation</a>
            <a href="<?= url('farmer/orders.php?status=active') ?>" class="btn <?= $filter === 'active' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">In Preparation / In Transit</a>
            <a href="<?= url('farmer/orders.php?status=delivered') ?>" class="btn <?= $filter === 'delivered' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Delivered / Completed</a>
            <a href="<?= url('farmer/orders.php?status=cancelled') ?>" class="btn <?= $filter === 'cancelled' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Cancelled / Rejected</a>
        </div>

        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">Orders List (<?= count($orders) ?>)</span>
            </div>

            <?php if (empty($orders)): ?>
                <div style="padding:50px; text-align:center; color:var(--gray-500);">
                    No orders found for this filter.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Buyer & Reliability Score</th>
                                <th>Produce Items</th>
                                <th>Target Delivery</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Status Pipeline Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $ord): ?>
                                <?php 
                                $bTrust = get_buyer_trust_info((int)$ord['buyer_completed'], (int)$ord['buyer_cancelled']);
                                $isHighlight = ($ord['order_id'] === $highlight);
                                ?>
                                <tr style="<?= $isHighlight ? 'background:#ecfdf5; border-left:4px solid var(--primary-600);' : '' ?>">
                                    <td>
                                        <strong style="color:var(--gray-900);"><?= e($ord['order_code']) ?></strong>
                                        <div style="font-size:11px; color:var(--gray-400);"><?= date('M d, h:i A', strtotime($ord['order_date'])) ?></div>
                                    </td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <img src="<?= e($ord['buyer_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80') ?>" alt="Buyer" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                                            <div>
                                                <div style="font-weight:700; color:var(--gray-900); font-size:13.5px;"><?= e($ord['buyer_name']) ?></div>
                                                <div style="font-size:11px; color:var(--gray-500);">📞 <?= e($ord['buyer_phone']) ?></div>
                                            </div>
                                        </div>
                                        <div style="margin-top:4px;">
                                            <span class="status-badge <?= e($bTrust['badge_class']) ?>" style="font-size:10.5px; padding:2px 6px;" title="<?= e($bTrust['description']) ?>">
                                                <?= e($bTrust['rate_text']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td style="max-width:240px; font-size:12.5px; color:var(--gray-700);">
                                        <strong><?= e($ord['items_summary']) ?></strong>
                                        <div style="font-size:11.5px; color:var(--gray-500); margin-top:2px;">
                                            📍 <?= e(mb_strimwidth($ord['delivery_address'], 0, 50, '...')) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?= e($ord['requested_delivery_date'] ? date('M d, Y', strtotime($ord['requested_delivery_date'])) : 'Standard') ?></div>
                                        <div style="font-size:11.5px; color:var(--gray-400);"><?= e($ord['estimated_delivery_time']) ?></div>
                                    </td>
                                    <td><strong><?= format_price($ord['total_amount']) ?></strong></td>
                                    <td><?= get_order_badge($ord['order_status']) ?></td>
                                    <td>
                                        <div style="display:flex; flex-direction:column; gap:6px;">
                                            <?php if ($ord['order_status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="updateOrderStatus(<?= $ord['order_id'] ?>, 'accepted');">
                                                    ✓ Accept Request
                                                </button>
                                                <button type="button" class="btn btn-secondary btn-sm" style="color:var(--danger-500);" onclick="const r=prompt('State reason for rejection:'); if(r) updateOrderStatus(<?= $ord['order_id'] ?>, 'rejected', r);">
                                                    &times; Reject Request
                                                </button>
                                            <?php elseif ($ord['order_status'] === 'accepted'): ?>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="updateOrderStatus(<?= $ord['order_id'] ?>, 'preparing');">
                                                    &rarr; Start Preparing Produce
                                                </button>
                                            <?php elseif ($ord['order_status'] === 'preparing'): ?>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="updateOrderStatus(<?= $ord['order_id'] ?>, 'ready_for_delivery');">
                                                    &rarr; Ready for Dispatch
                                                </button>
                                            <?php elseif ($ord['order_status'] === 'ready_for_delivery'): ?>
                                                <button type="button" class="btn btn-accent btn-sm" onclick="updateOrderStatus(<?= $ord['order_id'] ?>, 'out_for_delivery');">
                                                    🚚 Dispatch Out for Delivery
                                                </button>
                                            <?php elseif ($ord['order_status'] === 'out_for_delivery'): ?>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="updateOrderStatus(<?= $ord['order_id'] ?>, 'delivered');">
                                                    ✓ Mark as Delivered
                                                </button>
                                            <?php elseif ($ord['order_status'] === 'delivered'): ?>
                                                <span style="font-size:12px; color:var(--primary-700); font-weight:600;">
                                                    Awaiting Buyer Confirmation
                                                </span>
                                            <?php elseif ($ord['order_status'] === 'completed'): ?>
                                                <span style="font-size:12px; color:var(--primary-700); font-weight:700;">
                                                    ✓ Completed & Paid
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size:12px; color:var(--gray-400);">Closed</span>
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
