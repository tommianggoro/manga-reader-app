<?php
/**
 * API endpoint generic untuk crawl data manga & chapter dari SUMBER MANAPUN
 * yang terdaftar di sources/SourceRegistry.php, dipanggil SATU CHAPTER PER
 * REQUEST oleh JavaScript di crawl.php & crawl_all.php (supaya tidak kena
 * batas max_execution_time dan progress bisa ditampilkan).
 *
 * Params tambahan dibanding versi lama: `source` (mis. "shngm" / "komiku") dan
 * `source_ref` (ID/slug manga DI SUMBER itu, beda dari manga_id internal).
 *
 * action=init  -> ambil & simpan info manga dari 1 sumber, kembalikan chapter terbaru
 * action=step  -> ambil & simpan 1 chapter dari 1 sumber, kembalikan prev_chapter_id
 */

require_once "config.php";
require_once "sync_functions.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

$action = $_GET["action"] ?? null;

try {
    if ($action === "init") {
        $mangaId = $_GET["manga_id"] ?? null;    // manga_id INTERNAL
        $source = $_GET["source"] ?? null;
        $sourceRef = $_GET["source_ref"] ?? null;
        if (!$mangaId || !$source || !$sourceRef) {
            throw new Exception("manga_id, source, dan source_ref wajib diisi");
        }

        $adapter = getSource($source);
        if (!$adapter) throw new Exception("Sumber tidak dikenali: $source");

        $info = $adapter->fetchMangaInfo($sourceRef);
        saveMangaCore($pdo, $mangaId, $info);
        bindMangaSource($pdo, $mangaId, $source, $sourceRef);

        echo json_encode([
            "success" => true,
            "title" => $info["title"],
            "latest_chapter_id" => $info["latest_chapter_ref"],
            "latest_chapter_number" => $info["latest_chapter_number"],
        ]);
        exit;
    }

    if ($action === "step") {
        $mangaId = $_GET["manga_id"] ?? null;
        $source = $_GET["source"] ?? null;
        $sourceRef = $_GET["source_ref"] ?? null;
        $chapterId = $_GET["chapter_id"] ?? null; // source_chapter_ref
        if (!$mangaId || !$source || !$sourceRef || !$chapterId) {
            throw new Exception("manga_id, source, source_ref, dan chapter_id wajib diisi");
        }

        $result = genericSyncChapterStep($pdo, $mangaId, $source, $sourceRef, $chapterId);
        echo json_encode(array_merge(["success" => true, "done" => false], $result));
        exit;
    }

    throw new Exception("action tidak dikenali");
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
