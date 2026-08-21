-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 21, 2026 at 02:21 AM
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
-- Table structure for table `accesses`
--

CREATE TABLE `accesses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expired_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acl`
--

CREATE TABLE `acl` (
  `role_id` int(3) NOT NULL,
  `action` varchar(50) NOT NULL,
  `app_id` int(3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
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
-- Table structure for table `ams`
--

CREATE TABLE `ams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `sk` longtext DEFAULT NULL,
  `pk` longtext DEFAULT NULL,
  `ok` longtext DEFAULT NULL,
  `expDate` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
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
-- Table structure for table `aria_permissions`
--

CREATE TABLE `aria_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aria_roles`
--

CREATE TABLE `aria_roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
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
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cron`
--

CREATE TABLE `cron` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crongetorderdetails`
--

CREATE TABLE `crongetorderdetails` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `get_order_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `invoice` varchar(255) NOT NULL,
  `location_id` varchar(255) NOT NULL,
  `store_id` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `is_canceled` varchar(255) NOT NULL DEFAULT '10',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crongetorders`
--

CREATE TABLE `crongetorders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `from` date NOT NULL,
  `to` int(11) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 0,
  `total` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `step` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cronruns`
--

CREATE TABLE `cronruns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `command` varchar(255) NOT NULL,
  `schedule` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cron_stat_runs`
--

CREATE TABLE `cron_stat_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `runner` int(11) NOT NULL DEFAULT 0,
  `failed` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` int(2) NOT NULL,
  `address` text DEFAULT NULL,
  `description` text NOT NULL,
  `phone` varchar(20) NOT NULL,
  `phone2` varchar(20) NOT NULL,
  `email` varchar(50) NOT NULL,
  `fax` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
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
  `ppn` tinyint(1) DEFAULT 0,
  `operation_id` int(11) DEFAULT NULL,
  `is_online` tinyint(4) NOT NULL DEFAULT 0,
  `arrangement_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `contact_person` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customerstat`
--

CREATE TABLE `customerstat` (
  `customer_id` int(11) NOT NULL,
  `balance` decimal(20,2) NOT NULL DEFAULT 0.00,
  `rating` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
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
-- Table structure for table `cutis`
--

CREATE TABLE `cutis` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `karyawan_id` int(11) NOT NULL,
  `tipe` int(11) NOT NULL,
  `tgl_mulai` date NOT NULL,
  `tgl_akhir` date NOT NULL,
  `tahunan` int(11) NOT NULL DEFAULT 0,
  `sakit` int(11) NOT NULL DEFAULT 0,
  `mendadak` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_inventory_summaries`
--

CREATE TABLE `daily_inventory_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `qty_sell` decimal(15,2) NOT NULL DEFAULT 0.00,
  `qty_buy` decimal(15,2) NOT NULL DEFAULT 0.00,
  `qty_move_in` decimal(15,2) NOT NULL DEFAULT 0.00,
  `qty_move_out` decimal(15,2) NOT NULL DEFAULT 0.00,
  `qty_return_in` decimal(15,2) NOT NULL DEFAULT 0.00,
  `qty_return_out` decimal(15,2) NOT NULL DEFAULT 0.00,
  `qty_adjust_in` decimal(15,2) NOT NULL DEFAULT 0.00,
  `qty_adjust_out` decimal(15,2) NOT NULL DEFAULT 0.00,
  `stock_on_hand` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `receiver_type` tinyint(2) NOT NULL,
  `sender_type` tinyint(2) NOT NULL,
  `location_id` int(11) NOT NULL,
  `ppn` decimal(20,2) NOT NULL,
  `jubelio_return` int(11) DEFAULT NULL,
  `submit_type` int(11) DEFAULT 2,
  `user_jubelio` int(11) DEFAULT 0,
  `a_reference_id` varchar(255) DEFAULT NULL,
  `a_submit_by` int(11) DEFAULT NULL,
  `b_submit_by` int(11) DEFAULT NULL,
  `b_reference_id` varchar(255) DEFAULT NULL,
  `submit_a_count` int(11) NOT NULL,
  `submit_b_count` int(11) NOT NULL,
  `sync_hide` varchar(255) NOT NULL,
  `desty_side_a` int(11) DEFAULT NULL,
  `desty_side_b` int(11) DEFAULT NULL
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
-- Table structure for table `desty_payloads`
--

CREATE TABLE `desty_payloads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date DEFAULT NULL,
  `platform_warehouse_id` varchar(255) DEFAULT NULL,
  `platform_warehouse_name` varchar(255) DEFAULT NULL,
  `store_id` varchar(255) DEFAULT NULL,
  `store_name` varchar(255) DEFAULT NULL,
  `platform_name` varchar(255) DEFAULT NULL,
  `invoice` varchar(255) DEFAULT NULL,
  `adjustment` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_sales` decimal(15,2) NOT NULL DEFAULT 0.00,
  `order_status_list` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `info` text DEFAULT NULL,
  `item_list` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`item_list`)),
  `json_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `desty_syncs`
--

CREATE TABLE `desty_syncs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `platform_warehouse_id` varchar(255) DEFAULT NULL,
  `platform_warehouse_name` varchar(255) DEFAULT NULL,
  `store_id` varchar(255) DEFAULT NULL,
  `store_name` varchar(255) DEFAULT NULL,
  `external_warehouse_id` varchar(255) DEFAULT NULL,
  `warehouse_name` varchar(255) DEFAULT NULL,
  `warehouse_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `gudang_id` varchar(255) DEFAULT NULL,
  `slot_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `desty_warehouses`
--

CREATE TABLE `desty_warehouses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `platform_warehouse_id` varchar(255) DEFAULT NULL,
  `platform_warehouse_name` varchar(255) DEFAULT NULL,
  `store_id` varchar(255) DEFAULT NULL,
  `store_name` varchar(255) DEFAULT NULL,
  `platform_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `gajihs`
--

CREATE TABLE `gajihs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `karyawan_id` int(11) NOT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `bulanan` int(11) NOT NULL,
  `harian` int(11) NOT NULL,
  `premi` int(11) NOT NULL,
  `cuti_sakit` int(11) NOT NULL DEFAULT 0,
  `cuti_tahunan` int(11) NOT NULL DEFAULT 0,
  `cuti_mendadak` int(11) NOT NULL DEFAULT 0,
  `total_cuti` int(11) NOT NULL DEFAULT 0,
  `potongan_cuti_bulanan` int(11) NOT NULL DEFAULT 0,
  `potongan_cuti_premi` int(11) NOT NULL DEFAULT 0,
  `total_potongan` int(11) NOT NULL DEFAULT 0,
  `bonus` int(11) NOT NULL DEFAULT 0,
  `sanksi` int(11) NOT NULL DEFAULT 0,
  `total_gajih` int(11) NOT NULL DEFAULT 0,
  `flag` int(11) NOT NULL DEFAULT 1,
  `bank_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
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
  `legacy_code` varchar(255) DEFAULT NULL,
  `price` decimal(20,2) NOT NULL,
  `tag_ids` text NOT NULL,
  `description` text NOT NULL,
  `description2` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cost` decimal(20,2) NOT NULL,
  `qty` decimal(15,2) NOT NULL DEFAULT 0.00,
  `type` tinyint(3) NOT NULL DEFAULT 1,
  `group_id` int(11) NOT NULL,
  `variant` varchar(20) NOT NULL,
  `brand` int(5) NOT NULL DEFAULT 0,
  `size` int(5) NOT NULL DEFAULT 0,
  `genre` int(5) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `jubelio_item_id` bigint(11) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `image_path` varchar(2048) DEFAULT NULL,
  `restock_urgent_threshold` int(10) UNSIGNED DEFAULT NULL
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
-- Table structure for table `item_identity_conversion_results`
--

CREATE TABLE `item_identity_conversion_results` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `run_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL,
  `failure_code` varchar(40) DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_identity_conversion_runs`
--

CREATE TABLE `item_identity_conversion_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_type` tinyint(3) UNSIGNED NOT NULL,
  `dry_run` tinyint(1) NOT NULL DEFAULT 0,
  `batch_size` int(10) UNSIGNED NOT NULL DEFAULT 1000,
  `processed_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `success_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `failed_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skipped_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jubelioorders`
--

CREATE TABLE `jubelioorders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `jubelio_order_id` varchar(255) NOT NULL,
  `source` int(11) NOT NULL DEFAULT 1,
  `invoice` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `order_status` varchar(255) NOT NULL,
  `run_count` int(11) NOT NULL DEFAULT 0,
  `error_type` int(11) DEFAULT NULL,
  `error` longtext DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `execute_by` int(11) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jubelioreturns`
--

CREATE TABLE `jubelioreturns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` varchar(255) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `method_pay` varchar(255) DEFAULT NULL,
  `invoice` varchar(255) DEFAULT NULL,
  `pesan` longtext DEFAULT NULL,
  `location_name` varchar(255) DEFAULT NULL,
  `store_name` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `confirmed_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jubeliosyncs`
--

CREATE TABLE `jubeliosyncs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `jubelio_store_id` int(11) NOT NULL,
  `jubelio_store_name` varchar(255) NOT NULL,
  `jubelio_location_id` int(11) NOT NULL,
  `jubelio_location_name` varchar(255) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `bin_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jubelio_stock_checks`
--

CREATE TABLE `jubelio_stock_checks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `page_tracking` int(11) NOT NULL DEFAULT 1,
  `status` varchar(255) NOT NULL DEFAULT 'created',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jubelio_stock_discrepancies`
--

CREATE TABLE `jubelio_stock_discrepancies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `jubelio_stock_check_id` bigint(20) UNSIGNED NOT NULL,
  `jubelio_item_id` bigint(20) UNSIGNED NOT NULL,
  `jubelio_location_id` int(11) NOT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `jubelio_location_name` varchar(255) DEFAULT NULL,
  `warehouse_id` int(11) NOT NULL,
  `aria_qty` decimal(15,2) NOT NULL,
  `jubelio_qty` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `karyawans`
--

CREATE TABLE `karyawans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(255) NOT NULL,
  `alamat` longtext NOT NULL,
  `no_telp` varchar(255) NOT NULL,
  `bulanan` int(11) DEFAULT NULL,
  `harian` int(11) DEFAULT NULL,
  `premi` int(11) DEFAULT NULL,
  `flag` int(11) NOT NULL DEFAULT 1,
  `bank_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(5) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
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
  `date` datetime DEFAULT NULL,
  `meta` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logjubelios`
--

CREATE TABLE `logjubelios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` varchar(255) DEFAULT NULL,
  `error` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `invoice` varchar(255) DEFAULT NULL,
  `pesan` longtext DEFAULT NULL,
  `location_name` varchar(255) DEFAULT NULL,
  `store_name` varchar(255) DEFAULT NULL,
  `cron_failed` longtext DEFAULT NULL,
  `cron_run` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `user_solved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_account_summaries`
--

CREATE TABLE `monthly_account_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `year` smallint(6) NOT NULL,
  `month` tinyint(4) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `cash_in` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sell` decimal(15,2) NOT NULL DEFAULT 0.00,
  `return` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_category_summaries`
--

CREATE TABLE `monthly_category_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `year` smallint(6) NOT NULL,
  `month` tinyint(4) NOT NULL,
  `addrbook_type` tinyint(4) NOT NULL,
  `cash_in` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sell` decimal(15,2) NOT NULL DEFAULT 0.00,
  `buy` decimal(15,2) NOT NULL DEFAULT 0.00,
  `return` decimal(15,2) NOT NULL DEFAULT 0.00,
  `return_supplier` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_item_sales`
--

CREATE TABLE `monthly_item_sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `year` smallint(6) NOT NULL,
  `month` tinyint(4) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `qty_net` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_net` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `pos`
--

CREATE TABLE `pos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `type` tinyint(4) NOT NULL,
  `invoice` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sender_id` int(11) NOT NULL DEFAULT 0,
  `receiver_id` int(11) DEFAULT NULL,
  `total` int(11) NOT NULL DEFAULT 0,
  `total_items` int(11) NOT NULL DEFAULT 0,
  `detail_ids` text NOT NULL,
  `due` date DEFAULT NULL,
  `status` tinyint(4) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ppn` int(11) NOT NULL DEFAULT 0,
  `real_total` int(11) NOT NULL DEFAULT 0,
  `cogs` int(11) NOT NULL DEFAULT 0,
  `receiver_type` tinyint(4) NOT NULL DEFAULT 0,
  `sender_type` tinyint(4) NOT NULL DEFAULT 0,
  `location_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `po_details`
--

CREATE TABLE `po_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `available_quantity` int(11) NOT NULL DEFAULT 0,
  `price` int(11) NOT NULL DEFAULT 0,
  `discount` int(11) NOT NULL DEFAULT 0,
  `total` int(11) NOT NULL DEFAULT 0,
  `date` date NOT NULL,
  `transaction_type` tinyint(4) DEFAULT NULL,
  `sender_id` int(11) NOT NULL DEFAULT 0,
  `receiver_id` int(11) NOT NULL DEFAULT 0,
  `transaction_disc` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_performance_rollups`
--

CREATE TABLE `product_performance_rollups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `period_days` smallint(5) UNSIGNED NOT NULL,
  `lens` varchar(20) NOT NULL,
  `warehouse_id` int(11) NOT NULL DEFAULT 0,
  `grain` varchar(32) NOT NULL,
  `dimension_key` varchar(191) NOT NULL,
  `item_type` tinyint(3) UNSIGNED DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `net_qty` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pct_of_total` decimal(8,4) DEFAULT NULL,
  `rank` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
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
  `surat_jalan_potong` varchar(50) NOT NULL,
  `qc_id` int(11) NOT NULL DEFAULT 0,
  `qc_date` datetime DEFAULT NULL,
  `pritil_id` int(11) DEFAULT NULL,
  `pritil_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prod_worker`
--

CREATE TABLE `prod_worker` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
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
-- Table structure for table `restocks`
--

CREATE TABLE `restocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` int(11) NOT NULL,
  `restocked_quantity` int(11) DEFAULT NULL,
  `in_production_quantity` int(11) DEFAULT NULL,
  `shipped_quantity` int(11) DEFAULT NULL,
  `missing_quantity` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restock_cells`
--

CREATE TABLE `restock_cells` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `restock_sheet_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` int(11) NOT NULL,
  `color_id` int(11) DEFAULT NULL,
  `size_id` int(11) DEFAULT NULL,
  `qty_restock` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `qty_production` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `qty_shipped` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `qty_missing` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `missing_at` timestamp NULL DEFAULT NULL,
  `is_urgent` tinyint(1) NOT NULL DEFAULT 0,
  `urgent_manual` tinyint(1) NOT NULL DEFAULT 0,
  `urgent_flagged_at` timestamp NULL DEFAULT NULL,
  `urgent_threshold` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restock_cell_histories`
--

CREATE TABLE `restock_cell_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `restock_cell_id` bigint(20) UNSIGNED NOT NULL,
  `field` varchar(255) NOT NULL,
  `qty_before` int(11) NOT NULL DEFAULT 0,
  `qty_after` int(11) NOT NULL DEFAULT 0,
  `action` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restock_histories`
--

CREATE TABLE `restock_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `restock_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` int(11) NOT NULL,
  `step` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `qty_before` int(11) NOT NULL DEFAULT 0,
  `qty_after` int(11) NOT NULL DEFAULT 0,
  `qty_changed` int(11) NOT NULL DEFAULT 0,
  `invoice` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restock_sheets`
--

CREATE TABLE `restock_sheets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type_tag_id` int(11) NOT NULL,
  `representative_group_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `last_saved_at` timestamp NULL DEFAULT NULL,
  `last_saved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sidebar` text NOT NULL,
  `sidenav` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scheduled_tasks`
--

CREATE TABLE `scheduled_tasks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `command` varchar(255) NOT NULL,
  `frequency` varchar(255) NOT NULL DEFAULT 'daily',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `group` varchar(255) DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `value` text DEFAULT NULL,
  `location_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
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
-- Table structure for table `standalone_invoices`
--

CREATE TABLE `standalone_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `number` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `recipient` text NOT NULL,
  `sender_addrbook_id` bigint(20) UNSIGNED DEFAULT NULL,
  `template` varchar(32) NOT NULL DEFAULT 'classic',
  `preset_id` varchar(64) DEFAULT NULL,
  `terms_of_payment` text DEFAULT NULL,
  `pay_to` text DEFAULT NULL,
  `signatory_name` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `total_qty` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `subtotal` decimal(16,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `standalone_invoice_lines`
--

CREATE TABLE `standalone_invoice_lines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `standalone_invoice_id` bigint(20) UNSIGNED NOT NULL,
  `line_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `price` decimal(16,2) NOT NULL DEFAULT 0.00,
  `total` decimal(16,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stat_sells`
--

CREATE TABLE `stat_sells` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `group_id` int(11) NOT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `sum_qty` int(11) NOT NULL,
  `sum_total` decimal(30,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_data`
--

CREATE TABLE `stock_data` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_stock_report` bigint(20) UNSIGNED NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `score` decimal(8,4) NOT NULL,
  `performance_key` varchar(255) NOT NULL,
  `performance_level` varchar(255) NOT NULL,
  `gap_days` int(11) DEFAULT NULL,
  `current_warehouse_id` int(11) NOT NULL,
  `current_warehouse_name` varchar(255) NOT NULL,
  `current_warehouse_qty` int(11) NOT NULL,
  `current_warehouse_last_sale` varchar(255) DEFAULT NULL,
  `current_warehouse_days_ago` int(11) DEFAULT NULL,
  `best_performing_warehouse_id` int(11) DEFAULT NULL,
  `best_performing_warehouse_name` varchar(255) DEFAULT NULL,
  `best_performing_warehouse_last_sale` varchar(255) DEFAULT NULL,
  `best_performing_warehouse_days_ago` int(11) DEFAULT NULL,
  `best_performing_warehouse_qty` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stok_reports`
--

CREATE TABLE `stok_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `generet_at` timestamp NOT NULL,
  `type` varchar(255) NOT NULL,
  `generet_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `type` tinyint(1) NOT NULL,
  `code` varchar(50) NOT NULL,
  `item_type` int(11) NOT NULL DEFAULT 0,
  `price` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `ppn` decimal(20,2) NOT NULL DEFAULT 0.00,
  `submit_type` int(11) NOT NULL DEFAULT 1,
  `jubelio_return` int(11) NOT NULL DEFAULT 0,
  `a_submit_by` int(11) DEFAULT NULL,
  `a_reference_id` varchar(255) DEFAULT NULL,
  `b_submit_by` int(11) DEFAULT NULL,
  `b_reference_id` varchar(255) DEFAULT NULL,
  `submit_a_count` int(11) NOT NULL DEFAULT 0,
  `submit_b_count` int(11) NOT NULL DEFAULT 0,
  `sync_hide` varchar(255) DEFAULT 'N',
  `desty_side_a` int(11) DEFAULT NULL,
  `desty_side_b` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL
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
  `name` varchar(255) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `role_id` int(3) NOT NULL,
  `location_id` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
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
-- Table structure for table `warehouse_arrangement_candidates`
--

CREATE TABLE `warehouse_arrangement_candidates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `destination_warehouse_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `pcode` varchar(255) DEFAULT NULL,
  `master` varchar(255) DEFAULT NULL,
  `item_code` varchar(255) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `size_code` varchar(255) DEFAULT NULL,
  `warna` varchar(255) DEFAULT NULL,
  `demand_30` decimal(12,2) NOT NULL DEFAULT 0.00,
  `demand_90` decimal(12,2) NOT NULL DEFAULT 0.00,
  `demand_180` decimal(12,2) NOT NULL DEFAULT 0.00,
  `demand_365` decimal(12,2) NOT NULL DEFAULT 0.00,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_arrangement_candidate_sources`
--

CREATE TABLE `warehouse_arrangement_candidate_sources` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `candidate_id` bigint(20) UNSIGNED NOT NULL,
  `source_warehouse_id` int(11) NOT NULL,
  `source_stock` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `suggested_qty` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_arrangement_pcode_snapshots`
--

CREATE TABLE `warehouse_arrangement_pcode_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `destination_warehouse_id` int(11) NOT NULL,
  `pcode` varchar(255) NOT NULL,
  `master` varchar(255) DEFAULT NULL,
  `master_name` varchar(255) DEFAULT NULL,
  `warna` varchar(255) DEFAULT NULL,
  `present_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `total_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `completeness_pct` decimal(5,1) NOT NULL DEFAULT 0.0,
  `family_demand_365` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sizes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sizes`)),
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_arrangement_refresh_jobs`
--

CREATE TABLE `warehouse_arrangement_refresh_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `destination_warehouse_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'created',
  `phase` varchar(255) NOT NULL DEFAULT 'stats',
  `item_cursor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_items` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `stats_rows_inserted` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sync_candidates` int(10) UNSIGNED DEFAULT NULL,
  `sync_sources` int(10) UNSIGNED DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `result_message` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_arrangement_sources`
--

CREATE TABLE `warehouse_arrangement_sources` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `destination_warehouse_id` int(11) NOT NULL,
  `source_warehouse_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_compares`
--

CREATE TABLE `warehouse_compares` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_item`
--

CREATE TABLE `warehouse_item` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `quantity` decimal(11,2) NOT NULL,
  `warehouse_type` varchar(255) NOT NULL DEFAULT '2',
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_item_monthly_stats`
--

CREATE TABLE `warehouse_item_monthly_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `month` smallint(5) UNSIGNED NOT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `sold_qty` decimal(15,2) NOT NULL DEFAULT 0.00,
  `returned_qty` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sold_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `returned_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `item_type` tinyint(3) UNSIGNED DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `pcode` varchar(64) DEFAULT NULL,
  `type_code` varchar(64) NOT NULL DEFAULT '-',
  `warna_code` varchar(64) NOT NULL DEFAULT '-',
  `size_code` varchar(64) NOT NULL DEFAULT '-',
  `brand` tinyint(3) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accesses`
--
ALTER TABLE `accesses`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `ams`
--
ALTER TABLE `ams`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `aria_permissions`
--
ALTER TABLE `aria_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `aria_permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `aria_roles`
--
ALTER TABLE `aria_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `aria_roles_name_guard_name_unique` (`name`,`guard_name`);

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
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `cron`
--
ALTER TABLE `cron`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crongetorderdetails`
--
ALTER TABLE `crongetorderdetails`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crongetorders`
--
ALTER TABLE `crongetorders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cronruns`
--
ALTER TABLE `cronruns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cron_stat_runs`
--
ALTER TABLE `cron_stat_runs`
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
-- Indexes for table `cutis`
--
ALTER TABLE `cutis`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `daily_inventory_summaries`
--
ALTER TABLE `daily_inventory_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inventory_summary_unique` (`date`,`warehouse_id`,`item_id`),
  ADD KEY `daily_inventory_summaries_date_index` (`date`),
  ADD KEY `daily_inventory_summaries_warehouse_id_item_id_index` (`warehouse_id`,`item_id`),
  ADD KEY `daily_inventory_summaries_item_id_foreign` (`item_id`);

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
-- Indexes for table `desty_payloads`
--
ALTER TABLE `desty_payloads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `desty_syncs`
--
ALTER TABLE `desty_syncs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `desty_warehouses`
--
ALTER TABLE `desty_warehouses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `desty_warehouses_platform_warehouse_id_store_id_unique` (`platform_warehouse_id`,`store_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `gaji`
--
ALTER TABLE `gaji`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personnel_id` (`personnel_id`);

--
-- Indexes for table `gajihs`
--
ALTER TABLE `gajihs`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `item_identity_conversion_results`
--
ALTER TABLE `item_identity_conversion_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_identity_conversion_results_run_id_foreign` (`run_id`),
  ADD KEY `item_identity_conversion_results_item_id_status_index` (`item_id`,`status`),
  ADD KEY `item_identity_conversion_results_failure_code_index` (`failure_code`);

--
-- Indexes for table `item_identity_conversion_runs`
--
ALTER TABLE `item_identity_conversion_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_identity_conversion_runs_user_id_foreign` (`user_id`);

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
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jubelioorders`
--
ALTER TABLE `jubelioorders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `jubelioorders_jubelio_order_id_unique` (`jubelio_order_id`);

--
-- Indexes for table `jubelioreturns`
--
ALTER TABLE `jubelioreturns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jubeliosyncs`
--
ALTER TABLE `jubeliosyncs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jubelio_stock_checks`
--
ALTER TABLE `jubelio_stock_checks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jubelio_stock_discrepancies`
--
ALTER TABLE `jubelio_stock_discrepancies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jubelio_stock_discrepancies_jubelio_stock_check_id_foreign` (`jubelio_stock_check_id`);

--
-- Indexes for table `karyawans`
--
ALTER TABLE `karyawans`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `logjubelios`
--
ALTER TABLE `logjubelios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `monthly_account_summaries`
--
ALTER TABLE `monthly_account_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_summary_unique` (`year`,`month`,`customer_id`),
  ADD KEY `monthly_account_summaries_year_month_index` (`year`,`month`),
  ADD KEY `monthly_account_summaries_customer_id_foreign` (`customer_id`);

--
-- Indexes for table `monthly_category_summaries`
--
ALTER TABLE `monthly_category_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_summary_unique` (`year`,`month`,`addrbook_type`),
  ADD KEY `monthly_category_summaries_year_month_index` (`year`,`month`);

--
-- Indexes for table `monthly_item_sales`
--
ALTER TABLE `monthly_item_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_sale_cust_unique` (`year`,`month`,`group_id`,`customer_id`),
  ADD KEY `monthly_item_sales_year_month_index` (`year`,`month`),
  ADD KEY `monthly_item_sales_group_id_foreign` (`group_id`),
  ADD KEY `monthly_item_sales_customer_id_foreign` (`customer_id`);

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
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

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
-- Indexes for table `pos`
--
ALTER TABLE `pos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `po_details`
--
ALTER TABLE `po_details`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `product_performance_rollups`
--
ALTER TABLE `product_performance_rollups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_perf_rollups_unique` (`period_days`,`lens`,`warehouse_id`,`grain`,`dimension_key`,`item_type`),
  ADD KEY `product_perf_rollups_lookup` (`period_days`,`lens`,`warehouse_id`,`grain`,`rank`);

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
-- Indexes for table `restocks`
--
ALTER TABLE `restocks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `restock_cells`
--
ALTER TABLE `restock_cells`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `restock_cells_restock_sheet_id_item_id_unique` (`restock_sheet_id`,`item_id`),
  ADD KEY `restock_cells_restock_sheet_id_color_id_size_id_index` (`restock_sheet_id`,`color_id`,`size_id`),
  ADD KEY `restock_cells_item_id_foreign` (`item_id`),
  ADD KEY `restock_cells_color_id_foreign` (`color_id`),
  ADD KEY `restock_cells_size_id_foreign` (`size_id`);

--
-- Indexes for table `restock_cell_histories`
--
ALTER TABLE `restock_cell_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `restock_cell_histories_restock_cell_id_created_at_index` (`restock_cell_id`,`created_at`),
  ADD KEY `restock_cell_histories_transaction_id_index` (`transaction_id`),
  ADD KEY `restock_cell_histories_user_id_foreign` (`user_id`);

--
-- Indexes for table `restock_histories`
--
ALTER TABLE `restock_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `restock_histories_restock_id_foreign` (`restock_id`),
  ADD KEY `restock_histories_item_id_index` (`item_id`);

--
-- Indexes for table `restock_sheets`
--
ALTER TABLE `restock_sheets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `restock_sheets_type_tag_id_unique` (`type_tag_id`),
  ADD KEY `restock_sheets_type_tag_id_index` (`type_tag_id`),
  ADD KEY `restock_sheets_representative_group_id_foreign` (`representative_group_id`),
  ADD KEY `restock_sheets_created_by_foreign` (`created_by`),
  ADD KEY `restock_sheets_last_saved_by_foreign` (`last_saved_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `scheduled_tasks`
--
ALTER TABLE `scheduled_tasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `scheduled_tasks_command_unique` (`command`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD UNIQUE KEY `sessions_id_unique` (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `standalone_invoices`
--
ALTER TABLE `standalone_invoices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `standalone_invoice_lines`
--
ALTER TABLE `standalone_invoice_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `standalone_invoice_lines_standalone_invoice_id_index` (`standalone_invoice_id`);

--
-- Indexes for table `stat_sells`
--
ALTER TABLE `stat_sells`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_data`
--
ALTER TABLE `stock_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_data_id_stock_report_foreign` (`id_stock_report`),
  ADD KEY `stock_data_item_id_foreign` (`item_id`),
  ADD KEY `stock_data_current_warehouse_id_foreign` (`current_warehouse_id`),
  ADD KEY `stock_data_best_performing_warehouse_id_foreign` (`best_performing_warehouse_id`);

--
-- Indexes for table `stok_reports`
--
ALTER TABLE `stok_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stok_reports_generet_by_foreign` (`generet_by`);

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
-- Indexes for table `warehouse_arrangement_candidates`
--
ALTER TABLE `warehouse_arrangement_candidates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `arr_candidate_dest_item_unique` (`destination_warehouse_id`,`item_id`),
  ADD KEY `arr_candidate_dest_pcode_idx` (`destination_warehouse_id`,`pcode`),
  ADD KEY `arr_cand_item_fk` (`item_id`);

--
-- Indexes for table `warehouse_arrangement_candidate_sources`
--
ALTER TABLE `warehouse_arrangement_candidate_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `arr_cand_src_unique` (`candidate_id`,`source_warehouse_id`),
  ADD KEY `arr_cand_src_wh_fk` (`source_warehouse_id`);

--
-- Indexes for table `warehouse_arrangement_pcode_snapshots`
--
ALTER TABLE `warehouse_arrangement_pcode_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `arr_pcode_dest_pcode_unique` (`destination_warehouse_id`,`pcode`);

--
-- Indexes for table `warehouse_arrangement_refresh_jobs`
--
ALTER TABLE `warehouse_arrangement_refresh_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `arr_refresh_dest_status_idx` (`destination_warehouse_id`,`status`),
  ADD KEY `arr_refresh_status_idx` (`status`),
  ADD KEY `arr_refresh_user_fk` (`user_id`);

--
-- Indexes for table `warehouse_arrangement_sources`
--
ALTER TABLE `warehouse_arrangement_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `arr_src_dest_src_unique` (`destination_warehouse_id`,`source_warehouse_id`),
  ADD KEY `arr_src_source_fk` (`source_warehouse_id`);

--
-- Indexes for table `warehouse_compares`
--
ALTER TABLE `warehouse_compares`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `warehouse_item`
--
ALTER TABLE `warehouse_item`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`item_id`,`warehouse_id`) USING BTREE,
  ADD KEY `itemId` (`item_id`),
  ADD KEY `warehouseId` (`warehouse_id`);

--
-- Indexes for table `warehouse_item_monthly_stats`
--
ALTER TABLE `warehouse_item_monthly_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wh_item_monthly_unique` (`warehouse_id`,`item_id`,`month`,`year`),
  ADD KEY `wh_item_monthly_wh_period` (`warehouse_id`,`year`,`month`),
  ADD KEY `wh_item_monthly_item_period` (`item_id`,`year`,`month`),
  ADD KEY `warehouse_item_monthly_stats_group_id_foreign` (`group_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accesses`
--
ALTER TABLE `accesses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `ams`
--
ALTER TABLE `ams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_settings`
--
ALTER TABLE `app_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aria_permissions`
--
ALTER TABLE `aria_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aria_roles`
--
ALTER TABLE `aria_roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `crongetorderdetails`
--
ALTER TABLE `crongetorderdetails`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crongetorders`
--
ALTER TABLE `crongetorders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cronruns`
--
ALTER TABLE `cronruns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cron_stat_runs`
--
ALTER TABLE `cron_stat_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `cutis`
--
ALTER TABLE `cutis`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_inventory_summaries`
--
ALTER TABLE `daily_inventory_summaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `desty_payloads`
--
ALTER TABLE `desty_payloads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `desty_syncs`
--
ALTER TABLE `desty_syncs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `desty_warehouses`
--
ALTER TABLE `desty_warehouses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gaji`
--
ALTER TABLE `gaji`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gajihs`
--
ALTER TABLE `gajihs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `item_identity_conversion_results`
--
ALTER TABLE `item_identity_conversion_results`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_identity_conversion_runs`
--
ALTER TABLE `item_identity_conversion_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jubelioorders`
--
ALTER TABLE `jubelioorders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jubelioreturns`
--
ALTER TABLE `jubelioreturns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jubeliosyncs`
--
ALTER TABLE `jubeliosyncs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jubelio_stock_checks`
--
ALTER TABLE `jubelio_stock_checks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jubelio_stock_discrepancies`
--
ALTER TABLE `jubelio_stock_discrepancies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `karyawans`
--
ALTER TABLE `karyawans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `logjubelios`
--
ALTER TABLE `logjubelios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `monthly_account_summaries`
--
ALTER TABLE `monthly_account_summaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `monthly_category_summaries`
--
ALTER TABLE `monthly_category_summaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `monthly_item_sales`
--
ALTER TABLE `monthly_item_sales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `pos`
--
ALTER TABLE `pos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `po_details`
--
ALTER TABLE `po_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `product_performance_rollups`
--
ALTER TABLE `product_performance_rollups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `restocks`
--
ALTER TABLE `restocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restock_cells`
--
ALTER TABLE `restock_cells`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restock_cell_histories`
--
ALTER TABLE `restock_cell_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restock_histories`
--
ALTER TABLE `restock_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restock_sheets`
--
ALTER TABLE `restock_sheets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scheduled_tasks`
--
ALTER TABLE `scheduled_tasks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `solutions`
--
ALTER TABLE `solutions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `standalone_invoices`
--
ALTER TABLE `standalone_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `standalone_invoice_lines`
--
ALTER TABLE `standalone_invoice_lines`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stat_sells`
--
ALTER TABLE `stat_sells`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_data`
--
ALTER TABLE `stock_data`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stok_reports`
--
ALTER TABLE `stok_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `warehouse_arrangement_candidates`
--
ALTER TABLE `warehouse_arrangement_candidates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_arrangement_candidate_sources`
--
ALTER TABLE `warehouse_arrangement_candidate_sources`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_arrangement_pcode_snapshots`
--
ALTER TABLE `warehouse_arrangement_pcode_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_arrangement_refresh_jobs`
--
ALTER TABLE `warehouse_arrangement_refresh_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_arrangement_sources`
--
ALTER TABLE `warehouse_arrangement_sources`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_compares`
--
ALTER TABLE `warehouse_compares`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_item`
--
ALTER TABLE `warehouse_item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_item_monthly_stats`
--
ALTER TABLE `warehouse_item_monthly_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `daily_inventory_summaries`
--
ALTER TABLE `daily_inventory_summaries`
  ADD CONSTRAINT `daily_inventory_summaries_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `daily_inventory_summaries_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_identity_conversion_results`
--
ALTER TABLE `item_identity_conversion_results`
  ADD CONSTRAINT `item_identity_conversion_results_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_identity_conversion_results_run_id_foreign` FOREIGN KEY (`run_id`) REFERENCES `item_identity_conversion_runs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_identity_conversion_runs`
--
ALTER TABLE `item_identity_conversion_runs`
  ADD CONSTRAINT `item_identity_conversion_runs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `jubelio_stock_discrepancies`
--
ALTER TABLE `jubelio_stock_discrepancies`
  ADD CONSTRAINT `jubelio_stock_discrepancies_jubelio_stock_check_id_foreign` FOREIGN KEY (`jubelio_stock_check_id`) REFERENCES `jubelio_stock_checks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `aria_permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `aria_roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_account_summaries`
--
ALTER TABLE `monthly_account_summaries`
  ADD CONSTRAINT `monthly_account_summaries_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_item_sales`
--
ALTER TABLE `monthly_item_sales`
  ADD CONSTRAINT `monthly_item_sales_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `monthly_item_sales_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `item_group` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `restock_cells`
--
ALTER TABLE `restock_cells`
  ADD CONSTRAINT `restock_cells_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `tags` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `restock_cells_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restock_cells_restock_sheet_id_foreign` FOREIGN KEY (`restock_sheet_id`) REFERENCES `restock_sheets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restock_cells_size_id_foreign` FOREIGN KEY (`size_id`) REFERENCES `tags` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `restock_cell_histories`
--
ALTER TABLE `restock_cell_histories`
  ADD CONSTRAINT `restock_cell_histories_restock_cell_id_foreign` FOREIGN KEY (`restock_cell_id`) REFERENCES `restock_cells` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restock_cell_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `restock_histories`
--
ALTER TABLE `restock_histories`
  ADD CONSTRAINT `restock_histories_restock_id_foreign` FOREIGN KEY (`restock_id`) REFERENCES `restocks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `restock_sheets`
--
ALTER TABLE `restock_sheets`
  ADD CONSTRAINT `restock_sheets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restock_sheets_last_saved_by_foreign` FOREIGN KEY (`last_saved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `restock_sheets_representative_group_id_foreign` FOREIGN KEY (`representative_group_id`) REFERENCES `item_group` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `restock_sheets_type_tag_id_foreign` FOREIGN KEY (`type_tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `aria_permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `aria_roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_data`
--
ALTER TABLE `stock_data`
  ADD CONSTRAINT `stock_data_best_performing_warehouse_id_foreign` FOREIGN KEY (`best_performing_warehouse_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_data_current_warehouse_id_foreign` FOREIGN KEY (`current_warehouse_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_data_id_stock_report_foreign` FOREIGN KEY (`id_stock_report`) REFERENCES `stok_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_data_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stok_reports`
--
ALTER TABLE `stok_reports`
  ADD CONSTRAINT `stok_reports_generet_by_foreign` FOREIGN KEY (`generet_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_arrangement_candidates`
--
ALTER TABLE `warehouse_arrangement_candidates`
  ADD CONSTRAINT `arr_cand_dest_fk` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arr_cand_item_fk` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouse_arrangement_candidate_sources`
--
ALTER TABLE `warehouse_arrangement_candidate_sources`
  ADD CONSTRAINT `arr_cand_src_cand_fk` FOREIGN KEY (`candidate_id`) REFERENCES `warehouse_arrangement_candidates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arr_cand_src_wh_fk` FOREIGN KEY (`source_warehouse_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouse_arrangement_pcode_snapshots`
--
ALTER TABLE `warehouse_arrangement_pcode_snapshots`
  ADD CONSTRAINT `arr_pcode_snap_dest_fk` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouse_arrangement_refresh_jobs`
--
ALTER TABLE `warehouse_arrangement_refresh_jobs`
  ADD CONSTRAINT `arr_refresh_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_arrangement_sources`
--
ALTER TABLE `warehouse_arrangement_sources`
  ADD CONSTRAINT `arr_src_dest_fk` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arr_src_source_fk` FOREIGN KEY (`source_warehouse_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouse_item_monthly_stats`
--
ALTER TABLE `warehouse_item_monthly_stats`
  ADD CONSTRAINT `warehouse_item_monthly_stats_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `item_group` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_item_monthly_stats_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_item_monthly_stats_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
