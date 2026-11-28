<?php
/**
 * Manga Reader - Collection Page (Thin Controller)
 * Displays user's manga collection with grid/list view, filters, search
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

// Start session for auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require authentication
$userId = requireAuth();
$isAdminUser = isAdmin();

// Fetch all manga with latest chapter, rating, sources, genres, tags, favorites
$stmt = $pdo->prepare("
    SELECT m.manga_id, m.title, m.alternative_title, m.author, m.cover_image_url,
           m.genres, m.rating, m.created_at, m.updated_at,
           COALESCE((
               SELECT MAX(c.chapter_number)
               FROM chapters c
               WHERE c.manga_id = m.manga_id
           ), 0) AS latest_chapter_number,
           EXISTS(
               SELECT 1 FROM favorites f WHERE f.user_id = ? AND f.manga_id = m.manga_id
           ) AS is_favorite
    FROM manga m
    ORDER BY m.updated_at DESC
");
$stmt->execute([$userId]);
$mangas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sources per manga
$stmt = $pdo->prepare("
    SELECT manga_id, source FROM manga_sources
");
$stmt->execute();
$sourcesByManga = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $sourcesByManga[$row['manga_id']][] = $row['source'];
}

// Source labels
$sourceLabels = [
    'shinigami' => 'Shinigami',
    'komiku' => 'Komiku',
    'cosmic' => 'CosmicScans',
    'asura' => 'AsuraScans',
];

// Get all unique genres
$stmt = $pdo->query("SELECT DISTINCT genres FROM manga WHERE genres IS NOT NULL AND genres != ''");
$allGenres = [];
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $genresStr) {
    foreach (array_map('trim', explode(',', $genresStr)) as $genre) {
        if ($genre) $allGenres[$genre] = true;
    }
}
$allGenres = array_keys($allGenres);
sort($allGenres);

// Get user's custom tags
$userTags = [];
if ($userId) {
    $stmt = $pdo->prepare("SELECT id, name, color FROM user_tags WHERE user_id = ? ORDER BY name");
    $stmt->execute([$userId]);
    $userTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get manga-user tags mapping
$mangaTagsByManga = [];
if ($userId && !empty($userTags)) {
    $tagIds = array_column($userTags, 'id');
    $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
    $stmt = $pdo->prepare("
        SELECT mut.manga_id, mut.tag_id
        FROM manga_user_tags mut
        WHERE mut.tag_id IN ($placeholders)
    ");
    $stmt->execute($tagIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mangaTagsByManga[$row['manga_id']][] = (string) $row['tag_id'];
    }
}

// Get recent chapters per manga (last 3)
$recentChaptersByManga = [];
if (!empty($mangas)) {
    $mangaIds = array_column($mangas, 'manga_id');
    $placeholders = implode(',', array_fill(0, count($mangaIds), '?'));
    $stmt = $pdo->prepare("
        SELECT c.manga_id, c.chapter_number, c.updated_at, c.created_at
        FROM chapters c
        WHERE c.manga_id IN ($placeholders)
        ORDER BY c.manga_id, c.chapter_number DESC
    ");
    $stmt->execute($mangaIds);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $countPerManga = [];
    foreach ($chapters as $ch) {
        $mid = $ch['manga_id'];
        if (!isset($countPerManga[$mid])) $countPerManga[$mid] = 0;
        if ($countPerManga[$mid] < 3) {
            $recentChaptersByManga[$mid][] = $ch;
            $countPerManga[$mid]++;
        }
    }
}

// Stats
$totalManga = count($mangas);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM chapters WHERE manga_id IN (" . implode(',', array_fill(0, $totalManga, '?')) . ")");
$stmt->execute($mangaIds ?: [0]);
$totalChapters = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
$stmt->execute([$userId]);
$totalFavorites = (int) $stmt->fetchColumn();

// Recently updated this week
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT m.manga_id)
    FROM manga m
    JOIN chapters c ON c.manga_id = m.manga_id
    WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute();
$recentlyUpdated = (int) $stmt->fetchColumn();

// Import flash messages
$importSuccess = !empty($_SESSION['import_success']);
$importedCount = (int) ($_SESSION['imported_count'] ?? 0);
$importError = $_SESSION['import_error'] ?? '';
unset($_SESSION['import_success'], $_SESSION['imported_count'], $_SESSION['import_error']);

// Pass config to JavaScript
$jsConfig = [
    'isLoggedIn' => (bool) $userId,
    'isAdmin' => $isAdminUser,
];
?>

<?php
// Page variables for views
$pageTitle = 'Koleksi Manga Pribadi';
$backUrl = null; // Home page has no back link

// Include shared header
include __DIR__ . '/views/layout/header.php';
?>

<main id="main-content" class="container py-4" style="max-width: 1000px;">
    <?php
    // Pass variables to content view
    $mangas = $mangas;
    $sourcesByManga = $sourcesByManga;
    $sourceLabels = $sourceLabels;
    $mangaTagsByManga = $mangaTagsByManga;
    $recentChaptersByManga = $recentChaptersByManga;
    $allGenres = $allGenres;
    $userTags = $userTags;
    $totalManga = $totalManga;
    $totalChapters = $totalChapters;
    $totalFavorites = $totalFavorites;
    $recentlyUpdated = $recentlyUpdated;
    $importSuccess = $importSuccess;
    $importedCount = $importedCount;
    $importError = $importError;
    $isAdmin = $isAdminUser;
    $userId = $userId;

    include __DIR__ . '/views/index/content.php';
    ?>
</main>

<?php
// Include shared footer (includes theme.js, toast container, login modal)
include __DIR__ . '/views/layout/footer.php';
?>

<!-- Page-specific JS -->
<script>
    window.MANGA_READER_CONFIG = <?= json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="assets/js/index.js"></script>
</body>
</html>