<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$farmerUser = current_user();

// Fetch farmer verification status & farm name
$db = get_db();
$stmtFNav = $db->prepare("SELECT farm_name, verification_status, farmer_rating FROM farmers WHERE user_id = ?");
$stmtFNav->execute([$farmerUser['user_id']]);
$farmerNavData = $stmtFNav->fetch() ?: ['farm_name' => 'My Farm', 'verification_status' => 'pending', 'farmer_rating' => 0];

// Count pending orders
$stmtPendingOrders = $db->prepare("SELECT COUNT(*) FROM orders o JOIN farmers f ON o.farmer_id = f.farmer_id WHERE f.user_id = ? AND o.order_status = 'pending'");
$stmtPendingOrders->execute([$farmerUser['user_id']]);
$pendingCount = (int)$stmtPendingOrders->fetchColumn();
?>
<aside class="dashboard-sidebar">
    <div class="sidebar-user-badge">
        <img src="<?= e($farmerUser['profile_image'] ?: 'https://images.unsplash.com/photo-1595273670150-bd0c3c392e46?auto=format&fit=crop&w=150&q=80') ?>" alt="Farmer" class="sidebar-avatar">
        <div class="sidebar-user-info">
            <h4><?= e($farmerNavData['farm_name']) ?></h4>
            <div style="margin-top:2px;">
                <?= get_verification_badge($farmerNavData['verification_status']) ?>
            </div>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="sidebar-menu-title">Farmer Management</li>
        <li>
            <a href="<?= url('farmer/dashboard.php') ?>" class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                <span>Dashboard Overview</span>
            </a>
        </li>
        <li>
            <a href="<?= url('farmer/products.php') ?>" class="sidebar-link <?= in_array($currentPage, ['products.php', 'product-edit.php']) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                <span>Manage Products</span>
            </a>
        </li>
        <li>
            <a href="<?= url('farmer/product-add.php') ?>" class="sidebar-link <?= $currentPage === 'product-add.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                <span>Add Product</span>
            </a>
        </li>
        <li>
            <a href="<?= url('farmer/orders.php') ?>" class="sidebar-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                <span>Customer Orders</span>
                <?php if ($pendingCount > 0): ?>
                    <span class="badge-count"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?= url('farmer/reviews.php') ?>" class="sidebar-link <?= $currentPage === 'reviews.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                <span>Reviews & Ratings</span>
            </a>
        </li>

        <li class="sidebar-menu-title">Trust & Identity</li>
        <li>
            <a href="<?= url('farmer/verification.php') ?>" class="sidebar-link <?= $currentPage === 'verification.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <span>Verification Status</span>
            </a>
        </li>
        <li>
            <a href="<?= url('farmer/profile.php') ?>" class="sidebar-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>Farm Profile</span>
            </a>
        </li>
        <li>
            <a href="<?= url('farmer-profile.php?id=' . (int)($farmerNavData['farmer_id'] ?? 1)) ?>" target="_blank" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                <span>Public Farm View</span>
            </a>
        </li>
        <li>
            <a href="<?= url('logout.php') ?>" class="sidebar-link" style="color:var(--danger-500);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <span>Sign Out</span>
            </a>
        </li>
    </ul>
</aside>
