<?php
require_once "config.php";
requireAuth();

$chapterId = $_GET["chapter_id"] ?? die("chapter_id wajib diisi");

$stmt = $pdo->prepare("SELECT * FROM chapters WHERE chapter_id = :id");
$stmt->execute([":id" => $chapterId]);
$chapter = $stmt->fetch();
if (!$chapter) die("Chapter tidak ditemukan.");

$stmt = $pdo->prepare("SELECT * FROM mangas WHERE manga_id = :id");
$stmt->execute([":id" => $chapter["manga_id"]]);
$manga = $stmt->fetch();

// Tandai chapter ini sebagai yang terakhir dibaca
$stmt = $pdo->prepare("UPDATE mangas SET last_read_chapter_id = :cid, last_read_chapter_number = :cnum, last_read_at = NOW() WHERE manga_id = :mid");
$stmt->execute([
    ":cid" => $chapter["chapter_id"],
    ":cnum" => $chapter["chapter_number"],
    ":mid" => $chapter["manga_id"],
]);

$stmt = $pdo->prepare("SELECT * FROM chapter_images WHERE chapter_id = :id ORDER BY page_number ASC");
$stmt->execute([":id" => $chapterId]);
$images = $stmt->fetchAll();

// Chapter sebelum & sesudah untuk navigasi
$stmt = $pdo->prepare("SELECT chapter_id, chapter_number FROM chapters WHERE manga_id = :m AND chapter_number < :n ORDER BY chapter_number DESC LIMIT 1");
$stmt->execute([":m" => $chapter["manga_id"], ":n" => $chapter["chapter_number"]]);
$prevCh = $stmt->fetch();

$stmt = $pdo->prepare("SELECT chapter_id, chapter_number FROM chapters WHERE manga_id = :m AND chapter_number > :n ORDER BY chapter_number ASC LIMIT 1");
$stmt->execute([":m" => $chapter["manga_id"], ":n" => $chapter["chapter_number"]]);
$nextCh = $stmt->fetch();

// Preload gambar untuk chapter selanjutnya (jika ada)
$nextChapterImageUrls = [];
if ($nextCh) {
    $stmt = $pdo->prepare("SELECT c.base_url, c.image_path, ci.filename FROM chapter_images ci JOIN chapters c ON ci.chapter_id = c.chapter_id WHERE ci.chapter_id = :id ORDER BY ci.page_number ASC");
    $stmt->execute([":id" => $nextCh["chapter_id"]]);
    $nextImagesRaw = $stmt->fetchAll();
    foreach ($nextImagesRaw as $ni) {
        $nextChapterImageUrls[] = $ni['base_url'] . $ni['image_path'] . $ni['filename'];
    }
}

