<?php
/**
 * Stats Bar Component (Index Page)
 * Shows: total manga, total chapters, favorites, recently updated count
 */
use MangaReader\Helpers\HtmlSanitizer;

$totalManga = $totalManga ?? 0;
$totalChapters = $totalChapters ?? 0;
$totalFavorites = $totalFavorites ?? 0;
$recentlyUpdated = $recentlyUpdated ?? 0;
$userId = $userId ?? null;
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex flex-wrap gap-2">
        <div class="stats-badge"><i class="bi bi-collection-fill text-primary"></i> <span id="statMangaCount"><?= (int) $totalManga ?> Manga</span></div>
        <div class="stats-badge"><i class="bi bi-journals text-info"></i> <span id="statChapterCount"><?= (int) $totalChapters ?> Chapter</span></div>
        <?php if ($userId): ?>
            <div class="stats-badge"><i class="bi bi-star-fill text-warning"></i> <span id="statFavCount"><?= (int) $totalFavorites ?> Favorit</span></div>
        <?php endif; ?>
        <?php if ($recentlyUpdated > 0): ?>
            <div class="stats-badge"><i class="bi bi-fire text-danger"></i> <span>🔥 <?= (int) $recentlyUpdated ?> Update Minggu Ini</span></div>
        <?php endif; ?>
    </div>
    <?php if (isAdmin()): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleManageModeBtn">
            <i class="bi bi-ui-checks me-1"></i> Mode Kelola / Batch Delete
        </button>
    <?php endif; ?>
</div>