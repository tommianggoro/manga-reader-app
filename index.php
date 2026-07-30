<?php
require_once "config.php";
requireAuth();

$mangas = $pdo->query("SELECT * FROM mangas ORDER BY is_favorite DESC, title ASC")->fetchAll();

// Hitung statistik koleksi
$totalManga = count($mangas);
$totalChapters = (int) $pdo->query("SELECT COUNT(*) FROM chapters")->fetchColumn();
$totalFavorites = count(array_filter($mangas, fn($m) => !empty($m['is_favorite'])));
$recentlyUpdated = (int) $pdo->query("SELECT COUNT(DISTINCT manga_id) FROM chapters WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Kumpulkan semua genre unik dari koleksi untuk dijadikan chip filter
$allGenres = [];
foreach ($mangas as $m) {
    if (!empty($m['genres'])) {
        foreach (explode(",", $m['genres']) as $g) {
            $g = trim($g);
            if ($g !== "") $allGenres[$g] = true;
        }
    }
}
$allGenres = array_keys($allGenres);
sort($allGenres);
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Koleksi Manga Pribadi</title>
    <script>
        // Mencegah flicker tema saat halaman dimuat
        (function() {
            const savedTheme = localStorage.getItem('manga_theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%23f2a541'/%3E%3Ctext x='50' y='68' font-size='55' text-anchor='middle'%3E%F0%9F%93%96%3C/text%3E%3C/svg%3E">
    <style>
        :root, [data-bs-theme="dark"] {
            --bs-body-bg: #10131a;
            --bs-body-color: #eae7e0;
            --bs-primary: #f2a541;
            --bs-primary-rgb: 242, 165, 65;
            --bs-border-color: #242938;
            --bs-secondary-bg: #171b26;
            --bs-tertiary-bg: #202636;
            --bs-link-color: #f2a541;
            --bs-link-hover-color: #f7bc70;
            --bs-card-bg: #171b26;
            --shimmer-bg-1: #1f2533;
            --shimmer-bg-2: #2b3347;
        }

        [data-bs-theme="light"] {
            --bs-body-bg: #f5f6f8;
            --bs-body-color: #212529;
            --bs-primary: #e08b18;
            --bs-primary-rgb: 224, 139, 24;
            --bs-border-color: #e0e4eb;
            --bs-secondary-bg: #ffffff;
            --bs-tertiary-bg: #f0f2f5;
            --bs-link-color: #e08b18;
            --bs-link-hover-color: #c4760e;
            --bs-card-bg: #ffffff;
            --shimmer-bg-1: #e9ecef;
            --shimmer-bg-2: #f8f9fa;
        }

        body { font-family: 'Inter', system-ui, sans-serif; transition: background-color 0.3s ease, color 0.3s ease; }
        .brand-font { font-family: 'Bitter', Georgia, serif; }

        /* Header & Nav */
        .app-header { padding: 1.25rem 0 1rem; border-bottom: 1px solid var(--bs-border-color); margin-bottom: 1.5rem; }
        .app-header h1 {
            margin: 0; font-size: 1.5rem; font-weight: 700; letter-spacing: 0.01em;
            display: flex; align-items: center; gap: 0.6rem;
        }
        .app-header h1 i { font-size: 1.4rem; }

        /* Stats Bar */
        .stats-badge {
            background: var(--bs-tertiary-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 12px;
            padding: 0.5rem 0.85rem;
            font-size: 0.82rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
        }
        .stats-badge:hover {
            transform: translateY(-2px);
            border-color: var(--bs-primary);
        }

        /* Form & Inputs */
        .add-form .form-control::placeholder { color: #7c8194; }
        .theme-toggle-btn {
            width: 40px; height: 40px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-secondary-bg);
            color: var(--bs-body-color);
            transition: all 0.2s ease;
        }
        .theme-toggle-btn:hover {
            border-color: var(--bs-primary);
            color: var(--bs-primary);
            transform: rotate(15deg);
        }

        /* Chips Filter */
        .chip {
            border-radius: 50rem;
            font-size: 0.82rem;
            padding: 0.35rem 0.8rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .chip:hover {
            transform: translateY(-1px);
        }

        /* Shimmer Loading Effect */
        .skeleton-shimmer {
            background: linear-gradient(90deg, var(--shimmer-bg-1) 25%, var(--shimmer-bg-2) 50%, var(--shimmer-bg-1) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Manga Card (Grid View) */
        .manga-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            border-color: var(--bs-border-color);
            overflow: hidden;
            border-radius: 12px;
            background: var(--bs-card-bg);
        }
        .manga-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            border-color: var(--bs-primary);
        }
        .img-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 2/3;
            overflow: hidden;
            background: var(--shimmer-bg-1);
        }
        .img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 0.35s ease, transform 0.35s ease;
        }
        .img-wrapper img.loaded {
            opacity: 1;
        }
        .manga-card:hover .img-wrapper img.loaded {
            transform: scale(1.04);
        }
        .manga-card .card-title {
            font-size: 0.92rem;
            font-weight: 600;
            line-height: 1.35;
        }

        /* Manga Item (List View) */
        .manga-list-item {
            transition: transform 0.2s ease, border-color 0.2s ease;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-card-bg);
            border-radius: 12px;
            overflow: hidden;
        }
        .manga-list-item:hover {
            transform: translateX(4px);
            border-color: var(--bs-primary);
        }
        .manga-list-item .list-img-wrap {
            width: 90px;
            height: 125px;
            flex-shrink: 0;
            background: var(--shimmer-bg-1);
            position: relative;
        }
        .manga-list-item .list-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 0.35s ease;
        }
        .manga-list-item .list-img-wrap img.loaded {
            opacity: 1;
        }

        /* Fav Star Button */
        .fav-star {
            width: 36px; height: 36px; padding: 0; border: none;
            background: rgba(16, 19, 26, 0.75); backdrop-filter: blur(4px);
            color: #aaa; font-size: 1.05rem;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s ease;
        }
        [data-bs-theme="light"] .fav-star {
            background: rgba(255, 255, 255, 0.85);
            color: #666;
        }
        .fav-star.active { color: var(--bs-primary); }
        .fav-star:hover {
            transform: scale(1.1);
            background: rgba(16, 19, 26, 0.95);
        }

        /* Empty States */
        .empty-state-card {
            background: var(--bs-secondary-bg);
            border: 2px dashed var(--bs-border-color);
            border-radius: 16px;
            padding: 3rem 1.5rem;
            text-align: center;
        }
        .empty-icon-box {
            width: 80px; height: 80px;
            margin: 0 auto 1.25rem;
            border-radius: 50%;
            background: var(--bs-tertiary-bg);
            color: var(--bs-primary);
            display: flex; align-items: center; justify-content: justify-content;
            justify-content: center;
            font-size: 2.2rem;
        }
    </style>
</head>
<body>
<div class="container py-3">

    <!-- Header Section -->
    <div class="app-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h1 class="brand-font"><i class="bi bi-book-half text-primary"></i> Koleksi Manga Pribadi</h1>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="theme-toggle-btn" id="themeToggle" title="Ganti Mode Gelap/Terang">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
            </button>
        </div>
    </div>

    <!-- Statistik Bar -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <div class="stats-badge"><i class="bi bi-collection-fill text-primary"></i> <span><?= $totalManga ?> Manga</span></div>
        <div class="stats-badge"><i class="bi bi-journals text-info"></i> <span><?= $totalChapters ?> Total Chapter</span></div>
        <div class="stats-badge"><i class="bi bi-star-fill text-warning"></i> <span><?= $totalFavorites ?> Favorit</span></div>
        <?php if ($recentlyUpdated > 0): ?>
            <div class="stats-badge"><i class="bi bi-fire text-danger"></i> <span>🔥 <?= $recentlyUpdated ?> Update Minggu Ini</span></div>
        <?php endif; ?>
    </div>

    <!-- Form Tambah Manga -->
    <form class="row g-2 add-form mb-3" action="crawl.php" method="GET" id="addMangaForm">
        <div class="col">
            <input type="text" name="manga_id" id="mangaIdInput" class="form-control form-control-lg fs-6" placeholder="Tempel manga_id di sini (contoh: solo-leveling) untuk menambah/update..." required>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-lg fs-6"><i class="bi bi-plus-lg"></i> Tambah / Update</button>
        </div>
    </form>

    <!-- Search & View Mode Switcher -->
    <div class="row g-2 mb-3">
        <div class="col">
            <div class="input-group">
                <span class="input-group-text bg-body-tertiary border-secondary-subtle"><i class="bi bi-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Cari manga berdasarkan judul, id, pengarang, atau genre...">
                <button type="button" class="btn btn-outline-secondary" id="clearSearchBtn" style="display:none;" title="Bersihkan Pencarian">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="col-auto">
            <div class="btn-group" role="group" aria-label="Modus Tampilan">
                <button type="button" class="btn btn-outline-secondary active" id="viewGridBtn" title="Tampilan Grid">
                    <i class="bi bi-grid-fill"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" id="viewListBtn" title="Tampilan List Detail">
                    <i class="bi bi-list-task"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Chips -->
    <div class="d-flex flex-wrap gap-2 mb-4" id="filterChips">
        <button type="button" class="btn btn-sm btn-outline-secondary chip fav-chip" id="favChip" data-filter="favorite">
            <i class="bi bi-star-fill me-1"></i> Favorit
        </button>
        <?php foreach ($allGenres as $genre): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary chip genre-chip" data-genre="<?= htmlspecialchars(mb_strtolower($genre)) ?>">
                <?= htmlspecialchars($genre) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Grid View Container -->
    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3" id="mangaGrid">
        <?php foreach ($mangas as $m): ?>
            <div class="col manga-item-col">
                <a class="card h-100 text-decoration-none manga-card"
                   href="manga.php?manga_id=<?= urlencode($m['manga_id']) ?>"
                   data-title="<?= htmlspecialchars(mb_strtolower($m['title'])) ?>"
                   data-id="<?= htmlspecialchars(mb_strtolower($m['manga_id'])) ?>"
                   data-author="<?= htmlspecialchars(mb_strtolower($m['author'] ?? '')) ?>"
                   data-genres="<?= htmlspecialchars(mb_strtolower($m['genres'] ?? '')) ?>"
                   data-favorite="<?= !empty($m['is_favorite']) ? '1' : '0' ?>">
                    <div class="img-wrapper skeleton-shimmer">
                        <img src="<?= htmlspecialchars($m['cover_image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($m['title']) ?>" loading="lazy" onload="this.classList.add('loaded'); this.parentElement.classList.remove('skeleton-shimmer');">
                        <button type="button" class="btn position-absolute top-0 end-0 m-2 rounded-circle fav-star <?= !empty($m['is_favorite']) ? 'active' : '' ?>"
                                data-manga-id="<?= htmlspecialchars($m['manga_id']) ?>" title="Favoritkan">
                            <i class="bi <?= !empty($m['is_favorite']) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                        </button>
                    </div>
                    <div class="card-body p-2 d-flex flex-column justify-content-between">
                        <div class="card-title text-truncate mb-1" title="<?= htmlspecialchars($m['title']) ?>"><?= htmlspecialchars($m['title']) ?></div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="badge text-bg-secondary fw-normal">Ch. <?= (int) $m['latest_chapter_number'] ?></span>
                            <?php if (!empty($m['rating'])): ?>
                                <small class="text-warning fw-semibold"><i class="bi bi-star-fill"></i> <?= htmlspecialchars($m['rating']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- List View Container (Hidden by default) -->
    <div class="d-flex flex-column gap-2" id="mangaList" style="display: none !important;">
        <?php foreach ($mangas as $m): ?>
            <div class="manga-item-row col-12">
                <a class="text-decoration-none color-inherit manga-list-item d-flex align-items-center p-2"
                   href="manga.php?manga_id=<?= urlencode($m['manga_id']) ?>"
                   data-title="<?= htmlspecialchars(mb_strtolower($m['title'])) ?>"
                   data-id="<?= htmlspecialchars(mb_strtolower($m['manga_id'])) ?>"
                   data-author="<?= htmlspecialchars(mb_strtolower($m['author'] ?? '')) ?>"
                   data-genres="<?= htmlspecialchars(mb_strtolower($m['genres'] ?? '')) ?>"
                   data-favorite="<?= !empty($m['is_favorite']) ? '1' : '0' ?>">
                    <div class="list-img-wrap rounded me-3 skeleton-shimmer">
                        <img src="<?= htmlspecialchars($m['cover_image_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>" loading="lazy" onload="this.classList.add('loaded'); this.parentElement.classList.remove('skeleton-shimmer');">
                    </div>
                    <div class="flex-grow-1 min-w-0 me-2">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="h6 mb-0 text-truncate font-weight-bold brand-font" style="color: var(--bs-body-color)"><?= htmlspecialchars($m['title']) ?></h2>
                            <?php if (!empty($m['rating'])): ?>
                                <span class="badge text-bg-warning text-dark"><i class="bi bi-star-fill"></i> <?= htmlspecialchars($m['rating']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($m['alternative_title'])): ?>
                            <div class="small text-secondary text-truncate mb-1"><?= htmlspecialchars($m['alternative_title']) ?></div>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap align-items-center gap-2 small text-secondary">
                            <span class="badge text-bg-primary">Ch. <?= (int) $m['latest_chapter_number'] ?> Tersimpan</span>
                            <?php if (!empty($m['author'])): ?>
                                <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($m['author']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($m['genres'])): ?>
                                <span class="text-truncate d-none d-sm-inline" style="max-width: 250px;"><i class="bi bi-tags me-1"></i><?= htmlspecialchars($m['genres']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex-shrink-0 ms-auto text-end">
                        <button type="button" class="btn rounded-circle fav-star <?= !empty($m['is_favorite']) ? 'active' : '' ?>"
                                data-manga-id="<?= htmlspecialchars($m['manga_id']) ?>" title="Favoritkan">
                            <i class="bi <?= !empty($m['is_favorite']) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                        </button>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Empty State: Belum ada manga di database -->
    <?php if (empty($mangas)): ?>
        <div class="empty-state-card my-4" id="emptyDatabaseState">
            <div class="empty-icon-box">
                <i class="bi bi-journal-plus"></i>
            </div>
            <h3 class="h4 brand-font mb-2">Perpustakaan Manga Anda Masih Kosong</h3>
            <p class="text-secondary mb-4 mx-auto" style="max-width: 480px;">
                Mulai bangun koleksi manga pribadi Anda! Ambil <code class="text-primary">manga_id</code> dari URL manga di Shinigami, lalu tempelkan pada formulir di atas.
            </p>
            <button type="button" class="btn btn-primary px-4 py-2 fw-semibold" onclick="document.getElementById('mangaIdInput').focus();">
                <i class="bi bi-plus-lg me-1"></i> Tambahkan Manga Pertama
            </button>
        </div>
    <?php endif; ?>

    <!-- Empty State: Hasil Pencarian / Filter Kosong -->
    <div class="empty-state-card my-4" id="noResultState" style="display: none;">
        <div class="empty-icon-box" style="color: #7c8194; background: rgba(124, 129, 148, 0.1);">
            <i class="bi bi-search"></i>
        </div>
        <h3 class="h5 brand-font mb-2">Tidak Ada Manga yang Cocok</h3>
        <p class="text-secondary mb-3">Tidak ditemukan manga dengan kata kunci atau kriteria filter yang Anda pilih.</p>
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="resetFilterBtn">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter & Pencarian
        </button>
    </div>

</div>

<script>
    // Theme Switcher Logic
    const themeToggleBtn = document.getElementById("themeToggle");
    const themeIcon = document.getElementById("themeIcon");

    function updateThemeUI(theme) {
        document.documentElement.setAttribute("data-bs-theme", theme);
        localStorage.setItem("manga_theme", theme);
        if (theme === "dark") {
            themeIcon.className = "bi bi-moon-stars-fill";
            themeToggleBtn.title = "Ganti ke Mode Terang";
        } else {
            themeIcon.className = "bi bi-sun-fill text-warning";
            themeToggleBtn.title = "Ganti ke Mode Gelap";
        }
    }

    const currentTheme = localStorage.getItem("manga_theme") || "dark";
    updateThemeUI(currentTheme);

    themeToggleBtn.addEventListener("click", () => {
        const newTheme = document.documentElement.getAttribute("data-bs-theme") === "dark" ? "light" : "dark";
        updateThemeUI(newTheme);
    });

    // Filtering & Searching Logic
    const searchInput = document.getElementById("searchInput");
    const clearSearchBtn = document.getElementById("clearSearchBtn");
    const gridCols = Array.from(document.querySelectorAll("#mangaGrid .manga-item-col"));
    const listRows = Array.from(document.querySelectorAll("#mangaList .manga-item-row"));
    const noResultState = document.getElementById("noResultState");
    const resetFilterBtn = document.getElementById("resetFilterBtn");
    const chips = Array.from(document.querySelectorAll(".chip"));

    let activeGenre = null;
    let favOnly = false;
    let currentView = localStorage.getItem("manga_view_mode") || "grid";

    function setChipActive(chip, active) {
        chip.classList.toggle("btn-primary", active);
        chip.classList.toggle("btn-outline-secondary", !active);
    }

    function applyFilters() {
        const q = searchInput.value.trim().toLowerCase();
        clearSearchBtn.style.display = q ? "block" : "none";
        let visibleCount = 0;

        const checkMatch = (card) => {
            const matchSearch = !q || card.dataset.title.includes(q) || card.dataset.id.includes(q) || (card.dataset.author && card.dataset.author.includes(q)) || (card.dataset.genres && card.dataset.genres.includes(q));
            const matchGenre = !activeGenre || card.dataset.genres.includes(activeGenre);
            const matchFav = !favOnly || card.dataset.favorite === "1";
            return matchSearch && matchGenre && matchFav;
        };

        gridCols.forEach(col => {
            const card = col.querySelector(".manga-card");
            const match = checkMatch(card);
            col.style.display = match ? "" : "none";
            if (match) visibleCount++;
        });

        listRows.forEach(row => {
            const card = row.querySelector(".manga-list-item");
            const match = checkMatch(card);
            row.style.display = match ? "" : "none";
        });

        noResultState.style.display = (visibleCount === 0 && (gridCols.length > 0)) ? "block" : "none";
    }

    searchInput.addEventListener("input", applyFilters);

    clearSearchBtn.addEventListener("click", () => {
        searchInput.value = "";
        applyFilters();
        searchInput.focus();
    });

    resetFilterBtn.addEventListener("click", () => {
        searchInput.value = "";
        activeGenre = null;
        favOnly = false;
        chips.forEach(c => setChipActive(c, false));
        applyFilters();
    });

    chips.forEach(chip => {
        chip.addEventListener("click", () => {
            if (chip.dataset.filter === "favorite") {
                favOnly = !favOnly;
                setChipActive(chip, favOnly);
            } else {
                const genre = chip.dataset.genre;
                if (activeGenre === genre) {
                    activeGenre = null;
                    setChipActive(chip, false);
                } else {
                    chips.forEach(c => { if (c.dataset.genre) setChipActive(c, false); });
                    activeGenre = genre;
                    setChipActive(chip, true);
                }
            }
            applyFilters();
        });
    });

    // Grid vs List View Toggle
    const viewGridBtn = document.getElementById("viewGridBtn");
    const viewListBtn = document.getElementById("viewListBtn");
    const mangaGrid = document.getElementById("mangaGrid");
    const mangaList = document.getElementById("mangaList");

    function setViewMode(mode) {
        currentView = mode;
        localStorage.setItem("manga_view_mode", mode);
        if (mode === "grid") {
            mangaGrid.style.setProperty("display", "flex", "important");
            mangaList.style.setProperty("display", "none", "important");
            viewGridBtn.classList.add("active");
            viewListBtn.classList.remove("active");
        } else {
            mangaGrid.style.setProperty("display", "none", "important");
            mangaList.style.setProperty("display", "flex", "important");
            viewListBtn.classList.add("active");
            viewGridBtn.classList.remove("active");
        }
    }

    viewGridBtn.addEventListener("click", () => setViewMode("grid"));
    viewListBtn.addEventListener("click", () => setViewMode("list"));
    setViewMode(currentView);

    // Toggle favorit tanpa reload halaman
    document.querySelectorAll(".fav-star").forEach(star => {
        star.addEventListener("click", async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const mangaId = star.dataset.mangaId;
            try {
                const res = await fetch("toggle_favorite.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "manga_id=" + encodeURIComponent(mangaId),
                });
                const data = await res.json();
                if (!data.success) { alert("Gagal update favorit: " + data.error); return; }

                const isFav = data.is_favorite;
                // Sync favorit di semua tombol dengan mangaId yang sama (baik di Grid maupun List)
                document.querySelectorAll(`.fav-star[data-manga-id="${CSS.escape(mangaId)}"]`).forEach(s => {
                    const icon = s.querySelector("i");
                    s.classList.toggle("active", isFav);
                    icon.classList.toggle("bi-star-fill", isFav);
                    icon.classList.toggle("bi-star", !isFav);
                    const item = s.closest(".manga-card, .manga-list-item");
                    if (item) item.dataset.favorite = isFav ? "1" : "0";
                });

                applyFilters();
            } catch (err) {
                alert("Gagal update favorit: " + err.message);
            }
        });
    });
</script>
</body>
</html>