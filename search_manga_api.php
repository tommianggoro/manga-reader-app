<?php
require_once "config.php";
require_once "sources/SourceRegistry.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

$query = trim($_GET["q"] ?? "");
$page = max(1, (int) ($_GET["page"] ?? 1));

// Opsional: batasi pencarian ke sumber tertentu saja. Dipakai oleh "Muat Lebih
// Banyak" di halaman search supaya sumber yg TIDAK mendukung paging (Komiku,
// CosmicScans) tidak di-query ulang & menghasilkan duplikat di halaman berikutnya.
$onlySources = null;
if (!empty($_GET["sources"])) {
    $onlySources = array_filter(array_map('trim', explode(",", $_GET["sources"])));
}

if (mb_strlen($query) < 2) {
    echo json_encode(["success" => false, "error" => "Kata kunci pencarian minimal 2 karakter"]);
    exit;
}

$resultsBySource = [];
$errors = [];

foreach (getAllSources() as $key => $adapter) {
    if ($onlySources !== null && !in_array($key, $onlySources, true)) continue;

    try {
        $result = $adapter->search($query, $page);
        $items = $result["items"] ?? [];
        if (!empty($items)) {
            $resultsBySource[$key] = [
                "label" => $adapter->getLabel(),
                "items" => $items,
                "has_more" => !empty($result["has_more"]),
            ];
        }
    } catch (Exception $e) {
        $errors[$key] = $e->getMessage();
    }
}

echo json_encode([
    "success" => true,
    "query" => $query,
    "page" => $page,
    "results" => $resultsBySource,
    "errors" => $errors,
]);