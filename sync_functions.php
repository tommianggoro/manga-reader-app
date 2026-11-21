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

require_once __DIR__ . "/config.php";
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
 * $maxChaptersPerRun (opsional): batas maksimum chapter baru yang diproses per panggilan.
 *   - null (default) = tanpa batas (perilaku lama, sync sampai habis)
 *   - int > 0 = hentikan setelah N chapter BARU tersimpan, kembalikan next_chapter_ref untuk resume
 * Mengembalikan array dengan key tambahan: 'limited' (bool), 'next_chapter_ref' (string|null),
 * 'errors' (array of {chapter_ref, error}).
 */
function genericSyncSourceFull(PDO $pdo, string $mangaIdInternal, string $source, string $sourceRef, $onChapterSaved = null, ?int $maxChaptersPerRun = null): array
{
    $adapter = getSource($source);
    if (!$adapter) throw new Exception("Sumber tidak dikenali: $source");

    $info = $adapter->fetchMangaInfo($sourceRef);
    saveMangaCore($pdo, $mangaIdInternal, $info);
    bindMangaSource($pdo, $mangaIdInternal, $source, $sourceRef);

    $currentRef = $info["latest_chapter_ref"];
    $newChapters = 0;
    $skippedChapters = 0;
    $errors = [];

    // Rate limit delay from adapter (ms -> microseconds)
    $rateLimitDelayUs = $adapter->getRateLimitDelayMs() * 1000;

    while ($currentRef) {
        // Cek batas chapter per run (hanya hitung chapter BARU, bukan skipped)
        if ($maxChaptersPerRun !== null && $newChapters >= $maxChaptersPerRun) {
            // Sudah mencapai batas, kembalikan currentRef sebagai next_chapter_ref untuk resume
            return [
                "manga_id" => $mangaIdInternal,
                "title" => $info["title"],
                "new_chapters" => $newChapters,
                "skipped_chapters" => $skippedChapters,
                "limited" => true,
                "next_chapter_ref" => $currentRef,
                "errors" => $errors,
            ];
        }

        $cursor = getSyncCursor($pdo, $source, $currentRef);
        if ($cursor) break; // sudah pernah sync sampai sini, berhenti (resume)

        // Per-chapter error isolation: wrap in try/catch, continue on failure
        try {
            $result = genericSyncChapterStep($pdo, $mangaIdInternal, $source, $sourceRef, $currentRef);

            if ($result["skipped"]) {
                $skippedChapters++;
            } else {
                $newChapters++;
                if ($onChapterSaved) $onChapterSaved($result["chapter_number"]);
            }

            $currentRef = $result["prev_chapter_id"];
        } catch (Exception $e) {
            // Record error but continue to next chapter (via prev_chapter_id from cursor if available, else fetch)
            $errors[] = [
                "chapter_ref" => $currentRef,
                "error" => $e->getMessage(),
            ];
            // Try to get prev_chapter_id from cursor to continue chain, else stop this source
            $cursor = getSyncCursor($pdo, $source, $currentRef);
            if ($cursor && $cursor["prev_source_chapter_ref"]) {
                $currentRef = $cursor["prev_source_chapter_ref"];
            } else {
                // Can't continue chain without prev_ref
                break;
            }
        }
        usleep($rateLimitDelayUs); // jeda antar request, per-sumber configurable
    }

    return [
        "manga_id" => $mangaIdInternal,
        "title" => $info["title"],
        "new_chapters" => $newChapters,
        "skipped_chapters" => $skippedChapters,
        "limited" => false,
        "next_chapter_ref" => null,
        "errors" => $errors,
    ];
}

// ============================================================================
// MANGA-LEVEL SYNC CHECKPOINT (resume mid-fanout across sources)
// ============================================================================

/** Ambil checkpoint sync manga jika ada. */
function getMangaSyncCheckpoint(PDO $pdo, string $mangaIdInternal): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM manga_sync_checkpoint WHERE manga_id = :mid");
    $stmt->execute([":mid" => $mangaIdInternal]);
    $row = $stmt->fetch();
    if (!$row) return null;

    return [
        "current_source_index" => (int) $row["current_source_index"],
        "next_chapter_refs" => json_decode($row["next_chapter_refs"], true) ?: [],
        "max_chapters_per_run" => (int) $row["max_chapters_per_run"],
    ];
}

