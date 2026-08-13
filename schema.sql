-- phpMyAdmin SQL Dump
-- Manga Reader -- schema diperbaiki utk dukungan multi-source (Shinigami + Komiku)
--
-- PERBAIKAN dari versi sebelumnya:
--  1. manga_id / chapter_id / prev_chapter_id diperpanjang jadi VARCHAR(191)
--     (semula VARCHAR(36), pas utk UUID Shinigami tapi tidak muat slug URL
--     Komiku yang bisa jauh lebih panjang).
--  2. chapters.chapter_number diubah dari INT(11) jadi DECIMAL(8,2) -- INT akan
--     memotong nomor desimal (mis. chapter "154.5" jadi "154"), dan ini
--     KRUSIAL utk constraint dedup lintas-sumber baru di bawah (dua chapter
--     dgn nomor "154" dan "154.5" harus dianggap BEDA, bukan sama).
--  3. Kolom baru: chapters.source, chapters.source_ref, mangas.preferred_source.
--  4. Tabel baru: manga_sources, chapter_sync_cursor.
--  5. Unique key baru: chapters (manga_id, chapter_number) -- 1 manga tidak
--     boleh punya 2 baris nomor sama, walau dari sumber berbeda.
--
-- CATATAN: file ini utk INSTALL BARU (database kosong). Kalau kamu SUDAH
-- punya data (manga/chapter tersimpan), JANGAN jalankan file ini -- pakai
-- migration_multisource.sql (ALTER, bukan CREATE) supaya data lama tidak hilang.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Table structure for table `mangas`
--

CREATE TABLE `mangas` (
  `id` int(11) NOT NULL,
  `manga_id` varchar(191) NOT NULL,
  `preferred_source` varchar(32) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image_url` varchar(500) DEFAULT NULL,
  `latest_chapter_number` decimal(8,2) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `alternative_title` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `artist` varchar(255) DEFAULT NULL,
  `genres` varchar(500) DEFAULT NULL,
  `release_year` varchar(10) DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manga_sources`
-- (binding manga internal <-> ID/slug di tiap sumber eksternal)
--

CREATE TABLE `manga_sources` (
  `id` int(11) NOT NULL,
  `manga_id` varchar(191) NOT NULL,
  `source` varchar(32) NOT NULL,
  `source_ref` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chapters`
--

CREATE TABLE `chapters` (
  `id` int(11) NOT NULL,
  `chapter_id` varchar(191) NOT NULL,
  `manga_id` varchar(191) NOT NULL,
  `source` varchar(32) NOT NULL DEFAULT 'shngm',
  `source_ref` varchar(255) NOT NULL DEFAULT '',
  `chapter_number` decimal(8,2) NOT NULL,
  `chapter_title` varchar(255) DEFAULT NULL,
  `base_url` varchar(255) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `prev_chapter_id` varchar(191) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `prev_verified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chapter_sync_cursor`
-- (bookkeeping jalan-mundur sync per sumber, terpisah dari `chapters` supaya
--  resume cepat & sumber2 tidak saling tumpang tindih walau nomornya sama)
--

CREATE TABLE `chapter_sync_cursor` (
  `id` int(11) NOT NULL,
  `manga_id` varchar(191) NOT NULL,
  `source` varchar(32) NOT NULL,
  `source_chapter_ref` varchar(255) NOT NULL,
  `chapter_number` decimal(8,2) NOT NULL,
  `prev_source_chapter_ref` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chapter_images`
--

CREATE TABLE `chapter_images` (
  `id` int(11) NOT NULL,
  `chapter_id` varchar(191) NOT NULL,
  `page_number` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_attempt_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reading_progress`
--

CREATE TABLE `reading_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `chapter_id` varchar(191) NOT NULL,
  `scroll_position` int(11) DEFAULT NULL,
  `single_page_index` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_manga_state`
--

CREATE TABLE `user_manga_state` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `manga_id` varchar(191) NOT NULL,
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0,
  `last_read_chapter_id` varchar(191) DEFAULT NULL,
  `last_read_chapter_number` decimal(8,2) DEFAULT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

ALTER TABLE `mangas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `manga_id` (`manga_id`);

ALTER TABLE `manga_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_source_ref` (`source`,`source_ref`),
  ADD UNIQUE KEY `uniq_manga_source` (`manga_id`,`source`);

ALTER TABLE `chapters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chapter_id` (`chapter_id`),
  ADD UNIQUE KEY `uniq_manga_chapter_number` (`manga_id`,`chapter_number`),
  ADD KEY `idx_manga_chapter` (`manga_id`,`chapter_number`);

ALTER TABLE `chapter_sync_cursor`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_source_chapter` (`source`,`source_chapter_ref`),
  ADD KEY `idx_manga_source` (`manga_id`,`source`);

ALTER TABLE `chapter_images`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_chapter_page` (`chapter_id`,`page_number`),
  ADD KEY `idx_chapter_page` (`chapter_id`,`page_number`);

ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`ip_address`);

ALTER TABLE `reading_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_chapter` (`user_id`,`chapter_id`),
  ADD KEY `rp_chapter_fk` (`chapter_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `user_manga_state`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_manga` (`user_id`,`manga_id`),
  ADD KEY `idx_manga` (`manga_id`);

--
-- AUTO_INCREMENT for dumped tables
--

ALTER TABLE `mangas` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `manga_sources` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `chapters` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `chapter_sync_cursor` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `chapter_images` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `reading_progress` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `user_manga_state` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

ALTER TABLE `manga_sources`
  ADD CONSTRAINT `manga_sources_ibfk_1` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`manga_id`) ON DELETE CASCADE;

ALTER TABLE `chapters`
  ADD CONSTRAINT `chapters_ibfk_1` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`manga_id`) ON DELETE CASCADE;

ALTER TABLE `chapter_images`
  ADD CONSTRAINT `chapter_images_ibfk_1` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`chapter_id`) ON DELETE CASCADE;

ALTER TABLE `reading_progress`
  ADD CONSTRAINT `rp_chapter_fk` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`chapter_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rp_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_manga_state`
  ADD CONSTRAINT `ums_manga_fk` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`manga_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ums_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
