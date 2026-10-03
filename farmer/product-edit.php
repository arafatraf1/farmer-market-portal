<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('farmer');
$user = current_user();
$productId = (int)($_GET['id'] ?? 0);

if ($productId <= 0) {
    set_flash('error', 'Invalid product.');
    header('Location: ' . url('farmer/products.php'));
    exit;
}

$db = get_db();

// Verify product belongs to current farmer
$stmtFarmer = $db->prepare("SELECT farmer_id FROM farmers WHERE user_id = ?");
$stmtFarmer->execute([$user['user_id']]);
$farmerId = (int)$stmtFarmer->fetchColumn();

$stmt = $db->prepare("
    SELECT p.*, c.category_name, mp.official_price as master_price, mp.unit as master_unit, mp.product_name as master_name
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN master_products mp ON p.master_product_id = mp.master_product_id
    WHERE p.product_id = ? AND p.farmer_id = ?
");
$stmt->execute([$productId, $farmerId]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found or unauthorized.');
    header('Location: ' . url('farmer/products.php'));
    exit;
}

// Current official price from master catalog or fallback to current locked price
$officialPrice = (float)($product['master_price'] ?? $product['price']);
$officialUnit = $product['master_unit'] ?: $product['unit'];
$officialName = $product['master_name'] ?: $product['product_name'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = (int)($_POST['quantity'] ?? 0);
    $harvestDate = trim($_POST['harvest_date'] ?? '');
    $expiryDate = trim($_POST['expiry_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $availability = in_array($_POST['availability'] ?? '', ['available', 'unavailable', 'expired']) ? $_POST['availability'] : 'available';

    if ($quantity < 0) {
        $errors[] = 'Quantity must be greater than or equal to zero.';
    }
    if (empty($harvestDate) || empty($expiryDate)) {
        $errors[] = 'Harvest and expiry dates are required.';
    } elseif (strtotime($expiryDate) < strtotime($harvestDate)) {
        $errors[] = 'Expiry date cannot be earlier than harvest date.';
    }

    $imagePath = $product['image'];
    if (!empty($_FILES['product_image']['name'])) {
        $up = upload_image($_FILES['product_image'], 'products');
        if ($up['success']) {
            $imagePath = $up['filename'];
        } else {
            $errors[] = $up['error'];
        }
    }

    if (empty($errors)) {
        $fresh = get_freshness_status($expiryDate);
        if ($fresh['is_expired']) {
            $availability = 'expired';
        } elseif ($quantity === 0 && $availability === 'available') {
            $availability = 'unavailable';
        }

        // Server-side enforcement: Lock price to official catalog price
        $stmtUpdate = $db->prepare("
            UPDATE products 
            SET price = ?, unit = ?, quantity = ?, 
                harvest_date = ?, expiry_date = ?, description = ?, image = ?, availability = ?
            WHERE product_id = ? AND farmer_id = ?
        ");
        $stmtUpdate->execute([
            $officialPrice, // Server-enforced official price
            $officialUnit,  // Server-enforced official unit
            $quantity,
            $harvestDate,
            $expiryDate,
            $description,
            $imagePath,
            $availability,
            $productId,
            $farmerId
        ]);

        set_flash('success', "Listing for '{$product['product_name']}' updated successfully.");
        header('Location: ' . url('farmer/products.php'));
        exit;
    }
}

$pageTitle = 'Edit Produce Listing — ' . $product['product_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/farmer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <a href="<?= url('farmer/products.php') ?>" style="font-size:13px; color:var(--gray-500); font-weight:600; display:inline-flex; align-items:center; gap:4px; margin-bottom:6px;">
                    &larr; Back to Products
                </a>
                <h1>Edit Produce Listing</h1>
                <p>Update stock inventory, harvest dates, and cultivation notes for <?= e($product['product_name']) ?></p>
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

        <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:32px; max-width:740px; box-shadow:var(--shadow-sm);">
            
            <!-- Official Admin Price Callout -->
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:18px 20px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div>
                    <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#15803d; margin-bottom:2px;">
                        🔒 Official Market Fixed Price
                    </div>
                    <div style="font-size:24px; font-weight:800; color:#14532d;">
                        <?= format_price($officialPrice) ?> <span style="font-size:15px; font-weight:600; color:#166534;">/ <?= e($officialUnit) ?></span>
                    </div>
                    <div style="font-size:12px; color:#166534; margin-top:4px;">
                        Set by Platform Market Authority for <strong><?= e($product['product_name']) ?></strong>
                    </div>
                </div>
                <div>
                    <span class="status-badge badge-primary" style="font-size:13px; padding:6px 12px;"><?= e($product['category_name']) ?></span>
                </div>
            </div>

            <form action="" method="POST" enctype="multipart/form-data">
                
                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" class="form-control" value="<?= e($product['product_name']) ?>" readonly style="background:var(--gray-50); font-weight:700;">
                </div>

                <div class="form-group">
                    <label class="form-label">Available Stock Quantity (<?= e($officialUnit) ?>) <span class="req">*</span></label>
                    <input type="number" min="0" name="quantity" class="form-control" value="<?= e($_POST['quantity'] ?? $product['quantity']) ?>" required style="max-width:240px;">
                    <div class="form-hint">Set to 0 if out of stock.</div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Harvest / Production Date <span class="req">*</span></label>
                        <input type="date" name="harvest_date" class="form-control" value="<?= e($_POST['harvest_date'] ?? $product['harvest_date']) ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expiry / Best Before Date <span class="req">*</span></label>
                        <input type="date" name="expiry_date" class="form-control" value="<?= e($_POST['expiry_date'] ?? $product['expiry_date']) ?>" min="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Availability Status</label>
                    <select name="availability" class="form-control">
                        <option value="available" <?= ($product['availability'] === 'available') ? 'selected' : '' ?>>Available for Purchase</option>
                        <option value="unavailable" <?= ($product['availability'] === 'unavailable') ? 'selected' : '' ?>>Temporarily Unavailable / Paused</option>
                        <option value="expired" <?= ($product['availability'] === 'expired') ? 'selected' : '' ?>>Mark as Expired</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Farm Produce Photo</label>
                    <input type="file" name="product_image" class="form-control" accept="image/*" data-preview="editProdPreview">
                    <div id="editProdPreview" class="image-upload-preview" style="margin-top:10px;">
                        <img src="<?= e($product['image'] ?: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=400&q=80') ?>" alt="Current Image">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Cultivation Highlights & Notes</label>
                    <textarea name="description" class="form-control"><?= e($_POST['description'] ?? $product['description']) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width:100%; margin-top:10px;">
                    Save Listing Changes
                </button>
            </form>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