/** Simpan/update checkpoint sync manga. */
function saveMangaSyncCheckpoint(PDO $pdo, string $mangaIdInternal, int $currentSourceIndex, array $nextChapterRefs, int $maxChaptersPerRun): void
{
    $stmt = $pdo->prepare("
        INSERT INTO manga_sync_checkpoint (manga_id, current_source_index, next_chapter_refs, max_chapters_per_run)
        VALUES (:mid, :idx, :refs, :max)
        ON DUPLICATE KEY UPDATE
            current_source_index = VALUES(current_source_index),
            next_chapter_refs = VALUES(next_chapter_refs),
            max_chapters_per_run = VALUES(max_chapters_per_run),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ":mid" => $mangaIdInternal,
        ":idx" => $currentSourceIndex,
        ":refs" => json_encode($nextChapterRefs),
        ":max" => $maxChaptersPerRun,
    ]);
}

/** Hapus checkpoint sync manga (dipanggil saat sync selesai total). */
function clearMangaSyncCheckpoint(PDO $pdo, string $mangaIdInternal): void
{
    $stmt = $pdo->prepare("DELETE FROM manga_sync_checkpoint WHERE manga_id = :mid");
    $stmt->execute([":mid" => $mangaIdInternal]);
}

// ============================================================================
// PARALLEL SOURCE SYNC (pcntl_fork)
// ============================================================================

/** Cek apakah parallel processing tersedia (pcntl_fork di Linux/Unix). */
function isParallelSyncAvailable(): bool
{
    return function_exists('pcntl_fork') && PHP_OS_FAMILY !== 'Windows';
}

/**
 * Jalankan sync untuk SATU source di child process.
 * Child process mesti buat PDO connection sendiri.
 * Hasil ditulis ke temp file JSON.
 */
function syncSourceInChildProcess(string $mangaIdInternal, string $source, string $sourceRef, string $currentRef, ?int $maxChaptersPerRun, string $tempFile): void
{
    try {
        $pdo = createPdoConnection();
        $result = genericSyncSourceFullFromRef($pdo, $mangaIdInternal, $source, $sourceRef, $currentRef, null, $maxChaptersPerRun);
        file_put_contents($tempFile, json_encode(["success" => true, "result" => $result]));
    } catch (Exception $e) {
        file_put_contents($tempFile, json_encode(["success" => false, "error" => $e->getMessage()]));
    }
    exit(0); // Child exits here
}

/**
 * Versi parallel dari genericSyncMangaAllSources.
 * Fork satu process per source, jalankan bersamaan, kumpulkan hasil.
 * Hanya jalan kalau pcntl_fork tersedia DAN $parallel = true.
 */
