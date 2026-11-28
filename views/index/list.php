<?php
/**
 * List View Container (Index Page)
 * Renders all manga as detailed rows
 */
use MangaReader\Helpers\HtmlSanitizer;

$mangas = $mangas ?? [];
$sourcesByManga = $sourcesByManga ?? [];
$sourceLabels = $sourceLabels ?? [];
$recentChaptersByManga = $recentChaptersByManga ?? [];
$mangaTagsByManga = $mangaTagsByManga ?? [];
?>
<!-- List View Container (Hidden by default) -->
<div class="d-flex flex-column gap-2" id="mangaList" style="display: none !important;">
    <?php foreach ($mangas as $m): ?>
        <?php
        $manga = $m;
        include __DIR__ . '/../components/manga-row.php';
        ?>
    <?php endforeach; ?>
</div>