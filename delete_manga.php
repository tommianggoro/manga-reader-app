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

    // PENTING: chapter_sync_cursor TIDAK punya foreign key ke mangas (dipisah sengaja
    // supaya resume tetap cepat lintas source walau baris chapters-nya sendiri kena
    // hapus/rebuild), jadi baris cursor lama TIDAK ikut kehapus otomatis lewat
    // ON DELETE CASCADE spt tabel lain. Kalau tidak dibersihkan manual di sini,
    // manga yang dihapus lalu ditambah ulang bisa "resume" dari cache sync lama --
    // termasuk cache yang sempat gagal/salah (mis. prev_source_chapter_ref ke-simpan
    // NULL akibat bug parser versi sebelumnya) -- dan sync mundur akan berhenti
    // seolah-olah sudah "selesai", padahal belum.
    $stmtCursor = $pdo->prepare("DELETE FROM chapter_sync_cursor WHERE manga_id IN ($placeholders)");
    $stmtCursor->execute(array_values($targetIds));

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