function genericSyncMangaAllSourcesParallel(PDO $pdo, string $mangaIdInternal, $onChapterSaved = null, ?int $maxChaptersPerRun = null): array
{
    // Fallback ke sequential kalau parallel tidak tersedia
    if (!isParallelSyncAvailable()) {
        return genericSyncMangaAllSources($pdo, $mangaIdInternal, $onChapterSaved, $maxChaptersPerRun);
    }

    $stmt = $pdo->prepare("SELECT preferred_source FROM mangas WHERE manga_id = :mid");
    $stmt->execute([":mid" => $mangaIdInternal]);
    $preferred = $stmt->fetchColumn() ?: null;

    $bindings = getMangaSources($pdo, $mangaIdInternal);
    $bySource = [];
    foreach ($bindings as $b) $bySource[$b["source"]] = $b["source_ref"];

    $orderedKeys = orderSourcesByPreference(array_keys($bySource), $preferred);

    // Cek checkpoint existing untuk resume
    $checkpoint = getMangaSyncCheckpoint($pdo, $mangaIdInternal);
    $startSourceIndex = 0;
    $resumeChapterRefs = [];

    if ($checkpoint && $checkpoint["max_chapters_per_run"] === $maxChaptersPerRun) {
        $startSourceIndex = $checkpoint["current_source_index"];
        $resumeChapterRefs = $checkpoint["next_chapter_refs"];
    }

    // Siapkan argumen untuk setiap source yang akan di-fork
    $tasks = [];
    foreach ($orderedKeys as $index => $source) {
        if ($index < $startSourceIndex) continue;

        $sourceRef = $bySource[$source];
        $currentRef = null;

        if ($index === $startSourceIndex && isset($resumeChapterRefs[$source])) {
            $currentRef = $resumeChapterRefs[$source];
        } else {
            $adapter = getSource($source);
            if ($adapter) {
                $info = $adapter->fetchMangaInfo($sourceRef);
                $currentRef = $info["latest_chapter_ref"];
            }
        }

        if (!$currentRef) {
            // Source tanpa chapter - skip tapi tetap catat
            continue;
        }

        $tasks[] = [
            "index" => $index,
            "source" => $source,
            "sourceRef" => $sourceRef,
            "currentRef" => $currentRef,
        ];
    }

    if (empty($tasks)) {
        // Tidak ada source untuk disync
        clearMangaSyncCheckpoint($pdo, $mangaIdInternal);
        return [
            "manga_id" => $mangaIdInternal,
            "new_chapters" => 0,
            "skipped_chapters" => 0,
            "per_source" => [],
            "limited" => false,
            "next_chapter_refs" => [],
            "source_errors" => [],
        ];
    }

    // Fork child processes
    $pids = [];
    $tempFiles = [];
    foreach ($tasks as $task) {
        $tempFile = sys_get_temp_dir() . "/manga_sync_{$mangaIdInternal}_{$task['source']}_" . uniqid() . ".json";
        $pid = pcntl_fork();

        if ($pid === -1) {
            // Fork gagal - fallback sequential untuk task ini
            error_log("pcntl_fork failed for source {$task['source']}, falling back to sequential");
            $pdoChild = createPdoConnection();
            try {
                $result = genericSyncSourceFullFromRef($pdoChild, $mangaIdInternal, $task["source"], $task["sourceRef"], $task["currentRef"], null, $maxChaptersPerRun);
                $tempFiles[] = ["task" => $task, "result" => ["success" => true, "result" => $result]];
            } catch (Exception $e) {
                $tempFiles[] = ["task" => $task, "result" => ["success" => false, "error" => $e->getMessage()]];
            }
        } elseif ($pid === 0) {
            // CHILD PROCESS
            syncSourceInChildProcess($mangaIdInternal, $task["source"], $task["sourceRef"], $task["currentRef"], $maxChaptersPerRun, $tempFile);
            // syncSourceInChildProcess calls exit(0), so we never reach here
        } else {
            // PARENT PROCESS
            $pids[$pid] = $task;
            $tempFiles[] = ["task" => $task, "tempFile" => $tempFile];
        }
    }

    // Wait for all children
    $results = [];
    while ($pids) {
        $pid = pcntl_wait($status);
        if ($pid > 0) {
            unset($pids[$pid]);
        }
    }

    // Collect results from temp files
    $totalNew = 0;
    $totalSkipped = 0;
    $perSourceResult = [];
    $anyLimited = false;
    $nextChapterRefs = [];
    $sourceErrors = [];

    foreach ($tempFiles as $tf) {
        $task = $tf["task"];
        $source = $task["source"];

        if (isset($tf["result"])) {
            // Fallback sequential result
            $data = $tf["result"];
        } else {
            // Read from temp file
            $tempFile = $tf["tempFile"];
            $data = file_exists($tempFile) ? json_decode(file_get_contents($tempFile), true) : ["success" => false, "error" => "Temp file missing"];
            @unlink($tempFile);
        }

        if ($data["success"] ?? false) {
            $result = $data["result"];
            $totalNew += $result["new_chapters"] ?? 0;
            $totalSkipped += $result["skipped_chapters"] ?? 0;
            $perSourceResult[$source] = $result;

            if ($result["limited"] ?? false) {
                $anyLimited = true;
                $nextChapterRefs[$source] = $result["next_chapter_ref"] ?? null;
            }
        } else {
            $sourceErrors[$source] = $data["error"] ?? "Unknown error";
            $perSourceResult[$source] = [
                "manga_id" => $mangaIdInternal,
                "title" => "",
                "new_chapters" => 0,
                "skipped_chapters" => 0,
                "limited" => false,
                "next_chapter_ref" => null,
                "errors" => [["chapter_ref" => $task["currentRef"] ?? "init", "error" => $data["error"] ?? "Unknown error"]],
            ];
        }
    }

    // Handle checkpoint logic (same as sequential)
    if ($anyLimited && $maxChaptersPerRun !== null) {
        // Find which source hit the limit
        $limitedSource = null;
        foreach ($perSourceResult as $src => $res) {
            if ($res["limited"] ?? false) {
                $limitedSource = $src;
                break;
            }
        }
        if ($limitedSource !== null) {
            $limitedIndex = array_search($limitedSource, array_column($tasks, "source"));
            if ($limitedIndex !== false) {
                $nextChapterRefs = array_merge($resumeChapterRefs, $nextChapterRefs);
                saveMangaSyncCheckpoint($pdo, $mangaIdInternal, $tasks[$limitedIndex]["index"], $nextChapterRefs, $maxChaptersPerRun);

                return [
                    "manga_id" => $mangaIdInternal,
                    "new_chapters" => $totalNew,
                    "skipped_chapters" => $totalSkipped,
                    "per_source" => $perSourceResult,
                    "limited" => true,
                    "next_chapter_refs" => $nextChapterRefs,
                    "source_errors" => $sourceErrors,
                ];
            }
        }
    }

    // All sources completed without hitting limit
    clearMangaSyncCheckpoint($pdo, $mangaIdInternal);

    return [
        "manga_id" => $mangaIdInternal,
        "new_chapters" => $totalNew,
        "skipped_chapters" => $totalSkipped,
        "per_source" => $perSourceResult,
        "limited" => false,
        "next_chapter_refs" => [],
        "source_errors" => $sourceErrors,
    ];
}

