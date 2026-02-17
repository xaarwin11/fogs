-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 17, 2026 at 11:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fogs`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `cat_type` enum('food','drink','other') DEFAULT 'food',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `cat_type`, `created_at`) VALUES
(1, 'Iced Coffee', 'drink', '2026-02-15 01:02:13'),
(2, 'Sandwich', 'food', '2026-02-15 01:02:13'),
(3, 'Rice Meal', 'food', '2026-02-15 01:02:13'),
(4, 'Hot Coffee', 'drink', '2026-02-15 01:02:13'),
(5, 'Frappe', 'drink', '2026-02-15 01:02:13'),
(6, 'Milktea', 'drink', '2026-02-15 01:02:13'),
(7, 'Drinks', 'drink', '2026-02-15 01:02:13'),
(8, 'Non-coffee', 'drink', '2026-02-15 01:02:13'),
(9, 'Pasta', 'food', '2026-02-15 01:02:13'),
(10, 'Soup', 'food', '2026-02-15 01:02:13'),
(11, 'Pica-pica', 'food', '2026-02-15 01:02:13'),
(12, 'Salad', 'food', '2026-02-15 01:02:13'),
(13, 'Others', 'food', '2026-02-15 01:02:13');

-- --------------------------------------------------------

--
-- Table structure for table `category_modifiers`
--

CREATE TABLE `category_modifiers` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `modifier_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `category_modifiers`
--

INSERT INTO `category_modifiers` (`category_id`, `modifier_id`) VALUES
(1, 1),
(1, 3),
(4, 1),
(4, 3),
(5, 2),
(5, 3),
(6, 2);

-- --------------------------------------------------------

--
-- Table structure for table `category_variations`
--

CREATE TABLE `category_variations` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `variation_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credentials`
--

