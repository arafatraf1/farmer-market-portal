# Farmer Market Portal

A web app that connects local farmers directly with buyers. No middlemen. Verified farmers. Fresh produce tracking.

> **Course project — Web Programming, United International University (UIU).**
> Built by a team of **5 members** (me + 4 teammates).

---

## What it does

**Public marketplace**
- Browse produce by category: vegetables, fruits, meat & poultry, eggs, fish, dairy, grains.
- Live AJAX search with filters: keyword, category, price, farmer rating, verified-only, freshness, location.
- Sorting: newest, price, rating, expiring soon.
- Freshness guard: shows days left from harvest/expiry date. Expired items can't be ordered.

**Farmer verification**
- Farmers upload a National ID or Birth Certificate.
- Files sit in `storage/secure_docs/` (blocked from direct URL access).
- Only admins can view them, through `admin/view-doc.php`.
- Approved farmers get a **Verified Farmer** badge.

**Order tracking**
- Flow: Placed → Accepted → Preparing → Ready for Delivery → Out for Delivery → Delivered → Completed.
- Farmers update each stage. Buyers confirm receipt.
- Stock goes down on order, and comes back if the order is cancelled or rejected.

**Buyer trust score**
- Completed vs. cancelled orders. Farmers see it before accepting a request.

**Verified-purchase reviews**
- Only buyers with a completed order can review.
- One order = one review.
- Admins can moderate.

**Three roles:** Buyer, Farmer, Admin — each with its own dashboard.

---

## Tech stack

| Layer | Tools |
| :--- | :--- |
| Frontend | HTML5, CSS3, vanilla JavaScript (AJAX) |
| Backend | PHP 8.2, PDO prepared statements, sessions |
| Database | MySQL / MariaDB (`farmer_market_db`) |
| Security | BCRYPT password hashing, role-based access, input sanitization, protected document storage |
| Local server | XAMPP (MySQL) + PHP built-in server |

---

## How to run

1. **Start MySQL** (port 3306), for example from XAMPP.
2. **Check DB settings** in `config/config.php` (default: user `root`, empty password).
3. **Set up the database** (one time). This creates the tables and demo data:
   ```bash
   php database/setup.php
   ```
   Or open `http://localhost:8000/database/setup.php` after step 4.
4. **Start the server** from the project root:
   ```bash
   php -S localhost:8000
   ```
5. Open **http://localhost:8000**

---

## Demo accounts

| Role | Email | Password |
| :--- | :--- | :--- |
| Admin | `admin@farmermarket.com` | `admin123` |
| Verified farmer | `farmer.rahim@farmermarket.com` | `farmer123` |
| Pending farmer | `farmer.karim@farmermarket.com` | `farmer123` |
| Buyer | `buyer.anita@farmermarket.com` | `buyer123` |

The login page also has one-click demo sign-in buttons.

---

## Project structure

```
farmer-market-portal/
├── index.php               # Homepage
├── browse.php              # Marketplace catalog
├── product-details.php     # Product page
├── farmer-profile.php      # Public farm profile
├── about.php               # About + verification standards
├── cart.php                # Shopping cart
├── checkout.php            # Purchase request form
├── login.php / logout.php  # Auth
├── register.php            # Sign-up (buyer or farmer)
│
├── admin/                  # Dashboard, verification, users, categories,
│                           # products, pricing, orders, reviews, view-doc
├── farmer/                 # Dashboard, product CRUD, orders, reviews,
│                           # verification, profile
├── buyer/                  # Dashboard, orders, order tracking, reviews,
│                           # favorites, profile
├── api/                    # AJAX endpoints: search, cart, orders,
│                           # reviews, favorites
│
├── config/                 # config.php (constants), db.php (PDO), helpers.php
├── database/               # schema.sql, setup.php (installer + demo data)
├── includes/               # header, footer, role sidebars
├── assets/
│   ├── css/                # style.css, dashboards.css, tracking.css
│   └── js/                 # main.js, browse.js, cart.js, tracking.js
└── storage/
    ├── uploads/            # avatars/, products/ (user uploads)
    └── secure_docs/        # ID documents (private, .htaccess protected)
```

---

## Team

Web Programming project, UIU — 5 members in total.

| Name | Student ID | Role |
| :--- | :--- | :--- |
| _Your name_ | _ID_ | _e.g. Backend_ |
| _Member 2_ | _ID_ | _..._ |
| _Member 3_ | _ID_ | _..._ |
| _Member 4_ | _ID_ | _..._ |
| _Member 5_ | _ID_ | _..._ |

## Notes

- Demo credentials are for local testing only. Change them before any real deployment.
- `database/setup.php` should be removed or locked on a live server.
