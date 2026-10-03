<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$buyerUser = current_user();
?>
<aside class="dashboard-sidebar">
    <div class="sidebar-user-badge">
        <img src="<?= e($buyerUser['profile_image'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80') ?>" alt="Buyer" class="sidebar-avatar">
        <div class="sidebar-user-info">
            <h4><?= e($buyerUser['name']) ?></h4>
            <p>Buyer Account</p>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="sidebar-menu-title">Main Portal</li>
        <li>
            <a href="<?= url('buyer/dashboard.php') ?>" class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="<?= url('buyer/orders.php') ?>" class="sidebar-link <?= in_array($currentPage, ['orders.php', 'order-tracking.php']) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                <span>My Orders & Tracking</span>
            </a>
        </li>
        <li>
            <a href="<?= url('buyer/reviews.php') ?>" class="sidebar-link <?= $currentPage === 'reviews.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                <span>My Farmer Reviews</span>
            </a>
        </li>
        <li>
            <a href="<?= url('buyer/favorites.php') ?>" class="sidebar-link <?= $currentPage === 'favorites.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path></svg>
                <span>Saved Favorites</span>
            </a>
        </li>

        <li class="sidebar-menu-title">Account & Settings</li>
        <li>
            <a href="<?= url('buyer/profile.php') ?>" class="sidebar-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>Delivery Profile</span>
            </a>
        </li>
        <li>
            <a href="<?= url('browse.php') ?>" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <span>Browse Marketplace</span>
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
