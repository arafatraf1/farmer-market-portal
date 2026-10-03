<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

// Rule 1: Only registered buyers can purchase products
require_login(url('checkout.php'));
if (user_role() !== 'buyer') {
    set_flash('warning', 'Only buyer accounts can initiate agricultural purchase requests. Please register or log in as a buyer.');
    header('Location: ' . url('browse.php'));
    exit;
}

$cart = get_cart();
if (empty($cart)) {
    set_flash('info', 'Your shopping cart is empty.');
    header('Location: ' . url('browse.php'));
    exit;
}

$user = current_user();
$db = get_db();

// Fetch buyer record
$stmtBuyer = $db->prepare("SELECT * FROM buyers WHERE user_id = ?");
$stmtBuyer->execute([$user['user_id']]);
$buyer = $stmtBuyer->fetch();

if (!$buyer) {
    // Create buyer record if somehow missing
    $db->prepare("INSERT INTO buyers (user_id) VALUES (?)")->execute([$user['user_id']]);
    $buyerId = (int)$db->lastInsertId();
} else {
    $buyerId = (int)$buyer['buyer_id'];
}

$total = get_cart_total();
$errors = [];

// Group items by farmer (one order per farmer)
$farmerGroups = [];
foreach ($cart as $prodId => $item) {
    $fId = $item['farmer_id'];
    if (!isset($farmerGroups[$fId])) {
        $farmerGroups[$fId] = [
            'farmer_id' => $fId,
            'farm_name' => $item['farm_name'],
            'items' => [],
            'subtotal' => 0.0
        ];
    }
    $itemSub = $item['price'] * $item['quantity'];
    $farmerGroups[$fId]['items'][] = $item;
    $farmerGroups[$fId]['subtotal'] += $itemSub;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $deliveryDate = trim($_POST['requested_delivery_date'] ?? '');
    $instructions = trim($_POST['delivery_instructions'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');

    if (empty($deliveryAddress)) {
        $errors[] = 'Delivery address is required.';
    }

    if (empty($deliveryDate)) {
        $errors[] = 'Requested delivery date is required.';
    } elseif (strtotime($deliveryDate) < strtotime('today')) {
        $errors[] = 'Requested delivery date must be today or in the future.';
    }

    // Re-verify stock and expiry before committing transaction
    foreach ($cart as $prodId => $item) {
        $stmtCheck = $db->prepare("SELECT quantity, expiry_date, availability, product_name FROM products WHERE product_id = ? FOR UPDATE");
        $stmtCheck->execute([$prodId]);
        $curr = $stmtCheck->fetch();

        if (!$curr) {
            $errors[] = "Product {$item['product_name']} is no longer available.";
            continue;
        }

        $fresh = get_freshness_status($curr['expiry_date']);
        if ($fresh['is_expired'] || $curr['availability'] === 'expired') {
            $errors[] = "Product {$curr['product_name']} has expired and cannot be ordered.";
        }

        if ($curr['quantity'] < $item['quantity']) {
            $errors[] = "Insufficient stock for {$curr['product_name']}. Only {$curr['quantity']} available.";
        }
    }

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            $createdOrders = [];

            foreach ($farmerGroups as $fId => $fGroup) {
                $orderCode = 'FMP-' . strtoupper(bin2hex(random_bytes(4)));

                // 1. Insert order
                $stmtOrder = $db->prepare("
                    INSERT INTO orders (order_code, buyer_id, farmer_id, order_date, delivery_address, delivery_instructions, requested_delivery_date, total_amount, estimated_delivery_time, order_status)
                    VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, 'pending')
                ");
                $stmtOrder->execute([
                    $orderCode,
                    $buyerId,
                    $fId,
                    $deliveryAddress . ($contactPhone ? ' (Phone: ' . $contactPhone . ')' : ''),
                    $instructions,
                    $deliveryDate,
                    $fGroup['subtotal'],
                    '1-2 Days (Awaiting Farmer Confirmation)'
                ]);
                $orderId = (int)$db->lastInsertId();
                $createdOrders[] = $orderId;

                // 2. Insert order items & deduct stock
                $stmtItem = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmtDeduct = $db->prepare("UPDATE products SET quantity = quantity - ? WHERE product_id = ?");

                foreach ($fGroup['items'] as $it) {
                    $itemSub = $it['price'] * $it['quantity'];
                    $stmtItem->execute([$orderId, $it['product_id'], $it['quantity'], $it['price'], $itemSub]);
                    $stmtDeduct->execute([$it['quantity'], $it['product_id']]);
                }

                // 3. Initialize delivery tracking
                $stmtDel = $db->prepare("INSERT INTO delivery (order_id, status, tracking_notes, estimated_delivery) VALUES (?, 'pending', 'Order submitted to farmer for acceptance.', ?)");
                $stmtDel->execute([$orderId, $deliveryDate . ' 18:00:00']);
            }

            $db->commit();

            // Clear cart
            $_SESSION['cart'] = [];

            set_flash('success', 'Purchase request successfully placed! The farmer has been notified.');
            // Redirect to first order tracking
            header('Location: ' . url('buyer/order-tracking.php?id=' . $createdOrders[0]));
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Failed to process order: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout & Purchase Request';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 40px 20px 80px;">
    <h1 style="font-size:30px; font-weight:800; color:var(--gray-900); margin-bottom:8px;">Checkout & Purchase Request</h1>
    <p style="color:var(--gray-500); font-size:14.5px; margin-bottom:30px;">Specify delivery destination and timing for your fresh produce</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul style="margin-left: 20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="" method="POST" style="display:grid; grid-template-columns: 1fr 380px; gap:32px; align-items:flex-start;">
        
        <!-- Left: Delivery Details Form -->
        <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:30px; box-shadow:var(--shadow-sm);">
            <h3 style="font-size:18px; font-weight:700; color:var(--gray-900); margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid var(--border-color);">
                1. Delivery & Contact Details
            </h3>

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" value="<?= e($user['name']) ?>" readonly style="background:var(--gray-50);">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label">Contact Phone <span class="req">*</span></label>
                    <input type="tel" name="contact_phone" class="form-control" value="<?= e($_POST['contact_phone'] ?? $user['phone'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Requested Delivery Date <span class="req">*</span></label>
                    <input type="date" name="requested_delivery_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= e($_POST['requested_delivery_date'] ?? date('Y-m-d', strtotime('+1 day'))) ?>" required>
                    <div class="form-hint">Choose preferred harvest delivery day</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Delivery Address <span class="req">*</span></label>
                <textarea name="delivery_address" class="form-control" placeholder="House/Flat number, Road, Area, City..." required><?= e($_POST['delivery_address'] ?? $user['address'] ?? '') ?></textarea>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Special Delivery Instructions (Optional)</label>
                <textarea name="delivery_instructions" class="form-control" placeholder="e.g. Leave at gate if unattended, call upon arrival, handle eggs with extra care..."><?= e($_POST['delivery_instructions'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Right: Order Breakdown & Confirm Button -->
        <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:24px; box-shadow:var(--shadow-sm); position:sticky; top:94px;">
            <h3 style="font-size:18px; font-weight:700; color:var(--gray-900); margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid var(--border-color);">
                Order Breakdown
            </h3>

            <!-- Farmer Groups Review -->
            <?php foreach ($farmerGroups as $fg): ?>
                <div style="margin-bottom:18px; padding-bottom:14px; border-bottom:1px dashed var(--gray-200);">
                    <div style="font-weight:700; font-size:13.5px; color:var(--primary-800); margin-bottom:8px;">
                        🌾 <?= e($fg['farm_name']) ?>
                    </div>
                    <?php foreach ($fg['items'] as $item): ?>
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px; color:var(--gray-700);">
                            <span><?= e($item['product_name']) ?> &times; <?= $item['quantity'] ?> <?= e($item['unit']) ?></span>
                            <span style="font-weight:600;"><?= format_price($item['price'] * $item['quantity']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:10px; color:var(--gray-600);">
                <span>Subtotal</span>
                <strong><?= format_price($total) ?></strong>
            </div>

            <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:16px; color:var(--gray-600);">
                <span>Middleman Markup</span>
                <span style="color:var(--primary-700); font-weight:600;">৳0.00</span>
            </div>

            <div style="display:flex; justify-content:space-between; font-size:18px; padding-top:14px; border-top:1px solid var(--border-color); margin-bottom:24px;">
                <span style="font-weight:700; color:var(--gray-900);">Total Due</span>
                <strong style="font-weight:800; color:var(--primary-800); font-size:22px;"><?= format_price($total) ?></strong>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
                Confirm & Submit Request
            </button>

            <div style="margin-top:14px; font-size:12px; color:var(--gray-500); line-height:1.5; text-align:center;">
                Payment is processed upon direct farmer delivery confirmation.
            </div>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