CREATE TABLE `credentials` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL,
  `passcode` varchar(255) NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `hourly_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `credentials`
--

INSERT INTO `credentials` (`id`, `username`, `passcode`, `role_id`, `first_name`, `last_name`, `hourly_rate`, `created_at`, `updated_at`) VALUES
(1, 'Sharwin', '$2a$12$lvQ4pCQEVdmnWVS34UKTzuyTdTTqtbKzW2/35iv5qG77.8ioGm1Ii', 1, 'Sharwin', 'Tabila', 0.00, '2026-02-17 18:06:18', '2026-02-17 18:06:18');

-- --------------------------------------------------------

--
-- Table structure for table `discounts`
--

CREATE TABLE `discounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `target_type` enum('all','highest','food','drink','custom') NOT NULL DEFAULT 'all',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `table_id` int(10) UNSIGNED DEFAULT NULL,
  `discount_id` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `discount_total` decimal(10,2) DEFAULT 0.00,
  `discount_note` varchar(50) DEFAULT NULL,
  `grand_total` decimal(10,2) DEFAULT 0.00,
  `reference` varchar(64) DEFAULT NULL,
  `hidden_in_kds` tinyint(1) NOT NULL DEFAULT 0,
  `paid_at` datetime DEFAULT NULL,
  `checked_out_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `table_id`, `discount_id`, `status`, `subtotal`, `discount_total`, `discount_note`, `grand_total`, `reference`, `hidden_in_kds`, `paid_at`, `checked_out_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'open', 1000.00, 0.00, '', 1000.00, NULL, 0, NULL, NULL, '2026-02-17 18:09:36', '2026-02-17 18:15:47');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `unique_key` varchar(255) NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `variation_id` int(10) UNSIGNED DEFAULT NULL,
  `discount_id` int(10) UNSIGNED DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `base_price` decimal(10,2) NOT NULL,
  `modifier_total` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `discount_note` varchar(255) DEFAULT NULL,
  `line_total` decimal(10,2) NOT NULL,
  `served` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `kitchen_printed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `unique_key`, `order_id`, `product_id`, `variation_id`, `discount_id`, `quantity`, `base_price`, `modifier_total`, `discount_amount`, `discount_note`, `line_total`, `served`, `kitchen_printed`, `created_at`) VALUES
(1, 'p77_v0_m0', 1, 77, NULL, NULL, 1, 1000.00, 0.00, 0.00, '', 1000.00, 0, 1, '2026-02-17 18:15:47');

-- --------------------------------------------------------

--
-- Table structure for table `order_item_modifiers`
--

CREATE TABLE `order_item_modifiers` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `modifier_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(64) DEFAULT NULL,
  `change_given` decimal(10,2) DEFAULT NULL,
  `processed_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `printers`
--

CREATE TABLE `printers` (
  `id` int(10) UNSIGNED NOT NULL,
  `printer_label` varchar(100) NOT NULL,
  `connection_type` enum('usb','lan') NOT NULL,
  `path` varchar(255) NOT NULL,
  `character_limit` int(11) DEFAULT 48,
  `port` int(11) DEFAULT 9100,
  `beep_on_print` tinyint(1) DEFAULT 0,
  `cut_after_print` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `printers`
--

INSERT INTO `printers` (`id`, `printer_label`, `connection_type`, `path`, `character_limit`, `port`, `beep_on_print`, `cut_after_print`, `is_active`) VALUES
(1, 'Main Receipt Printer', 'lan', '192.168.0.7', 48, 9100, 0, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `has_variation` tinyint(1) NOT NULL DEFAULT 0,
  `available` tinyint(1) NOT NULL DEFAULT 1,
  `kds` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `price`, `has_variation`, `available`, `kds`, `created_at`, `updated_at`) VALUES
(1, 1, 'Iced Americano', 115.00, 1, 1, 0, '2026-02-15 01:02:13', '2026-02-16 22:32:22'),
(2, 1, 'Iced Butterscotch Latte', 130.00, 1, 1, 0, '2026-02-15 01:02:13', '2026-02-16 22:32:19'),
(3, 1, 'Iced Capuccino', 125.00, 1, 1, 0, '2026-02-15 01:02:13', '2026-02-16 22:32:27'),
(4, 1, 'Iced Caramel Latte', 130.00, 1, 1, 0, '2026-02-15 01:02:13', '2026-02-16 22:32:29'),
(5, 1, 'Iced Hazelnut Late', 130.00, 1, 1, 0, '2026-02-15 01:02:13', '2026-02-16 22:32:24'),
(6, 1, 'Iced Latte', 125.00, 1, 1, 0, '2026-02-15 01:02:13', '2026-02-16 22:32:25'),
(7, 1, 'Iced Mocha Latte', 130.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(8, 1, 'Iced Roasted Almond Latte', 130.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(9, 1, 'Iced Salted Caramel Latte', 130.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(10, 1, 'Iced Spanish Latte', 115.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(11, 1, 'Iced White Mocha Latte', 130.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(12, 2, 'Bikini', 80.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(13, 2, 'Clubhouse', 120.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(14, 2, 'Clubhouse Gourmet', 160.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(15, 3, 'Bangus w/ Egg', 140.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(16, 3, 'Longanisa w/ Egg', 140.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(17, 3, 'Tocino w/ Egg', 180.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(18, 3, 'Hotdog w/ Egg', 130.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(19, 4, 'Americano', 75.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(20, 4, 'Butterscotch Latte', 105.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(21, 4, 'Cafe Bombon', 70.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(22, 4, 'Cafe Carajillo', 110.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(23, 4, 'Cafe Corto', 60.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(24, 4, 'Cafe Latte', 90.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(25, 4, 'Cafe Mocha', 105.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(26, 4, 'Cafe Trifasico', 110.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(27, 4, 'Cafe White Mocha', 105.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(28, 4, 'Capuccino', 90.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(29, 4, 'Caramel Latte', 105.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(30, 4, 'Cortado', 65.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(31, 4, 'Hazelnut Late', 105.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(32, 4, 'Roasted Almond Latte', 105.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(33, 4, 'Salted Caramel Latte', 105.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(34, 4, 'Spanish Latte', 105.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(35, 5, 'Avocado Frappe', 150.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(36, 5, 'Chocolate F', 120.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(37, 5, 'Cookies&Cream F', 115.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(38, 5, 'Dark Chocolate F', 120.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(39, 5, 'Gourmet C&C F', 145.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(40, 5, 'Gourmet F', 140.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(41, 5, 'Matcha F', 145.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(42, 5, 'Matcha-Berry F', 160.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(43, 5, 'Red Velvet F', 120.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(44, 5, 'Salted Caramel F', 125.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(45, 5, 'Strawberry F', 145.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(46, 5, 'Taro F', 120.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(47, 6, 'Cheesecake MT', 110.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(48, 6, 'Chocolate MT', 90.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(49, 6, 'Cookies and Cream MT', 90.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(50, 6, 'Dark Chocolate MT', 95.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(51, 6, 'Double Dutch MT', 100.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(52, 6, 'Hokkaido MT', 110.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(53, 6, 'Java Chips MT', 95.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(54, 6, 'Matcha MT', 110.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(55, 6, 'Okinawa MT', 110.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(56, 6, 'Red Velvet MT', 95.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(57, 6, 'Salted Caramel MT', 100.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(58, 6, 'Strawberry MT', 90.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(59, 6, 'Taro MT', 95.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(60, 6, 'Wintermelon MT', 95.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(61, 8, 'Black Tea', 75.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(62, 8, 'Chamomile Tea', 75.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(63, 8, 'Green Tea', 75.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(64, 8, 'Hot Matcha Latte', 100.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(65, 8, 'Iced Matcha', 125.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(66, 8, 'Iced Matcha-berry', 135.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(67, 8, 'Iced Strawberry Latte', 125.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(68, 8, 'Hot Matcha Coffee', 125.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(69, 8, 'Iced Matcha Coffee', 150.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(70, 9, 'Tuna Pasta', 190.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(71, 9, 'Garlic&Parsley Pasta', 170.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(72, 9, 'Bolognese Pasta', 190.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(73, 9, 'Meat Lasagna', 200.00, 0, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(74, 10, 'Vegetable Soup', 140.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(75, 10, 'Sinigang na Hipon', 220.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(76, 10, 'Sinigang na Malaga', 250.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(77, 10, 'Sinigang Mix', 0.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(78, 11, 'Fish&Chips', 180.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(79, 11, 'Fries', 65.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(80, 11, 'Croquettes', 100.00, 0, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(81, 11, 'Sauteed Squid', 120.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(82, 11, 'Calamari', 120.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(83, 11, 'Chicken Wings', 140.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(84, 12, 'Green Salad w/ Tuna', 140.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(85, 12, 'Pasta Salad', 130.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(86, 7, 'Pale Pilsen', 65.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(87, 7, 'San Miguel Light', 70.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(88, 7, 'San Miguel Apple', 63.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(89, 7, 'Red Horse', 70.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(90, 7, 'Alfonso Light', 290.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(91, 7, 'Alfonso Solera', 405.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(92, 7, 'Emperador Light', 185.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(93, 7, 'Gin Mojito', 155.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(94, 7, 'Gin Strawberry Cocktail', 150.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(95, 7, 'Gin Tonic Cocktail', 180.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(96, 7, 'Coca-Cola', 15.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(97, 7, 'Coca-Cola Zero', 50.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(98, 7, 'Sprite', 15.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(99, 7, 'Royal Orange', 15.00, 1, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(100, 7, 'Royal Lemon', 15.00, 0, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `product_modifiers`
--

CREATE TABLE `product_modifiers` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_modifiers`
--

