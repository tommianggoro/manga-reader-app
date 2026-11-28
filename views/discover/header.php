<?php
/**
 * Discover Page Header
 * Used by: discover.php
 * Variables: $userId
 */
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <a class="d-inline-flex align-items-center gap-1 text-decoration-none fw-medium" href="index.php">
        <i class="bi bi-arrow-left"></i> Kembali ke koleksi
    </a>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="theme-toggle-btn" id="themeToggle" title="Ganti Mode Gelap/Terang">
            <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
        </button>
        <?php if ($userId): ?>
            <a href="profile.php" class="theme-toggle-btn user-avatar-btn p-0 overflow-hidden" title="Profil (<?= htmlspecialchars(currentUsername()) ?>">
                <img src="<?= htmlspecialchars(currentUserAvatarUrl()) ?>" alt="Profil">
            </a>
            <button type="button" class="theme-toggle-btn" onclick="logoutUser()" title="Keluar (<?= htmlspecialchars(currentUsername()) ?>">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        <?php endif; ?>
    </div>
</div>

<h1 class="brand-font h4 mb-1"><i class="bi bi-compass text-primary me-1"></i> Temukan Manga</h1>
<p class="text-secondary small mb-3">Cari judul manga/manhwa — hasil digabung dari semua sumber yang mendukung pencarian.</p>