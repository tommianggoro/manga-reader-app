<?php
require_once __DIR__ . "/MangaSourceInterface.php";

/**
 * Adapter AsuraScans (asurascans.com). Sama seperti Komiku, TIDAK ADA API resmi
 * -- semua data didapat lewat scraping HTML halaman publik. Parser ditulis
 * seminimal mungkin bergantung pada nama class CSS (situs pakai Astro, class
 * bisa berubah kapan saja tanpa pemberitahuan), lebih mengandalkan pola URL
 * & domain CDN gambar yang stabil.
 *
 * Kunci penting AsuraScans dibanding sumber lain:
 *  - URL chapter SANGAT PREDIKTIF: https://asurascans.com/comics/{slug}/chapter/{N}
 *    (N bisa desimal, mis. "12.5"). Karena itu, `source_ref` = slug manga --
 *    tidak perlu cari URL chapter lewat regex, cukup rakit langsung.
 *  - PENTING soal `chapter_ref` (dipakai sbg `source_chapter_ref`): sync_functions.php
 *    men-dedup/resume cache (`chapter_sync_cursor`) HANYA berdasarkan (source,
 *    source_chapter_ref) -- TANPA manga_id. Kalau chapter_ref cuma angka polos
 *    ("8"), DUA manga AsuraScans berbeda yang sama2 py "chapter 8" akan TABRAKAN
 *    di kunci yang sama (chapter kedua gagal disimpan / kena skip keliru).
 *    Karena itu chapter_ref di sini SELALU berformat "{slug}/{nomor}", supaya unik
 *    global per sumber, bukan cuma nomor mentah.
 *  - Navigasi "jalan mundur" (prev_source_chapter_ref) TIDAK diambil dari teks
 *    "Prev" (rawan gagal kalau ada ikon/elemen lain di dalam link), melainkan
 *    dgn mengumpulkan semua link chapter di halaman lalu ambil nomor terbesar
 *    yang masih lebih kecil dari chapter saat ini. Di chapter PERTAMA otomatis
 *    tidak ada kandidat lebih kecil -> null -> penanda alami chain selesai.
 *  - Gambar chapter ada di domain cdn.asurascans.com/asura-images/chapters/...
 *    -- cukup stabil utk diambil langsung pakai regex tanpa bergantung pada DOM.
 *
 * CATATAN: parser ini ditulis & diverifikasi manual terhadap 1 contoh manga
 * (halaman detail + 2 halaman chapter) per Agustus 2026, TAPI belum diuji
 * end-to-end di server produksi. Titik yang paling mungkin perlu disesuaikan
 * kalau struktur situs berubah sudah diberi komentar "SESUAIKAN".
 */
class AsuraScansSource implements MangaSourceInterface
{
    private const BASE = "https://asurascans.com";

    public function getKey(): string { return "asura"; }
    public function getLabel(): string { return "AsuraScans"; }

    public function detect(string $input): ?string
    {
        $input = trim($input);
        // Terima URL manga detail ATAU URL chapter, ambil slug-nya saja:
        // https://asurascans.com/comics/{slug}/  atau  .../comics/{slug}/chapter/{n}
        if (preg_match('#^https?://(?:www\.)?asurascans\.com/comics/([a-z0-9\-]+)(?:/.*)?$#i', $input, $m)) {
            return $m[1];
        }
        return null;
    }

