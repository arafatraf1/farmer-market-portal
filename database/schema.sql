-- Farmer Market Portal Database Schema
-- Compatible with MySQL 8.0+ and MariaDB 10.4+

CREATE DATABASE IF NOT EXISTS `farmer_market_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `farmer_market_db`;

-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(30) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('buyer', 'farmer', 'admin') NOT NULL DEFAULT 'buyer',
    `address` TEXT NULL,
    `profile_image` VARCHAR(255) NULL,
    `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table: farmers
CREATE TABLE IF NOT EXISTS `farmers` (
    `farmer_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `farm_name` VARCHAR(150) NOT NULL,
    `farm_location` VARCHAR(150) NOT NULL,
    `bio` TEXT NULL,
    `verification_status` ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    `verification_doc_type` ENUM('nid', 'birth_certificate') NOT NULL DEFAULT 'nid',
    `verification_doc_path` VARCHAR(255) NULL,
    `admin_notes` TEXT NULL,
    `farmer_rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `total_reviews` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_farmer_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table: buyers
CREATE TABLE IF NOT EXISTS `buyers` (
    `buyer_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `buyer_rating` DECIMAL(3,2) NOT NULL DEFAULT 5.00,
    `completed_orders` INT NOT NULL DEFAULT 0,
    `cancelled_orders` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_buyer_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table: categories
CREATE TABLE IF NOT EXISTS `categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `icon` VARCHAR(50) DEFAULT 'leaf',
    `image` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table: master_products (Admin Official Product & Price Master Catalog)
CREATE TABLE IF NOT EXISTS `master_products` (
    `master_product_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `product_name` VARCHAR(150) NOT NULL UNIQUE,
    `official_price` DECIMAL(10,2) NOT NULL,
    `unit` VARCHAR(20) NOT NULL DEFAULT 'kg',
    `description` TEXT NULL,
    `image` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_master_price` CHECK (`official_price` > 0),
    CONSTRAINT `fk_master_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Table: products (Farmer Inventory Listings linked to Master Products)
CREATE TABLE IF NOT EXISTS `products` (
    `product_id` INT AUTO_INCREMENT PRIMARY KEY,
    `farmer_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `master_product_id` INT NULL,
    `product_name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `unit` VARCHAR(20) NOT NULL DEFAULT 'kg',
    `quantity` INT NOT NULL DEFAULT 0,
    `image` VARCHAR(255) NULL,
    `harvest_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `availability` ENUM('available', 'unavailable', 'expired') NOT NULL DEFAULT 'available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_product_price` CHECK (`price` > 0),
    CONSTRAINT `chk_product_quantity` CHECK (`quantity` >= 0),
    CONSTRAINT `fk_product_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmers`(`farmer_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_product_master` FOREIGN KEY (`master_product_id`) REFERENCES `master_products`(`master_product_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table: orders
CREATE TABLE IF NOT EXISTS `orders` (
    `order_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(30) NOT NULL UNIQUE,
    `buyer_id` INT NOT NULL,
    `farmer_id` INT NOT NULL,
    `order_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `delivery_address` TEXT NOT NULL,
    `delivery_instructions` TEXT NULL,
    `requested_delivery_date` DATE NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `estimated_delivery_time` VARCHAR(100) DEFAULT '1-2 Days',
    `order_status` ENUM(
        'pending', 
        'accepted', 
        'preparing', 
        'ready_for_delivery', 
        'out_for_delivery', 
        'delivered', 
        'completed', 
        'cancelled', 
        'rejected'
    ) NOT NULL DEFAULT 'pending',
    `cancellation_reason` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_order_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyers`(`buyer_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmers`(`farmer_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table: order_items
CREATE TABLE IF NOT EXISTS `order_items` (
    `order_item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `chk_item_quantity` CHECK (`quantity` > 0),
    CONSTRAINT `fk_order_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_item_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table: delivery
CREATE TABLE IF NOT EXISTS `delivery` (
    `delivery_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL UNIQUE,
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
    `tracking_notes` TEXT NULL,
    `estimated_delivery` DATETIME NULL,
    `actual_delivery` DATETIME NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_delivery_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table: reviews
CREATE TABLE IF NOT EXISTS `reviews` (
    `review_id` INT AUTO_INCREMENT PRIMARY KEY,
    `buyer_id` INT NOT NULL,
    `farmer_id` INT NOT NULL,
    `product_id` INT NULL,
    `order_id` INT NOT NULL,
    `rating` INT NOT NULL,
    `review_text` TEXT NOT NULL,
    `status` ENUM('active', 'reported', 'removed') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_review_rating` CHECK (`rating` BETWEEN 1 AND 5),
    CONSTRAINT `uq_order_review` UNIQUE (`buyer_id`, `order_id`),
    CONSTRAINT `fk_review_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyers`(`buyer_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_review_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmers`(`farmer_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table: favorites
CREATE TABLE IF NOT EXISTS `favorites` (
    `favorite_id` INT AUTO_INCREMENT PRIMARY KEY,
    `buyer_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_buyer_favorite` UNIQUE (`buyer_id`, `product_id`),
    CONSTRAINT `fk_favorite_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyers`(`buyer_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_favorite_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB;
