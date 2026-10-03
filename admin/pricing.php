<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');
$db = get_db();

$errors = [];

// Handle Delete Master Product
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $count = (int)$db->query("SELECT COUNT(*) FROM products WHERE master_product_id = {$delId}")->fetchColumn();
    if ($count > 0) {
        set_flash('error', "Cannot delete master product: {$count} farmer inventory listings are currently linked to it.");
    } else {
        $db->prepare("DELETE FROM master_products WHERE master_product_id = ?")->execute([$delId]);
        set_flash('success', 'Master product removed from catalog.');
    }
    header('Location: ' . url('admin/pricing.php'));
    exit;
}

// Handle Toggle Status (Active / Inactive)
if (isset($_GET['toggle_id'])) {
    $toggleId = (int)$_GET['toggle_id'];
    $curr = $db->query("SELECT status FROM master_products WHERE master_product_id = {$toggleId}")->fetchColumn();
    if ($curr !== false) {
        $new = ($curr === 'active') ? 'inactive' : 'active';
        $db->prepare("UPDATE master_products SET status = ? WHERE master_product_id = ?")->execute([$new, $toggleId]);
        set_flash('success', 'Product status changed to ' . ucfirst($new));
        header('Location: ' . url('admin/pricing.php'));
        exit;
    }
}

