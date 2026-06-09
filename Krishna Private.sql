-- phpMyAdmin SQL Dump
-- version 4.9.5
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 13, 2022 at 06:55 PM
-- Server version: 10.5.12-MariaDB
-- PHP Version: 7.3.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `id18451611_test`
--

-- --------------------------------------------------------

--
-- Table structure for table `history`
--

CREATE TABLE `history` (
  `id_history` int(11) NOT NULL,
  `keys_id` varchar(33) DEFAULT NULL,
  `user_do` varchar(33) DEFAULT NULL,
  `info` mediumtext NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `history`
--

INSERT INTO `history` (`id_history`, `keys_id`, `user_do`, `info`, `created_at`, `updated_at`) VALUES
(534, '534', 'admin', 'PUBG|2DRCd|1|1', '2021-11-16 00:42:03', '2021-11-16 00:42:03'),
(535, '535', 'admin', 'PUBG|2fZgS|1|1', '2022-05-14 01:33:20', '2022-05-14 01:33:20');

-- --------------------------------------------------------

--
-- Table structure for table `keys_code`
--

CREATE TABLE `keys_code` (
  `id_keys` int(11) NOT NULL,
  `game` varchar(32) NOT NULL,
  `user_key` varchar(32) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `expired_date` datetime DEFAULT NULL,
  `max_devices` int(11) DEFAULT NULL,
  `devices` mediumtext DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `registrator` varchar(32) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `keys_code`
--

INSERT INTO `keys_code` (`id_keys`, `game`, `user_key`, `duration`, `expired_date`, `max_devices`, `devices`, `status`, `registrator`, `created_at`, `updated_at`) VALUES
(534, 'PUBG', '2DRCdX4QP1n8hMFA', 1, NULL, 1, NULL, 1, 'admin', '2021-11-16 00:42:03', '2021-11-16 00:42:03'),
(535, 'PUBG', '2fZgSUE0sO7xVL39', 1, NULL, 1, NULL, 1, 'admin', '2022-05-14 01:33:20', '2022-05-14 01:33:20');

-- --------------------------------------------------------

--
-- Table structure for table `referral_code`
--

CREATE TABLE `referral_code` (
  `id_reff` int(11) NOT NULL,
  `code` varchar(128) DEFAULT NULL,
  `set_saldo` int(11) DEFAULT NULL,
  `used_by` varchar(66) DEFAULT NULL,
  `created_by` varchar(66) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_users` int(11) NOT NULL,
  `fullname` varchar(155) DEFAULT NULL,
  `username` varchar(66) NOT NULL,
  `level` int(11) DEFAULT 2,
  `saldo` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `uplink` varchar(66) DEFAULT NULL,
  `password` varchar(155) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_users`, `fullname`, `username`, `level`, `saldo`, `status`, `uplink`, `password`, `created_at`, `updated_at`) VALUES
(1, 'OwnerKrishna', 'OwnerKrishna', 1, 2147447546, 1, 'OwnerKrishna', '$2y$08$noWN0FFCvAmj3ELIQXPufepW6yxKJ/Ou25k0TwtzfbLSFw8TQ69B6', '2021-07-28 17:43:57', '2022-05-14 01:33:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `history`
--
ALTER TABLE `history`
  ADD PRIMARY KEY (`id_history`);

--
-- Indexes for table `keys_code`
--
ALTER TABLE `keys_code`
  ADD PRIMARY KEY (`id_keys`),
  ADD UNIQUE KEY `user_key` (`user_key`);

--
-- Indexes for table `referral_code`
--
ALTER TABLE `referral_code`
  ADD PRIMARY KEY (`id_reff`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_users`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `history`
--
ALTER TABLE `history`
  MODIFY `id_history` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=536;

--
-- AUTO_INCREMENT for table `keys_code`
--
ALTER TABLE `keys_code`
  MODIFY `id_keys` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=536;

--
-- AUTO_INCREMENT for table `referral_code`
--
ALTER TABLE `referral_code`
  MODIFY `id_reff` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_users` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================================
-- Bảng games: quản lý game động cho panel (thay hardcode PUBG)
-- durations = JSON: [{"hours":1,"price":10}, {"hours":24,"price":40}, ...]
-- game_code = mã dùng cho API verify (keys_code.game)
-- ============================================================
CREATE TABLE IF NOT EXISTS `games` (
  `id_game` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `game_code` VARCHAR(32) NOT NULL,
  `durations` TEXT DEFAULT NULL,
  `status` TINYINT(1) DEFAULT 1,
  `sort_order` INT(11) DEFAULT 0,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_game`),
  UNIQUE KEY `uniq_game_code` (`game_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Seed: PUBG (giữ nguyên bảng giá hardcode cũ để không vỡ key đang chạy)
INSERT INTO `games` (`name`, `game_code`, `durations`, `status`, `sort_order`, `created_at`, `updated_at`)
SELECT 'PUBG Mobile', 'PUBG',
  '[{"hours":1,"price":10},{"hours":5,"price":20},{"hours":24,"price":40},{"hours":72,"price":100},{"hours":168,"price":170},{"hours":336,"price":300},{"hours":720,"price":500},{"hours":1440,"price":800}]',
  1, 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `games` WHERE `game_code`='PUBG');
