<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');
$user = current_user();
$db = get_db();

// System KPIs
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalBuyers = (int)$db->query("SELECT COUNT(*) FROM buyers")->fetchColumn();
$totalFarmers = (int)$db->query("SELECT COUNT(*) FROM farmers")->fetchColumn();
$verifiedFarmers = (int)$db->query("SELECT COUNT(*) FROM farmers WHERE verification_status = 'verified'")->fetchColumn();
$pendingFarmers = (int)$db->query("SELECT COUNT(*) FROM farmers WHERE verification_status = 'pending'")->fetchColumn();

$totalProducts = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$activeProducts = (int)$db->query("SELECT COUNT(*) FROM products WHERE availability = 'available' AND quantity > 0 AND expiry_date >= CURDATE()")->fetchColumn();
$expiredProducts = (int)$db->query("SELECT COUNT(*) FROM products WHERE expiry_date < CURDATE() OR availability = 'expired'")->fetchColumn();

$stmtOrders = $db->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN order_status IN ('cancelled', 'rejected') THEN 1 ELSE 0 END) as cancelled_orders,
        COALESCE(SUM(CASE WHEN order_status = 'completed' THEN total_amount ELSE 0 END), 0) as total_volume
    FROM orders
")->fetch();

// Pending farmer verifications queue
$stmtPending = $db->query("
    SELECT f.*, u.name as farmer_name, u.email, u.phone
    FROM farmers f
    JOIN users u ON f.user_id = u.user_id
    WHERE f.verification_status = 'pending'
    ORDER BY f.created_at ASC
");
$pendingQueue = $stmtPending->fetchAll();

// Recent platform orders
$stmtRecentOrders = $db->query("
    SELECT o.*, f.farm_name, u.name as buyer_name
    FROM orders o
    JOIN farmers f ON o.farmer_id = f.farmer_id
    JOIN buyers b ON o.buyer_id = b.buyer_id
    JOIN users u ON b.user_id = u.user_id
    ORDER BY o.order_date DESC
    LIMIT 6
");
$recentOrders = $stmtRecentOrders->fetchAll();

$pageTitle = 'Admin Portal Control Center';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Admin Control Center</h1>
                <p>System metrics, farmer verification queue, user management, and platform analytics</p>
            </div>
            
            <div style="display:flex; gap:10px;">
                <a href="<?= url('admin/pricing.php') ?>" class="btn btn-secondary btn-sm">
                    ⚖️ Product & Price Control
                </a>
                <a href="<?= url('admin/verification.php') ?>" class="btn btn-primary btn-sm">
                    Review Verifications (<?= $pendingFarmers ?>)
                </a>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple">👥</div>
                <div class="stat-details">
                    <span class="stat-value"><?= $totalUsers ?></span>
                    <span class="stat-label">Total Users (<?= $totalBuyers ?> Buyers, <?= $totalFarmers ?> Farmers)</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">🛡️</div>
                <div class="stat-details">
                    <span class="stat-value"><?= $verifiedFarmers ?> / <?= $totalFarmers ?></span>
                    <span class="stat-label">Verified Farmers (<?= $pendingFarmers ?> Pending)</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon amber">🌾</div>
                <div class="stat-details">
                    <span class="stat-value"><?= $activeProducts ?></span>
                    <span class="stat-label">Active Listings (<?= $expiredProducts ?> Expired)</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue">📦</div>
                <div class="stat-details">
                    <span class="stat-value"><?= (int)$stmtOrders['total_orders'] ?></span>
                    <span class="stat-label">Orders (<?= (int)$stmtOrders['completed_orders'] ?> Completed)</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">💵</div>
                <div class="stat-details">
                    <span class="stat-value"><?= format_price($stmtOrders['total_volume']) ?></span>
                    <span class="stat-label">Gross Completed Trade</span>
                </div>
            </div>
        </div>

        <!-- Pending Verifications Alert / Card -->
        <?php if (!empty($pendingQueue)): ?>
            <div class="card-panel" style="border-left:4px solid var(--accent-amber-dark);">
                <div class="card-panel-header" style="background:#fffbeb;">
                    <div>
                        <span class="card-panel-title" style="color:#92400e;">⚠️ Pending Farmer Identity Verifications (<?= count($pendingQueue) ?>)</span>
                        <div style="font-size:12.5px; color:#b45309; margin-top:2px;">
                            Inspect submitted NID/Birth certificates and grant or deny verification badges
                        </div>
                    </div>
                    <a href="<?= url('admin/verification.php') ?>" class="btn btn-accent btn-sm">Audit All &rarr;</a>
                </div>

                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Farmer Name</th>
                                <th>Farm & Location</th>
                                <th>Document Type</th>
                                <th>Submitted On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingQueue as $pFarmer): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($pFarmer['farmer_name']) ?></strong>
                                        <div style="font-size:11.5px; color:var(--gray-500);"><?= e($pFarmer['email']) ?> • <?= e($pFarmer['phone']) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?= e($pFarmer['farm_name']) ?></div>
                                        <div style="font-size:12px; color:var(--gray-500);"><?= e($pFarmer['farm_location']) ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-primary"><?= strtoupper(e($pFarmer['verification_doc_type'])) ?></span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($pFarmer['created_at'])) ?></td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <a href="<?= url('admin/view-doc.php?farmer_id=' . $pFarmer['farmer_id']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size:12px;">
                                                🔍 View Doc
                                            </a>
                                            <a href="<?= url('admin/verification.php?action=approve&id=' . $pFarmer['farmer_id']) ?>" class="btn btn-primary btn-sm" style="font-size:12px;" onclick="return confirm('Approve this farmer as Verified?');">
                                                Approve
                                            </a>
                                            <button type="button" class="btn btn-secondary btn-sm" style="color:var(--danger-500); font-size:12px;" onclick="const r=prompt('Enter rejection reason:'); if(r) window.location.href='<?= url('admin/verification.php?action=reject&id=' . $pFarmer['farmer_id']) ?>&notes='+encodeURIComponent(r);">
                                                Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Platform Orders -->
        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">Recent System Orders</span>
                <a href="<?= url('admin/orders.php') ?>" class="btn btn-secondary btn-sm">All Orders &rarr;</a>
            </div>

            <div class="table-responsive">
                <table class="portal-table">
                    <thead>
                        <tr>
                            <th>Order Code</th>
                            <th>Buyer</th>
                            <th>Farm</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $ro): ?>
                            <tr>
                                <td><strong><?= e($ro['order_code']) ?></strong></td>
                                <td><?= e($ro['buyer_name']) ?></td>
                                <td><?= e($ro['farm_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($ro['order_date'])) ?></td>
                                <td><strong><?= format_price($ro['total_amount']) ?></strong></td>
                                <td><?= get_order_badge($ro['order_status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
