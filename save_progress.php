<?php
require_once "config.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

$chapterId = $_POST["chapter_id"] ?? null;
$scrollPosition = isset($_POST["scroll_position"]) && $_POST["scroll_position"] !== "" ? (int) $_POST["scroll_position"] : null;
$singlePageIndex = isset($_POST["single_page_index"]) && $_POST["single_page_index"] !== "" ? (int) $_POST["single_page_index"] : null;

if (!$chapterId) {
    echo json_encode(["success" => false, "error" => "chapter_id wajib diisi"]);
    exit;
}

$userId = currentUserId();

// COALESCE: field yang tidak dikirim (null) tidak akan menimpa nilai lama.
$stmt = $pdo->prepare("
    INSERT INTO reading_progress (user_id, chapter_id, scroll_position, single_page_index)
    VALUES (:uid, :cid, :scroll, :page)
    ON DUPLICATE KEY UPDATE
        scroll_position = COALESCE(VALUES(scroll_position), scroll_position),
        single_page_index = COALESCE(VALUES(single_page_index), single_page_index)
");
$stmt->execute([
    ":uid" => $userId,
    ":cid" => $chapterId,
    ":scroll" => $scrollPosition,
    ":page" => $singlePageIndex,
]);

echo json_encode(["success" => true]);