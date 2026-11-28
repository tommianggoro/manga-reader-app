<?php
/**
 * Manga Action Buttons Component
 * Shows: Resume/Start reading, Update/Sync, Preferred Source selector (admin)
 * Used by: manga.php
 */
use MangaReader\Helpers\HtmlSanitizer;

$firstChapter = $firstChapter ?? null;
$userId = $userId ?? null;
$manga = $manga ?? [];
$mangaId = HtmlSanitizer::escape($manga['manga_id'] ?? '');
$mangaSourceBindings = $mangaSourceBindings ?? [];
$sourceLabelsMap = $sourceLabelsMap ?? [];
$isAdmin = $isAdmin ?? false;
$preferredSource = $manga['preferred_source'] ?? '';
?>
<!-- Action Buttons (Resume/Start/Update) -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-4 p-3 rounded-3 action-buttons-row" style="background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color);" id="actionButtonsRow">
    <?php if ($firstChapter): ?>
        <?php if ($userId && !empty($manga['last_read_chapter_id'])): ?>
            <a class="btn btn-primary fw-semibold px-3" id="resumeBtn" href="reader.php?chapter_id=<?= urlencode($manga['last_read_chapter_id']) ?>">
                <i class="bi bi-play-fill fs-5 me-1"></i> <span id="resumeBtnText">Lanjutkan Chapter <?= (float) $manga['last_read_chapter_number'] ?></span>
            </a>
            <a class="btn btn-outline-secondary px-3" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                <i class="bi bi-book me-1"></i> Baca dari Awal
            </a>
        <?php else: ?>
            <!-- Guest (atau user login tanpa histori DB): tombol default "mulai dari awal",
                 akan DIGANTI oleh JS jadi "Lanjutkan" kalau localStorage punya histori utk manga ini. -->
            <a class="btn btn-primary fw-semibold px-4" id="resumeBtn" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                <i class="bi bi-book me-1"></i> <span id="resumeBtnText">Mulai Baca dari Awal</span>
            </a>
            <a class="btn btn-outline-secondary px-3 d-none" id="startOverBtn" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                <i class="bi bi-book me-1"></i> Baca dari Awal
            </a>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($isAdmin && count($mangaSourceBindings) > 1): ?>
    <select class="form-select form-select-sm ms-auto" style="max-width: 220px;" id="preferredSourceSelect">
        <option value="">Sumber Prioritas: Otomatis</option>
        <?php foreach ($mangaSourceBindings as $b): ?>
            <option value="<?= HtmlSanitizer::escape($b['source']) ?>" <?= $preferredSource === $b['source'] ? 'selected' : '' ?>>
                Prioritas: <?= HtmlSanitizer::escape($sourceLabelsMap[$b['source']] ?? $b['source']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <a class="btn btn-outline-warning <?= count($mangaSourceBindings) > 1 ? '' : 'ms-auto' ?>" href="crawl.php?manga_id=<?= urlencode($manga['manga_id']) ?>" target="_blank" title="Cek & Sync Chapter Baru">
        <i class="bi bi-arrow-repeat me-1"></i> Update / Sync Chapter (<?= count($mangaSourceBindings) ?> sumber)
    </a>
    <?php endif; ?>
</div>