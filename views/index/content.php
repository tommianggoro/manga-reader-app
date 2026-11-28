<?php
/**
 * Main Index Page Content
 * Composes all index view components
 * Used by: index.php (thin controller)
 */
use MangaReader\Helpers\HtmlSanitizer;

$pageTitle = $pageTitle ?? 'Koleksi Manga Pribadi';
$backUrl = null; // Home page has no back link
$userId = $userId ?? null;
$isAdmin = $isAdmin ?? false;
$importSuccess = $importSuccess ?? false;
$importedCount = $importedCount ?? 0;
$importError = $importError ?? '';
?>
<?php if ($importSuccess): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i> Berhasil mengimpor data! Total <strong><?= (int) $importedCount ?></strong> manga berhasil dipulihkan.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($importError): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> Gagal mengimpor backup: <?= HtmlSanitizer::escape($importError) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header Section -->
<div class="app-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h1 class="brand-font"><i class="bi bi-book-half text-primary"></i> Koleksi Manga Pribadi</h1>
    <div class="d-flex align-items-center gap-2 header-actions">
        <?php if ($isAdmin): ?>
            <a href="crawl_all.php" target="_blank" class="btn btn-outline-warning btn-sm fw-semibold" title="Sinkronisasi seluruh manga di koleksi">
                <i class="bi bi-arrow-repeat me-1"></i>
                <span class="d-none d-sm-inline">🔥 Cek Update Semua Manga</span>
                <span class="d-inline d-sm-none">Sync</span>
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#backupModal" title="Ekspor/Impor JSON Backup">
                <i class="bi bi-database-gear me-1"></i> <span class="d-none d-sm-inline">Backup</span>
            </button>
            <a href="manage_admin.php" class="btn btn-outline-secondary btn-sm" title="Kelola Admin">
                <i class="bi bi-people-fill me-1"></i> <span class="d-none d-sm-inline">Kelola Admin</span>
            </a>
        <?php endif; ?>
        <button type="button" class="theme-toggle-btn ms-1" id="themeToggle" title="Ganti Mode Gelap/Terang">
            <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
        </button>
        <?php if ($userId): ?>
            <a href="discover.php" class="btn btn-outline-secondary btn-sm" title="Temukan Manga Baru">
                <i class="bi bi-compass"></i> <span class="d-none d-sm-inline">Temukan</span>
            </a>
            <a href="stats.php" class="btn btn-outline-secondary btn-sm" title="Statistik Bacaan">
                <i class="bi bi-graph-up-arrow"></i> <span class="d-none d-sm-inline">Statistik</span>
            </a>
            <a href="profile.php" class="theme-toggle-btn user-avatar-btn p-0 overflow-hidden" title="Profil (<?= HtmlSanitizer::escape(currentUsername()) ?>)">
                <img src="<?= HtmlSanitizer::escapeAttr(currentUserAvatarUrl()) ?>" alt="Profil">
            </a>
            <button type="button" class="theme-toggle-btn" onclick="logoutUser()" title="Keluar (<?= HtmlSanitizer::escape(currentUsername()) ?>)">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        <?php else: ?>
            <a href="discover.php" class="btn btn-outline-secondary btn-sm" title="Temukan Manga Baru">
                <i class="bi bi-compass"></i> <span class="d-none d-sm-inline">Temukan</span>
            </a>
            <button type="button" class="btn btn-primary btn-sm fw-semibold" onclick="showLoginRequiredModal('Masuk untuk mengelola bookmark & progres bacamu.')">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </button>
        <?php endif; ?>
    </div>
</div>

<?php
// Stats bar
$totalManga = $totalManga ?? 0;
$totalChapters = $totalChapters ?? 0;
$totalFavorites = $totalFavorites ?? 0;
$recentlyUpdated = $recentlyUpdated ?? 0;
include __DIR__ . '/stats-bar.php';
?>

<?php if ($isAdmin): ?>
<div class="d-flex gap-2 mb-3">
    <a href="search_manga.php" class="btn btn-primary btn-lg fs-6 flex-grow-1">
        <i class="bi bi-search-heart me-1"></i> Cari & Tambah Manga
    </a>
    <button type="button" class="btn btn-outline-secondary btn-lg" data-bs-toggle="collapse" data-bs-target="#manualAddCollapse" title="Tambah manual via link/manga_id">
        <i class="bi bi-link-45deg"></i>
    </button>
</div>
<div class="collapse mb-3" id="manualAddCollapse">
    <form class="row g-2 add-form" action="add_manga.php" method="GET" id="addMangaForm">
        <div class="col-12 col-sm">
            <input type="text" name="manga_id" id="mangaIdInput" class="form-control" placeholder="Tempel manga_id Shinigami ATAU URL Komiku/CosmicScans/AsuraScans..." required>
        </div>
        <div class="col-12 col-sm-auto">
            <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-plus-lg"></i> Tambah Manual</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Search & View Mode Switcher -->
<div class="row g-2 mb-3">
    <div class="col">
        <div class="input-group">
            <span class="input-group-text bg-body-tertiary border-secondary-subtle"><i class="bi bi-search"></i></span>
            <input type="text" id="searchInput" class="form-control" placeholder="Cari manga berdasarkan judul, id, pengarang, atau genre...">
            <button type="button" class="btn btn-outline-secondary" id="clearSearchBtn" style="display:none;" title="Bersihkan Pencarian">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
    <div class="col-auto">
        <div class="btn-group" role="group" aria-label="Modus Tampilan">
            <button type="button" class="btn btn-outline-secondary active" id="viewGridBtn" title="Tampilan Grid">
                <i class="bi bi-grid-fill"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="viewListBtn" title="Tampilan List Detail">
                <i class="bi bi-list-task"></i>
            </button>
        </div>
    </div>
</div>

<?php
// Filter chips
$allGenres = $allGenres ?? [];
$userTags = $userTags ?? [];
include __DIR__ . '/filter-chips.php';
?>

<?php
// Grid view
$mangas = $mangas ?? [];
$sourcesByManga = $sourcesByManga ?? [];
$sourceLabels = $sourceLabels ?? [];
$mangaTagsByManga = $mangaTagsByManga ?? [];
include __DIR__ . '/grid.php';
?>

<?php
// List view
$recentChaptersByManga = $recentChaptersByManga ?? [];
include __DIR__ . '/list.php';
?>

<?php
// Empty states
include __DIR__ . '/../components/empty-state.php';
?>

<?php
// Admin modals and batch action bar
include __DIR__ . '/../partials/backup-modal.php';
include __DIR__ . '/../partials/batch-delete-modal.php';
include __DIR__ . '/../partials/batch-action-bar.php';
?>

<?php
// New chapter toast
include __DIR__ . '/../components/toast-new-chapter.php';
?>