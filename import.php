<?php
require_once "config.php";
requireAuth();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["backup_file"])) {
    try {
        $file = $_FILES["backup_file"];
        if ($file["error"] !== UPLOAD_ERR_OK) {
            throw new Exception("Gagal mengunggah berkas. Kode error: " . $file["error"]);
        }

        $jsonContent = file_get_contents($file["tmp_name"]);
        $data = json_decode($jsonContent, true);

        if (!$data || !isset($data["mangas"]) || !is_array($data["mangas"])) {
            throw new Exception("Format berkas JSON backup tidak valid.");
        }

        $userId = currentUserId();
        $isLegacyFormat = ($data["version"] ?? "1.0") === "1.0";

        $pdo->beginTransaction();

        $importedMangaCount = 0;
        $importedChapterCount = 0;

        $stmtManga = $pdo->prepare("
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

        $stmtChapter = $pdo->prepare("
            INSERT INTO chapters (chapter_id, manga_id, chapter_number, chapter_title, base_url, image_path, prev_chapter_id, prev_verified)
            VALUES (:chapter_id, :manga_id, :chapter_number, :chapter_title, :base_url, :image_path, :prev_chapter_id, :prev_verified)
            ON DUPLICATE KEY UPDATE
                chapter_title = VALUES(chapter_title),
                prev_chapter_id = VALUES(prev_chapter_id),
                prev_verified = VALUES(prev_verified)
        ");

        $stmtImg = $pdo->prepare("
            INSERT INTO chapter_images (chapter_id, page_number, filename)
            VALUES (:chapter_id, :page_number, :filename)
            ON DUPLICATE KEY UPDATE filename = VALUES(filename)
        ");

        $stmtUserState = $pdo->prepare("
            INSERT INTO user_manga_state (user_id, manga_id, is_favorite, last_read_chapter_id, last_read_chapter_number, last_read_at)
            VALUES (:uid, :mid, :fav, :cid, :cnum, :at)
            ON DUPLICATE KEY UPDATE
                is_favorite = VALUES(is_favorite),
                last_read_chapter_id = VALUES(last_read_chapter_id),
                last_read_chapter_number = VALUES(last_read_chapter_number),
                last_read_at = VALUES(last_read_at)
        ");

        foreach ($data["mangas"] as $m) {
            $stmtManga->execute([
                ":manga_id" => $m["manga_id"],
                ":title" => $m["title"],
                ":alt_title" => $m["alternative_title"] ?? "",
                ":description" => $m["description"] ?? "",
                ":cover" => $m["cover_image_url"] ?? "",
                ":latest_ch" => $m["latest_chapter_number"] ?? 0,
                ":author" => $m["author"] ?? "",
                ":artist" => $m["artist"] ?? "",
                ":genres" => $m["genres"] ?? "",
                ":release_year" => $m["release_year"] ?? "",
                ":rating" => $m["rating"] ?? null,
            ]);
            $importedMangaCount++;

            // Backward compatibility: backup lama (v1.0) menyimpan favorite/last_read
            // di dalam tiap item manga. Pulihkan sebagai status milik user yang mengimpor sekarang.
            if ($isLegacyFormat && (!empty($m["is_favorite"]) || !empty($m["last_read_chapter_id"]))) {
                $stmtUserState->execute([
                    ":uid" => $userId,
                    ":mid" => $m["manga_id"],
                    ":fav" => !empty($m["is_favorite"]) ? 1 : 0,
                    ":cid" => $m["last_read_chapter_id"] ?? null,
                    ":cnum" => $m["last_read_chapter_number"] ?? null,
                    ":at" => $m["last_read_at"] ?? null,
                ]);
            }

            if (isset($m["chapters"]) && is_array($m["chapters"])) {
                foreach ($m["chapters"] as $ch) {
                    $stmtChapter->execute([
                        ":chapter_id" => $ch["chapter_id"],
                        ":manga_id" => $m["manga_id"],
                        ":chapter_number" => $ch["chapter_number"],
                        ":chapter_title" => $ch["chapter_title"] ?? "",
                        ":base_url" => $ch["base_url"],
                        ":image_path" => $ch["image_path"],
                        ":prev_chapter_id" => $ch["prev_chapter_id"] ?? null,
                        ":prev_verified" => !empty($ch["prev_verified"]) ? 1 : 0,
                    ]);
                    $importedChapterCount++;

                    if (isset($ch["images"]) && is_array($ch["images"])) {
                        foreach ($ch["images"] as $img) {
                            $stmtImg->execute([
                                ":chapter_id" => $ch["chapter_id"],
                                ":page_number" => $img["page_number"],
                                ":filename" => $img["filename"],
                            ]);
                        }
                    }
                }
            }
        }

        // Format baru (v2.0): status user tersimpan terpisah di "user_state"
        if (!$isLegacyFormat && isset($data["user_state"]) && is_array($data["user_state"])) {
            $favorites = $data["user_state"]["favorites"] ?? [];
            $lastRead = $data["user_state"]["last_read"] ?? [];
            $readingProgress = $data["user_state"]["reading_progress"] ?? [];

            $mangaIdsWithState = array_unique(array_merge($favorites, array_keys($lastRead)));
            foreach ($mangaIdsWithState as $mid) {
                $lr = $lastRead[$mid] ?? null;
                $stmtUserState->execute([
                    ":uid" => $userId,
                    ":mid" => $mid,
                    ":fav" => in_array($mid, $favorites, true) ? 1 : 0,
                    ":cid" => $lr["chapter_id"] ?? null,
                    ":cnum" => $lr["chapter_number"] ?? null,
                    ":at" => $lr["last_read_at"] ?? null,
                ]);
            }

            $stmtProgress = $pdo->prepare("
                INSERT INTO reading_progress (user_id, chapter_id, scroll_position, single_page_index)
                VALUES (:uid, :cid, :scroll, :page)
                ON DUPLICATE KEY UPDATE
                    scroll_position = VALUES(scroll_position),
                    single_page_index = VALUES(single_page_index)
            ");
            foreach ($readingProgress as $chapterId => $prog) {
                $stmtProgress->execute([
                    ":uid" => $userId,
                    ":cid" => $chapterId,
                    ":scroll" => $prog["scroll_position"] ?? null,
                    ":page" => $prog["single_page_index"] ?? null,
                ]);
            }
        }

        $pdo->commit();

        header("Location: index.php?import_success=1&mangas=" . $importedMangaCount);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = urlencode($e->getMessage());
        header("Location: index.php?import_error=" . $error);
        exit;
    }
}
header("Location: index.php");
exit;