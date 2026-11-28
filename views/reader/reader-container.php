<?php
/**
 * Reader Container - Chapter Images & End Chapter Card
 * Used by: reader.php
 */
use MangaReader\Helpers\HtmlSanitizer;

$chapter = $chapter ?? [];
$images = $images ?? [];
$prevCh = $prevCh ?? null;
$nextCh = $nextCh ?? null;
$manga = $manga ?? [];
?>
<!-- Reader Container -->
<div class="reader d-flex flex-column align-items-center" id="readerContainer">
    <?php foreach ($images as $idx => $img): ?>
        <div class="page-wrapper skeleton-shimmer <?= $idx === 0 ? 'active-page' : '' ?>" data-page-number="<?= $idx + 1 ?>">
            <img src="<?= HtmlSanitizer::escape($chapter['base_url'] . $chapter['image_path'] . $img['filename']) ?>"
                 alt="Halaman <?= (int) $img['page_number'] ?>"
                 loading="lazy"
                 onload="this.classList.add('loaded'); this.parentElement.classList.remove('skeleton-shimmer'); this.parentElement.style.minHeight = 'auto';"
                 onerror="handleImageError(this, <?= (int) $img['page_number'] ?>, <?= json_encode($chapter['base_url'] . $chapter['image_path'] . $img['filename']) ?>)">
        </div>
    <?php endforeach; ?>

    <!-- End Chapter Card -->
    <div class="end-chapter-card">
        <div class="mb-2 text-warning fs-3"><i class="bi bi-check-circle-fill"></i></div>
        <h3 class="h5 brand-font mb-2">Anda Telah Selesai Membaca Chapter <?= HtmlSanitizer::escape(formatChapterNumber($chapter['chapter_number'] ?? 0)) ?></h3>
        <p class="text-secondary small mb-3">Lanjutkan petualangan ke chapter selanjutnya atau kembali ke daftar chapter.</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <?php if ($prevCh): ?>
                <a href="reader.php?chapter_id=<?= urlencode($prevCh['chapter_id']) ?>" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-chevron-left me-1"></i> Ch. <?= HtmlSanitizer::escape(formatChapterNumber($prevCh['chapter_number'])) ?>
                </a>
            <?php endif; ?>
            <a href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>" class="btn btn-outline-secondary btn-sm px-3">
                <i class="bi bi-list me-1"></i> Daftar Chapter
            </a>
            <?php if ($nextCh): ?>
                <a href="reader.php?chapter_id=<?= urlencode($nextCh['chapter_id']) ?>" class="btn btn-primary btn-sm px-3 fw-semibold">
                    Ch. <?= HtmlSanitizer::escape(formatChapterNumber($nextCh['chapter_number'])) ?> Selanjutnya <i class="bi bi-chevron-right ms-1"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>