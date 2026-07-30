<?php
/**
 * API endpoint untuk crawl data manga & chapter dari Shinigami, dipanggil
 * SATU CHAPTER PER REQUEST oleh JavaScript di crawl.php (supaya tidak
 * kena batas max_execution_time dan progressnya bisa ditampilkan).
 *
 * action=init  -> ambil & simpan info manga, kembalikan chapter terbaru
 * action=step  -> ambil & simpan 1 chapter, kembalikan prev_chapter_id
 */

require_once "config.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

const API_BASE = "https://api.shngm.io/v1";

function apiGet($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; PersonalArchiveBot/1.0)");
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
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

function extractTaxonomyNames($taxonomy, $key) {
    if (!isset($taxonomy[$key]) || !is_array($taxonomy[$key])) return "";
    return implode(", ", array_map(fn($t) => $t["name"], $taxonomy[$key]));
}

function saveManga($pdo, $manga) {
    $taxonomy = $manga["taxonomy"] ?? [];
    $author = extractTaxonomyNames($taxonomy, "Author");
    $artist = extractTaxonomyNames($taxonomy, "Artist");
    $genres = extractTaxonomyNames($taxonomy, "Genre");

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

function getExistingChapter($pdo, $chapterId) {
    $stmt = $pdo->prepare("SELECT chapter_number, prev_chapter_id, prev_verified FROM chapters WHERE chapter_id = :id");
    $stmt->execute([":id" => $chapterId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function patchPrevChapterId($pdo, $chapterId, $prevChapterId) {
    $stmt = $pdo->prepare("UPDATE chapters SET prev_chapter_id = :prev, prev_verified = 1 WHERE chapter_id = :id");
    $stmt->execute([":prev" => $prevChapterId, ":id" => $chapterId]);
}

function saveChapter($pdo, $chapterDetail, $mangaId) {
    $stmt = $pdo->prepare("
        INSERT INTO chapters (chapter_id, manga_id, chapter_number, chapter_title, base_url, image_path, prev_chapter_id, prev_verified)
        VALUES (:chapter_id, :manga_id, :chapter_number, :chapter_title, :base_url, :image_path, :prev_chapter_id, 1)
        ON DUPLICATE KEY UPDATE chapter_title = VALUES(chapter_title), prev_chapter_id = VALUES(prev_chapter_id), prev_verified = 1
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

// ==== MAIN ====

$action = $_GET["action"] ?? null;

try {
    if ($action === "init") {
        $mangaId = $_GET["manga_id"] ?? null;
        if (!$mangaId) throw new Exception("manga_id wajib diisi");

        $manga = apiGet(API_BASE . "/manga/detail/$mangaId");
        saveManga($pdo, $manga);

        echo json_encode([
            "success" => true,
            "title" => $manga["title"],
            "latest_chapter_id" => $manga["latest_chapter_id"],
            "latest_chapter_number" => $manga["latest_chapter_number"],
        ]);
        exit;
    }

    if ($action === "step") {
        $mangaId = $_GET["manga_id"] ?? null;
        $chapterId = $_GET["chapter_id"] ?? null;
        if (!$mangaId || !$chapterId) throw new Exception("manga_id dan chapter_id wajib diisi");

        $existing = getExistingChapter($pdo, $chapterId);

        if ($existing && (int) $existing["prev_verified"] === 1) {
            // Sudah pernah tersimpan DAN sudah tahu link ke chapter sebelumnya - skip tanpa panggil API
            echo json_encode([
                "success" => true,
                "done" => false,
                "skipped" => true,
                "repaired" => false,
                "chapter_number" => $existing["chapter_number"],
                "prev_chapter_id" => $existing["prev_chapter_id"],
            ]);
            exit;
        }

        if ($existing && (int) $existing["prev_verified"] === 0) {
            // Data lama (sebelum fitur ini ada) - tambal link-nya lewat API, tanpa insert ulang gambar
            $chapterDetail = apiGet(API_BASE . "/chapter/detail/$chapterId");
            $prevId = $chapterDetail["prev_chapter_id"] ?? null;
            patchPrevChapterId($pdo, $chapterId, $prevId);

            echo json_encode([
                "success" => true,
                "done" => false,
                "skipped" => true,
                "repaired" => true,
                "chapter_number" => $existing["chapter_number"],
                "prev_chapter_id" => $prevId,
            ]);
            exit;
        }

        // Belum ada sama sekali - ambil baru dari API
        $chapterDetail = apiGet(API_BASE . "/chapter/detail/$chapterId");
        saveChapter($pdo, $chapterDetail, $mangaId);

        echo json_encode([
            "success" => true,
            "done" => false,
            "skipped" => false,
            "repaired" => false,
            "chapter_number" => $chapterDetail["chapter_number"],
            "prev_chapter_id" => $chapterDetail["prev_chapter_id"],
        ]);
        exit;
    }

    throw new Exception("action tidak dikenali");
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
