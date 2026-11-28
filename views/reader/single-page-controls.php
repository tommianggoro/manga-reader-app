<?php
/**
 * Single Page Controls (Top & Bottom)
 * Used by: reader.php
 */
$totalPages = $totalPages ?? count($images ?? []);
?>
<!-- Single Page Controls Top Bar -->
<div class="single-page-controls" id="singlePageControlsTop">
    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="prevSinglePage()">
        <i class="bi bi-chevron-left"></i><span class="d-none d-sm-inline ms-1">Halaman Sebelumnya</span>
    </button>
    <span class="fw-semibold small" id="pageIndicatorText">Halaman 1 / <?= $totalPages ?></span>
    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="nextSinglePage()">
        <span class="d-none d-sm-inline me-1">Halaman Selanjutnya</span><i class="bi bi-chevron-right"></i>
    </button>
</div>