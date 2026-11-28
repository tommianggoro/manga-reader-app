<?php
/**
 * Reader Settings Modal
 * Used by: reader.php
 */
?>
<!-- Modal Pengaturan Reader -->
<div class="modal fade" id="readerSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title brand-font"><i class="bi bi-sliders me-2 text-warning"></i>Pengaturan Reader</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Lebar Gambar Reader</label>
                    <select class="form-select form-select-sm" id="settingWidthSelect">
                        <option value="600px">Compact (600px)</option>
                        <option value="800px">Standard (800px)</option>
                        <option value="100%">Full Width (100%)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-semibold">Mode Tampilan Layout</label>
                    <select class="form-select form-select-sm" id="settingLayoutSelect">
                        <option value="webtoon">Webtoon (Scroll Vertikal)</option>
                        <option value="single_page">Single Page (Per Halaman)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>