// Semua chapter untuk dropdown "loncat ke chapter"
$stmt = $pdo->prepare("SELECT chapter_id, chapter_number FROM chapters WHERE manga_id = :m ORDER BY chapter_number DESC");
$stmt->execute([":m" => $chapter["manga_id"]]);
$allChapters = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($manga['title']) ?> - Chapter <?= (int) $chapter['chapter_number'] ?></title>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('manga_theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
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
            --bs-body-bg: #0b0c10;
            --bs-body-color: #eae7e0;
            --bs-primary: #f2a541;
            --bs-primary-rgb: 242, 165, 65;
            --topbar-bg: #14171f;
            --topbar-border: #2a2f3d;
            --shimmer-bg-1: #191d29;
            --shimmer-bg-2: #252b3c;
        }

        [data-bs-theme="light"] {
            --bs-body-bg: #e5e7eb;
            --bs-body-color: #111827;
            --bs-primary: #e08b18;
            --bs-primary-rgb: 224, 139, 24;
            --topbar-bg: #ffffff;
            --topbar-border: #d1d5db;
            --shimmer-bg-1: #d1d5db;
            --shimmer-bg-2: #f3f4f6;
        }

        body { padding-bottom: 100px; font-family: 'Inter', system-ui, sans-serif; transition: background-color 0.3s ease; }

        /* Reading Progress Bar (Fixed Top) */
        .reading-progress-bar {
            position: fixed; top: 0; left: 0; width: 0%; height: 4px;
            background: linear-gradient(90deg, var(--bs-primary), #f7bc70);
            z-index: 1060; transition: width 0.1s linear;
        }

        .topbar {
            background: var(--topbar-bg); border-bottom: 1px solid var(--topbar-border);
            transition: background 0.3s ease, transform 0.25s ease, opacity 0.25s ease;
        }
        .topbar a { color: var(--bs-body-color); }

        .theme-toggle-btn {
            width: 34px; height: 34px; border-radius: 50%; padding: 0;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--topbar-border); background: transparent;
            color: var(--bs-body-color); transition: all 0.2s ease;
        }

        /* Chapter Images Wrapper & Skeleton */
        .reader { width: 100%; max-width: 800px; margin: 0 auto; transition: max-width 0.2s ease; }
        .page-wrapper {
            position: relative; width: 100%; min-height: 400px; background: var(--shimmer-bg-1);
            margin-bottom: 0; display: flex; align-items: center; justify-content: center;
        }
        .page-wrapper img {
            width: 100%; display: block; height: auto; opacity: 0; transition: opacity 0.35s ease;
        }
        .page-wrapper img.loaded { opacity: 1; }

        /* Skeleton Shimmer Loading */
        .skeleton-shimmer {
            background: linear-gradient(90deg, var(--shimmer-bg-1) 25%, var(--shimmer-bg-2) 50%, var(--shimmer-bg-1) 75%);
            background-size: 200% 100%; animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Mode Single Page Layout */
        .mode-single-page .page-wrapper { display: none !important; }
        .mode-single-page .page-wrapper.active-page { display: flex !important; }
        .mode-single-page .end-chapter-card { display: none; }
        .mode-single-page .end-chapter-card.active-end-card { display: block; }

        /* Single Page Controls Bar */
        .single-page-controls {
            display: none; align-items: center; justify-content: center; gap: 1rem;
            margin: 1.5rem 0 0.5rem;
        }
        .mode-single-page .single-page-controls { display: flex; }

        /* Image Error Card */
        .image-error-card {
            background: var(--topbar-bg); border: 1px solid #dc3545; border-radius: 12px;
            padding: 2rem 1.5rem; text-align: center; width: 100%; max-width: 500px; margin: 1.5rem auto;
        }

        /* Floating Nav Bar */
        .floating-nav {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: rgba(20, 23, 31, 0.88); backdrop-filter: blur(10px);
            border-radius: 50rem; padding: 0.4rem 0.8rem;
            display: flex; align-items: center; gap: 0.4rem;
            box-shadow: 0 6px 25px rgba(0,0,0,0.5); z-index: 1000;
            border: 1px solid rgba(255,255,255,0.1);
            transition: transform 0.25s ease, opacity 0.25s ease;
        }

        /* Immersive Reading Mode: navbar & floating-nav auto-hide saat scroll ke bawah,
           muncul lagi saat scroll ke atas, dekat ujung chapter, atau gambar di-tap sekali. */
        body.reader-hide-ui .topbar {
            transform: translateY(-100%); opacity: 0; pointer-events: none;
        }
        body.reader-hide-ui .floating-nav {
            transform: translateX(-50%) translateY(140%); opacity: 0; pointer-events: none;
        }
        [data-bs-theme="light"] .floating-nav {
            background: rgba(255, 255, 255, 0.9); box-shadow: 0 6px 25px rgba(0,0,0,0.15); border: 1px solid rgba(0,0,0,0.1);
        }
        .floating-nav .btn {
            width: 40px; height: 40px; border-radius: 50%; padding: 0;
            display: flex; align-items: center; justify-content: center;
            background: transparent; border: none; color: var(--bs-body-color); font-size: 1.15rem;
            transition: all 0.15s ease;
        }
        .floating-nav .btn:hover:not(:disabled) { background: rgba(120, 120, 120, 0.2); transform: scale(1.05); }
        .floating-nav .btn:disabled { opacity: 0.25; }
        .floating-nav select {
            background: var(--topbar-bg); color: var(--bs-body-color); border: 1px solid var(--topbar-border);
            border-radius: 50rem; padding: 0.4rem 0.8rem; font-size: 0.85rem; max-width: 120px;
        }

        /* Tap Zones Navigation Overlay */
        .tap-zone {
            position: fixed; top: 50px; bottom: 80px; width: 15%; z-index: 500;
            opacity: 0; transition: opacity 0.2s ease;
            display: flex; align-items: center; justify-content: center;
            color: var(--bs-primary); font-size: 2rem; pointer-events: auto;
        }
        .tap-zone-left { left: 0; background: linear-gradient(90deg, rgba(0,0,0,0.2), transparent); }
        .tap-zone-right { right: 0; background: linear-gradient(-90deg, rgba(0,0,0,0.2), transparent); }
        .tap-zone:active { opacity: 0.8; }

        /* End Chapter Banner */
        .end-chapter-card {
            max-width: 700px; margin: 2.5rem auto 1rem;
            background: var(--topbar-bg); border: 1px solid var(--topbar-border);
            border-radius: 16px; padding: 2rem; text-align: center; width: 100%;
        }

        /* Toast Hint */
        .shortcut-toast {
            position: fixed; bottom: 80px; right: 20px; z-index: 1050;
            background: var(--topbar-bg); border: 1px solid var(--topbar-border);
            border-radius: 12px; padding: 0.75rem 1rem; font-size: 0.82rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3); display: none;
        }
    </style>
