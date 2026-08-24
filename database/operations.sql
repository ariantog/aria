-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 24, 2026
-- Server version: 11.8.8-MariaDB-log
-- PHP Version: 7.2.34
--
-- Target state after reporting:apply-ledger-plan operation simplification.
-- See plan/reporting/08-ledger-simplification-plan.md and
-- app/Support/OperationSimplificationPlan.php

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `report_slug` varchar(40) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `operations` (15 active categories)
--

INSERT INTO `operations` (`id`, `name`, `description`, `report_slug`, `deleted_at`) VALUES
(3, 'Marketing Umum', '', 'marketing', NULL),
(4, 'Gaji & Upah', '', 'gaji', NULL),
(7, 'Sewa HQ', 'HQ rent only (Sambisari, Gedung)', 'sewa', NULL),
(8, 'Kantor & Utilitas', '', 'kantor', NULL),
(10, 'Perbankan', '', 'bank', NULL),
(12, 'Asuransi', '', 'kantor', NULL),
(13, 'Perawatan & Mesin', '', 'maintenance', NULL),
(14, 'Jasa Profesional', '', 'jasa', NULL),
(17, 'Logistik', '', 'logistik', NULL),
(18, 'Pajak & Retribusi', '', 'pajak', NULL),
(20, 'Perijinan', '', 'lain', NULL),
(21, 'Kesejahteraan Karyawan', '', 'sdm', NULL),
(22, 'Lain-lain', '', 'lain', NULL),
(27, 'Produksi', 'Material, biaya produksi, permak', 'produksi', NULL),
(29, 'Biaya Marketplace', 'Online channel and dept-store partner fees', 'marketplace', NULL),
(30, 'Biaya Toko', 'Physical shop upkeep (WTC, Citos)', 'toko', NULL),
(31, 'Penyesuaian', 'Adjustments, rounding, write-offs', 'penyesuaian', NULL);

--
-- Soft-deleted legacy categories (re-parent accounts before deleting)
--
-- (9, 'Utilitas') → merged into 8
-- (11, 'Research & Development') → merged into 3
-- (15, 'Jasa Training') → merged into 21
-- (16, 'Biaya Langganan') → merged into 8
-- (19, 'Entertain') → merged into 22
-- (24, 'Non-Operational') → split to 29/30/31/22/27
-- (25, 'General') → merged into 22
-- (26, 'Sumbangan') → merged into 22
-- (28, 'Operational Luar') → split to 29/30/22

--
-- Indexes for dumped tables
--

ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
