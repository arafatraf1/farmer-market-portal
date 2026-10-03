<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$farmerId = (int)($_GET['id'] ?? 0);
if ($farmerId <= 0) {
    set_flash('error', 'Invalid farmer specified.');
    header('Location: ' . url('browse.php'));
    exit;
}

$db = get_db();

// Fetch farmer profile
$stmt = $db->prepare("
    SELECT f.*, u.name as farmer_name, u.profile_image, u.phone, u.email, u.created_at as joined_date
    FROM farmers f
    JOIN users u ON f.user_id = u.user_id
    WHERE f.farmer_id = ?
");
$stmt->execute([$farmerId]);
$farmer = $stmt->fetch();

if (!$farmer) {
    set_flash('error', 'Farmer profile not found.');
    header('Location: ' . url('browse.php'));
    exit;
}

// Fetch active products
$stmtProducts = $db->prepare("
    SELECT p.*, c.category_name 
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    WHERE p.farmer_id = ? AND p.availability = 'available' AND p.expiry_date >= CURDATE()
    ORDER BY p.created_at DESC
");
$stmtProducts->execute([$farmerId]);
$products = $stmtProducts->fetchAll();

// Fetch reviews
$stmtReviews = $db->prepare("
    SELECT r.*, u.name as buyer_name, u.profile_image as buyer_avatar
    FROM reviews r
    JOIN buyers b ON r.buyer_id = b.buyer_id
    JOIN users u ON b.user_id = u.user_id
    WHERE r.farmer_id = ? AND r.status = 'active'
    ORDER BY r.created_at DESC
");
$stmtReviews->execute([$farmerId]);
$reviews = $stmtReviews->fetchAll();

$pageTitle = $farmer['farm_name'] . ' — Verified Farmer Profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 40px 20px 80px;">
    <!-- Profile Hero Card -->
    <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:20px; padding:36px; box-shadow:var(--shadow-sm); margin-bottom:40px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:24px;">
        <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
            <img src="<?= e($farmer['profile_image'] ?: 'https://images.unsplash.com/photo-1595273670150-bd0c3c392e46?auto=format&fit=crop&w=200&q=80') ?>" alt="Farmer" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:4px solid var(--primary-100); box-shadow:var(--shadow-md);">
            <div>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
                    <h1 style="font-size:28px; font-weight:800; color:var(--gray-900);"><?= e($farmer['farm_name']) ?></h1>
                    <?= get_verification_badge($farmer['verification_status']) ?>
                </div>
                <div style="color:var(--gray-600); font-size:14.5px; margin-bottom:8px;">
                    Operated by <strong><?= e($farmer['farmer_name']) ?></strong> • 📍 <?= e($farmer['farm_location']) ?>
                </div>
                <div style="display:flex; align-items:center; gap:16px;">
                    <?= render_stars((float)$farmer['farmer_rating'], (int)$farmer['total_reviews']) ?>
                    <span style="color:var(--gray-400);">•</span>
                    <span style="font-size:13px; color:var(--gray-500);">Member since <?= date('M Y', strtotime($farmer['joined_date'])) ?></span>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:20px; background:var(--gray-50); padding:16px 24px; border-radius:14px; border:1px solid var(--gray-200);">
            <div style="text-align:center;">
                <div style="font-size:22px; font-weight:800; color:var(--primary-800);"><?= count($products) ?></div>
                <div style="font-size:12px; color:var(--gray-500);">Active Produce</div>
            </div>
            <div style="width:1px; background:var(--gray-200);"></div>
            <div style="text-align:center;">
                <div style="font-size:22px; font-weight:800; color:var(--primary-800);"><?= (int)$farmer['total_reviews'] ?></div>
                <div style="font-size:12px; color:var(--gray-500);">Verified Reviews</div>
            </div>
        </div>
    </div>

    <!-- Farm Bio -->
    <?php if ($farmer['bio']): ?>
    <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:24px; margin-bottom:40px;">
        <h3 style="font-size:16px; font-weight:700; color:var(--gray-900); margin-bottom:8px;">About the Farm & Practices</h3>
        <p style="font-size:14.5px; color:var(--gray-700); line-height:1.7;">
            <?= nl2br(e($farmer['bio'])) ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Farmer Products Grid -->
    <div style="margin-bottom:50px;">
        <div class="section-header">
            <div>
                <h2 class="section-title">Available Produce from <?= e($farmer['farm_name']) ?></h2>
                <p class="section-subtitle">Freshly stocked and available for direct order</p>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <div style="background:#ffffff; padding:50px; text-align:center; border-radius:16px; border:1px solid var(--border-color); color:var(--gray-500);">
                This farmer has no active product batches listed at this moment. Please check back soon.
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $p): ?>
                    <?php 
                    $freshness = get_freshness_status($p['expiry_date']);
                    $isAvailable = ($p['quantity'] > 0 && !$freshness['is_expired']);
                    ?>
                    <div class="product-card">
                        <div class="product-thumb-wrap">
                            <img src="<?= e($p['image'] ?: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=400&q=80') ?>" alt="<?= e($p['product_name']) ?>" class="product-thumb">
                            <div class="product-badges">
                                <span class="freshness-chip <?= e($freshness['class']) ?>"><?= e($freshness['label']) ?></span>
                            </div>
                        </div>

                        <div class="product-body">
                            <div class="product-category"><?= e($p['category_name']) ?></div>
                            <a href="<?= url('product-details.php?id=' . $p['product_id']) ?>" class="product-title"><?= e($p['product_name']) ?></a>

                            <div class="product-pricing-row">
                                <div>
                                    <span class="product-price"><?= format_price($p['price']) ?></span>
                                    <span class="product-unit">/ <?= e($p['unit']) ?></span>
                                </div>
                                <div style="font-size:12.5px; color: <?= $p['quantity'] > 0 ? 'var(--primary-700)' : 'var(--danger-500)' ?>; font-weight:600;">
                                    <?= $p['quantity'] > 0 ? $p['quantity'] . ' ' . e($p['unit']) . ' in stock' : 'Out of Stock' ?>
                                </div>
                            </div>
                        </div>

                        <div class="product-card-footer">
                            <a href="<?= url('product-details.php?id=' . $p['product_id']) ?>" class="btn btn-secondary btn-sm" style="flex:1;">Details</a>
                            <?php if ($isAvailable): ?>
                                <button type="button" class="btn btn-primary btn-sm btn-add-to-cart" data-product-id="<?= $p['product_id'] ?>">
                                    Add to Cart
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Verified Reviews -->
    <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:20px; padding:36px; box-shadow:var(--shadow-sm);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px;">
            <div>
                <h3 style="font-size:22px; font-weight:800; color:var(--gray-900);">Verified Customer Ratings</h3>
                <p style="color:var(--gray-500); font-size:14px;">Genuine feedback from verified completed purchases</p>
            </div>
            <div>
                <?= render_stars((float)$farmer['farmer_rating'], (int)$farmer['total_reviews']) ?>
            </div>
        </div>

        <?php if (empty($reviews)): ?>
            <div style="text-align:center; padding:40px; color:var(--gray-500); background:var(--gray-50); border-radius:12px;">
                No reviews yet for this farmer.
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
                <?php foreach ($reviews as $rev): ?>
                    <div style="background:var(--gray-50); border:1px solid var(--border-color); border-radius:14px; padding:20px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <img src="<?= e($rev['buyer_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80') ?>" alt="Buyer" style="width:34px; height:34px; border-radius:50%; object-fit:cover;">
                                <div>
                                    <div style="font-weight:700; font-size:13.5px;"><?= e($rev['buyer_name']) ?></div>
                                    <div style="font-size:11px; color:var(--primary-700); font-weight:600;">Verified Purchase</div>
                                </div>
                            </div>
                            <div><?= render_stars((float)$rev['rating']) ?></div>
                        </div>
                        <p style="font-size:13.5px; color:var(--gray-700); line-height:1.6;">
                            "<?= e($rev['review_text']) ?>"
                        </p>
                        <div style="font-size:11.5px; color:var(--gray-400); margin-top:10px;">
                            <?= date('M d, Y', strtotime($rev['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
