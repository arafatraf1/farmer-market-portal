<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('farmer');
$user = current_user();
$db = get_db();

$stmtFarmer = $db->prepare("SELECT farmer_id, farm_name, farmer_rating, total_reviews FROM farmers WHERE user_id = ?");
$stmtFarmer->execute([$user['user_id']]);
$farmer = $stmtFarmer->fetch();
$farmerId = (int)$farmer['farmer_id'];

// Fetch reviews
$stmt = $db->prepare("
    SELECT r.*, u.name as buyer_name, u.profile_image as buyer_avatar, o.order_code, p.product_name
    FROM reviews r
    JOIN buyers b ON r.buyer_id = b.buyer_id
    JOIN users u ON b.user_id = u.user_id
    JOIN orders o ON r.order_id = o.order_id
    LEFT JOIN products p ON r.product_id = p.product_id
    WHERE r.farmer_id = ? AND r.status = 'active'
    ORDER BY r.created_at DESC
");
$stmt->execute([$farmerId]);
$reviews = $stmt->fetchAll();

// Rating breakdown (1 to 5)
$counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($reviews as $rev) {
    $counts[(int)$rev['rating']]++;
}

$pageTitle = 'Customer Ratings & Reviews';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/farmer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Customer Ratings & Feedback</h1>
                <p>Verified purchase reviews left by buyers who received your agricultural produce</p>
            </div>
        </div>

        <!-- Rating Summary Hero -->
        <div style="display:grid; grid-template-columns: 280px 1fr; gap:28px; background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:30px; box-shadow:var(--shadow-sm); margin-bottom:30px; align-items:center;">
            <div style="text-align:center; padding-right:20px; border-right:1px solid var(--border-color);">
                <div style="font-size:46px; font-weight:800; color:var(--gray-900); line-height:1;"><?= number_format((float)$farmer['farmer_rating'], 1) ?></div>
                <div style="margin:10px 0;"><?= render_stars((float)$farmer['farmer_rating']) ?></div>
                <div style="font-size:13px; color:var(--gray-500);">Based on <?= (int)$farmer['total_reviews'] ?> verified reviews</div>
            </div>

            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php for ($s = 5; $s >= 1; $s--): ?>
                    <?php 
                    $c = $counts[$s]; 
                    $pct = count($reviews) > 0 ? round(($c / count($reviews)) * 100) : 0; 
                    ?>
                    <div style="display:flex; align-items:center; gap:12px; font-size:13px;">
                        <span style="width:35px; font-weight:600; color:var(--gray-700);"><?= $s ?> ★</span>
                        <div style="flex:1; height:8px; background:var(--gray-100); border-radius:4px; overflow:hidden;">
                            <div style="width:<?= $pct ?>%; height:100%; background:var(--accent-amber); border-radius:4px;"></div>
                        </div>
                        <span style="width:50px; text-align:right; color:var(--gray-500);"><?= $c ?> (<?= $pct ?>%)</span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Reviews List -->
        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">All Customer Reviews (<?= count($reviews) ?>)</span>
            </div>

            <?php if (empty($reviews)): ?>
                <div style="padding:50px; text-align:center; color:var(--gray-500);">
                    No reviews received yet. As you fulfill and deliver orders, happy buyers will post their ratings here.
                </div>
            <?php else: ?>
                <div style="padding:24px; display:flex; flex-direction:column; gap:16px;">
                    <?php foreach ($reviews as $rev): ?>
                        <div style="background:var(--gray-50); border:1px solid var(--border-color); border-radius:14px; padding:20px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <img src="<?= e($rev['buyer_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80') ?>" alt="Buyer" style="width:38px; height:38px; border-radius:50%; object-fit:cover;">
                                    <div>
                                        <div style="font-weight:700; color:var(--gray-900); font-size:14px;"><?= e($rev['buyer_name']) ?></div>
                                        <div style="font-size:11.5px; color:var(--gray-500);">
                                            Order <strong><?= e($rev['order_code']) ?></strong>
                                            <?php if ($rev['product_name']): ?>
                                                • Purchased <em><?= e($rev['product_name']) ?></em>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <?= render_stars((float)$rev['rating']) ?>
                                    <div style="font-size:11px; color:var(--gray-400); margin-top:4px;">
                                        <?= date('M d, Y', strtotime($rev['created_at'])) ?>
                                    </div>
                                </div>
                            </div>

                            <p style="font-size:14px; color:var(--gray-700); line-height:1.6; font-style:italic;">
                                "<?= e($rev['review_text']) ?>"
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
