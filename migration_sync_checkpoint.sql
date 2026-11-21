-- ============================================================================
-- MIGRASI: Tabel Checkpoint Sinkronisasi Manga (untuk resume mid-fanout)
-- ============================================================================
-- Jalankan sekali lewat phpMyAdmin / mysql client pada database manga_reader
-- yang sudah ada isinya (bukan install baru).
--
-- Tabel ini menyimpan posisi resume saat sync manga dibatasi oleh maxChaptersPerRun
-- dan perlu dilanjutkan dari tengah fan-out ke multiple sources.
-- ============================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- Tabel baru: manga_sync_checkpoint
-- Menyimpan state resume untuk genericSyncMangaAllSources:
--   - manga_id: manga yang sedang disync
--   - current_source_index: indeks source yang sedang diproses (0-based)
--   - next_chapter_refs: JSON mapping source -> next_chapter_ref untuk resume
--   - max_chapters_per_run: nilai limit yang dipakai (untuk validasi konsistensi)
--   - updated_at: timestamp terakhir update checkpoint
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `manga_sync_checkpoint` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `manga_id` VARCHAR(191) NOT NULL,
  `current_source_index` INT(11) NOT NULL DEFAULT 0,
  `next_chapter_refs` JSON NOT NULL,
  `max_chapters_per_run` INT(11) NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_manga_checkpoint` (`manga_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;