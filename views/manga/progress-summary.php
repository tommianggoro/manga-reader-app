<?php
/**
 * Reading Progress Summary Component (Logged-in Users)
 * Used by: manga.php
 */
$readChaptersCount = $readChaptersCount ?? 0;
$totalChaptersInManga = $totalChaptersInManga ?? 0;
$readPercent = $readPercent ?? 0;
?>
<?php if ($userId ?? false): ?>
<!-- Reading Progress Summary -->
<div class="progress-summary mb-3 p-3 rounded-3" style="background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color);">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="progress-circle" style="width: 48px; height: 48px; border-radius: 50%; background: conic-gradient(var(--bs-primary) <?= $readPercent * 3.6 ?>deg, var(--bs-border-color) 0deg); display: flex; align-items: center; justify-content: center;">
                <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--bs-tertiary-bg); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem;"><?= $readPercent ?>%</div>
            </div>
            <div>
                <div class="fw-semibold"><?= $readChaptersCount ?> / <?= $totalChaptersInManga ?> chapter dibaca</div>
                <div class="small text-secondary">Progres baca manga ini</div>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="filterReadBtn" title="Tampilkan hanya chapter sudah dibaca">
                <i class="bi bi-check-circle me-1"></i> Sudah Dibaca
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="filterUnreadBtn" title="Tampilkan hanya chapter belum dibaca">
                <i class="bi bi-circle me-1"></i> Belum Dibaca
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="filterAllBtn" title="Tampilkan semua chapter">
                <i class="bi bi-grid me-1"></i> Semua
            </button>
        </div>
    </div>
</div>
<?php endif; ?>