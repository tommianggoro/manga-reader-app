<?php
require_once "config.php";
requireAuth();

// Ambil seluruh data mangas
$mangas = $pdo->query("SELECT * FROM mangas ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$exportData = [
    "version" => "1.0",
    "app" => "Manga Reader Personal",
    "exported_at" => date("Y-m-d H:i:s"),
    "mangas" => []
];

foreach ($mangas as $m) {
    // Ambil chapter per manga
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

$filename = "manga_reader_backup_" . date("Y-m-d_H-i") . ".json";
$jsonString = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

header("Content-Type: application/json; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Length: " . strlen($jsonString));

echo $jsonString;
exit;
