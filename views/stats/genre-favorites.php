<?php
/**
 * Stats Genre Favorites
 * Used by: stats.php
 * Variables: $topGenres
 */
?>
<?php if (!empty($topGenres)): ?>
<!-- Genre Favorit -->
<div class="card mb-4" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
    <div class="card-header bg-transparent border-bottom" style="border-color: var(--bs-border-color);">
        <h5 class="brand-font mb-0"><i class="bi bi-tags text-primary me-1"></i> Genre Favorit</h5>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <?php
            $maxCount = max($topGenres);
            foreach ($topGenres as $genre => $count):
                $pct = $maxCount > 0 ? round(($count / $maxCount) * 100) : 0;
            ?>
                <div class="genre-tag">
                    <span><?= htmlspecialchars($genre) ?></span>
                    <span class="badge text-bg-secondary"><?= $count ?></span>
                    <div class="bar"><div class="bar-fill" style="width: <?= $pct ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>