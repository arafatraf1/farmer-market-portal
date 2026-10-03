<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$adminUser = current_user();

$db = get_db();
$pendingVerifications = (int)$db->query("SELECT COUNT(*) FROM farmers WHERE verification_status = 'pending'")->fetchColumn();
$activeUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>
<aside class="dashboard-sidebar">
    <div class="sidebar-user-badge" style="background:#0f172a; color:#ffffff; border-color:#1e293b;">
        <img src="<?= e($adminUser['profile_image'] ?: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=150&q=80') ?>" alt="Admin" class="sidebar-avatar">
        <div class="sidebar-user-info">
            <h4 style="color:#ffffff;"><?= e($adminUser['name']) ?></h4>
            <p style="color:#94a3b8;">Portal Super Admin</p>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="sidebar-menu-title">Control Center</li>
        <li>
            <a href="<?= url('admin/dashboard.php') ?>" class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                <span>Dashboard Overview</span>
            </a>
        </li>
        <li>
            <a href="<?= url('admin/verification.php') ?>" class="sidebar-link <?= in_array($currentPage, ['verification.php', 'view-doc.php']) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <span>Farmer Verifications</span>
                <?php if ($pendingVerifications > 0): ?>
                    <span class="badge-count" style="background:var(--danger-500);"><?= $pendingVerifications ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?= url('admin/users.php') ?>" class="sidebar-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <span>User Management</span>
            </a>
        </li>
        <li>
            <a href="<?= url('admin/pricing.php') ?>" class="sidebar-link <?= $currentPage === 'pricing.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg>
                <span>Product & Price Control</span>
            </a>
        </li>
        <li>
            <a href="<?= url('admin/products.php') ?>" class="sidebar-link <?= $currentPage === 'products.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                <span>Farmer Produce Listings</span>
            </a>
        </li>
        <li>
            <a href="<?= url('admin/categories.php') ?>" class="sidebar-link <?= $currentPage === 'categories.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                <span>Category Management</span>
            </a>
        </li>
        <li>
            <a href="<?= url('admin/orders.php') ?>" class="sidebar-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                <span>Orders Oversight</span>
            </a>
        </li>
        <li>
            <a href="<?= url('admin/reviews.php') ?>" class="sidebar-link <?= $currentPage === 'reviews.php' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                <span>Review Moderation</span>
            </a>
        </li>

        <li class="sidebar-menu-title">Session</li>
        <li>
            <a href="<?= url('logout.php') ?>" class="sidebar-link" style="color:var(--danger-500);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <span>Sign Out</span>
            </a>
        </li>
    </ul>
</aside>
