<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');
$db = get_db();

$statusFilter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT o.*, f.farm_name, f.verification_status,
           uBuyer.name as buyer_name, uBuyer.phone as buyer_phone,
           uFarmer.name as farmer_name,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items_count,
           (SELECT GROUP_CONCAT(CONCAT(p.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ')
            FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as items_summary
    FROM orders o
    JOIN farmers f ON o.farmer_id = f.farmer_id
    JOIN users uFarmer ON f.user_id = uFarmer.user_id
    JOIN buyers b ON o.buyer_id = b.buyer_id
    JOIN users uBuyer ON b.user_id = uBuyer.user_id
    WHERE 1=1
";
$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND o.order_status = :status";
    $params[':status'] = $statusFilter;
}

if ($search !== '') {
    $sql .= " AND (o.order_code LIKE :q OR uBuyer.name LIKE :q OR f.farm_name LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

$sql .= " ORDER BY o.order_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Orders Oversight — Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>System-Wide Orders Oversight</h1>
                <p>Monitor transaction progression, trade volumes, and dispatch efficiency</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <form action="" method="GET" style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap; background:#ffffff; padding:16px 20px; border-radius:12px; border:1px solid var(--border-color); align-items:center;">
            <input type="text" name="q" placeholder="Order code, buyer, farm..." value="<?= e($search) ?>" class="form-control" style="width:260px;">
            
            <select name="status" class="form-control" style="width:190px;">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <?php foreach (ORDER_STATUSES as $sKey => $sLabel): ?>
                    <option value="<?= $sKey ?>" <?= $statusFilter === $sKey ? 'selected' : '' ?>><?= e($sLabel) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">Filter Orders</button>
            <a href="<?= url('admin/orders.php') ?>" class="btn btn-secondary btn-sm">Reset</a>
        </form>

        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">Recorded Transactions (<?= count($orders) ?>)</span>
            </div>

            <div class="table-responsive">
                <table class="portal-table">
                    <thead>
                        <tr>
                            <th>Order Code</th>
                            <th>Buyer</th>
                            <th>Farmer / Farm</th>
                            <th>Produce Summary</th>
                            <th>Order Date</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $ord): ?>
                            <tr>
                                <td>
                                    <strong style="color:var(--gray-900);"><?= e($ord['order_code']) ?></strong>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:var(--gray-900);"><?= e($ord['buyer_name']) ?></div>
                                    <div style="font-size:11.5px; color:var(--gray-500);">📞 <?= e($ord['buyer_phone']) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:600;"><?= e($ord['farm_name']) ?></div>
                                    <?= get_verification_badge($ord['verification_status']) ?>
                                </td>
                                <td style="max-width:240px; font-size:12.5px; color:var(--gray-700);">
                                    <?= e(mb_strimwidth($ord['items_summary'] ?? '', 0, 60, '...')) ?>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($ord['order_date'])) ?></td>
                                <td><strong><?= format_price($ord['total_amount']) ?></strong></td>
                                <td><?= get_order_badge($ord['order_status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
