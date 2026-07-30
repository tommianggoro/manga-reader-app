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

        $pdo->beginTransaction();

        $importedMangaCount = 0;
        $importedChapterCount = 0;

        $stmtManga = $pdo->prepare("
            INSERT INTO mangas (manga_id, title, alternative_title, description, cover_image_url, latest_chapter_number, author, artist, genres, release_year, rating, is_favorite, last_read_chapter_id, last_read_chapter_number, last_read_at)
            VALUES (:manga_id, :title, :alt_title, :description, :cover, :latest_ch, :author, :artist, :genres, :release_year, :rating, :is_favorite, :last_read_id, :last_read_num, :last_read_at)
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
                rating = VALUES(rating),
                is_favorite = VALUES(is_favorite),
                last_read_chapter_id = VALUES(last_read_chapter_id),
                last_read_chapter_number = VALUES(last_read_chapter_number),
                last_read_at = VALUES(last_read_at)
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
                ":is_favorite" => !empty($m["is_favorite"]) ? 1 : 0,
                ":last_read_id" => $m["last_read_chapter_id"] ?? null,
                ":last_read_num" => $m["last_read_chapter_number"] ?? null,
                ":last_read_at" => $m["last_read_at"] ?? null,
            ]);
            $importedMangaCount++;

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
