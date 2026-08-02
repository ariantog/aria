-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 31, 2026 at 08:57 AM
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
-- Database: `u343060430_db_ariacore`
--

-- --------------------------------------------------------

--
-- Table structure for table `addrbooks`
--

CREATE TABLE `addrbooks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `ppn` tinyint(1) NOT NULL DEFAULT 0,
  `member_id` varchar(255) DEFAULT NULL,
  `type` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `operation_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `addrbook_dailies`
--

CREATE TABLE `addrbook_dailies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `addrbook_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `date` date NOT NULL,
  `cash_in` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sell` decimal(15,2) NOT NULL DEFAULT 0.00,
  `buy` decimal(15,2) NOT NULL DEFAULT 0.00,
  `return` decimal(15,2) NOT NULL DEFAULT 0.00,
  `return_supplier` decimal(15,2) NOT NULL DEFAULT 0.00,
  `use` decimal(15,2) NOT NULL DEFAULT 0.00,
  `move` decimal(15,2) NOT NULL DEFAULT 0.00,
  `transfer` decimal(15,2) NOT NULL DEFAULT 0.00,
  `adjust` decimal(15,2) NOT NULL DEFAULT 0.00,
  `depreciation` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `addrbook_stats`
--

CREATE TABLE `addrbook_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `addrbook_id` bigint(20) UNSIGNED NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `borongans`
--

CREATE TABLE `borongans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `jahit_id` bigint(20) UNSIGNED NOT NULL,
  `tres` decimal(12,2) NOT NULL DEFAULT 0.00,
  `permak` decimal(12,2) NOT NULL DEFAULT 0.00,
  `lain2` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_items` int(11) NOT NULL DEFAULT 0,
  `from` date DEFAULT NULL,
  `to` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `borongan_details`
--

CREATE TABLE `borongan_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `borongan_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `produksi_id` bigint(20) UNSIGNED NOT NULL,
  `ongkos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `cutis`
--

CREATE TABLE `cutis` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `karyawan_id` bigint(20) UNSIGNED NOT NULL,
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
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `qty_sell` decimal(15,2) NOT NULL DEFAULT 0.00,
  `stock_on_hand` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deleted_transactions`
--

CREATE TABLE `deleted_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date DEFAULT NULL,
  `type` tinyint(3) UNSIGNED DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `sender_type` varchar(255) DEFAULT NULL,
  `sender_id` bigint(20) UNSIGNED DEFAULT NULL,
  `receiver_type` varchar(255) DEFAULT NULL,
  `receiver_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_number` varchar(255) DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `submit_type` tinyint(4) DEFAULT NULL,
  `total` decimal(15,2) DEFAULT NULL,
  `discount` decimal(15,2) DEFAULT NULL,
  `adjustment` decimal(15,2) DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT NULL,
  `total_items` decimal(15,2) DEFAULT NULL,
  `sender_balance` decimal(15,2) DEFAULT NULL,
  `receiver_balance` decimal(15,2) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED DEFAULT NULL,
  `sync_hide` varchar(1) DEFAULT NULL,
  `a_submit_by` bigint(20) UNSIGNED DEFAULT NULL,
  `b_submit_by` bigint(20) UNSIGNED DEFAULT NULL,
  `a_reference_id` varchar(255) DEFAULT NULL,
  `b_reference_id` varchar(255) DEFAULT NULL,
  `submit_a_count` int(11) DEFAULT NULL,
  `submit_b_count` int(11) DEFAULT NULL,
  `jubelio_return` int(11) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deleted_transaction_details`
--

