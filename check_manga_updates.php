<?php
/**
 * Endpoint ringan untuk polling background di manga.php.
 * Mengembalikan chapter-chapter BARU (chapter_number > since) untuk satu manga,
 * supaya bisa disisipkan langsung ke daftar chapter tanpa reload halaman.
 * Sumber data tetap dari cron sync (cron_update.php), endpoint ini hanya membaca.
 */

require_once "config.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

$mangaId = $_GET["manga_id"] ?? null;
$since = isset($_GET["since"]) ? (float) $_GET["since"] : 0;

if (!$mangaId) {
    echo json_encode(["success" => false, "error" => "manga_id wajib diisi"]);
    exit;
}

function formatTanggalIndoApi($datetime) {
    $bulan = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
    $ts = strtotime($datetime);
    return date("j", $ts) . " " . $bulan[(int) date("n", $ts) - 1] . " " . date("Y", $ts);
}

$stmt = $pdo->prepare("SELECT latest_chapter_number FROM mangas WHERE manga_id = :id");
$stmt->execute([":id" => $mangaId]);
$manga = $stmt->fetch();

if (!$manga) {
    echo json_encode(["success" => false, "error" => "Manga tidak ditemukan"]);
    exit;
}

$totalChapters = (int) $pdo->prepare("SELECT COUNT(*) FROM chapters WHERE manga_id = :id");
$stmt = $pdo->prepare("SELECT COUNT(*) FROM chapters WHERE manga_id = :id");
$stmt->execute([":id" => $mangaId]);
$totalChapters = (int) $stmt->fetchColumn();

$newChapters = [];
if ($since > 0) {
    $stmt = $pdo->prepare("SELECT chapter_id, chapter_number, chapter_title, updated_at, created_at FROM chapters WHERE manga_id = :id AND chapter_number > :since ORDER BY chapter_number ASC");
    $stmt->execute([":id" => $mangaId, ":since" => $since]);
    foreach ($stmt->fetchAll() as $ch) {
        $newChapters[] = [
            "chapter_id" => $ch["chapter_id"],
            "chapter_number" => (float) $ch["chapter_number"],
            "chapter_title" => $ch["chapter_title"] ?? "",
            "date_label" => formatTanggalIndoApi($ch["updated_at"] ?? $ch["created_at"]),
        ];
    }
}

echo json_encode([
    "success" => true,
    "latest_chapter_number" => (float) $manga["latest_chapter_number"],
    "total_chapters" => $totalChapters,
    "new_chapters" => $newChapters,
]);