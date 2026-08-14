<?php
require_once __DIR__ . "/MangaSourceInterface.php";

/**
 * Adapter Komiku.org. TIDAK ADA API resmi di sini -- semua data didapat dengan
 * scraping HTML halaman publik. Karena itu, parser di bawah ini ditulis supaya
 * SEMINIMAL MUNGKIN bergantung pada nama class/id CSS spesifik (yang bisa
 * berubah sewaktu-waktu tanpa pemberitahuan), dan lebih mengandalkan pola URL
 * & struktur teks yang stabil (mis. domain gambar img.komiku.org/upload5/...,
 * format anchor "Chapter N", dst).
 *
 * CATATAN PENTING: parser ini ditulis & diverifikasi manual terhadap 2 contoh
 * halaman nyata (manga detail & chapter reader) per Agustus 2026, TAPI belum
 * diuji end-to-end di server produksi. Kalau ada perubahan struktur di Komiku,
 * titik yang paling mungkin perlu disesuaikan sudah diberi komentar "SESUAIKAN".
 */
class KomikuSource implements MangaSourceInterface
{
    private const BASE = "https://komiku.org";

    public function getKey(): string { return "komiku"; }
    public function getLabel(): string { return "Komiku"; }

    public function detect(string $input): ?string
    {
        $input = trim($input);
        // Terima URL manga detail: https://komiku.org/manga/{slug}/
        if (preg_match('#^https?://(?:www\.)?komiku\.org/manga/([a-z0-9\-]+)/?#i', $input, $m)) {
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
            throw new Exception("Gagal mengambil halaman Komiku: $url (HTTP $httpCode). " .
                "Kemungkinan diblokir proteksi anti-bot -- coba lagi nanti atau cek manual di browser.");
        }
        return $response;
    }

    /** Ambil substring HTML di antara dua penanda teks (dipakai utk membatasi area parsing). */
    private function sliceBetween(string $html, string $startMarker, array $endMarkers): string
    {
        $startPos = stripos($html, $startMarker);
        if ($startPos === false) return $html; // fallback: parse seluruh halaman kalau marker tak ketemu

        $endPos = strlen($html);
        foreach ($endMarkers as $marker) {
            $p = stripos($html, $marker, $startPos);
            if ($p !== false && $p < $endPos) $endPos = $p;
        }
        return substr($html, $startPos, $endPos - $startPos);
    }

    /** Ubah href relatif (mis. "/slug-chapter-1/") jadi URL absolut ke komiku.org. */
    private function normalizeHref(string $href): string
    {
        if (preg_match('#^https?://#i', $href)) return $href;
        return self::BASE . "/" . ltrim($href, "/");
    }

