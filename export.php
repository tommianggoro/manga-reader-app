<?php
require_once "config.php";
requireAuth();

$userId = currentUserId();

$mangas = $pdo->query("SELECT * FROM mangas ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$exportData = [
    "version" => "2.0",
    "app" => "Manga Reader Personal",
    "exported_by" => currentUsername(),
    "exported_at" => date("Y-m-d H:i:s"),
    "mangas" => [],
    "user_state" => [
        "favorites" => [],
        "last_read" => [],
        "reading_progress" => [],
    ],
];

foreach ($mangas as $m) {
    $stmtCh = $pdo->prepare("SELECT * FROM chapters WHERE manga_id = :mid ORDER BY chapter_number ASC");
    $stmtCh->execute([":mid" => $m["manga_id"]]);
    $chapters = $stmtCh->fetchAll(PDO::FETCH_ASSOC);

    $mangaItem = $m;
    $mangaItem["chapters"] = [];

    foreach ($chapters as $ch) {
        $stmtImg = $pdo->prepare("SELECT page_number, filename FROM chapter_images WHERE chapter_id = :cid ORDER BY page_number ASC");
        $stmtImg->execute([":cid" => $ch["chapter_id"]]);
        $images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

        $chItem = $ch;
        $chItem["images"] = $images;
        $mangaItem["chapters"][] = $chItem;
    }

    $exportData["mangas"][] = $mangaItem;
}

// Status pribadi user yang sedang login
$stmt = $pdo->prepare("SELECT manga_id, is_favorite, last_read_chapter_id, last_read_chapter_number, last_read_at FROM user_manga_state WHERE user_id = :uid");
$stmt->execute([":uid" => $userId]);
foreach ($stmt->fetchAll() as $row) {
    if ($row["is_favorite"]) {
        $exportData["user_state"]["favorites"][] = $row["manga_id"];
    }
    if ($row["last_read_chapter_id"]) {
        $exportData["user_state"]["last_read"][$row["manga_id"]] = [
            "chapter_id" => $row["last_read_chapter_id"],
            "chapter_number" => (float) $row["last_read_chapter_number"],
            "last_read_at" => $row["last_read_at"],
        ];
    }
}

$stmt = $pdo->prepare("SELECT chapter_id, scroll_position, single_page_index FROM reading_progress WHERE user_id = :uid");
$stmt->execute([":uid" => $userId]);
foreach ($stmt->fetchAll() as $row) {
    $exportData["user_state"]["reading_progress"][$row["chapter_id"]] = [
        "scroll_position" => $row["scroll_position"] !== null ? (int) $row["scroll_position"] : null,
        "single_page_index" => $row["single_page_index"] !== null ? (int) $row["single_page_index"] : null,
    ];
}

$filename = "manga_reader_backup_" . date("Y-m-d_H-i") . ".json";
$jsonString = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

header("Content-Type: application/json; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Length: " . strlen($jsonString));

echo $jsonString;
exit;