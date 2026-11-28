<?php
/**
 * Stats Page Content - Composes all stats components
 * Used by: stats.php
 * Variables: $totalChaptersRead, $totalMangaRead, $estimatedHours, $estimatedMins, $weeklyVelocity,
 *            $currentStreak, $longestStreak, $topGenres, $topManga, $currentlyReading,
 *            $dailyReads, $monthlyStats, $yearlyStats, $username, $formatTanggalIndo
 */
?>
<?php if (($totalChaptersRead ?? 0) === 0): ?>
    <?php include __DIR__ . '/empty-state.php'; ?>
<?php else: ?>
    <?php include __DIR__ . '/overview-stats.php'; ?>
    <?php include __DIR__ . '/streak-cards.php'; ?>
    <?php include __DIR__ . '/genre-favorites.php'; ?>
    <?php include __DIR__ . '/top-manga.php'; ?>
    <?php include __DIR__ . '/currently-reading.php'; ?>
    <?php include __DIR__ . '/activity-heatmap.php'; ?>
    <?php include __DIR__ . '/monthly-recap.php'; ?>
    <?php include __DIR__ . '/yearly-stats.php'; ?>
<?php endif; ?>