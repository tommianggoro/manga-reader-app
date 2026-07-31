<?php
/**
 * Kumpulan fungsi inti untuk sinkronisasi data manga & chapter dari Shinigami API.
 * Dipakai bersama oleh:
 *  - crawler.php   -> dipanggil per-chapter dari browser (tombol per-manga & batch update)
 *  - cron_update.php -> auto-sync semua manga sekaligus (GitHub Actions / cron server)
 *
 * Dengan satu sumber logika ini, tombol "Update Batch" di UI dan cron GitHub Action
 * selalu memakai cara sync yang sama persis.
 */

const SHNGM_API_BASE = "https://api.shngm.io/v1";

function shngmApiGet($url) {
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

function shngmExtractTaxonomyNames($taxonomy, $key) {
    if (!isset($taxonomy[$key]) || !is_array($taxonomy[$key])) return "";
    return implode(", ", array_map(fn($t) => $t["name"], $taxonomy[$key]));
}

function shngmSaveManga($pdo, $manga) {
    $taxonomy = $manga["taxonomy"] ?? [];
    $author = shngmExtractTaxonomyNames($taxonomy, "Author");
    $artist = shngmExtractTaxonomyNames($taxonomy, "Artist");
    $genres = shngmExtractTaxonomyNames($taxonomy, "Genre");

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
        ":cover" => !empty($manga["cover_portrait_url"]) ? $manga["cover_portrait_url"] : ($manga["cover_image_url"] ?? ""),
        ":latest_ch" => $manga["latest_chapter_number"],
        ":author" => $author,
        ":artist" => $artist,
        ":genres" => $genres,
        ":release_year" => $manga["release_year"] ?? "",
        ":rating" => $manga["user_rate"] ?? null,
    ]);
}

function shngmGetExistingChapter($pdo, $chapterId) {
    $stmt = $pdo->prepare("SELECT chapter_number, prev_chapter_id, prev_verified FROM chapters WHERE chapter_id = :id");
    $stmt->execute([":id" => $chapterId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function shngmPatchPrevChapterId($pdo, $chapterId, $prevChapterId) {
    $stmt = $pdo->prepare("UPDATE chapters SET prev_chapter_id = :prev, prev_verified = 1, updated_at = NOW() WHERE chapter_id = :id");
    $stmt->execute([":prev" => $prevChapterId, ":id" => $chapterId]);
}

function shngmSaveChapter($pdo, $chapterDetail, $mangaId) {
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

/**
 * Sinkronisasi 1 chapter saja. Dipakai oleh crawler.php (action=step, dipanggil
 * satu-per-satu dari browser) DAN oleh shngmSyncMangaFull() di bawah (dipakai cron).
 */
function shngmSyncChapterStep($pdo, $mangaId, $chapterId) {
    $existing = shngmGetExistingChapter($pdo, $chapterId);

    if ($existing && (int) $existing["prev_verified"] === 1) {
        return [
            "skipped" => true,
            "repaired" => false,
            "chapter_number" => $existing["chapter_number"],
            "prev_chapter_id" => $existing["prev_chapter_id"],
        ];
    }

    if ($existing && (int) $existing["prev_verified"] === 0) {
        $chapterDetail = shngmApiGet(SHNGM_API_BASE . "/chapter/detail/$chapterId");
        $prevId = $chapterDetail["prev_chapter_id"] ?? null;
        shngmPatchPrevChapterId($pdo, $chapterId, $prevId);

        return [
            "skipped" => true,
            "repaired" => true,
            "chapter_number" => $existing["chapter_number"],
            "prev_chapter_id" => $prevId,
        ];
    }

    $chapterDetail = shngmApiGet(SHNGM_API_BASE . "/chapter/detail/$chapterId");
    shngmSaveChapter($pdo, $chapterDetail, $mangaId);

    return [
        "skipped" => false,
        "repaired" => false,
        "chapter_number" => $chapterDetail["chapter_number"],
        "prev_chapter_id" => $chapterDetail["prev_chapter_id"] ?? null,
    ];
}

/**
 * Sinkronisasi 1 manga secara PENUH dalam satu pemanggilan (ambil info manga,
 * lalu mundur dari chapter terbaru sampai ketemu chapter yang sudah tersimpan
 * & terverifikasi). Dipakai oleh cron_update.php.
 *
 * $onChapterSaved (opsional): callback dipanggil setiap 1 chapter baru tersimpan,
 * berguna untuk logging.
 */
function shngmSyncMangaFull($pdo, $mangaId, $onChapterSaved = null) {
    $mangaDetail = shngmApiGet(SHNGM_API_BASE . "/manga/detail/$mangaId");
    shngmSaveManga($pdo, $mangaDetail);

    $currentChapterId = $mangaDetail["latest_chapter_id"];
    $newChapters = 0;
    $skippedChapters = 0;

    while ($currentChapterId) {
        $existing = shngmGetExistingChapter($pdo, $currentChapterId);
        if ($existing && (int) $existing["prev_verified"] === 1) {
            break; // sudah pernah sync sampai sini, berhenti
        }

        $result = shngmSyncChapterStep($pdo, $mangaId, $currentChapterId);

        if ($result["skipped"]) {
            $skippedChapters++;
        } else {
            $newChapters++;
            if ($onChapterSaved) {
                $onChapterSaved($result["chapter_number"]);
            }
        }

        $currentChapterId = $result["prev_chapter_id"];
        usleep(300000); // jeda 300ms antar request API, ramah ke server Shinigami
    }

    return [
        "manga_id" => $mangaId,
        "title" => $mangaDetail["title"],
        "new_chapters" => $newChapters,
        "skipped_chapters" => $skippedChapters,
    ];
}