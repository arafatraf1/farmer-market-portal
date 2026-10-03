<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) {
    set_flash('error', 'Invalid product selected.');
    header('Location: ' . url('browse.php'));
    exit;
}

$db = get_db();

// Fetch product with category and farmer
$stmt = $db->prepare("
    SELECT p.*, c.category_name, c.slug as category_slug,
           f.farmer_id, f.farm_name, f.farm_location, f.verification_status, f.farmer_rating, f.total_reviews, f.bio,
           u.name as farmer_name, u.profile_image as farmer_avatar, u.phone as farmer_phone
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    JOIN farmers f ON p.farmer_id = f.farmer_id
    JOIN users u ON f.user_id = u.user_id
    WHERE p.product_id = ?
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product listing not found.');
    header('Location: ' . url('browse.php'));
    exit;
}

$freshness = get_freshness_status($product['expiry_date']);
$canOrder = ($product['quantity'] > 0 && !$freshness['is_expired'] && $product['availability'] === 'available');

// Fetch reviews for this farmer
$stmtReviews = $db->prepare("
    SELECT r.*, u.name as buyer_name, u.profile_image as buyer_avatar
    FROM reviews r
    JOIN buyers b ON r.buyer_id = b.buyer_id
    JOIN users u ON b.user_id = u.user_id
    WHERE r.farmer_id = ? AND r.status = 'active'
    ORDER BY r.created_at DESC
    LIMIT 6
");
$stmtReviews->execute([$product['farmer_id']]);
$farmerReviews = $stmtReviews->fetchAll();

$pageTitle = $product['product_name'] . ' — ' . $product['farm_name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 40px 20px 80px;">
    <!-- Breadcrumbs -->
    <div style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--gray-500); margin-bottom: 24px;">
        <a href="<?= url('index.php') ?>">Home</a>
        <span>/</span>
        <a href="<?= url('browse.php') ?>">Browse</a>
        <span>/</span>
        <a href="<?= url('browse.php?category=' . urlencode($product['category_slug'])) ?>"><?= e($product['category_name']) ?></a>
        <span>/</span>
        <span style="color:var(--gray-800); font-weight:600;"><?= e($product['product_name']) ?></span>
    </div>

    <!-- Product Showcase Grid -->
    <div style="display:grid; grid-template-columns: 1.1fr 1fr; gap: 40px; background:#ffffff; border:1px solid var(--border-color); border-radius: 20px; padding: 36px; box-shadow: var(--shadow-sm); margin-bottom: 50px;">
        
        <!-- Left: Product Image & Freshness Callout -->
        <div>
            <div style="width:100%; height:420px; border-radius:16px; overflow:hidden; background:var(--gray-100); border:1px solid var(--border-color); margin-bottom:16px;">
                <img src="<?= e($product['image'] ?: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=800&q=80') ?>" alt="<?= e($product['product_name']) ?>" style="width:100%; height:100%; object-fit:cover;">
            </div>

            <!-- Freshness Banner -->
            <?php if ($freshness['is_expired']): ?>
                <div class="alert alert-error" style="margin-bottom:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                    <div>
                        <strong>Product Shelf Life Expired</strong><br>
                        This batch exceeded its expiry date on <?= date('M d, Y', strtotime($product['expiry_date'])) ?> and cannot be purchased.
                    </div>
                </div>
            <?php elseif ($freshness['is_warning']): ?>
                <div class="alert alert-warning" style="margin-bottom:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <div>
                        <strong>Attention: Expiring Soon!</strong><br>
                        <?= e($freshness['label']) ?> (Harvested on <?= date('M d', strtotime($product['harvest_date'])) ?>). Order for immediate delivery.
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success" style="margin-bottom:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <div>
                        <strong>Guaranteed Fresh Harvest</strong><br>
                        <?= e($freshness['label']) ?>. Freshly packed by <?= e($product['farm_name']) ?>.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Details, Pricing, Actions -->
        <div style="display:flex; flex-direction:column;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <span class="status-badge badge-primary"><?= e($product['category_name']) ?></span>
                <span class="freshness-chip <?= e($freshness['class']) ?>"><?= e($freshness['label']) ?></span>
            </div>

            <h1 style="font-size: 30px; font-weight:800; color:var(--gray-900); margin-bottom:12px; line-height:1.25;">
                <?= e($product['product_name']) ?>
            </h1>

            <!-- Farmer Card Snippet -->
            <div style="background:var(--gray-50); border:1px solid var(--border-color); border-radius:12px; padding:16px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:14px;">
                    <img src="<?= e($product['farmer_avatar'] ?: 'https://images.unsplash.com/photo-1595273670150-bd0c3c392e46?auto=format&fit=crop&w=100&q=80') ?>" alt="Farmer" style="width:48px; height:48px; border-radius:50%; object-fit:cover; border:2px solid #ffffff;">
                    <div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <a href="<?= url('farmer-profile.php?id=' . $product['farmer_id']) ?>" style="font-weight:700; font-size:15px; color:var(--gray-900);">
                                <?= e($product['farm_name']) ?>
                            </a>
                            <?= get_verification_badge($product['verification_status']) ?>
                        </div>
                        <div style="font-size:12.5px; color:var(--gray-500); margin-top:2px;">
                            📍 <?= e($product['farm_location']) ?>
                        </div>
                    </div>
                </div>
                <div>
                    <?= render_stars((float)$product['farmer_rating'], (int)$product['total_reviews']) ?>
                </div>
            </div>

            <!-- Price & Availability -->
            <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:20px; padding-bottom:20px; border-bottom:1px solid var(--border-color);">
                <span style="font-size:36px; font-weight:800; color:var(--primary-800);"><?= format_price($product['price']) ?></span>
                <span style="font-size:16px; color:var(--gray-500); font-weight:600;">/ per <?= e($product['unit']) ?></span>
                
                <div style="margin-left:auto; text-align:right;">
                    <?php if ($canOrder): ?>
                        <span style="display:inline-flex; align-items:center; gap:6px; color:var(--primary-700); font-weight:700; font-size:14px;">
                            <span style="width:8px; height:8px; background:var(--primary-600); border-radius:50%;"></span>
                            <?= $product['quantity'] ?> <?= e($product['unit']) ?> Available
                        </span>
                    <?php else: ?>
                        <span style="display:inline-flex; align-items:center; gap:6px; color:var(--danger-500); font-weight:700; font-size:14px;">
                            <span style="width:8px; height:8px; background:var(--danger-500); border-radius:50%;"></span>
                            <?= $freshness['is_expired'] ? 'Expired (Cannot Order)' : 'Currently Out of Stock' ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <div style="margin-bottom:24px;">
                <h4 style="font-size:14px; text-transform:uppercase; letter-spacing:0.5px; color:var(--gray-400); margin-bottom:8px;">Harvest & Description</h4>
                <p style="font-size:14.5px; color:var(--gray-700); line-height:1.7;">
                    <?= nl2br(e($product['description'] ?: 'Pure farm-grown agricultural product cultivated with utmost care and fresh standards.')) ?>
                </p>
            </div>

            <!-- Metadata Specs -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; background:var(--gray-50); padding:16px; border-radius:12px; margin-bottom:28px; font-size:13px;">
                <div>
                    <span style="color:var(--gray-500);">Harvest / Production:</span>
                    <strong style="display:block; color:var(--gray-800); margin-top:2px;"><?= date('F d, Y', strtotime($product['harvest_date'])) ?></strong>
                </div>
                <div>
                    <span style="color:var(--gray-500);">Expiry / Best Before:</span>
                    <strong style="display:block; color:var(--gray-800); margin-top:2px;"><?= date('F d, Y', strtotime($product['expiry_date'])) ?></strong>
                </div>
            </div>

            <!-- Admin / Farmer Owner Quick Action Bar -->
            <?php if ($currentUser): ?>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <div style="background:#0f172a; color:#ffffff; padding:12px 16px; border-radius:10px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <span style="font-size:12.5px; font-weight:600;">👑 Admin Controls for this Produce:</span>
                        <div style="display:flex; gap:8px;">
                            <a href="<?= url('admin/pricing.php') ?>" class="btn btn-sm" style="background:#22c55e; color:#0f172a; font-weight:700; font-size:11.5px;">⚖️ Adjust Official Price</a>
                            <a href="<?= url('admin/products.php') ?>" class="btn btn-sm" style="background:#334155; color:#ffffff; font-size:11.5px;">🌾 Admin Moderation</a>
                        </div>
                    </div>
                <?php elseif ($currentUser['role'] === 'farmer' && (int)$currentUser['user_id'] === (int)$product['farmer_id']): ?>
                    <div style="background:#14532d; color:#ffffff; padding:12px 16px; border-radius:10px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <span style="font-size:12.5px; font-weight:600;">🌱 This is your farm's produce listing</span>
                        <a href="<?= url('farmer/product-edit.php?id=' . $product['product_id']) ?>" class="btn btn-sm" style="background:#22c55e; color:#052e16; font-weight:700; font-size:11.5px;">✏️ Edit Inventory / Dates</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Order / Cart Form -->
            <div style="margin-top:auto;">
                <?php if ($canOrder): ?>
                    <div style="display:flex; align-items:center; gap:16px; margin-bottom:14px;">
                        <label for="detailQty" style="font-weight:700; font-size:14px; color:var(--gray-700);">Quantity (<?= e($product['unit']) ?>):</label>
                        <div style="display:flex; align-items:center; border:1px solid var(--gray-300); border-radius:8px; overflow:hidden; width:130px;">
                            <button type="button" onclick="const q=document.getElementById('detailQty'); if(q.value>1)q.value--;" style="width:38px; height:38px; border:none; background:var(--gray-100); font-size:16px; cursor:pointer;">-</button>
                            <input type="number" id="detailQty" value="1" min="1" max="<?= $product['quantity'] ?>" style="width:54px; height:38px; border:none; text-align:center; font-weight:700; font-size:15px;" readonly>
                            <button type="button" onclick="const q=document.getElementById('detailQty'); if(q.value < <?= $product['quantity'] ?>)q.value++;" style="width:38px; height:38px; border:none; background:var(--gray-100); font-size:16px; cursor:pointer;">+</button>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;">
                        <button type="button" class="btn btn-primary btn-lg" onclick="addToCart(<?= $product['product_id'] ?>, parseInt(document.getElementById('detailQty').value));">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                            Add to Cart
                        </button>
                        <button type="button" class="btn btn-accent btn-lg" onclick="addToCart(<?= $product['product_id'] ?>, parseInt(document.getElementById('detailQty').value)).then(d => { if(d && d.success) window.location.href = '<?= url('checkout.php') ?>'; });">
                            Purchase Request &rarr;
                        </button>
                    </div>
                <?php else: ?>
                    <button class="btn btn-secondary btn-lg" style="width:100%;" disabled>
                        <?= $freshness['is_expired'] ? 'Cannot Order Expired Agricultural Produce' : 'Item Out of Stock' ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Verified Customer Reviews for this Farmer -->
    <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:20px; padding:36px; box-shadow:var(--shadow-sm);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px;">
            <div>
                <h3 style="font-size:22px; font-weight:800; color:var(--gray-900);">Farmer Reviews & Ratings</h3>
                <p style="color:var(--gray-500); font-size:14px;">Verified buyer feedback for <?= e($product['farm_name']) ?></p>
            </div>
            <div>
                <?= render_stars((float)$product['farmer_rating'], (int)$product['total_reviews']) ?>
            </div>
        </div>

        <?php if (empty($farmerReviews)): ?>
            <div style="text-align:center; padding:40px; color:var(--gray-500); background:var(--gray-50); border-radius:12px;">
                No reviews recorded yet for this farmer. Completed orders will appear here.
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
                <?php foreach ($farmerReviews as $rev): ?>
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