    private function extractMeta(string $html, string $property): ?string
    {
        // SESUAIKAN kalau format meta tag Komiku berubah (og:title, og:description, og:image)
        if (preg_match('#<meta[^>]+property=["\']' . preg_quote($property, '#') . '["\'][^>]+content=["\']([^"\']*)["\']#i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }
        return null;
    }

    /** Ambil teks bersih (tag dibuang) dari 300 karakter pertama -- dipakai utk pesan error diagnostik. */
    private function diagnosticSnippet(string $html): string
    {
        $text = preg_replace('#<script.*?</script>#is', ' ', $html);
        $text = preg_replace('#<style.*?</style>#is', ' ', (string) $text);
        $text = strip_tags((string) $text);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return trim(mb_substr(trim((string) $text), 0, 300));
    }

    /**
     * Verifikasi ringan bahwa respons yang diterima memang halaman Komiku asli,
     * bukan halaman challenge/block dari proteksi anti-bot. Kalau ini gagal,
     * masalahnya BUKAN di parser -- HTTP request itu sendiri diblokir/dialihkan.
     */
    private function assertLooksLikeRealPage(string $html, string $url): void
    {
        if (stripos($html, "komiku") === false) {
            throw new Exception(
                "Respons dari $url sepertinya BUKAN halaman Komiku asli " .
                "(kemungkinan diblokir proteksi anti-bot atau dialihkan ke halaman lain). " .
                "Cuplikan awal respons: \"" . $this->diagnosticSnippet($html) . "\". " .
                "Coba jalankan komiku_fetch_test.php di server utk diagnosa lebih lanjut."
            );
        }
    }

    public function fetchMangaInfo(string $sourceRef): array
    {
        $url = self::BASE . "/manga/" . $sourceRef . "/";
        $html = $this->httpGet($url);
        $this->assertLooksLikeRealPage($html, $url);

        $ogTitle = $this->extractMeta($html, "og:title") ?? "";
        $title = preg_replace('/^Komik\s+/i', '', $ogTitle) ?: $sourceRef;

        // Deskripsi lengkap dari <p itemprop="description">, fallback ke og:description (yg suka terpotong "...")
        $description = "";
        if (preg_match('#<p[^>]+itemprop=["\']description["\'][^>]*>(.*?)</p>#is', $html, $dm)) {
            $description = trim(html_entity_decode(strip_tags($dm[1]), ENT_QUOTES | ENT_HTML5));
        } else {
            $description = $this->extractMeta($html, "og:description") ?? "";
        }

        $cover = $this->extractMeta($html, "og:image") ?? "";
        $cover = preg_replace('/\?.*$/', '', $cover);

        // Judul Alternatif: baris tabel info "<td>Judul Alternatif:</td><td>VALUE</td>"
        $altTitle = "";
        if (preg_match('#<td>\s*Judul Alternatif\s*:?\s*</td>\s*<td[^>]*>(.*?)</td>#is', $html, $am)) {
            $altTitle = trim(html_entity_decode(strip_tags($am[1]), ENT_QUOTES | ENT_HTML5));
        }

        // Author: coba dari <span itemprop="author">..<meta itemprop="name" content="..">, fallback ke baris tabel
        $author = "";
        if (preg_match('#itemprop=["\']author["\'][^>]*>.*?itemprop=["\']name["\'][^>]+content=["\']([^"\']*)["\']#is', $html, $aum)) {
            $author = trim($aum[1]);
        }
        if ($author === "" || $author === "-") {
            $author = "";
            if (preg_match('#<td>\s*Author\s*:?\s*</td>\s*<td[^>]*>(.*?)</td>#is', $html, $am2)) {
                $val = trim(html_entity_decode(strip_tags($am2[1]), ENT_QUOTES | ENT_HTML5));
                if ($val !== "-" && $val !== "") $author = $val;
            }
        }

        // Genre: dari <meta itemprop="genre" content="..."> yang berulang -- paling stabil,
        // tidak bergantung pada markup <ul>/<li>/<a> yang bisa berubah.
        preg_match_all('#<meta[^>]+itemprop=["\']genre["\'][^>]+content=["\']([^"\']*)["\']#i', $html, $gm);
        $genres = implode(", ", $gm[1] ?? []);

        $releaseYear = "";

        // Chapter TERBARU: ambil dari link eksplisit "Terbaru: Chapter N" (div.new1 di dekat
        // tombol Bookmark), jauh lebih stabil drpd parsing seluruh tabel Daftar Chapter.
        $latestRef = null;
        $latestNumber = 0.0;
        if (preg_match(
            '#<a[^>]+href=["\']((?:https?://[^"\']*komiku\.org)?/[^"\']+)["\'][^>]*>\s*<span[^>]*>\s*Terbaru:?\s*</span>\s*<span[^>]*>\s*Chapter\s*([\d.]+)#is',
            $html, $tm
        )) {
            $latestHref = $this->normalizeHref($tm[1]);
            $latestRef = trim((string) parse_url($latestHref, PHP_URL_PATH), "/");
            $latestNumber = (float) $tm[2];
        }

        // Fallback kalau link "Terbaru:" tidak ketemu (mis. struktur berubah): ambil baris
        // PERTAMA di tabel id="daftarChapter" (list terurut terbaru -> terlama).
        if (!$latestRef) {
            $chapterListBlock = $this->sliceBetween($html, 'id="daftarChapter"', ['id="Sinopsis"', 'Komik Serupa', '</table>']);
            if (preg_match('#<a[^>]+href=["\']((?:https?://[^"\']*komiku\.org)?/[^"\']+)["\'][^>]*>.*?<b>\s*Chapter\s*([\d.]+)\s*</b>#is', $chapterListBlock, $cm)) {
                $latestHref = $this->normalizeHref($cm[1]);
                $latestRef = trim((string) parse_url($latestHref, PHP_URL_PATH), "/");
                $latestNumber = (float) $cm[2];
            }
        }

        if (!$latestRef) {
            throw new Exception(
                "Tidak menemukan chapter terbaru di halaman manga Komiku: $url. " .
                "Kemungkinan struktur halaman berubah. Cuplikan awal respons: \"" .
                $this->diagnosticSnippet($html) . "\""
            );
        }

        return [
            "title" => $title,
            "alternative_title" => $altTitle,
            "description" => $description,
            "cover_image_url" => $cover,
            "latest_chapter_number" => $latestNumber,
            "author" => $author,
            "artist" => "", // Komiku umumnya tidak memisahkan author/artist
            "genres" => $genres,
            "release_year" => $releaseYear,
            "rating" => null, // Komiku pakai rating usia (mis. "13+"), bukan skor numerik spt Shinigami
            "latest_chapter_ref" => $latestRef,
        ];
    }

    public function fetchChapterStep(string $sourceRef, string $chapterRef): array
    {
        $chapterUrl = self::BASE . "/" . ltrim($chapterRef, "/") . "/";
        $html = $this->httpGet($chapterUrl);
        $this->assertLooksLikeRealPage($html, $chapterUrl);

        // Nomor chapter: ambil dari heading halaman (H1), BUKAN dari slug URL,
        // karena slug kadang punya suffix (mis. "-2") yang tidak mencerminkan nomor asli.
        $chapterTitle = "";
        $chapterNumber = 0.0;
        if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $hm)) {
            $heading = trim(html_entity_decode(strip_tags($hm[1]), ENT_QUOTES | ENT_HTML5));
            if (preg_match('/Chapter\s*([\d.]+)/i', $heading, $nm)) {
                $chapterNumber = (float) $nm[1];
            }
            $chapterTitle = $heading;
        }
        // Fallback kalau H1 tidak ketemu/tidak mengandung nomor: coba dari <title> halaman
        if ($chapterNumber <= 0 && preg_match('#<title>(.*?)</title>#is', $html, $ttm)) {
            $titleText = trim(html_entity_decode(strip_tags($ttm[1]), ENT_QUOTES | ENT_HTML5));
            if (preg_match('/Chapter\s*([\d.]+)/i', $titleText, $nm2)) {
                $chapterNumber = (float) $nm2[1];
                if ($chapterTitle === "") $chapterTitle = $titleText;
            }
        }
        if ($chapterNumber <= 0) {
            throw new Exception(
                "Tidak bisa membaca nomor chapter dari: $chapterUrl. Cuplikan awal respons: \"" .
                $this->diagnosticSnippet($html) . "\""
            );
        }

        // Gambar: domain img.komiku.org/upload5/... sangat spesifik & stabil,
        // jadi cukup aman diambil langsung pakai regex tanpa bergantung pada DOM.
        preg_match_all('#https://img\.komiku\.org/upload5/[^\s"\'\)<]+\.(?:webp|jpg|jpeg|png)#i', $html, $im);
        $images = array_values(array_unique($im[0]));
        if (empty($images)) {
            throw new Exception(
                "Tidak menemukan gambar chapter di: $chapterUrl. Kemungkinan proteksi anti-bot atau " .
                "domain gambar berubah. Cuplikan awal respons: \"" . $this->diagnosticSnippet($html) . "\""
            );
        }

        // Chapter sebelumnya: dibatasi ke area setelah galeri gambar & sebelum "Komentar",
        // supaya tidak kebawa link manga lain dari sidebar "Peringkat Mingguan". Toleran
        // thd urutan atribut (href/title bisa saling tukar posisi) & kutip tunggal/ganda.
        $navBlock = $this->sliceBetween($html, "Baca Online", ["Komentar", "Peringkat Mingguan"]);
        preg_match_all(
            '#<a\s+(?=[^>]*href=["\']((?:https?://[^"\']*komiku\.org)?/[^"\']+)["\'])(?=[^>]*title=["\'][^"\']*Chapter\s*([\d.]+))[^>]*>#is',
            $navBlock,
            $navMatches,
            PREG_SET_ORDER
        );

        $prevRef = null;
        $prevBestNumber = -1;
        foreach ($navMatches as $nm) {
            $candidateHref = $this->normalizeHref($nm[1]);
            $candidateSlug = trim((string) parse_url($candidateHref, PHP_URL_PATH), "/");
            if ($candidateSlug === $chapterRef) continue; // bukan link ke chapter ini sendiri
            $candidateNumber = (float) $nm[2];
            // Ambil kandidat dgn nomor TERBESAR yang masih LEBIH KECIL dari chapter saat ini
            // (utk membedakan tombol "sebelumnya" dari "selanjutnya" kalau keduanya ada di halaman).
            if ($candidateNumber < $chapterNumber && $candidateNumber > $prevBestNumber) {
                $prevBestNumber = $candidateNumber;
                $prevRef = $candidateSlug;
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
