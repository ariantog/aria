-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 22, 2026 at 03:37 AM
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
-- Database: `u343060430_aria`
--

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `operations`
--

INSERT INTO `operations` (`id`, `name`, `description`) VALUES
(3, 'Marketing', ''),
(4, 'Gaji dan Upah', ''),
(7, 'Sewa Menyewa', ''),
(8, 'Perlengkapan Kantor', ''),
(9, 'Utilitas', ''),
(10, 'Perbankan', ''),
(11, 'Research & Development', ''),
(12, 'Asuransi', ''),
(13, 'Repair & Maintenance', ''),
(14, 'Jasa Profesional', ''),
(15, 'Jasa Training', ''),
(16, 'Biaya Langganan', ''),
(17, 'Logistik', ''),
(18, 'Pajak', ''),
(19, 'Entertain', ''),
(20, 'Perijinan', ''),
(21, 'Biaya Karyawan', ''),
(22, 'Lain-lain', ''),
(24, 'Non-Operational', ''),
(25, 'General', ''),
(26, 'Sumbangan', ''),
(27, 'Ongkos Produksi', 'Non quantifiable production costs'),
(28, 'Operational Luar', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
