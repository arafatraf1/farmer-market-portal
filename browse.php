<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$pageTitle = 'Browse Farm Produce & Verified Farmers';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

// Fetch categories with count of available products
$categories = $db->query("
    SELECT c.*, COUNT(p.product_id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id AND p.availability != 'expired' AND p.expiry_date >= CURDATE()
    GROUP BY c.category_id
    ORDER BY c.category_name ASC
")->fetchAll();

$selectedCategory = trim($_GET['category'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');
$maxPriceParam = (int)($_GET['max_price'] ?? 50);
?>

<div class="container" style="padding: 40px 20px 80px;">
    <!-- Page Header -->
    <div style="margin-bottom: 30px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="font-size: 32px; font-weight: 800; color: var(--gray-900);">Marketplace Catalog</h1>
            <p style="color: var(--gray-500); font-size: 15px;">Explore fresh produce from verified regional farmers</p>
        </div>
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                <a href="<?= url('admin/pricing.php') ?>" class="btn btn-primary btn-sm">⚖️ Product & Price Management</a>
            <?php elseif ($currentUser && $currentUser['role'] === 'farmer'): ?>
                <a href="<?= url('farmer/product-add.php') ?>" class="btn btn-primary btn-sm">+ Add Farm Produce</a>
            <?php endif; ?>

            <div style="display:flex; align-items:center; gap:8px;">
                <label for="sortSelect" style="font-size:14px; font-weight:600; color:var(--gray-600);">Sort By:</label>
                <select id="sortSelect" class="form-control" style="width: auto; padding: 8px 14px; font-size:13.5px;">
                    <option value="newest">Newest Harvest</option>
                    <option value="price_asc">Price: Low to High</option>
                    <option value="price_desc">Price: High to Low</option>
                    <option value="rating_desc">Highest Rated Farmer</option>
                    <option value="expiring_soon">Expiring Soon (Quick Fresh)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Main 2-Column Layout: Sidebar Filters + Products Grid -->
    <div style="display: grid; grid-template-columns: 280px 1fr; gap: 32px; align-items: flex-start;">
        
        <!-- Filters Sidebar -->
        <aside style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; box-shadow: var(--shadow-sm); position:sticky; top:94px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid var(--border-color);">
                <h3 style="font-size: 17px; font-weight: 700; color: var(--gray-900);">Filters</h3>
                <button type="button" id="resetFiltersBtn" class="btn btn-secondary btn-sm" style="padding:4px 10px; font-size:12px;">Reset All</button>
            </div>

            <!-- Keyword Search -->
            <div class="form-group">
                <label class="form-label">Search Keyword</label>
                <input type="text" id="searchInput" class="form-control" placeholder="Tomatoes, Savar, Milk..." value="<?= e($searchQuery) ?>">
            </div>

            <!-- Categories -->
            <div class="form-group">
                <label class="form-label">Category</label>
                <div style="display:flex; flex-direction:column; gap:8px; max-height: 200px; overflow-y: auto; padding-right: 4px;">
                    <label style="display:flex; align-items:center; justify-content:space-between; font-size:13.5px; cursor:pointer;">
                        <span style="display:flex; align-items:center; gap:8px;">
                            <input type="radio" name="filter_category" class="filter-trigger" value="" <?= $selectedCategory === '' ? 'checked' : '' ?>>
                            All Categories
                        </span>
                    </label>
                    <?php foreach ($categories as $cat): ?>
                        <label style="display:flex; align-items:center; justify-content:space-between; font-size:13.5px; cursor:pointer;">
                            <span style="display:flex; align-items:center; gap:8px;">
                                <input type="radio" name="filter_category" class="filter-trigger" value="<?= e($cat['slug']) ?>" <?= $selectedCategory === $cat['slug'] ? 'checked' : '' ?>>
                                <?= e($cat['category_name']) ?>
                            </span>
                            <span style="font-size:11.5px; color:var(--gray-400);">(<?= $cat['product_count'] ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Price Slider -->
            <div class="form-group" style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border-color);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label class="form-label" style="margin-bottom:0;">Max Price</label>
                    <span id="priceMaxDisplay" style="font-weight:700; color:var(--primary-800); font-size:14px;">৳1500</span>
                </div>
                <input type="range" id="priceMax" min="10" max="1500" value="1500" step="10" style="width:100%; accent-color:var(--primary-600);">
                <div style="display:flex; justify-content:space-between; font-size:11.5px; color:var(--gray-400); margin-top:4px;">
                    <span>৳10</span>
                    <span>৳1500+</span>
                </div>
            </div>

            <!-- Verified Farmers Checkbox -->
            <div class="form-group" style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border-color);">
                <label style="display:flex; align-items:center; gap:10px; font-size:13.5px; font-weight:600; cursor:pointer;">
                    <input type="checkbox" id="filterVerifiedOnly" class="filter-trigger" value="1">
                    <span style="display:flex; align-items:center; gap:6px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Verified Farmers Only
                    </span>
                </label>
            </div>

            <!-- Freshness / Shelf Life -->
            <div class="form-group" style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border-color);">
                <label class="form-label">Freshness & Expiry</label>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:13.5px; cursor:pointer;">
                        <input type="radio" name="filter_freshness" class="filter-trigger" value="all" checked> All Shelf Lifes
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13.5px; cursor:pointer;">
                        <input type="radio" name="filter_freshness" class="filter-trigger" value="fresh"> Fresh (&ge; 4 days left)
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13.5px; cursor:pointer;">
                        <input type="radio" name="filter_freshness" class="filter-trigger" value="expiring_soon"> ⚠️ Expiring Soon (1-3 days)
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13.5px; cursor:pointer;">
                        <input type="radio" name="filter_freshness" class="filter-trigger" value="in_stock"> In-Stock Only
                    </label>
                </div>
            </div>

            <!-- Farmer Rating -->
            <div class="form-group" style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border-color);">
                <label class="form-label">Farmer Minimum Rating</label>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:13.5px; cursor:pointer;">
                        <input type="radio" name="filter_rating" class="filter-trigger" value="" checked> Any Rating
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13.5px; cursor:pointer;">
                        <input type="radio" name="filter_rating" class="filter-trigger" value="4.5"> 4.5★ & Above
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13.5px; cursor:pointer;">
                        <input type="radio" name="filter_rating" class="filter-trigger" value="4.0"> 4.0★ & Above
                    </label>
                </div>
            </div>

            <!-- Farm Location Filter -->
            <div class="form-group" style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border-color); margin-bottom:0;">
                <label class="form-label">Location / Region</label>
                <input type="text" id="locationFilter" class="form-control" placeholder="e.g. Savar, Bogura, Dhaka">
            </div>
        </aside>

        <!-- Product Results Area -->
        <div>
            <div style="margin-bottom: 20px; font-size: 14.5px; font-weight: 600; color: var(--gray-600);" id="productResultsCount">
                Loading products...
            </div>

            <div class="product-grid" id="browseProductGrid">
                <!-- Dynamically populated via applyFilters() on load -->
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        applyFilters();
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
