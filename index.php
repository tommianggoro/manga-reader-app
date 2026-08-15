<?php
require_once "config.php";
requireAuth();
require_once "sources/SourceRegistry.php";

$userId = currentUserId();

$stmt = $pdo->prepare("
    SELECT m.*, COALESCE(s.is_favorite, 0) AS is_favorite
    FROM mangas m
    LEFT JOIN user_manga_state s ON s.manga_id = m.manga_id AND s.user_id = :uid
    ORDER BY is_favorite DESC, m.title ASC
");
$stmt->execute([":uid" => $userId]);
$mangas = $stmt->fetchAll();

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

$sourcesByManga = [];
$srcRows = $pdo->query("SELECT manga_id, source FROM manga_sources")->fetchAll();
foreach ($srcRows as $row) {
    $sourcesByManga[$row['manga_id']][] = $row['source'];
}

// Label tampilan untuk tiap key sumber (mis. "shngm" -> "Shinigami")
$sourceLabels = [];
foreach (getAllSources() as $key => $adapter) {
    $sourceLabels[$key] = $adapter->getLabel();
}

// Ambil maksimal 3 chapter TERBARU (berdasarkan chapter_number) per manga,
// beserta tanggal update, untuk ditampilkan di tampilan List.
$recentChaptersByManga = [];
$allRecentChapters = $pdo->query("
    SELECT manga_id, chapter_id, chapter_number, chapter_title, updated_at, created_at
    FROM chapters
    ORDER BY manga_id ASC, chapter_number DESC
")->fetchAll();
foreach ($allRecentChapters as $ch) {
    $mid = $ch['manga_id'];
    if (!isset($recentChaptersByManga[$mid])) {
        $recentChaptersByManga[$mid] = [];
    }
    if (count($recentChaptersByManga[$mid]) < 3) {
        $recentChaptersByManga[$mid][] = $ch;
    }
}

// Helper format tanggal singkat ala Indonesia, mis. "29 Jul 2026"
function formatTanggalIndo($datetime) {
    $bulan = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
    $ts = strtotime($datetime);
    return date("j", $ts) . " " . $bulan[(int) date("n", $ts) - 1] . " " . date("Y", $ts);
}

$importSuccess = $_GET["import_success"] ?? null;
$importedCount = $_GET["mangas"] ?? 0;
$importError = $_GET["import_error"] ?? null;
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Koleksi Manga Pribadi</title>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('manga_theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="favicon.ico" sizes="any">


    <link rel="manifest" href="https://tommianggoro.github.io/manga-reader-app/manifest.json">
    <meta name="theme-color" content="#10131a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Manga Reader">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">

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

        body { font-family: 'Inter', system-ui, sans-serif; transition: background-color 0.3s ease, color 0.3s ease; padding-bottom: 70px; }
        .brand-font { font-family: 'Bitter', Georgia, serif; }

        /* Header & Nav */
        .app-header { padding: 1.25rem 0 1rem; border-bottom: 1px solid var(--bs-border-color); margin-bottom: 1.5rem; }
        .app-header h1 {
            margin: 0; font-size: 1.5rem; font-weight: 700; letter-spacing: 0.01em;
            display: flex; align-items: center; gap: 0.6rem;
        }

        /* Stats Bar */
        .stats-badge {
            background: var(--bs-tertiary-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 12px;
            padding: 0.45rem 0.85rem;
            font-size: 0.82rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
        }

        .theme-toggle-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-secondary-bg);
            color: var(--bs-body-color);
            transition: all 0.2s ease;
        }
        .theme-toggle-btn:hover {
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }

        .chip {
            border-radius: 50rem;
            font-size: 0.82rem;
            padding: 0.35rem 0.8rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
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
            position: relative;
        }
        .manga-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            border-color: var(--bs-primary);
        }
        .img-wrapper {
            position: relative; width: 100%; aspect-ratio: 2/3; overflow: hidden; background: var(--shimmer-bg-1);
        }
        .img-wrapper img {
            width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 0.35s ease;
        }
        .img-wrapper img.loaded { opacity: 1; }

        /* Manga Item (List View) */
        .manga-list-item {
            transition: transform 0.2s ease, border-color 0.2s ease;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-card-bg);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }
        .manga-list-item .list-img-wrap {
            width: 90px; height: 125px; flex-shrink: 0; background: var(--shimmer-bg-1); position: relative;
        }
        .manga-list-item .list-img-wrap img {
            width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 0.35s ease;
        }
        .manga-list-item .list-img-wrap img.loaded { opacity: 1; }

        /* Source Tags (dipakai di Grid & List) */
        .source-tags { display: flex; flex-wrap: wrap; gap: 0.3rem; }
        .source-tag {
            font-size: 0.62rem;
            font-weight: 600;
            background: var(--bs-tertiary-bg);
            color: var(--bs-primary);
            border: 1px solid var(--bs-border-color);
            padding: 0.18rem 0.55rem;
            border-radius: 50rem;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        /* Recent Chapters Row (List View) */
        .recent-chapters-row {
            display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.35rem;
        }
        .recent-chapter-chip {
            font-size: 0.72rem;
            background: var(--bs-tertiary-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 50rem;
            padding: 0.15rem 0.6rem;
            color: var(--bs-body-color);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
        }
        .recent-chapter-date {
            color: #7c8194;
            font-size: 0.68rem;
        }

        /* Fav Star Button */
        .fav-star {
            width: 36px; height: 36px; padding: 0; border: none;
            background: rgba(16, 19, 26, 0.75); backdrop-filter: blur(4px);
            color: #aaa; font-size: 1.05rem;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s ease;
        }
        [data-bs-theme="light"] .fav-star { background: rgba(255, 255, 255, 0.85); color: #666; }
        .fav-star.active { color: var(--bs-primary); }

        /* Checkbox & Batch Selection */
        .batch-checkbox {
            width: 22px; height: 22px;
            position: absolute; top: 10px; left: 10px; z-index: 10;
            cursor: pointer; display: none;
            accent-color: var(--bs-primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }
        .manage-mode .batch-checkbox { display: block; }
        .manage-mode .fav-star { display: none !important; }

        /* Floating Batch Action Bar */
        .batch-action-bar {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color);
            border-radius: 50rem; padding: 0.5rem 1.25rem;
            display: none; align-items: center; gap: 0.8rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.4); z-index: 1040;
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
            width: 80px; height: 80px; margin: 0 auto 1.25rem; border-radius: 50%;
            background: var(--bs-tertiary-bg); color: var(--bs-primary);
            display: flex; align-items: center; justify-content: center; font-size: 2.2rem;
        }
        /* Highlight kartu saat ada chapter baru dari sync background */
        @keyframes newChapterPulse {
            0%   { box-shadow: 0 0 0 0 rgba(var(--bs-primary-rgb), 0.55); }
            70%  { box-shadow: 0 0 0 10px rgba(var(--bs-primary-rgb), 0); }
            100% { box-shadow: 0 0 0 0 rgba(var(--bs-primary-rgb), 0); }
        }
        .just-updated {
            animation: newChapterPulse 1.2s ease-out 2;
            border-color: var(--bs-primary) !important;
        }
        .chapter-badge.just-flashed {
            background-color: var(--bs-primary) !important;
            color: #10131a !important;
            transition: background-color 0.3s ease;
        }
        /* === Mobile touch target & layout fixes === */
        @media (max-width: 576px) {
            .app-header { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .app-header h1 { font-size: 1.3rem; }
            .header-actions { width: 100%; }
            .header-actions .btn { flex: 1; }

            .theme-toggle-btn { width: 44px; height: 44px; }
            .fav-star { width: 44px; height: 44px; font-size: 1.15rem; }
            .batch-checkbox { width: 26px; height: 26px; top: 8px; left: 8px; }
        }

        .batch-action-bar {
            padding-bottom: max(0.5rem, env(safe-area-inset-bottom));
            max-width: 94vw;
        }
        @media (max-width: 480px) {
            .batch-action-bar { flex-wrap: wrap; justify-content: center; padding: 0.6rem 1rem; }
            .batch-action-bar .btn { min-height: 40px; }
        }
    </style>
</head>
<body>
<div class="container py-3">

    <!-- Flash Messages (Import Result) -->
    <?php if ($importSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Berhasil mengimpor data! Total <strong><?= (int) $importedCount ?></strong> manga berhasil dipulihkan.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($importError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> Gagal mengimpor backup: <?= htmlspecialchars($importError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="app-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h1 class="brand-font"><i class="bi bi-book-half text-primary"></i> Koleksi Manga Pribadi</h1>
        <div class="d-flex align-items-center gap-2 header-actions">
            <a href="crawl_all.php" target="_blank" class="btn btn-outline-warning btn-sm fw-semibold" title="Sinkronisasi seluruh manga di koleksi">
                <i class="bi bi-arrow-repeat me-1"></i>
                <span class="d-none d-sm-inline">🔥 Cek Update Semua Manga</span>
                <span class="d-inline d-sm-none">Sync</span>
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#backupModal" title="Ekspor/Impor JSON Backup">
                <i class="bi bi-database-gear me-1"></i> <span class="d-none d-sm-inline">Backup</span>
            </button>
            <button type="button" class="theme-toggle-btn ms-1" id="themeToggle" title="Ganti Mode Gelap/Terang">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
            </button>
            <a href="logout.php" class="theme-toggle-btn" title="Keluar (<?= htmlspecialchars(currentUsername()) ?>)">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Statistik Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex flex-wrap gap-2">
            <div class="stats-badge"><i class="bi bi-collection-fill text-primary"></i> <span id="statMangaCount"><?= $totalManga ?> Manga</span></div>
            <div class="stats-badge"><i class="bi bi-journals text-info"></i> <span id="statChapterCount"><?= $totalChapters ?> Chapter</span></div>
            <div class="stats-badge"><i class="bi bi-star-fill text-warning"></i> <span id="statFavCount"><?= $totalFavorites ?> Favorit</span></div>
            <?php if ($recentlyUpdated > 0): ?>
                <div class="stats-badge"><i class="bi bi-fire text-danger"></i> <span>🔥 <?= $recentlyUpdated ?> Update Minggu Ini</span></div>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleManageModeBtn">
            <i class="bi bi-ui-checks me-1"></i> Mode Kelola / Batch Delete
        </button>
    </div>

    <!-- Form Tambah Manga -->
    <form class="row g-2 add-form mb-3" action="add_manga.php" method="GET" id="addMangaForm">
        <div class="col-12 col-sm">
            <input type="text" name="manga_id" id="mangaIdInput" class="form-control form-control-lg fs-6" placeholder="Tempel manga_id Shinigami (mis: solo-leveling) ATAU URL Komiku (https://komiku.org/manga/...)  untuk menambah/update..." required>
        </div>
        <div class="col-12 col-sm-auto">
            <button type="submit" class="btn btn-primary btn-lg fs-6 w-100"><i class="bi bi-plus-lg"></i> Tambah / Update</button>
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
            <div class="col manga-item-col" data-manga-id="<?= htmlspecialchars($m['manga_id']) ?>">
                <div class="card h-100 manga-card">
                    <input type="checkbox" class="batch-checkbox" value="<?= htmlspecialchars($m['manga_id']) ?>">
                    <a class="text-decoration-none color-inherit flex-grow-1" href="manga.php?manga_id=<?= urlencode($m['manga_id']) ?>"
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
                            <?php if (!empty($sourcesByManga[$m['manga_id']])): ?>
                                <div class="source-tags mb-1">
                                    <?php foreach ($sourcesByManga[$m['manga_id']] as $src): ?>
                                        <span class="source-tag"><i class="bi bi-hdd-network me-1"></i><?= htmlspecialchars($sourceLabels[$src] ?? $src) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="card-title text-truncate mb-1" title="<?= htmlspecialchars($m['title']) ?>"><?= htmlspecialchars($m['title']) ?></div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="badge text-bg-secondary fw-normal chapter-badge" data-chapter="<?= htmlspecialchars(formatChapterNumber($m['latest_chapter_number'])) ?>">Ch. <?= htmlspecialchars(formatChapterNumber($m['latest_chapter_number'])) ?></span>
                                <?php if (!empty($m['rating'])): ?>
                                    <small class="text-warning fw-semibold"><i class="bi bi-star-fill"></i> <?= htmlspecialchars($m['rating']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- List View Container (Hidden by default) -->
    <div class="d-flex flex-column gap-2" id="mangaList" style="display: none !important;">
        <?php foreach ($mangas as $m): ?>
            <div class="manga-item-row col-12" data-manga-id="<?= htmlspecialchars($m['manga_id']) ?>">
                <div class="manga-list-item d-flex align-items-center p-2">
                    <input type="checkbox" class="batch-checkbox ms-2 me-1" value="<?= htmlspecialchars($m['manga_id']) ?>">
                    <a class="text-decoration-none color-inherit d-flex align-items-center flex-grow-1 min-w-0"
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
                                <span class="badge text-bg-primary chapter-badge" data-chapter="<?= htmlspecialchars(formatChapterNumber($m['latest_chapter_number'])) ?>">Ch. <?= htmlspecialchars(formatChapterNumber($m['latest_chapter_number'])) ?> Tersimpan</span>
                                <?php if (!empty($m['author'])): ?>
                                    <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($m['author']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($sourcesByManga[$m['manga_id']])): ?>
                                    <span class="source-tags">
                                        <?php foreach ($sourcesByManga[$m['manga_id']] as $src): ?>
                                            <span class="source-tag"><i class="bi bi-hdd-network me-1"></i><?= htmlspecialchars($sourceLabels[$src] ?? $src) ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($recentChaptersByManga[$m['manga_id']])): ?>
                                <div class="recent-chapters-row">
                                    <?php foreach ($recentChaptersByManga[$m['manga_id']] as $rc): ?>
                                        <span class="recent-chapter-chip">
                                            <i class="bi bi-journal-text"></i>
                                            Ch. <?= htmlspecialchars(formatChapterNumber($rc['chapter_number'])) ?>
                                            <span class="recent-chapter-date"><?= formatTanggalIndo($rc['updated_at'] ?? $rc['created_at']) ?></span>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-shrink-0 ms-auto text-end">
                            <button type="button" class="btn rounded-circle fav-star <?= !empty($m['is_favorite']) ? 'active' : '' ?>"
                                    data-manga-id="<?= htmlspecialchars($m['manga_id']) ?>" title="Favoritkan">
                                <i class="bi <?= !empty($m['is_favorite']) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                            </button>
                        </div>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Empty State: Belum ada manga di database -->
    <?php if (empty($mangas)): ?>
        <div class="empty-state-card my-4" id="emptyDatabaseState">
            <div class="empty-icon-box"><i class="bi bi-journal-plus"></i></div>
            <h3 class="h4 brand-font mb-2">Perpustakaan Manga Anda Masih Kosong</h3>
            <p class="text-secondary mb-4 mx-auto" style="max-width: 480px;">
                Mulai bangun koleksi manga pribadi Anda! Ambil <code class="text-primary">manga_id</code> dari Shinigami, lalu tempelkan pada formulir di atas.
            </p>
            <button type="button" class="btn btn-primary px-4 py-2 fw-semibold" onclick="document.getElementById('mangaIdInput').focus();">
                <i class="bi bi-plus-lg me-1"></i> Tambahkan Manga Pertama
            </button>
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

</div>

<!-- Floating Action Bar Mode Kelola / Batch Delete -->
<div class="batch-action-bar" id="batchBar">
    <span class="small fw-semibold me-2" id="selectedCountText">0 Terpilih</span>
    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="selectAllBtn">Pilih Semua</button>
    <button type="button" class="btn btn-sm btn-danger rounded-pill px-3" id="batchDeleteBtn" disabled>
        <i class="bi bi-trash3-fill me-1"></i> Hapus Terpilih
    </button>
    <button type="button" class="btn btn-sm btn-close ms-1" id="cancelManageBtn" title="Batal Mode Kelola"></button>
</div>

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

<!-- Modal Konfirmasi Batch Delete -->
<div class="modal fade" id="confirmBatchDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header text-danger">
                <h5 class="modal-title brand-font"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Massal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus secara permanen <strong id="deleteCountModalText">0</strong> manga yang dipilih beserta seluruh chapter dan riwayatnya?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="executeBatchDeleteBtn"><i class="bi bi-trash3 me-1"></i> Ya, Hapus Sekarang</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notifikasi Chapter Baru (hasil sync background/cron) -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="newChapterToast" class="toast align-items-center border-0 text-bg-primary" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="newChapterToastText">
                <i class="bi bi-stars me-1"></i> Ada chapter baru!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
            const card = col.querySelector("a");
            const match = checkMatch(card);
            col.style.display = match ? "" : "none";
            if (match) visibleCount++;
        });

        listRows.forEach(row => {
            const card = row.querySelector("a");
            const match = checkMatch(card);
            row.style.display = match ? "" : "none";
        });

        noResultState.style.display = (visibleCount === 0 && (gridCols.length > 0)) ? "block" : "none";
    }

    searchInput.addEventListener("input", applyFilters);
    clearSearchBtn.addEventListener("click", () => { searchInput.value = ""; applyFilters(); searchInput.focus(); });
    resetFilterBtn.addEventListener("click", () => {
        searchInput.value = ""; activeGenre = null; favOnly = false;
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
    setViewMode(localStorage.getItem("manga_view_mode") || "grid");

    // Toggle Favorit
    document.querySelectorAll(".fav-star").forEach(star => {
        star.addEventListener("click", async (e) => {
            e.preventDefault(); e.stopPropagation();
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
                document.querySelectorAll(`.fav-star[data-manga-id="${CSS.escape(mangaId)}"]`).forEach(s => {
                    const icon = s.querySelector("i");
                    s.classList.toggle("active", isFav);
                    icon.classList.toggle("bi-star-fill", isFav);
                    icon.classList.toggle("bi-star", !isFav);
                    const item = s.closest("a");
                    if (item) item.dataset.favorite = isFav ? "1" : "0";
                });
                applyFilters();
            } catch (err) { alert("Gagal update favorit: " + err.message); }
        });
    });

    // Mode Kelola & Batch Delete Logic
    const toggleManageModeBtn = document.getElementById("toggleManageModeBtn");
    const batchBar = document.getElementById("batchBar");
    const cancelManageBtn = document.getElementById("cancelManageBtn");
    const selectAllBtn = document.getElementById("selectAllBtn");
    const batchDeleteBtn = document.getElementById("batchDeleteBtn");
    const selectedCountText = document.getElementById("selectedCountText");
    const confirmBatchDeleteModal = new bootstrap.Modal(document.getElementById("confirmBatchDeleteModal"));
    const executeBatchDeleteBtn = document.getElementById("executeBatchDeleteBtn");
    const deleteCountModalText = document.getElementById("deleteCountModalText");

    let isManageMode = false;

    function toggleManageMode(enable) {
        isManageMode = enable !== undefined ? enable : !isManageMode;
        document.body.classList.toggle("manage-mode", isManageMode);
        batchBar.style.display = isManageMode ? "flex" : "none";
        toggleManageModeBtn.classList.toggle("btn-primary", isManageMode);
        toggleManageModeBtn.classList.toggle("btn-outline-secondary", !isManageMode);

        if (!isManageMode) {
            document.querySelectorAll(".batch-checkbox").forEach(cb => cb.checked = false);
            updateBatchUI();
        }
    }

    toggleManageModeBtn.addEventListener("click", () => toggleManageMode());
    cancelManageBtn.addEventListener("click", () => toggleManageMode(false));

    function getSelectedMangaIds() {
        const checked = Array.from(document.querySelectorAll(".batch-checkbox:checked"));
        return Array.from(new Set(checked.map(cb => cb.value)));
    }

    function updateBatchUI() {
        const selected = getSelectedMangaIds();
        selectedCountText.textContent = `${selected.length} Terpilih`;
        batchDeleteBtn.disabled = selected.length === 0;
    }

    document.querySelectorAll(".batch-checkbox").forEach(cb => {
        cb.addEventListener("change", (e) => {
            // Saling sync antara checkbox di Grid & List
            const val = e.target.value;
            document.querySelectorAll(`.batch-checkbox[value="${CSS.escape(val)}"]`).forEach(c => c.checked = e.target.checked);
            updateBatchUI();
        });
    });

    selectAllBtn.addEventListener("click", () => {
        const allCheckboxes = Array.from(document.querySelectorAll(".batch-checkbox"));
        const visibleCols = gridCols.filter(col => col.style.display !== "none");
        const visibleIds = visibleCols.map(col => col.dataset.mangaId);

        const areAllVisibleSelected = visibleIds.every(id => {
            const cb = document.querySelector(`.batch-checkbox[value="${CSS.escape(id)}"]`);
            return cb && cb.checked;
        });

        allCheckboxes.forEach(cb => {
            if (visibleIds.includes(cb.value)) {
                cb.checked = !areAllVisibleSelected;
            }
        });
        updateBatchUI();
    });

    batchDeleteBtn.addEventListener("click", () => {
        const selected = getSelectedMangaIds();
        if (selected.length === 0) return;
        deleteCountModalText.textContent = selected.length;
        confirmBatchDeleteModal.show();
    });

    executeBatchDeleteBtn.addEventListener("click", async () => {
        const selected = getSelectedMangaIds();
        if (selected.length === 0) return;

        try {
            executeBatchDeleteBtn.disabled = true;
            executeBatchDeleteBtn.textContent = "Menghapus...";

            const res = await fetch("delete_manga.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "manga_ids=" + encodeURIComponent(JSON.stringify(selected))
            });
            const data = await res.json();
            if (!data.success) {
                alert("Gagal menghapus manga: " + data.error);
                return;
            }

            // Hapus elemen dari DOM
            selected.forEach(mId => {
                document.querySelectorAll(`[data-manga-id="${CSS.escape(mId)}"]`).forEach(el => el.remove());
            });

            confirmBatchDeleteModal.hide();
            toggleManageMode(false);

            // Update stats counter
            const remaining = document.querySelectorAll("#mangaGrid .manga-item-col").length;
            document.getElementById("statMangaCount").textContent = `${remaining} Manga`;

            if (remaining === 0) {
                window.location.reload();
            }
        } catch (err) {
            alert("Error: " + err.message);
        } finally {
            executeBatchDeleteBtn.disabled = false;
            executeBatchDeleteBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Ya, Hapus Sekarang';
        }
    });

    // ==== Auto-Update Background (Polling, Tanpa Reload) ====
    // Sinkronisasi data manga/chapter dilakukan oleh cron (GitHub Actions / server cron)
    // lewat cron_update.php. Skrip ini hanya memeriksa server secara berkala dan
    // memperbarui tampilan begitu ada perubahan, supaya user tidak perlu reload manual.

    const POLL_INTERVAL_MS = 45000; // cek server tiap 45 detik

    // Snapshot chapter terakhir yang diketahui browser, diisi dari render awal PHP
    const mangaChapterSnapshot = {};
    document.querySelectorAll('[data-manga-id] .chapter-badge').forEach(badge => {
        const col = badge.closest('[data-manga-id]');
        if (col) mangaChapterSnapshot[col.dataset.mangaId] = parseFloat(badge.dataset.chapter || '0');
    });

    function applyChapterUpdate(mangaId, newChapterNumber) {
        document.querySelectorAll(`[data-manga-id="${CSS.escape(mangaId)}"] .chapter-badge`).forEach(badge => {
            badge.textContent = badge.textContent.replace(/\d+(\.\d+)?/, newChapterNumber);
            badge.dataset.chapter = newChapterNumber;
            badge.classList.add('just-flashed');
            setTimeout(() => badge.classList.remove('just-flashed'), 1500);
        });

        document.querySelectorAll(`.manga-card, .manga-list-item`).forEach(card => {
            const wrapper = card.closest('[data-manga-id]');
            if (wrapper && wrapper.dataset.mangaId === mangaId) {
                card.classList.add('just-updated');
                setTimeout(() => card.classList.remove('just-updated'), 2500);
            }
        });
    }

    function showNewChapterToast(count) {
        const toastEl = document.getElementById('newChapterToast');
        const textEl = document.getElementById('newChapterToastText');
        textEl.innerHTML = `<i class="bi bi-stars me-1"></i> ` +
            (count === 1 ? '1 manga punya chapter baru!' : `${count} manga punya chapter baru!`);
        bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 6000 }).show();
    }

    async function pollForUpdates() {
        try {
            const res = await fetch('check_updates.php', { cache: 'no-store' });
            const data = await res.json();
            if (!data.success) return;

            document.getElementById('statMangaCount').textContent = `${data.stats.total_manga} Manga`;
            document.getElementById('statFavCount').textContent = `${data.stats.total_favorites} Favorit`;
            const chEl = document.getElementById('statChapterCount');
            if (chEl) chEl.textContent = `${data.stats.total_chapters} Chapter`;

            let updatedCount = 0;
            for (const [mangaId, latestChapter] of Object.entries(data.mangas)) {
                const prev = mangaChapterSnapshot[mangaId];
                if (prev === undefined) continue; // manga baru ditambah manual, bukan dari cron
                if (latestChapter > prev) {
                    mangaChapterSnapshot[mangaId] = latestChapter;
                    applyChapterUpdate(mangaId, latestChapter);
                    updatedCount++;
                }
            }

            if (updatedCount > 0) showNewChapterToast(updatedCount);
        } catch (err) {
            console.warn('Gagal memeriksa update background:', err.message);
        }
    }

    let pollTimer = setInterval(pollForUpdates, POLL_INTERVAL_MS);

    // Hemat resource: berhenti polling saat tab tidak aktif, cek langsung saat kembali aktif
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(pollTimer);
            pollTimer = null;
        } else {
            pollForUpdates();
            if (!pollTimer) pollTimer = setInterval(pollForUpdates, POLL_INTERVAL_MS);
        }
    });

    // Multi-tab: kalau user sync manual lewat crawl.php/crawl_all.php di tab lain,
    // tab index.php ini langsung ikut ter-update juga tanpa nunggu polling interval.
    if (window.BroadcastChannel) {
        const updateChannel = new BroadcastChannel('manga_reader_updates');
        updateChannel.addEventListener('message', (event) => {
            const msg = event.data;
            if (msg && msg.type === 'manga_updated' && msg.manga_id && msg.chapter_number) {
                const prev = mangaChapterSnapshot[msg.manga_id] || 0;
                if (msg.chapter_number > prev) {
                    mangaChapterSnapshot[msg.manga_id] = msg.chapter_number;
                    applyChapterUpdate(msg.manga_id, msg.chapter_number);
                }
            }
        });
    }
</script>
</body>
</html>