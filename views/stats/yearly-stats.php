<?php
/**
 * Stats Yearly Stats
 * Used by: stats.php
 * Variables: $yearlyStats
 */
?>
<?php if (!empty($yearlyStats)): ?>
<!-- Yearly Stats -->
<div class="card" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
    <div class="card-header bg-transparent border-bottom" style="border-color: var(--bs-border-color);">
        <h5 class="brand-font mb-0"><i class="bi bi-award-fill text-warning me-1"></i> Recap Tahunan</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($yearlyStats as $ys): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="stat-card text-center" style="padding: 1rem;">
                        <div class="stat-value" style="font-size: 2rem;"><?= $ys['chapters'] ?></div>
                        <div class="stat-label"><?= $ys['year'] ?></div>
                        <div class="small text-secondary mt-1"><?= $ys['manga_count'] ?> manga</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>