INSERT INTO `product_modifiers` (`id`, `product_id`, `name`, `price`) VALUES
(1, NULL, 'Macchiato', 5.00),
(2, NULL, 'Extra Pearls', 25.00),
(3, NULL, 'Extra Shot', 25.00);

-- --------------------------------------------------------

--
-- Table structure for table `product_variations`
--

CREATE TABLE `product_variations` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_variations`
--

INSERT INTO `product_variations` (`id`, `product_id`, `name`, `price`) VALUES
(1, 1, 'Regular', 115.00),
(2, 1, 'Large', 135.00),
(3, 2, 'Regular', 130.00),
(4, 2, 'Large', 150.00),
(5, 3, 'Regular', 125.00),
(6, 3, 'Large', 145.00),
(7, 4, 'Regular', 130.00),
(8, 4, 'Large', 150.00),
(9, 5, 'Regular', 130.00),
(10, 5, 'Large', 150.00),
(11, 6, 'Regular', 125.00),
(12, 6, 'Large', 145.00),
(13, 7, 'Regular', 130.00),
(14, 7, 'Large', 150.00),
(15, 8, 'Regular', 130.00),
(16, 8, 'Large', 150.00),
(17, 9, 'Regular', 130.00),
(18, 9, 'Large', 150.00),
(19, 10, 'Regular', 115.00),
(20, 10, 'Large', 140.00),
(21, 11, 'Regular', 130.00),
(22, 11, 'Large', 150.00),
(23, 19, 'Regular', 75.00),
(24, 19, 'Large', 90.00),
(25, 24, 'Regular', 90.00),
(26, 24, 'Large', 100.00),
(27, 20, 'Regular', 105.00),
(28, 20, 'Large', 120.00),
(29, 25, 'Regular', 105.00),
(30, 25, 'Large', 120.00),
(31, 27, 'Regular', 105.00),
(32, 27, 'Large', 120.00),
(33, 28, 'Regular', 90.00),
(34, 28, 'Large', 100.00),
(35, 29, 'Regular', 105.00),
(36, 29, 'Large', 120.00),
(37, 31, 'Regular', 105.00),
(38, 31, 'Large', 120.00),
(39, 32, 'Regular', 105.00),
(40, 32, 'Large', 120.00),
(41, 33, 'Regular', 105.00),
(42, 33, 'Large', 120.00),
(43, 34, 'Regular', 105.00),
(44, 34, 'Large', 120.00),
(45, 35, 'Regular', 150.00),
(46, 35, 'Large', 175.00),
(47, 36, 'Regular', 120.00),
(48, 36, 'Large', 140.00),
(49, 37, 'Regular', 115.00),
(50, 37, 'Large', 135.00),
(51, 38, 'Regular', 120.00),
(52, 38, 'Large', 140.00),
(53, 39, 'Regular', 145.00),
(54, 39, 'Large', 170.00),
(55, 40, 'Regular', 140.00),
(56, 40, 'Large', 170.00),
(57, 41, 'Regular', 145.00),
(58, 41, 'Large', 170.00),
(59, 42, 'Regular', 160.00),
(60, 42, 'Large', 185.00),
(61, 43, 'Regular', 120.00),
(62, 43, 'Large', 140.00),
(63, 44, 'Regular', 125.00),
(64, 44, 'Large', 145.00),
(65, 45, 'Regular', 145.00),
(66, 45, 'Large', 170.00),
(67, 46, 'Regular', 120.00),
(68, 46, 'Large', 140.00),
(69, 47, 'Regular', 110.00),
(70, 47, 'Large', 130.00),
(71, 48, 'Regular', 90.00),
(72, 48, 'Large', 110.00),
(73, 49, 'Regular', 90.00),
(74, 49, 'Large', 110.00),
(75, 50, 'Regular', 95.00),
(76, 50, 'Large', 115.00),
(77, 51, 'Regular', 100.00),
(78, 51, 'Large', 120.00),
(79, 52, 'Regular', 110.00),
(80, 52, 'Large', 130.00),
(81, 53, 'Regular', 95.00),
(82, 53, 'Large', 115.00),
(83, 54, 'Regular', 110.00),
(84, 54, 'Large', 130.00),
(85, 55, 'Regular', 110.00),
(86, 55, 'Large', 130.00),
(87, 56, 'Regular', 95.00),
(88, 56, 'Large', 115.00),
(89, 57, 'Regular', 100.00),
(90, 57, 'Large', 120.00),
(91, 58, 'Regular', 90.00),
(92, 58, 'Large', 110.00),
(93, 59, 'Regular', 95.00),
(94, 59, 'Large', 115.00),
(95, 60, 'Regular', 95.00),
(96, 60, 'Large', 115.00),
(97, 64, 'Regular', 100.00),
(98, 64, 'Large', 115.00),
(99, 65, 'Regular', 125.00),
(100, 65, 'Large', 155.00),
(101, 66, 'Regular', 135.00),
(102, 66, 'Large', 165.00),
(103, 67, 'Regular', 125.00),
(104, 67, 'Large', 155.00),
(105, 68, 'Regular', 125.00),
(106, 68, 'Large', 140.00),
(107, 69, 'Regular', 150.00),
(108, 69, 'Large', 170.00),
(109, 86, 'S', 65.00),
(110, 86, '1L', 140.00),
(111, 89, 'Stallion', 70.00),
(112, 89, '1L', 140.00),
(113, 90, '70cl', 290.00),
(114, 90, '1L', 335.00),
(115, 91, '70cl', 405.00),
(116, 91, '1L', 475.00),
(117, 92, '70cl', 185.00),
(118, 92, '1L', 235.00),
(119, 93, '70cl', 155.00),
(120, 93, '1L', 215.00),
(121, 96, '8oz', 15.00),
(122, 96, 'Can', 50.00),
(123, 96, '1L', 50.00),
(124, 96, '1.5L', 75.00),
(125, 97, 'Can', 50.00),
(126, 97, '1.5L', 75.00),
(127, 98, '8oz', 15.00),
(128, 98, '1L', 50.00),
(129, 98, '1.5L', 75.00),
(130, 99, '8oz', 15.00),
(131, 99, '1L', 50.00),
(132, 99, '1.5L', 75.00);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `description`) VALUES
(1, 'Admin', NULL),
(2, 'Manager', NULL),
(3, 'Staff', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `category` enum('business','pos','financial','hardware') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `category`) VALUES
('currency_symbol', '₱', 'pos'),
('route_bar', '1', 'hardware'),
('route_kitchen', '1', 'hardware'),
('route_receipt', '1', 'hardware'),
('store_address', 'San Esteban, Ilocos Sur', 'business'),
('store_name', 'FogsTasa\'s Cafe', 'business'),
('vat_rate', '0', 'financial');

-- --------------------------------------------------------

--
-- Table structure for table `tables`
--

CREATE TABLE `tables` (
  `id` int(10) UNSIGNED NOT NULL,
  `table_number` varchar(20) NOT NULL,
  `status` enum('available','dirty','reserved') NOT NULL DEFAULT 'available',
  `table_type` enum('physical','virtual') DEFAULT 'physical',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tables`
