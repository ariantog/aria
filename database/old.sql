-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 07, 2026 at 04:53 AM
-- Server version: 11.8.8-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u343060430_coreId`
--

-- --------------------------------------------------------

--
-- Table structure for table `acl`
--

CREATE TABLE `acl` (
  `role_id` int(3) NOT NULL,
  `action` varchar(50) NOT NULL,
  `app_id` int(3) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `advices`
--

CREATE TABLE `advices` (
  `problem_id` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alert`
--

CREATE TABLE `alert` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `expires` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alertrules`
--

CREATE TABLE `alertrules` (
  `id` int(11) NOT NULL,
  `type` int(3) NOT NULL,
  `entityId` int(11) NOT NULL,
  `condition` varchar(3) NOT NULL,
  `value` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

CREATE TABLE `app_settings` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aproduksi`
--

CREATE TABLE `aproduksi` (
  `id` int(11) NOT NULL,
  `potong_in` date DEFAULT NULL,
  `jahit_in` date DEFAULT NULL,
  `jahit_out` date DEFAULT NULL,
  `permak_in` date DEFAULT NULL,
  `permak_out` date DEFAULT NULL,
  `item_id` int(11) NOT NULL,
  `jahit` varchar(10) NOT NULL,
  `customer` varchar(25) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(5) NOT NULL,
  `potong_id` int(5) NOT NULL,
  `quantity` int(3) NOT NULL,
  `warna` varchar(255) NOT NULL,
  `temp_name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `q_in` int(3) NOT NULL DEFAULT 0,
  `q_out` int(3) NOT NULL DEFAULT 0,
  `size_id` int(3) NOT NULL,
  `urgent` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `invoice` varchar(20) NOT NULL,
  `detail_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `balance_trackers`
--

CREATE TABLE `balance_trackers` (
  `customer_id` int(11) NOT NULL,
  `transaction_ids` text NOT NULL,
  `partial_id` int(11) NOT NULL,
  `partial_balance` decimal(20,2) NOT NULL,
  `partial_due` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `borongan`
--

CREATE TABLE `borongan` (
  `id` int(11) NOT NULL,
  `group` varchar(10) NOT NULL,
  `total` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `detail_ids` text NOT NULL,
  `user_id` int(11) NOT NULL,
  `tres` int(11) NOT NULL,
  `permak` int(11) NOT NULL,
  `lain2` int(11) NOT NULL,
  `total_items` int(5) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `from` date DEFAULT NULL,
  `to` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `borongandetail`
--

CREATE TABLE `borongandetail` (
  `id` int(11) NOT NULL,
  `borongan_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `ongkos` int(5) NOT NULL,
  `quantity` int(5) NOT NULL,
  `total` int(11) NOT NULL,
  `produksi_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cron`
--

CREATE TABLE `cron` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` int(2) NOT NULL,
  `address` text NOT NULL,
  `description` text NOT NULL,
  `phone` varchar(20) NOT NULL,
  `phone2` varchar(20) NOT NULL,
  `email` varchar(50) NOT NULL,
  `fax` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `category` tinyint(1) NOT NULL DEFAULT 0,
  `discount` decimal(5,2) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `return_p` decimal(5,2) NOT NULL,
  `contract_ends` date DEFAULT NULL,
  `parent_id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL DEFAULT 0,
  `province_id` int(11) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `memberId` varchar(32) NOT NULL,
  `password` varchar(10) NOT NULL,
  `portalId` int(11) NOT NULL,
  `ppn` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customerstat`
--

CREATE TABLE `customerstat` (
  `customer_id` int(11) NOT NULL,
  `balance` decimal(20,2) NOT NULL DEFAULT 0.00,
  `rating` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_class`
--

CREATE TABLE `customer_class` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `customer_type` int(3) NOT NULL,
  `date` date DEFAULT NULL,
  `rating` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sell` decimal(20,2) NOT NULL DEFAULT 0.00,
  `cash_in` decimal(20,2) NOT NULL DEFAULT 0.00,
  `return` decimal(20,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(20,2) NOT NULL DEFAULT 0.00,
  `buy` decimal(20,2) NOT NULL DEFAULT 0.00,
  `use` decimal(20,2) NOT NULL DEFAULT 0.00,
  `move` decimal(20,2) NOT NULL DEFAULT 0.00,
  `transfer` decimal(20,2) NOT NULL DEFAULT 0.00,
  `return_supplier` decimal(20,2) NOT NULL DEFAULT 0.00,
  `class` varchar(1) NOT NULL,
  `adjust` decimal(20,2) NOT NULL,
  `depreciation` decimal(20,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deleted`
--

CREATE TABLE `deleted` (
  `id` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `type` tinyint(1) NOT NULL,
  `invoice` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `total` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_items` decimal(20,2) NOT NULL DEFAULT 0.00,
  `detail_ids` text NOT NULL,
  `discount` decimal(6,2) NOT NULL DEFAULT 0.00,
  `due` date NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `adjustment` decimal(20,2) NOT NULL DEFAULT 0.00,
  `receiver_balance` decimal(20,2) NOT NULL DEFAULT 0.00,
  `sender_balance` decimal(20,2) NOT NULL DEFAULT 0.00,
  `real_total` decimal(20,2) NOT NULL DEFAULT 0.00,
  `cogs` decimal(20,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `receiver_type` tinyint(2) NOT NULL,
  `sender_type` tinyint(2) NOT NULL,
  `location_id` int(11) NOT NULL,
  `ppn` decimal(20,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deleted_details`
--

CREATE TABLE `deleted_details` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(20,2) NOT NULL DEFAULT 0.00,
  `price` decimal(20,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total` decimal(20,2) NOT NULL DEFAULT 0.00,
  `date` date NOT NULL,
  `transaction_type` tinyint(2) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `transaction_disc` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `depreciation`
--

CREATE TABLE `depreciation` (
  `item_id` int(11) NOT NULL,
  `value` int(5) NOT NULL,
  `buy_date` date NOT NULL,
  `buy_price` decimal(20,2) NOT NULL,
  `expire_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gaji`
--

CREATE TABLE `gaji` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `gaji_bulanan` decimal(20,2) NOT NULL,
  `gaji_harian` decimal(20,2) NOT NULL,
  `premi` decimal(20,2) NOT NULL,
  `sanksi` decimal(20,2) NOT NULL,
  `cuti_mendadak` decimal(20,2) NOT NULL,
  `terlambat` decimal(5,2) NOT NULL,
  `premi_hangus` tinyint(1) NOT NULL,
  `bonus` decimal(20,2) NOT NULL,
  `tunjangan` decimal(20,2) NOT NULL,
  `mendadak_counter` int(3) NOT NULL,
  `tahunan_counter` int(3) NOT NULL DEFAULT 0,
  `sakit_counter` int(3) NOT NULL DEFAULT 0,
  `pelanggaran_counter` int(3) NOT NULL,
  `total_gaji` decimal(20,2) NOT NULL,
  `gpu` decimal(20,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `geo_city`
--

CREATE TABLE `geo_city` (
  `id` int(11) NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  `province_id` int(11) NOT NULL,
  `lat` decimal(18,15) NOT NULL,
  `lng` decimal(18,15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `geo_province`
--

CREATE TABLE `geo_province` (
  `id` int(11) NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  `lat` decimal(18,15) NOT NULL,
  `lng` decimal(18,15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gpu`
--

CREATE TABLE `gpu` (
  `personnel_id` int(11) NOT NULL,
  `gaji` decimal(20,2) NOT NULL,
  `account_id` int(11) NOT NULL DEFAULT 0,
  `private` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hashtags`
--

CREATE TABLE `hashtags` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hashtag_transaction`
--

CREATE TABLE `hashtag_transaction` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `month` int(2) NOT NULL,
  `year` int(4) NOT NULL,
  `hash_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `total` decimal(20,2) NOT NULL,
  `transaction_type` int(3) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `sender_type` int(11) NOT NULL,
  `receiver_type` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ideas`
--

CREATE TABLE `ideas` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `leader_id` int(11) NOT NULL,
  `why` text NOT NULL,
  `start_date` date NOT NULL,
  `how` text NOT NULL,
  `due_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `idea_comments`
--

CREATE TABLE `idea_comments` (
  `id` int(11) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `idea_milestones`
--

CREATE TABLE `idea_milestones` (
  `id` int(11) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `due_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `idea_personnel`
--

CREATE TABLE `idea_personnel` (
  `id` int(11) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `itemalert`
--

CREATE TABLE `itemalert` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `type` tinyint(2) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `pcode` varchar(50) NOT NULL,
  `code` varchar(50) NOT NULL,
  `price` decimal(20,2) NOT NULL,
  `tag_ids` text NOT NULL,
  `description` text NOT NULL,
  `description2` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `cost` decimal(20,2) NOT NULL,
  `type` tinyint(3) NOT NULL DEFAULT 1,
  `group_id` int(11) NOT NULL,
  `variant` varchar(20) NOT NULL,
  `brand` int(5) NOT NULL DEFAULT 0,
  `size` int(5) NOT NULL DEFAULT 0,
  `genre` int(5) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_group`
--

CREATE TABLE `item_group` (
  `id` int(11) NOT NULL,
  `master` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `variant` varchar(20) NOT NULL,
  `description` varchar(200) NOT NULL,
  `alias` varchar(100) NOT NULL,
  `description2` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_stat`
--

CREATE TABLE `item_stat` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `buy_items` decimal(20,2) NOT NULL,
  `buy_total` decimal(20,2) NOT NULL,
  `sell_items` decimal(20,2) NOT NULL,
  `sell_total` decimal(20,2) NOT NULL,
  `return_items` decimal(20,2) NOT NULL,
  `return_total` decimal(20,2) NOT NULL,
  `supplier_items` decimal(20,2) NOT NULL,
  `supplier_total` decimal(20,2) NOT NULL,
  `sell_average` decimal(20,2) NOT NULL,
  `buy_average` decimal(20,2) NOT NULL,
  `return_average` decimal(20,2) NOT NULL,
  `supplier_average` decimal(20,2) NOT NULL,
  `use_total` decimal(20,2) NOT NULL,
  `use_items` decimal(20,2) NOT NULL,
  `use_average` decimal(20,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_tag`
--

CREATE TABLE `item_tag` (
  `id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(5) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `child_ids` text NOT NULL,
  `parent_ids` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `location_customer`
--

CREATE TABLE `location_customer` (
  `location_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loginlog`
--

CREATE TABLE `loginlog` (
  `id` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `meta` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_records`
--

CREATE TABLE `monthly_records` (
  `id` int(11) NOT NULL,
  `month` int(2) NOT NULL,
  `year` int(4) NOT NULL,
  `location_id` int(11) NOT NULL DEFAULT 0,
  `buy` decimal(20,2) NOT NULL,
  `buy_items` decimal(20,2) NOT NULL,
  `return_total` decimal(20,2) NOT NULL,
  `return_items` decimal(20,2) NOT NULL,
  `sell` decimal(20,2) NOT NULL,
  `sell_items` decimal(20,2) NOT NULL,
  `move` decimal(20,2) NOT NULL,
  `move_items` decimal(20,2) NOT NULL,
  `transfer` decimal(20,2) NOT NULL,
  `cash_out` decimal(20,2) NOT NULL,
  `use_total` decimal(20,2) NOT NULL,
  `use_items` decimal(20,2) NOT NULL,
  `cash_in` decimal(20,2) NOT NULL,
  `journal` decimal(20,2) NOT NULL,
  `return_supplier` decimal(20,2) NOT NULL,
  `return_supplier_items` decimal(20,2) NOT NULL,
  `total_revenue` decimal(20,2) NOT NULL,
  `hpp` decimal(20,2) NOT NULL,
  `gaji_produksi` decimal(20,2) NOT NULL,
  `gross_profit` decimal(20,2) NOT NULL,
  `total_operational` decimal(20,2) NOT NULL,
  `ebitda` decimal(20,2) NOT NULL,
  `nett` decimal(20,2) NOT NULL,
  `depreciation` decimal(20,2) NOT NULL,
  `cogs` decimal(20,2) NOT NULL,
  `adjustment` decimal(20,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `app_id` int(4) NOT NULL,
  `entity_id` int(11) NOT NULL DEFAULT 0,
  `action` varchar(10) NOT NULL,
  `date` date NOT NULL,
  `start` date NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personnels`
--

CREATE TABLE `personnels` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `group` varchar(10) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `department` tinyint(2) NOT NULL,
  `jabatan` varchar(50) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `date_in` date DEFAULT NULL,
  `date_out` date DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `birthplace` varchar(50) NOT NULL,
  `account_id` int(11) DEFAULT 0,
  `bulanan` decimal(20,2) NOT NULL DEFAULT 0.00,
  `harian` decimal(20,2) NOT NULL DEFAULT 0.00,
  `premi` decimal(20,2) NOT NULL DEFAULT 0.00,
  `private` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personnel_cuti`
--

CREATE TABLE `personnel_cuti` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `sisa_sakit` int(2) NOT NULL,
  `year` int(5) NOT NULL,
  `sisa_tahunan` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `problems`
--

CREATE TABLE `problems` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `problem_solution`
--

CREATE TABLE `problem_solution` (
  `id` int(11) NOT NULL,
  `problem_id` int(11) NOT NULL,
  `solution_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `produksi`
--

CREATE TABLE `produksi` (
  `id` int(11) NOT NULL,
  `potong_in` date DEFAULT NULL,
  `jahit_in` date DEFAULT NULL,
  `jahit_out` date DEFAULT NULL,
  `permak_in` date DEFAULT NULL,
  `permak_out` date DEFAULT NULL,
  `item_id` int(11) NOT NULL,
  `jahit` varchar(10) NOT NULL,
  `customer` varchar(25) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(5) NOT NULL,
  `potong_id` int(5) NOT NULL,
  `quantity` int(3) NOT NULL,
  `warna` varchar(255) NOT NULL,
  `temp_name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `q_in` int(3) NOT NULL DEFAULT 0,
  `q_out` int(3) NOT NULL DEFAULT 0,
  `size_id` int(3) NOT NULL,
  `urgent` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `invoice` varchar(50) NOT NULL,
  `detail_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prod_borongan`
--

CREATE TABLE `prod_borongan` (
  `id` int(11) NOT NULL,
  `jahit_id` int(10) NOT NULL,
  `total` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `tres` int(11) NOT NULL,
  `permak` int(11) NOT NULL,
  `lain2` int(11) NOT NULL,
  `total_items` int(5) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `from` date DEFAULT NULL,
  `to` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prod_borongandetail`
--

CREATE TABLE `prod_borongandetail` (
  `id` int(11) NOT NULL,
  `borongan_id` int(11) NOT NULL,
  `ongkos` int(5) NOT NULL,
  `produksi_id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prod_produksi`
--

CREATE TABLE `prod_produksi` (
  `id` int(11) NOT NULL,
  `potong_date` date DEFAULT NULL,
  `item_id` int(11) NOT NULL,
  `jahit_id` int(10) NOT NULL,
  `customer` varchar(25) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(5) NOT NULL,
  `potong_id` int(5) NOT NULL,
  `quantity` int(3) NOT NULL,
  `warna` varchar(255) NOT NULL,
  `temp_name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `size_id` int(3) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `invoice` varchar(50) NOT NULL,
  `detail_id` int(11) NOT NULL,
  `jahit_date` date DEFAULT NULL,
  `permak` tinyint(1) DEFAULT 0,
  `setor_date` date DEFAULT NULL,
  `original_id` int(11) NOT NULL DEFAULT 0,
  `transaction_id` int(11) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `gudang_date` date DEFAULT NULL,
  `surat_jalan_potong` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prod_worker`
--

CREATE TABLE `prod_worker` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promos`
--

CREATE TABLE `promos` (
  `id` int(11) NOT NULL,
  `start` date NOT NULL,
  `stop` date NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` text NOT NULL,
  `discount` decimal(20,2) NOT NULL,
  `type` tinyint(1) NOT NULL,
  `status` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promo_transaction`
--

CREATE TABLE `promo_transaction` (
  `id` int(11) NOT NULL,
  `promo_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `p_cuti`
--

CREATE TABLE `p_cuti` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `type` int(2) NOT NULL,
  `sisa_tahunan` int(3) NOT NULL,
  `sisa_sakit` int(2) NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `p_pelanggaran`
--

CREATE TABLE `p_pelanggaran` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `jenis` varchar(50) NOT NULL,
  `disipliner` int(3) NOT NULL,
  `administrative` int(11) NOT NULL,
  `disipliner_date` date NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reminder`
--

CREATE TABLE `reminder` (
  `id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `startDate` int(11) NOT NULL,
  `endDate` int(11) NOT NULL,
  `closed` tinyint(1) NOT NULL DEFAULT 0,
  `userId` int(11) NOT NULL DEFAULT 0,
  `roleId` int(3) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report`
--

CREATE TABLE `report` (
  `day` int(2) NOT NULL,
  `month` int(2) NOT NULL,
  `year` int(4) NOT NULL,
  `type` int(2) NOT NULL,
  `totalItems` int(11) NOT NULL,
  `balance` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `sidebar` text NOT NULL,
  `sidenav` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `payload` text NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `name` varchar(25) NOT NULL,
  `value` varchar(50) NOT NULL,
  `location_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sitesettings`
--

CREATE TABLE `sitesettings` (
  `name` varchar(50) NOT NULL,
  `value` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `solutions`
--

CREATE TABLE `solutions` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `type` tinyint(1) NOT NULL,
  `code` varchar(50) NOT NULL,
  `price` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `type` tinyint(2) NOT NULL,
  `invoice` varchar(50) DEFAULT NULL,
  `description` text NOT NULL,
  `sender_id` int(11) DEFAULT 0,
  `receiver_id` int(11) DEFAULT NULL,
  `total` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_items` decimal(20,2) NOT NULL DEFAULT 0.00,
  `detail_ids` text NOT NULL,
  `discount` decimal(5,2) NOT NULL DEFAULT 0.00,
  `due` date NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `adjustment` decimal(20,2) NOT NULL DEFAULT 0.00,
  `receiver_balance` decimal(20,2) NOT NULL DEFAULT 0.00,
  `sender_balance` decimal(20,2) NOT NULL DEFAULT 0.00,
  `real_total` decimal(20,2) NOT NULL,
  `cogs` decimal(20,2) NOT NULL,
  `receiver_type` tinyint(2) NOT NULL DEFAULT 0,
  `sender_type` tinyint(2) NOT NULL DEFAULT 0,
  `location_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ppn` decimal(20,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci
PARTITION BY RANGE COLUMNS(`date`)
(
PARTITION p2014 VALUES LESS THAN ('2014-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2015 VALUES LESS THAN ('2015-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2016 VALUES LESS THAN ('2016-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2017 VALUES LESS THAN ('2017-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2018 VALUES LESS THAN ('2018-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2019 VALUES LESS THAN ('2019-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2020 VALUES LESS THAN ('2020-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2021 VALUES LESS THAN ('2021-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2022 VALUES LESS THAN ('2022-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2023 VALUES LESS THAN ('2023-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2024 VALUES LESS THAN ('2024-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2025 VALUES LESS THAN ('2025-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2026 VALUES LESS THAN ('2026-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2027 VALUES LESS THAN ('2027-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2028 VALUES LESS THAN ('2028-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2029 VALUES LESS THAN ('2029-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2030 VALUES LESS THAN ('2030-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2031 VALUES LESS THAN ('2031-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2032 VALUES LESS THAN ('2032-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2033 VALUES LESS THAN ('2033-01-01 00:00:00') ENGINE=InnoDB,
PARTITION pmax VALUES LESS THAN ('9999-12-31 00:00:00') ENGINE=InnoDB
);

-- --------------------------------------------------------

--
-- Table structure for table `transaction_details`
--

CREATE TABLE `transaction_details` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price` decimal(20,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total` decimal(20,2) NOT NULL DEFAULT 0.00,
  `date` date NOT NULL,
  `transaction_type` tinyint(2) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `transaction_disc` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci
PARTITION BY RANGE COLUMNS(`date`)
(
PARTITION p2014 VALUES LESS THAN ('2014-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2015 VALUES LESS THAN ('2015-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2016 VALUES LESS THAN ('2016-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2017 VALUES LESS THAN ('2017-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2018 VALUES LESS THAN ('2018-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2019 VALUES LESS THAN ('2019-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2020 VALUES LESS THAN ('2020-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2021 VALUES LESS THAN ('2021-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2022 VALUES LESS THAN ('2022-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2023 VALUES LESS THAN ('2023-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2024 VALUES LESS THAN ('2024-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2025 VALUES LESS THAN ('2025-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2026 VALUES LESS THAN ('2026-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2027 VALUES LESS THAN ('2027-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2028 VALUES LESS THAN ('2028-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2029 VALUES LESS THAN ('2029-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2030 VALUES LESS THAN ('2030-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2031 VALUES LESS THAN ('2031-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2032 VALUES LESS THAN ('2032-01-01 00:00:00') ENGINE=InnoDB,
PARTITION p2033 VALUES LESS THAN ('2033-01-01 00:00:00') ENGINE=InnoDB,
PARTITION pmax VALUES LESS THAN ('9999-12-31 00:00:00') ENGINE=InnoDB
);

-- --------------------------------------------------------

--
-- Table structure for table `updater`
--

CREATE TABLE `updater` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `app_id` int(5) NOT NULL,
  `date` date NOT NULL,
  `flag` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `role_id` int(3) NOT NULL,
  `location_id` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `remember_token` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usersettings`
--

CREATE TABLE `usersettings` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` int(5) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_activity`
--

CREATE TABLE `user_activity` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `app_id` int(11) NOT NULL,
  `action` varchar(30) NOT NULL,
  `time` int(11) NOT NULL,
  `comment` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_item`
--

CREATE TABLE `warehouse_item` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `quantity` decimal(11,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `acl`
--
ALTER TABLE `acl`
  ADD PRIMARY KEY (`role_id`,`action`,`app_id`);

--
-- Indexes for table `advices`
--
ALTER TABLE `advices`
  ADD PRIMARY KEY (`problem_id`);

--
-- Indexes for table `alert`
--
ALTER TABLE `alert`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expires` (`expires`);

--
-- Indexes for table `alertrules`
--
ALTER TABLE `alertrules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entityId` (`entityId`);

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `aproduksi`
--
ALTER TABLE `aproduksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `jahit` (`jahit`),
  ADD KEY `warna` (`warna`),
  ADD KEY `temp_name` (`temp_name`),
  ADD KEY `customer` (`customer`),
  ADD KEY `detail_id` (`detail_id`);

--
-- Indexes for table `balance_trackers`
--
ALTER TABLE `balance_trackers`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `partial_due` (`partial_due`);

--
-- Indexes for table `borongan`
--
ALTER TABLE `borongan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `borongandetail`
--
ALTER TABLE `borongandetail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gajiId` (`borongan_id`);

--
-- Indexes for table `cron`
--
ALTER TABLE `cron`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`,`type`),
  ADD KEY `name_2` (`name`),
  ADD KEY `birthdate` (`birthdate`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `customerstat`
--
ALTER TABLE `customerstat`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `customer_class`
--
ALTER TABLE `customer_class`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`,`date`);

--
-- Indexes for table `deleted`
--
ALTER TABLE `deleted`
  ADD PRIMARY KEY (`id`),
  ADD KEY `senderId` (`sender_id`),
  ADD KEY `invoice` (`invoice`),
  ADD KEY `receiverId` (`receiver_id`),
  ADD KEY `status` (`status`),
  ADD KEY `userId` (`user_id`),
  ADD KEY `date_2` (`sender_id`,`receiver_id`),
  ADD KEY `due` (`due`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `deleted_details`
--
ALTER TABLE `deleted_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `itemId` (`item_id`),
  ADD KEY `transactionId` (`transaction_id`);

--
-- Indexes for table `depreciation`
--
ALTER TABLE `depreciation`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `buy_date` (`buy_date`),
  ADD KEY `expire_date` (`expire_date`);

--
-- Indexes for table `gaji`
--
ALTER TABLE `gaji`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personnel_id` (`personnel_id`);

--
-- Indexes for table `geo_city`
--
ALTER TABLE `geo_city`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `geo_province`
--
ALTER TABLE `geo_province`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gpu`
--
ALTER TABLE `gpu`
  ADD PRIMARY KEY (`personnel_id`);

--
-- Indexes for table `hashtags`
--
ALTER TABLE `hashtags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `hashtag_transaction`
--
ALTER TABLE `hashtag_transaction`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hash_id` (`hash_id`,`transaction_id`);

--
-- Indexes for table `ideas`
--
ALTER TABLE `ideas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leader_id` (`leader_id`);

--
-- Indexes for table `idea_comments`
--
ALTER TABLE `idea_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idea_id` (`idea_id`,`user_id`);

--
-- Indexes for table `idea_milestones`
--
ALTER TABLE `idea_milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idea_id` (`idea_id`);

--
-- Indexes for table `idea_personnel`
--
ALTER TABLE `idea_personnel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idea_id` (`idea_id`,`personnel_id`);

--
-- Indexes for table `itemalert`
--
ALTER TABLE `itemalert`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_id` (`item_id`),
  ADD KEY `expired` (`type`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `pcode` (`pcode`),
  ADD KEY `type` (`type`),
  ADD KEY `name` (`name`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `item_group`
--
ALTER TABLE `item_group`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `item_stat`
--
ALTER TABLE `item_stat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_id` (`item_id`,`date`);

--
-- Indexes for table `item_tag`
--
ALTER TABLE `item_tag`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `catId` (`tag_id`,`item_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `location_customer`
--
ALTER TABLE `location_customer`
  ADD PRIMARY KEY (`location_id`,`customer_id`);

--
-- Indexes for table `loginlog`
--
ALTER TABLE `loginlog`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `monthly_records`
--
ALTER TABLE `monthly_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `month` (`month`,`year`,`location_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `app_id` (`app_id`),
  ADD KEY `date` (`date`),
  ADD KEY `start` (`start`);

--
-- Indexes for table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `personnels`
--
ALTER TABLE `personnels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `personnel_cuti`
--
ALTER TABLE `personnel_cuti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personnel_id` (`personnel_id`,`year`);

--
-- Indexes for table `problems`
--
ALTER TABLE `problems`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `problem_solution`
--
ALTER TABLE `problem_solution`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `problem_id` (`problem_id`,`solution_id`);

--
-- Indexes for table `produksi`
--
ALTER TABLE `produksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `jahit` (`jahit`),
  ADD KEY `customer` (`customer`),
  ADD KEY `warna` (`warna`),
  ADD KEY `temp_name` (`temp_name`);

--
-- Indexes for table `prod_borongan`
--
ALTER TABLE `prod_borongan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `prod_borongandetail`
--
ALTER TABLE `prod_borongandetail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gajiId` (`borongan_id`);

--
-- Indexes for table `prod_produksi`
--
ALTER TABLE `prod_produksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `jahit` (`jahit_id`),
  ADD KEY `customer` (`customer`),
  ADD KEY `warna` (`warna`),
  ADD KEY `temp_name` (`temp_name`),
  ADD KEY `ori` (`original_id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `prod_worker`
--
ALTER TABLE `prod_worker`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `promos`
--
ALTER TABLE `promos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `start` (`start`,`stop`);

--
-- Indexes for table `promo_transaction`
--
ALTER TABLE `promo_transaction`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `promo_id` (`promo_id`,`transaction_id`,`date`);

--
-- Indexes for table `p_cuti`
--
ALTER TABLE `p_cuti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personnel_id` (`personnel_id`);

--
-- Indexes for table `p_pelanggaran`
--
ALTER TABLE `p_pelanggaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personnel_id` (`personnel_id`);

--
-- Indexes for table `reminder`
--
ALTER TABLE `reminder`
  ADD PRIMARY KEY (`id`),
  ADD KEY `startDate` (`startDate`,`endDate`);

--
-- Indexes for table `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`day`,`month`,`year`,`type`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD UNIQUE KEY `sessions_id_unique` (`id`);

--
-- Indexes for table `sitesettings`
--
ALTER TABLE `sitesettings`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `solutions`
--
ALTER TABLE `solutions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`,`date`),
  ADD KEY `senderId` (`sender_id`),
  ADD KEY `invoice` (`invoice`),
  ADD KEY `receiverId` (`receiver_id`),
  ADD KEY `status` (`status`),
  ADD KEY `userId` (`user_id`),
  ADD KEY `total` (`total`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `transaction_details`
--
ALTER TABLE `transaction_details`
  ADD PRIMARY KEY (`id`,`date`),
  ADD KEY `itemId` (`item_id`),
  ADD KEY `transactionId` (`transaction_id`),
  ADD KEY `transaction_type` (`transaction_type`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `updater`
--
ALTER TABLE `updater`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entity_id_2` (`entity_id`,`app_id`),
  ADD KEY `entity_id` (`entity_id`,`date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `usersettings`
--
ALTER TABLE `usersettings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `warehouse_item`
--
ALTER TABLE `warehouse_item`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`item_id`,`warehouse_id`) USING BTREE,
  ADD KEY `itemId` (`item_id`),
  ADD KEY `warehouseId` (`warehouse_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alert`
--
ALTER TABLE `alert`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `alertrules`
--
ALTER TABLE `alertrules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_settings`
--
ALTER TABLE `app_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `borongan`
--
ALTER TABLE `borongan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `borongandetail`
--
ALTER TABLE `borongandetail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cron`
--
ALTER TABLE `cron`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_class`
--
ALTER TABLE `customer_class`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deleted`
--
ALTER TABLE `deleted`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deleted_details`
--
ALTER TABLE `deleted_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gaji`
--
ALTER TABLE `gaji`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hashtags`
--
ALTER TABLE `hashtags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hashtag_transaction`
--
ALTER TABLE `hashtag_transaction`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ideas`
--
ALTER TABLE `ideas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `idea_comments`
--
ALTER TABLE `idea_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `idea_milestones`
--
ALTER TABLE `idea_milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `idea_personnel`
--
ALTER TABLE `idea_personnel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `itemalert`
--
ALTER TABLE `itemalert`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_group`
--
ALTER TABLE `item_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_stat`
--
ALTER TABLE `item_stat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_tag`
--
ALTER TABLE `item_tag`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loginlog`
--
ALTER TABLE `loginlog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `monthly_records`
--
ALTER TABLE `monthly_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personnels`
--
ALTER TABLE `personnels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personnel_cuti`
--
ALTER TABLE `personnel_cuti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `problems`
--
ALTER TABLE `problems`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `problem_solution`
--
ALTER TABLE `problem_solution`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `produksi`
--
ALTER TABLE `produksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prod_borongan`
--
ALTER TABLE `prod_borongan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prod_borongandetail`
--
ALTER TABLE `prod_borongandetail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prod_produksi`
--
ALTER TABLE `prod_produksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prod_worker`
--
ALTER TABLE `prod_worker`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promos`
--
ALTER TABLE `promos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promo_transaction`
--
ALTER TABLE `promo_transaction`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `p_cuti`
--
ALTER TABLE `p_cuti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `p_pelanggaran`
--
ALTER TABLE `p_pelanggaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reminder`
--
ALTER TABLE `reminder`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `solutions`
--
ALTER TABLE `solutions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction_details`
--
ALTER TABLE `transaction_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `updater`
--
ALTER TABLE `updater`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usersettings`
--
ALTER TABLE `usersettings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_activity`
--
ALTER TABLE `user_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_item`
--
ALTER TABLE `warehouse_item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
