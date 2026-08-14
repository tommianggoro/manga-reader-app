<?php
require_once __DIR__ . "/MangaSourceInterface.php";

/**
 * Adapter CosmicScans. Beda dgn Komiku, situs ini punya API JSON asli
 * (cdncid.csmcscns.id/v1) -- jauh lebih stabil drpd scraping HTML, karena
 * bentuk responsnya kontrak resmi, bukan markup yang bisa berubah kapan saja.
 *
 * Detail unik CosmicScans dibanding sumber lain:
 *  - mangaDetail() sudah mengembalikan SELURUH daftar chapter sekaligus (tidak
 *    perlu jalan-mundur manual spt Shinigami), tapi supaya tetap sesuai
 *    kontrak MangaSourceInterface (step-by-step, ringan per-request), kita
 *    tetap pura-pura jalan mundur satu-satu -- bedanya, readingPage() per
 *    chapter SUDAH menyertakan `otherChapters` (list lengkap juga), jadi
 *    prev_chapter_ref bisa dihitung TANPA request tambahan.
 *  - chapterNum bisa berupa string non-angka murni (mis. "160 Fix"), jadi
 *    kita ambil angka di depannya saja dgn regex.
 */
class CosmicScansSource implements MangaSourceInterface
{
    private const API_BASE = "https://cdncid.csmcscns.id/v1";

    public function getKey(): string { return "cosmicscans"; }
    public function getLabel(): string { return "CosmicScans"; }

    public function detect(string $input): ?string
    {
        $input = trim($input);
        // Terima URL manga detail: https://{subdomain}.cosmicscans.to/series/{slug}/
        if (preg_match('#^https?://[\w.\-]*cosmicscans\.to/series/([a-z0-9\-]+)/?#i', $input, $m)) {
            return $m[1];
        }
        return null;
    }

    private function apiGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new Exception("Gagal memanggil API CosmicScans: $url (HTTP $httpCode)");
        }
        $data = json_decode($response, true);
        if (!$data || empty($data["success"]) || !isset($data["data"])) {
            throw new Exception("Response API CosmicScans tidak valid: $url");
        }
        return $data["data"];
    }

    /** Ambil angka di depan string chapterNum, mis. "160 Fix" -> 160.0, "33.2" -> 33.2, "09" -> 9.0 */
    private function parseChapterNumber(?string $raw): float
    {
        if ($raw && preg_match('/^(\d+(?:\.\d+)?)/', trim($raw), $m)) {
            return (float) $m[1];
        }
        return 0.0;
    }

    /** Ambil src dari string HTML "<img src='URL'>" yang dikirim API sbg representasi 1 halaman. */
    private function extractImageSrc(string $imgHtml): ?string
    {
        if (preg_match('#src=["\']([^"\']+)["\']#i', $imgHtml, $m)) {
            return $m[1];
        }
        return null;
    }

    public function fetchMangaInfo(string $sourceRef): array
    {
        $data = $this->apiGet(self::API_BASE . "/manga/mangaDetail/" . $sourceRef);

        $chapters = $data["chapters"] ?? [];
        if (empty($chapters)) {
            throw new Exception("Manga CosmicScans '$sourceRef' tidak punya chapter apapun.");
        }
        // Diasumsikan terurut TERBARU -> TERLAMA (konsisten dgn contoh response yg diberikan).
        $latest = $chapters[0];

        $description = trim(strip_tags((string) ($data["sinopsis"] ?? "")));
        $genres = isset($data["genre"]) && is_array($data["genre"]) ? implode(", ", $data["genre"]) : "";

        return [
            "title" => $data["title"] ?? $sourceRef,
            "alternative_title" => "",
            "description" => $description,
            "cover_image_url" => $data["cover"] ?? ($data["big_cover"] ?? ""),
            "latest_chapter_number" => $this->parseChapterNumber($latest["chapterNum"] ?? null),
            "author" => $data["author"] ?? "",
            "artist" => $data["artist"] ?? "",
            "genres" => $genres,
            "release_year" => $data["published"] ?? "",
            "rating" => is_numeric($data["rating"] ?? null) ? (float) $data["rating"] : null,
            "latest_chapter_ref" => $latest["slug"] ?? null,
        ];
    }

    public function fetchChapterStep(string $sourceRef, string $chapterRef): array
    {
        $data = $this->apiGet(self::API_BASE . "/manga/readingPage/" . $chapterRef);

        $chapterNumber = $this->parseChapterNumber($data["chapterNum"] ?? null);
        if ($chapterNumber <= 0) {
            throw new Exception("Tidak bisa membaca nomor chapter dari readingPage: $chapterRef");
        }

        $images = [];
        foreach (($data["chapters"] ?? []) as $imgHtml) {
            $src = $this->extractImageSrc((string) $imgHtml);
            if ($src) $images[] = $src;
        }
        if (empty($images)) {
            throw new Exception("Tidak menemukan gambar chapter di readingPage: $chapterRef");
        }

        // prev_chapter_ref: cari posisi chapter ini di otherChapters (list lengkap,
        // terurut terbaru->terlama), lalu ambil item SESUDAHNYA (lebih lama) sbg prev.
        $prevRef = null;
        $otherChapters = $data["otherChapters"] ?? [];
        foreach ($otherChapters as $i => $ch) {
            if (($ch["slug"] ?? null) === $chapterRef) {
                $prevRef = $otherChapters[$i + 1]["slug"] ?? null;
                break;
            }
        }

        return [
            "chapter_number" => $chapterNumber,
            "chapter_title" => $data["chapterTitle"] ?? "",
            "images" => $images,
            "prev_source_chapter_ref" => $prevRef,
        ];
    }
}
