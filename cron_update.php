<?php
/**
 * Headless Cron Script untuk memeriksa & mengunduh chapter terbaru untuk
 * seluruh manga di database, dari SEMUA sumber yg ter-bind ke tiap manga
 * (lihat manga_sources). Logika sync-nya sama persis dgn tombol "Cek Update
 * Semua Manga" di UI (lihat sync_functions.php -> genericSyncMangaAllSources).
 *
 * Cara menjalankan:
 * 1. CLI Terminal / Crontab Server:
 *    php cron_update.php
 * 2. Web Request / GitHub Actions / Webhook:
 *    http://yoursite.com/cron_update.php?key=manga_reader_secret_key_123
 */

require_once "config.php";
require_once "sync_functions.php";

$isCli = (php_sapi_name() === 'cli');
$keyInput = $_GET['key'] ?? null;

// Optional: max chapters per run per source (for batching long syncs)
// Usage: cron_update.php?key=...&max_chapters=50
$maxChaptersPerRun = isset($_GET['max_chapters']) ? max(1, (int)$_GET['max_chapters']) : null;

// Optional: parallel mode - fork one process per source (requires pcntl, Linux only)
// Usage: cron_update.php?key=...&max_chapters=50&parallel=1
$parallel = isset($_GET['parallel']) && $_GET['parallel'] === '1';

if (!$isCli && $keyInput !== CRON_SECRET_KEY) {
    header("HTTP/1.1 403 Forbidden");
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "error" => "Akses ditolak: Key tidak valid."]);
    exit;
}

if (!$isCli) {
    header("Content-Type: application/json; charset=utf-8");
}

set_time_limit(0);

function cronLog($msg) {
    if (php_sapi_name() === 'cli') {
        echo $msg . "\n";
    }
}

$startTime = microtime(true);
cronLog("=== MEMULAI SYNC MANGA CRONJOB (multi-source) ===");

$mangas = $pdo->query("SELECT manga_id, title FROM mangas ORDER BY title ASC")->fetchAll();
$totalManga = count($mangas);
$totalNewChapters = 0;
$results = [];

foreach ($mangas as $index => $m) {
    $mangaId = $m["manga_id"];
    $title = $m["title"];
    cronLog("\n[" . ($index + 1) . "/$totalManga] Cek update: $title ($mangaId)");

    try {
        $syncResult = genericSyncMangaAllSources($pdo, $mangaId, function ($chapterNumber) {
            cronLog("  ✓ Chapter $chapterNumber disimpan");
        }, $maxChaptersPerRun, $parallel);

        $totalNewChapters += $syncResult["new_chapters"];

        // Check for source-level errors (per-source isolation)
        $sourceErrors = $syncResult["source_errors"] ?? [];
        $hasSourceErrors = !empty($sourceErrors);

        // Collect per-chapter errors from each source
        $allChapterErrors = [];
        foreach ($syncResult["per_source"] ?? [] as $src => $srcResult) {
            if (!empty($srcResult["errors"])) {
                foreach ($srcResult["errors"] as $err) {
                    $allChapterErrors[] = "[$src] " . $err["chapter_ref"] . ": " . $err["error"];
                }
            }
        }
        $hasChapterErrors = !empty($allChapterErrors);

        $results[] = [
            "manga_id" => $mangaId,
            "title" => $title,
            "new_chapters" => $syncResult["new_chapters"],
            "sources" => array_keys($syncResult["per_source"]),
            "status" => ($hasSourceErrors || $hasChapterErrors) ? "partial" : "success",
            "limited" => $syncResult["limited"] ?? false,
            "next_chapter_refs" => $syncResult["next_chapter_refs"] ?? [],
            "source_errors" => $sourceErrors,
            "chapter_errors" => $allChapterErrors,
        ];

        $msg = "  -> $title selesai: {$syncResult['new_chapters']} chapter baru dari " . count($syncResult["per_source"]) . " sumber.";
        if ($syncResult["limited"] ?? false) {
            $msg .= " (LIMITED - next refs: " . implode(", ", array_map(fn($s, $r) => "$s:$r", array_keys($syncResult["next_chapter_refs"]), $syncResult["next_chapter_refs"])) . ")";
        }
        if ($hasSourceErrors) {
            $msg .= " | Source errors: " . implode("; ", array_map(fn($s, $e) => "$s: $e", array_keys($sourceErrors), $sourceErrors));
        }
        if ($hasChapterErrors) {
            $msg .= " | Chapter errors: " . count($allChapterErrors) . " chapter(s) failed";
        }
        cronLog($msg);
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

// ==== Trigger per-user notifications (async via HTTP request) ====
// Hanya jalankan jika ada chapter baru dan bukan CLI (biar tidak nambah waktu response web)
// Kalau CLI, user bisa jalanin cron_notify_users.php manual atau lewat wrapper script.
if (!$isCli && $totalNewChapters > 0) {
    $notifyUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/cron_notify_users.php?key=" . CRON_SECRET_KEY;
    cronLog("Memicu notifikasi per-user: $notifyUrl");

    $ch = curl_init($notifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // timeout cepat, biarkan jalan di background
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
}

if (!$isCli) {
    echo json_encode([
        "success" => true,
        "execution_time_seconds" => $executionTime,
        "total_manga" => $totalManga,
        "total_new_chapters" => $totalNewChapters,
        "results" => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
