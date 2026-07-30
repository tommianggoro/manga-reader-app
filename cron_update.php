<?php
/**
 * Headless Cron Script untuk memeriksa dan mengunduh chapter terbaru
 * untuk seluruh manga yang ada di database.
 * 
 * Cara menjalankan:
 * 1. CLI Terminal / Crontab Server:
 *    php cron_update.php
 * 2. Web Request / GitHub Actions / Webhook:
 *    http://yoursite.com/cron_update.php?key=manga_reader_secret_key_123
 */

require_once "config.php";

const API_BASE = "https://api.shngm.io/v1";

// Keamanan: Cek apakah dijalankan via CLI atau via Web dengan Secret Key
$isCli = (php_sapi_name() === 'cli');
$keyInput = $_GET['key'] ?? null;

if (!$isCli && $keyInput !== CRON_SECRET_KEY) {
    header("HTTP/1.1 403 Forbidden");
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "error" => "Akses ditolak: Key tidak valid."]);
    exit;
}

if (!$isCli) {
    header("Content-Type: application/json; charset=utf-8");
}

function cronLog($msg) {
    if (php_sapi_name() === 'cli') {
        echo $msg . "\n";
    }
}

function apiGetCron($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; PersonalArchiveBot/1.0)");
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        throw new Exception("Gagal memanggil API: $url (HTTP $httpCode)");
    }
    $data = json_decode($response, true);
    if (!$data || $data["retcode"] !== 0) {
        throw new Exception("Response API tidak valid: $url");
    }
    return $data["data"];
}

function extractTaxonomyNamesCron($taxonomy, $key) {
    if (!isset($taxonomy[$key]) || !is_array($taxonomy[$key])) return "";
    return implode(", ", array_map(fn($t) => $t["name"], $taxonomy[$key]));
}

