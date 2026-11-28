<?php
/**
 * Stats Overview Cards
 * Used by: stats.php
 * Variables: $totalChaptersRead, $totalMangaRead, $estimatedHours, $estimatedMins, $weeklyVelocity
 */
?>
<!-- Overview Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-primary-subtle text-primary mx-auto mb-2"><i class="bi bi-journals"></i></div>
            <div class="stat-value"><?= number_format($totalChaptersRead ?? 0) ?></div>
            <div class="stat-label">Chapter Dibaca</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-info-subtle text-info mx-auto mb-2"><i class="bi bi-collection"></i></div>
            <div class="stat-value"><?= number_format($totalMangaRead ?? 0) ?></div>
            <div class="stat-label">Manga Dibaca</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-warning-subtle text-warning mx-auto mb-2"><i class="bi bi-clock"></i></div>
            <div class="stat-value"><?= ($estimatedHours ?? 0) > 0 ? "{$estimatedHours}j {$estimatedMins}m" : "{$estimatedMins}m" ?></div>
            <div class="stat-label">Estimasi Waktu</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-success-subtle text-success mx-auto mb-2"><i class="bi bi-speedometer2"></i></div>
            <div class="stat-value"><?= $weeklyVelocity ?? 0 ?></div>
            <div class="stat-label">Ch/Minggu (8 wk)</div>
        </div>
    </div>
</div>