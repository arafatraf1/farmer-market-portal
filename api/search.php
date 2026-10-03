<?php
/**
 * API: Live Product Search & Filter
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

try {
    $db = get_db();

    $q = trim($_GET['q'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $maxPrice = !empty($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
    $minRating = !empty($_GET['rating']) ? (float)$_GET['rating'] : 0;
    $verifiedOnly = !empty($_GET['verified_only']);
    $freshness = trim($_GET['freshness'] ?? 'all');
    $location = trim($_GET['location'] ?? '');
    $sort = trim($_GET['sort'] ?? 'newest');

    $sql = "SELECT p.*, c.category_name, c.slug as category_slug, 
                   f.farm_name, f.farm_location, f.verification_status, f.farmer_rating, f.total_reviews,
                   u.name as farmer_name
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            JOIN farmers f ON p.farmer_id = f.farmer_id
            JOIN users u ON f.user_id = u.user_id
            WHERE p.availability != 'expired' AND p.expiry_date >= CURDATE()";

    $params = [];

    // Search keyword
    if ($q !== '') {
        $sql .= " AND (p.product_name LIKE :q1 OR p.description LIKE :q2 OR c.category_name LIKE :q3 OR f.farm_name LIKE :q4 OR u.name LIKE :q5 OR f.farm_location LIKE :q6)";
        $searchTerm = '%' . $q . '%';
        $params[':q1'] = $searchTerm;
        $params[':q2'] = $searchTerm;
        $params[':q3'] = $searchTerm;
        $params[':q4'] = $searchTerm;
        $params[':q5'] = $searchTerm;
        $params[':q6'] = $searchTerm;
    }

    // Category
    if ($category !== '') {
        $sql .= " AND (c.slug = :category OR c.category_id = :cat_id)";
        $params[':category'] = $category;
        $params[':cat_id'] = (int)$category;
    }

    // Price
    if ($maxPrice > 0) {
        $sql .= " AND p.price <= :max_price";
        $params[':max_price'] = $maxPrice;
    }

    // Farmer Rating
    if ($minRating > 0) {
        $sql .= " AND f.farmer_rating >= :min_rating";
        $params[':min_rating'] = $minRating;
    }

    // Verified Only
    if ($verifiedOnly) {
        $sql .= " AND f.verification_status = 'verified'";
    }

    // Location
    if ($location !== '') {
        $sql .= " AND f.farm_location LIKE :loc";
        $params[':loc'] = '%' . $location . '%';
    }

    // Freshness Filter
    if ($freshness === 'fresh') {
        $sql .= " AND DATEDIFF(p.expiry_date, CURDATE()) >= 4";
    } elseif ($freshness === 'expiring_soon') {
        $sql .= " AND DATEDIFF(p.expiry_date, CURDATE()) BETWEEN 0 AND 3";
    } elseif ($freshness === 'in_stock') {
        $sql .= " AND p.quantity > 0";
    }

    // Sorting
    $sortMap = [
        'price_asc' => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'rating_desc' => 'f.farmer_rating DESC, f.total_reviews DESC',
        'expiring_soon' => 'p.expiry_date ASC',
        'newest' => 'p.created_at DESC'
    ];
    $sql .= " ORDER BY " . ($sortMap[$sort] ?? 'p.created_at DESC');

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Render HTML cards
    ob_start();
    if (empty($products)) {
        ?>
        <div style="grid-column: 1/-1; text-align:center; padding: 60px 20px; background:#fff; border-radius:16px; border:1px solid var(--border-color);">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px;">
                <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <h3 style="font-size:18px; color:var(--gray-800); margin-bottom:6px;">No products match your criteria</h3>
            <p style="color:var(--gray-500); font-size:14px;">Try loosening your filters, adjusting the price range, or searching for a different keyword.</p>
        </div>
        <?php
    } else {
        foreach ($products as $product) {
            $freshnessStatus = get_freshness_status($product['expiry_date']);
            $isAvailable = ($product['quantity'] > 0 && !$freshnessStatus['is_expired']);
            ?>
            <div class="product-card">
                <div class="product-thumb-wrap">
                    <img src="<?= e($product['image'] ?: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=400&q=80') ?>" alt="<?= e($product['product_name']) ?>" class="product-thumb" loading="lazy">
                    <div class="product-badges">
                        <span class="freshness-chip <?= e($freshnessStatus['class']) ?>">
                            <?= e($freshnessStatus['label']) ?>
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
            <?php
        }
    }
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'count' => count($products),
        'html' => $html
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
