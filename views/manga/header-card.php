<?php
/**
 * Manga Header Card Component
 * Shows: cover, title, favorite button, badges, genres, custom tags
 * Used by: manga.php
 */
use MangaReader\Helpers\HtmlSanitizer;

$manga = $manga ?? [];
$mangaId = HtmlSanitizer::escape($manga['manga_id'] ?? '');
$title = HtmlSanitizer::escape($manga['title'] ?? '');
$coverUrl = HtmlSanitizer::escape($manga['cover_image_url'] ?? '');
$alternativeTitle = $manga['alternative_title'] ?? '';
$author = $manga['author'] ?? '';
$artist = $manga['artist'] ?? '';
$releaseYear = $manga['release_year'] ?? '';
$rating = $manga['rating'] ?? '';
$genres = $manga['genres'] ?? '';
$isFavorite = !empty($manga['is_favorite']);
$totalChapters = (int) ($totalChapters ?? 0);
$userId = $userId ?? null;
?>
<!-- Manga Main Header Card -->
<div class="d-flex gap-3 mb-4 flex-column flex-sm-row">
    <div class="cover-img-wrap skeleton-shimmer mx-auto mx-sm-0">
        <img src="<?= $coverUrl ?>" alt="<?= $title ?>" onload="this.classList.add('loaded'); this.parentElement.classList.remove('skeleton-shimmer');">
    </div>
    <div class="flex-grow-1 min-w-0">
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="brand-font h3 mb-0"><?= $title ?></h1>
            <button type="button" class="btn rounded-circle fav-star-btn <?= $isFavorite ? 'active' : '' ?>"
                    id="favBtn" data-manga-id="<?= $mangaId ?>" title="Favoritkan Manga">
                <i class="bi <?= $isFavorite ? 'bi-star-fill' : 'bi-star' ?>"></i>
            </button>
        </div>
        <?php if ($alternativeTitle): ?>
            <p class="text-secondary small mb-2"><?= HtmlSanitizer::escape($alternativeTitle) ?></p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-1 mb-2">
            <?php if ($author): ?><span class="badge rounded-pill text-bg-secondary"><i class="bi bi-person me-1"></i><?= HtmlSanitizer::escape($author) ?></span><?php endif; ?>
            <?php if ($artist): ?><span class="badge rounded-pill text-bg-secondary"><i class="bi bi-palette me-1"></i><?= HtmlSanitizer::escape($artist) ?></span><?php endif; ?>
            <?php if ($releaseYear): ?><span class="badge rounded-pill text-bg-secondary"><i class="bi bi-calendar-event me-1"></i><?= HtmlSanitizer::escape($releaseYear) ?></span><?php endif; ?>
            <?php if ($rating): ?><span class="badge rounded-pill text-bg-warning text-dark"><i class="bi bi-star-fill me-1"></i><?= HtmlSanitizer::escape($rating) ?></span><?php endif; ?>
            <span class="badge rounded-pill text-bg-primary" id="totalChapterBadge"><i class="bi bi-journals me-1"></i><span id="totalChapterCount"><?= $totalChapters ?></span> Chapter</span>
        </div>
        <?php if ($genres): ?>
            <div class="small text-secondary"><i class="bi bi-tags me-1"></i><?= HtmlSanitizer::escape($genres) ?></div>
        <?php endif; ?>
        <!-- User Custom Tags -->
        <?php if ($userId): ?>
        <div class="mt-2">
            <div id="mangaTagsContainer" class="tag-chips-wrap"></div>
            <button type="button" class="tag-add-btn" id="tagAddBtn" title="Tambah tag ke manga ini">
                <i class="bi bi-plus-lg"></i> Tag
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>