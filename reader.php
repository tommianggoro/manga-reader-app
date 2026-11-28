<?php
/**
 * Manga Reader - Reader Page (Thin Controller)
 * Displays chapter images with webtoon/single-page modes, progress sync
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

// Start session for auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$chapterId = $_GET['chapter_id'] ?? die('chapter_id wajib diisi');

$stmt = $pdo->prepare('SELECT * FROM chapters WHERE chapter_id = :id');
$stmt->execute([':id' => $chapterId]);
$chapter = $stmt->fetch();
if (!$chapter) die('Chapter tidak ditemukan.');

$stmt = $pdo->prepare('SELECT * FROM mangas WHERE manga_id = :id');
$stmt->execute([':id' => $chapter['manga_id']]);
$manga = $stmt->fetch();

$userId = currentUserId(); // null kalau guest

// Ambil posisi baca terakhir chapter ini dari DB HANYA kalau login.
// Guest mengandalkan localStorage sepenuhnya (dibaca lewat JS di bawah).
$savedProgress = null;
if ($userId) {
    $stmt = $pdo->prepare('SELECT scroll_position, single_page_index FROM reading_progress WHERE user_id = :uid AND chapter_id = :cid');
    $stmt->execute([':uid' => $userId, ':cid' => $chapterId]);
    $savedProgress = $stmt->fetch();

    // Tandai chapter ini sebagai yang terakhir dibaca -- HANYA utk user yang
    // login (guest ditandai via localStorage lewat JS, lihat di bawah).
    $stmt = $pdo->prepare('
        INSERT INTO user_manga_state (user_id, manga_id, last_read_chapter_id, last_read_chapter_number, last_read_at)
        VALUES (:uid, :mid, :cid, :cnum, NOW())
        ON DUPLICATE KEY UPDATE
            last_read_chapter_id = VALUES(last_read_chapter_id),
            last_read_chapter_number = VALUES(last_read_chapter_number),
            last_read_at = VALUES(last_read_at)
    ');
    $stmt->execute([
        ':uid' => $userId,
        ':mid' => $chapter['manga_id'],
        ':cid' => $chapter['chapter_id'],
        ':cnum' => $chapter['chapter_number'],
    ]);
}

$stmt = $pdo->prepare('SELECT * FROM chapter_images WHERE chapter_id = :id ORDER BY page_number ASC');
$stmt->execute([':id' => $chapterId]);
$images = $stmt->fetchAll();

// Chapter sebelum & sesudah untuk navigasi
$stmt = $pdo->prepare('SELECT chapter_id, chapter_number FROM chapters WHERE manga_id = :m AND chapter_number < :n ORDER BY chapter_number DESC LIMIT 1');
$stmt->execute([':m' => $chapter['manga_id'], ':n' => $chapter['chapter_number']]);
$prevCh = $stmt->fetch();

$stmt = $pdo->prepare('SELECT chapter_id, chapter_number FROM chapters WHERE manga_id = :m AND chapter_number > :n ORDER BY chapter_number ASC LIMIT 1');
$stmt->execute([':m' => $chapter['manga_id'], ':n' => $chapter['chapter_number']]);
$nextCh = $stmt->fetch();

// Preload gambar untuk chapter selanjutnya (jika ada)
$nextChapterImageUrls = [];
if ($nextCh) {
    $stmt = $pdo->prepare('SELECT c.base_url, c.image_path, ci.filename FROM chapter_images ci JOIN chapters c ON ci.chapter_id = c.chapter_id WHERE ci.chapter_id = :id ORDER BY ci.page_number ASC');
    $stmt->execute([':id' => $nextCh['chapter_id']]);
    $nextImagesRaw = $stmt->fetchAll();
    foreach ($nextImagesRaw as $ni) {
        $nextChapterImageUrls[] = $ni['base_url'] . $ni['image_path'] . $ni['filename'];
    }
}

// Semua chapter untuk dropdown "loncat ke chapter"
$stmt = $pdo->prepare('SELECT chapter_id, chapter_number FROM chapters WHERE manga_id = :m ORDER BY chapter_number DESC');
$stmt->execute([':m' => $chapter['manga_id']]);
$allChapters = $stmt->fetchAll();

// Pass config to JavaScript
$jsConfig = [
    'isLoggedIn' => (bool) $userId,
    'currentMangaId' => $manga['manga_id'],
    'currentChapterId' => $chapter['chapter_id'],
    'currentChapterNumber' => (float) $chapter['chapter_number'],
    'prevChapterId' => $prevCh ? $prevCh['chapter_id'] : null,
    'nextChapterId' => $nextCh ? $nextCh['chapter_id'] : null,
    'nextChapterImageUrls' => $nextChapterImageUrls,
    'totalPages' => count($images),
    'dbScrollPosition' => ($savedProgress && $savedProgress['scroll_position'] !== null) ? (int)$savedProgress['scroll_position'] : null,
    'dbSinglePageIndex' => ($savedProgress && $savedProgress['single_page_index'] !== null) ? (int)$savedProgress['single_page_index'] : null,
];
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($manga['title']) ?> - Chapter <?= htmlspecialchars(formatChapterNumber($chapter['chapter_number'])) ?></title>

    <!-- Pre-paint theme script (no flash) -->
    <script>
    (function() {
        const savedTheme = localStorage.getItem('manga_theme') || 'dark';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    })();
    </script>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts: Bitter (serif) + Inter (sans) -->
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Design Tokens -->
    <link href="assets/css/variables.css" rel="stylesheet">
    <!-- Reader Specific Styles -->
    <link href="assets/css/reader.css" rel="stylesheet">

    <link rel="icon" href="favicon.ico" sizes="any">

    <link rel="manifest" href="https://tommianggoro.github.io/manga-reader-app/manifest.json">
    <meta name="theme-color" content="#10131a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Manga Reader">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
</head>
<body>

<?php
// Include composed content view
$manga = $manga;
$chapter = $chapter;
$images = $images;
$prevCh = $prevCh;
$nextCh = $nextCh;
$allChapters = $allChapters;
$userId = $userId;
$totalPages = count($images);
include __DIR__ . '/views/reader/content.php';
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Page-specific JS config -->
<script>
    window.MANGA_READER_CONFIG = <?= json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="assets/js/reader.js"></script>

</body>
</html>