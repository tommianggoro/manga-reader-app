<?php
/**
 * Filter Chips Component (Index Page)
 * Genre chips, favorite chip, and custom tag filter chips
 */
use MangaReader\Helpers\HtmlSanitizer;

$allGenres = $allGenres ?? [];
$userTags = $userTags ?? [];
$isLoggedIn = $isLoggedIn ?? false;
?>
<!-- Filter Chips -->
<div class="d-flex flex-wrap gap-2 mb-4" id="filterChips">
    <button type="button" class="btn btn-sm btn-outline-secondary chip fav-chip" id="favChip" data-filter="favorite">
        <i class="bi bi-star-fill me-1"></i> Favorit
    </button>
    <?php foreach ($allGenres as $genre): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary chip genre-chip" data-genre="<?= HtmlSanitizer::escape(mb_strtolower($genre)) ?>">
            <?= HtmlSanitizer::escape($genre) ?>
        </button>
    <?php endforeach; ?>
</div>

<?php if (!empty($userTags)): ?>
<!-- Filter by custom tag -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-4" id="tagFilterChips">
    <span class="small text-secondary fw-semibold"><i class="bi bi-tags-fill me-1"></i>Tag:</span>
    <?php foreach ($userTags as $tag): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary chip tag-filter-chip"
                data-tag-id="<?= (int) $tag['id'] ?>"
                style="border-color: <?= HtmlSanitizer::escape($tag['color']) ?>; color: <?= HtmlSanitizer::escape($tag['color']) ?>;">
            <i class="bi bi-tag-fill me-1"></i><?= HtmlSanitizer::escape($tag['name']) ?>
        </button>
    <?php endforeach; ?>
</div>
<?php endif; ?>