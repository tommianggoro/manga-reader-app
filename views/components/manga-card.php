<?php
/**
 * Manga Card Component (Grid View)
 * Used by: index.php (grid view)
 */
use MangaReader\Helpers\HtmlSanitizer;

$manga = $manga ?? $m;
$mangaId = HtmlSanitizer::escape($manga['manga_id']);
$title = HtmlSanitizer::escape($manga['title']);
$coverUrl = HtmlSanitizer::escape($manga['cover_image_url']);
$latestChapter = HtmlSanitizer::escape(formatChapterNumber($manga['latest_chapter_number']));
$isFavorite = !empty($manga['is_favorite']);
$rating = $manga['rating'] ?? null;
$sources = $sourcesByManga[$mangaId] ?? [];
$sourceLabels = $sourceLabels ?? [];
$mangaTags = $mangaTagsByManga[$mangaId] ?? [];
?>
<div class="col manga-item-col" data-manga-id="<?= $mangaId ?>">
    <div class="card h-100 manga-card">
        <input type="checkbox" class="batch-checkbox" value="<?= $mangaId ?>">
        <a class="text-decoration-none color-inherit flex-grow-1" href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>"
           data-title="<?= HtmlSanitizer::escape(mb_strtolower($title)) ?>"
           data-id="<?= HtmlSanitizer::escape(mb_strtolower($mangaId)) ?>"
           data-author="<?= HtmlSanitizer::escape(mb_strtolower($manga['author'] ?? '')) ?>"
           data-genres="<?= HtmlSanitizer::escape(mb_strtolower($manga['genres'] ?? '')) ?>"
           data-tags="<?= HtmlSanitizer::escape(implode(',', $mangaTags)) ?>"
           data-favorite="<?= $isFavorite ? '1' : '0' ?>">
            <div class="img-wrapper skeleton-shimmer">
                <img src="<?= $coverUrl ?>" class="card-img-top" alt="<?= $title ?>" loading="lazy" onload="this.classList.add('loaded'); this.parentElement.classList.remove('skeleton-shimmer');">
                <button type="button" class="btn position-absolute top-0 end-0 m-2 rounded-circle fav-star <?= $isFavorite ? 'active' : '' ?>"
                        data-manga-id="<?= $mangaId ?>" title="Favoritkan">
                    <i class="bi <?= $isFavorite ? 'bi-star-fill' : 'bi-star' ?>"></i>
                </button>
            </div>
            <div class="card-body p-2 d-flex flex-column justify-content-between">
                <?php if (!empty($sources)): ?>
                    <div class="source-tags mb-1">
                        <?php foreach ($sources as $src): ?>
                            <span class="source-tag"><i class="bi bi-hdd-network me-1"></i><?= HtmlSanitizer::escape($sourceLabels[$src] ?? $src) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="card-title text-truncate mb-1" title="<?= $title ?>"><?= $title ?></div>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="badge text-bg-secondary fw-normal chapter-badge" data-chapter="<?= $latestChapter ?>">Ch. <?= $latestChapter ?></span>
                    <?php if ($rating): ?>
                        <small class="text-warning fw-semibold"><i class="bi bi-star-fill"></i> <?= HtmlSanitizer::escape($rating) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
</div>