function saveMangaCron($pdo, $manga) {
    $taxonomy = $manga["taxonomy"] ?? [];
    $author = extractTaxonomyNamesCron($taxonomy, "Author");
    $artist = extractTaxonomyNamesCron($taxonomy, "Artist");
    $genres = extractTaxonomyNamesCron($taxonomy, "Genre");

    $stmt = $pdo->prepare("
        INSERT INTO mangas (manga_id, title, alternative_title, description, cover_image_url, latest_chapter_number, author, artist, genres, release_year, rating)
        VALUES (:manga_id, :title, :alt_title, :description, :cover, :latest_ch, :author, :artist, :genres, :release_year, :rating)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            alternative_title = VALUES(alternative_title),
            description = VALUES(description),
            cover_image_url = VALUES(cover_image_url),
            latest_chapter_number = VALUES(latest_chapter_number),
            author = VALUES(author),
            artist = VALUES(artist),
            genres = VALUES(genres),
            release_year = VALUES(release_year),
            rating = VALUES(rating)
    ");
    $stmt->execute([
        ":manga_id" => $manga["manga_id"],
        ":title" => $manga["title"],
        ":alt_title" => $manga["alternative_title"] ?? "",
        ":description" => $manga["description"] ?? "",
        ":cover" => $manga["cover_portrait_url"] ?? $manga["cover_image_url"],
        ":latest_ch" => $manga["latest_chapter_number"],
        ":author" => $author,
        ":artist" => $artist,
        ":genres" => $genres,
        ":release_year" => $manga["release_year"] ?? "",
        ":rating" => $manga["user_rate"] ?? null,
    ]);
}

function saveChapterCron($pdo, $chapterDetail, $mangaId) {
    $stmt = $pdo->prepare("
        INSERT INTO chapters (chapter_id, manga_id, chapter_number, chapter_title, base_url, image_path, prev_chapter_id, prev_verified)
        VALUES (:chapter_id, :manga_id, :chapter_number, :chapter_title, :base_url, :image_path, :prev_chapter_id, 1)
        ON DUPLICATE KEY UPDATE
            chapter_title = VALUES(chapter_title),
            prev_chapter_id = VALUES(prev_chapter_id),
            prev_verified = 1,
            updated_at = NOW()
    ");
    $stmt->execute([
        ":chapter_id" => $chapterDetail["chapter_id"],
        ":manga_id" => $mangaId,
        ":chapter_number" => $chapterDetail["chapter_number"],
        ":chapter_title" => $chapterDetail["chapter_title"] ?? "",
        ":base_url" => $chapterDetail["base_url"],
        ":image_path" => $chapterDetail["chapter"]["path"],
        ":prev_chapter_id" => $chapterDetail["prev_chapter_id"] ?? null,
    ]);

    $stmtImg = $pdo->prepare("
        INSERT INTO chapter_images (chapter_id, page_number, filename)
        VALUES (:chapter_id, :page_number, :filename)
        ON DUPLICATE KEY UPDATE filename = VALUES(filename)
    ");
    foreach ($chapterDetail["chapter"]["data"] as $i => $filename) {
        $stmtImg->execute([
            ":chapter_id" => $chapterDetail["chapter_id"],
            ":page_number" => $i + 1,
            ":filename" => $filename,
        ]);
    }
}

// ==== PROSES MAIN CRON ====
$startTime = microtime(true);
cronLog("=== MEMULAI SYNC MANGA CRONJOB ===");

$mangas = $pdo->query("SELECT manga_id, title FROM mangas ORDER BY title ASC")->fetchAll();
$totalManga = count($mangas);
$totalNewChapters = 0;
$results = [];

foreach ($mangas as $index => $m) {
    $mangaId = $m["manga_id"];
    $title = $m["title"];
    cronLog("\n[" . ($index + 1) . "/$totalManga] Cek update: $title ($mangaId)");

    try {
        $mangaDetail = apiGetCron(API_BASE . "/manga/detail/$mangaId");
        saveMangaCron($pdo, $mangaDetail);

        $currentChapterId = $mangaDetail["latest_chapter_id"];
        $newChaptersManga = 0;

        while ($currentChapterId) {
            // Cek apakah chapter sudah tersimpan dan prev_verified
            $stmt = $pdo->prepare("SELECT chapter_number, prev_chapter_id, prev_verified FROM chapters WHERE chapter_id = :id");
            $stmt->execute([":id" => $currentChapterId]);
            $existing = $stmt->fetch();

            if ($existing && (int) $existing["prev_verified"] === 1) {
                // Sudah ada dan terverifikasi - stop iterasi mundur untuk manga ini
                break;
            }

            // Ambil detail chapter baru
            $chapterDetail = apiGetCron(API_BASE . "/chapter/detail/$currentChapterId");
            saveChapterCron($pdo, $chapterDetail, $mangaId);
            $newChaptersManga++;
            $totalNewChapters++;

            cronLog("  ✓ Chapter {$chapterDetail['chapter_number']} disimpan");
            $currentChapterId = $chapterDetail["prev_chapter_id"] ?? null;
            usleep(300000); // Tahan 300ms antar request agar ramah API
        }

        $results[] = [
            "manga_id" => $mangaId,
            "title" => $title,
            "new_chapters" => $newChaptersManga,
            "status" => "success"
        ];

        cronLog("  -> $title selesai: $newChaptersManga chapter baru.");
    } catch (Exception $e) {
        cronLog("  ❌ ERROR: " . $e->getMessage());
        $results[] = [
            "manga_id" => $mangaId,
            "title" => $title,
            "error" => $e->getMessage(),
            "status" => "error"
        ];
    }
}

$executionTime = round(microtime(true) - $startTime, 2);
cronLog("\n=== CRONJOB SELESAI ({$executionTime}s) ===");
cronLog("Total Manga: $totalManga | Total Chapter Baru: $totalNewChapters");

if (!$isCli) {
    echo json_encode([
        "success" => true,
        "execution_time_seconds" => $executionTime,
        "total_manga" => $totalManga,
        "total_new_chapters" => $totalNewChapters,
        "results" => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
