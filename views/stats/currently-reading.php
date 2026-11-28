<?php
/**
 * Stats Currently Reading
 * Used by: stats.php
 * Variables: $currentlyReading
 */
?>
<?php if (!empty($currentlyReading)): ?>
<!-- Currently Reading -->
<div class="card mb-4" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
    <div class="card-header bg-transparent border-bottom" style="border-color: var(--bs-border-color);">
        <h5 class="brand-font mb-0"><i class="bi bi-bookmark-heart-fill text-danger me-1"></i> Sedang Dibaca</h5>
    </div>
    <div class="card-body p-0">
        <?php foreach ($currentlyReading as $m):
            $progress = ($m['latest_chapter_number'] ?? 0) > 0 ? round(($m['last_read_chapter_number'] / $m['latest_chapter_number']) * 100) : 0;
        ?>
            <a href="reader.php?chapter_id=<?= urlencode($m['last_read_chapter_id'] ?? '') ?>" class="text-decoration-none color-inherit manga-stat-item d-flex align-items-center" style="margin: 0.5rem; border-radius: 10px;">
                <img src="<?= htmlspecialchars($m['cover_image_url']) ?>" class="manga-stat-cover" alt="" loading="lazy">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate"><?= htmlspecialchars($m['title']) ?></div>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <div class="flex-grow-1" style="height: 6px; background: var(--bs-border-color); border-radius: 3px; overflow: hidden;">
                            <div style="width: <?= $progress ?>%; height: 100%; background: linear-gradient(90deg, var(--bs-primary), #f7bc70); border-radius: 3px;"></div>
                        </div>
                        <span class="small text-secondary fw-medium"><?= $progress ?>%</span>
                    </div>
                    <div class="small text-secondary">Ch. <?= (float) ($m['last_read_chapter_number'] ?? 0) ?> / Ch. <?= (float) ($m['latest_chapter_number'] ?? 0) ?> · <?= isset($formatTanggalIndo) ? $formatTanggalIndo($m['last_read_at']) : date('d M Y', strtotime($m['last_read_at'])) ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>