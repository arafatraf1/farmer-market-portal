<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('buyer');
$user = current_user();
$db = get_db();

$stmtBuyer = $db->prepare("SELECT buyer_id FROM buyers WHERE user_id = ?");
$stmtBuyer->execute([$user['user_id']]);
$buyerId = (int)$stmtBuyer->fetchColumn();

// Fetch favorites
$stmtFav = $db->prepare("
    SELECT p.*, c.category_name, f.farm_name, f.farm_location, f.verification_status, f.farmer_rating, f.total_reviews
    FROM favorites fav
    JOIN products p ON fav.product_id = p.product_id
    JOIN categories c ON p.category_id = c.category_id
    JOIN farmers f ON p.farmer_id = f.farmer_id
    WHERE fav.buyer_id = ?
    ORDER BY fav.created_at DESC
");
$stmtFav->execute([$buyerId]);
$favorites = $stmtFav->fetchAll();

$pageTitle = 'Saved Favorites';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/buyer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Saved Favorite Produce</h1>
                <p>Quick access to your preferred seasonal crops and recurring farm orders</p>
            </div>
            <a href="<?= url('browse.php') ?>" class="btn btn-secondary btn-sm">Explore More Produce</a>
        </div>

        <?php if (empty($favorites)): ?>
            <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:60px 20px; text-align:center; color:var(--gray-500);">
                <div style="font-size:32px; margin-bottom:12px;">❤️</div>
                <h3>No favorites saved yet</h3>
                <p style="font-size:14px; margin-top:4px;">Browse products in the marketplace to bookmark your favourite farm goods.</p>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($favorites as $p): ?>
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

                            <div class="product-farmer-row">
                                <span style="font-weight:600;"><?= e($p['farm_name']) ?></span>
                                <span>•</span>
                                <span><?= e($p['farm_location']) ?></span>
                            </div>

                            <div class="product-pricing-row">
                                <div>
                                    <span class="product-price"><?= format_price($p['price']) ?></span>
                                    <span class="product-unit">/ <?= e($p['unit']) ?></span>
                                </div>
                                <div style="font-size:12.5px; color:var(--primary-700); font-weight:600;">
                                    <?= $p['quantity'] ?> <?= e($p['unit']) ?> left
                                </div>
                            </div>
                        </div>

                        <div class="product-card-footer">
                            <a href="<?= url('product-details.php?id=' . $p['product_id']) ?>" class="btn btn-secondary btn-sm" style="flex:1;">View Details</a>
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

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
