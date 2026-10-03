<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('buyer');
$user = current_user();
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    set_flash('error', 'Invalid order specified.');
    header('Location: ' . url('buyer/orders.php'));
    exit;
}

$db = get_db();

// Fetch order details ensuring it belongs to current buyer
$stmt = $db->prepare("
    SELECT o.*, f.farmer_id, f.farm_name, f.farm_location, f.verification_status, f.farmer_rating, f.total_reviews,
           u.name as farmer_name, u.phone as farmer_phone, u.profile_image as farmer_avatar,
           d.status as delivery_status, d.tracking_notes, d.estimated_delivery, d.actual_delivery,
           r.review_id, r.rating as user_rating, r.review_text, r.created_at as review_date
    FROM orders o
    JOIN buyers b ON o.buyer_id = b.buyer_id
    JOIN farmers f ON o.farmer_id = f.farmer_id
    JOIN users u ON f.user_id = u.user_id
    LEFT JOIN delivery d ON o.order_id = d.order_id
    LEFT JOIN reviews r ON o.order_id = r.order_id AND r.buyer_id = o.buyer_id
    WHERE o.order_id = ? AND b.user_id = ?
");
$stmt->execute([$orderId, $user['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found or unauthorized.');
    header('Location: ' . url('buyer/orders.php'));
    exit;
}

// Fetch items
$stmtItems = $db->prepare("
    SELECT oi.*, p.product_name, p.unit, p.image, p.harvest_date, p.expiry_date
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$stmtItems->execute([$orderId]);
$items = $stmtItems->fetchAll();

// Timeline milestone definitions
$pipeline = [
    'pending' => ['step' => 1, 'title' => 'Order Placed', 'desc' => 'Waiting for farmer acceptance'],
    'accepted' => ['step' => 2, 'title' => 'Accepted', 'desc' => 'Farmer confirmed produce availability'],
    'preparing' => ['step' => 3, 'title' => 'Preparing Produce', 'desc' => 'Harvesting & packing freshly'],
    'ready_for_delivery' => ['step' => 4, 'title' => 'Ready for Dispatch', 'desc' => 'Packed in chilled crates'],
    'out_for_delivery' => ['step' => 5, 'title' => 'Out for Delivery', 'desc' => 'Courier dispatched to address'],
    'delivered' => ['step' => 6, 'title' => 'Delivered to Door', 'desc' => 'Awaiting buyer confirmation'],
    'completed' => ['step' => 7, 'title' => 'Order Completed', 'desc' => 'Confirmed & verified']
];

$currStatus = $order['order_status'];
$isCancelled = in_array($currStatus, ['cancelled', 'rejected']);

$currentStepNumber = $pipeline[$currStatus]['step'] ?? 1;

$pageTitle = 'Tracking Order ' . $order['order_code'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/buyer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <a href="<?= url('buyer/orders.php') ?>" style="font-size:13px; color:var(--gray-500); font-weight:600; display:inline-flex; align-items:center; gap:4px; margin-bottom:6px;">
                    &larr; Back to My Orders
                </a>
                <h1>Delivery Tracking: <?= e($order['order_code']) ?></h1>
                <p>Real-time delivery milestones directly from <?= e($order['farm_name']) ?></p>
            </div>
            
            <div>
                <?= get_order_badge($currStatus) ?>
            </div>
        </div>

        <!-- Tracking Status Hero Card -->
        <div class="tracking-status-hero">
            <div class="tracking-status-left">
                <h2>Current Stage: <?= e(ucfirst(str_replace('_', ' ', $currStatus))) ?></h2>
                <p>
                    <?= e($order['tracking_notes'] ?: 'Farm dispatch unit is updating progress.') ?>
                </p>
                <div style="font-size:13px; color:var(--primary-800); margin-top:8px;">
                    <strong>Estimated Arrival:</strong> <?= e($order['estimated_delivery_time'] ?: '1-2 Business Days') ?>
                    <?php if ($order['requested_delivery_date']): ?>
                        (Target: <?= date('F d, Y', strtotime($order['requested_delivery_date'])) ?>)
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($currStatus === 'delivered'): ?>
                <button type="button" class="btn btn-primary btn-lg" onclick="confirmOrderReceived(<?= $order['order_id'] ?>);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Confirm Order Received
                </button>
            <?php elseif ($currStatus === 'completed' && empty($order['review_id'])): ?>
                <button type="button" class="btn btn-accent btn-lg" onclick="openModal('reviewModal');">
                    ★ Rate & Review Farmer
                </button>
            <?php endif; ?>
        </div>

        <!-- Visual Milestone Timeline (if not cancelled) -->
        <?php if (!$isCancelled): ?>
            <div class="tracking-timeline-container">
                <div style="font-size:15px; font-weight:700; color:var(--gray-900); margin-bottom:8px;">Delivery Progress Pipeline</div>
                
                <div class="tracking-timeline">
                    <?php foreach ($pipeline as $statusKey => $stepInfo): ?>
                        <?php 
                        $stepNum = $stepInfo['step'];
                        $isPast = ($stepNum < $currentStepNumber);
                        $isCurrent = ($stepNum === $currentStepNumber);
                        $stepClass = $isPast ? 'completed' : ($isCurrent ? 'active' : 'pending');
                        ?>
                        <div class="tracking-step <?= $stepClass ?>">
                            <div class="step-icon-wrap">
                                <?php if ($isPast): ?>
                                    ✓
                                <?php else: ?>
                                    <?= $stepNum ?>
                                <?php endif; ?>
                            </div>
                            <div class="step-title"><?= e($stepInfo['title']) ?></div>
                            <div class="step-time"><?= e($stepInfo['desc']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-error" style="margin-bottom:28px;">
                <strong>Order Cancelled / Rejected:</strong> <?= e($order['cancellation_reason'] ?: 'This order was cancelled and is not in transit.') ?>
            </div>
        <?php endif; ?>

        <!-- Order Items & Farmer Card Split -->
        <div style="display:grid; grid-template-columns: 1fr 340px; gap:28px; align-items:flex-start;">
            
            <!-- Items Card -->
            <div class="card-panel">
                <div class="card-panel-header">
                    <span class="card-panel-title">Purchased Farm Produce (<?= count($items) ?> items)</span>
                </div>

                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Produce</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th>Freshness Shelf Life</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <?php $itFresh = get_freshness_status($it['expiry_date']); ?>
                                <tr>
                                    <td>
                                        <div class="table-item-cell">
                                            <img src="<?= e($it['image'] ?: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=150&q=80') ?>" alt="Thumb" class="table-thumbnail">
                                            <div>
                                                <strong style="color:var(--gray-900);"><?= e($it['product_name']) ?></strong>
                                                <div style="font-size:12px; color:var(--gray-500);">Harvested: <?= date('M d', strtotime($it['harvest_date'])) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= format_price($it['price']) ?> / <?= e($it['unit']) ?></td>
                                    <td><strong><?= $it['quantity'] ?> <?= e($it['unit']) ?></strong></td>
                                    <td>
                                        <span class="freshness-chip <?= e($itFresh['class']) ?>" style="font-size:11px; padding:2px 8px;">
                                            <?= e($itFresh['label']) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= format_price($it['subtotal']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="padding:20px 24px; background:var(--gray-50); border-top:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:700; color:var(--gray-700);">Total Order Amount:</span>
                    <span style="font-size:22px; font-weight:800; color:var(--primary-800);"><?= format_price($order['total_amount']) ?></span>
                </div>
            </div>

            <!-- Farmer & Delivery Destination Card -->
            <div style="display:flex; flex-direction:column; gap:20px;">
                
                <!-- Farmer Contact -->
                <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:20px; box-shadow:var(--shadow-sm);">
                    <h4 style="font-size:15px; font-weight:700; margin-bottom:14px; color:var(--gray-900);">Farmer & Dispatcher</h4>
                    
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                        <img src="<?= e($order['farmer_avatar'] ?: 'https://images.unsplash.com/photo-1595273670150-bd0c3c392e46?auto=format&fit=crop&w=100&q=80') ?>" alt="Farmer" style="width:46px; height:46px; border-radius:50%; object-fit:cover;">
                        <div>
                            <div style="font-weight:700; font-size:14px;"><?= e($order['farm_name']) ?></div>
                            <div style="font-size:12px; color:var(--gray-500);"><?= e($order['farmer_name']) ?> • <?= e($order['farm_location']) ?></div>
                        </div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <?= get_verification_badge($order['verification_status']) ?>
                    </div>

                    <div style="font-size:13px; color:var(--gray-600); margin-top:8px;">
                        📞 Contact: <strong><?= e($order['farmer_phone']) ?></strong>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:20px; box-shadow:var(--shadow-sm);">
                    <h4 style="font-size:15px; font-weight:700; margin-bottom:10px; color:var(--gray-900);">Delivery Destination</h4>
                    <p style="font-size:13.5px; color:var(--gray-700); line-height:1.6; margin-bottom:10px;">
                        <?= nl2br(e($order['delivery_address'])) ?>
                    </p>
                    <?php if ($order['delivery_instructions']): ?>
                        <div style="font-size:12.5px; background:var(--gray-50); padding:10px; border-radius:8px; color:var(--gray-600);">
                            <strong>Note:</strong> <?= e($order['delivery_instructions']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Existing Review Callout (if completed & reviewed) -->
                <?php if (!empty($order['review_id'])): ?>
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:16px; padding:20px;">
                        <div style="font-weight:700; font-size:14px; color:#166534; margin-bottom:6px;">Your Verified Review Submitted</div>
                        <div style="margin-bottom:8px;"><?= render_stars((float)$order['user_rating']) ?></div>
                        <p style="font-size:13px; color:#14532d; font-style:italic;">
                            "<?= e($order['review_text']) ?>"
                        </p>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </main>
</div>

<!-- Review Modal Dialog -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Rate <?= e($order['farm_name']) ?></h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="reviewForm" onsubmit="submitReview(event);">
            <div class="modal-body">
                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                <input type="hidden" name="farmer_id" value="<?= $order['farmer_id'] ?>">
                
                <div class="form-group">
                    <label class="form-label">How would you rate the freshness and delivery?</label>
                    <div style="display:flex; gap:12px; font-size:24px; cursor:pointer;" id="starRatingPicker">
                        <span onclick="setRating(1)" data-star="1">★</span>
                        <span onclick="setRating(2)" data-star="2">★</span>
                        <span onclick="setRating(3)" data-star="3">★</span>
                        <span onclick="setRating(4)" data-star="4">★</span>
                        <span onclick="setRating(5)" data-star="5">★</span>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="5">
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Your Review & Feedback <span class="req">*</span></label>
                    <textarea name="review_text" class="form-control" placeholder="Describe the quality of vegetables, fruits, taste, packaging, and farmer responsiveness..." required minlength="5"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Verified Review</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentStar = 5;
function setRating(num) {
    currentStar = num;
    document.getElementById('ratingInput').value = num;
    document.querySelectorAll('#starRatingPicker span').forEach(el => {
        const starIndex = parseInt(el.getAttribute('data-star'));
        el.style.color = starIndex <= num ? '#f59e0b' : '#cbd5e1';
    });
}
document.addEventListener('DOMContentLoaded', () => {
    setRating(5);
    <?php if (isset($_GET['review']) && empty($order['review_id']) && $currStatus === 'completed'): ?>
        openModal('reviewModal');
    <?php endif; ?>
});

function submitReview(e) {
    e.preventDefault();
    const form = document.getElementById('reviewForm');
    const formData = new FormData(form);

    fetch('<?= url('api/reviews.php') ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('reviewModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(data.error || 'Failed to submit review', 'error');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
