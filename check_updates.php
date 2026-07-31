<?php
/**
 * Endpoint ringan untuk polling background di index.php.
 * Dipanggil berkala oleh JavaScript untuk mendeteksi perubahan (chapter baru
 * dari hasil cron sync) tanpa perlu reload halaman.
 */

require_once "config.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

$rows = $pdo->query("SELECT manga_id, latest_chapter_number FROM mangas")->fetchAll();

$totalManga = count($rows);
$totalChapters = (int) $pdo->query("SELECT COUNT(*) FROM chapters")->fetchColumn();
$totalFavorites = (int) $pdo->query("SELECT COUNT(*) FROM mangas WHERE is_favorite = 1")->fetchColumn();
$recentlyUpdated = (int) $pdo->query("SELECT COUNT(DISTINCT manga_id) FROM chapters WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$mangas = [];
foreach ($rows as $r) {
    $mangas[$r["manga_id"]] = (int) $r["latest_chapter_number"];
}

echo json_encode([
    "success" => true,
    "stats" => [
        "total_manga" => $totalManga,
        "total_chapters" => $totalChapters,
        "total_favorites" => $totalFavorites,
        "recently_updated" => $recentlyUpdated,
    ],
    // map manga_id -> latest_chapter_number, biar gampang di-diff di JS
    "mangas" => $mangas,
]);