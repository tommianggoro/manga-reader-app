<?php
/**
 * Floating Batch Action Bar (Admin Only)
 * Used by: index.php
 */
use MangaReader\Helpers\HtmlSanitizer;
?>
<?php if (isAdmin()): ?>
<!-- Floating Action Bar Mode Kelola / Batch Delete -->
<div class="batch-action-bar" id="batchBar">
    <span class="small fw-semibold me-2" id="selectedCountText">0 Terpilih</span>
    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="selectAllBtn">Pilih Semua</button>
    <button type="button" class="btn btn-sm btn-danger rounded-pill px-3" id="batchDeleteBtn" disabled>
        <i class="bi bi-trash3-fill me-1"></i> Hapus Terpilih
    </button>
    <button type="button" class="btn btn-sm btn-close ms-1" id="cancelManageBtn" title="Batal Mode Kelola"></button>
</div>
<?php endif; ?>