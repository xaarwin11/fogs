-- Full Unified DB schema for FOGS application (Finalized Feb 2026)
DROP DATABASE IF EXISTS `fogs`;
CREATE DATABASE `fogs` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fogs`;

-- 1. Roles & Credentials
CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `credentials` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL UNIQUE,
  `passcode` VARCHAR(255) NOT NULL UNIQUE,
  `role_id` INT UNSIGNED NOT NULL,
  `first_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) DEFAULT NULL,
  `hourly_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB;

-- 2. Menu Structure
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `cat_type` ENUM('food', 'drink', 'other') DEFAULT 'food',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `has_variation` TINYINT(1) NOT NULL DEFAULT 0,
  `available` TINYINT(1) NOT NULL DEFAULT 1,
  `kds` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `product_variations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL, 
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_variation_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- THE FIX: product_id is now NULLABLE to allow "Global Modifiers"
CREATE TABLE `product_modifiers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_modifier_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- THE BRIDGE: Links Modifiers to entire Categories
CREATE TABLE `category_modifiers` (
  `category_id` INT UNSIGNED NOT NULL,
  `modifier_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`category_id`, `modifier_id`),
  CONSTRAINT `fk_category_link` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_modifier_link` FOREIGN KEY (`modifier_id`) REFERENCES `product_modifiers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Dining Tables
CREATE TABLE `tables` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_number` VARCHAR(20) NOT NULL UNIQUE, 
  `status` ENUM('available', 'dirty', 'reserved') NOT NULL DEFAULT 'available',
  `table_type` ENUM('physical', 'virtual') DEFAULT 'physical',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `discounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,           -- e.g. "Senior Citizen", "Employee Meal"
  `type` ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent',
  `value` DECIMAL(10,2) NOT NULL DEFAULT 0.00, -- e.g. 20.00
  `target_type` ENUM('all', 'highest', 'food', 'drink', 'custom') NOT NULL DEFAULT 'all',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- 4. Orders & Items
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_id` INT UNSIGNED DEFAULT NULL,
  `discount_id` INT UNSIGNED DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'open',
  `subtotal` DECIMAL(10,2) DEFAULT 0.00,       
  `discount_total` DECIMAL(10,2) DEFAULT 0.00, 
  `discount_note` VARCHAR(50) DEFAULT NULL,
  `grand_total` DECIMAL(10,2) DEFAULT 0.00,    
  `reference` VARCHAR(64) DEFAULT NULL,
  `hidden_in_kds` TINYINT(1) NOT NULL DEFAULT 0,
  `paid_at` DATETIME DEFAULT NULL,
  `checked_out_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_orders_table` FOREIGN KEY (`table_id`) REFERENCES `tables`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_discount` FOREIGN KEY (`discount_id`) REFERENCES `discounts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unique_key` VARCHAR(255) NOT NULL, 
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variation_id` INT UNSIGNED DEFAULT NULL,
  `discount_id` INT UNSIGNED DEFAULT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `base_price` DECIMAL(10,2) NOT NULL,      
  `modifier_total` DECIMAL(10,2) DEFAULT 0.00, 
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00, 
  `discount_note` VARCHAR(255) DEFAULT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  `served` INT UNSIGNED NOT NULL DEFAULT 0,
  `kitchen_printed` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_orderitems_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orderitems_discount` FOREIGN KEY (`discount_id`) REFERENCES `discounts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `order_item_modifiers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_item_id` INT UNSIGNED NOT NULL,
  `modifier_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL, 
  `price` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_oim_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Time Tracking
CREATE TABLE `time_tracking` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `clock_in` DATETIME DEFAULT NULL,
  `clock_out` DATETIME DEFAULT NULL,
  `hours_worked` DECIMAL(10,2) DEFAULT NULL,
  `date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_time_user` FOREIGN KEY (`user_id`) REFERENCES `credentials`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Payments
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` VARCHAR(64) DEFAULT NULL,
  `change_given` DECIMAL(10,2) DEFAULT NULL,
  `processed_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Printers
CREATE TABLE `printers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `printer_label` VARCHAR(100) NOT NULL,
  `connection_type` ENUM('usb', 'lan') NOT NULL,
  `path` VARCHAR(255) NOT NULL, 
  `character_limit` INT DEFAULT 48,
  `port` INT DEFAULT 9100,      
  `beep_on_print` TINYINT(1) DEFAULT 0,
  `cut_after_print` TINYINT(1) DEFAULT 1,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- 8. System Settings
CREATE TABLE `system_settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT,
  `category` ENUM('business', 'pos', 'financial', 'hardware') NOT NULL
) ENGINE=InnoDB;

CREATE TABLE `category_variations` (
  `category_id` INT UNSIGNED NOT NULL,
  `variation_name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`category_id`, `variation_name`),
  CONSTRAINT `fk_cat_var_link` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO `discounts` (`name`, `type`, `value`, `target_type`) VALUES 
('Senior/PWD', 'percent', 20.00, 'highest'), 
('Employee Meal', 'percent', 50.00, 'food'),
('Promo (Fixed)', 'fixed', 100.00, 'all');

-- --- SEED DATA ---
INSERT INTO `roles` (`role_name`) VALUES ('Admin'), ('Manager'), ('Staff');
INSERT INTO `categories` (`name`, `cat_type`) VALUES ('Iced Coffee', 'drink'), ('Sandwich', 'food'), ('Rice Meal', 'food');
INSERT INTO `products` (`category_id`, `name`, `price`, `has_variation`, `available`, `kds`) VALUES
(1, 'Iced Caramel Latte', 130.00, 0, 1, 1),
(1, 'Iced Americano', 115.00, 0, 1, 1),
(2, 'Clubhouse', 120.00, 0, 1, 1),
(2, 'Clubhouse Gourmet', 90.00, 0, 1, 1),
(3, 'Longanisa w/ Egg', 180.00, 0, 1, 1),
(3, 'Tocino w/ Egg', 180.00, 0, 1, 1);
INSERT INTO product_variations (`product_id`, `name`, `price`) VALUES
(1, 'Regular', 130.00), (1, 'Large', 150.00),
(2, 'Regular', 150.00), (2, 'Large', 130.00);

INSERT INTO `printers` (`printer_label`, `connection_type`, `path`, `is_active`) VALUES
('Main Receipt Printer', 'usb', 'VOZY-80', 1);

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `category`) VALUES
('store_name', 'FOGS RESTAURANT', 'business'),
('store_address', 'San Esteban, Ilocos Region', 'business'),
('vat_rate', '0', 'financial'), 
('currency_symbol', '₱', 'pos');

INSERT INTO `tables` (`table_number`, `status`, `table_type`) VALUES 
('1', 'available', 'physical'), ('2', 'available', 'physical'),
('TO-1', 'available', 'virtual'), ('TO-2', 'available', 'virtual');

INSERT INTO `credentials` (`username`, `passcode`, `role_id`, `first_name`, `last_name`) 
VALUES ('Sharwin', '$2a$12$lvQ4pCQEVdmnWVS34UKTzuyTdTTqtbKzW2/35iv5qG77.8ioGm1Ii', 1, 'Sharwin', 'Tabila');