--

INSERT INTO `tables` (`id`, `table_number`, `status`, `table_type`, `created_at`) VALUES
(1, '1', '', 'physical', '2026-02-17 18:06:18'),
(2, '2', 'available', 'physical', '2026-02-17 18:06:18'),
(3, 'TO-1', 'available', 'virtual', '2026-02-17 18:06:18'),
(4, 'TO-2', 'available', 'virtual', '2026-02-17 18:06:18');

-- --------------------------------------------------------

--
-- Table structure for table `time_tracking`
--

CREATE TABLE `time_tracking` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `hours_worked` decimal(10,2) DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `category_modifiers`
--
ALTER TABLE `category_modifiers`
  ADD PRIMARY KEY (`category_id`,`modifier_id`),
  ADD KEY `fk_modifier_link` (`modifier_id`);

--
-- Indexes for table `category_variations`
--
ALTER TABLE `category_variations`
  ADD PRIMARY KEY (`category_id`,`variation_name`);

--
-- Indexes for table `credentials`
--
ALTER TABLE `credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `passcode` (`passcode`),
  ADD KEY `fk_user_role` (`role_id`);

--
-- Indexes for table `discounts`
--
ALTER TABLE `discounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_orders_table` (`table_id`),
  ADD KEY `fk_orders_discount` (`discount_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_orderitems_order` (`order_id`),
  ADD KEY `fk_orderitems_discount` (`discount_id`);

--
-- Indexes for table `order_item_modifiers`
--
ALTER TABLE `order_item_modifiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_oim_item` (`order_item_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_payments_order` (`order_id`);

--
-- Indexes for table `printers`
--
ALTER TABLE `printers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_product_category` (`category_id`);

--
-- Indexes for table `product_modifiers`
--
ALTER TABLE `product_modifiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_modifier_product` (`product_id`);

--
-- Indexes for table `product_variations`
--
ALTER TABLE `product_variations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_variation_product` (`product_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `table_number` (`table_number`);

