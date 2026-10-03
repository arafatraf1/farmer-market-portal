<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$pageTitle = 'About & Farmer Verification Standards';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 50px 20px 80px;">
    <!-- Title Area -->
    <div style="text-align:center; max-width:760px; margin:0 auto 50px;">
        <span class="verified-badge" style="font-size:13px; padding:6px 14px; margin-bottom:14px;">Direct Agricultural Transparency</span>
        <h1 style="font-size:38px; font-weight:800; color:var(--gray-900); margin-bottom:16px;">
            Connecting Farmers and Conscious Consumers
        </h1>
        <p style="font-size:17px; color:var(--gray-600); line-height:1.7;">
            Farmer Market Portal is an open, direct digital marketplace engineered to eliminate predatory middlemen, reward verified agricultural producers with fair prices, and supply households with uncompromised, freshly harvested produce.
        </p>
    </div>

    <!-- 3 Core Pillars Grid -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px; margin-bottom: 60px;">
        <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:32px; box-shadow:var(--shadow-sm);">
            <div style="width:52px; height:52px; background:#dcfce7; color:#15803d; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:20px;">🛡️</div>
            <h3 style="font-size:20px; font-weight:700; margin-bottom:12px;">Rigorous Identity Verification</h3>
            <p style="color:var(--gray-600); font-size:14.5px; line-height:1.7;">
                To prevent fraudulent brokers and fake sellers, every farmer must submit official National ID (NID) or Birth Certificate documentation. Our administrative team manually audits each document before issuing the <strong>Verified Farmer Badge</strong>.
            </p>
            <div style="margin-top:16px; font-size:13px; color:var(--primary-800); background:var(--primary-50); padding:10px 14px; border-radius:8px;">
                🔒 Identification documents are encrypted and stored in secure directories restricted from public web access.
            </div>
        </div>

        <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:32px; box-shadow:var(--shadow-sm);">
            <div style="width:52px; height:52px; background:#fef3c7; color:#b45309; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:20px;">🌿</div>
            <h3 style="font-size:20px; font-weight:700; margin-bottom:12px;">Automated Freshness Guard</h3>
            <p style="color:var(--gray-600); font-size:14.5px; line-height:1.7;">
                Agricultural produce is perishable. Our system mandates both <strong>Harvest Date</strong> and <strong>Expiry Date</strong> for every listing. When a product nears its shelf life, warning badges trigger automatically, and expired goods are instantly barred from checkout.
            </p>
        </div>

        <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:32px; box-shadow:var(--shadow-sm);">
            <div style="width:52px; height:52px; background:#dbeafe; color:#1d4ed8; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:20px;">⭐</div>
            <h3 style="font-size:20px; font-weight:700; margin-bottom:12px;">Verified Purchase Reviews Only</h3>
            <p style="color:var(--gray-600); font-size:14.5px; line-height:1.7;">
                We enforce strict anti-gaming review rules. Only buyers who have placed an order, received the goods, and verified completion can leave a 1-5 star review. One order equals one review, guaranteeing 100% authentic community sentiment.
            </p>
        </div>
    </div>

    <!-- Mission Banner -->
    <div style="background:linear-gradient(135deg, #14532d 0%, #15803d 100%); color:#ffffff; border-radius:20px; padding:50px 40px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:30px;">
        <div style="max-width:640px;">
            <h2 style="font-size:30px; font-weight:800; color:#ffffff; margin-bottom:12px;">Are you a local producer?</h2>
            <p style="color:#dcfce7; font-size:16px; line-height:1.6;">
                Get direct access to thousands of appreciative buyers. Register your farm, pass our simple identity verification, and start selling directly with zero middleman commissions.
            </p>
        </div>
        <div style="display:flex; gap:14px;">
            <a href="<?= url('register.php?role=farmer') ?>" class="btn btn-accent btn-lg">Join as a Farmer</a>
            <a href="<?= url('browse.php') ?>" class="btn btn-secondary btn-lg" style="background:transparent; color:#ffffff; border-color:rgba(255,255,255,0.4);">Browse Produce</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
