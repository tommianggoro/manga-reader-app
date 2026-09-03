-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 03, 2026 at 03:15 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `manga_reader`
--

-- --------------------------------------------------------

--
-- Table structure for table `chapters`
--

CREATE TABLE `chapters` (
  `id` int NOT NULL,
  `chapter_id` varchar(191) COLLATE utf8mb4_general_ci NOT NULL,
  `manga_id` varchar(191) COLLATE utf8mb4_general_ci NOT NULL,
  `source` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'shngm',
  `source_ref` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `chapter_number` decimal(8,2) NOT NULL,
  `chapter_title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `base_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `prev_chapter_id` varchar(191) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `prev_verified` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chapter_images`
--

CREATE TABLE `chapter_images` (
  `id` int NOT NULL,
  `chapter_id` varchar(191) COLLATE utf8mb4_general_ci NOT NULL,
  `page_number` int NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chapter_sync_cursor`
--

CREATE TABLE `chapter_sync_cursor` (
  `id` int NOT NULL,
  `manga_id` varchar(191) COLLATE utf8mb4_general_ci NOT NULL,
  `source` varchar(32) COLLATE utf8mb4_general_ci NOT NULL,
  `source_chapter_ref` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `chapter_number` decimal(8,2) NOT NULL,
  `prev_source_chapter_ref` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `last_attempt_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mangas`
--

CREATE TABLE `mangas` (
  `id` int NOT NULL,
  `manga_id` varchar(191) COLLATE utf8mb4_general_ci NOT NULL,
  `preferred_source` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `cover_image_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `latest_chapter_number` decimal(8,2) DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `alternative_title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `author` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `artist` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `genres` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `release_year` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manga_sources`
--

CREATE TABLE `manga_sources` (
  `id` int NOT NULL,
  `manga_id` varchar(191) COLLATE utf8mb4_general_ci NOT NULL,
  `source` varchar(32) COLLATE utf8mb4_general_ci NOT NULL,
  `source_ref` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reading_progress`
--

CREATE TABLE `reading_progress` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `chapter_id` varchar(191) COLLATE utf8mb4_general_ci NOT NULL,
  `scroll_position` int DEFAULT NULL,
  `single_page_index` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration_attempts`
--

CREATE TABLE `registration_attempts` (
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `window_started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `telegram_bot_state`
--

CREATE TABLE `telegram_bot_state` (
  `id` tinyint NOT NULL DEFAULT '1',
  `last_update_id` bigint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trending_manga`
--

CREATE TABLE `trending_manga` (
  `id` int NOT NULL,
  `source` varchar(32) COLLATE utf8mb4_general_ci NOT NULL,
  `source_ref` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `cover_image_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `latest_chapter_number` decimal(8,2) DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT NULL,
  `rank_position` int NOT NULL DEFAULT '0',
  `fetched_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `telegram_chat_id` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telegram_link_code` varchar(16) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telegram_link_code_expires` datetime DEFAULT NULL,
  `telegram_notify_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `telegram_linked_at` timestamp NULL DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `profile_photo_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_manga_state`
--

CREATE TABLE `user_manga_state` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `manga_id` varchar(191) COLLATE utf8mb4_general_ci NOT NULL,
  `is_favorite` tinyint(1) NOT NULL DEFAULT '0',
  `last_read_chapter_id` varchar(191) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_read_chapter_number` decimal(8,2) DEFAULT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chapters`
--
ALTER TABLE `chapters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chapter_id` (`chapter_id`),
  ADD UNIQUE KEY `uniq_manga_chapter_number` (`manga_id`,`chapter_number`),
  ADD KEY `idx_manga_chapter` (`manga_id`,`chapter_number`);

--
-- Indexes for table `chapter_images`
--
ALTER TABLE `chapter_images`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_chapter_page` (`chapter_id`,`page_number`),
  ADD KEY `idx_chapter_page` (`chapter_id`,`page_number`);

--
-- Indexes for table `chapter_sync_cursor`
--
ALTER TABLE `chapter_sync_cursor`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_source_chapter` (`source`,`source_chapter_ref`),
  ADD KEY `idx_manga_source` (`manga_id`,`source`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`ip_address`);

--
-- Indexes for table `mangas`
--
ALTER TABLE `mangas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `manga_id` (`manga_id`);

--
-- Indexes for table `manga_sources`
--
ALTER TABLE `manga_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_source_ref` (`source`,`source_ref`),
  ADD UNIQUE KEY `uniq_manga_source` (`manga_id`,`source`);

--
-- Indexes for table `reading_progress`
--
ALTER TABLE `reading_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_chapter` (`user_id`,`chapter_id`),
  ADD KEY `rp_chapter_fk` (`chapter_id`);

--
-- Indexes for table `registration_attempts`
--
ALTER TABLE `registration_attempts`
  ADD PRIMARY KEY (`ip_address`);

--
-- Indexes for table `telegram_bot_state`
--
ALTER TABLE `telegram_bot_state`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trending_manga`
--
ALTER TABLE `trending_manga`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_source_ref_trending` (`source`,`source_ref`),
  ADD KEY `idx_source_rank` (`source`,`rank_position`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uniq_telegram_chat_id` (`telegram_chat_id`);

--
-- Indexes for table `user_manga_state`
--
ALTER TABLE `user_manga_state`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_manga` (`user_id`,`manga_id`),
  ADD KEY `idx_manga` (`manga_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chapters`
--
ALTER TABLE `chapters`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chapter_images`
--
ALTER TABLE `chapter_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chapter_sync_cursor`
--
ALTER TABLE `chapter_sync_cursor`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mangas`
--
ALTER TABLE `mangas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manga_sources`
--
ALTER TABLE `manga_sources`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reading_progress`
--
ALTER TABLE `reading_progress`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trending_manga`
--
ALTER TABLE `trending_manga`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_manga_state`
--
ALTER TABLE `user_manga_state`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chapters`
--
ALTER TABLE `chapters`
  ADD CONSTRAINT `chapters_ibfk_1` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`manga_id`) ON DELETE CASCADE;

--
-- Constraints for table `chapter_images`
--
ALTER TABLE `chapter_images`
  ADD CONSTRAINT `chapter_images_ibfk_1` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`chapter_id`) ON DELETE CASCADE;

--
-- Constraints for table `manga_sources`
--
ALTER TABLE `manga_sources`
  ADD CONSTRAINT `manga_sources_ibfk_1` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`manga_id`) ON DELETE CASCADE;

--
-- Constraints for table `reading_progress`
--
ALTER TABLE `reading_progress`
  ADD CONSTRAINT `rp_chapter_fk` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`chapter_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rp_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_manga_state`
--
ALTER TABLE `user_manga_state`
  ADD CONSTRAINT `ums_manga_fk` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`manga_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ums_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
