-- Migration: User Tags System
-- Creates user_tags table for custom tag definitions per user
-- Creates manga_user_tags junction table for many-to-many relationship

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Tabel user_tags: definisi tag custom per user
-- Setiap user bisa membuat tag sendiri (mis. "Prioritas", "Baca Nanti", "Favorit Genre", dll)
CREATE TABLE `user_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `color` varchar(7) DEFAULT '#6c757d', -- hex color, default abu-abu
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_tag_name` (`user_id`, `name`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel manga_user_tags: junction table many-to-many antara manga dan user_tags
-- Satu manga bisa punya banyak tag, satu tag bisa diassign ke banyak manga (untuk user yg sama)
CREATE TABLE `manga_user_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `manga_id` varchar(191) COLLATE utf8mb4_general_ci NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_manga_tag` (`user_id`, `manga_id`, `tag_id`),
  KEY `idx_user_manga` (`user_id`, `manga_id`),
  KEY `idx_tag_id` (`tag_id`),
  CONSTRAINT `mut_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mut_manga_fk` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`manga_id`) ON DELETE CASCADE,
  CONSTRAINT `mut_tag_fk` FOREIGN KEY (`tag_id`) REFERENCES `user_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;