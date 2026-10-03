<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');
$db = get_db();

// Delete / Moderate review
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmtF = $db->prepare("SELECT farmer_id FROM reviews WHERE review_id = ?");
    $stmtF->execute([$delId]);
    $farmerId = (int)$stmtF->fetchColumn();

    if ($farmerId > 0) {
        $db->prepare("DELETE FROM reviews WHERE review_id = ?")->execute([$delId]);

        // Recalculate farmer rating and count
        $stmtStats = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM reviews WHERE farmer_id = ? AND status = 'active'");
        $stmtStats->execute([$farmerId]);
        $stats = $stmtStats->fetch();

        $newAvg = round((float)($stats['avg_rating'] ?? 0.0), 2);
        $newCount = (int)($stats['cnt'] ?? 0);

        $db->prepare("UPDATE farmers SET farmer_rating = ?, total_reviews = ? WHERE farmer_id = ?")->execute([$newAvg, $newCount, $farmerId]);

        set_flash('success', 'Review has been removed and farmer rating re-indexed.');
    }
    header('Location: ' . url('admin/reviews.php'));
    exit;
}

$sql = "
    SELECT r.*, f.farm_name, f.farmer_id,
           uBuyer.name as buyer_name, uBuyer.profile_image as buyer_avatar,
           o.order_code, p.product_name
    FROM reviews r
    JOIN farmers f ON r.farmer_id = f.farmer_id
    JOIN buyers b ON r.buyer_id = b.buyer_id
    JOIN users uBuyer ON b.user_id = uBuyer.user_id
    JOIN orders o ON r.order_id = o.order_id
    LEFT JOIN products p ON r.product_id = p.product_id
    ORDER BY r.created_at DESC
";
$reviews = $db->query($sql)->fetchAll();

$pageTitle = 'Review Moderation — Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Review & Rating Moderation</h1>
                <p>Inspect community feedback for compliance with agricultural marketplace standards</p>
            </div>
        </div>

        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">Published Reviews (<?= count($reviews) ?>)</span>
            </div>

            <?php if (empty($reviews)): ?>
                <div style="padding:40px; text-align:center; color:var(--gray-500);">
                    No reviews currently published on the platform.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Buyer</th>
                                <th>Farmer / Farm</th>
                                <th>Order</th>
                                <th>Rating</th>
                                <th>Review Feedback</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $rev): ?>
                                <tr>
                                    <td>
                                        <div class="table-item-cell">
                                            <img src="<?= e($rev['buyer_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=60&q=80') ?>" alt="Buyer" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                                            <strong style="color:var(--gray-900);"><?= e($rev['buyer_name']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= e($rev['farm_name']) ?></strong>
                                    </td>
                                    <td>
                                        <code><?= e($rev['order_code']) ?></code>
                                    </td>
                                    <td>
                                        <?= render_stars((float)$rev['rating']) ?>
                                    </td>
                                    <td style="max-width:300px; font-size:13.5px; color:var(--gray-700); line-height:1.5;">
                                        "<?= e($rev['review_text']) ?>"
                                    </td>
                                    <td><?= date('M d, Y', strtotime($rev['created_at'])) ?></td>
                                    <td>
                                        <a href="<?= url('admin/reviews.php?delete_id=' . $rev['review_id']) ?>" class="btn btn-secondary btn-sm" style="color:var(--danger-500); font-size:12px;" onclick="return confirm('Remove this review permanently?');">
                                            Remove
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
