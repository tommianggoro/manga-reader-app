<?php
/**
 * Backup & Restore Modal (Admin Only)
 * Used by: index.php
 */
use MangaReader\Helpers\HtmlSanitizer;
?>
<?php if (isAdmin()): ?>
<!-- Modal Backup & Restore -->
<div class="modal fade" id="backupModal" tabindex="-1" aria-labelledby="backupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title brand-font" id="backupModalLabel"><i class="bi bi-database-gear me-2 text-primary"></i>Backup & Restore Koleksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-download me-1 text-success"></i> 1. Ekspor Koleksi (Backup)</h6>
                    <p class="small text-secondary mb-2">Unduh seluruh berkas cadangan koleksi manga, chapter, dan histori bacaan Anda dalam format JSON.</p>
                    <a href="export.php" class="btn btn-outline-success btn-sm w-100 fw-semibold">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh File JSON Backup
                    </a>
                </div>
                <hr>
                <div>
                    <h6 class="fw-bold mb-1"><i class="bi bi-upload me-1 text-primary"></i> 2. Impor Koleksi (Restore)</h6>
                    <p class="small text-secondary mb-2">Unggah berkas JSON cadangan untuk memulihkan koleksi manga ke database.</p>
                    <form action="import.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <input class="form-control form-control-sm" type="file" name="backup_file" accept=".json" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                            <i class="bi bi-file-earmark-arrow-up me-1"></i> Mulai Impor Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>