<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$pageTitle = 'Direct Agricultural Marketplace — Fresh Produce';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

// Fetch categories
$categories = $db->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

// Fetch featured fresh products (non-expired, available)
$stmtFeatured = $db->query("
    SELECT p.*, c.category_name, f.farm_name, f.farm_location, f.verification_status, f.farmer_rating, f.total_reviews
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    JOIN farmers f ON p.farmer_id = f.farmer_id
    WHERE p.availability = 'available' AND p.quantity > 0 AND p.expiry_date >= CURDATE()
    ORDER BY p.created_at DESC
    LIMIT 8
");
$featuredProducts = $stmtFeatured->fetchAll();

// Fetch verified farmers
$stmtFarmers = $db->query("
    SELECT f.*, u.name as farmer_name, u.profile_image, COUNT(p.product_id) as total_products
    FROM farmers f
    JOIN users u ON f.user_id = u.user_id
    LEFT JOIN products p ON f.farmer_id = p.farmer_id AND p.availability = 'available'
    WHERE f.verification_status = 'verified'
    GROUP BY f.farmer_id
    ORDER BY f.farmer_rating DESC
    LIMIT 4
");
$topFarmers = $stmtFarmers->fetchAll();

// Fetch real recent customer reviews
$stmtReviews = $db->query("
    SELECT r.*, u.name as buyer_name, u.profile_image as buyer_image, f.farm_name, p.product_name
    FROM reviews r
    JOIN buyers b ON r.buyer_id = b.buyer_id
    JOIN users u ON b.user_id = u.user_id
    JOIN farmers f ON r.farmer_id = f.farmer_id
    LEFT JOIN products p ON r.product_id = p.product_id
    WHERE r.status = 'active'
    ORDER BY r.created_at DESC
    LIMIT 3
");
$customerReviews = $stmtReviews->fetchAll();
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container hero-grid">
        <div>
            <div class="hero-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Direct Farmer-to-Consumer Agricultural Platform</span>
            </div>
            <h1 class="hero-title">
                Fresh From Farmers, <br><span>Directly to You.</span>
            </h1>
            <p class="hero-subtitle">
                Purchase farm-fresh vegetables, organic seasonal fruits, unadulterated dairy, and sustainably harvested meats & fish directly from verified local growers.
            </p>
            <div class="hero-cta">
                <a href="<?= url('browse.php') ?>" class="btn btn-accent btn-lg">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Browse Produce
                </a>
                <a href="<?= url('register.php?role=farmer') ?>" class="btn btn-outline btn-lg" style="color:#ffffff; border-color:rgba(255,255,255,0.4);">
                    Sell as a Farmer
                </a>
            </div>

            <div class="hero-trust-bar">
                <div class="hero-stat">
                    <h4>100%</h4>
                    <p>Verified Farmers</p>
                </div>
                <div class="hero-stat">
                    <h4>0</h4>
                    <p>Middlemen Markups</p>
                </div>
                <div class="hero-stat">
                    <h4>24-48h</h4>
                    <p>Harvest to Home</p>
                </div>
            </div>
        </div>

        <div class="hero-visual">
            <img src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=800&q=80" alt="Fresh Harvest" class="hero-card-img">
            <div class="floating-glass-card">
                <div class="icon-box">🌱</div>
                <div>
                    <div style="font-weight:700; font-size:14.5px;">Morning Harvest Dispatched</div>
                    <div style="font-size:12px; color:var(--gray-500);">Direct from Savar & Bogura Farms</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section style="padding: 60px 0 40px;">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title">Explore Agricultural Categories</h2>
                <p class="section-subtitle">Wholesome and pure farm goods categorized for effortless browsing</p>
            </div>
            <a href="<?= url('browse.php') ?>" class="btn btn-secondary btn-sm">All Categories &rarr;</a>
        </div>

        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="<?= url('browse.php?category=' . urlencode($cat['slug'])) ?>" class="category-card">
                    <div class="cat-icon">
                        <?php 
                        // Visual icon rendering
                        switch($cat['slug']) {
                            case 'vegetables': echo '🥦'; break;
                            case 'fruits': echo '🍎'; break;
                            case 'meat': echo '🥩'; break;
                            case 'eggs': echo '🥚'; break;
                            case 'fish': echo '🐟'; break;
                            case 'dairy': echo '🥛'; break;
                            case 'grains': echo '🌾'; break;
                            default: echo '🌱'; break;
                        }
                        ?>
                    </div>
                    <h3><?= e($cat['category_name']) ?></h3>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Fresh Produce -->
<section style="padding: 20px 0 60px;">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title">Fresh Harvest Listings</h2>
                <p class="section-subtitle">Real-time stock straight from verified farms with active freshness dates</p>
            </div>
            <a href="<?= url('browse.php') ?>" class="btn btn-secondary btn-sm">View All Produce &rarr;</a>
        </div>

        <div class="product-grid">
            <?php foreach ($featuredProducts as $product): ?>
                <?php 
                $freshness = get_freshness_status($product['expiry_date']); 
                $isAvailable = ($product['quantity'] > 0 && !$freshness['is_expired']);
                ?>
                <div class="product-card">
                    <div class="product-thumb-wrap">
                        <img src="<?= e($product['image'] ?: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=400&q=80') ?>" alt="<?= e($product['product_name']) ?>" class="product-thumb" loading="lazy">
                        <div class="product-badges">
                            <span class="freshness-chip <?= e($freshness['class']) ?>">
                                <?= e($freshness['label']) ?>
                            </span>
                            <?php if ($product['verification_status'] === 'verified'): ?>
                                <span class="verified-badge">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    Verified Farmer
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="product-body">
                        <div class="product-category"><?= e($product['category_name']) ?></div>
                        <a href="<?= url('product-details.php?id=' . $product['product_id']) ?>" class="product-title">
                            <?= e($product['product_name']) ?>
                        </a>

                        <div class="product-farmer-row">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            <a href="<?= url('farmer-profile.php?id=' . $product['farmer_id']) ?>" class="product-farmer-name">
                                <?= e($product['farm_name']) ?>
                            </a>
                            <span>•</span>
                            <span><?= e($product['farm_location']) ?></span>
                        </div>

                        <div style="margin-bottom:10px;">
                            <?= render_stars((float)$product['farmer_rating'], (int)$product['total_reviews']) ?>
                        </div>

                        <div class="product-pricing-row">
                            <div>
                                <span class="product-price"><?= format_price($product['price']) ?></span>
                                <span class="product-unit">/ <?= e($product['unit']) ?></span>
                            </div>
                            <div style="font-size:12.5px; color: <?= $product['quantity'] > 0 ? 'var(--primary-700)' : 'var(--danger-500)' ?>; font-weight:600;">
                                <?= $product['quantity'] > 0 ? $product['quantity'] . ' ' . e($product['unit']) . ' in stock' : 'Out of Stock' ?>
                            </div>
                        </div>
                    </div>

                    <div class="product-card-footer">
                        <a href="<?= url('product-details.php?id=' . $product['product_id']) ?>" class="btn btn-secondary btn-sm" style="flex:1;">Details</a>
                        <?php if ($isAvailable): ?>
                            <button type="button" class="btn btn-primary btn-sm btn-add-to-cart" data-product-id="<?= $product['product_id'] ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                                Add to Cart
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-sm" disabled>Unavailable</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Verified Farmers Spotlight -->
<section style="background: #ffffff; padding: 60px 0; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title">Meet Our Verified Farmers</h2>
                <p class="section-subtitle">Local growers whose identities and agricultural practices are rigorously checked</p>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
            <?php foreach ($topFarmers as $farmer): ?>
                <div style="background:var(--gray-50); border:1px solid var(--border-color); border-radius:16px; padding:24px; display:flex; flex-direction:column;">
                    <div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">
                        <img src="<?= e($farmer['profile_image'] ?: 'https://images.unsplash.com/photo-1595273670150-bd0c3c392e46?auto=format&fit=crop&w=150&q=80') ?>" alt="Farmer" style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:3px solid #ffffff; box-shadow:var(--shadow-sm);">
                        <div>
                            <h3 style="font-size:17px; margin-bottom:4px;"><?= e($farmer['farm_name']) ?></h3>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <?= get_verification_badge($farmer['verification_status']) ?>
                                <span style="font-size:12px; color:var(--gray-500);"><?= e($farmer['farm_location']) ?></span>
                            </div>
                        </div>
                    </div>

                    <p style="font-size:13.5px; color:var(--gray-600); line-height:1.6; margin-bottom:20px; flex:1;">
                        <?= e($farmer['bio'] ?: 'Dedicated to quality farming and direct delivery to households.') ?>
                    </p>

                    <div style="display:flex; align-items:center; justify-content:space-between; padding-top:16px; border-top:1px solid var(--gray-200);">
                        <div>
                            <?= render_stars((float)$farmer['farmer_rating'], (int)$farmer['total_reviews']) ?>
                        </div>
                        <a href="<?= url('farmer-profile.php?id=' . $farmer['farmer_id']) ?>" class="btn btn-outline btn-sm">
                            View Farm &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section style="padding: 70px 0;">
    <div class="container">
        <div style="text-align:center; max-width:650px; margin:0 auto 48px;">
            <h2 class="section-title" style="font-size:32px;">How Farmer Market Portal Works</h2>
            <p class="section-subtitle">A transparent, reliable 4-step pipeline designed for fairness and freshness</p>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 28px;">
            <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:28px; text-align:center;">
                <div style="width:56px; height:56px; background:#dcfce7; color:#15803d; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:22px; font-weight:800;">1</div>
                <h3 style="font-size:17px; margin-bottom:8px;">1. Browse & Select</h3>
                <p style="font-size:13.5px; color:var(--gray-600); line-height:1.6;">Search fresh farm produce, examine harvest dates, check days until expiry, and inspect farmer ratings.</p>
            </div>

            <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:28px; text-align:center;">
                <div style="width:56px; height:56px; background:#fef3c7; color:#b45309; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:22px; font-weight:800;">2</div>
                <h3 style="font-size:17px; margin-bottom:8px;">2. Place Purchase Request</h3>
                <p style="font-size:13.5px; color:var(--gray-600); line-height:1.6;">Send order requests with delivery dates. Farmers review your buyer trust score and accept immediately.</p>
            </div>

            <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:28px; text-align:center;">
                <div style="width:56px; height:56px; background:#dbeafe; color:#1d4ed8; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:22px; font-weight:800;">3</div>
                <h3 style="font-size:17px; margin-bottom:8px;">3. Milestone Tracking</h3>
                <p style="font-size:13.5px; color:var(--gray-600); line-height:1.6;">Watch your order progress live: Placed &rarr; Preparing &rarr; Ready &rarr; Out for Delivery &rarr; Delivered.</p>
            </div>

            <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:28px; text-align:center;">
                <div style="width:56px; height:56px; background:#f3e8ff; color:#7e22ce; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:22px; font-weight:800;">4</div>
                <h3 style="font-size:17px; margin-bottom:8px;">4. Confirm & Review</h3>
                <p style="font-size:13.5px; color:var(--gray-600); line-height:1.6;">Confirm receipt and submit genuine 1-5 star ratings and reviews that reward hardworking verified farmers.</p>
            </div>
        </div>
    </div>
</section>

<!-- Customer Reviews Section -->
<?php if (!empty($customerReviews)): ?>
<section style="background:var(--primary-50); padding:60px 0; border-top:1px solid var(--primary-200);">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title">Verified Buyer Feedback</h2>
                <p class="section-subtitle">Real reviews from buyers who completed actual purchases</p>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:24px;">
            <?php foreach ($customerReviews as $rev): ?>
                <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:24px; box-shadow:var(--shadow-sm);">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                        <div><?= render_stars((float)$rev['rating']) ?></div>
                        <span style="font-size:12px; color:var(--gray-400);"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                    </div>
                    <p style="font-size:14px; color:var(--gray-700); line-height:1.6; margin-bottom:16px; font-style:italic;">
                        "<?= e($rev['review_text']) ?>"
                    </p>
                    <div style="display:flex; align-items:center; gap:12px; padding-top:12px; border-top:1px solid var(--gray-100);">
                        <img src="<?= e($rev['buyer_image'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80') ?>" alt="Buyer" style="width:36px; height:36px; border-radius:50%; object-fit:cover;">
                        <div>
                            <div style="font-weight:700; font-size:13.5px;"><?= e($rev['buyer_name']) ?></div>
                            <div style="font-size:12px; color:var(--primary-700);">Bought from <?= e($rev['farm_name']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
