<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('farmer');
$user = current_user();
$db = get_db();

$stmtFarmer = $db->prepare("SELECT farmer_id, farm_name, verification_status FROM farmers WHERE user_id = ?");
$stmtFarmer->execute([$user['user_id']]);
$farmer = $stmtFarmer->fetch();
$farmerId = (int)$farmer['farmer_id'];

// Quick toggle availability action
if (isset($_GET['toggle_id'])) {
    $toggleId = (int)$_GET['toggle_id'];
    $stmtCheck = $db->prepare("SELECT availability FROM products WHERE product_id = ? AND farmer_id = ?");
    $stmtCheck->execute([$toggleId, $farmerId]);
    $curr = $stmtCheck->fetchColumn();

    if ($curr !== false) {
        $newAvail = ($curr === 'available') ? 'unavailable' : 'available';
        $db->prepare("UPDATE products SET availability = ? WHERE product_id = ?")->execute([$newAvail, $toggleId]);
        set_flash('success', 'Product availability status updated to ' . $newAvail);
        header('Location: ' . url('farmer/products.php'));
        exit;
    }
}

// Fetch products
$stmt = $db->prepare("
    SELECT p.*, c.category_name
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    WHERE p.farmer_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$farmerId]);
$products = $stmt->fetchAll();

$pageTitle = 'Manage Agricultural Products';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/farmer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Manage Agricultural Produce</h1>
                <p>Track stock levels, harvest dates, and product freshness countdowns</p>
            </div>
            
            <a href="<?= url('farmer/product-add.php') ?>" class="btn btn-primary btn-sm">
                + Add New Product
            </a>
        </div>

        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">My Product Listings (<?= count($products) ?>)</span>
            </div>

            <?php if (empty($products)): ?>
                <div style="padding:50px; text-align:center; color:var(--gray-500);">
                    You have not added any products yet.
                    <div style="margin-top:14px;">
                        <a href="<?= url('farmer/product-add.php') ?>" class="btn btn-primary btn-sm">+ Add Your First Crop</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock Qty</th>
                                <th>Harvest Date</th>
                                <th>Expiry / Freshness</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <?php 
                                $freshness = get_freshness_status($p['expiry_date']);
                                $isExpired = $freshness['is_expired'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="table-item-cell">
                                            <img src="<?= e($p['image'] ?: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=150&q=80') ?>" alt="Thumb" class="table-thumbnail">
                                            <div>
                                                <a href="<?= url('product-details.php?id=' . $p['product_id']) ?>" target="_blank" style="font-weight:700; color:var(--gray-900);">
                                                    <?= e($p['product_name']) ?>
                                                </a>
                                                <div style="font-size:11px; color:var(--gray-400);">ID #<?= $p['product_id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="status-badge badge-primary"><?= e($p['category_name']) ?></span></td>
                                    <td>
                                        <strong><?= format_price($p['price']) ?></strong>
                                        <span style="font-size:11px; color:var(--gray-400);">/ <?= e($p['unit']) ?></span>
                                    </td>
                                    <td>
                                        <span style="font-weight:700; color: <?= $p['quantity'] > 0 ? 'var(--gray-800)' : 'var(--danger-500)' ?>;">
                                            <?= $p['quantity'] ?> <?= e($p['unit']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($p['harvest_date'])) ?></td>
                                    <td>
                                        <span class="freshness-chip <?= e($freshness['class']) ?>">
                                            <?= e($freshness['label']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($isExpired): ?>
                                            <span class="status-badge badge-danger">Expired</span>
                                        <?php elseif ($p['availability'] === 'available'): ?>
                                            <a href="<?= url('farmer/products.php?toggle_id=' . $p['product_id']) ?>" class="status-badge badge-success" title="Click to toggle unavailable">
                                                Available
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= url('farmer/products.php?toggle_id=' . $p['product_id']) ?>" class="status-badge badge-secondary" title="Click to toggle available">
                                                Unavailable
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <a href="<?= url('farmer/product-edit.php?id=' . $p['product_id']) ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px;" title="Edit Product">
                                                Edit
                                            </a>
                                            <a href="<?= url('farmer/product-delete.php?id=' . $p['product_id']) ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px; color:var(--danger-500);" onclick="return confirm('Are you sure you want to delete this product listing?');" title="Delete Product">
                                                Delete
                                            </a>
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