--
-- Indexes for table `time_tracking`
--
ALTER TABLE `time_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_time_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `credentials`
--
ALTER TABLE `credentials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `discounts`
--
ALTER TABLE `discounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_item_modifiers`
--
ALTER TABLE `order_item_modifiers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `printers`
--
ALTER TABLE `printers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `product_modifiers`
--
ALTER TABLE `product_modifiers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `product_variations`
--
ALTER TABLE `product_variations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tables`
--
ALTER TABLE `tables`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `time_tracking`
--
ALTER TABLE `time_tracking`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `category_modifiers`
--
ALTER TABLE `category_modifiers`
  ADD CONSTRAINT `fk_category_link` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_modifier_link` FOREIGN KEY (`modifier_id`) REFERENCES `product_modifiers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `category_variations`
--
ALTER TABLE `category_variations`
  ADD CONSTRAINT `fk_cat_var_link` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `credentials`
--
ALTER TABLE `credentials`
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_discount` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_table` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_orderitems_discount` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orderitems_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_item_modifiers`
--
ALTER TABLE `order_item_modifiers`
  ADD CONSTRAINT `fk_oim_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_modifiers`
--
ALTER TABLE `product_modifiers`
  ADD CONSTRAINT `fk_modifier_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variations`
--
ALTER TABLE `product_variations`
  ADD CONSTRAINT `fk_variation_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `time_tracking`
--
ALTER TABLE `time_tracking`
  ADD CONSTRAINT `fk_time_user` FOREIGN KEY (`user_id`) REFERENCES `credentials` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
