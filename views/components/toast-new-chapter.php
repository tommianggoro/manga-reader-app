<?php
/**
 * New Chapter Toast Notification
 * Shows when background sync detects new chapters
 * Used by: index.php
 */
use MangaReader\Helpers\HtmlSanitizer;
?>
<!-- Toast Notifikasi Chapter Baru (hasil sync background/cron) -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="newChapterToast" class="toast align-items-center border-0 text-bg-primary" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="newChapterToastText">
                <i class="bi bi-stars me-1"></i> Ada chapter baru!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>