<?php
/**
 * Headless Cron Script untuk memeriksa dan mengunduh chapter terbaru
 * untuk seluruh manga yang ada di database. Logika sync-nya sama persis
 * dengan yang dipakai tombol "Cek Update Semua Manga" di UI (lihat sync_functions.php).
 *
 * Cara menjalankan:
 * 1. CLI Terminal / Crontab Server:
 *    php cron_update.php
 * 2. Web Request / GitHub Actions / Webhook:
 *    http://yoursite.com/cron_update.php?key=manga_reader_secret_key_123
 */

require_once "config.php";
require_once "sync_functions.php";

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

set_time_limit(0); // koleksi besar bisa makan waktu lama

function cronLog($msg) {
    if (php_sapi_name() === 'cli') {
        echo $msg . "\n";
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
        $syncResult = shngmSyncMangaFull($pdo, $mangaId, function ($chapterNumber) {
            cronLog("  ✓ Chapter $chapterNumber disimpan");
        });

        $totalNewChapters += $syncResult["new_chapters"];
        $results[] = [
            "manga_id" => $mangaId,
            "title" => $title,
            "new_chapters" => $syncResult["new_chapters"],
            "status" => "success"
        ];

        cronLog("  -> $title selesai: {$syncResult['new_chapters']} chapter baru.");
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