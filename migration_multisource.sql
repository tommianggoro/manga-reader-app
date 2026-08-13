-- ============================================================================
-- MIGRASI: Dukungan Multi-Source (Shinigami + Komiku + sumber lain di masa depan)
-- ============================================================================
-- WAJIB BACKUP DATABASE DULU SEBELUM MENJALANKAN INI.
-- Jalankan sekali lewat phpMyAdmin / mysql client pada database manga_reader
-- yang sudah ada isinya (bukan install baru).
--
-- CATATAN: MySQL/MariaDB tidak bisa MODIFY kolom yang sedang dipakai foreign
-- key constraint (error #1833) -- karena itu urutannya di sini:
--   1. DROP semua FK yang menunjuk ke kolom yang mau diubah tipenya
--   2. MODIFY kolom-kolomnya
--   3. Tambah kolom/tabel baru
--   4. PASANG LAGI semua FK di akhir
-- ============================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- 0. Lepas dulu semua FOREIGN KEY yang menunjuk ke kolom yang akan diubah
--    tipenya (mangas.manga_id, chapters.chapter_id).
-- ---------------------------------------------------------------------------
ALTER TABLE `chapters` DROP FOREIGN KEY `chapters_ibfk_1`;
ALTER TABLE `chapter_images` DROP FOREIGN KEY `chapter_images_ibfk_1`;
ALTER TABLE `reading_progress` DROP FOREIGN KEY `rp_chapter_fk`;
ALTER TABLE `user_manga_state` DROP FOREIGN KEY `ums_manga_fk`;

-- ---------------------------------------------------------------------------
-- 1. Perpanjang kolom ID yang tadinya VARCHAR(36) (pas untuk UUID Shinigami)
--    supaya muat slug URL Komiku yang bisa jauh lebih panjang.
-- ---------------------------------------------------------------------------
ALTER TABLE `mangas`
  MODIFY `manga_id` VARCHAR(191) NOT NULL;

ALTER TABLE `chapters`
  MODIFY `chapter_id` VARCHAR(191) NOT NULL,
  MODIFY `manga_id` VARCHAR(191) NOT NULL,
  MODIFY `prev_chapter_id` VARCHAR(191) DEFAULT NULL;

ALTER TABLE `chapter_images`
  MODIFY `chapter_id` VARCHAR(191) NOT NULL;

ALTER TABLE `reading_progress`
  MODIFY `chapter_id` VARCHAR(191) NOT NULL;

ALTER TABLE `user_manga_state`
  MODIFY `manga_id` VARCHAR(191) NOT NULL,
  MODIFY `last_read_chapter_id` VARCHAR(191) DEFAULT NULL;

-- 1b. PENTING: chapter_number semula INT(11) -- ini akan memotong nomor
--     desimal (mis. chapter "154.5" jadi "154"). Constraint dedup lintas-sumber
--     di bawah butuh presisi desimal supaya tidak salah anggap dua chapter
--     beda sbg "sama". Ini persis masalah yg didiagnosis check_decimal_chapters.php.
ALTER TABLE `chapters`
  MODIFY `chapter_number` DECIMAL(8,2) NOT NULL;

-- ---------------------------------------------------------------------------
-- 2. Tambah kolom source & source_ref di chapters, isi data lama dgn source=shngm
-- ---------------------------------------------------------------------------
ALTER TABLE `chapters`
  ADD COLUMN `source` VARCHAR(32) NOT NULL DEFAULT 'shngm' AFTER `manga_id`,
  ADD COLUMN `source_ref` VARCHAR(255) NOT NULL DEFAULT '' AFTER `source`;

UPDATE `chapters` SET `source_ref` = `chapter_id` WHERE `source_ref` = '';

-- Dedup lintas-sumber: 1 manga (internal) tidak boleh py 2 chapter nomor sama
ALTER TABLE `chapters`
  ADD UNIQUE KEY `uniq_manga_chapter_number` (`manga_id`, `chapter_number`);

-- 3. Tambah preferred_source (opsional, dipilih manual per manga) di mangas
ALTER TABLE `mangas`
  ADD COLUMN `preferred_source` VARCHAR(32) DEFAULT NULL AFTER `manga_id`;

-- 4. Tabel baru: manga_sources (binding manga internal <-> ID eksternal per sumber)
CREATE TABLE IF NOT EXISTS `manga_sources` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `manga_id` VARCHAR(191) NOT NULL,
  `source` VARCHAR(32) NOT NULL,
  `source_ref` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_source_ref` (`source`, `source_ref`),
  UNIQUE KEY `uniq_manga_source` (`manga_id`, `source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Tabel baru: chapter_sync_cursor (bookkeeping jalan-mundur per sumber,
--    terpisah dari tabel `chapters` supaya resume cepat & sumber-sumber tidak
--    saling tumpang tindih walau chapter_number-nya sama)
CREATE TABLE IF NOT EXISTS `chapter_sync_cursor` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `manga_id` VARCHAR(191) NOT NULL,
  `source` VARCHAR(32) NOT NULL,
  `source_chapter_ref` VARCHAR(255) NOT NULL,
  `chapter_number` DECIMAL(8,2) NOT NULL,
  `prev_source_chapter_ref` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_source_chapter` (`source`, `source_chapter_ref`),
  KEY `idx_manga_source` (`manga_id`, `source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 6. Pasang lagi semua FOREIGN KEY yang dilepas di langkah 0, plus 1 FK baru
--    utk manga_sources.
-- ---------------------------------------------------------------------------
ALTER TABLE `chapters`
  ADD CONSTRAINT `chapters_ibfk_1` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`manga_id`) ON DELETE CASCADE;

ALTER TABLE `chapter_images`
  ADD CONSTRAINT `chapter_images_ibfk_1` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`chapter_id`) ON DELETE CASCADE;

ALTER TABLE `reading_progress`
  ADD CONSTRAINT `rp_chapter_fk` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`chapter_id`) ON DELETE CASCADE;

ALTER TABLE `user_manga_state`
  ADD CONSTRAINT `ums_manga_fk` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`manga_id`) ON DELETE CASCADE;

ALTER TABLE `manga_sources`
  ADD CONSTRAINT `manga_sources_ibfk_1` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`manga_id`) ON DELETE CASCADE;

-- ---------------------------------------------------------------------------
-- 7. Migrasi data lama: setiap manga existing otomatis dapat binding ke source shngm
-- ---------------------------------------------------------------------------
INSERT INTO `manga_sources` (`manga_id`, `source`, `source_ref`)
SELECT `manga_id`, 'shngm', `manga_id` FROM `mangas`
ON DUPLICATE KEY UPDATE `source_ref` = VALUES(`source_ref`);

-- Isi cursor lama dari chapters existing supaya sync shngm bisa langsung resume
-- cepat tanpa jalan ulang dari awal setelah migrasi.
INSERT INTO `chapter_sync_cursor` (`manga_id`, `source`, `source_chapter_ref`, `chapter_number`, `prev_source_chapter_ref`)
SELECT `manga_id`, `source`, `source_ref`, `chapter_number`, `prev_chapter_id`
FROM `chapters`
WHERE `prev_verified` = 1
ON DUPLICATE KEY UPDATE `prev_source_chapter_ref` = VALUES(`prev_source_chapter_ref`);

COMMIT;
