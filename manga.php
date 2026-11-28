<?php
/**
 * Manga Reader - Detail Page (Thin Controller)
 * Displays manga detail with chapter list, reading progress, tags
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

// Start session for auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mangaId = $_GET['manga_id'] ?? die('manga_id wajib diisi');
$userId = currentUserId(); // null kalau guest
$isAdminUser = isAdmin();

// Fetch manga with user-specific state
$stmt = $pdo->prepare("
    SELECT m.*, COALESCE(s.is_favorite, 0) AS is_favorite,
           s.last_read_chapter_id, s.last_read_chapter_number, s.last_read_at
    FROM mangas m
    LEFT JOIN user_manga_state s ON s.manga_id = m.manga_id AND s.user_id = :uid
    WHERE m.manga_id = :id
");
$stmt->execute([':uid' => $userId, ':id' => $mangaId]);
$manga = $stmt->fetch();
if (!$manga) die('Manga tidak ditemukan di database.');

require_once 'sync_functions.php';
$mangaSourceBindings = getMangaSources($pdo, $mangaId); // [['source'=>..,'source_ref'=>..], ...]
$sourceLabelsMap = [];
foreach (getAllSources() as $key => $adapter) $sourceLabelsMap[$key] = $adapter->getLabel();

$stmt = $pdo->prepare('SELECT * FROM chapters WHERE manga_id = :id ORDER BY chapter_number DESC');
$stmt->execute([':id' => $mangaId]);
$chapters = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT chapter_id, chapter_number FROM chapters WHERE manga_id = :id ORDER BY chapter_number ASC LIMIT 1');
$stmt->execute([':id' => $mangaId]);
$firstChapter = $stmt->fetch();

// Reading history untuk manga ini (user login)
$readChapterIds = []; // chapter_id -> updated_at (waktu baca terakhir)
if ($userId) {
    $stmt = $pdo->prepare("
        SELECT rp.chapter_id, rp.updated_at
        FROM reading_progress rp
        JOIN chapters c ON c.id = rp.chapter_id
        WHERE rp.user_id = :uid AND c.manga_id = :mid
        ORDER BY rp.updated_at DESC
    ");
    $stmt->execute([':uid' => $userId, ':mid' => $mangaId]);
    foreach ($stmt->fetchAll() as $row) {
        $readChapterIds[$row['chapter_id']] = $row['updated_at'];
    }
}
$totalChaptersInManga = count($chapters);
$readChaptersCount = count($readChapterIds);
$readPercent = $totalChaptersInManga > 0 ? round(($readChaptersCount / $totalChaptersInManga) * 100) : 0;

// Latest chapter number for polling
$latestChapterNumber = $chapters[0]['chapter_number'] ?? 0;

// Pass config to JavaScript
$jsConfig = [
    'isLoggedIn' => (bool) $userId,
    'currentMangaId' => $mangaId,
    'knownLatestChapter' => (float) $latestChapterNumber,
];
?>

<?php
// Page variables for views
$pageTitle = $manga['title'] . ' - Detail Manga';
$backUrl = 'index.php';
$backLabel = 'Kembali ke koleksi';

// Include shared header
include __DIR__ . '/views/layout/header.php';
?>

<main id="main-content" class="container py-4" style="max-width: 850px;">
    <?php
    // Pass variables to content views
    $manga = $manga;
    $chapters = $chapters;
    $firstChapter = $firstChapter;
    $readChapterIds = $readChapterIds;
    $totalChapters = count($chapters);
    $readChaptersCount = $readChaptersCount;
    $totalChaptersInManga = $totalChaptersInManga;
    $readPercent = $readPercent;
    $userId = $userId;
    $isAdmin = $isAdminUser;
    $mangaSourceBindings = $mangaSourceBindings;
    $sourceLabelsMap = $sourceLabelsMap;

    // Header card
    include __DIR__ . '/views/manga/header-card.php';

    // Description
    if (!empty($manga['description'])):
    ?>
    <div class="card description-card mb-4">
        <div class="card-body small" style="line-height: 1.65; color: var(--bs-body-color);"><?= nl2br(htmlspecialchars($manga['description'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Action buttons -->
    <?php include __DIR__ . '/views/manga/action-buttons.php'; ?>

    <!-- Progress summary -->
    <?php include __DIR__ . '/views/manga/progress-summary.php'; ?>

    <!-- Chapter list -->
    <?php include __DIR__ . '/views/manga/chapter-list.php'; ?>

    <!-- Delete modal -->
    <?php include __DIR__ . '/views/partials/delete-manga-modal.php'; ?>

    <!-- New chapter toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
        <div id="newChapterToast" class="toast align-items-center border-0 text-bg-primary" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="newChapterToastText">
                    <i class="bi bi-stars me-1"></i> Ada chapter baru!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
</main>

<?php
// Include shared footer (includes theme.js, toast container, login modal)
include __DIR__ . '/views/layout/footer.php';
?>

<!-- Page-specific JS -->
<script>
    window.MANGA_READER_CONFIG = <?= json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="assets/js/manga.js"></script>
</body>
</html>