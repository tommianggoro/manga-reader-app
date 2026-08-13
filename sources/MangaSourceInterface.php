<?php
/**
 * Kontrak WAJIB untuk setiap sumber data manga (Shinigami, Komiku, atau sumber
 * lain di masa depan). Semua kode generic (sync_functions.php, crawler.php,
 * cron_update.php, crawl_all.php, dll) HANYA bicara lewat interface ini dan
 * TIDAK PERNAH tahu detail spesifik satu sumber tertentu.
 *
 * Menambah sumber baru = bikin 1 class baru yang implement interface ini,
 * lalu daftarkan di SourceRegistry.php. Tidak perlu ubah file lain sama sekali.
 */
interface MangaSourceInterface
{
    /** Kunci unik sumber ini, dipakai sbg nilai kolom `source` di DB. Mis: "shngm", "komiku". */
    public function getKey(): string;

    /** Nama tampilan untuk UI, mis. "Shinigami", "Komiku". */
    public function getLabel(): string;

    /**
     * Dikasih input mentah dari form "Tambah Manga" (bisa manga_id polos, atau
     * URL lengkap). Kembalikan source_ref (identitas manga di sumber ini) kalau
     * input ini dikenali sebagai milik sumber ini, atau null kalau bukan.
     *
     * Sumber yang mendeteksi lewat pola URL/domain spesifik harus dicek DULUAN
     * di SourceRegistry (lihat urutan array di sana) sebelum sumber fallback
     * (Shinigami) yang menerima sembarang string bukan-URL.
     */
    public function detect(string $input): ?string;

    /**
     * Ambil info dasar manga + referensi chapter TERBARU (dipakai sbg titik
     * awal jalan-mundur di fetchChapterStep). Kembalikan array ternormalisasi:
     * [
     *   title, alternative_title, description, cover_image_url,
     *   latest_chapter_number, author, artist, genres, release_year, rating,
     *   latest_chapter_ref  <- source_chapter_ref utk chapter terbaru
     * ]
     */
    public function fetchMangaInfo(string $sourceRef): array;

    /**
     * Ambil SATU chapter (dipanggil berulang, mundur satu-per-satu, mengikuti
     * prev_chapter_ref yang dikembalikan) supaya request per-langkah tetap
     * ringan (menghindari batas execution time di shared hosting) dan progres
     * bisa ditampilkan di UI (crawl.php/crawl_all.php).
     *
     * Kembalikan:
     * [
     *   chapter_number, chapter_title, images (array of full image URL),
     *   prev_source_chapter_ref (string|null)
     * ]
     */
    public function fetchChapterStep(string $sourceRef, string $chapterRef): array;
}
