<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('buyer');
$user = current_user();
$db = get_db();

// Fetch buyer ID
$stmtBuyer = $db->prepare("SELECT buyer_id FROM buyers WHERE user_id = ?");
$stmtBuyer->execute([$user['user_id']]);
$buyerId = (int)$stmtBuyer->fetchColumn();

// Fetch given reviews
$stmtGiven = $db->prepare("
    SELECT r.*, f.farm_name, f.farm_location, o.order_code, p.product_name
    FROM reviews r
    JOIN farmers f ON r.farmer_id = f.farmer_id
    JOIN orders o ON r.order_id = o.order_id
    LEFT JOIN products p ON r.product_id = p.product_id
    WHERE r.buyer_id = ?
    ORDER BY r.created_at DESC
");
$stmtGiven->execute([$buyerId]);
$givenReviews = $stmtGiven->fetchAll();

// Fetch completed orders without a review
$stmtPendingReview = $db->prepare("
    SELECT o.*, f.farm_name, f.farm_location
    FROM orders o
    JOIN farmers f ON o.farmer_id = f.farmer_id
    LEFT JOIN reviews r ON o.order_id = r.order_id AND r.buyer_id = o.buyer_id
    WHERE o.buyer_id = ? AND o.order_status = 'completed' AND r.review_id IS NULL
    ORDER BY o.order_date DESC
");
$stmtPendingReview->execute([$buyerId]);
$pendingReviewOrders = $stmtPendingReview->fetchAll();

$pageTitle = 'My Reviews & Ratings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/buyer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>My Farmer Reviews</h1>
                <p>Authentic feedback you have shared with verified agricultural producers</p>
            </div>
        </div>

        <!-- Pending Reviews Alert/Invitation -->
        <?php if (!empty($pendingReviewOrders)): ?>
            <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:16px; padding:20px 24px; margin-bottom:30px;">
                <h3 style="font-size:16px; font-weight:700; color:#92400e; margin-bottom:6px;">🌟 Share Feedback on Completed Deliveries</h3>
                <p style="font-size:13.5px; color:#b45309; margin-bottom:14px;">
                    You have <?= count($pendingReviewOrders) ?> completed order(s) eligible for review. Your honest rating helps fellow buyers and empowers honest farmers.
                </p>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($pendingReviewOrders as $pOrd): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; padding:12px 18px; border-radius:10px; border:1px solid #fef3c7;">
                            <div>
                                <strong style="color:var(--gray-900);"><?= e($pOrd['order_code']) ?></strong> — <?= e($pOrd['farm_name']) ?> (<?= format_price($pOrd['total_amount']) ?>)
                            </div>
                            <a href="<?= url('buyer/order-tracking.php?id=' . $pOrd['order_id'] . '&review=1') ?>" class="btn btn-accent btn-sm">
                                Rate This Delivery &rarr;
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Given Reviews List -->
        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">Your Submitted Reviews (<?= count($givenReviews) ?>)</span>
            </div>

            <?php if (empty($givenReviews)): ?>
                <div style="padding:40px; text-align:center; color:var(--gray-500);">
                    You haven't submitted any reviews yet. Complete a fresh farm delivery to rate a grower!
                </div>
            <?php else: ?>
                <div style="padding:24px; display:flex; flex-direction:column; gap:16px;">
                    <?php foreach ($givenReviews as $rev): ?>
                        <div style="background:var(--gray-50); border:1px solid var(--border-color); border-radius:12px; padding:20px;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                <div>
                                    <h4 style="font-size:16px; color:var(--gray-900); margin-bottom:4px;"><?= e($rev['farm_name']) ?></h4>
                                    <div style="font-size:12px; color:var(--gray-500);">
                                        Order <strong><?= e($rev['order_code']) ?></strong> • <?= date('F d, Y', strtotime($rev['created_at'])) ?>
                                    </div>
                                </div>
                                <div>
                                    <?= render_stars((float)$rev['rating']) ?>
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
