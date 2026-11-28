<?php
/**
 * Shared Header Layout
 * Used by: index.php, manga.php, reader.php, discover.php, stats.php, profile.php
 */

use MangaReader\Helpers\HtmlSanitizer;

// Ensure session is started for auth functions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$isAuthPage = in_array($currentPage, ['login.php', 'register.php']);
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= HtmlSanitizer::escape($_COOKIE['manga_theme'] ?? 'dark') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#10131a">

    <title><?= HtmlSanitizer::escape(($pageTitle ?? 'Manga Reader') . ' | Manga Reader') ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts: Bitter (serif) + Inter (sans) -->
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS Variables -->
    <link href="assets/css/variables.css" rel="stylesheet">

    <!-- Pre-paint theme script (no flash) -->
    <script>
    (function() {
        const savedTheme = localStorage.getItem('manga_theme') || 'dark';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    })();
    </script>
</head>
<body>
    <!-- Skip Link for Accessibility -->
    <a href="#main-content" class="visually-hidden-focusable skip-link">Skip to main content</a>

    <?php if (!$isAuthPage): ?>
    <!-- Header / Navbar -->
    <header class="app-header py-3 border-bottom" style="border-color: var(--bs-border-color) !important;">
        <div class="container" style="max-width: 1000px;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <!-- Left: Back link or Logo -->
                <div class="d-flex align-items-center gap-3">
                    <?php if (isset($backUrl)): ?>
                        <a href="<?= HtmlSanitizer::escapeAttr($backUrl) ?>" class="text-decoration-none fw-medium text-body">
                            <i class="bi bi-arrow-left me-1"></i><?= HtmlSanitizer::escape($backLabel ?? 'Kembali') ?>
                        </a>
                    <?php else: ?>
                        <a href="index.php" class="text-decoration-none brand-font fw-bold fs-5 text-body" style="color: var(--bs-primary);">
                            <i class="bi bi-book me-2"></i>Manga Reader
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Right: Theme Toggle + User Menu -->
                <div class="d-flex align-items-center gap-2">
                    <!-- Theme Toggle -->
                    <button id="themeToggleBtn" class="theme-toggle-btn" title="Ganti Tema" aria-label="Toggle theme">
                        <i id="themeIcon" class="bi bi-moon-stars-fill fs-5"></i>
                    </button>

                    <?php if (isLoggedIn()): ?>
                        <!-- User Menu Dropdown -->
                        <div class="dropdown">
                            <button class="theme-toggle-btn dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Menu User">
                                <img src="<?= HtmlSanitizer::escapeAttr(currentUserAvatarUrl()) ?>" alt="" class="rounded-circle" width="32" height="32" style="object-fit: cover;">
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header brand-font">Menu</h6></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profil</a></li>
                                <li><a class="dropdown-item" href="stats.php"><i class="bi bi-graph-up me-2"></i>Statistik</a></li>
                                <?php if (isAdmin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><h6 class="dropdown-header brand-font">Admin</h6></li>
                                    <li><a class="dropdown-item" href="add_manga.php"><i class="bi bi-plus-circle me-2"></i>Tambah Manga</a></li>
                                    <li><a class="dropdown-item" href="crawl_all.php"><i class="bi bi-arrow-repeat me-2"></i>Crawl Semua</a></li>
                                    <li><a class="dropdown-item" href="manage_admin.php"><i class="bi bi-people me-2"></i>Kelola Admin</a></li>
                                    <li><a class="dropdown-item" href="import.php"><i class="bi bi-upload me-2"></i>Import/Export</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><form action="logout.php" method="POST"><button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button></form></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Guest: Login/Register -->
                        <a href="login.php" class="btn btn-sm btn-outline-primary px-3" id="headerLoginBtn">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                        </a>
                        <a href="register.php" class="btn btn-sm btn-primary px-3" id="headerRegisterBtn">
                            <i class="bi bi-person-plus me-1"></i> Daftar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>

    <main id="main-content" class="container py-4" style="max-width: 1000px;">