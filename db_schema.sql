-- Full DB schema for FOGS application (fresh install)
-- This script drops and recreates the `fogssystem` database. Run only
-- when you intend a clean install (it will destroy existing data).

DROP DATABASE IF EXISTS `fogs`;
CREATE DATABASE `fogs` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fogs`;

-- Credentials / users table
CREATE TABLE `credentials` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(32) NOT NULL DEFAULT 'staff',
  `first_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dining tables
CREATE TABLE `tables` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_number` INT NOT NULL UNIQUE,
  `occupied` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(32) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `available` TINYINT(1) NOT NULL DEFAULT 1,
  `kds` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_available` (`available`),
  KEY `idx_kds` (`kds`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_id` INT UNSIGNED DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'open',
  `reference` VARCHAR(64) DEFAULT NULL,
  `hidden_in_kds` TINYINT(1) NOT NULL DEFAULT 0,
  `paid_at` DATETIME DEFAULT NULL,
  `checked_out_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_table_id` (`table_id`),
  KEY `idx_reference` (`reference`),
  CONSTRAINT `fk_orders_table` FOREIGN KEY (`table_id`) REFERENCES `tables`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_checked_by` FOREIGN KEY (`checked_out_by`) REFERENCES `credentials`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order items

CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `served` INT UNSIGNED NOT NULL DEFAULT 0,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_order_product` (`order_id`, `product_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `fk_orderitems_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orderitems_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Time tracking / timesheets
CREATE TABLE `time_tracking` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `clock_in` DATETIME DEFAULT NULL,
  `clock_out` DATETIME DEFAULT NULL,
  `hours_worked` DECIMAL(5,2) DEFAULT NULL,
  `date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`date`),
  CONSTRAINT `fk_time_user` FOREIGN KEY (`user_id`) REFERENCES `credentials`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments (optional audit table)
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` VARCHAR(64) DEFAULT NULL,
  `change_given` DECIMAL(10,2) DEFAULT NULL,
  `processed_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pay_order` (`order_id`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_by` FOREIGN KEY (`processed_by`) REFERENCES `credentials`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data (replace password hash with a real hash produced by password_hash())
INSERT INTO `credentials` (`username`, `password`, `role`, `first_name`, `last_name`) VALUES ('admin', '$2y$10$REPLACE_WITH_HASH', 'admin', 'Site', 'Admin');

INSERT INTO `tables` (`table_number`, `occupied`) VALUES (1,0),(2,0),(3,0),(4,0),(5,0);

-- Example products
INSERT INTO `products` (`name`,`category`,`price`,`available`,`kds`) VALUES
('Plain Rice','Sides',30.00,1,0),
('Adobo','Main',120.00,1,1),
('Sinigang','Main',140.00,1,1),
('Iced Tea','Drinks',45.00,1,0);

-- End of fresh-install schema
-- Helpful sample data (optional)
-- INSERT INTO `credentials` (`username`,`password`,`role`,`first_name`,`last_name`) VALUES ('admin', '<PASSWORD_HASH>', 'admin', 'Site', 'Admin');

-- End of schema
