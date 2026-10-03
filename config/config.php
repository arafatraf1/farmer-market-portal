<?php
/**
 * Farmer Market Portal - Configuration
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Environment & Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'farmer_market_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application URL configuration
define('APP_NAME', 'Farmer Market Portal');
define('APP_TAGLINE', 'Fresh From Farmers, Directly to You');

// Compute root URL dynamically
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$rootUrl = rtrim($scriptName, '/');
// If running from subdirectory, adjust
if ($rootUrl === '' || $rootUrl === '/') {
    define('BASE_URL', '');
} else {
    // If inside admin, farmer, buyer, api, config
    $parts = explode('/', trim($rootUrl, '/'));
    $subfolders = ['admin', 'farmer', 'buyer', 'api', 'config', 'database'];
    while (!empty($parts) && in_array(end($parts), $subfolders)) {
        array_pop($parts);
    }
    $cleanRoot = implode('/', $parts);
    define('BASE_URL', $cleanRoot ? '/' . $cleanRoot : '');
}

// Physical paths
define('ROOT_PATH', dirname(__DIR__));
define('STORAGE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'storage');
define('UPLOAD_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('SECURE_DOC_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'secure_docs');

// Accepted units for agricultural products
define('PRODUCT_UNITS', [
    'kg' => 'Kilogram (kg)',
    'gram' => 'Gram (g)',
    'dozen' => 'Dozen',
    'piece' => 'Piece',
    'liter' => 'Liter (L)',
    'packet' => 'Packet'
]);

// Order status progression
define('ORDER_STATUSES', [
    'pending' => 'Pending Confirmation',
    'accepted' => 'Order Accepted',
    'preparing' => 'Preparing Produce',
    'ready_for_delivery' => 'Ready for Delivery',
    'out_for_delivery' => 'Out for Delivery',
    'delivered' => 'Delivered to Buyer',
    'completed' => 'Completed & Confirmed',
    'cancelled' => 'Cancelled',
    'rejected' => 'Rejected by Farmer'
]);