    private function httpGet(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept-Language: id-ID,id;q=0.9,en;q=0.8"]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new Exception("Gagal mengambil halaman AsuraScans: $url (HTTP $httpCode). " .
                "Kemungkinan diblokir proteksi anti-bot -- coba lagi nanti atau cek manual di browser.");
        }
        return $response;
    }

    private function diagnosticSnippet(string $html): string
    {
        $text = preg_replace('#<script.*?</script>#is', ' ', $html);
        $text = preg_replace('#<style.*?</style>#is', ' ', (string) $text);
        $text = strip_tags((string) $text);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return trim(mb_substr(trim((string) $text), 0, 300));
    }

    /** Verifikasi ringan bahwa respons memang halaman AsuraScans asli, bukan halaman block anti-bot. */
    private function assertLooksLikeRealPage(string $html, string $url): void
    {
        if (stripos($html, "asura") === false) {
            throw new Exception(
                "Respons dari $url sepertinya BUKAN halaman AsuraScans asli " .
                "(kemungkinan diblokir proteksi anti-bot atau dialihkan ke halaman lain). " .
                "Cuplikan awal respons: \"" . $this->diagnosticSnippet($html) . "\"."
            );
        }
    }

    private function extractMeta(string $html, string $property): ?string
    {
        // SESUAIKAN kalau format meta tag AsuraScans berubah (og:title, og:description, og:image)
        if (preg_match('#<meta[^>]+property=["\']' . preg_quote($property, '#') . '["\'][^>]+content=["\']([^"\']*)["\']#i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }
        return null;
    }

    public function fetchMangaInfo(string $sourceRef): array
    {
        $url = self::BASE . "/comics/" . $sourceRef;
        $html = $this->httpGet($url);
        $this->assertLooksLikeRealPage($html, $url);

        $ogTitle = $this->extractMeta($html, "og:title") ?? "";
        // SESUAIKAN: og:title formatnya "{Judul} | Asura Scans"
        $title = trim(preg_replace('/\s*\|\s*Asura Scans\s*$/i', '', $ogTitle)) ?: $sourceRef;

        $description = $this->extractMeta($html, "og:description") ?? "";

        $cover = $this->extractMeta($html, "og:image") ?? "";
        $cover = preg_replace('/\?.*$/', '', $cover); // buang query string cache-busting kalau ada

        // Author & Artist: link ke halaman browse dgn parameter author=/artist=
        $author = "";
        if (preg_match('#<a[^>]+href=["\'][^"\']*browse\?author=[^"\']*["\'][^>]*>(.*?)</a>#is', $html, $am)) {
            $author = trim(html_entity_decode(strip_tags($am[1]), ENT_QUOTES | ENT_HTML5));
        }
        $artist = "";
        if (preg_match('#<a[^>]+href=["\'][^"\']*browse\?artist=[^"\']*["\'][^>]*>(.*?)</a>#is', $html, $rm)) {
            $artist = trim(html_entity_decode(strip_tags($rm[1]), ENT_QUOTES | ENT_HTML5));
        }

        // Genre: semua link browse?genres=...
        preg_match_all('#<a[^>]+href=["\'][^"\']*browse\?genres=[^"\']*["\'][^>]*>(.*?)</a>#is', $html, $gm);
        $genreNames = array_map(fn($g) => trim(html_entity_decode(strip_tags($g), ENT_QUOTES | ENT_HTML5)), $gm[1] ?? []);
        $genres = implode(", ", array_filter($genreNames));

        // Rating: angka desimal (mis "9.0") yang muncul tepat sebelum label "Rating".
        // SESUAIKAN kalau markup berubah -- fallback: null (tidak fatal).
        $rating = null;
        if (preg_match('#([\d]+(?:\.\d+)?)\s*(?:</[a-z]+>\s*)*(?:<[^>]+>\s*)*Rating#is', $html, $rgm)) {
            $rating = (float) $rgm[1];
        }

        // Chapter TERBARU: scan semua link "/comics/{slug}/chapter/{n}", ambil nomor terbesar.
        $latestNumber = 0.0;
        $latestNumStr = null;
        $pattern = '#/comics/' . preg_quote($sourceRef, '#') . '/chapter/([\d.]+)#i';
        if (preg_match_all($pattern, $html, $cm)) {
            foreach ($cm[1] as $numStr) {
                $num = (float) $numStr;
                if ($num > $latestNumber) {
                    $latestNumber = $num;
                    $latestNumStr = $numStr;
                }
            }
        }

        if (!$latestNumStr) {
            throw new Exception(
                "Tidak menemukan link chapter apapun di halaman manga AsuraScans: $url. " .
                "Kemungkinan struktur halaman berubah. Cuplikan awal respons: \"" .
                $this->diagnosticSnippet($html) . "\""
            );
        }

        // latest_chapter_ref diberi PREFIX slug ("{slug}/{nomor}") -- lihat catatan di
        // komentar class ttg kenapa nomor mentah saja tidak aman dipakai sbg source_chapter_ref.
        $latestRef = $sourceRef . "/" . $latestNumStr;

        return [
            "title" => $title,
            "alternative_title" => "", // AsuraScans tidak menampilkan judul alternatif terpisah di halaman
            "description" => $description,
            "cover_image_url" => $cover,
            "latest_chapter_number" => $latestNumber,
            "author" => $author,
            "artist" => $artist,
            "genres" => $genres,
            "release_year" => "", // tidak ditampilkan eksplisit di halaman manga
            "rating" => $rating,
            "latest_chapter_ref" => $latestRef,
        ];
    }

    public function fetchChapterStep(string $sourceRef, string $chapterRef): array
    {
        // chapterRef berformat "{slug}/{nomor}" (lihat catatan di komentar class).
        // Ambil segmen nomor di akhir; fallback ke chapterRef mentah kalau ternyata
        // sudah berupa angka polos saja (kompatibilitas kalau dipanggil manual).
        if (preg_match('#/([\d.]+)$#', $chapterRef, $nm)) {
            $numStr = $nm[1];
        } elseif (preg_match('#^[\d.]+$#', $chapterRef)) {
            $numStr = $chapterRef;
        } else {
            throw new Exception("Format chapter_ref AsuraScans tidak dikenali: $chapterRef");
        }

        $chapterNumber = (float) $numStr;
        if ($chapterNumber <= 0) {
            throw new Exception("chapter_ref tidak valid: $chapterRef");
        }

        // URL chapter prediktif -- tidak perlu cari href, cukup rakit langsung dari nomor.
        $chapterUrl = self::BASE . "/comics/" . $sourceRef . "/chapter/" . $numStr;
        $html = $this->httpGet($chapterUrl);
        $this->assertLooksLikeRealPage($html, $chapterUrl);

        // Judul chapter (opsional): AsuraScans umumnya tidak punya subjudul selain "Chapter N",
        // jadi default kosong kecuali <title> menyertakan teks tambahan setelah nomor.
        $chapterTitle = "";
        if (preg_match('#<title>(.*?)</title>#is', $html, $ttm)) {
            $titleText = trim(html_entity_decode(strip_tags($ttm[1]), ENT_QUOTES | ENT_HTML5));
            // SESUAIKAN: format <title> = "{Judul Manga} Chapter {N} - Read Online | Asura Scans"
            if (preg_match('/Chapter\s*[\d.]+\s*:\s*(.+?)\s*-\s*Read Online/i', $titleText, $stm)) {
                $chapterTitle = trim($stm[1]);
            }
        }

        // Gambar: domain cdn.asurascans.com/asura-images/chapters/... cukup spesifik & stabil.
        // PENTING: pola non-greedy, berhenti PERSIS di ekstensi file. Halaman ini menyisipkan
        // blob JSON tersembunyi (data prefetch Astro) yang mengulang URL gambar yang sama
        // dgn tanda kutip di-escape sbg "&quot;" (bukan '"' literal) -- kalau regex dibuat
        // greedy dgn exclusion-class (spt versi lama), match jadi "lolos" lewat &quot; dan
        // terus menelan seluruh JSON di sekitarnya jadi satu "URL gambar" sepanjang ribuan
        // karakter, yg kemudian overflow kolom chapter_images.filename (VARCHAR(255)) saat
        // INSERT -- bikin exception & memutus chain sync mundur di tengah jalan. Query string
        // "?v=..." (cache-buster) sengaja TIDAK diambil, tidak dibutuhkan CDN utk load gambar.
        preg_match_all('#https://cdn\.asurascans\.com/asura-images/chapters/[^\s"\'<>]+?\.(?:webp|jpg|jpeg|png)#i', $html, $im);
        $images = array_values(array_unique($im[0]));
        if (empty($images)) {
            throw new Exception(
                "Tidak menemukan gambar chapter di: $chapterUrl. Kemungkinan proteksi anti-bot atau " .
                "domain gambar berubah. Cuplikan awal respons: \"" . $this->diagnosticSnippet($html) . "\""
            );
        }

        // Chapter sebelumnya: TIDAK mengandalkan teks "Prev" (link itu kemungkinan berisi
        // ikon/elemen lain di dalam <a>, jadi rawan gagal match kalau markup berubah).
        // Sebagai gantinya, kumpulkan SEMUA link chapter yang muncul di halaman ini
        // (praktiknya cuma tombol Prev & Next) lalu ambil nomor TERBESAR yang masih
        // LEBIH KECIL dari chapter saat ini -- pendekatan yang sama dipakai di KomikuSource
        // utk membedakan "sebelumnya" dari "selanjutnya". Di chapter PERTAMA, tidak akan
        // ada kandidat yg lebih kecil, jadi hasilnya otomatis null (penanda alami chain selesai).
        $prevRef = null;
        $prevBestNumber = -1;
        if (preg_match_all('#/comics/' . preg_quote($sourceRef, '#') . '/chapter/([\d.]+)#i', $html, $navm)) {
            foreach ($navm[1] as $candidateStr) {
                $num = (float) $candidateStr;
                if ($num < $chapterNumber && $num > $prevBestNumber) {
                    $prevBestNumber = $num;
                    $prevRef = $sourceRef . "/" . $candidateStr;
                }
            }
        }

        return [
            "chapter_number" => $chapterNumber,
            "chapter_title" => $chapterTitle,
            "images" => $images,
            "prev_source_chapter_ref" => $prevRef,
        ];
    }
}