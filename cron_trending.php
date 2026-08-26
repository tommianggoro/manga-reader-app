<?php
/**
 * Cron 1x/hari: ambil top-3 manga TRENDING dari tiap sumber (lihat getTrending()
 * di masing-masing adapter sources/*.php), simpan ke tabel trending_manga.
 * index.php HANYA membaca dari tabel ini (tidak pernah fetch live ke 4 sumber
 * saat user buka halaman), supaya index.php tetap cepat di shared hosting.
 *
 * Cara menjalankan (sama seperti cron_update.php):
 *   CLI  : php cron_trending.php
 *   Web  : https://situskamu.com/cron_trending.php?key=CRON_SECRET_KEY
 */

require_once "config.php";
require_once "sources/SourceRegistry.php";

const TRENDING_LIMIT_PER_SOURCE = 3;

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

set_time_limit(0);

function trendingLog($msg) {
    if (php_sapi_name() === 'cli') echo $msg . "\n";
}

$results = [];
$totalSaved = 0;

$stmtInsert = $pdo->prepare("
    INSERT INTO trending_manga (source, source_ref, title, cover_image_url, latest_chapter_number, rating, rank_position)
    VALUES (:source, :ref, :title, :cover, :latest_ch, :rating, :rank)
");

$pdo->beginTransaction();
try {
    // Bersihkan cache lama dulu -- refresh PENUH tiap run (bukan upsert), supaya
    // manga yang sudah tidak trending lagi otomatis hilang dari daftar besok.
    $pdo->exec("DELETE FROM trending_manga");

    foreach (getAllSources() as $key => $adapter) {
        try {
            $items = $adapter->getTrending(TRENDING_LIMIT_PER_SOURCE);
            $rank = 0;
            foreach ($items as $item) {
                $rank++;
                $stmtInsert->execute([
                    ":source" => $key,
                    ":ref" => $item["ref"],
                    ":title" => $item["title"],
                    ":cover" => $item["cover_image_url"] ?? "",
                    ":latest_ch" => $item["latest_chapter_number"] ?? null,
                    ":rating" => $item["rating"] ?? null,
                    ":rank" => $rank,
                ]);
                $totalSaved++;
            }
            $results[$key] = ["status" => "success", "count" => $rank];
            trendingLog("✓ [{$adapter->getLabel()}] {$rank} manga trending disimpan.");
        } catch (Exception $e) {
            // Per-sumber error isolation -- sama spt search_manga_api.php:
            // 1 sumber gagal tidak menggagalkan sumber lain.
            $results[$key] = ["status" => "error", "error" => $e->getMessage()];
            trendingLog("❌ [{$adapter->getLabel()}] Gagal: " . $e->getMessage());
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    trendingLog("❌ FATAL: " . $e->getMessage());
    if (!$isCli) echo json_encode(["success" => false, "error" => $e->getMessage()]);
    exit(1);
}

trendingLog("\nTotal manga trending disimpan: $totalSaved");

if (!$isCli) {
    echo json_encode(["success" => true, "total_saved" => $totalSaved, "results" => $results]);
}