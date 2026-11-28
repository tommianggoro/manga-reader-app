<?php
/**
 * Chapter List Component
 * Shows: search input, chapter list with read/unread status
 * Used by: manga.php
 */
use MangaReader\Helpers\HtmlSanitizer;

$chapters = $chapters ?? [];
$userId = $userId ?? null;
$manga = $manga ?? [];
$readChapterIds = $readChapterIds ?? [];
$mangaId = HtmlSanitizer::escape($manga['manga_id'] ?? '');
?>
<!-- Chapter Search & List Header -->
<div class="d-flex align-items-center justify-content-between mb-2">
    <h2 class="h5 brand-font mb-0"><i class="bi bi-list-nested me-1"></i> Daftar Chapter</h2>
    <span class="small text-secondary">Diurutkan Terbaru</span>
</div>

<div class="input-group mb-3">
    <span class="input-group-text bg-body-tertiary border-secondary-subtle"><i class="bi bi-search"></i></span>
    <input type="text" id="searchInput" class="form-control" placeholder="Cari chapter (ketik nomor atau judul)...">
</div>

<div class="chapter-list-wrap">
    <div class="list-group list-group-flush" id="chapterList">
        <?php foreach ($chapters as $ch):
            $isCurrent = $userId && !empty($manga['last_read_chapter_id']) && $manga['last_read_chapter_id'] === $ch['chapter_id'];
            $isRead = $userId && isset($readChapterIds[$ch['chapter_id']]);
            $readAt = $isRead ? $readChapterIds[$ch['chapter_id']] : null;
        ?>
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 <?= $isCurrent ? 'current-read' : '' ?> <?= $isRead ? 'chapter-read' : '' ?>"
               href="reader.php?chapter_id=<?= urlencode($ch['chapter_id']) ?>"
               data-chapter-id="<?= HtmlSanitizer::escape($ch['chapter_id']) ?>"
               data-search="<?= HtmlSanitizer::escape(mb_strtolower($ch['chapter_number'] . ' ' . ($ch['chapter_title'] ?? ''))) ?>"
               data-read="<?= $isRead ? '1' : '0' ?>">
                <span class="fw-medium d-flex align-items-center gap-2">
                    <?php if ($isRead): ?>
                        <i class="bi bi-check-circle-fill text-success" title="Dibaca pada <?= formatTanggalIndo($readAt) ?>"></i>
                    <?php else: ?>
                        <i class="bi bi-circle text-secondary" style="opacity: 0.4;" title="Belum dibaca"></i>
                    <?php endif; ?>
                    Chapter <?= (float) $ch['chapter_number'] ?>
                    <?= $ch['chapter_title'] ? " — <span class='text-secondary font-normal'>" . HtmlSanitizer::escape($ch['chapter_title']) . "</span>" : "" ?>
                </span>
                <span class="d-flex align-items-center gap-2 flex-shrink-0">
                    <small class="text-secondary"><?= formatTanggalIndo($ch['updated_at'] ?? $ch['created_at']) ?></small>
                    <?php if ($isCurrent): ?><i class="bi bi-bookmark-fill text-primary current-read-icon" title="Chapter terakhir dibaca"></i><?php endif; ?>
                    <?php if ($isRead): ?><small class="text-success">✓ Dibaca</small><?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="no-result text-center py-4 mb-0" id="noResult" style="display:none">Tidak ada chapter yang cocok.</p>
</div>