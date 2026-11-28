<?php
/**
 * Grid View Container (Index Page)
 * Renders all manga as cards in a responsive grid
 */
use MangaReader\Helpers\HtmlSanitizer;

$mangas = $mangas ?? [];
$sourcesByManga = $sourcesByManga ?? [];
$sourceLabels = $sourceLabels ?? [];
$mangaTagsByManga = $mangaTagsByManga ?? [];
?>
<!-- Grid View Container -->
<div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3" id="mangaGrid">
    <?php foreach ($mangas as $m): ?>
        <?php
        $manga = $m;
        include __DIR__ . '/../components/manga-card.php';
        ?>
    <?php endforeach; ?>
</div>