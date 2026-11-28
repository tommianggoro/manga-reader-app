<?php
/**
 * Stats Streak Cards
 * Used by: stats.php
 * Variables: $currentStreak, $longestStreak
 */
?>
<!-- Streak Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="streak-card">
            <div class="streak-value"><?= $currentStreak ?? 0 ?></div>
            <div class="text-secondary mt-1">Hari Berturut-turut</div>
            <div class="small text-secondary mt-2">Streak saat ini<?= ($currentStreak ?? 0) > 0 ? " (mulai " . date("d M", strtotime("-" . (($currentStreak ?? 1) - 1) . " days")) . ")" : "" ?></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="streak-card" style="border-color: var(--bs-warning);">
            <div class="streak-value" style="color: var(--bs-warning);"><?= $longestStreak ?? 0 ?></div>
            <div class="text-secondary mt-1">Rekor Terpanjang</div>
            <div class="small text-secondary mt-2">Panjang streak tertinggi sepanjang masa</div>
        </div>
    </div>
</div>