<?php
/**
 * Delete Manga Confirmation Modal (Admin Only)
 * Used by: manga.php
 */
use MangaReader\Helpers\HtmlSanitizer;

$manga = $manga ?? [];
?>
<?php if (isAdmin()): ?>
<!-- Modal Konfirmasi Hapus Manga -->
<div class="modal fade" id="deleteMangaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header text-danger">
                <h5 class="modal-title brand-font"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Manga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus manga <strong><?= HtmlSanitizer::escape($manga['title'] ?? '') ?></strong> dari koleksi Anda?
                <p class="text-danger small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i> Tindakan ini akan menghapus seluruh data chapter dan histori bacaan secara permanen.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn"><i class="bi bi-trash3 me-1"></i> Hapus Permanen</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>