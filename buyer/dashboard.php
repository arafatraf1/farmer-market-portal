<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('buyer');
$user = current_user();
$db = get_db();

// Fetch buyer profile and trust metrics
$stmtBuyer = $db->prepare("SELECT * FROM buyers WHERE user_id = ?");
$stmtBuyer->execute([$user['user_id']]);
$buyer = $stmtBuyer->fetch() ?: ['completed_orders' => 0, 'cancelled_orders' => 0];
$trust = get_buyer_trust_info((int)$buyer['completed_orders'], (int)$buyer['cancelled_orders']);

// Order stats
$stmtStats = $db->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN order_status NOT IN ('completed', 'cancelled', 'rejected') THEN 1 ELSE 0 END) as active_count,
        COALESCE(SUM(CASE WHEN order_status = 'completed' THEN total_amount ELSE 0 END), 0) as total_spent
    FROM orders 
    WHERE buyer_id = ?
");
$stmtStats->execute([$buyer['buyer_id'] ?? 0]);
$stats = $stmtStats->fetch();

// Active deliveries currently in transit or preparing
$stmtActive = $db->prepare("
    SELECT o.*, f.farm_name, f.farm_location, u.name as farmer_name
    FROM orders o
    JOIN farmers f ON o.farmer_id = f.farmer_id
    JOIN users u ON f.user_id = u.user_id
    WHERE o.buyer_id = ? AND o.order_status NOT IN ('completed', 'cancelled', 'rejected')
    ORDER BY o.order_date DESC
");
$stmtActive->execute([$buyer['buyer_id'] ?? 0]);
$activeOrders = $stmtActive->fetchAll();

$pageTitle = 'Buyer Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/buyer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Welcome back, <?= e(explode(' ', $user['name'])[0]) ?>!</h1>
                <p>Track fresh harvest shipments and manage your direct farm orders</p>
            </div>
            
            <div class="trust-score-box">
                <span style="font-size:12px; color:var(--gray-500); font-weight:600;">Buyer Reliability:</span>
                <span class="status-badge <?= e($trust['badge_class']) ?>"><?= e($trust['rate_text']) ?></span>
            </div>
        </div>

        <!-- KPI Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green">📦</div>
                <div class="stat-details">
                    <span class="stat-value"><?= (int)$stats['total_orders'] ?></span>
                    <span class="stat-label">Total Orders Placed</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon amber">🚚</div>
                <div class="stat-details">
                    <span class="stat-value"><?= (int)$stats['active_count'] ?></span>
                    <span class="stat-label">In-Progress Deliveries</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue">✅</div>
                <div class="stat-details">
                    <span class="stat-value"><?= (int)$stats['completed_count'] ?></span>
                    <span class="stat-label">Completed Purchases</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">💰</div>
                <div class="stat-details">
                    <span class="stat-value"><?= format_price($stats['total_spent']) ?></span>
                    <span class="stat-label">Total Farm Spent</span>
                </div>
            </div>
        </div>

        <!-- Active Deliveries Section -->
        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">Active Shipments & Deliveries</span>
                <a href="<?= url('buyer/orders.php') ?>" class="btn btn-secondary btn-sm">View All Orders &rarr;</a>
            </div>

            <?php if (empty($activeOrders)): ?>
                <div style="padding:40px; text-align:center; color:var(--gray-500);">
                    You currently have no active deliveries in progress.
                    <div style="margin-top:12px;">
                        <a href="<?= url('browse.php') ?>" class="btn btn-primary btn-sm">Browse Fresh Produce</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Order Code</th>
                                <th>Farm / Farmer</th>
                                <th>Ordered On</th>
                                <th>Estimated Delivery</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Tracking</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeOrders as $ord): ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--gray-900);"><?= e($ord['order_code']) ?></strong>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?= e($ord['farm_name']) ?></div>
                                        <div style="font-size:12px; color:var(--gray-500);"><?= e($ord['farm_location']) ?></div>
                                    </td>
                                    <td><?= date('M d, Y h:i A', strtotime($ord['order_date'])) ?></td>
                                    <td><?= e($ord['estimated_delivery_time']) ?></td>
                                    <td><strong><?= format_price($ord['total_amount']) ?></strong></td>
                                    <td><?= get_order_badge($ord['order_status']) ?></td>
                                    <td>
                                        <a href="<?= url('buyer/order-tracking.php?id=' . $ord['order_id']) ?>" class="btn btn-primary btn-sm">
                                            Live Tracker &rarr;
                                        </a>
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