/** Sinkronisasi manga lewat SEMUA sumber yg ter-bind, urut sesuai preferred_source.
 *  Mendukung resume mid-fanout via manga_sync_checkpoint table.
 *  Per-source error isolation: continues to next source on failure.
 *  $parallel: true = gunakan pcntl_fork untuk jalankan source bersamaan (hanya Linux/Unix). */
function genericSyncMangaAllSources(PDO $pdo, string $mangaIdInternal, $onChapterSaved = null, ?int $maxChaptersPerRun = null, bool $parallel = false): array
{
    // Parallel mode: hanya jalan kalau maxChaptersPerRun diset DAN parallel=true DAN pcntl_fork tersedia
    if ($parallel && $maxChaptersPerRun !== null && isParallelSyncAvailable()) {
        return genericSyncMangaAllSourcesParallel($pdo, $mangaIdInternal, $onChapterSaved, $maxChaptersPerRun);
    }

    // Jika tidak ada limit, jalankan langsung tanpa checkpoint (perilaku lama)
    if ($maxChaptersPerRun === null) {
        return genericSyncMangaAllSourcesUnlimited($pdo, $mangaIdInternal, $onChapterSaved);
    }

    $stmt = $pdo->prepare("SELECT preferred_source FROM mangas WHERE manga_id = :mid");
    $stmt->execute([":mid" => $mangaIdInternal]);
    $preferred = $stmt->fetchColumn() ?: null;

    $bindings = getMangaSources($pdo, $mangaIdInternal);
    $bySource = [];
    foreach ($bindings as $b) $bySource[$b["source"]] = $b["source_ref"];

    $orderedKeys = orderSourcesByPreference(array_keys($bySource), $preferred);

    // Cek checkpoint existing untuk resume
    $checkpoint = getMangaSyncCheckpoint($pdo, $mangaIdInternal);
    $startSourceIndex = 0;
    $resumeChapterRefs = [];

    if ($checkpoint && $checkpoint["max_chapters_per_run"] === $maxChaptersPerRun) {
        $startSourceIndex = $checkpoint["current_source_index"];
        $resumeChapterRefs = $checkpoint["next_chapter_refs"];
    }

    $totalNew = 0;
    $totalSkipped = 0;
    $perSourceResult = [];
    $anyLimited = false;
    $nextChapterRefs = []; // source -> next_chapter_ref untuk resume
    $sourceErrors = [];    // source -> error message

    foreach ($orderedKeys as $index => $source) {
        // Skip sources before resume point
        if ($index < $startSourceIndex) {
            continue;
        }

        // Determine starting chapter_ref for this source
        $sourceRef = $bySource[$source];
        $currentRef = null;

        if ($index === $startSourceIndex && isset($resumeChapterRefs[$source])) {
            // Resume from checkpoint for this source
            $currentRef = $resumeChapterRefs[$source];
        } else {
            // Start from latest chapter (normal flow)
            $adapter = getSource($source);
            if ($adapter) {
                $info = $adapter->fetchMangaInfo($sourceRef);
                $currentRef = $info["latest_chapter_ref"];
            }
        }

        // If no currentRef (no chapters or error), skip to next source
        if (!$currentRef) {
            $perSourceResult[$source] = [
                "manga_id" => $mangaIdInternal,
                "title" => "",
                "new_chapters" => 0,
                "skipped_chapters" => 0,
                "limited" => false,
                "next_chapter_ref" => null,
                "errors" => [],
            ];
            continue;
        }

        // Sync this source with the determined starting ref (per-source error isolation)
        try {
            $result = genericSyncSourceFullFromRef($pdo, $mangaIdInternal, $source, $sourceRef, $currentRef, $onChapterSaved, $maxChaptersPerRun);
        } catch (Exception $e) {
            // Source-level failure: record error, continue to next source
            $sourceErrors[$source] = $e->getMessage();
            $perSourceResult[$source] = [
                "manga_id" => $mangaIdInternal,
                "title" => "",
                "new_chapters" => 0,
                "skipped_chapters" => 0,
                "limited" => false,
                "next_chapter_ref" => null,
                "errors" => [["chapter_ref" => $currentRef ?? "init", "error" => $e->getMessage()]],
            ];
            continue;
        }

        $totalNew += $result["new_chapters"];
        $totalSkipped += $result["skipped_chapters"];
        $perSourceResult[$source] = $result;

        if ($result["limited"] ?? false) {
            $anyLimited = true;
            $nextChapterRefs[$source] = $result["next_chapter_ref"];

            // Save checkpoint and return early - we hit the limit
            // Preserve new ref for current source, keep old refs for subsequent sources
            $nextChapterRefs = array_merge($resumeChapterRefs, $nextChapterRefs);
            saveMangaSyncCheckpoint($pdo, $mangaIdInternal, $index, $nextChapterRefs, $maxChaptersPerRun);

            return [
                "manga_id" => $mangaIdInternal,
                "new_chapters" => $totalNew,
                "skipped_chapters" => $totalSkipped,
                "per_source" => $perSourceResult,
                "limited" => true,
                "next_chapter_refs" => $nextChapterRefs,
                "source_errors" => $sourceErrors,
            ];
        }
    }

    // All sources completed without hitting limit - clear checkpoint
    clearMangaSyncCheckpoint($pdo, $mangaIdInternal);

    return [
        "manga_id" => $mangaIdInternal,
        "new_chapters" => $totalNew,
        "skipped_chapters" => $totalSkipped,
        "per_source" => $perSourceResult,
        "limited" => false,
        "next_chapter_refs" => [],
        "source_errors" => $sourceErrors,
    ];
}

