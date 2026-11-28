<?php
/**
 * Manga Row Component (List View)
 * Used by: index.php (list view)
 */
use MangaReader\Helpers\HtmlSanitizer;

$manga = $manga ?? $m;
$mangaId = HtmlSanitizer::escape($manga['manga_id']);
$title = HtmlSanitizer::escape($manga['title']);
$coverUrl = HtmlSanitizer::escape($manga['cover_image_url']);
$latestChapter = HtmlSanitizer::escape(formatChapterNumber($manga['latest_chapter_number']));
$isFavorite = !empty($manga['is_favorite']);
$rating = $manga['rating'] ?? null;
$alternativeTitle = $manga['alternative_title'] ?? '';
$author = $manga['author'] ?? '';
$sources = $sourcesByManga[$mangaId] ?? [];
$sourceLabels = $sourceLabels ?? [];
$recentChapters = $recentChaptersByManga[$mangaId] ?? [];
$mangaTags = $mangaTagsByManga[$mangaId] ?? [];
?>
<div class="manga-item-row col-12" data-manga-id="<?= $mangaId ?>">
    <div class="manga-list-item d-flex align-items-center p-2">
        <input type="checkbox" class="batch-checkbox ms-2 me-1" value="<?= $mangaId ?>">
        <a class="text-decoration-none color-inherit d-flex align-items-center flex-grow-1 min-w-0"
           href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>"
           data-title="<?= HtmlSanitizer::escape(mb_strtolower($title)) ?>"
           data-id="<?= HtmlSanitizer::escape(mb_strtolower($mangaId)) ?>"
           data-author="<?= HtmlSanitizer::escape(mb_strtolower($author)) ?>"
           data-genres="<?= HtmlSanitizer::escape(mb_strtolower($manga['genres'] ?? '')) ?>"
           data-tags="<?= HtmlSanitizer::escape(implode(',', $mangaTags)) ?>"
           data-favorite="<?= $isFavorite ? '1' : '0' ?>">
            <div class="list-img-wrap rounded me-3 skeleton-shimmer">
                <img src="<?= $coverUrl ?>" alt="<?= $title ?>" loading="lazy" onload="this.classList.add('loaded'); this.parentElement.classList.remove('skeleton-shimmer');">
            </div>
            <div class="flex-grow-1 min-w-0 me-2">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h2 class="h6 mb-0 text-truncate font-weight-bold brand-font" style="color: var(--bs-body-color)"><?= $title ?></h2>
                    <?php if ($rating): ?>
                        <span class="badge text-bg-warning text-dark"><i class="bi bi-star-fill"></i> <?= HtmlSanitizer::escape($rating) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($alternativeTitle): ?>
                    <div class="small text-secondary text-truncate mb-1"><?= HtmlSanitizer::escape($alternativeTitle) ?></div>
                <?php endif; ?>
                <div class="d-flex flex-wrap align-items-center gap-2 small text-secondary">
                    <span class="badge text-bg-primary chapter-badge" data-chapter="<?= $latestChapter ?>">Ch. <?= $latestChapter ?> Tersimpan</span>
                    <?php if ($author): ?>
                        <span><i class="bi bi-person me-1"></i><?= HtmlSanitizer::escape($author) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($sources)): ?>
                        <span class="source-tags">
                            <?php foreach ($sources as $src): ?>
                                <span class="source-tag"><i class="bi bi-hdd-network me-1"></i><?= HtmlSanitizer::escape($sourceLabels[$src] ?? $src) ?></span>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($recentChapters)): ?>
                    <div class="recent-chapters-row">
                        <?php foreach ($recentChapters as $rc): ?>
                            <span class="recent-chapter-chip">
                                <i class="bi bi-journal-text"></i>
                                Ch. <?= HtmlSanitizer::escape(formatChapterNumber($rc['chapter_number'])) ?>
                                <span class="recent-chapter-date"><?= formatTanggalIndo($rc['updated_at'] ?? $rc['created_at']) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex-shrink-0 ms-auto text-end">
                <button type="button" class="btn rounded-circle fav-star <?= $isFavorite ? 'active' : '' ?>"
                        data-manga-id="<?= $mangaId ?>" title="Favoritkan">
                    <i class="bi <?= $isFavorite ? 'bi-star-fill' : 'bi-star' ?>"></i>
                </button>
            </div>
        </a>
    </div>
</div>