<?php
/**
 * Kumpulan fungsi inti sinkronisasi manga & chapter -- SEKARANG GENERIC, tidak
 * tahu apa-apa soal Shinigami/Komiku/sumber manapun secara spesifik. Semua
 * detail spesifik-sumber didelegasikan ke adapter di folder sources/ lewat
 * MangaSourceInterface.
 *
 * Dipakai bersama oleh:
 *  - crawler.php     -> dipanggil per-chapter dari browser (tombol per-manga & batch update)
 *  - cron_update.php -> auto-sync semua manga (semua source yg ter-bind) sekaligus
 */

require_once __DIR__ . "/sources/SourceRegistry.php";

// ============================================================================
// MANGA & SOURCE BINDING
// ============================================================================

/** Cari manga_id internal yang sudah terhubung ke (source, source_ref) tsb, kalau ada. */
function findMangaBySource(PDO $pdo, string $source, string $sourceRef): ?string
{
    $stmt = $pdo->prepare("SELECT manga_id FROM manga_sources WHERE source = :src AND source_ref = :ref");
    $stmt->execute([":src" => $source, ":ref" => $sourceRef]);
    $row = $stmt->fetch();
    return $row ? $row["manga_id"] : null;
}

/** Cari manga existing dgn judul mirip (dipakai utk konfirmasi linking manual saat tambah manga). */
function findSimilarMangas(PDO $pdo, string $title, int $limit = 8): array
{
    $stmt = $pdo->prepare("SELECT manga_id, title, cover_image_url FROM mangas WHERE title LIKE :q ORDER BY title ASC LIMIT $limit");
    $stmt->execute([":q" => "%" . $title . "%"]);
    return $stmt->fetchAll();
}

/** Generate manga_id internal baru yang unik, berbasis judul (bukan ID sumber manapun). */
function generateInternalMangaId(PDO $pdo, string $title): string
{
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
    $base = $base !== '' ? substr($base, 0, 150) : 'manga';
    $candidate = $base;
    $stmt = $pdo->prepare("SELECT 1 FROM mangas WHERE manga_id = :id");
    $i = 1;
    while (true) {
        $stmt->execute([":id" => $candidate]);
        if (!$stmt->fetch()) return $candidate;
        $i++;
        $candidate = $base . "-" . $i;
    }
}