/** Versi unlimited (tanpa checkpoint) - dipakai saat maxChaptersPerRun = null.
 *  Per-source error isolation: continues to next source on failure.
 *  $parallel: true = gunakan pcntl_fork untuk jalankan source bersamaan (hanya Linux/Unix). */
function genericSyncMangaAllSourcesUnlimited(PDO $pdo, string $mangaIdInternal, $onChapterSaved = null, bool $parallel = false): array
{
    // Parallel mode untuk unlimited
    if ($parallel && isParallelSyncAvailable()) {
        return genericSyncMangaAllSourcesUnlimitedParallel($pdo, $mangaIdInternal, $onChapterSaved);
    }

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
    $sourceErrors = [];

    foreach ($orderedKeys as $source) {
        try {
            $result = genericSyncSourceFull($pdo, $mangaIdInternal, $source, $bySource[$source], $onChapterSaved, null);
        } catch (Exception $e) {
            // Source-level failure: record error, continue to next source
            $sourceErrors[$source] = $e->getMessage();
            $perSourceResult[$source] = [
                "manga_id" => $mangaIdInternal,
                "title" => "",
                "new_chapters" => 0,
                "skipped_chapters" => 0,
                "limited" => false,
                "next_chapter_ref" => null,
                "errors" => [["chapter_ref" => "init", "error" => $e->getMessage()]],
            ];
            continue;
        }
        $totalNew += $result["new_chapters"];
        $totalSkipped += $result["skipped_chapters"];
        $perSourceResult[$source] = $result;
    }

    return [
        "manga_id" => $mangaIdInternal,
        "new_chapters" => $totalNew,
        "skipped_chapters" => $totalSkipped,
        "per_source" => $perSourceResult,
        "limited" => false,
        "next_chapter_refs" => [],
        "source_errors" => $sourceErrors,
    ];
}

