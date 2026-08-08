<?php
require_once "config.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

$mangaId = $_POST["manga_id"] ?? null;
if (!$mangaId) {
    echo json_encode(["success" => false, "error" => "manga_id wajib diisi"]);
    exit;
}

$userId = currentUserId();

$stmt = $pdo->prepare("SELECT manga_id FROM mangas WHERE manga_id = :id");
$stmt->execute([":id" => $mangaId]);
if (!$stmt->fetch()) {
    echo json_encode(["success" => false, "error" => "Manga tidak ditemukan"]);
    exit;
}

$stmt = $pdo->prepare("SELECT is_favorite FROM user_manga_state WHERE user_id = :uid AND manga_id = :mid");
$stmt->execute([":uid" => $userId, ":mid" => $mangaId]);
$row = $stmt->fetch();

$newValue = ($row && $row["is_favorite"]) ? 0 : 1;

$stmt = $pdo->prepare("
    INSERT INTO user_manga_state (user_id, manga_id, is_favorite)
    VALUES (:uid, :mid, :fav)
    ON DUPLICATE KEY UPDATE is_favorite = VALUES(is_favorite)
");
$stmt->execute([":uid" => $userId, ":mid" => $mangaId, ":fav" => $newValue]);

echo json_encode(["success" => true, "is_favorite" => (bool) $newValue]);