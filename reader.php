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
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%23f2a541'/%3E%3Ctext x='50' y='68' font-size='55' text-anchor='middle'%3E%F0%9F%93%96%3C/text%3E%3C/svg%3E">
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

        .topbar { background: var(--topbar-bg); border-bottom: 1px solid var(--topbar-border); transition: background 0.3s ease; }
        .topbar a { color: var(--bs-body-color); }

        .theme-toggle-btn {
            width: 34px; height: 34px; border-radius: 50%; padding: 0;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--topbar-border);
            background: transparent;
            color: var(--bs-body-color);
            transition: all 0.2s ease;
        }

        /* Chapter Images Wrapper & Skeleton */
        .reader { width: 100%; max-width: 800px; margin: 0 auto; }
        .page-wrapper {
            position: relative;
            width: 100%;
            min-height: 400px;
            background: var(--shimmer-bg-1);
            margin-bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .page-wrapper img {
            width: 100%; max-width: 800px; display: block; height: auto; opacity: 0;
            transition: opacity 0.35s ease;
        }
        .page-wrapper img.loaded { opacity: 1; }

        /* Skeleton Shimmer Loading */
        .skeleton-shimmer {
            background: linear-gradient(90deg, var(--shimmer-bg-1) 25%, var(--shimmer-bg-2) 50%, var(--shimmer-bg-1) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Floating Nav Bar */
        .floating-nav {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: rgba(20, 23, 31, 0.88); backdrop-filter: blur(10px);
            border-radius: 50rem; padding: 0.4rem 0.8rem;
            display: flex; align-items: center; gap: 0.4rem;
            box-shadow: 0 6px 25px rgba(0,0,0,0.5); z-index: 1000;
            border: 1px solid rgba(255,255,255,0.1);
        }
        [data-bs-theme="light"] .floating-nav {
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 6px 25px rgba(0,0,0,0.15);
            border: 1px solid rgba(0,0,0,0.1);
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
            max-width: 700px;
            margin: 2.5rem auto 1rem;
            background: var(--topbar-bg);
            border: 1px solid var(--topbar-border);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
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
        <a class="d-inline-flex align-items-center gap-2 text-decoration-none fw-medium text-truncate" href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>" style="max-width: 70%;">
            <i class="bi bi-arrow-left"></i> <span class="text-truncate"><?= htmlspecialchars($manga['title']) ?></span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-primary">Ch. <?= (int) $chapter['chapter_number'] ?></span>
            <button type="button" class="theme-toggle-btn" id="themeToggle" title="Ganti Mode Gelap/Terang">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
            </button>
        </div>
    </nav>

    <!-- Reader Container -->
    <div class="reader d-flex flex-column align-items-center">
        <?php foreach ($images as $img): ?>
            <div class="page-wrapper skeleton-shimmer">
                <img src="<?= htmlspecialchars($chapter['base_url'] . $chapter['image_path'] . $img['filename']) ?>"
                     alt="Halaman <?= (int) $img['page_number'] ?>"
                     loading="lazy"
                     onload="this.classList.add('loaded'); this.parentElement.classList.remove('skeleton-shimmer'); this.parentElement.style.minHeight = 'auto';">
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
    <div class="tap-zone tap-zone-left d-none d-md-flex" onclick="goPrev()" title="Klik untuk Chapter Sebelumnya">
        <i class="bi bi-chevron-left"></i>
    </div>
    <div class="tap-zone tap-zone-right d-none d-md-flex" onclick="goNext()" title="Klik untuk Chapter Selanjutnya">
        <i class="bi bi-chevron-right"></i>
    </div>

    <!-- Keyboard Shortcuts Modal Hint -->
    <div class="toast shortcut-toast" id="shortcutToast">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <strong class="text-primary"><i class="bi bi-keyboard me-1"></i> Keyboard Shortcuts</strong>
            <button type="button" class="btn-close btn-close-white btn-sm" onclick="document.getElementById('shortcutToast').style.display='none'"></button>
        </div>
        <ul class="list-unstyled mb-0 small text-secondary">
            <li><kbd>→</kbd> / <kbd>D</kbd> : Chapter Selanjutnya</li>
            <li><kbd>←</kbd> / <kbd>A</kbd> : Chapter Sebelumnya</li>
            <li><kbd>Space</kbd> : Scroll Turun</li>
            <li><kbd>Home</kbd> / <kbd>End</kbd> : Puncak / Dasar Halaman</li>
        </ul>
    </div>

    <script>
        const prevChapterId = <?= $prevCh ? json_encode($prevCh['chapter_id']) : 'null' ?>;
        const nextChapterId = <?= $nextCh ? json_encode($nextCh['chapter_id']) : 'null' ?>;
        const nextChapterImageUrls = <?= json_encode($nextChapterImageUrls) ?>;

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

        const currentTheme = localStorage.getItem("manga_theme") || "dark";
        updateThemeUI(currentTheme);

        themeToggleBtn.addEventListener("click", () => {
            const newTheme = document.documentElement.getAttribute("data-bs-theme") === "dark" ? "light" : "dark";
            updateThemeUI(newTheme);
        });

        // Reading Progress Bar Indicator
        const progressBar = document.getElementById("progressBar");
        function updateProgressBar() {
            const scrollTotal = document.documentElement.scrollHeight - window.innerHeight;
            if (scrollTotal <= 0) {
                progressBar.style.width = "100%";
                return;
            }
            const currentScroll = window.scrollY;
            const percentage = Math.min(100, Math.max(0, (currentScroll / scrollTotal) * 100));
            progressBar.style.width = percentage + "%";
        }
        window.addEventListener("scroll", updateProgressBar);
        window.addEventListener("resize", updateProgressBar);
        updateProgressBar();

        // Keyboard Shortcuts
        document.addEventListener("keydown", (e) => {
            if (["INPUT", "TEXTAREA", "SELECT"].includes(document.activeElement.tagName)) return;

            if (e.key === "ArrowRight" || e.key === "d" || e.key === "D") {
                if (nextChapterId) goNext();
            } else if (e.key === "ArrowLeft" || e.key === "a" || e.key === "A") {
                if (prevChapterId) goPrev();
            } else if (e.key === "?" || e.key === "k" || e.key === "K") {
                const toast = document.getElementById("shortcutToast");
                toast.style.display = toast.style.display === "block" ? "none" : "block";
            }
        });

        document.getElementById("shortcutHelpBtn").addEventListener("click", () => {
            const toast = document.getElementById("shortcutToast");
            toast.style.display = toast.style.display === "block" ? "none" : "block";
        });

        // Background Preloading Gambar Chapter Selanjutnya
        if (nextChapterImageUrls && nextChapterImageUrls.length > 0) {
            window.addEventListener("load", () => {
                setTimeout(() => {
                    nextChapterImageUrls.forEach(url => {
                        const img = new Image();
                        img.src = url;
                    });
                }, 1000);
            });
        }
    </script>
</body>
</html>