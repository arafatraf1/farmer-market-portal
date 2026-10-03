<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('farmer');
$user = current_user();
$db = get_db();

// Fetch farmer profile
$stmtFarmer = $db->prepare("SELECT * FROM farmers WHERE user_id = ?");
$stmtFarmer->execute([$user['user_id']]);
$farmer = $stmtFarmer->fetch();
$farmerId = (int)($farmer['farmer_id'] ?? 0);

// Product KPIs
$stmtProdStats = $db->prepare("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN availability = 'available' AND quantity > 0 AND expiry_date >= CURDATE() THEN 1 ELSE 0 END) as active_products,
        SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN expiry_date < CURDATE() OR availability = 'expired' THEN 1 ELSE 0 END) as expired_products
    FROM products 
    WHERE farmer_id = ?
");
$stmtProdStats->execute([$farmerId]);
$prodStats = $stmtProdStats->fetch();

// Order KPIs
$stmtOrdStats = $db->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN order_status = 'accepted' THEN 1 ELSE 0 END) as accepted_orders,
        SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN order_status IN ('cancelled', 'rejected') THEN 1 ELSE 0 END) as cancelled_orders,
        COALESCE(SUM(CASE WHEN order_status = 'completed' THEN total_amount ELSE 0 END), 0) as total_sales
    FROM orders 
    WHERE farmer_id = ?
");
$stmtOrdStats->execute([$farmerId]);
$ordStats = $stmtOrdStats->fetch();