/** Versi parallel unlimited (tanpa checkpoint) - fork semua source sekaligus. */
function genericSyncMangaAllSourcesUnlimitedParallel(PDO $pdo, string $mangaIdInternal, $onChapterSaved = null): array
{
    $stmt = $pdo->prepare("SELECT preferred_source FROM mangas WHERE manga_id = :mid");
    $stmt->execute([":mid" => $mangaIdInternal]);
    $preferred = $stmt->fetchColumn() ?: null;

    $bindings = getMangaSources($pdo, $mangaIdInternal);
    $bySource = [];
    foreach ($bindings as $b) $bySource[$b["source"]] = $b["source_ref"];

    $orderedKeys = orderSourcesByPreference(array_keys($bySource), $preferred);

    // Determine starting refs for all sources
    $tasks = [];
    foreach ($orderedKeys as $source) {
        $adapter = getSource($source);
        if (!$adapter) continue;
        try {
            $info = $adapter->fetchMangaInfo($bySource[$source]);
            $currentRef = $info["latest_chapter_ref"] ?? null;
        } catch (Exception $e) {
            $currentRef = null;
        }
        if (!$currentRef) continue;

        $tasks[] = [
            "source" => $source,
            "sourceRef" => $bySource[$source],
            "currentRef" => $currentRef,
        ];
    }

    if (empty($tasks)) {
        return [
            "manga_id" => $mangaIdInternal,
            "new_chapters" => 0,
            "skipped_chapters" => 0,
            "per_source" => [],
            "limited" => false,
            "next_chapter_refs" => [],
            "source_errors" => [],
        ];
    }

    // Fork child processes (no limit, so null maxChaptersPerRun)
    $pids = [];
    $tempFiles = [];
    foreach ($tasks as $task) {
        $tempFile = sys_get_temp_dir() . "/manga_sync_{$mangaIdInternal}_{$task['source']}_" . uniqid() . ".json";
        $pid = pcntl_fork();

        if ($pid === -1) {
            error_log("pcntl_fork failed for source {$task['source']}, falling back to sequential");
            $pdoChild = createPdoConnection();
            try {
                $result = genericSyncSourceFullFromRef($pdoChild, $mangaIdInternal, $task["source"], $task["sourceRef"], $task["currentRef"], null, null);
                $tempFiles[] = ["task" => $task, "result" => ["success" => true, "result" => $result]];
            } catch (Exception $e) {
                $tempFiles[] = ["task" => $task, "result" => ["success" => false, "error" => $e->getMessage()]];
            }
        } elseif ($pid === 0) {
            // CHILD PROCESS
            syncSourceInChildProcess($mangaIdInternal, $task["source"], $task["sourceRef"], $task["currentRef"], null, $tempFile);
        } else {
            $pids[$pid] = $task;
            $tempFiles[] = ["task" => $task, "tempFile" => $tempFile];
        }
    }

    // Wait for all children
    while ($pids) {
        $pid = pcntl_wait($status);
        if ($pid > 0) unset($pids[$pid]);
    }

    // Collect results
    $totalNew = 0;
    $totalSkipped = 0;
    $perSourceResult = [];
    $sourceErrors = [];

    foreach ($tempFiles as $tf) {
        $task = $tf["task"];
        $source = $task["source"];

        if (isset($tf["result"])) {
            $data = $tf["result"];
        } else {
            $tempFile = $tf["tempFile"];
            $data = file_exists($tempFile) ? json_decode(file_get_contents($tempFile), true) : ["success" => false, "error" => "Temp file missing"];
            @unlink($tempFile);
        }

        if ($data["success"] ?? false) {
            $result = $data["result"];
            $totalNew += $result["new_chapters"] ?? 0;
            $totalSkipped += $result["skipped_chapters"] ?? 0;
            $perSourceResult[$source] = $result;
        } else {
            $sourceErrors[$source] = $data["error"] ?? "Unknown error";
            $perSourceResult[$source] = [
                "manga_id" => $mangaIdInternal,
                "title" => "",
                "new_chapters" => 0,
                "skipped_chapters" => 0,
                "limited" => false,
                "next_chapter_ref" => null,
                "errors" => [["chapter_ref" => $task["currentRef"] ?? "init", "error" => $data["error"] ?? "Unknown error"]],
            ];
        }
    }

    return [
        "manga_id" => $mangaIdInternal,
        "new_chapters" => $totalNew,
        "skipped_chapters" => $totalSkipped,
        "per_source" => $perSourceResult,
        "limited" => false,
        "next_chapter_refs" => [],
        "source_errors" => $sourceErrors,
    ];
}

