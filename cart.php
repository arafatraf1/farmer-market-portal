<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$pageTitle = 'Your Shopping Cart';
require_once __DIR__ . '/includes/header.php';

$cart = get_cart();
$total = get_cart_total();
?>

<div class="container" style="padding: 40px 20px 80px;">
    <h1 style="font-size:30px; font-weight:800; color:var(--gray-900); margin-bottom:8px;">Your Shopping Cart</h1>
    <p style="color:var(--gray-500); font-size:14.5px; margin-bottom:30px;">Review fresh agricultural items before submitting your purchase request</p>

    <?php if (empty($cart)): ?>
        <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:20px; padding:60px 20px; text-align:center; max-width:600px; margin:0 auto; box-shadow:var(--shadow-sm);">
            <div style="width:70px; height:70px; background:var(--gray-100); color:var(--gray-400); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
            </div>
            <h3 style="font-size:20px; font-weight:700; color:var(--gray-800); margin-bottom:8px;">Your cart is currently empty</h3>
            <p style="color:var(--gray-500); font-size:14px; margin-bottom:24px;">Discover pure, fresh farm vegetables, seasonal fruits, dairy, and more directly from growers.</p>
            <a href="<?= url('browse.php') ?>" class="btn btn-primary btn-lg">Browse Marketplace</a>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns: 1fr 360px; gap:32px; align-items:flex-start;">
            
            <!-- Items Table Panel -->
            <div class="card-panel">
                <div class="card-panel-header">
                    <span class="card-panel-title">Cart Items (<?= count($cart) ?> items)</span>
                    <button type="button" onclick="if(confirm('Clear all items from your cart?')) { fetch('<?= url('api/cart.php') ?>', {method:'POST', body:new URLSearchParams({action:'clear'})}).then(() => window.location.reload()); }" class="btn btn-secondary btn-sm" style="color:var(--danger-500);">Clear Cart</button>
                </div>

                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Produce</th>
                                <th>Farm / Farmer</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart as $id => $item): ?>
                                <?php 
                                $freshness = get_freshness_status($item['expiry_date']);
                                $subtotal = $item['price'] * $item['quantity'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="table-item-cell">
                                            <img src="<?= e($item['image'] ?: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=150&q=80') ?>" alt="Product" class="table-thumbnail">
                                            <div>
                                                <a href="<?= url('product-details.php?id=' . $item['product_id']) ?>" style="font-weight:700; color:var(--gray-900);">
                                                    <?= e($item['product_name']) ?>
                                                </a>
                                                <div style="margin-top:2px;">
                                                    <span class="freshness-chip <?= e($freshness['class']) ?>" style="font-size:10.5px; padding:2px 6px;">
                                                        <?= e($freshness['label']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight:600; color:var(--gray-700);"><?= e($item['farm_name']) ?></span>
                                    </td>
                                    <td>
                                        <span style="font-weight:600;"><?= format_price($item['price']) ?></span>
                                        <span style="font-size:12px; color:var(--gray-400);">/ <?= e($item['unit']) ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex; align-items:center; border:1px solid var(--gray-300); border-radius:6px; overflow:hidden; width:110px;">
                                            <button type="button" onclick="updateCartItem(<?= $item['product_id'] ?>, <?= max(1, $item['quantity'] - 1) ?>);" style="width:32px; height:32px; border:none; background:var(--gray-100); cursor:pointer; font-weight:700;">-</button>
                                            <input type="text" value="<?= $item['quantity'] ?>" style="width:46px; height:32px; border:none; text-align:center; font-weight:700; font-size:13.5px;" readonly>
                                            <button type="button" onclick="updateCartItem(<?= $item['product_id'] ?>, <?= min($item['max_quantity'], $item['quantity'] + 1) ?>);" style="width:32px; height:32px; border:none; background:var(--gray-100); cursor:pointer; font-weight:700;" <?= $item['quantity'] >= $item['max_quantity'] ? 'disabled' : '' ?>>+</button>
                                        </div>
                                        <div style="font-size:11px; color:var(--gray-400); margin-top:2px;">Max: <?= $item['max_quantity'] ?> <?= e($item['unit']) ?></div>
                                    </td>
                                    <td>
                                        <span style="font-weight:800; color:var(--primary-800); font-size:15px;"><?= format_price($subtotal) ?></span>
                                    </td>
                                    <td>
                                        <button type="button" onclick="removeCartItem(<?= $item['product_id'] ?>);" style="background:transparent; border:none; color:var(--danger-500); cursor:pointer;" title="Remove Item">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Order Summary Card -->
            <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:24px; box-shadow:var(--shadow-sm); position:sticky; top:94px;">
                <h3 style="font-size:18px; font-weight:700; color:var(--gray-900); margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid var(--border-color);">
                    Order Summary
                </h3>

                <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:12px; color:var(--gray-600);">
                    <span>Produce Subtotal</span>
                    <strong style="color:var(--gray-900);"><?= format_price($total) ?></strong>
                </div>

                <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:12px; color:var(--gray-600);">
                    <span>Platform Middleman Fee</span>
                    <strong style="color:var(--primary-700);">৳0.00 (Direct Trade)</strong>
                </div>

                <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:20px; color:var(--gray-600);">
                    <span>Estimated Farm Dispatch</span>
                    <span style="color:var(--gray-800); font-weight:600;">Direct from Farm</span>
                </div>

                <div style="display:flex; justify-content:space-between; font-size:18px; padding-top:16px; border-top:1px solid var(--border-color); margin-bottom:24px;">
                    <span style="font-weight:700; color:var(--gray-900);">Total Amount</span>
                    <strong style="font-weight:800; color:var(--primary-800); font-size:22px;"><?= format_price($total) ?></strong>
                </div>

                <a href="<?= url('checkout.php') ?>" class="btn btn-accent btn-lg" style="width:100%;">
                    Proceed to Purchase &rarr;
                </a>

                <div style="margin-top:16px; font-size:12px; color:var(--gray-500); text-align:center; line-height:1.5;">
                    🛡️ Protected by Farmer Market Verification & Inspection
                </div>
            </div>

        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
