<?php
/**
 * Profile Page Header
 * Used by: profile.php
 */
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <a class="d-inline-flex align-items-center gap-1 text-decoration-none fw-medium" href="index.php">
        <i class="bi bi-arrow-left"></i> Kembali ke koleksi
    </a>
    <div class="d-flex align-items-center gap-2">
        <a href="discover.php" class="btn btn-outline-secondary btn-sm" title="Temukan Manga Baru">
            <i class="bi bi-compass"></i> <span class="d-none d-sm-inline">Temukan</span>
        </a>
        <a href="stats.php" class="btn btn-outline-secondary btn-sm" title="Statistik Bacaan">
            <i class="bi bi-graph-up-arrow"></i> <span class="d-none d-sm-inline">Statistik</span>
        </a>
        <?php if (isAdmin()): ?>
            <a href="manage_admin.php" class="theme-toggle-btn" title="Kelola Admin"><i class="bi bi-people-fill"></i></a>
        <?php endif; ?>
        <button type="button" class="theme-toggle-btn" onclick="logoutUser('index.php')" title="Keluar"><i class="bi bi-box-arrow-right"></i></button>
    </div>
</div>

<h1 class="brand-font h4 mb-4"><i class="bi bi-person-circle text-primary me-1"></i> Profil Saya</h1>