/**
 * Sinkronisasi PENUH satu manga dari SATU sumber, mulai dari chapter_ref tertentu.
 * Variasi dari genericSyncSourceFull yang menerima $startChapterRef eksplisit untuk resume.
 * Per-chapter error isolation: continues on individual chapter failures.
 */
function genericSyncSourceFullFromRef(PDO $pdo, string $mangaIdInternal, string $source, string $sourceRef, string $startChapterRef, $onChapterSaved = null, ?int $maxChaptersPerRun = null): array
{
    $adapter = getSource($source);
    if (!$adapter) throw new Exception("Sumber tidak dikenali: $source");

    $info = $adapter->fetchMangaInfo($sourceRef);
    saveMangaCore($pdo, $mangaIdInternal, $info);
    bindMangaSource($pdo, $mangaIdInternal, $source, $sourceRef);

    $currentRef = $startChapterRef;
    $newChapters = 0;
    $skippedChapters = 0;
    $errors = [];

    // Rate limit delay from adapter (ms -> microseconds)
    $rateLimitDelayUs = $adapter->getRateLimitDelayMs() * 1000;

    while ($currentRef) {
        // Cek batas chapter per run (hanya hitung chapter BARU, bukan skipped)
        if ($maxChaptersPerRun !== null && $newChapters >= $maxChaptersPerRun) {
            return [
                "manga_id" => $mangaIdInternal,
                "title" => $info["title"],
                "new_chapters" => $newChapters,
                "skipped_chapters" => $skippedChapters,
                "limited" => true,
                "next_chapter_ref" => $currentRef,
                "errors" => $errors,
            ];
        }

        $cursor = getSyncCursor($pdo, $source, $currentRef);
        if ($cursor) break; // sudah pernah sync sampai sini, berhenti (resume)

        // Per-chapter error isolation: wrap in try/catch, continue on failure
        try {
            $result = genericSyncChapterStep($pdo, $mangaIdInternal, $source, $sourceRef, $currentRef);

            if ($result["skipped"]) {
                $skippedChapters++;
            } else {
                $newChapters++;
                if ($onChapterSaved) $onChapterSaved($result["chapter_number"]);
            }

            $currentRef = $result["prev_chapter_id"];
        } catch (Exception $e) {
            // Record error but continue to next chapter
            $errors[] = [
                "chapter_ref" => $currentRef,
                "error" => $e->getMessage(),
            ];
            // Try to get prev_chapter_id from cursor to continue chain
            $cursor = getSyncCursor($pdo, $source, $currentRef);
            if ($cursor && $cursor["prev_source_chapter_ref"]) {
                $currentRef = $cursor["prev_source_chapter_ref"];
            } else {
                // Can't continue chain without prev_ref
                break;
            }
        }
        usleep($rateLimitDelayUs); // jeda antar request, per-sumber configurable
    }

    return [
        "manga_id" => $mangaIdInternal,
        "title" => $info["title"],
        "new_chapters" => $newChapters,
        "skipped_chapters" => $skippedChapters,
        "limited" => false,
        "next_chapter_ref" => null,
        "errors" => $errors,
    ];
}
