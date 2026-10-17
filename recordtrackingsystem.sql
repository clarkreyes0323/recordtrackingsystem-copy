-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 07, 2026 at 11:39 AM
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
  `user_id` int(11) DEFAULT NULL,
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

INSERT INTO `document_requests` (`id`, `user_id`, `file_no`, `student_no`, `firstname`, `middlename`, `lastname`, `year_level`, `program`, `email`, `doc_type`, `purpose`, `claiming_area`, `status`, `created_at`) VALUES
(1, NULL, 'bTZ5ZnNoKzEzNWVpTEJLOG0yRkdpdUNaSm5SenF5YkFCK0h0bXcxc0g1QT0=', 'TW4vVE02OFNvVDdzdDRWL1JXUGwyUT09', 'WEtvT1FMQ0tPd0p1M2x3am5CcWFjZz09', 'aWMyS1crdmtyZDBYenRFeUF4enhtUT09', 'c2ZFU3ozd2tMMjVXTG0vSFVWbjdNQT09', 'cVlGWTVJVFhBRENwSTFXTTNsY1FEUT09', 'QzNWQXNKS1lzZlRRZ1lkRFZDc1YrQT09', 'ZEI5Q2luZjBRSmZLQklzRGl4S3pmWVpuSE15VEs4N29kYmQrSXM1Z3RvZz0=', 'clYyN1RMcGR4bitGcHpYM0xMdEF0UkNFSjJkdXVNVDFuejJmcSs5OWF1WT0=', 'Mzk5a0JpK3k3U01IY3cvRnpJVTVoV1BINHhQUERhdjhXUmxZY3RKSk0vYz0=', 'bEhvYyt6Ry9NQUpod3pldENJVWFaZz09', 'V2FBWTRNZS9Ua3RNakhkaXU1Z2tIZz09', '2026-09-22 10:30:49'),
(2, NULL, 'UWNKVm1CZUdDVTg0TEdMVXZvTkJJYVEySldRTExzVVoyQ0RDL2U0eVNZRT0=', 'cTRYRDNFc3dSY3ZIZnpxVURlTlFrdz09', 'OW5CSGpGbkc3b082c0VoS3g5Nk9qZz09', 'ZFl2T1F4bTlCM0k4NkEvcXdPOHE4Zz09', 'c1hodytiSGgvek53MjdSaE5PM2pTUT09', 'cVlGWTVJVFhBRENwSTFXTTNsY1FEUT09', 'QzNWQXNKS1lzZlRRZ1lkRFZDc1YrQT09', 'VjNCQ1NEL0MxUXZvNmhTdmxOUkQvSlg1WHRwdjdUS05JZnVnR3d1cUhuWT0=', 'SVlzZWVsbWNPRTR4bms5eW5zcTdZWE5EMlVsbSs1Y1FqT3crTWtjcWdpWT0=', 'd25KcTZHNnJxWmQrOGt1WVV3eGRPQT09', 'VFY5cEVuR3oycGpmTitqelRtTlRHUT09', 'WnVwTWdmOWhmUWVwaXd4NUpJa3VneHpERStoVklKaUFnZW9URkpaa0xrND0=', '2026-09-23 07:17:09'),
(3, NULL, 'ZS9ReVhmWWVLWmtSa1NiTGpxdVJOMytsMGhFcHBuYjlESDNidk9ZdWNJMD0=', 'MTJYZVJFQlAyenlnRU15NStZOXkrZz09', 'dnptWE50UGtJY0JtWEE4NzhsYTF1Zz09', 'NlQ5OFpDVHFadkUrSnk5K0xLWG5UZz09', 'dUFybHpHNGJwb3hHSC9XTDAwV2J3Zz09', 'WHhQTk1CYVo1MXJUTWZYSFZOdDh3UT09', 'N0lOUGZ5WTRuamNwWkVaaVdpSGhIdz09', 'NWJCUlo1aFFpb1F3TXErS0RQanRObXptQkhZSkRtT3ZOd2tlcGhjMmFXWT0=', 'UGY0N0YxRUZ3RXJWNCtEWjB1UHFEQT09', 'Mzk5a0JpK3k3U01IY3cvRnpJVTVoV1BINHhQUERhdjhXUmxZY3RKSk0vYz0=', 'SFk5Zkx5RnJTYXE1VWZtTTVkcmJSODAybXhHV3RmVEtpN1pzTkZlT0orTT0=', 'MHBRNU5LNXBjUXZRSTVRMnRJajlwZz09', '2026-09-23 13:07:22'),
(4, 5, 'YkdWZnV0N2tqc1l5bW1HNzFubk1Ld2pYKy9PRWtxOXp0b0JZNHB1ay9COD0=', 'c3Q3UWg1SGZNUk5UTlc2Q0hqMzAxZz09', 'TzE0TU1aNWFxOVhVbmM2bWxvN2Vjdz09', 'K2MraExZaWVKTHZsc04wS2FvdjBaUT09', 'K0Vtc3dBL1BvLzFvR3ZNc1EvRzVsdz09', 'WHhQTk1CYVo1MXJUTWZYSFZOdDh3UT09', 'QzNWQXNKS1lzZlRRZ1lkRFZDc1YrQT09', 'NkRkYnRFK0NXT2QyWXdBOHVDOHRkc1R4S2Rza0k3bUFuZE5reG1aQndUUT0=', 'UGY0N0YxRUZ3RXJWNCtEWjB1UHFEQT09', 'Mzk5a0JpK3k3U01IY3cvRnpJVTVoV1BINHhQUERhdjhXUmxZY3RKSk0vYz0=', 'SFk5Zkx5RnJTYXE1VWZtTTVkcmJSODAybXhHV3RmVEtpN1pzTkZlT0orTT0=', 'MHBRNU5LNXBjUXZRSTVRMnRJajlwZz09', '2026-09-28 01:09:47'),
(5, 5, 'MUtrcGl2MmZpdzBsRGF2NFVMR1N5QnlzcFhTaGhMZ3d1TTFERnBlbHVucz0=', 'VGZ2aS91cVY4UWRIMUxrblh1S2IrQT09', 'Kzl0YitUc2ZnbXFQRTFNMG1xeCtUdz09', 'NlQ5OFpDVHFadkUrSnk5K0xLWG5UZz09', 'b0hnd0NIWDF3d3VkK1FKSnhhVnB6QT09', 'cVlGWTVJVFhBRENwSTFXTTNsY1FEUT09', 'QzNWQXNKS1lzZlRRZ1lkRFZDc1YrQT09', 'NkRkYnRFK0NXT2QyWXdBOHVDOHRkc1R4S2Rza0k3bUFuZE5reG1aQndUUT0=', 'clYyN1RMcGR4bitGcHpYM0xMdEF0UkNFSjJkdXVNVDFuejJmcSs5OWF1WT0=', 'Mzk5a0JpK3k3U01IY3cvRnpJVTVoV1BINHhQUERhdjhXUmxZY3RKSk0vYz0=', 'SFk5Zkx5RnJTYXE1VWZtTTVkcmJSODAybXhHV3RmVEtpN1pzTkZlT0orTT0=', 'MHBRNU5LNXBjUXZRSTVRMnRJajlwZz09', '2026-09-28 01:49:05'),
(6, 5, 'YkptVUJmY0I2emNPSjlLblBTUFcwY1dCMWZGSG55dEV2WEwwWkFiTWFVND0=', 'VGZ2aS91cVY4UWRIMUxrblh1S2IrQT09', 'Kzl0YitUc2ZnbXFQRTFNMG1xeCtUdz09', 'NlQ5OFpDVHFadkUrSnk5K0xLWG5UZz09', 'b0hnd0NIWDF3d3VkK1FKSnhhVnB6QT09', 'cVlGWTVJVFhBRENwSTFXTTNsY1FEUT09', 'QzNWQXNKS1lzZlRRZ1lkRFZDc1YrQT09', 'NkRkYnRFK0NXT2QyWXdBOHVDOHRkc1R4S2Rza0k3bUFuZE5reG1aQndUUT0=', 'SVlzZWVsbWNPRTR4bms5eW5zcTdZWE5EMlVsbSs1Y1FqT3crTWtjcWdpWT0=', 'Mzk5a0JpK3k3U01IY3cvRnpJVTVoV1BINHhQUERhdjhXUmxZY3RKSk0vYz0=', 'SFk5Zkx5RnJTYXE1VWZtTTVkcmJSODAybXhHV3RmVEtpN1pzTkZlT0orTT0=', 'QmpFVUdkTkhHbzhKMFZEd2Y0bjhTUT09', '2026-09-28 04:05:03'),
(7, NULL, 'V0pyZ1VQWGlzbmFUWmtOaE5wL1o3SG8xdnZSV2tITEN1T29kWm1rNEpHbz0=', 'dU9ZN0V1dGY0bkNvaWw1MWNGSU5yUT09', 'TUs0RHRkRXo1RGhOd3psZVNBWFN2QT09', 'NlQ5OFpDVHFadkUrSnk5K0xLWG5UZz09', 'c2pnUCtGanVtTHFlRjNwTlI2Lzlkdz09', 'ZHVSc1Z6bXYwaFZHVmh0YnRUU2ZhVm53KzdjMy9ZR254ZWFqOWQ5Mk9ZZz0=', 'eDl3cXpFbUozNXI0SVdwOTVMSWJLNi8xNmpOQUx3eTVCekFwbFJHdlhFRT0=', 'eVFKOVA1WUVFUytnT2VRQ2hQd04xV0dqOTRuemhGbTFTSEp0cXlHSzdlTT0=', 'TEx3RENGR3c5WktnMG9rRU5EUGhXZz09', 'ZUhDZHdpY3pRTTJDblpxMWxnK09wdz09', 'SFk5Zkx5RnJTYXE1VWZtTTVkcmJSODAybXhHV3RmVEtpN1pzTkZlT0orTT0=', 'MHBRNU5LNXBjUXZRSTVRMnRJajlwZz09', '2026-10-06 05:11:36'),
(8, 5, 'djh0c01xSk1SZjBzM3pscStPVlkvL1pMZFBxYmdMbHFrMXFoTWNmR2dhZz0=', 'NFpLNCtJbnROMTdjbU8xaVVTcmU0QT09', 'NE1sWWY1a0tyY1JCRDFVSndyQ0g0QT09', 'ZHA5bGVFaGVqbHZPNGorYkNRWWkxdz09', 'QTlSUE8vQmhvcG85YUx4MlFRTWlXZz09', 'ZHVSc1Z6bXYwaFZHVmh0YnRUU2ZhVm53KzdjMy9ZR254ZWFqOWQ5Mk9ZZz0=', 'eDl3cXpFbUozNXI0SVdwOTVMSWJLNi8xNmpOQUx3eTVCekFwbFJHdlhFRT0=', 'Z2t0aTZvRDJJeHlHNTN4eGNBbklDQT09', 'd0piWEx6eGRQWHV5bnVtK2tqbkk3Rnh4dVR3RHZITUNUSXVJNWh6aGlyaz0=', 'ZUhDZHdpY3pRTTJDblpxMWxnK09wdz09', 'SFk5Zkx5RnJTYXE1VWZtTTVkcmJSODAybXhHV3RmVEtpN1pzTkZlT0orTT0=', 'MHBRNU5LNXBjUXZRSTVRMnRJajlwZz09', '2026-10-06 05:28:49');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `message` varchar(500) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `recipient_id`, `request_id`, `message`, `is_read`, `created_at`) VALUES
(2, 4, 8, 'New document request DOC-20261006-2087162F was submitted.', 0, '2026-10-06 05:28:49'),
(3, 5, 6, 'Your request DOC-20260928-9F9995B6 was updated: status changed from Pending to Processing.', 0, '2026-10-06 05:41:26');

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
(5, 'Gabriel John Dela Paz', 'NkRkYnRFK0NXT2QyWXdBOHVDOHRkc1R4S2Rza0k3bUFuZE5reG1aQndUUT0=', '$2y$10$TgaUoHcSx19oltqwTOQ3d.pGHXuXfDIsin2XyEFD8ZjnfGGQkDnIS', 'student', '2026-09-27 15:31:14'),
(6, 'FEUR Registrar', 'L2tqS0ZoWTFqZ2Z0U0hoQ3ozRlZtcUkxUCs5cUd3SHIxSXlZdzhtZmRHK1U5K3lMbWJtaFNIeHVrcnQ3TnJRUw==', '$2y$10$wapiAJivxnbWpHbWcSS4veAAzNER58XkQyOQU8NljFJipKLH/W/tu', 'admin', '2026-10-07 07:36:54'),
(7, 'Juan Dela Cruz', 'anllY201UnVMVytyemNQSi9DQkEzUT09', '$2y$10$LBGcA1h.3e4Ic1PSOzeezOC5wEgM7xGkBnxZYcr6uRLaT212T4gYi', 'student', '2026-10-07 09:27:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient_unread_created` (`recipient_id`,`is_read`,`created_at`),
  ADD KEY `request_id` (`request_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `document_requests_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_recipient_fk` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
