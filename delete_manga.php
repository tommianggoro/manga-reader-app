<?php
require_once "config.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "Metode request harus POST"]);
    exit;
}

// Menerima input manga_id tunggal atau manga_ids array
$mangaId = $_POST["manga_id"] ?? null;
$mangaIds = $_POST["manga_ids"] ?? null;

if (empty($mangaId) && empty($mangaIds)) {
    echo json_encode(["success" => false, "error" => "Tidak ada manga yang dipilih untuk dihapus"]);
    exit;
}

$targetIds = [];
if (!empty($mangaIds)) {
    if (is_string($mangaIds)) {
        $targetIds = json_decode($mangaIds, true) ?: explode(",", $mangaIds);
    } elseif (is_array($mangaIds)) {
        $targetIds = $mangaIds;
    }
} elseif (!empty($mangaId)) {
    $targetIds = [$mangaId];
}

$targetIds = array_filter(array_map('trim', $targetIds));

if (empty($targetIds)) {
    echo json_encode(["success" => false, "error" => "Daftar manga_id tidak valid"]);
    exit;
}

try {
    $placeholders = implode(",", array_fill(0, count($targetIds), "?"));
    $stmt = $pdo->prepare("DELETE FROM mangas WHERE manga_id IN ($placeholders)");
    $stmt->execute(array_values($targetIds));

    $deletedCount = $stmt->rowCount();

    echo json_encode([
        "success" => true,
        "deleted_count" => $deletedCount,
        "message" => "Berhasil menghapus $deletedCount manga dari koleksi."
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Gagal menghapus manga: " . $e->getMessage()]);
}
