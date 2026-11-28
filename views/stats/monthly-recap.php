<?php
/**
 * Stats Monthly Recap
 * Used by: stats.php
 * Variables: $monthlyStats
 */
?>
<?php if (!empty($monthlyStats)): ?>
<!-- Monthly Recap -->
<div class="card mb-4" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
    <div class="card-header bg-transparent border-bottom" style="border-color: var(--bs-border-color);">
        <h5 class="brand-font mb-0"><i class="bi bi-calendar3 text-info me-1"></i> Recap Bulanan (6 Bulan)</h5>
    </div>
    <div class="card-body">
        <?php
        $maxMonthly = max(array_column($monthlyStats, 'chapters'));
        foreach ($monthlyStats as $ms):
            $pct = $maxMonthly > 0 ? round(($ms['chapters'] / $maxMonthly) * 100) : 0;
            $monthLabel = date("M Y", strtotime($ms['month'] . "-01"));
        ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span><?= $monthLabel ?></span>
                    <span class="fw-semibold"><?= $ms['chapters'] ?> ch · <?= $ms['manga_count'] ?> manga</span>
                </div>
                <div style="height: 28px; background: var(--bs-tertiary-bg); border-radius: 6px; overflow: hidden; border: 1px solid var(--bs-border-color);">
                    <div class="monthly-bar" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>