<?php
/**
 * Full Cron Wrapper: Runs sync + per-user notifications sequentially
 * Usage: php cron_full.php [max_chapters] [parallel]
 *
 * Designed for actual server crontab (not GitHub Actions)
 * Example crontab: 0 5 * * * /usr/bin/php /path/to/cron_full.php 50 1
 */

require_once "config.php";
require_once "sync_functions.php";

set_time_limit(0);

$maxChaptersPerRun = $argv[1] ?? null;
if ($maxChaptersPerRun !== null) {
    $maxChaptersPerRun = max(1, (int)$maxChaptersPerRun);
}

$parallel = isset($argv[2]) && $argv[2] === '1';

function cronLog($msg) {
    echo $msg . "\n";
}

$startTime = microtime(true);
cronLog("=== MEMULAI FULL CRON (sync + notify) ===");

// ==== Step 1: Sync manga ====
$mangas = $pdo->query("SELECT manga_id, title FROM mangas ORDER BY title ASC")->fetchAll();
$totalManga = count($mangas);
$totalNewChapters = 0;

cronLog("Total manga: $totalManga");

foreach ($mangas as $index => $m) {
    $mangaId = $m["manga_id"];
    $title = $m["title"];
    cronLog("\n[" . ($index + 1) . "/$totalManga] Cek update: $title ($mangaId)");

    try {
        $syncResult = genericSyncMangaAllSources($pdo, $mangaId, function ($chapterNumber) {
            cronLog("  ✓ Chapter $chapterNumber disimpan");
        }, $maxChaptersPerRun, $parallel);

        $totalNewChapters += $syncResult["new_chapters"];
        cronLog("  -> $title: {$syncResult['new_chapters']} chapter baru");
    } catch (Exception $e) {
        cronLog("  ❌ ERROR: " . $e->getMessage());
    }
}

$syncTime = round(microtime(true) - $startTime, 2);
cronLog("\n=== SYNC SELESAI ({$syncTime}s) ===");
cronLog("Total Chapter Baru: $totalNewChapters");

// ==== Step 2: Per-user notifications ====
if ($totalNewChapters > 0) {
    cronLog("\n=== MEMULAI NOTIFIKASI PER-USER ===");
    require_once "cron_notify_users.php";
    runNotifyUsers($pdo);
} else {
    cronLog("\nTidak ada chapter baru, skip notifikasi per-user.");
}

$totalTime = round(microtime(true) - $startTime, 2);
cronLog("\n=== FULL CRON SELESAI ({$totalTime}s) ===");