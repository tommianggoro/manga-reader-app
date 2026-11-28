<?php
/**
 * Stats Page Header
 * Used by: stats.php
 */
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <a class="d-inline-flex align-items-center gap-1 text-decoration-none fw-medium" href="index.php">
        <i class="bi bi-arrow-left"></i> Kembali ke koleksi
    </a>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="theme-toggle-btn" id="themeToggle" title="Ganti Mode Gelap/Terang">
            <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
        </button>
        <a href="discover.php" class="btn btn-outline-secondary btn-sm" title="Temukan Manga Baru">
            <i class="bi bi-compass"></i> <span class="d-none d-sm-inline">Temukan</span>
        </a>
        <a href="stats.php" class="btn btn-outline-secondary btn-sm" title="Statistik Bacaan">
            <i class="bi bi-graph-up-arrow"></i> <span class="d-none d-sm-inline">Statistik</span>
        </a>
        <a href="profile.php" class="theme-toggle-btn user-avatar-btn p-0 overflow-hidden" title="Profil (<?= htmlspecialchars($username ?? '') ?>)">
            <img src="<?= htmlspecialchars(currentUserAvatarUrl()) ?>" alt="Profil">
        </a>
        <button type="button" class="theme-toggle-btn" onclick="logoutUser()" title="Keluar (<?= htmlspecialchars($username ?? '') ?>)">
            <i class="bi bi-box-arrow-right"></i>
        </button>
    </div>
</div>

<h1 class="brand-font h3 mb-1"><i class="bi bi-graph-up-arrow text-primary me-1"></i> Statistik Bacaan</h1>
<p class="text-secondary mb-4">Ringkasan aktivitas membaca manga kamu, <?= htmlspecialchars($username ?? '') ?>.</p>