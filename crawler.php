<?php
/**
 * API endpoint untuk crawl data manga & chapter dari Shinigami, dipanggil
 * SATU CHAPTER PER REQUEST oleh JavaScript di crawl.php & crawl_all.php
 * (supaya tidak kena batas max_execution_time dan progressnya bisa ditampilkan).
 *
 * Logika sync-nya sama persis dengan yang dipakai cron_update.php, lihat sync_functions.php.
 *
 * action=init  -> ambil & simpan info manga, kembalikan chapter terbaru
 * action=step  -> ambil & simpan 1 chapter, kembalikan prev_chapter_id
 */

require_once "config.php";
require_once "sync_functions.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

$action = $_GET["action"] ?? null;

try {
    if ($action === "init") {
        $mangaId = $_GET["manga_id"] ?? null;
        if (!$mangaId) throw new Exception("manga_id wajib diisi");

        $manga = shngmApiGet(SHNGM_API_BASE . "/manga/detail/$mangaId");
        shngmSaveManga($pdo, $manga);

        echo json_encode([
            "success" => true,
            "title" => $manga["title"],
            "latest_chapter_id" => $manga["latest_chapter_id"],
            "latest_chapter_number" => $manga["latest_chapter_number"],
        ]);
        exit;
    }

    if ($action === "step") {
        $mangaId = $_GET["manga_id"] ?? null;
        $chapterId = $_GET["chapter_id"] ?? null;
        if (!$mangaId || !$chapterId) throw new Exception("manga_id dan chapter_id wajib diisi");

        $result = shngmSyncChapterStep($pdo, $mangaId, $chapterId);
        echo json_encode(array_merge(["success" => true, "done" => false], $result));
        exit;
    }

    throw new Exception("action tidak dikenali");
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}