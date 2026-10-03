<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
$currentUser = current_user();
$cartCount = get_cart_count();
$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME . ' — ' . APP_TAGLINE;
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="Direct marketplace connecting local farmers and buyers for 100% fresh agricultural produce with verified identities and delivery tracking.">
    
    <!-- Design System CSS -->
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/dashboards.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/tracking.css') ?>">

    <script>
        window.APP_BASE_URL = '<?= BASE_URL ?>';
    </script>
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <!-- Brand Logo -->
        <a href="<?= url('index.php') ?>" class="brand-logo">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"></path>
                <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"></path>
            </svg>
            <span>Farmer<span class="highlight">Market</span></span>
        </a>

        <!-- Universal Search Bar -->
        <form action="<?= url('browse.php') ?>" method="GET" class="header-search">
            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="q" placeholder="Search fresh vegetables, fruits, dairy, fish..." value="<?= e($_GET['q'] ?? '') ?>">
        </form>

        <!-- Navigation Links -->
        <nav class="main-nav">
            <a href="<?= url('index.php') ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">Home</a>
            <a href="<?= url('browse.php') ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'browse.php' ? 'active' : '' ?>">Browse Produce</a>
            <a href="<?= url('about.php') ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : '' ?>">About & Trust</a>
            
            <?php if ($currentUser): ?>
                <?php if ($currentUser['role'] === 'farmer'): ?>
                    <a href="<?= url('farmer/dashboard.php') ?>" class="nav-link">Farmer Portal</a>
                <?php elseif ($currentUser['role'] === 'buyer'): ?>
                    <a href="<?= url('buyer/dashboard.php') ?>" class="nav-link">Buyer Dashboard</a>
                <?php elseif ($currentUser['role'] === 'admin'): ?>
                    <a href="<?= url('admin/dashboard.php') ?>" class="nav-link">Admin Console</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <!-- Header Actions (Cart, Profile, Login/Register) -->
        <div class="header-actions">
            <!-- Cart Button -->
            <a href="<?= url('cart.php') ?>" class="cart-btn" title="View Shopping Cart">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="8" cy="21" r="1"></circle>
                    <circle cx="19" cy="21" r="1"></circle>
                    <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
                </svg>
                <span class="cart-badge" style="<?= $cartCount > 0 ? '' : 'display:none;' ?>"><?= $cartCount ?></span>
            </a>

            <?php if ($currentUser): ?>
                <!-- User Profile Dropdown -->
                <div class="user-menu-wrapper">
                    <button class="user-menu-btn" id="userMenuBtn" type="button">
                        <img src="<?= e($currentUser['profile_image'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80') ?>" alt="Avatar" class="user-avatar-sm">
                        <span style="font-weight:600; font-size:13.5px;"><?= e(explode(' ', $currentUser['name'])[0]) ?></span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="user-menu-dropdown" id="userMenuDropdown">
                        <div class="dropdown-header">
                            <div class="user-name"><?= e($currentUser['name']) ?></div>
                            <div class="user-role"><?= e($currentUser['role']) ?> account</div>
                        </div>

                        <?php if ($currentUser['role'] === 'farmer'): ?>
                            <a href="<?= url('farmer/dashboard.php') ?>" class="dropdown-item">Dashboard Overview</a>
                            <a href="<?= url('farmer/products.php') ?>" class="dropdown-item">Manage Products</a>
                            <a href="<?= url('farmer/orders.php') ?>" class="dropdown-item">Customer Orders</a>
                            <a href="<?= url('farmer/verification.php') ?>" class="dropdown-item">Verification Status</a>
                            <a href="<?= url('farmer/profile.php') ?>" class="dropdown-item">Farm Profile</a>
                        <?php elseif ($currentUser['role'] === 'buyer'): ?>
                            <a href="<?= url('buyer/dashboard.php') ?>" class="dropdown-item">Buyer Dashboard</a>
                            <a href="<?= url('buyer/orders.php') ?>" class="dropdown-item">My Orders & Tracking</a>
                            <a href="<?= url('buyer/reviews.php') ?>" class="dropdown-item">Farmer Reviews</a>
                            <a href="<?= url('buyer/profile.php') ?>" class="dropdown-item">Delivery Profile</a>
                        <?php elseif ($currentUser['role'] === 'admin'): ?>
                            <a href="<?= url('admin/dashboard.php') ?>" class="dropdown-item">Admin Dashboard</a>
                            <a href="<?= url('admin/verification.php') ?>" class="dropdown-item">Farmer Verifications</a>
                            <a href="<?= url('admin/pricing.php') ?>" class="dropdown-item">Product & Price Control</a>
                            <a href="<?= url('admin/products.php') ?>" class="dropdown-item">Farmer Listings</a>
                            <a href="<?= url('admin/users.php') ?>" class="dropdown-item">User Management</a>
                            <a href="<?= url('admin/categories.php') ?>" class="dropdown-item">Category Control</a>
                            <a href="<?= url('admin/orders.php') ?>" class="dropdown-item">All Orders</a>
                        <?php endif; ?>

                        <div class="dropdown-divider"></div>
                        <a href="<?= url('logout.php') ?>" class="dropdown-item" style="color:var(--danger-500);">Sign Out</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Guest Auth Links -->
                <a href="<?= url('login.php') ?>" class="btn btn-secondary btn-sm">Log In</a>
                <a href="<?= url('register.php') ?>" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php if ($currentUser): ?>
    <!-- Active Role Banner & Quick Switcher -->
    <div style="background: <?= $currentUser['role'] === 'admin' ? '#0f172a' : ($currentUser['role'] === 'farmer' ? '#14532d' : '#1e3a8a') ?>; color:#ffffff; padding:8px 16px; font-size:13px; border-bottom:1px solid rgba(255,255,255,0.15);">
        <div class="container" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:8px; font-weight:600;">
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <span>👑 <strong>ADMIN MODE</strong> &mdash; Logged in as Platform Super Admin (<?= e($currentUser['name']) ?>)</span>
                <?php elseif ($currentUser['role'] === 'farmer'): ?>
                    <span>🌱 <strong>FARMER MODE</strong> &mdash; Logged in as Farmer (<?= e($currentUser['name']) ?>)</span>
                <?php else: ?>
                    <span>🛒 <strong>BUYER MODE</strong> &mdash; Logged in as Buyer (<?= e($currentUser['name']) ?>)</span>
                <?php endif; ?>
            </div>

            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-sm" style="background:#334155; color:#ffffff; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">📊 Admin Control Center</a>
                    <a href="<?= url('admin/pricing.php') ?>" class="btn btn-sm" style="background:#22c55e; color:#0f172a; font-weight:700; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">⚖️ Product & Price Control</a>
                    <a href="<?= url('admin/verification.php') ?>" class="btn btn-sm" style="background:#f59e0b; color:#0f172a; font-weight:700; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">🛡️ Farmer Verifications (Accept/Reject)</a>
                    <a href="<?= url('admin/products.php') ?>" class="btn btn-sm" style="background:#334155; color:#ffffff; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">🌾 Farmer Listings</a>
                    <a href="<?= url('admin/users.php') ?>" class="btn btn-sm" style="background:#334155; color:#ffffff; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">👥 Users</a>
                <?php elseif ($currentUser['role'] === 'farmer'): ?>
                    <a href="<?= url('farmer/dashboard.php') ?>" class="btn btn-sm" style="background:#15803d; color:#ffffff; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">📊 Farmer Dashboard</a>
                    <a href="<?= url('farmer/product-add.php') ?>" class="btn btn-sm" style="background:#22c55e; color:#052e16; font-weight:700; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">+ Add Product (Fixed Admin Price)</a>
                    <a href="<?= url('farmer/products.php') ?>" class="btn btn-sm" style="background:#166534; color:#ffffff; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">Manage Listings</a>
                    <a href="<?= url('farmer/verification.php') ?>" class="btn btn-sm" style="background:#ca8a04; color:#ffffff; font-weight:700; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">🛡️ Verification Status</a>
                <?php else: ?>
                    <a href="<?= url('buyer/dashboard.php') ?>" class="btn btn-sm" style="background:#2563eb; color:#ffffff; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">📊 Buyer Dashboard</a>
                    <a href="<?= url('buyer/orders.php') ?>" class="btn btn-sm" style="background:#1d4ed8; color:#ffffff; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">📦 My Orders & Tracking</a>
                    <a href="<?= url('cart.php') ?>" class="btn btn-sm" style="background:#f59e0b; color:#0f172a; font-weight:700; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px;">🛒 Cart (<?= $cartCount ?>)</a>
                <?php endif; ?>
                <a href="<?= url('logout.php') ?>" class="btn btn-sm" style="background:rgba(239,68,68,0.25); color:#fca5a5; padding:4px 10px; font-size:12px; text-decoration:none; border-radius:6px; border:1px solid rgba(239,68,68,0.4);">Sign Out</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($flash): ?>
    <div class="flash-container">
        <div class="alert alert-<?= e($flash['type']) ?>">
            <span><?= e($flash['message']) ?></span>
        </div>
    </div>
<?php endif; ?>

<main class="main-content">
