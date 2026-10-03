<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('farmer');
$user = current_user();
$productId = (int)($_GET['id'] ?? 0);

if ($productId > 0) {
    $db = get_db();
    $stmtFarmer = $db->prepare("SELECT farmer_id FROM farmers WHERE user_id = ?");
    $stmtFarmer->execute([$user['user_id']]);
    $farmerId = (int)$stmtFarmer->fetchColumn();

    $stmtDelete = $db->prepare("DELETE FROM products WHERE product_id = ? AND farmer_id = ?");
    $stmtDelete->execute([$productId, $farmerId]);

    if ($stmtDelete->rowCount() > 0) {
        set_flash('success', 'Product listing has been removed.');
    } else {
        set_flash('error', 'Could not delete product or unauthorized.');
    }
}

header('Location: ' . url('farmer/products.php'));
exit;
