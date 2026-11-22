<?php
/**
 * Public Search API - mencari manga dari SEMUA sumber, lalu DEDUPLICATE
 * berdasarkan judul (case-insensitive, trim) supaya manga yang sama dari
 * sumber berbeda digabung jadi 1 kartu dengan badge multi-sumber.
 *
 * Dipakai oleh discover.php (halaman pencarian publik untuk semua user).
 */
require_once "config.php";
require_once "sources/SourceRegistry.php";
optionalAuth();

header("Content-Type: application/json; charset=utf-8");

$query = trim($_GET["q"] ?? "");
$page = max(1, (int) ($_GET["page"] ?? 1));

// Opsional: batasi pencarian ke sumber tertentu (untuk "Muat Lebih Banyak" supaya
// sumber non-paging seperti Komiku/CosmicScans tidak di-query ulang & duplikat).
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

/**
 * DEDUPLICATE: Gabungkan item dari semua sumber berdasarkan judul yang "mirip".
 * Strategi: normalisasi judul (lowercase, hapus non-alnum, trim spasi ganda) -> key.
 * Item dari sumber berbeda dengan key yang sama akan digabung.
 */
$normalizedItems = []; // norm_title -> [merged_item, source_keys[]]
$sourceLabels = [];
foreach (getAllSources() as $key => $adapter) {
    $sourceLabels[$key] = $adapter->getLabel();
}

foreach ($resultsBySource as $sourceKey => $group) {
    foreach ($group["items"] as $item) {
        $normTitle = normalizeTitleForDedup($item["title"]);
        if (!isset($normalizedItems[$normTitle])) {
            $normalizedItems[$normTitle] = [
                "title" => $item["title"],
                "cover_image_url" => $item["cover_image_url"] ?? "",
                "latest_chapter_number" => $item["latest_chapter_number"] ?? null,
                "rating" => $item["rating"] ?? null,
                "sources" => [], // array of [source_key, source_ref, source_label]
                "has_more" => false,
            ];
        }
        $merged = &$normalizedItems[$normTitle];
        $merged["sources"][] = [
            "key" => $sourceKey,
            "ref" => $item["ref"],
            "label" => $sourceLabels[$sourceKey] ?? $sourceKey,
        ];
        // Gunakan cover/rating/chapter dari sumber yang PALING LENGKAP
        if (($item["cover_image_url"] ?? "") && !$merged["cover_image_url"]) {
            $merged["cover_image_url"] = $item["cover_image_url"];
        }
        if (($item["latest_chapter_number"] ?? null) !== null && ($merged["latest_chapter_number"] ?? null) === null) {
            $merged["latest_chapter_number"] = $item["latest_chapter_number"];
        }
        if (($item["rating"] ?? null) !== null && ($merged["rating"] ?? null) === null) {
            $merged["rating"] = $item["rating"];
        }
        if (!empty($group["has_more"])) {
            $merged["has_more"] = true;
        }
    }
}

// Konversi ke array indexed untuk JSON
$finalItems = array_values($normalizedItems);

// Hitung sourcesWithMore untuk pagination "Muat Lebih Banyak"
$sourcesWithMore = [];
foreach ($resultsBySource as $sourceKey => $group) {
    if (!empty($group["has_more"])) {
        $sourcesWithMore[] = $sourceKey;
    }
}

echo json_encode([
    "success" => true,
    "query" => $query,
    "page" => $page,
    "items" => $finalItems,
    "total" => count($finalItems),
    "sources_with_more" => $sourcesWithMore,
    "errors" => $errors,
], JSON_UNESCAPED_UNICODE);

/**
 * Normalisasi judul untuk dedup: lowercase, hapus karakter non-alnum (kecuali spasi),
 * kompres spasi ganda, trim.
 */
function normalizeTitleForDedup(string $title): string
{
    $t = mb_strtolower(trim($title));
    $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t); // hapus simbol/punctuation
    $t = preg_replace('/\s+/', ' ', $t); // kompres spasi
    return trim($t);
}