CREATE TABLE `deleted_transaction_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` bigint(20) UNSIGNED NOT NULL,
  `date` date DEFAULT NULL,
  `transaction_type` tinyint(3) UNSIGNED DEFAULT NULL,
  `sender_id` bigint(20) UNSIGNED DEFAULT NULL,
  `receiver_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` decimal(15,2) DEFAULT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `discount` decimal(15,2) DEFAULT NULL,
  `total` decimal(15,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
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
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gajis`
--

CREATE TABLE `gajis` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `karyawan_id` bigint(20) UNSIGNED NOT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `bulanan` int(11) NOT NULL DEFAULT 0,
  `harian` int(11) NOT NULL DEFAULT 0,
  `premi` int(11) NOT NULL DEFAULT 0,
  `cuti_sakit` int(11) NOT NULL DEFAULT 0,
  `cuti_tahunan` int(11) NOT NULL DEFAULT 0,
  `cuti_mendadak` int(11) NOT NULL DEFAULT 0,
  `total_cuti` int(11) NOT NULL DEFAULT 0,
  `potongan_cuti_bulanan` int(11) NOT NULL DEFAULT 0,
  `potongan_cuti_premi` int(11) NOT NULL DEFAULT 0,
  `total_potongan` int(11) NOT NULL DEFAULT 0,
  `bonus` int(11) NOT NULL DEFAULT 0,
  `sanksi` int(11) NOT NULL DEFAULT 0,
  `total_gaji` int(11) NOT NULL DEFAULT 0,
  `flag` int(11) NOT NULL DEFAULT 1,
  `bank_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `group_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `pcode` varchar(255) NOT NULL,
  `brand` int(11) NOT NULL DEFAULT 0,
  `type` int(11) NOT NULL DEFAULT 1,
  `size` int(11) NOT NULL DEFAULT 0,
  `genre` int(11) NOT NULL DEFAULT 0,
  `price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cost` decimal(15,2) DEFAULT NULL,
  `qty` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tag_ids` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `description2` text DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `jubelio_item_id` bigint(20) DEFAULT NULL,
  `image_path` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_groups`
--

CREATE TABLE `item_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `description2` varchar(255) DEFAULT NULL,
  `alias` varchar(255) DEFAULT NULL,
  `master` varchar(255) DEFAULT NULL,
  `variant` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_tag`
--

CREATE TABLE `item_tag` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `tag_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `payload` longtext DEFAULT NULL,
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
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `jubelio_location_id` int(11) NOT NULL,
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
  `bank_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `addrbook_id` bigint(20) UNSIGNED NOT NULL,
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
  `group_id` bigint(20) UNSIGNED DEFAULT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `qty_net` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_net` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `produksis`
--

CREATE TABLE `produksis` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `temp_name` varchar(255) DEFAULT NULL,
  `size_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `customer` varchar(255) DEFAULT NULL,
  `warna` varchar(255) DEFAULT NULL,
  `potong_id` bigint(20) UNSIGNED DEFAULT NULL,
  `potong_date` date DEFAULT NULL,
  `surat_jalan_potong` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '1: Produksi, 2: Setor',
  `invoice` varchar(255) DEFAULT NULL,
  `transaction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `detail_id` bigint(20) UNSIGNED DEFAULT NULL,
  `setor_date` date DEFAULT NULL,
  `gudang_date` date DEFAULT NULL,
  `original_id` bigint(20) UNSIGNED DEFAULT NULL,
  `jahit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `jahit_date` date DEFAULT NULL,
  `qc_id` bigint(20) UNSIGNED DEFAULT NULL,
  `qc_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restocks`
--

CREATE TABLE `restocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `group_id` bigint(20) UNSIGNED DEFAULT NULL,
  `color_id` bigint(20) UNSIGNED DEFAULT NULL,
  `size_id` varchar(255) DEFAULT NULL,
  `size_type` varchar(255) DEFAULT NULL,
  `date` date NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `restocked_quantity` int(11) NOT NULL DEFAULT 0,
  `in_production_quantity` int(11) NOT NULL DEFAULT 0,
  `shipped_quantity` int(11) NOT NULL DEFAULT 0,
  `missing_quantity` int(11) NOT NULL DEFAULT 0,
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
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `group_id` bigint(20) UNSIGNED DEFAULT NULL,
  `color_id` bigint(20) UNSIGNED DEFAULT NULL,
  `size_id` varchar(255) DEFAULT NULL,
  `size_type` varchar(255) DEFAULT NULL,
  `step` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `qty_before` int(11) NOT NULL,
  `qty_after` int(11) NOT NULL,
  `qty_changed` int(11) NOT NULL,
  `invoice` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `frequency` varchar(255) NOT NULL DEFAULT '0 0 * * *',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
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
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `group` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stat_sells`
--

CREATE TABLE `stat_sells` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `group_id` bigint(20) UNSIGNED DEFAULT NULL,
  `bulan` smallint(5) UNSIGNED NOT NULL,
  `tahun` smallint(5) UNSIGNED NOT NULL,
  `sender_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` tinyint(3) UNSIGNED NOT NULL,
  `sum_qty` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sum_total` decimal(15,2) NOT NULL DEFAULT 0.00,
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
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `score` decimal(8,4) NOT NULL,
  `performance_key` varchar(255) NOT NULL,
  `performance_level` varchar(255) NOT NULL,
  `gap_days` int(11) DEFAULT NULL,
  `current_warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `current_warehouse_name` varchar(255) NOT NULL,
  `current_warehouse_qty` int(11) NOT NULL,
  `current_warehouse_last_sale` varchar(255) DEFAULT NULL,
  `current_warehouse_days_ago` int(11) DEFAULT NULL,
  `best_performing_warehouse_id` bigint(20) UNSIGNED DEFAULT NULL,
  `best_performing_warehouse_name` varchar(255) DEFAULT NULL,
  `best_performing_warehouse_last_sale` varchar(255) DEFAULT NULL,
  `best_performing_warehouse_days_ago` int(11) DEFAULT NULL,
  `best_performing_warehouse_qty` int(11) DEFAULT NULL,
  `audit_reference_date` varchar(255) DEFAULT NULL,
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
  `generet_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `type` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `item_type` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `type` tinyint(3) UNSIGNED NOT NULL,
  `due_date` date DEFAULT NULL,
  `sender_type` varchar(255) DEFAULT NULL,
  `sender_id` bigint(20) UNSIGNED DEFAULT NULL,
  `receiver_type` varchar(255) DEFAULT NULL,
  `receiver_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_number` varchar(255) DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `submit_type` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1: aria submit, 2: cron jubelio',
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `adjustment` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_items` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sender_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `receiver_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `sync_hide` varchar(1) NOT NULL DEFAULT 'N',
  `a_submit_by` bigint(20) UNSIGNED DEFAULT NULL,
  `b_submit_by` bigint(20) UNSIGNED DEFAULT NULL,
  `a_reference_id` varchar(255) DEFAULT NULL,
  `b_reference_id` varchar(255) DEFAULT NULL,
  `submit_a_count` int(11) NOT NULL DEFAULT 0,
  `submit_b_count` int(11) NOT NULL DEFAULT 0,
  `jubelio_return` int(11) NOT NULL DEFAULT 0,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_details`
--

CREATE TABLE `transaction_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` bigint(20) UNSIGNED NOT NULL,
  `date` date DEFAULT NULL,
  `transaction_type` tinyint(3) UNSIGNED DEFAULT NULL,
  `sender_id` bigint(20) UNSIGNED DEFAULT NULL,
  `receiver_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_compares`
--

CREATE TABLE `warehouse_compares` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_items`
--

CREATE TABLE `warehouse_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_type` varchar(255) NOT NULL DEFAULT 'AppModelsAddrbook',
  `quantity` decimal(15,2) NOT NULL DEFAULT 0.00,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workers`
--

CREATE TABLE `workers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` int(11) NOT NULL COMMENT '1: Potong, 2: Jahit, 3: QC',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addrbooks`
--
ALTER TABLE `addrbooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `addrbooks_operation_id_foreign` (`operation_id`);

--
-- Indexes for table `addrbook_dailies`
--
ALTER TABLE `addrbook_dailies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `addrbook_classes_addrbook_id_foreign` (`addrbook_id`);

--
-- Indexes for table `addrbook_stats`
--
ALTER TABLE `addrbook_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `addrbook_stats_addrbook_id_foreign` (`addrbook_id`);

--
-- Indexes for table `borongans`
--
ALTER TABLE `borongans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `borongans_user_id_foreign` (`user_id`),
  ADD KEY `borongans_jahit_id_foreign` (`jahit_id`);

--
-- Indexes for table `borongan_details`
--
ALTER TABLE `borongan_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `borongan_details_borongan_id_foreign` (`borongan_id`),
  ADD KEY `borongan_details_item_id_foreign` (`item_id`),
  ADD KEY `borongan_details_produksi_id_foreign` (`produksi_id`);

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
-- Indexes for table `cutis`
--
ALTER TABLE `cutis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cutis_karyawan_id_foreign` (`karyawan_id`);

--
-- Indexes for table `daily_inventory_summaries`
--
ALTER TABLE `daily_inventory_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inventory_summary_unique` (`date`,`warehouse_id`,`item_id`),
  ADD KEY `daily_inventory_summaries_item_id_foreign` (`item_id`),
  ADD KEY `daily_inventory_summaries_date_index` (`date`),
  ADD KEY `daily_inventory_summaries_warehouse_id_item_id_index` (`warehouse_id`,`item_id`);

--
-- Indexes for table `deleted_transactions`
--
ALTER TABLE `deleted_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deleted_transaction_details`
--
ALTER TABLE `deleted_transaction_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deleted_transaction_details_transaction_id_index` (`transaction_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `gajis`
--
ALTER TABLE `gajis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gajis_karyawan_id_foreign` (`karyawan_id`),
  ADD KEY `gajis_bank_id_foreign` (`bank_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `items_group_id_foreign` (`group_id`),
  ADD KEY `items_code_index` (`code`),
  ADD KEY `items_pcode_index` (`pcode`);

--
-- Indexes for table `item_groups`
--
ALTER TABLE `item_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_groups_name_index` (`name`);

--
-- Indexes for table `item_tag`
--
ALTER TABLE `item_tag`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_tag_item_id_tag_id_unique` (`item_id`,`tag_id`),
  ADD KEY `item_tag_tag_id_foreign` (`tag_id`);

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
  ADD KEY `jubelio_stock_discrepancies_jubelio_stock_check_id_foreign` (`jubelio_stock_check_id`),
  ADD KEY `jubelio_stock_discrepancies_item_id_foreign` (`item_id`);

--
-- Indexes for table `karyawans`
--
ALTER TABLE `karyawans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `karyawans_bank_id_foreign` (`bank_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
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
  ADD UNIQUE KEY `account_summary_unique` (`year`,`month`,`addrbook_id`),
  ADD KEY `monthly_account_summaries_addrbook_id_foreign` (`addrbook_id`),
  ADD KEY `monthly_account_summaries_year_month_index` (`year`,`month`);

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
  ADD KEY `monthly_item_sales_group_id_foreign` (`group_id`),
  ADD KEY `monthly_item_sales_customer_id_foreign` (`customer_id`),
  ADD KEY `monthly_item_sales_year_month_index` (`year`,`month`);

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
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posts_user_id_foreign` (`user_id`);

--
-- Indexes for table `produksis`
--
ALTER TABLE `produksis`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `restocks`
--
ALTER TABLE `restocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `restocks_item_id_foreign` (`item_id`),
  ADD KEY `restocks_group_id_foreign` (`group_id`),
  ADD KEY `restocks_color_id_foreign` (`color_id`);

--
-- Indexes for table `restock_histories`
--
ALTER TABLE `restock_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `restock_histories_restock_id_foreign` (`restock_id`),
  ADD KEY `restock_histories_item_id_foreign` (`item_id`),
  ADD KEY `restock_histories_user_id_foreign` (`user_id`),
  ADD KEY `restock_histories_group_id_foreign` (`group_id`),
  ADD KEY `restock_histories_color_id_foreign` (`color_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settings_slug_unique` (`slug`),
  ADD KEY `settings_group_index` (`group`);

--
-- Indexes for table `stat_sells`
--
ALTER TABLE `stat_sells`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stat_sells_unique` (`group_id`,`bulan`,`tahun`,`sender_id`,`type`),
  ADD KEY `stat_sells_sender_id_foreign` (`sender_id`),
  ADD KEY `stat_sells_bulan_index` (`bulan`),
  ADD KEY `stat_sells_tahun_index` (`tahun`),
  ADD KEY `stat_sells_group_id_index` (`group_id`),
  ADD KEY `stat_sells_type_index` (`type`);

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
  ADD KEY `tags_type_index` (`type`),
  ADD KEY `tags_item_type_index` (`item_type`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transactions_sender_type_sender_id_index` (`sender_type`,`sender_id`),
  ADD KEY `transactions_receiver_type_receiver_id_index` (`receiver_type`,`receiver_id`),
  ADD KEY `transactions_user_id_foreign` (`user_id`),
  ADD KEY `transactions_a_submit_by_foreign` (`a_submit_by`),
  ADD KEY `transactions_b_submit_by_foreign` (`b_submit_by`);

--
-- Indexes for table `transaction_details`
--
ALTER TABLE `transaction_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_details_transaction_id_foreign` (`transaction_id`),
  ADD KEY `transaction_details_item_id_foreign` (`item_id`),
  ADD KEY `td_audit_index` (`item_id`,`sender_id`,`transaction_type`,`date`),
  ADD KEY `transaction_details_date_index` (`date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_username_unique` (`username`),
  ADD KEY `users_location_id_foreign` (`location_id`);

--
-- Indexes for table `warehouse_compares`
--
ALTER TABLE `warehouse_compares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `warehouse_compares_user_id_warehouse_id_unique` (`user_id`,`warehouse_id`),
  ADD KEY `warehouse_compares_warehouse_id_foreign` (`warehouse_id`);

--
-- Indexes for table `warehouse_items`
--
ALTER TABLE `warehouse_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `warehouse_items_item_warehouse_unique` (`item_id`,`warehouse_id`,`warehouse_type`),
  ADD KEY `warehouse_items_warehouse_id_warehouse_type_index` (`warehouse_id`,`warehouse_type`);

--
-- Indexes for table `workers`
--
ALTER TABLE `workers`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addrbooks`
--
ALTER TABLE `addrbooks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `addrbook_dailies`
--
ALTER TABLE `addrbook_dailies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `addrbook_stats`
--
ALTER TABLE `addrbook_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `borongans`
--
ALTER TABLE `borongans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `borongan_details`
--
ALTER TABLE `borongan_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gajis`
--
ALTER TABLE `gajis`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_groups`
--
ALTER TABLE `item_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_tag`
--
ALTER TABLE `item_tag`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `produksis`
--
ALTER TABLE `produksis`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restocks`
--
ALTER TABLE `restocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restock_histories`
--
ALTER TABLE `restock_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction_details`
--
ALTER TABLE `transaction_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_compares`
--
ALTER TABLE `warehouse_compares`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_items`
--
ALTER TABLE `warehouse_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workers`
--
ALTER TABLE `workers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addrbooks`
--
ALTER TABLE `addrbooks`
  ADD CONSTRAINT `addrbooks_operation_id_foreign` FOREIGN KEY (`operation_id`) REFERENCES `operations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `addrbook_dailies`
--
ALTER TABLE `addrbook_dailies`
  ADD CONSTRAINT `addrbook_classes_addrbook_id_foreign` FOREIGN KEY (`addrbook_id`) REFERENCES `addrbooks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `addrbook_stats`
--
ALTER TABLE `addrbook_stats`
  ADD CONSTRAINT `addrbook_stats_addrbook_id_foreign` FOREIGN KEY (`addrbook_id`) REFERENCES `addrbooks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `borongans`
--
ALTER TABLE `borongans`
  ADD CONSTRAINT `borongans_jahit_id_foreign` FOREIGN KEY (`jahit_id`) REFERENCES `workers` (`id`),
  ADD CONSTRAINT `borongans_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `borongan_details`
--
ALTER TABLE `borongan_details`
  ADD CONSTRAINT `borongan_details_borongan_id_foreign` FOREIGN KEY (`borongan_id`) REFERENCES `borongans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `borongan_details_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `borongan_details_produksi_id_foreign` FOREIGN KEY (`produksi_id`) REFERENCES `produksis` (`id`);

--
-- Constraints for table `cutis`
--
ALTER TABLE `cutis`
  ADD CONSTRAINT `cutis_karyawan_id_foreign` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `daily_inventory_summaries`
--
ALTER TABLE `daily_inventory_summaries`
  ADD CONSTRAINT `daily_inventory_summaries_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `daily_inventory_summaries_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `addrbooks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gajis`
--
ALTER TABLE `gajis`
  ADD CONSTRAINT `gajis_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `addrbooks` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `gajis_karyawan_id_foreign` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `item_groups` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `item_tag`
--
ALTER TABLE `item_tag`
  ADD CONSTRAINT `item_tag_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jubelio_stock_discrepancies`
--
ALTER TABLE `jubelio_stock_discrepancies`
  ADD CONSTRAINT `jubelio_stock_discrepancies_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jubelio_stock_discrepancies_jubelio_stock_check_id_foreign` FOREIGN KEY (`jubelio_stock_check_id`) REFERENCES `jubelio_stock_checks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `karyawans`
--
ALTER TABLE `karyawans`
  ADD CONSTRAINT `karyawans_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `addrbooks` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_account_summaries`
--
ALTER TABLE `monthly_account_summaries`
  ADD CONSTRAINT `monthly_account_summaries_addrbook_id_foreign` FOREIGN KEY (`addrbook_id`) REFERENCES `addrbooks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_item_sales`
--
ALTER TABLE `monthly_item_sales`
  ADD CONSTRAINT `monthly_item_sales_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `addrbooks` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `monthly_item_sales_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `item_groups` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `restocks`
--
ALTER TABLE `restocks`
  ADD CONSTRAINT `restocks_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restocks_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `item_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restocks_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `restock_histories`
--
ALTER TABLE `restock_histories`
  ADD CONSTRAINT `restock_histories_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restock_histories_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `item_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restock_histories_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restock_histories_restock_id_foreign` FOREIGN KEY (`restock_id`) REFERENCES `restocks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restock_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stat_sells`
--
ALTER TABLE `stat_sells`
  ADD CONSTRAINT `stat_sells_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `item_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stat_sells_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `addrbooks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_data`
--
ALTER TABLE `stock_data`
  ADD CONSTRAINT `stock_data_best_performing_warehouse_id_foreign` FOREIGN KEY (`best_performing_warehouse_id`) REFERENCES `addrbooks` (`id`),
  ADD CONSTRAINT `stock_data_current_warehouse_id_foreign` FOREIGN KEY (`current_warehouse_id`) REFERENCES `addrbooks` (`id`),
  ADD CONSTRAINT `stock_data_id_stock_report_foreign` FOREIGN KEY (`id_stock_report`) REFERENCES `stok_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_data_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `stok_reports`
--
ALTER TABLE `stok_reports`
  ADD CONSTRAINT `stok_reports_generet_by_foreign` FOREIGN KEY (`generet_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_a_submit_by_foreign` FOREIGN KEY (`a_submit_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_b_submit_by_foreign` FOREIGN KEY (`b_submit_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `transaction_details`
--
ALTER TABLE `transaction_details`
  ADD CONSTRAINT `transaction_details_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `transaction_details_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_compares`
--
ALTER TABLE `warehouse_compares`
  ADD CONSTRAINT `warehouse_compares_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_compares_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `addrbooks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouse_items`
--
ALTER TABLE `warehouse_items`
  ADD CONSTRAINT `warehouse_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_items_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `addrbooks` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
