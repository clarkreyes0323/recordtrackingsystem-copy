-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 28, 2026 at 04:19 AM
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
-- Database: `recordtrackingsystem`
--

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL,
  `file_no` varchar(255) NOT NULL,
  `student_no` varchar(255) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `middlename` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) NOT NULL,
  `year_level` varchar(255) NOT NULL,
  `program` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `doc_type` varchar(255) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `claiming_area` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_requests`
--

INSERT INTO `document_requests` (`id`, `file_no`, `student_no`, `firstname`, `middlename`, `lastname`, `year_level`, `program`, `email`, `doc_type`, `purpose`, `claiming_area`, `status`, `created_at`) VALUES
(1, 'bTZ5ZnNoKzEzNWVpTEJLOG0yRkdpdUNaSm5SenF5YkFCK0h0bXcxc0g1QT0=', 'TW4vVE02OFNvVDdzdDRWL1JXUGwyUT09', 'WEtvT1FMQ0tPd0p1M2x3am5CcWFjZz09', 'aWMyS1crdmtyZDBYenRFeUF4enhtUT09', 'c2ZFU3ozd2tMMjVXTG0vSFVWbjdNQT09', 'cVlGWTVJVFhBRENwSTFXTTNsY1FEUT09', 'QzNWQXNKS1lzZlRRZ1lkRFZDc1YrQT09', 'ZEI5Q2luZjBRSmZLQklzRGl4S3pmWVpuSE15VEs4N29kYmQrSXM1Z3RvZz0=', 'clYyN1RMcGR4bitGcHpYM0xMdEF0UkNFSjJkdXVNVDFuejJmcSs5OWF1WT0=', 'Mzk5a0JpK3k3U01IY3cvRnpJVTVoV1BINHhQUERhdjhXUmxZY3RKSk0vYz0=', 'V3l5b3dvNXBQZjRtUmxDdHBnMEcvdz09', 'QmpFVUdkTkhHbzhKMFZEd2Y0bjhTUT09', '2026-09-22 10:30:49'),
(2, 'UWNKVm1CZUdDVTg0TEdMVXZvTkJJYVEySldRTExzVVoyQ0RDL2U0eVNZRT0=', 'cTRYRDNFc3dSY3ZIZnpxVURlTlFrdz09', 'OW5CSGpGbkc3b082c0VoS3g5Nk9qZz09', 'ZFl2T1F4bTlCM0k4NkEvcXdPOHE4Zz09', 'c1hodytiSGgvek53MjdSaE5PM2pTUT09', 'cVlGWTVJVFhBRENwSTFXTTNsY1FEUT09', 'QzNWQXNKS1lzZlRRZ1lkRFZDc1YrQT09', 'VjNCQ1NEL0MxUXZvNmhTdmxOUkQvSlg1WHRwdjdUS05JZnVnR3d1cUhuWT0=', 'SVlzZWVsbWNPRTR4bms5eW5zcTdZWE5EMlVsbSs1Y1FqT3crTWtjcWdpWT0=', 'd25KcTZHNnJxWmQrOGt1WVV3eGRPQT09', 'VFY5cEVuR3oycGpmTitqelRtTlRHUT09', 'WnVwTWdmOWhmUWVwaXd4NUpJa3VneHpERStoVklKaUFnZW9URkpaa0xrND0=', '2026-09-23 07:17:09'),
(3, 'ZS9ReVhmWWVLWmtSa1NiTGpxdVJOMytsMGhFcHBuYjlESDNidk9ZdWNJMD0=', 'MTJYZVJFQlAyenlnRU15NStZOXkrZz09', 'dnptWE50UGtJY0JtWEE4NzhsYTF1Zz09', 'NlQ5OFpDVHFadkUrSnk5K0xLWG5UZz09', 'dUFybHpHNGJwb3hHSC9XTDAwV2J3Zz09', 'WHhQTk1CYVo1MXJUTWZYSFZOdDh3UT09', 'N0lOUGZ5WTRuamNwWkVaaVdpSGhIdz09', 'NWJCUlo1aFFpb1F3TXErS0RQanRObXptQkhZSkRtT3ZOd2tlcGhjMmFXWT0=', 'UGY0N0YxRUZ3RXJWNCtEWjB1UHFEQT09', 'Mzk5a0JpK3k3U01IY3cvRnpJVTVoV1BINHhQUERhdjhXUmxZY3RKSk0vYz0=', 'SFk5Zkx5RnJTYXE1VWZtTTVkcmJSODAybXhHV3RmVEtpN1pzTkZlT0orTT0=', 'MHBRNU5LNXBjUXZRSTVRMnRJajlwZz09', '2026-09-23 13:07:22'),
(4, 'YkdWZnV0N2tqc1l5bW1HNzFubk1Ld2pYKy9PRWtxOXp0b0JZNHB1ay9COD0=', 'c3Q3UWg1SGZNUk5UTlc2Q0hqMzAxZz09', 'TzE0TU1aNWFxOVhVbmM2bWxvN2Vjdz09', 'K2MraExZaWVKTHZsc04wS2FvdjBaUT09', 'K0Vtc3dBL1BvLzFvR3ZNc1EvRzVsdz09', 'WHhQTk1CYVo1MXJUTWZYSFZOdDh3UT09', 'QzNWQXNKS1lzZlRRZ1lkRFZDc1YrQT09', 'NkRkYnRFK0NXT2QyWXdBOHVDOHRkc1R4S2Rza0k3bUFuZE5reG1aQndUUT0=', 'UGY0N0YxRUZ3RXJWNCtEWjB1UHFEQT09', 'Mzk5a0JpK3k3U01IY3cvRnpJVTVoV1BINHhQUERhdjhXUmxZY3RKSk0vYz0=', 'SFk5Zkx5RnJTYXE1VWZtTTVkcmJSODAybXhHV3RmVEtpN1pzTkZlT0orTT0=', 'MHBRNU5LNXBjUXZRSTVRMnRJajlwZz09', '2026-09-28 01:09:47'),
(5, 'MUtrcGl2MmZpdzBsRGF2NFVMR1N5QnlzcFhTaGhMZ3d1TTFERnBlbHVucz0=', 'VGZ2aS91cVY4UWRIMUxrblh1S2IrQT09', 'Kzl0YitUc2ZnbXFQRTFNMG1xeCtUdz09', 'NlQ5OFpDVHFadkUrSnk5K0xLWG5UZz09', 'b0hnd0NIWDF3d3VkK1FKSnhhVnB6QT09', 'cVlGWTVJVFhBRENwSTFXTTNsY1FEUT09', 'QzNWQXNKS1lzZlRRZ1lkRFZDc1YrQT09', 'NkRkYnRFK0NXT2QyWXdBOHVDOHRkc1R4S2Rza0k3bUFuZE5reG1aQndUUT0=', 'clYyN1RMcGR4bitGcHpYM0xMdEF0UkNFSjJkdXVNVDFuejJmcSs5OWF1WT0=', 'Mzk5a0JpK3k3U01IY3cvRnpJVTVoV1BINHhQUERhdjhXUmxZY3RKSk0vYz0=', 'SFk5Zkx5RnJTYXE1VWZtTTVkcmJSODAybXhHV3RmVEtpN1pzTkZlT0orTT0=', 'MHBRNU5LNXBjUXZRSTVRMnRJajlwZz09', '2026-09-28 01:49:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','admin') DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(4, 'System Administrator', 'WG1oZkJBWmZ6VlBlZG9MU0FhSnRDVWhNdmd3Njc3KzYwKzQyTmdmbE9jND0=', '$2y$10$.7eN6m6a5yF8XHdDw4MlluxiLpwmKigQivCarnnWTziom5JvE/1fe', 'admin', '2026-09-27 15:19:05'),
(5, 'Gabriel John Dela Paz', 'NkRkYnRFK0NXT2QyWXdBOHVDOHRkc1R4S2Rza0k3bUFuZE5reG1aQndUUT0=', '$2y$10$TgaUoHcSx19oltqwTOQ3d.pGHXuXfDIsin2XyEFD8ZjnfGGQkDnIS', 'student', '2026-09-27 15:31:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
