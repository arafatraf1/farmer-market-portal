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

// Fetch all active master products configured by Admin
$masterProducts = $db->query("
    SELECT mp.*, c.category_name 
    FROM master_products mp
    JOIN categories c ON mp.category_id = c.category_id
    WHERE mp.status = 'active'
    ORDER BY c.category_name ASC, mp.product_name ASC
")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $masterProductId = (int)($_POST['master_product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $harvestDate = trim($_POST['harvest_date'] ?? '');
    $expiryDate = trim($_POST['expiry_date'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Server-side Master Product lookup (Source of Truth for Price & Unit)
    $stmtMaster = $db->prepare("
        SELECT mp.*, c.category_name 
        FROM master_products mp
        JOIN categories c ON mp.category_id = c.category_id
        WHERE mp.master_product_id = ? AND mp.status = 'active'
    ");
    $stmtMaster->execute([$masterProductId]);
    $masterItem = $stmtMaster->fetch();

    if (!$masterItem) {
        $errors[] = 'Please select a valid agricultural product from the official catalog.';
    }

    if ($quantity <= 0) {
        $errors[] = 'Available quantity must be greater than zero.';
    }

    if (empty($harvestDate) || empty($expiryDate)) {
        $errors[] = 'Both harvest date and expiry date are required for fresh produce.';
    } elseif (strtotime($expiryDate) < strtotime($harvestDate)) {
        $errors[] = 'Expiry date cannot be earlier than the harvest date.';
    }

    // Process image upload (fallback to master product catalog image)
    $imagePath = $masterItem['image'] ?: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=600&q=80';
    if (!empty($_FILES['product_image']['name'])) {
        $up = upload_image($_FILES['product_image'], 'products');
        if ($up['success']) {
            $imagePath = $up['filename'];
        } else {
            $errors[] = $up['error'];
        }
    }

    if (empty($errors) && $masterItem) {
        // SERVER-SIDE STRICT ENFORCEMENT: Price, Unit, Name, and Category come 100% from Master Product
        $officialPrice = (float)$masterItem['official_price'];
        $unit = $masterItem['unit'];
        $categoryId = (int)$masterItem['category_id'];
        $productName = $masterItem['product_name'];

        $fresh = get_freshness_status($expiryDate);
        $avail = ($quantity > 0 && !$fresh['is_expired']) ? 'available' : 'unavailable';
        if ($fresh['is_expired']) $avail = 'expired';

        $stmtInsert = $db->prepare("
            INSERT INTO products (farmer_id, category_id, master_product_id, product_name, description, price, unit, quantity, image, harvest_date, expiry_date, availability)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([
            $farmerId,
            $categoryId,
            $masterItem['master_product_id'],
            $productName,
            $description ?: $masterItem['description'],
            $officialPrice, // Server-enforced official price
            $unit,          // Server-enforced official unit
            $quantity,
            $imagePath,
            $harvestDate,
            $expiryDate,
            $avail
        ]);

        set_flash('success', "Your listing for '{$productName}' ({$quantity} {$unit} @ " . format_price($officialPrice) . "/{$unit}) has been published successfully.");
        header('Location: ' . url('farmer/products.php'));
        exit;
    }
}

$pageTitle = 'Add Produce Listing — Farmer Portal';
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
                <h1>List Fresh Agricultural Produce</h1>
                <p>Select produce from the official catalog to automatically inherit verified admin-controlled market pricing</p>
            </div>
        </div>

        <?php if ($farmer['verification_status'] !== 'verified'): ?>
            <div class="verification-banner pending" style="margin-bottom:24px;">
                <div>
                    <strong>Verification Reminder:</strong> Your farm profile is pending identity audit. While you can prepare and save product listings now, listings are prioritized for verified farmers once approved.
                </div>
            </div>
        <?php endif; ?>

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
            <form action="" method="POST" enctype="multipart/form-data">
                
                <!-- Product Selection from Official Master Catalog -->
                <div class="form-group">
                    <label class="form-label" for="masterProductSelect">Select Agricultural Product <span class="req">*</span></label>
                    <select id="masterProductSelect" name="master_product_id" class="form-control" required onchange="onProductSelect(this);">
                        <option value="">-- Choose from Official Product Catalog --</option>
                        <?php 
                        $currCat = '';
                        foreach ($masterProducts as $mp): 
                            if ($currCat !== $mp['category_name']):
                                if ($currCat !== '') echo '</optgroup>';
                                $currCat = $mp['category_name'];
                                echo '<optgroup label="' . e($currCat) . '">';
                            endif;
                        ?>
                            <option value="<?= $mp['master_product_id'] ?>" 
                                    data-price="<?= $mp['official_price'] ?>"
                                    data-price-fmt="<?= format_price($mp['official_price']) ?>"
                                    data-unit="<?= e($mp['unit']) ?>"
                                    data-category="<?= e($mp['category_name']) ?>"
                                    data-desc="<?= e($mp['description']) ?>"
                                    data-image="<?= e($mp['image']) ?>"
                                    <?= ((int)($_POST['master_product_id'] ?? 0) === (int)$mp['master_product_id']) ? 'selected' : '' ?>>
                                <?= e($mp['product_name']) ?> (Official: <?= format_price($mp['official_price']) ?> / <?= e($mp['unit']) ?>)
                            </option>
                        <?php endforeach; 
                        if ($currCat !== '') echo '</optgroup>';
                        ?>
                    </select>
                    <div class="form-hint">Prices are officially regulated across all farmers to ensure fair market competition.</div>
                </div>

                <!-- Locked Official Price Callout -->
                <div id="priceInfoBox" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:18px 20px; margin-bottom:24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                        <div>
                            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#15803d; margin-bottom:2px;">
                                🔒 Official Admin-Controlled Price
                            </div>
                            <div style="font-size:24px; font-weight:800; color:#14532d;">
                                <span id="dispPrice">৳0.00</span> <span style="font-size:15px; font-weight:600; color:#166534;" id="dispUnit">/ kg</span>
                            </div>
                        </div>
                        <div>
                            <span class="status-badge badge-primary" id="dispCategory" style="font-size:13px; padding:6px 12px;">Vegetables</span>
                        </div>
                    </div>
                    <div style="font-size:12px; color:#166534; margin-top:8px;">
                        ✓ This official rate applies equally to every farmer selling this product on Farmer Market Portal.
                    </div>
                </div>

                <!-- Stock Quantity -->
                <div class="form-group">
                    <label class="form-label" for="quantityInput">Available Stock Quantity <span class="req">*</span></label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <input type="number" id="quantityInput" min="1" name="quantity" class="form-control" placeholder="e.g. 50" value="<?= e($_POST['quantity'] ?? '20') ?>" required style="max-width:240px;">
                        <span id="quantityUnitLabel" style="font-weight:700; color:var(--gray-700); font-size:15px;">units</span>
                    </div>
                    <div class="form-hint">Specify how much quantity your farm can deliver right now.</div>
                </div>

                <!-- Perishable Freshness & Dates Section -->
                <div style="background:var(--primary-50); border:1px solid var(--primary-200); border-radius:12px; padding:20px; margin-bottom:20px;">
                    <div style="font-weight:700; font-size:14px; color:var(--primary-900); margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                        <span>🌿 Freshness & Expiry Guard</span>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Harvest / Production Date <span class="req">*</span></label>
                            <input type="date" name="harvest_date" id="harvestDate" class="form-control" value="<?= e($_POST['harvest_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Expiry / Best Before Date <span class="req">*</span></label>
                            <input type="date" name="expiry_date" id="expiryDate" class="form-control" value="<?= e($_POST['expiry_date'] ?? date('Y-m-d', strtotime('+7 days'))) ?>" min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div id="freshnessNotice" style="margin-top:12px; font-size:12.5px; font-weight:600; color:var(--primary-800);"></div>
                </div>

                <!-- Product Photo -->
                <div class="form-group">
                    <label class="form-label">Farm Produce Photo (Optional — Overrides default catalog image)</label>
                    <input type="file" name="product_image" class="form-control" accept="image/*" data-preview="prodPreview">
                    <div class="form-hint">Upload genuine photos from your farm fields or harvest baskets.</div>
                    <div id="prodPreview" class="image-upload-preview" style="margin-top:10px;">
                        <span style="font-size:12px; color:var(--gray-400);">Optional Photo Preview</span>
                    </div>
                </div>

                <!-- Farm Specific Notes -->
                <div class="form-group">
                    <label class="form-label">Cultivation Highlights & Notes</label>
                    <textarea name="description" class="form-control" placeholder="Share organic practices, soil care, pesticide-free verification, special taste highlights..."><?= e($_POST['description'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width:100%; margin-top:10px;">
                    Publish Farm Produce Listing
                </button>
            </form>
        </div>

    </main>
</div>

<script>
function onProductSelect(select) {
    const opt = select.options[select.selectedIndex];
    const box = document.getElementById('priceInfoBox');
    const dispPrice = document.getElementById('dispPrice');
    const dispUnit = document.getElementById('dispUnit');
    const dispCategory = document.getElementById('dispCategory');
    const quantityUnitLabel = document.getElementById('quantityUnitLabel');

    if (opt && opt.value) {
        box.style.display = 'block';
        dispPrice.textContent = opt.getAttribute('data-price-fmt');
        dispUnit.textContent = '/ ' + opt.getAttribute('data-unit');
        dispCategory.textContent = opt.getAttribute('data-category');
        quantityUnitLabel.textContent = opt.getAttribute('data-unit');
    } else {
        box.style.display = 'none';
        quantityUnitLabel.textContent = 'units';
    }
}

function checkFreshness() {
    const exp = document.getElementById('expiryDate').value;
    const notice = document.getElementById('freshnessNotice');
    if (!exp) return;
    const today = new Date();
    today.setHours(0,0,0,0);
    const expDate = new Date(exp);
    const diffTime = expDate - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
        notice.innerHTML = '<span style="color:var(--danger-500);">⚠️ Expiry date is in the past. Product will be marked expired!</span>';
    } else if (diffDays <= 3) {
        notice.innerHTML = `<span style="color:var(--accent-amber-dark);">⚠️ Expiring Soon warning will be shown (${diffDays} days shelf life left).</span>`;
    } else {
        notice.innerHTML = `<span style="color:var(--primary-700);">✓ Fresh produce (${diffDays} days shelf life).</span>`;
    }
}

document.getElementById('expiryDate').addEventListener('change', checkFreshness);
document.addEventListener('DOMContentLoaded', () => {
    checkFreshness();
    const sel = document.getElementById('masterProductSelect');
    if (sel && sel.value) {
        onProductSelect(sel);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
