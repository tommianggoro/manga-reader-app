<?php
/**
 * Reader Top Navigation Bar
 * Used by: reader.php
 */
use MangaReader\Helpers\HtmlSanitizer;

$manga = $manga ?? [];
$chapter = $chapter ?? [];
$mangaId = HtmlSanitizer::escape($manga['manga_id'] ?? '');
$mangaTitle = HtmlSanitizer::escape($manga['title'] ?? '');
$chapterNumber = HtmlSanitizer::escape(formatChapterNumber($chapter['chapter_number'] ?? 0));
$userId = $userId ?? null;
?>
<!-- Top Navigation Bar -->
<nav class="topbar sticky-top d-flex justify-content-between align-items-center px-3 py-2">
    <a class="d-inline-flex align-items-center gap-2 text-decoration-none fw-medium text-truncate" href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>" style="max-width: 65%;">
        <i class="bi bi-arrow-left"></i> <span class="text-truncate"><?= $mangaTitle ?></span>
    </a>
    <div class="d-flex align-items-center gap-2">
        <span class="badge text-bg-primary">Ch. <?= $chapterNumber ?></span>
        <button type="button" class="theme-toggle-btn" data-bs-toggle="modal" data-bs-target="#readerSettingsModal" title="Pengaturan Tampilan Pembaca">
            <i class="bi bi-gear-fill text-warning"></i>
        </button>
        <button type="button" class="theme-toggle-btn" id="themeToggle" title="Ganti Mode Gelap/Terang">
            <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
        </button>
        <?php if ($userId): ?>
            <a href="discover.php" class="btn btn-outline-secondary btn-sm" title="Temukan Manga Baru">
                <i class="bi bi-compass"></i> <span class="d-none d-sm-inline">Temukan</span>
            </a>
            <a href="stats.php" class="btn btn-outline-secondary btn-sm" title="Statistik Bacaan">
                <i class="bi bi-graph-up-arrow"></i> <span class="d-none d-sm-inline">Statistik</span>
            </a>
            <button type="button" class="theme-toggle-btn" onclick="logoutUser()" title="Keluar (<?= HtmlSanitizer::escape(currentUsername()) ?>)">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        <?php else: ?>
            <a href="discover.php" class="btn btn-outline-secondary btn-sm" title="Temukan Manga Baru">
                <i class="bi bi-compass"></i> <span class="d-none d-sm-inline">Temukan</span>
            </a>
            <button type="button" class="theme-toggle-btn" onclick="showLoginRequiredModal('Login supaya progres bacamu tersimpan ke akun.')" title="Login">
                <i class="bi bi-box-arrow-in-right"></i>
            </button>
        <?php endif; ?>
    </div>
</nav>