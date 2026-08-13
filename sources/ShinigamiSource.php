<?php
require_once __DIR__ . "/MangaSourceInterface.php";

/**
 * Adapter Shinigami. Ini murni MEMBUNGKUS logika API yang sebelumnya ada di
 * sync_functions.php (shngmApiGet, shngmSaveManga, dst) supaya sesuai kontrak
 * MangaSourceInterface. Perilaku terhadap API Shinigami TIDAK berubah.
 */
class ShinigamiSource implements MangaSourceInterface
{
    private const API_BASE = "https://api.shngm.io/v1";

    public function getKey(): string { return "shngm"; }
    public function getLabel(): string { return "Shinigami"; }

    public function detect(string $input): ?string
    {
        $input = trim($input);
        if ($input === "") return null;

        // Shinigami dipakai sbg FALLBACK: kalau input bukan URL sama sekali,
        // anggap itu manga_id Shinigami polos (perilaku form lama, tidak berubah).
        // Sumber lain (Komiku, dst) mendeteksi lewat pola domain/URL spesifik
        // mereka sendiri dan harus dicek LEBIH DULU di SourceRegistry.
        if (preg_match('#^https?://#i', $input)) return null;

        return $input;
    }

    private function apiGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; PersonalArchiveBot/1.0)");
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new Exception("Gagal memanggil API Shinigami: $url (HTTP $httpCode)");
        }
        $data = json_decode($response, true);
        if (!$data || $data["retcode"] !== 0) {
            throw new Exception("Response API Shinigami tidak valid: $url");
        }
        return $data["data"];
    }

    private function extractTaxonomyNames(array $taxonomy, string $key): string
    {
        if (!isset($taxonomy[$key]) || !is_array($taxonomy[$key])) return "";
        return implode(", ", array_map(fn($t) => $t["name"], $taxonomy[$key]));
    }

    public function fetchMangaInfo(string $sourceRef): array
    {
        $manga = $this->apiGet(self::API_BASE . "/manga/detail/$sourceRef");
        $taxonomy = $manga["taxonomy"] ?? [];

        return [
            "title" => $manga["title"],
            "alternative_title" => $manga["alternative_title"] ?? "",
            "description" => $manga["description"] ?? "",
            "cover_image_url" => !empty($manga["cover_portrait_url"]) ? $manga["cover_portrait_url"] : ($manga["cover_image_url"] ?? ""),
            "latest_chapter_number" => (float) $manga["latest_chapter_number"],
            "author" => $this->extractTaxonomyNames($taxonomy, "Author"),
            "artist" => $this->extractTaxonomyNames($taxonomy, "Artist"),
            "genres" => $this->extractTaxonomyNames($taxonomy, "Genre"),
            "release_year" => $manga["release_year"] ?? "",
            "rating" => $manga["user_rate"] ?? null,
            "latest_chapter_ref" => $manga["latest_chapter_id"],
        ];
    }

    public function fetchChapterStep(string $sourceRef, string $chapterRef): array
    {
        $chapterDetail = $this->apiGet(self::API_BASE . "/chapter/detail/$chapterRef");

        $images = [];
        foreach ($chapterDetail["chapter"]["data"] as $filename) {
            $images[] = $chapterDetail["base_url"] . $chapterDetail["chapter"]["path"] . $filename;
        }

        return [
            "chapter_number" => (float) $chapterDetail["chapter_number"],
            "chapter_title" => $chapterDetail["chapter_title"] ?? "",
            "images" => $images,
            "prev_source_chapter_ref" => $chapterDetail["prev_chapter_id"] ?? null,
        ];
    }
}
