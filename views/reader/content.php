<?php
/**
 * Reader Page Content - Composes all reader components
 * Used by: reader.php
 */
use MangaReader\Helpers\HtmlSanitizer;

// Reading Progress Bar
?>
<div class="reading-progress-bar" id="progressBar"></div>

<?php
// Top Navigation Bar
$manga = $manga ?? [];
$chapter = $chapter ?? [];
$userId = $userId ?? null;
include __DIR__ . '/topbar.php';
?>

<?php
// Single Page Controls
$totalPages = $totalPages ?? count($images ?? []);
include __DIR__ . '/single-page-controls.php';
?>

<?php
// Guest Notice
include __DIR__ . '/guest-notice.php';
?>

<?php
// Reader Container
$images = $images ?? [];
$prevCh = $prevCh ?? null;
$nextCh = $nextCh ?? null;
include __DIR__ . '/reader-container.php';
?>

<?php
// Floating Navigation
$allChapters = $allChapters ?? [];
include __DIR__ . '/floating-nav.php';
?>

<?php
// Tap Zones
include __DIR__ . '/tap-zones.php';
?>

<?php
// Reader Settings Modal
include __DIR__ . '/settings-modal.php';
?>

<?php
// Shortcut Toast
include __DIR__ . '/shortcut-toast.php';
?>

<?php
// Login Modal (shared)
include __DIR__ . '/../partials/login_modal.php';
?>