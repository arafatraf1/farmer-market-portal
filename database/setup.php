<?php
/**
 * Farmer Market Portal - Database Auto Setup & Seed
 * Can be run via CLI (`php database/setup.php`) or accessed via web browser.
 */

require_once __DIR__ . '/../config/config.php';

$isCli = (php_sapi_name() === 'cli');

function out(string $msg, bool $isCli, string $type = 'info'): void {
    if ($isCli) {
        $prefix = match($type) {
            'success' => '[SUCCESS] ',
            'error'   => '[ERROR] ',
            default   => '[INFO] '
        };
        echo $prefix . $msg . PHP_EOL;
    } else {
        $color = match($type) {
            'success' => '#16a34a',
            'error'   => '#dc2626',
            default   => '#2563eb'
        };
        echo "<div style='font-family:sans-serif;margin:4px 0;padding:8px 12px;border-radius:6px;background:#f8fafc;border-left:4px solid {$color};'><strong>{$msg}</strong></div>";
    }
}

try {
    // 1. Connect to MySQL server without DB specified
    $dsnNoDb = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
    $pdo = new PDO($dsnNoDb, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    out('Connected to MySQL server successfully.', $isCli, 'info');

    // 2. Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `" . DB_NAME . "`;");
    out('Database `' . DB_NAME . '` selected.', $isCli, 'info');

    // 3. Execute Schema
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schemaSql);

    // Auto-migration: ensure master_product_id column exists on products
    try {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `master_product_id` INT NULL AFTER `category_id`");
    } catch (Exception $ignored) {
        // Column already exists
    }
    out('Database schema tables and migrations verified successfully.', $isCli, 'success');

    // 4. Create directories for storage
    $directories = [
        STORAGE_PATH,
        UPLOAD_PATH,
        UPLOAD_PATH . '/products',
        UPLOAD_PATH . '/avatars',
        SECURE_DOC_PATH
    ];
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    // Secure docs htaccess
    file_put_contents(SECURE_DOC_PATH . '/.htaccess', "Deny from all\n");
    out('Storage and secure document directories verified.', $isCli, 'info');

    // 5. Create sample mock verification document
    $mockNidPath = SECURE_DOC_PATH . '/sample_karim_nid.txt';
    if (!file_exists($mockNidPath)) {
        file_put_contents($mockNidPath, "CONFIDENTIAL IDENTITY VERIFICATION DOCUMENT\nHolder: Mohammad Karim\nDoc Type: National ID (NID)\nNID Number: 19842614920000831\nAddress: Karim Agro Farm, Bogura, Bangladesh\nStatus: Submitted for Verification Review.");
    }

    // 6. Seed Categories
    $categories = [
        [
            'category_name' => 'Vegetables',
            'slug' => 'vegetables',
            'description' => 'Crisp, organically cultivated fresh greens, root vegetables, and herbs direct from local farms.',
            'icon' => 'carrot',
            'image' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=600&q=80'
        ],
        [
            'category_name' => 'Fruits',
            'slug' => 'fruits',
            'description' => 'Naturally tree-ripened, pesticide-free seasonal and exotic fruits packed with pure nutrients.',
            'icon' => 'apple',
            'image' => 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?auto=format&fit=crop&w=600&q=80'
        ],
        [
            'category_name' => 'Meat & Poultry',
            'slug' => 'meat',
            'description' => 'Free-range, antibiotic-free grass-fed meats, country chicken, and beef prepared with strict hygiene.',
            'icon' => 'drumstick',
            'image' => 'https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?auto=format&fit=crop&w=600&q=80'
        ],
        [
            'category_name' => 'Farm Fresh Eggs',
            'slug' => 'eggs',
            'description' => 'Free-range country chicken and duck eggs gathered daily at dawn with bright golden yolks.',
            'icon' => 'egg',
            'image' => 'https://images.unsplash.com/photo-1582722872445-44dc5f7e3c8f?auto=format&fit=crop&w=600&q=80'
        ],
        [
            'category_name' => 'Freshwater Fish',
            'slug' => 'fish',
            'description' => 'Sustainably farmed freshwater Rui, Katla, Hilsa, and Tilapia harvested to order.',
            'icon' => 'fish',
            'image' => 'https://images.unsplash.com/photo-1534483509719-3feaee7c30da?auto=format&fit=crop&w=600&q=80'
        ],
        [
            'category_name' => 'Dairy Products',
            'slug' => 'dairy',
            'description' => 'Pure raw cow milk, organic cultured yogurt, and artisan churned pure desi ghee.',
            'icon' => 'milk',
            'image' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=600&q=80'
        ],
        [
            'category_name' => 'Grains & Cereals',
            'slug' => 'grains',
            'description' => 'Stone-ground whole wheat, aromatic Miniket rice, premium Basmati, and sun-dried corn.',
            'icon' => 'wheat',
            'image' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=600&q=80'
        ]
    ];

    $stmtCat = $pdo->prepare("INSERT INTO categories (category_name, slug, description, icon, image) 
        VALUES (:category_name, :slug, :description, :icon, :image)
        ON DUPLICATE KEY UPDATE description = VALUES(description), image = VALUES(image)");
    foreach ($categories as $cat) {
        $stmtCat->execute($cat);
    }
    out('Categories populated (' . count($categories) . ' categories).', $isCli, 'info');

    // 7. Seed Users (Admin, Rahim - Verified Farmer, Karim - Pending Farmer, Anita - Buyer)
    $passwordAdmin = password_hash('admin123', PASSWORD_BCRYPT);
    $passwordFarmer = password_hash('farmer123', PASSWORD_BCRYPT);
    $passwordBuyer = password_hash('buyer123', PASSWORD_BCRYPT);

    $users = [
        [
            'name' => 'Platform Administrator',
            'email' => 'admin@farmermarket.com',
            'phone' => '+880 1711-000001',
            'password' => $passwordAdmin,
            'role' => 'admin',
            'address' => 'Agritech Center, Level 8, Dhaka',
            'profile_image' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=300&q=80',
            'status' => 'active'
        ],
        [
            'name' => 'Abdur Rahim',
            'email' => 'farmer.rahim@farmermarket.com',
            'phone' => '+880 1812-345678',
            'password' => $passwordFarmer,
            'role' => 'farmer',
            'address' => 'Green Valley Farm, Savar, Dhaka',
            'profile_image' => 'https://images.unsplash.com/photo-1595273670150-bd0c3c392e46?auto=format&fit=crop&w=300&q=80',
            'status' => 'active'
        ],
        [
            'name' => 'Mohammad Karim',
            'email' => 'farmer.karim@farmermarket.com',
            'phone' => '+880 1913-987654',
            'password' => $passwordFarmer,
            'role' => 'farmer',
            'address' => 'Karim Agro Fishery, Sherpur Road, Bogura',
            'profile_image' => 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=300&q=80',
            'status' => 'active'
        ],
        [
            'name' => 'Anita Chowdhury',
            'email' => 'buyer.anita@farmermarket.com',
            'phone' => '+880 1614-554433',
            'password' => $passwordBuyer,
            'role' => 'buyer',
            'address' => 'House 14, Road 5, Dhanmondi, Dhaka',
            'profile_image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=300&q=80',
            'status' => 'active'
        ]
    ];

    $stmtUser = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, address, profile_image, status)
        VALUES (:name, :email, :phone, :password, :role, :address, :profile_image, :status)
        ON DUPLICATE KEY UPDATE name=VALUES(name), phone=VALUES(phone), address=VALUES(address)");
    foreach ($users as $u) {
        $stmtUser->execute($u);
    }
    out('Core users populated (Admin, Verified Farmer Rahim, Pending Farmer Karim, Buyer Anita).', $isCli, 'info');

    // Get user IDs
    $stmtGetId = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    
    $stmtGetId->execute(['farmer.rahim@farmermarket.com']);
    $rahimUserId = (int)$stmtGetId->fetchColumn();

    $stmtGetId->execute(['farmer.karim@farmermarket.com']);
    $karimUserId = (int)$stmtGetId->fetchColumn();

    $stmtGetId->execute(['buyer.anita@farmermarket.com']);
    $anitaUserId = (int)$stmtGetId->fetchColumn();

    // 8. Seed Farmers table
    // Rahim: Verified Farmer with 4.9 rating
    $stmtFarmer = $pdo->prepare("INSERT INTO farmers (user_id, farm_name, farm_location, bio, verification_status, verification_doc_type, verification_doc_path, farmer_rating, total_reviews)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE verification_status=VALUES(verification_status), farm_name=VALUES(farm_name), farmer_rating=VALUES(farmer_rating)");
    $stmtFarmer->execute([
        $rahimUserId,
        'Green Valley Organic Farm',
        'Savar, Dhaka',
        'Generational organic farmer dedicated to producing 100% pesticide-free vegetables, heirloom fruits, and grass-fed dairy.',
        'verified',
        'nid',
        'sample_rahim_nid.txt',
        4.90,
        14
    ]);

    // Karim: Pending Farmer awaiting Admin approval
    $stmtFarmer->execute([
        $karimUserId,
        'Karim Agro & Sustainable Fisheries',
        'Sherpur, Bogura',
        'Specializing in eco-friendly pond-farmed sweet freshwater fishes and farm fresh poultry eggs.',
        'pending',
        'nid',
        'sample_karim_nid.txt',
        0.00,
        0
    ]);
    out('Farmer profiles created (Rahim: Verified, Karim: Pending Verification).', $isCli, 'info');

    // 9. Seed Buyers table
    $stmtBuyer = $pdo->prepare("INSERT INTO buyers (user_id, buyer_rating, completed_orders, cancelled_orders)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE buyer_rating=VALUES(buyer_rating), completed_orders=VALUES(completed_orders)");
    $stmtBuyer->execute([
        $anitaUserId,
        5.00,
        4,
        0
    ]);
    out('Buyer profile initialized for Anita (100% completion rate).', $isCli, 'info');

    // Get farmer_id and buyer_id
    $rahimFarmerId = (int)$pdo->query("SELECT farmer_id FROM farmers WHERE user_id = {$rahimUserId}")->fetchColumn();
    $karimFarmerId = (int)$pdo->query("SELECT farmer_id FROM farmers WHERE user_id = {$karimUserId}")->fetchColumn();
    $anitaBuyerId = (int)$pdo->query("SELECT buyer_id FROM buyers WHERE user_id = {$anitaUserId}")->fetchColumn();

    // Category IDs
    $catIds = [];
    $stmtCats = $pdo->query("SELECT category_id, slug FROM categories");
    while ($row = $stmtCats->fetch()) {
        $catIds[$row['slug']] = (int)$row['category_id'];
    }

    // 10. Seed Master Products (Admin Official Product & Price Master Catalog)
    $masterProducts = [
        [
            'category_id' => $catIds['vegetables'],
            'product_name' => 'Organic Red Tomatoes',
            'official_price' => 50.00,
            'unit' => 'kg',
            'description' => 'Vine-ripened, juicy red organic tomatoes. Ideal for healthy salads, rich gravies, and soups.',
            'image' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=600&q=80',
            'status' => 'active'
        ],
        [
            'category_id' => $catIds['vegetables'],
            'product_name' => 'Crisp Garden Cucumbers',
            'official_price' => 35.00,
            'unit' => 'kg',
            'description' => 'Crisp and hydrating naturally grown cucumbers without bitter ends. Freshly handpicked.',
            'image' => 'https://images.unsplash.com/photo-1449300079323-02e209d9d3a6?auto=format&fit=crop&w=600&q=80',
            'status' => 'active'
        ],
        [
            'category_id' => $catIds['vegetables'],
            'product_name' => 'Native Red Potatoes',
            'official_price' => 40.00,
            'unit' => 'kg',
            'description' => 'Locally harvested smooth-skin native potatoes. Perfect starch balance for boiling, roasting, and curry dishes.',
            'image' => 'https://images.unsplash.com/photo-1518977676601-b53f82aba655?auto=format&fit=crop&w=600&q=80',
            'status' => 'active'
        ],
        [
            'category_id' => $catIds['fruits'],
            'product_name' => 'Sweet Amrapali Mangoes',
            'official_price' => 120.00,
            'unit' => 'kg',
            'description' => 'Tree-ripened, naturally sweet fragrant Amrapali mangoes with fiberless buttery pulp.',
            'image' => 'https://images.unsplash.com/photo-1553279768-865429fa0078?auto=format&fit=crop&w=600&q=80',
            'status' => 'active'
        ],
        [
            'category_id' => $catIds['fruits'],
            'product_name' => 'Sagor Banana Bunches',
            'official_price' => 50.00,
            'unit' => 'dozen',
            'description' => 'Naturally ripened Sagor bananas, chemical and carbide free. Packed with immediate energy.',
            'image' => 'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?auto=format&fit=crop&w=600&q=80',
            'status' => 'active'
        ],
        [
            'category_id' => $catIds['eggs'],
            'product_name' => 'Pasture-Raised Country Chicken Eggs',
            'official_price' => 140.00,
            'unit' => 'dozen',
            'description' => 'Fresh brown country chicken eggs gathered daily from foraging hens. Deep orange yolks.',
            'image' => 'https://images.unsplash.com/photo-1506976785307-8732e854ad03?auto=format&fit=crop&w=600&q=80',
            'status' => 'active'
        ],
        [
            'category_id' => $catIds['dairy'],
            'product_name' => 'Pure Grass-Fed Whole Cow Milk',
            'official_price' => 80.00,
            'unit' => 'liter',
            'description' => 'Unadulterated raw whole milk bottled straight from healthy pasture-grazed dairy cows.',
            'image' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=600&q=80',
            'status' => 'active'
        ],
        [
            'category_id' => $catIds['dairy'],
            'product_name' => 'Traditional Cultured Ghee',
            'official_price' => 1200.00,
            'unit' => 'packet',
            'description' => 'Slow-cooked artisan ghee prepared from pure curd butter with a rich golden hue.',
            'image' => 'https://images.unsplash.com/photo-1628088062854-d1870b4553da?auto=format&fit=crop&w=600&q=80',
            'status' => 'active'
        ],
        [
            'category_id' => $catIds['grains'],
            'product_name' => 'Aromatic Premium Kataribhog Rice',
            'official_price' => 60.00,
            'unit' => 'kg',
            'description' => 'Heirloom long-grain aromatic rice grown organically in Dinajpur paddies. Fluffy texture.',
            'image' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=600&q=80',
            'status' => 'active'
        ],
        [
            'category_id' => $catIds['fish'],
            'product_name' => 'Freshwater Sweet Rui Fish',
            'official_price' => 380.00,
            'unit' => 'kg',
            'description' => 'Sustainably farmed freshwater sweet Rui fish harvested to order directly from eco ponds.',
            'image' => 'https://images.unsplash.com/photo-1534483509719-3feaee7c30da?auto=format&fit=crop&w=600&q=80',
            'status' => 'active'
        ],
        [
            'category_id' => $catIds['vegetables'],
            'product_name' => 'Crisp Garden Spinach',
            'official_price' => 30.00,
            'unit' => 'kg',
            'description' => 'Organically cultivated leafy greens packed with iron and minerals.',
            'image' => 'https://images.unsplash.com/photo-1576045057995-568f588f82fb?auto=format&fit=crop&w=600&q=80',
            'status' => 'active'
        ]
    ];

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE order_items;");
    $pdo->exec("TRUNCATE TABLE delivery;");
    $pdo->exec("TRUNCATE TABLE reviews;");
    $pdo->exec("TRUNCATE TABLE orders;");
    $pdo->exec("TRUNCATE TABLE products;");
    $pdo->exec("TRUNCATE TABLE master_products;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    $stmtMaster = $pdo->prepare("INSERT INTO master_products (category_id, product_name, official_price, unit, description, image, status)
        VALUES (:category_id, :product_name, :official_price, :unit, :description, :image, :status)");

    foreach ($masterProducts as $mp) {
        $stmtMaster->execute($mp);
    }
    out('Populated ' . count($masterProducts) . ' Master Products with Admin-controlled official prices.', $isCli, 'success');

    // Fetch master product IDs and official prices
    $masterMap = [];
    $stmtMMap = $pdo->query("SELECT master_product_id, product_name, category_id, official_price, unit, image FROM master_products");
    while ($mRow = $stmtMMap->fetch()) {
        $masterMap[$mRow['product_name']] = $mRow;
    }

    // 11. Seed Products for Farmers (Linked to Master Products with Official Price)
    $today = new DateTimeImmutable('today');
    $p_veg = $today->sub(new DateInterval('P1D'))->format('Y-m-d');
    $p_veg_exp = $today->add(new DateInterval('P6D'))->format('Y-m-d'); // 6 days left (fresh)
    $p_warning_exp = $today->add(new DateInterval('P2D'))->format('Y-m-d'); // 2 days left (warning)
    $p_expired_exp = $today->sub(new DateInterval('P2D'))->format('Y-m-d'); // 2 days ago (expired)

    $products = [
        [
            'farmer_id' => $rahimFarmerId,
            'master_name' => 'Organic Red Tomatoes',
            'description' => 'Vine-ripened, juicy red organic tomatoes plucked fresh from Savar farm beds.',
            'quantity' => 60,
            'harvest_date' => $p_veg,
            'expiry_date' => $p_veg_exp,
            'availability' => 'available'
        ],
        [
            'farmer_id' => $rahimFarmerId,
            'master_name' => 'Crisp Garden Cucumbers',
            'description' => 'Crisp and hydrating naturally grown cucumbers without bitter ends. Freshly handpicked.',
            'quantity' => 45,
            'harvest_date' => $today->sub(new DateInterval('P2D'))->format('Y-m-d'),
            'expiry_date' => $p_warning_exp,
            'availability' => 'available'
        ],
        [
            'farmer_id' => $rahimFarmerId,
            'master_name' => 'Native Red Potatoes',
            'description' => 'Locally harvested smooth-skin native potatoes from Savar rich alluvial soils.',
            'quantity' => 120,
            'harvest_date' => $today->sub(new DateInterval('P5D'))->format('Y-m-d'),
            'expiry_date' => $today->add(new DateInterval('P25D'))->format('Y-m-d'),
            'availability' => 'available'
        ],
        [
            'farmer_id' => $rahimFarmerId,
            'master_name' => 'Sweet Amrapali Mangoes',
            'description' => 'Tree-ripened, naturally sweet fragrant Amrapali mangoes with fiberless buttery pulp.',
            'quantity' => 35,
            'harvest_date' => $today->sub(new DateInterval('P1D'))->format('Y-m-d'),
            'expiry_date' => $today->add(new DateInterval('P5D'))->format('Y-m-d'),
            'availability' => 'available'
        ],
        [
            'farmer_id' => $rahimFarmerId,
            'master_name' => 'Sagor Banana Bunches',
            'description' => 'Naturally ripened Sagor bananas, chemical and carbide free.',
            'quantity' => 25,
            'harvest_date' => $today->sub(new DateInterval('P3D'))->format('Y-m-d'),
            'expiry_date' => $today->add(new DateInterval('P3D'))->format('Y-m-d'),
            'availability' => 'available'
        ],
        [
            'farmer_id' => $rahimFarmerId,
            'master_name' => 'Pasture-Raised Country Chicken Eggs',
            'description' => 'Fresh brown country chicken eggs gathered daily from foraging hens.',
            'quantity' => 40,
            'harvest_date' => $today->format('Y-m-d'),
            'expiry_date' => $today->add(new DateInterval('P14D'))->format('Y-m-d'),
            'availability' => 'available'
        ],
        [
            'farmer_id' => $rahimFarmerId,
            'master_name' => 'Pure Grass-Fed Whole Cow Milk',
            'description' => 'Unadulterated raw whole milk bottled straight from healthy pasture-grazed cows.',
            'quantity' => 30,
            'harvest_date' => $today->format('Y-m-d'),
            'expiry_date' => $today->add(new DateInterval('P2D'))->format('Y-m-d'),
            'availability' => 'available'
        ],
        [
            'farmer_id' => $rahimFarmerId,
            'master_name' => 'Traditional Cultured Ghee',
            'description' => 'Slow-cooked artisan ghee prepared from pure curd butter with a rich golden hue.',
            'quantity' => 15,
            'harvest_date' => $today->sub(new DateInterval('P10D'))->format('Y-m-d'),
            'expiry_date' => $today->add(new DateInterval('P90D'))->format('Y-m-d'),
            'availability' => 'available'
        ],
        [
            'farmer_id' => $rahimFarmerId,
            'master_name' => 'Aromatic Premium Kataribhog Rice',
            'description' => 'Heirloom long-grain aromatic rice grown organically in Dinajpur paddies.',
            'quantity' => 80,
            'harvest_date' => $today->sub(new DateInterval('P30D'))->format('Y-m-d'),
            'expiry_date' => $today->add(new DateInterval('P180D'))->format('Y-m-d'),
            'availability' => 'available'
        ],
        [
            'farmer_id' => $rahimFarmerId,
            'master_name' => 'Crisp Garden Spinach',
            'description' => 'Demonstration expired batch to test portal freshness expiration protection.',
            'quantity' => 0,
            'harvest_date' => $today->sub(new DateInterval('P10D'))->format('Y-m-d'),
            'expiry_date' => $p_expired_exp,
            'availability' => 'expired'
        ]
    ];

    $stmtProd = $pdo->prepare("INSERT INTO products (farmer_id, category_id, master_product_id, product_name, description, price, unit, quantity, image, harvest_date, expiry_date, availability)
        VALUES (:farmer_id, :category_id, :master_product_id, :product_name, :description, :price, :unit, :quantity, :image, :harvest_date, :expiry_date, :availability)");

    foreach ($products as $p) {
        $mItem = $masterMap[$p['master_name']] ?? null;
        if (!$mItem) continue;

        $stmtProd->execute([
            ':farmer_id' => $p['farmer_id'],
            ':category_id' => $mItem['category_id'],
            ':master_product_id' => $mItem['master_product_id'],
            ':product_name' => $mItem['product_name'],
            ':description' => $p['description'],
            ':price' => $mItem['official_price'], // Strict official price from master product
            ':unit' => $mItem['unit'],
            ':quantity' => $p['quantity'],
            ':image' => $mItem['image'],
            ':harvest_date' => $p['harvest_date'],
            ':expiry_date' => $p['expiry_date'],
            ':availability' => $p['availability']
        ]);
    }
    out('Populated ' . count($products) . ' farmer product listings strictly synced to official admin pricing.', $isCli, 'success');

    // 12. Seed an existing completed order for Anita from Rahim with a review
    $stmtGetProd = $pdo->prepare("SELECT product_id, price FROM products WHERE product_name = ? LIMIT 1");
    
    $stmtGetProd->execute(['Organic Red Tomatoes']);
    $tomatoProd = $stmtGetProd->fetch();
    
    $stmtGetProd->execute(['Pure Grass-Fed Whole Cow Milk']);
    $milkProd = $stmtGetProd->fetch();

    $orderCode1 = 'FMP-COMP-1001';
    $orderDate1 = $today->sub(new DateInterval('P4D'))->format('Y-m-d H:i:s');
    
    $tomatoQty = 2; // 2 kg @ 50 = 100
    $milkQty = 3;   // 3 L @ 80 = 240
    $total1 = ($tomatoProd['price'] * $tomatoQty) + ($milkProd['price'] * $milkQty); // 340.00 BDT

    $stmtOrder = $pdo->prepare("INSERT INTO orders (order_code, buyer_id, farmer_id, order_date, delivery_address, total_amount, estimated_delivery_time, order_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtOrder->execute([
        $orderCode1,
        $anitaBuyerId,
        $rahimFarmerId,
        $orderDate1,
        'House 14, Road 5, Dhanmondi, Dhaka',
        $total1,
        'Delivered on Time',
        'completed'
    ]);
    $order1Id = (int)$pdo->lastInsertId();

    // Order items
    $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
    $stmtItem->execute([$order1Id, $tomatoProd['product_id'], $tomatoQty, $tomatoProd['price'], $tomatoProd['price'] * $tomatoQty]);
    $stmtItem->execute([$order1Id, $milkProd['product_id'], $milkQty, $milkProd['price'], $milkProd['price'] * $milkQty]);

    // Delivery tracking for completed order
    $stmtDel = $pdo->prepare("INSERT INTO delivery (order_id, status, tracking_notes, estimated_delivery, actual_delivery) VALUES (?, ?, ?, ?, ?)");
    $stmtDel->execute([
        $order1Id,
        'completed',
        'Delivered successfully in chilled farm crates and verified by buyer Anita.',
        $today->sub(new DateInterval('P4D'))->format('Y-m-d 15:00:00'),
        $today->sub(new DateInterval('P4D'))->format('Y-m-d 14:30:00')
    ]);

    // Review by Anita for Rahim
    $stmtRev = $pdo->prepare("INSERT INTO reviews (buyer_id, farmer_id, product_id, order_id, rating, review_text, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtRev->execute([
        $anitaBuyerId,
        $rahimFarmerId,
        $tomatoProd['product_id'],
        $order1Id,
        5,
        'Incredible freshness! The organic tomatoes tasted like real sun-kissed garden produce, and the raw cow milk had thick cream on top. Rahim is a trustworthy verified farmer. 10/10 service!',
        'active'
    ]);

    // 13. Seed an active order currently "out_for_delivery" so Anita can test real-time tracking
    $orderCode2 = 'FMP-TRK-1002';
    $orderDate2 = $today->format('Y-m-d 08:30:00');
    
    $stmtGetProd->execute(['Pasture-Raised Country Chicken Eggs']);
    $eggProd = $stmtGetProd->fetch();
    
    $stmtGetProd->execute(['Sweet Amrapali Mangoes']);
    $mangoProd = $stmtGetProd->fetch();

    $eggQty = 2; // 2 dozen @ 140 = 280
    $mangoQty = 3; // 3 kg @ 120 = 360
    $total2 = ($eggProd['price'] * $eggQty) + ($mangoProd['price'] * $mangoQty); // 640.00 BDT

    $stmtOrder->execute([
        $orderCode2,
        $anitaBuyerId,
        $rahimFarmerId,
        $orderDate2,
        'House 14, Road 5, Dhanmondi, Dhaka',
        $total2,
        'Today by 6:00 PM',
        'out_for_delivery'
    ]);
    $order2Id = (int)$pdo->lastInsertId();

    $stmtItem->execute([$order2Id, $eggProd['product_id'], $eggQty, $eggProd['price'], $eggProd['price'] * $eggQty]);
    $stmtItem->execute([$order2Id, $mangoProd['product_id'], $mangoQty, $mangoProd['price'], $mangoProd['price'] * $mangoQty]);

    $stmtDel->execute([
        $order2Id,
        'out_for_delivery',
        'Farm dispatch van #04 has left Savar depot and is en route via Mirpur Road to Dhanmondi.',
        $today->format('Y-m-d 18:00:00'),
        null
    ]);

    out('Sample completed and in-transit tracking orders initialized.', $isCli, 'success');
    out('ALL SETUP COMPLETED SUCCESSFULLY! Farmer Market Portal is ready.', $isCli, 'success');

} catch (Exception $e) {
    out('Setup failed: ' . $e->getMessage(), $isCli, 'error');
    if ($isCli) {
        exit(1);
    }
}