</head>
<body>

    <!-- Reading Progress Bar -->
    <div class="reading-progress-bar" id="progressBar"></div>

    <!-- Top Navigation Bar -->
    <nav class="topbar sticky-top d-flex justify-content-between align-items-center px-3 py-2">
        <a class="d-inline-flex align-items-center gap-2 text-decoration-none fw-medium text-truncate" href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>" style="max-width: 65%;">
            <i class="bi bi-arrow-left"></i> <span class="text-truncate"><?= htmlspecialchars($manga['title']) ?></span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-primary">Ch. <?= (int) $chapter['chapter_number'] ?></span>
            <button type="button" class="theme-toggle-btn" data-bs-toggle="modal" data-bs-target="#readerSettingsModal" title="Pengaturan Tampilan Pembaca">
                <i class="bi bi-gear-fill text-warning"></i>
            </button>
            <button type="button" class="theme-toggle-btn" id="themeToggle" title="Ganti Mode Gelap/Terang">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
            </button>
        </div>
    </nav>

    <!-- Single Page Controls Top Bar -->
    <div class="single-page-controls" id="singlePageControlsTop">
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="prevSinglePage()"><i class="bi bi-chevron-left me-1"></i> Halaman Sebelumnya</button>
        <span class="fw-semibold small" id="pageIndicatorText">Halaman 1 / <?= count($images) ?></span>
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="nextSinglePage()">Halaman Selanjutnya <i class="bi bi-chevron-right ms-1"></i></button>
    </div>

    <!-- Reader Container -->
    <div class="reader d-flex flex-column align-items-center" id="readerContainer">
        <?php foreach ($images as $idx => $img): ?>
            <div class="page-wrapper skeleton-shimmer <?= $idx === 0 ? 'active-page' : '' ?>" data-page-number="<?= $idx + 1 ?>">
                <img src="<?= htmlspecialchars($chapter['base_url'] . $chapter['image_path'] . $img['filename']) ?>"
                     alt="Halaman <?= (int) $img['page_number'] ?>"
                     loading="lazy"
                     onload="this.classList.add('loaded'); this.parentElement.classList.remove('skeleton-shimmer'); this.parentElement.style.minHeight = 'auto';"
                     onerror="handleImageError(this, <?= (int) $img['page_number'] ?>, <?= json_encode($chapter['base_url'] . $chapter['image_path'] . $img['filename']) ?>)">
            </div>
        <?php endforeach; ?>

        <!-- End Chapter Card -->
        <div class="end-chapter-card">
            <div class="mb-2 text-warning fs-3"><i class="bi bi-check-circle-fill"></i></div>
            <h3 class="h5 brand-font mb-2">Anda Telah Selesai Membaca Chapter <?= (int) $chapter['chapter_number'] ?></h3>
            <p class="text-secondary small mb-3">Lanjutkan petualangan ke chapter selanjutnya atau kembali ke daftar chapter.</p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <?php if ($prevCh): ?>
                    <a href="reader.php?chapter_id=<?= urlencode($prevCh['chapter_id']) ?>" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-chevron-left me-1"></i> Ch. <?= (int) $prevCh['chapter_number'] ?>
                    </a>
                <?php endif; ?>
                <a href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-list me-1"></i> Daftar Chapter
                </a>
                <?php if ($nextCh): ?>
                    <a href="reader.php?chapter_id=<?= urlencode($nextCh['chapter_id']) ?>" class="btn btn-primary btn-sm px-3 fw-semibold">
                        Ch. <?= (int) $nextCh['chapter_number'] ?> Selanjutnya <i class="bi bi-chevron-right ms-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Floating Navigation Control -->
    <div class="floating-nav">
        <a href="index.php" class="btn" title="Beranda (Koleksi)"><i class="bi bi-house-fill"></i></a>
        <a href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>" class="btn" title="Detail Manga"><i class="bi bi-book-fill"></i></a>

        <button type="button" class="btn" onclick="goPrev()" title="Chapter Sebelumnya (Panah Kiri / A)" <?= $prevCh ? '' : 'disabled' ?>>
            <i class="bi bi-chevron-left"></i>
        </button>

        <select id="jumpSelect" title="Loncat ke chapter">
            <?php foreach ($allChapters as $c): ?>
                <option value="<?= urlencode($c['chapter_id']) ?>" <?= $c['chapter_id'] === $chapter['chapter_id'] ? 'selected' : '' ?>>
                    Ch. <?= (int) $c['chapter_number'] ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="button" class="btn" onclick="goNext()" title="Chapter Selanjutnya (Panah Kanan / D)" <?= $nextCh ? '' : 'disabled' ?>>
            <i class="bi bi-chevron-right"></i>
        </button>

        <button type="button" class="btn text-warning ms-1" id="shortcutHelpBtn" title="Shortcut Keyboard (?)">
            <i class="bi bi-keyboard"></i>
        </button>
    </div>

    <!-- Tap Zones -->
    <div class="tap-zone tap-zone-left d-none d-md-flex" onclick="handleTapZone('left')" title="Chapter/Halaman Sebelumnya">
        <i class="bi bi-chevron-left"></i>
    </div>
    <div class="tap-zone tap-zone-right d-none d-md-flex" onclick="handleTapZone('right')" title="Chapter/Halaman Selanjutnya">
        <i class="bi bi-chevron-right"></i>
    </div>

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

    <!-- Toast Shortcut Hint -->
    <div class="toast shortcut-toast" id="shortcutToast">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <strong class="text-primary"><i class="bi bi-keyboard me-1"></i> Keyboard Shortcuts</strong>
            <button type="button" class="btn-close btn-close-white btn-sm" onclick="document.getElementById('shortcutToast').style.display='none'"></button>
        </div>
        <ul class="list-unstyled mb-0 small text-secondary">
            <li><kbd>→</kbd> / <kbd>D</kbd> : Next Chapter / Page</li>
            <li><kbd>←</kbd> / <kbd>A</kbd> : Prev Chapter / Page</li>
            <li><kbd>Space</kbd> : Scroll Turun</li>
            <li><kbd>Home</kbd> / <kbd>End</kbd> : Puncak / Dasar Halaman</li>
        </ul>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const prevChapterId = <?= $prevCh ? json_encode($prevCh['chapter_id']) : 'null' ?>;
        const nextChapterId = <?= $nextCh ? json_encode($nextCh['chapter_id']) : 'null' ?>;
        const nextChapterImageUrls = <?= json_encode($nextChapterImageUrls) ?>;
        const totalPages = <?= count($images) ?>;
        let currentPageIndex = 0;

        function goPrev() {
            if (prevChapterId) window.location.href = "reader.php?chapter_id=" + encodeURIComponent(prevChapterId);
        }
        function goNext() {
            if (nextChapterId) window.location.href = "reader.php?chapter_id=" + encodeURIComponent(nextChapterId);
        }

        document.getElementById("jumpSelect").addEventListener("change", (e) => {
            window.location.href = "reader.php?chapter_id=" + encodeURIComponent(e.target.value);
        });

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
        updateThemeUI(localStorage.getItem("manga_theme") || "dark");
        themeToggleBtn.addEventListener("click", () => {
            const newTheme = document.documentElement.getAttribute("data-bs-theme") === "dark" ? "light" : "dark";
            updateThemeUI(newTheme);
        });

        // Reader Width & Layout Mode Settings
        const settingWidthSelect = document.getElementById("settingWidthSelect");
        const settingLayoutSelect = document.getElementById("settingLayoutSelect");
        const readerContainer = document.getElementById("readerContainer");
        const pageIndicatorText = document.getElementById("pageIndicatorText");
        const pages = Array.from(document.querySelectorAll(".page-wrapper"));

        function applyReaderSettings() {
            const width = localStorage.getItem("manga_reader_width") || "800px";
            const layout = localStorage.getItem("manga_reader_layout") || "webtoon";
            const endCard = document.querySelector(".end-chapter-card");

            readerContainer.style.maxWidth = width;
            settingWidthSelect.value = width;
            settingLayoutSelect.value = layout;

            if (layout === "single_page") {
                document.body.classList.add("mode-single-page");
                updateSinglePageUI();
            } else {
                document.body.classList.remove("mode-single-page");
                if (endCard) endCard.classList.remove("active-end-card");
            }
        }

        settingWidthSelect.addEventListener("change", (e) => {
            localStorage.setItem("manga_reader_width", e.target.value);
            applyReaderSettings();
        });

        settingLayoutSelect.addEventListener("change", (e) => {
            localStorage.setItem("manga_reader_layout", e.target.value);
            applyReaderSettings();
        });

        function updateSinglePageUI() {
            const endCard = document.querySelector(".end-chapter-card");
            pages.forEach((p, idx) => {
                p.classList.toggle("active-page", idx === currentPageIndex);
            });

            pageIndicatorText.textContent = `Halaman ${currentPageIndex + 1} / ${totalPages}`;

            if (endCard) {
                if (currentPageIndex === totalPages - 1) {
                    endCard.classList.add("active-end-card");
                } else {
                    endCard.classList.remove("active-end-card");
                }
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function prevSinglePage() {
            if (currentPageIndex > 0) {
                currentPageIndex--;
                updateSinglePageUI();
            } else {
                goPrev();
            }
        }

        function nextSinglePage() {
            if (currentPageIndex < totalPages - 1) {
                currentPageIndex++;
                updateSinglePageUI();
            } else {
                goNext();
            }
        }

        function handleTapZone(direction) {
            const layout = localStorage.getItem("manga_reader_layout") || "webtoon";
            if (layout === "single_page") {
                if (direction === "left") prevSinglePage();
                else nextSinglePage();
            } else {
                if (direction === "left") window.scrollBy({ top: -400, behavior: 'smooth' });
                else window.scrollBy({ top: 400, behavior: 'smooth' });
            }
        }

        applyReaderSettings();

        // Reading Progress Bar Indicator
        const progressBar = document.getElementById("progressBar");
        function updateProgressBar() {
            const layout = localStorage.getItem("manga_reader_layout") || "webtoon";
            if (layout === "single_page") {
                const pct = Math.min(100, Math.round(((currentPageIndex + 1) / totalPages) * 100));
                progressBar.style.width = pct + "%";
            } else {
                const scrollTotal = document.documentElement.scrollHeight - window.innerHeight;
                if (scrollTotal <= 0) { progressBar.style.width = "100%"; return; }
                const percentage = Math.min(100, Math.max(0, (window.scrollY / scrollTotal) * 100));
                progressBar.style.width = percentage + "%";
            }
        }
        window.addEventListener("scroll", updateProgressBar);
        window.addEventListener("resize", updateProgressBar);
        updateProgressBar();

        // Immersive Reading Mode: sembunyikan topbar & floating-nav saat scroll ke bawah,
        // tampilkan lagi saat scroll ke atas, dekat ujung atas/bawah chapter, atau saat
        // gambar chapter di-tap sekali (toggle).
        (function () {
            let lastScrollY = window.scrollY;
            const SCROLL_THRESHOLD = 10; // px, biar tidak ke-trigger getaran scroll kecil
            const EDGE_ZONE = 120; // px, jarak dari atas/bawah yang selalu memaksa UI tampil

            function hideUI() { document.body.classList.add("reader-hide-ui"); }
            function showUI() { document.body.classList.remove("reader-hide-ui"); }

            window.addEventListener("scroll", () => {
                const currentScrollY = window.scrollY;
                const delta = currentScrollY - lastScrollY;
                const scrollBottom = document.documentElement.scrollHeight - window.innerHeight - currentScrollY;

                if (currentScrollY < EDGE_ZONE || scrollBottom < EDGE_ZONE) {
                    // Dekat paling atas atau paling bawah chapter -> selalu tampilkan UI
                    showUI();
                } else if (Math.abs(delta) > SCROLL_THRESHOLD) {
                    if (delta > 0) hideUI(); else showUI();
                }

                lastScrollY = currentScrollY;
            }, { passive: true });

            // Tap sekali di gambar chapter -> toggle tampil/sembunyi UI.
            // Dibedakan dari swipe/scroll dengan mengecek jarak pergerakan jari.
            let touchStartX = 0, touchStartY = 0;
            const TAP_MOVE_TOLERANCE = 10; // px

            readerContainer.addEventListener("touchstart", (e) => {
                const t = e.touches[0];
                touchStartX = t.clientX;
                touchStartY = t.clientY;
            }, { passive: true });

            readerContainer.addEventListener("touchend", (e) => {
                // Jangan toggle kalau tap kena elemen interaktif (tombol reload gambar, dll)
                if (e.target.closest("button, a, .image-error-card, .single-page-controls")) return;

                const t = e.changedTouches[0];
                const movedX = Math.abs(t.clientX - touchStartX);
                const movedY = Math.abs(t.clientY - touchStartY);

                if (movedX < TAP_MOVE_TOLERANCE && movedY < TAP_MOVE_TOLERANCE) {
                    document.body.classList.toggle("reader-hide-ui");
                }
            });
        })();

        // Keyboard Shortcuts
        document.addEventListener("keydown", (e) => {
            if (["INPUT", "TEXTAREA", "SELECT"].includes(document.activeElement.tagName)) return;
            const layout = localStorage.getItem("manga_reader_layout") || "webtoon";

            if (e.key === "ArrowRight" || e.key === "d" || e.key === "D") {
                if (layout === "single_page") nextSinglePage(); else goNext();
            } else if (e.key === "ArrowLeft" || e.key === "a" || e.key === "A") {
                if (layout === "single_page") prevSinglePage(); else goPrev();
            } else if (e.key === "?" || e.key === "k" || e.key === "K") {
                const toast = document.getElementById("shortcutToast");
                toast.style.display = toast.style.display === "block" ? "none" : "block";
            }
        });

        document.getElementById("shortcutHelpBtn").addEventListener("click", () => {
            const toast = document.getElementById("shortcutToast");
            toast.style.display = toast.style.display === "block" ? "none" : "block";
        });

        // Image Fallback & Retry Handler
        function handleImageError(imgEl, pageNum, originalSrc) {
            const wrapper = imgEl.parentElement;
            wrapper.classList.remove("skeleton-shimmer");
            wrapper.style.minHeight = "auto";

            const errorCard = document.createElement("div");
            errorCard.className = "image-error-card";
            errorCard.innerHTML = `
                <i class="bi bi-exclamation-octagon-fill text-danger fs-3 mb-2 d-block"></i>
                <div class="small fw-semibold mb-2">Gambar Halaman ${pageNum} Gagal Dimuat</div>
                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="reloadChapterImage(this, '${originalSrc}')">
                    <i class="bi bi-arrow-repeat me-1"></i> Muat Ulang Gambar
                </button>
            `;
            imgEl.style.display = "none";
            wrapper.appendChild(errorCard);
        }

        function reloadChapterImage(btnEl, originalSrc) {
            const errorCard = btnEl.closest(".image-error-card");
            const wrapper = errorCard.parentElement;
            const imgEl = wrapper.querySelector("img");

            errorCard.remove();
            wrapper.classList.add("skeleton-shimmer");
            imgEl.style.display = "block";
            imgEl.src = originalSrc + "?retry=" + new Date().getTime();
        }

        // Background Preloading Gambar Chapter Selanjutnya
        if (nextChapterImageUrls && nextChapterImageUrls.length > 0) {
            window.addEventListener("load", () => {
                setTimeout(() => {
                    nextChapterImageUrls.forEach(url => { const img = new Image(); img.src = url; });
                }, 1000);
            });
        }
    </script>
</body>
</html>