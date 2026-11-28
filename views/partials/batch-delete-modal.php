<?php
/**
 * Batch Delete Confirmation Modal (Admin Only)
 * Used by: index.php
 */
use MangaReader\Helpers\HtmlSanitizer;
?>
<?php if (isAdmin()): ?>
<!-- Modal Konfirmasi Batch Delete -->
<div class="modal fade" id="confirmBatchDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header text-danger">
                <h5 class="modal-title brand-font"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Massal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus secara permanen <strong id="deleteCountModalText">0</strong> manga yang dipilih beserta seluruh chapter dan riwayatnya?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="executeBatchDeleteBtn"><i class="bi bi-trash3 me-1"></i> Ya, Hapus Sekarang</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>