function saveMangaCore(PDO $pdo, string $mangaIdInternal, array $detail): void
{
    $stmt = $pdo->prepare("
        INSERT INTO mangas (manga_id, title, alternative_title, description, cover_image_url, latest_chapter_number, author, artist, genres, release_year, rating)
        VALUES (:manga_id, :title, :alt_title, :description, :cover, :latest_ch, :author, :artist, :genres, :release_year, :rating)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            alternative_title = VALUES(alternative_title),
            description = VALUES(description),
            cover_image_url = VALUES(cover_image_url),
            latest_chapter_number = GREATEST(latest_chapter_number, VALUES(latest_chapter_number)),
            author = IF(VALUES(author) = '', author, VALUES(author)),
            artist = IF(VALUES(artist) = '', artist, VALUES(artist)),
            genres = IF(VALUES(genres) = '', genres, VALUES(genres)),
            release_year = IF(VALUES(release_year) = '', release_year, VALUES(release_year)),
            rating = COALESCE(VALUES(rating), rating)
    ");
    $stmt->execute([
        ":manga_id" => $mangaIdInternal,
        ":title" => $detail["title"],
        ":alt_title" => $detail["alternative_title"] ?? "",
        ":description" => $detail["description"] ?? "",
        ":cover" => $detail["cover_image_url"] ?? "",
        ":latest_ch" => (float) ($detail["latest_chapter_number"] ?? 0),
        ":author" => $detail["author"] ?? "",
        ":artist" => $detail["artist"] ?? "",
        ":genres" => $detail["genres"] ?? "",
        ":release_year" => $detail["release_year"] ?? "",
        ":rating" => $detail["rating"] ?? null,
    ]);
}

function bindMangaSource(PDO $pdo, string $mangaIdInternal, string $source, string $sourceRef): void
{
    $stmt = $pdo->prepare("
        INSERT INTO manga_sources (manga_id, source, source_ref)
        VALUES (:mid, :src, :ref)
        ON DUPLICATE KEY UPDATE source_ref = VALUES(source_ref)
    ");
    $stmt->execute([":mid" => $mangaIdInternal, ":src" => $source, ":ref" => $sourceRef]);
}

/** Semua binding sumber utk 1 manga, mis. [['source'=>'shngm','source_ref'=>'..'], ['source'=>'komiku','source_ref'=>'..']] */
function getMangaSources(PDO $pdo, string $mangaIdInternal): array
{
    $stmt = $pdo->prepare("SELECT source, source_ref FROM manga_sources WHERE manga_id = :mid");
    $stmt->execute([":mid" => $mangaIdInternal]);
    return $stmt->fetchAll();
}

function setPreferredSource(PDO $pdo, string $mangaIdInternal, ?string $source): void
{
    $stmt = $pdo->prepare("UPDATE mangas SET preferred_source = :src WHERE manga_id = :mid");
    $stmt->execute([":src" => $source, ":mid" => $mangaIdInternal]);
}

// ============================================================================
// CHAPTER SYNC (generic, jalan-mundur per-langkah, resume via chapter_sync_cursor)
// ============================================================================

function getSyncCursor(PDO $pdo, string $source, string $sourceChapterRef)
{
    $stmt = $pdo->prepare("SELECT * FROM chapter_sync_cursor WHERE source = :src AND source_chapter_ref = :ref");
    $stmt->execute([":src" => $source, ":ref" => $sourceChapterRef]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function saveSyncCursor(PDO $pdo, string $mangaIdInternal, string $source, string $sourceChapterRef, float $chapterNumber, ?string $prevRef): void
{
    $stmt = $pdo->prepare("
        INSERT INTO chapter_sync_cursor (manga_id, source, source_chapter_ref, chapter_number, prev_source_chapter_ref)
        VALUES (:mid, :src, :ref, :num, :prev)
        ON DUPLICATE KEY UPDATE prev_source_chapter_ref = VALUES(prev_source_chapter_ref)
    ");
    $stmt->execute([
        ":mid" => $mangaIdInternal, ":src" => $source, ":ref" => $sourceChapterRef,
        ":num" => $chapterNumber, ":prev" => $prevRef,
    ]);
}

function saveChapterImages(PDO $pdo, string $chapterId, array $imageUrls): void
{
    $stmtImg = $pdo->prepare("
        INSERT INTO chapter_images (chapter_id, page_number, filename)
        VALUES (:chapter_id, :page_number, :filename)
        ON DUPLICATE KEY UPDATE filename = VALUES(filename)
    ");
    foreach ($imageUrls as $i => $url) {
        $stmtImg->execute([
            ":chapter_id" => $chapterId,
            ":page_number" => $i + 1,
            ":filename" => $url, // sumber non-Shinigami mengirim URL penuh; kolom filename dipakai apa adanya
        ]);
    }
}

/**
 * Sinkronisasi SATU langkah (satu chapter) dari SATU sumber, dipanggil berulang
 * dari crawler.php (action=step) sambil jalan mundur lewat prev_source_chapter_ref.
 *
 * Dedup lintas-sumber: kalau chapter dgn nomor yang sama utk manga ini SUDAH ADA
 * (dari sumber manapun, termasuk sumber ini sendiri), tidak akan dibuat baris baru
 * -- kolom `chapters.chapter_id` tetap dipegang sumber yang PERTAMA kali
 * berhasil mengisinya. Chain jalan-mundur sumber INI tetap lanjut memakai
 * prev_source_chapter_ref dari sumber ini sendiri, terlepas dari itu.
 */
function genericSyncChapterStep(PDO $pdo, string $mangaIdInternal, string $source, string $sourceRef, string $chapterRef): array
{
    $adapter = getSource($source);
    if (!$adapter) throw new Exception("Sumber tidak dikenali: $source");

    // Resume cepat: kalau langkah ini sudah pernah diproses sumber ini, tidak perlu HTTP request lagi.
    $cursor = getSyncCursor($pdo, $source, $chapterRef);
    if ($cursor) {
        return [
            "skipped" => true,
            "chapter_number" => (float) $cursor["chapter_number"],
            "prev_chapter_id" => $cursor["prev_source_chapter_ref"],
        ];
    }

    $step = $adapter->fetchChapterStep($sourceRef, $chapterRef);
    $chapterNumber = (float) $step["chapter_number"];
    $prevRef = $step["prev_source_chapter_ref"];

    saveSyncCursor($pdo, $mangaIdInternal, $source, $chapterRef, $chapterNumber, $prevRef);

    // Cek dedup lintas-sumber berbasis nomor chapter (bukan chapter_id, krn beda sumber = beda chapter_id)
    $stmt = $pdo->prepare("SELECT chapter_id FROM chapters WHERE manga_id = :mid AND chapter_number = :num");
    $stmt->execute([":mid" => $mangaIdInternal, ":num" => $chapterNumber]);
    $existingOwner = $stmt->fetchColumn();

    if ($existingOwner) {
        // Sudah ada (mungkin dari sumber lain, mungkin dari sumber ini di run sebelumnya
        // sebelum cursor tercatat) -- tidak insert dobel, chain tetap lanjut jalan.
        return ["skipped" => true, "chapter_number" => $chapterNumber, "prev_chapter_id" => $prevRef];
    }

    $chapterId = $source . ":" . $chapterRef;
    $stmt = $pdo->prepare("
        INSERT INTO chapters (chapter_id, manga_id, source, source_ref, chapter_number, chapter_title, base_url, image_path, prev_chapter_id, prev_verified)
        VALUES (:chapter_id, :manga_id, :source, :source_ref, :chapter_number, :chapter_title, '', '', :prev_chapter_id, 1)
    ");
    $stmt->execute([
        ":chapter_id" => $chapterId,
        ":manga_id" => $mangaIdInternal,
        ":source" => $source,
        ":source_ref" => $chapterRef,
        ":chapter_number" => $chapterNumber,
        ":chapter_title" => $step["chapter_title"] ?? "",
        ":prev_chapter_id" => $prevRef,
    ]);

    saveChapterImages($pdo, $chapterId, $step["images"]);

    return ["skipped" => false, "chapter_number" => $chapterNumber, "prev_chapter_id" => $prevRef];
}

/**
 * Sinkronisasi PENUH satu manga dari SATU sumber dalam 1 pemanggilan (dipakai cron).
 * $onChapterSaved (opsional): callback tiap 1 chapter baru tersimpan, utk logging.
 */
function genericSyncSourceFull(PDO $pdo, string $mangaIdInternal, string $source, string $sourceRef, $onChapterSaved = null): array
{
    $adapter = getSource($source);
    if (!$adapter) throw new Exception("Sumber tidak dikenali: $source");

    $info = $adapter->fetchMangaInfo($sourceRef);
    saveMangaCore($pdo, $mangaIdInternal, $info);
    bindMangaSource($pdo, $mangaIdInternal, $source, $sourceRef);

    $currentRef = $info["latest_chapter_ref"];
    $newChapters = 0;
    $skippedChapters = 0;

    while ($currentRef) {
        $cursor = getSyncCursor($pdo, $source, $currentRef);
        if ($cursor) break; // sudah pernah sync sampai sini, berhenti (resume)

        $result = genericSyncChapterStep($pdo, $mangaIdInternal, $source, $sourceRef, $currentRef);

        if ($result["skipped"]) {
            $skippedChapters++;
        } else {
            $newChapters++;
            if ($onChapterSaved) $onChapterSaved($result["chapter_number"]);
        }

        $currentRef = $result["prev_chapter_id"];
        usleep(300000); // jeda 300ms antar request, ramah ke server sumber
    }

    return [
        "manga_id" => $mangaIdInternal,
        "title" => $info["title"],
        "new_chapters" => $newChapters,
        "skipped_chapters" => $skippedChapters,
    ];
}

/** Sinkronisasi manga lewat SEMUA sumber yg ter-bind, urut sesuai preferred_source. */
function genericSyncMangaAllSources(PDO $pdo, string $mangaIdInternal, $onChapterSaved = null): array
{
    $stmt = $pdo->prepare("SELECT preferred_source FROM mangas WHERE manga_id = :mid");
    $stmt->execute([":mid" => $mangaIdInternal]);
    $preferred = $stmt->fetchColumn() ?: null;

    $bindings = getMangaSources($pdo, $mangaIdInternal);
    $bySource = [];
    foreach ($bindings as $b) $bySource[$b["source"]] = $b["source_ref"];

    $orderedKeys = orderSourcesByPreference(array_keys($bySource), $preferred);

    $totalNew = 0;
    $totalSkipped = 0;
    $perSourceResult = [];
    foreach ($orderedKeys as $source) {
        $result = genericSyncSourceFull($pdo, $mangaIdInternal, $source, $bySource[$source], $onChapterSaved);
        $totalNew += $result["new_chapters"];
        $totalSkipped += $result["skipped_chapters"];
        $perSourceResult[$source] = $result;
    }

    return [
        "manga_id" => $mangaIdInternal,
        "new_chapters" => $totalNew,
        "skipped_chapters" => $totalSkipped,
        "per_source" => $perSourceResult,
    ];
}
