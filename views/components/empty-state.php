<?php
/**
 * Empty State Components
 * Used by: index.php (no manga in database, no search results)
 */
use MangaReader\Helpers\HtmlSanitizer;

$isAdmin = $isAdmin ?? false;
?>
<!-- Empty State: Belum ada manga di database -->
<?php if (empty($mangas)): ?>
<div class="empty-state-card my-4" id="emptyDatabaseState">
    <div class="empty-icon-box"><i class="bi bi-journal-plus"></i></div>
    <h3 class="h4 brand-font mb-2">Perpustakaan Manga Masih Kosong</h3>
    <p class="text-secondary mb-4 mx-auto" style="max-width: 480px;">
        <?php if ($isAdmin): ?>
            Mulai bangun koleksi manga! Klik "Cari & Tambah Manga" di atas.
        <?php else: ?>
            Belum ada manga di koleksi ini. Hubungi admin untuk menambahkan manga.
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<!-- Empty State: Hasil Pencarian Kosong -->
<div class="empty-state-card my-4" id="noResultState" style="display: none;">
    <div class="empty-icon-box" style="color: #7c8194; background: rgba(124, 129, 148, 0.1);"><i class="bi bi-search"></i></div>
    <h3 class="h5 brand-font mb-2">Tidak Ada Manga yang Cocok</h3>
    <p class="text-secondary mb-3">Tidak ditemukan manga dengan kata kunci atau kriteria filter yang Anda pilih.</p>
    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="resetFilterBtn">
        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter & Pencarian
    </button>
</div>