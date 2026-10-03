</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"></path>
                        <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"></path>
                    </svg>
                    <span>Farmer Market Portal</span>
                </div>
                <p class="footer-desc">
                    Bridging direct connections between dedicated local agricultural farmers and mindful consumers. Fair prices for growers, fresher produce for families.
                </p>
                <div style="display:flex; gap:10px; align-items:center;">
                    <span class="verified-badge" style="background:#064e3b; color:#6ee7b7; border-color:#047857;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        NID & Identity Verified Farmers
                    </span>
                </div>
            </div>

            <div>
                <h4 class="footer-title">Produce Categories</h4>
                <ul class="footer-links">
                    <li><a href="<?= url('browse.php?category=vegetables') ?>">Fresh Vegetables</a></li>
                    <li><a href="<?= url('browse.php?category=fruits') ?>">Seasonal Fruits</a></li>
                    <li><a href="<?= url('browse.php?category=dairy') ?>">Farm Dairy & Ghee</a></li>
                    <li><a href="<?= url('browse.php?category=fish') ?>">Freshwater Fish</a></li>
                    <li><a href="<?= url('browse.php?category=grains') ?>">Organic Grains</a></li>
                </ul>
            </div>

            <div>
                <h4 class="footer-title">Platform & Roles</h4>
                <ul class="footer-links">
                    <li><a href="<?= url('browse.php') ?>">Browse Marketplace</a></li>
                    <li><a href="<?= url('register.php?role=farmer') ?>">Sell as a Farmer</a></li>
                    <li><a href="<?= url('register.php?role=buyer') ?>">Join as Buyer</a></li>
                    <li><a href="<?= url('about.php') ?>">Verification Standards</a></li>
                    <li><a href="<?= url('login.php') ?>">Sign In / Demo Logins</a></li>
                </ul>
            </div>

            <div>
                <h4 class="footer-title">Instant Demo Logins</h4>
                <div style="background:#1e293b; padding:12px 14px; border-radius:8px; font-size:12.5px; line-height:1.6; border:1px solid #334155;">
                    <div style="margin-bottom:6px;"><strong style="color:#34d399;">Admin:</strong> admin@farmermarket.com (admin123)</div>
                    <div style="margin-bottom:6px;"><strong style="color:#38bdf8;">Verified Farmer:</strong> farmer.rahim@farmermarket.com (farmer123)</div>
                    <div style="margin-bottom:6px;"><strong style="color:#fbbf24;">Pending Farmer:</strong> farmer.karim@farmermarket.com (farmer123)</div>
                    <div><strong style="color:#f472b6;">Buyer:</strong> buyer.anita@farmermarket.com (buyer123)</div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div>
                &copy; <?= date('Y') ?> Farmer Market Portal. All rights reserved. Dedicated to agricultural sustainability.
            </div>
            <div style="display:flex; gap:18px;">
                <a href="<?= url('about.php') ?>">Privacy & Data Safety</a>
                <a href="<?= url('about.php') ?>">Terms of Service</a>
                <a href="<?= url('about.php') ?>">Report Suspicious Activity</a>
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="<?= url('assets/js/main.js') ?>"></script>
<script src="<?= url('assets/js/cart.js') ?>"></script>
<script src="<?= url('assets/js/browse.js') ?>"></script>
<script src="<?= url('assets/js/tracking.js') ?>"></script>

</body>
</html>