// Recent Orders with Buyer Trust Information
$stmtRecent = $db->prepare("
    SELECT o.*, u.name as buyer_name, u.phone as buyer_phone,
           b.completed_orders as buyer_completed, b.cancelled_orders as buyer_cancelled
    FROM orders o
    JOIN buyers b ON o.buyer_id = b.buyer_id
    JOIN users u ON b.user_id = u.user_id
    WHERE o.farmer_id = ?
    ORDER BY o.order_date DESC
    LIMIT 6
");
$stmtRecent->execute([$farmerId]);
$recentOrders = $stmtRecent->fetchAll();

$pageTitle = 'Farmer Dashboard — ' . $farmer['farm_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/farmer_nav.php'; ?>

    <main class="dashboard-main">
        
        <!-- Header & Verification Banner -->
        <div class="dashboard-header">
            <div>
                <h1><?= e($farmer['farm_name']) ?></h1>
                <p>Manage crops, inspect purchase requests, and dispatch fresh agricultural produce</p>
            </div>
            
            <div style="display:flex; align-items:center; gap:12px;">
                <?= get_verification_badge($farmer['verification_status']) ?>
                <a href="<?= url('farmer/product-add.php') ?>" class="btn btn-primary btn-sm">
                    + Add New Product
                </a>
            </div>
        </div>

        <!-- Verification Banner if not verified -->
        <?php if ($farmer['verification_status'] === 'pending'): ?>
            <div class="verification-banner pending">
                <div>
                    <h3 style="font-size:16px; font-weight:700; margin-bottom:4px;">⏳ Farmer Identity Verification Under Review</h3>
                    <p style="font-size:13.5px; line-height:1.5;">
                        Your submitted identification document (NID / Birth Certificate) is currently being audited by platform administrators. Once approved, your products will carry the official <strong>Verified Farmer Badge</strong>.
                    </p>
                </div>
                <a href="<?= url('farmer/verification.php') ?>" class="btn btn-secondary btn-sm">Check Status</a>
            </div>
        <?php elseif ($farmer['verification_status'] === 'rejected'): ?>
            <div class="verification-banner rejected">
                <div>
                    <h3 style="font-size:16px; font-weight:700; margin-bottom:4px;">⚠️ Verification Needs Resubmission</h3>
                    <p style="font-size:13.5px; line-height:1.5;">
                        Admin notes: <em><?= e($farmer['admin_notes'] ?: 'Document was illegible or incomplete.') ?></em>. Please resubmit clear documentation.
                    </p>
                </div>
                <a href="<?= url('farmer/verification.php') ?>" class="btn btn-danger btn-sm">Re-upload Document</a>
            </div>
        <?php endif; ?>

        <!-- Quick Actions Bar -->
        <div style="display:flex; gap:10px; margin-bottom:28px; flex-wrap:wrap;">
            <a href="<?= url('farmer/product-add.php') ?>" class="btn btn-primary btn-sm">Add Product</a>
            <a href="<?= url('farmer/products.php') ?>" class="btn btn-secondary btn-sm">Manage Products</a>
            <a href="<?= url('farmer/orders.php') ?>" class="btn btn-secondary btn-sm">
                View Orders <?= ((int)$ordStats['pending_orders'] > 0) ? '(' . (int)$ordStats['pending_orders'] . ' Pending)' : '' ?>
            </a>
            <a href="<?= url('farmer/reviews.php') ?>" class="btn btn-secondary btn-sm">Customer Reviews</a>
            <a href="<?= url('farmer/profile.php') ?>" class="btn btn-secondary btn-sm">Farm Profile</a>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green">🌾</div>
                <div class="stat-details">
                    <span class="stat-value"><?= (int)$prodStats['total_products'] ?></span>
                    <span class="stat-label">Total Products (<?= (int)$prodStats['active_products'] ?> Active)</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon amber">🔔</div>
                <div class="stat-details">
                    <span class="stat-value"><?= (int)$ordStats['pending_orders'] ?></span>
                    <span class="stat-label">Pending Purchase Requests</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue">🚚</div>
                <div class="stat-details">
                    <span class="stat-value"><?= (int)$ordStats['accepted_orders'] ?></span>
                    <span class="stat-label">Accepted / In Transit</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">💰</div>
                <div class="stat-details">
                    <span class="stat-value"><?= format_price($ordStats['total_sales']) ?></span>
                    <span class="stat-label">Completed Sales Volume</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon rose">⭐</div>
                <div class="stat-details">
                    <span class="stat-value"><?= number_format((float)$farmer['farmer_rating'], 1) ?> / 5</span>
                    <span class="stat-label"><?= (int)$farmer['total_reviews'] ?> Verified Reviews</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:#fee2e2; color:#b91c1c;">⚠️</div>
                <div class="stat-details">
                    <span class="stat-value"><?= (int)$prodStats['out_of_stock'] ?></span>
                    <span class="stat-label">Out of Stock Listings</span>
                </div>
            </div>
        </div>

        <!-- Recent Customer Orders Section with Buyer Trust Rating -->
        <div class="card-panel">
            <div class="card-panel-header">
                <div>
                    <span class="card-panel-title">Incoming Customer Orders</span>
                    <div style="font-size:12.5px; color:var(--gray-500); margin-top:2px;">
                        Inspect buyer reliability scores before accepting purchase requests
                    </div>
                </div>
                <a href="<?= url('farmer/orders.php') ?>" class="btn btn-secondary btn-sm">All Orders &rarr;</a>
            </div>

            <?php if (empty($recentOrders)): ?>
                <div style="padding:40px; text-align:center; color:var(--gray-500);">
                    No orders received yet. Active product listings will appear in buyer searches.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Order Code</th>
                                <th>Buyer & Reliability Score</th>
                                <th>Order Date</th>
                                <th>Requested Delivery</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $ord): ?>
                                <?php $bTrust = get_buyer_trust_info((int)$ord['buyer_completed'], (int)$ord['buyer_cancelled']); ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('farmer/orders.php?highlight=' . $ord['order_id']) ?>" style="font-weight:700; color:var(--primary-800);">
                                            <?= e($ord['order_code']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div style="font-weight:700; color:var(--gray-900);"><?= e($ord['buyer_name']) ?></div>
                                        <div style="margin-top:2px;">
                                            <span class="status-badge <?= e($bTrust['badge_class']) ?>" style="font-size:11px; padding:2px 7px;">
                                                <?= e($bTrust['rate_text']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?= date('M d, h:i A', strtotime($ord['order_date'])) ?></td>
                                    <td><?= e($ord['requested_delivery_date'] ? date('M d, Y', strtotime($ord['requested_delivery_date'])) : 'Standard') ?></td>
                                    <td><strong><?= format_price($ord['total_amount']) ?></strong></td>
                                    <td><?= get_order_badge($ord['order_status']) ?></td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <?php if ($ord['order_status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="updateOrderStatus(<?= $ord['order_id'] ?>, 'accepted');">
                                                    Accept
                                                </button>
                                                <button type="button" class="btn btn-secondary btn-sm" style="color:var(--danger-500);" onclick="const r=prompt('Enter rejection reason:'); if(r) updateOrderStatus(<?= $ord['order_id'] ?>, 'rejected', r);">
                                                    Reject
                                                </button>
                                            <?php else: ?>
                                                <a href="<?= url('farmer/orders.php?highlight=' . $ord['order_id']) ?>" class="btn btn-secondary btn-sm">
                                                    Manage &rarr;
                                                </a>
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
