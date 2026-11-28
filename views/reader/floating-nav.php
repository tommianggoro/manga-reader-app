<?php
/**
 * Floating Navigation Control
 * Used by: reader.php
 */
use MangaReader\Helpers\HtmlSanitizer;

$manga = $manga ?? [];
$prevCh = $prevCh ?? null;
$nextCh = $nextCh ?? null;
$allChapters = $allChapters ?? [];
$chapter = $chapter ?? [];
?>
<!-- Floating Navigation Control -->
<div class="floating-nav">
    <a href="index.php" class="btn" title="Beranda (Koleksi)"><i class="bi bi-house-fill"></i></a>
    <a href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>" class="btn" title="Detail Manga"><i class="bi bi-book-fill"></i></a>

    <button type="button" class="btn" onclick="goPrev()" title="Chapter Sebelumnya (Panah Kiri / A)" <?= $prevCh ? '' : 'disabled' ?>>
        <i class="bi bi-chevron-left"></i>
    </button>

    <select id="jumpSelect" title="Loncat ke chapter">
        <?php foreach ($allChapters as $c): ?>
            <option value="<?= urlencode($c['chapter_id']) ?>" <?= $c['chapter_id'] === $chapter['chapter_id'] ? 'selected' : '' ?>>
                Ch. <?= (int) $c['chapter_number'] ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="button" class="btn" onclick="goNext()" title="Chapter Selanjutnya (Panah Kanan / D)" <?= $nextCh ? '' : 'disabled' ?>>
        <i class="bi bi-chevron-right"></i>
    </button>

    <button type="button" class="btn text-warning ms-1" id="shortcutHelpBtn" title="Shortcut Keyboard (?)">
        <i class="bi bi-keyboard"></i>
    </button>
</div>