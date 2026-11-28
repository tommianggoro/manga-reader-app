<?php
/**
 * Stats Activity Heatmap (30 days)
 * Used by: stats.php
 * Variables: $dailyReads
 */
?>
<!-- Activity Heatmap (30 hari) -->
<div class="card mb-4" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--bs-border-color);">
        <h5 class="brand-font mb-0"><i class="bi bi-calendar-heart-fill text-danger me-1"></i> Aktivitas 30 Hari Terakhir</h5>
        <div class="d-flex align-items-center gap-2 small text-secondary">
            <span><i class="bi bi-square" style="color: var(--bs-border-color)"></i> 0</span>
            <span><i class="bi bi-square-fill" style="color: #3fb950"></i> 4+</span>
        </div>
    </div>
    <div class="card-body">
        <div class="heatmap" id="heatmap">
            <?php
            // Generate 30 hari grid
            $startDate = new DateTime("-29 days");
            $endDate = new DateTime("today");
            $days = [];
            while ($startDate <= $endDate) {
                $key = $startDate->format("Y-m-d");
                $chapters = $dailyReads[$key] ?? 0;
                $level = 0;
                if ($chapters > 0) $level = 1;
                if ($chapters >= 2) $level = 2;
                if ($chapters >= 3) $level = 3;
                if ($chapters >= 4) $level = 4;
                $days[] = ['date' => $key, 'level' => $level, 'chapters' => $chapters];
                $startDate->modify("+1 day");
            }
            foreach ($days as $i => $day):
            ?>
                <div class="heatmap-day level-<?= $day['level'] ?>"
                     title="<?= date("d M Y", strtotime($day['date'])) ?>: <?= $day['chapters'] ?> chapter"
                     data-bs-toggle="tooltip" data-bs-placement="top"></div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex justify-content-between small text-secondary mt-2 px-1">
            <span>Lebih lama</span>
            <span>Hari ini</span>
        </div>
    </div>
</div>