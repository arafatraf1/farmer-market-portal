<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');
$db = get_db();

// Delete inappropriate product
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $db->prepare("DELETE FROM products WHERE product_id = ?")->execute([$deleteId]);
    set_flash('success', 'Product has been permanently removed by administrator.');
    header('Location: ' . url('admin/products.php'));
    exit;
}

// Toggle availability
if (isset($_GET['toggle_id'])) {
    $toggleId = (int)$_GET['toggle_id'];
    $curr = $db->query("SELECT availability FROM products WHERE product_id = {$toggleId}")->fetchColumn();
    if ($curr !== false) {
        $new = ($curr === 'available') ? 'unavailable' : 'available';
        $db->prepare("UPDATE products SET availability = ? WHERE product_id = ?")->execute([$new, $toggleId]);
        set_flash('success', 'Product availability changed to ' . $new);
        header('Location: ' . url('admin/products.php'));
        exit;
    }
}

$statusFilter = trim($_GET['status'] ?? 'all');
$catFilter = trim($_GET['category'] ?? 'all');

$sql = "
    SELECT p.*, c.category_name, f.farm_name, f.verification_status, u.name as farmer_name
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    JOIN farmers f ON p.farmer_id = f.farmer_id
    JOIN users u ON f.user_id = u.user_id
    WHERE 1=1
";
$params = [];

if ($statusFilter === 'expired') {
    $sql .= " AND (p.expiry_date < CURDATE() OR p.availability = 'expired')";
} elseif ($statusFilter === 'available') {
    $sql .= " AND p.availability = 'available' AND p.expiry_date >= CURDATE()";
} elseif ($statusFilter === 'unavailable') {
    $sql .= " AND p.availability = 'unavailable'";
}

if ($catFilter !== 'all') {
    $sql .= " AND c.slug = :slug";
    $params[':slug'] = $catFilter;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

$pageTitle = 'Product Catalog Moderation — Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Agricultural Product Moderation</h1>
                <p>Oversight of fresh crop listings, shelf-life compliance, and safety standards</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="<?= url('admin/pricing.php') ?>" class="btn btn-primary btn-sm">
                    ⚖️ Product & Price Management &rarr;
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap;">
            <a href="<?= url('admin/products.php?status=all') ?>" class="btn <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All Produce</a>
            <a href="<?= url('admin/products.php?status=available') ?>" class="btn <?= $statusFilter === 'available' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Available</a>
            <a href="<?= url('admin/products.php?status=expired') ?>" class="btn <?= $statusFilter === 'expired' ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="color:var(--danger-500);">Expired Goods</a>
            <a href="<?= url('admin/products.php?status=unavailable') ?>" class="btn <?= $statusFilter === 'unavailable' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Unavailable</a>
        </div>

        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">Catalog Inventory (<?= count($products) ?> items)</span>
            </div>

            <div class="table-responsive">
                <table class="portal-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Farm & Farmer</th>
                            <th>Category</th>
                            <th>Price / Unit</th>
                            <th>Stock</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <?php $freshness = get_freshness_status($p['expiry_date']); ?>
                            <tr>
                                <td>
                                    <div class="table-item-cell">
                                        <img src="<?= e($p['image'] ?: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=80&q=80') ?>" alt="Thumb" class="table-thumbnail">
                                        <div>
                                            <a href="<?= url('product-details.php?id=' . $p['product_id']) ?>" target="_blank" style="font-weight:700; color:var(--gray-900);">
                                                <?= e($p['product_name']) ?>
                                            </a>
                                            <div style="font-size:11px; color:var(--gray-400);">Harvested: <?= date('M d, Y', strtotime($p['harvest_date'])) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:600;"><?= e($p['farm_name']) ?></div>
                                    <?= get_verification_badge($p['verification_status']) ?>
                                </td>
                                <td><span class="status-badge badge-primary"><?= e($p['category_name']) ?></span></td>
                                <td><?= format_price($p['price']) ?> / <?= e($p['unit']) ?></td>
                                <td>
                                    <strong><?= $p['quantity'] ?> <?= e($p['unit']) ?></strong>
                                </td>
                                <td>
                                    <span class="freshness-chip <?= e($freshness['class']) ?>">
                                        <?= e($freshness['label']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $p['availability'] === 'available' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= ucfirst(e($p['availability'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <a href="<?= url('admin/products.php?toggle_id=' . $p['product_id']) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;">
                                            <?= $p['availability'] === 'available' ? 'Pause' : 'Activate' ?>
                                        </a>
                                        <a href="<?= url('admin/products.php?delete_id=' . $p['product_id']) ?>" class="btn btn-secondary btn-sm" style="color:var(--danger-500); font-size:12px;" onclick="return confirm('Remove this product permanently from the platform?');">
                                            Remove
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
