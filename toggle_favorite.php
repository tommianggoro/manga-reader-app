<?php
require_once "config.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

$mangaId = $_POST["manga_id"] ?? null;
if (!$mangaId) {
    echo json_encode(["success" => false, "error" => "manga_id wajib diisi"]);
    exit;
}

$stmt = $pdo->prepare("SELECT is_favorite FROM mangas WHERE manga_id = :id");
$stmt->execute([":id" => $mangaId]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(["success" => false, "error" => "Manga tidak ditemukan"]);
    exit;
}

$newValue = $row["is_favorite"] ? 0 : 1;

$stmt = $pdo->prepare("UPDATE mangas SET is_favorite = :fav WHERE manga_id = :id");
$stmt->execute([":fav" => $newValue, ":id" => $mangaId]);

echo json_encode(["success" => true, "is_favorite" => (bool) $newValue]);