// Handle Add / Edit Master Product
$editProduct = null;
if (isset($_GET['edit_id'])) {
    $stmtEdit = $db->prepare("SELECT * FROM master_products WHERE master_product_id = ?");
    $stmtEdit->execute([(int)$_GET['edit_id']]);
    $editProduct = $stmtEdit->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prodName = trim($_POST['product_name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $officialPrice = (float)($_POST['official_price'] ?? 0);
    $unit = trim($_POST['unit'] ?? 'kg');
    $description = trim($_POST['description'] ?? '');
    $imageUrl = trim($_POST['image'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $masterId = (int)($_POST['master_product_id'] ?? 0);

    if (empty($prodName)) {
        $errors[] = 'Product name is required.';
    }
    if ($categoryId <= 0) {
        $errors[] = 'Please select a valid category.';
    }
    if ($officialPrice <= 0) {
        $errors[] = 'Official price must be greater than zero.';
    }
    if (empty($unit)) {
        $errors[] = 'Measurement unit is required.';
    }

    if (empty($errors)) {
        if ($masterId > 0) {
            // Update Master Product
            $stmt = $db->prepare("
                UPDATE master_products 
                SET product_name = ?, category_id = ?, official_price = ?, unit = ?, description = ?, image = ?, status = ?
                WHERE master_product_id = ?
            ");
            $stmt->execute([$prodName, $categoryId, $officialPrice, $unit, $description, $imageUrl, $status, $masterId]);

            // Sync all active farmer listings to the newly updated official price & unit
            $stmtSync = $db->prepare("
                UPDATE products 
                SET price = ?, unit = ?, product_name = ?, category_id = ?
                WHERE master_product_id = ?
            ");
            $stmtSync->execute([$officialPrice, $unit, $prodName, $categoryId, $masterId]);

            set_flash('success', "Official price for '{$prodName}' updated to " . format_price($officialPrice) . "/{$unit}. All farmer listings synced.");
        } else {
            // Insert New Master Product
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM master_products WHERE product_name = ?");
            $stmtCheck->execute([$prodName]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errors[] = "A master product with name '{$prodName}' already exists in the catalog.";
            } else {
                $stmt = $db->prepare("
                    INSERT INTO master_products (category_id, product_name, official_price, unit, description, image, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$categoryId, $prodName, $officialPrice, $unit, $description, $imageUrl, $status]);
                set_flash('success', "New product '{$prodName}' added with official price " . format_price($officialPrice) . "/{$unit}.");
            }
        }

        if (empty($errors)) {
            header('Location: ' . url('admin/pricing.php'));
            exit;
        }
    }
}

// Fetch Master Products with linked farmer listing counts
$search = trim($_GET['q'] ?? '');
$catFilter = trim($_GET['category'] ?? 'all');

$sql = "
    SELECT mp.*, c.category_name, c.slug as category_slug,
           COUNT(p.product_id) as total_farmer_listings,
           COALESCE(SUM(p.quantity), 0) as total_market_stock
    FROM master_products mp
    JOIN categories c ON mp.category_id = c.category_id
    LEFT JOIN products p ON mp.master_product_id = p.master_product_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (mp.product_name LIKE :q OR mp.description LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

if ($catFilter !== 'all') {
    $sql .= " AND c.slug = :slug";
    $params[':slug'] = $catFilter;
}

$sql .= " GROUP BY mp.master_product_id ORDER BY c.category_name ASC, mp.product_name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$masterProducts = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

$pageTitle = 'Product & Price Management — Admin Control';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Product & Price Management</h1>
                <p>Set and enforce official market prices (BDT) across all farmer listings to remove price ambiguity</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="<?= url('admin/products.php') ?>" class="btn btn-secondary btn-sm">
                    🌾 Farmer Listings Moderation &rarr;
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left:20px;">
                    <?php foreach ($errors as $e): ?>
                        <li><?= e($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Key Policy Notice Banner -->
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; gap:16px;">
            <div style="font-size:24px;">⚖️</div>
            <div style="font-size:13.5px; color:#166534; line-height:1.5;">
                <strong>Admin Fixed Price Policy:</strong> When you set or modify the official price of a product below (e.g. <em>Tomato = 50 BDT/kg</em>), EVERY farmer who adds or sells that product is automatically locked to that exact price. Farmers can only decide their stock quantity.
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 380px; gap:32px; align-items:flex-start;">
            
            <!-- Table of Official Products -->
            <div>
                <!-- Filter Bar -->
                <form action="" method="GET" style="display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap;">
                    <input type="text" name="q" placeholder="Search product name..." value="<?= e($search) ?>" class="form-control" style="width:220px;">
                    <select name="category" class="form-control" style="width:170px;">
                        <option value="all" <?= $catFilter === 'all' ? 'selected' : '' ?>>All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat['slug']) ?>" <?= $catFilter === $cat['slug'] ? 'selected' : '' ?>>
                                <?= e($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="<?= url('admin/pricing.php') ?>" class="btn btn-secondary btn-sm">Reset</a>
                </form>

                <div class="card-panel">
                    <div class="card-panel-header">
                        <span class="card-panel-title">Official Product Price Catalog (<?= count($masterProducts) ?> Products)</span>
                    </div>

                    <div class="table-responsive">
                        <table class="portal-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Official Price</th>
                                    <th>Unit</th>
                                    <th>Farmers Selling</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($masterProducts)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center; padding:40px; color:var(--gray-500);">
                                            No master products found matching your search.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($masterProducts as $mp): ?>
                                        <tr>
                                            <td>
                                                <div class="table-item-cell">
                                                    <img src="<?= e($mp['image'] ?: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=80&q=80') ?>" alt="Thumb" class="table-thumbnail">
                                                    <div>
                                                        <strong style="color:var(--gray-900); font-size:14px;"><?= e($mp['product_name']) ?></strong>
                                                        <div style="font-size:11.5px; color:var(--gray-400);">ID #<?= $mp['master_product_id'] ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge badge-primary"><?= e($mp['category_name']) ?></span>
                                            </td>
                                            <td>
                                                <span style="font-size:15px; font-weight:800; color:var(--primary-800);">
                                                    <?= format_price($mp['official_price']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge badge-info"><?= e($mp['unit']) ?></span>
                                            </td>
                                            <td>
                                                <div style="font-weight:700; color:var(--gray-800);">
                                                    <?= (int)$mp['total_farmer_listings'] ?> listings
                                                </div>
                                                <div style="font-size:11px; color:var(--gray-400);">
                                                    <?= $mp['total_market_stock'] ?> <?= e($mp['unit']) ?> in market
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= $mp['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                                    <?= ucfirst(e($mp['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display:flex; gap:6px;">
                                                    <a href="<?= url('admin/pricing.php?edit_id=' . $mp['master_product_id']) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;" title="Edit Official Price">
                                                        Edit Price
                                                    </a>
                                                    <a href="<?= url('admin/pricing.php?toggle_id=' . $mp['master_product_id']) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;">
                                                        <?= $mp['status'] === 'active' ? 'Pause' : 'Activate' ?>
                                                    </a>
                                                    <?php if ((int)$mp['total_farmer_listings'] === 0): ?>
                                                        <a href="<?= url('admin/pricing.php?delete_id=' . $mp['master_product_id']) ?>" class="btn btn-secondary btn-sm" style="color:var(--danger-500); font-size:12px;" onclick="return confirm('Delete this master product?');">
                                                            Delete
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add / Edit Form Card -->
            <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:24px; box-shadow:var(--shadow-sm); position:sticky; top:94px;">
                <h3 style="font-size:17px; font-weight:700; color:var(--gray-900); margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border-color);">
                    <?= $editProduct ? 'Edit Official Price & Details' : 'Add New Master Product' ?>
                </h3>

                <form action="" method="POST">
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="master_product_id" value="<?= $editProduct['master_product_id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Product Name <span class="req">*</span></label>
                        <input type="text" name="product_name" class="form-control" placeholder="e.g. Tomato, Potato, Miniket Rice" value="<?= e($editProduct['product_name'] ?? $_POST['product_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category <span class="req">*</span></label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select Category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= ((int)($editProduct['category_id'] ?? $_POST['category_id'] ?? 0) === (int)$cat['category_id']) ? 'selected' : '' ?>>
                                    <?= e($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label">Official Price (BDT / ৳) <span class="req">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="official_price" class="form-control" placeholder="50.00" value="<?= e($editProduct['official_price'] ?? $_POST['official_price'] ?? '') ?>" required>
                            <div class="form-hint">Enforced for all farmers</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Unit <span class="req">*</span></label>
                            <select name="unit" class="form-control" required>
                                <?php foreach (PRODUCT_UNITS as $uKey => $uLabel): ?>
                                    <option value="<?= $uKey ?>" <?= (($editProduct['unit'] ?? $_POST['unit'] ?? 'kg') === $uKey) ? 'selected' : '' ?>>
                                        <?= e($uKey) ?> (<?= e($uLabel) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= (($editProduct['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active (Farmers can sell)</option>
                            <option value="inactive" <?= (($editProduct['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive (Paused)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Catalog Reference Image URL</label>
                        <input type="url" name="image" class="form-control" placeholder="https://..." value="<?= e($editProduct['image'] ?? $_POST['image'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description & Quality Standards</label>
                        <textarea name="description" class="form-control" placeholder="Official description, standard grading requirements..."><?= e($editProduct['description'] ?? $_POST['description'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
                        <?= $editProduct ? 'Save Official Price Changes' : 'Publish Master Product' ?>
                    </button>

                    <?php if ($editProduct): ?>
                        <div style="text-align:center; margin-top:10px;">
                            <a href="<?= url('admin/pricing.php') ?>" style="font-size:13px; color:var(--gray-500);">Cancel Edit</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
