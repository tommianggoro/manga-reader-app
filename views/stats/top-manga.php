<?php
/**
 * Stats Top Manga
 * Used by: stats.php
 * Variables: $topManga
 */
?>
<?php if (!empty($topManga)): ?>
<!-- Top Manga -->
<div class="card mb-4" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--bs-border-color);">
        <h5 class="brand-font mb-0"><i class="bi bi-trophy-fill text-warning me-1"></i> Manga Paling Sering Dibaca</h5>
        <small class="text-secondary">Berdasarkan jumlah chapter dibaca</small>
    </div>
    <div class="card-body p-0">
        <?php foreach ($topManga as $i => $m): ?>
            <a href="manga.php?manga_id=<?= urlencode($m['manga_id']) ?>" class="text-decoration-none color-inherit manga-stat-item d-flex align-items-center" style="margin: 0.5rem; border-radius: 10px;">
                <span class="badge text-bg-primary fw-normal" style="font-size: 1rem; min-width: 2.5rem;">#<?= $i + 1 ?></span>
                <img src="<?= htmlspecialchars($m['cover_image_url']) ?>" class="manga-stat-cover" alt="" loading="lazy">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate"><?= htmlspecialchars($m['title']) ?></div>
                    <div class="small text-secondary"><?= number_format($m['chapters_read']) ?> chapter dibaca</div>
                </div>
                <i class="bi bi-chevron-right text-secondary"></i>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>