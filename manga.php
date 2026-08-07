<?php
require_once "config.php";
requireAuth();

$mangaId = $_GET["manga_id"] ?? die("manga_id wajib diisi");

$stmt = $pdo->prepare("SELECT * FROM mangas WHERE manga_id = :id");
$stmt->execute([":id" => $mangaId]);
$manga = $stmt->fetch();
if (!$manga) die("Manga tidak ditemukan di database.");

$stmt = $pdo->prepare("SELECT * FROM chapters WHERE manga_id = :id ORDER BY chapter_number DESC");
$stmt->execute([":id" => $mangaId]);
$chapters = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT chapter_id, chapter_number FROM chapters WHERE manga_id = :id ORDER BY chapter_number ASC LIMIT 1");
$stmt->execute([":id" => $mangaId]);
$firstChapter = $stmt->fetch();

// Helper format tanggal singkat ala Indonesia, mis. "29 Jul 2026"
function formatTanggalIndo($datetime) {
    $bulan = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
    $ts = strtotime($datetime);
    return date("j", $ts) . " " . $bulan[(int) date("n", $ts) - 1] . " " . date("Y", $ts);
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($manga['title']) ?> - Detail Manga</title>
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

        body { font-family: 'Inter', system-ui, sans-serif; transition: background-color 0.3s ease, color 0.3s ease; }
        .brand-font { font-family: 'Bitter', Georgia, serif; }

        .cover-img-wrap {
            width: 140px; aspect-ratio: 2/3; border-radius: 0.75rem; overflow: hidden; flex-shrink: 0;
            background: var(--shimmer-bg-1); position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .cover-img-wrap img { width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 0.35s ease; }
        .cover-img-wrap img.loaded { opacity: 1; }

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

        .fav-star-btn {
            width: 40px; height: 40px; border: 1px solid var(--bs-border-color); background: var(--bs-tertiary-bg);
            color: #9a9fb0; font-size: 1.2rem; display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.2s ease;
        }
        .fav-star-btn.active { color: var(--bs-primary); border-color: var(--bs-primary); }
        .fav-star-btn:hover { transform: scale(1.05); }

        .theme-toggle-btn {
            width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--bs-border-color); background: var(--bs-secondary-bg); color: var(--bs-body-color);
            transition: all 0.2s ease;
        }

        .description-card { background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); border-radius: 12px; }

        .chapter-list-wrap { max-height: 520px; overflow-y: auto; border-radius: 12px; border: 1px solid var(--bs-border-color); }
        .list-group-item { background: var(--bs-secondary-bg); border-color: var(--bs-border-color); transition: all 0.15s ease; }
        .list-group-item.current-read { border-left: 4px solid var(--bs-primary); background: var(--bs-tertiary-bg); }
        .list-group-item-action:hover { background: var(--bs-tertiary-bg); transform: translateX(3px); }

        .no-result { color: #7c8194; }
        /* Highlight chapter baru hasil sync background */
        @keyframes newChapterRowFlash {
            0%   { background-color: rgba(var(--bs-primary-rgb), 0.35); }
            100% { background-color: var(--bs-secondary-bg); }
        }
        .list-group-item.just-added {
            animation: newChapterRowFlash 2s ease-out;
        }
    </style>
</head>
<body>
<div class="container py-3" style="max-width: 850px;">

    <!-- Top Bar Navigation -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <a class="d-inline-flex align-items-center gap-1 text-decoration-none fw-medium" href="index.php">
            <i class="bi bi-arrow-left"></i> Kembali ke koleksi
        </a>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteMangaModal" title="Hapus Manga Ini">
                <i class="bi bi-trash3 me-1"></i> Hapus Manga
            </button>
            <button type="button" class="theme-toggle-btn" id="themeToggle" title="Ganti Mode Gelap/Terang">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
            </button>
        </div>
    </div>

    <!-- Manga Main Header Card -->
    <div class="d-flex gap-3 mb-4 flex-column flex-sm-row">
        <div class="cover-img-wrap skeleton-shimmer mx-auto mx-sm-0">
            <img src="<?= htmlspecialchars($manga['cover_image_url']) ?>" alt="<?= htmlspecialchars($manga['title']) ?>" onload="this.classList.add('loaded'); this.parentElement.classList.remove('skeleton-shimmer');">
        </div>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="brand-font h3 mb-0"><?= htmlspecialchars($manga['title']) ?></h1>
                <button type="button" class="btn rounded-circle fav-star-btn <?= !empty($manga['is_favorite']) ? 'active' : '' ?>"
                        id="favBtn" data-manga-id="<?= htmlspecialchars($manga['manga_id']) ?>" title="Favoritkan Manga">
                    <i class="bi <?= !empty($manga['is_favorite']) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                </button>
            </div>
            <?php if (!empty($manga['alternative_title'])): ?>
                <p class="text-secondary small mb-2"><?= htmlspecialchars($manga['alternative_title']) ?></p>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-1 mb-2">
                <?php if (!empty($manga['author'])): ?><span class="badge rounded-pill text-bg-secondary"><i class="bi bi-person me-1"></i><?= htmlspecialchars($manga['author']) ?></span><?php endif; ?>
                <?php if (!empty($manga['artist'])): ?><span class="badge rounded-pill text-bg-secondary"><i class="bi bi-palette me-1"></i><?= htmlspecialchars($manga['artist']) ?></span><?php endif; ?>
                <?php if (!empty($manga['release_year'])): ?><span class="badge rounded-pill text-bg-secondary"><i class="bi bi-calendar-event me-1"></i><?= htmlspecialchars($manga['release_year']) ?></span><?php endif; ?>
                <?php if (!empty($manga['rating'])): ?><span class="badge rounded-pill text-bg-warning text-dark"><i class="bi bi-star-fill me-1"></i><?= htmlspecialchars($manga['rating']) ?></span><?php endif; ?>
                <span class="badge rounded-pill text-bg-primary" id="totalChapterBadge"><i class="bi bi-journals me-1"></i><span id="totalChapterCount"><?= (int) count($chapters) ?></span> Chapter</span>
            </div>
            <?php if (!empty($manga['genres'])): ?>
                <div class="small text-secondary"><i class="bi bi-tags me-1"></i><?= htmlspecialchars($manga['genres']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Deskripsi Manga -->
    <?php if (!empty($manga['description'])): ?>
        <div class="card description-card mb-4">
            <div class="card-body small" style="line-height: 1.65; color: var(--bs-body-color);"><?= nl2br(htmlspecialchars($manga['description'])) ?></div>
        </div>
    <?php endif; ?>

    <!-- Action Buttons (Resume/Start/Update) -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4 p-3 rounded-3" style="background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color);">
        <?php if ($firstChapter): ?>
            <?php if (!empty($manga['last_read_chapter_id'])): ?>
                <a class="btn btn-primary fw-semibold px-3" href="reader.php?chapter_id=<?= urlencode($manga['last_read_chapter_id']) ?>">
                    <i class="bi bi-play-fill fs-5 me-1"></i> Lanjutkan Chapter <?= (float) $manga['last_read_chapter_number'] ?>
                </a>
                <a class="btn btn-outline-secondary px-3" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                    <i class="bi bi-book me-1"></i> Baca dari Awal
                </a>
            <?php else: ?>
                <a class="btn btn-primary fw-semibold px-4" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                    <i class="bi bi-book me-1"></i> Mulai Baca dari Awal
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <a class="btn btn-outline-warning ms-auto" href="crawl.php?manga_id=<?= urlencode($manga['manga_id']) ?>" target="_blank" title="Cek & Sync Chapter Baru">
            <i class="bi bi-arrow-repeat me-1"></i> Update / Sync Chapter
        </a>
    </div>

    <!-- Chapter Search & List Header -->
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h2 class="h5 brand-font mb-0"><i class="bi bi-list-nested me-1"></i> Daftar Chapter</h2>
        <span class="small text-secondary">Diurutkan Terbaru</span>
    </div>

    <div class="input-group mb-3">
        <span class="input-group-text bg-body-tertiary border-secondary-subtle"><i class="bi bi-search"></i></span>
        <input type="text" id="searchInput" class="form-control" placeholder="Cari chapter (ketik nomor atau judul)...">
    </div>

    <div class="chapter-list-wrap">
        <div class="list-group list-group-flush" id="chapterList">
            <?php foreach ($chapters as $ch): ?>
                <?php $isCurrent = !empty($manga['last_read_chapter_id']) && $manga['last_read_chapter_id'] === $ch['chapter_id']; ?>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 <?= $isCurrent ? 'current-read' : '' ?>"
                   href="reader.php?chapter_id=<?= urlencode($ch['chapter_id']) ?>"
                   data-search="<?= htmlspecialchars(mb_strtolower($ch['chapter_number'] . ' ' . ($ch['chapter_title'] ?? ''))) ?>">
                    <span class="fw-medium">
                        Chapter <?= (float) $ch['chapter_number'] ?>
                        <?= $ch['chapter_title'] ? " — <span class='text-secondary font-normal'>" . htmlspecialchars($ch['chapter_title']) . "</span>" : "" ?>
                    </span>
                    <span class="d-flex align-items-center gap-2 flex-shrink-0">
                        <small class="text-secondary"><?= formatTanggalIndo($ch['updated_at'] ?? $ch['created_at']) ?></small>
                        <?php if ($isCurrent): ?><i class="bi bi-bookmark-fill text-primary" title="Chapter terakhir dibaca"></i><?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="no-result text-center py-4 mb-0" id="noResult" style="display:none">Tidak ada chapter yang cocok.</p>
    </div>

</div>

<!-- Modal Konfirmasi Hapus Manga -->
<div class="modal fade" id="deleteMangaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header text-danger">
                <h5 class="modal-title brand-font"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Manga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus manga <strong><?= htmlspecialchars($manga['title']) ?></strong> dari koleksi Anda?
                <p class="text-danger small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i> Tindakan ini akan menghapus seluruh data chapter dan histori bacaan secara permanen.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn"><i class="bi bi-trash3 me-1"></i> Hapus Permanen</button>
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
    // Theme Switcher
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

    // Chapter search filter
    const searchInput = document.getElementById("searchInput");
    const items = Array.from(document.querySelectorAll("#chapterList a"));
    const noResult = document.getElementById("noResult");

    searchInput.addEventListener("input", () => {
        const q = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;
        items.forEach(item => {
            const match = item.dataset.search.includes(q);
            item.classList.toggle("d-none", !match);
            if (match) visibleCount++;
        });
        noResult.style.display = visibleCount === 0 ? "block" : "none";
    });

    // Toggle favorit
    const favBtn = document.getElementById("favBtn");
    favBtn.addEventListener("click", async () => {
        try {
            const res = await fetch("toggle_favorite.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "manga_id=" + encodeURIComponent(favBtn.dataset.mangaId),
            });
            const data = await res.json();
            if (!data.success) { alert("Gagal update favorit: " + data.error); return; }

            const icon = favBtn.querySelector("i");
            favBtn.classList.toggle("active", data.is_favorite);
            icon.classList.toggle("bi-star-fill", data.is_favorite);
            icon.classList.toggle("bi-star", !data.is_favorite);
        } catch (err) { alert("Gagal update favorit: " + err.message); }
    });

    // Hapus Single Manga
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    confirmDeleteBtn.addEventListener("click", async () => {
        try {
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = "Menghapus...";

            const res = await fetch("delete_manga.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "manga_id=" + encodeURIComponent(<?= json_encode($mangaId) ?>)
            });
            const data = await res.json();
            if (!data.success) {
                alert("Gagal menghapus manga: " + data.error);
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Hapus Permanen';
                return;
            }
            window.location.href = "index.php";
        } catch (err) {
            alert("Error: " + err.message);
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Hapus Permanen';
        }
    });

    // ==== Auto-Update Background (Polling, Tanpa Reload) ====
    // Sync data dilakukan oleh cron (GitHub Actions / server cron) lewat cron_update.php.
    // Skrip ini hanya memeriksa server secara berkala untuk manga yang sedang dibuka,
    // dan menyisipkan chapter baru ke daftar tanpa perlu reload halaman.

    const POLL_INTERVAL_MS = 45000; // cek server tiap 45 detik
    const currentMangaId = <?= json_encode($mangaId) ?>;
    let knownLatestChapter = <?= (float) ($chapters[0]['chapter_number'] ?? 0) ?>;

    function showNewChapterToast(count) {
        const toastEl = document.getElementById('newChapterToast');
        const textEl = document.getElementById('newChapterToastText');
        textEl.innerHTML = `<i class="bi bi-stars me-1"></i> ` +
            (count === 1 ? '1 chapter baru tersedia!' : `${count} chapter baru tersedia!`);
        bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 6000 }).show();
    }

    function prependNewChapters(newChapters) {
        const list = document.getElementById("chapterList");
        // API mengembalikan urutan ASC (lama -> baru); kita masukkan dari yang
        // paling baru dulu ke posisi paling atas, supaya urutan akhir tetap DESC.
        const ordered = [...newChapters].reverse();

        ordered.forEach(ch => {
            const a = document.createElement("a");
            a.className = "list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 just-added";
            a.href = "reader.php?chapter_id=" + encodeURIComponent(ch.chapter_id);
            a.dataset.search = (ch.chapter_number + " " + (ch.chapter_title || "")).toLowerCase();

            const titlePart = ch.chapter_title
                ? ` — <span class="text-secondary font-normal">${ch.chapter_title.replace(/</g, "&lt;")}</span>`
                : "";

            a.innerHTML = `
                <span class="fw-medium">Chapter ${ch.chapter_number}${titlePart}</span>
                <span class="d-flex align-items-center gap-2 flex-shrink-0">
                    <small class="text-secondary">${ch.date_label}</small>
                    <span class="badge text-bg-primary">Baru</span>
                </span>
            `;

            list.prepend(a);
            items.unshift(a); // supaya ikut ter-cover oleh filter pencarian yang sudah ada

            setTimeout(() => a.classList.remove("just-added"), 2500);
        });
    }

    async function pollForMangaUpdates() {
        try {
            const res = await fetch(`check_manga_updates.php?manga_id=${encodeURIComponent(currentMangaId)}&since=${knownLatestChapter}`, { cache: 'no-store' });
            const data = await res.json();
            if (!data.success) return;

            if (data.new_chapters && data.new_chapters.length > 0) {
                prependNewChapters(data.new_chapters);
                knownLatestChapter = data.latest_chapter_number;

                const countEl = document.getElementById("totalChapterCount");
                if (countEl) countEl.textContent = data.total_chapters;

                showNewChapterToast(data.new_chapters.length);
            }
        } catch (err) {
            console.warn('Gagal memeriksa update background:', err.message);
        }
    }

    let mangaPollTimer = setInterval(pollForMangaUpdates, POLL_INTERVAL_MS);

    // Hemat resource: berhenti polling saat tab tidak aktif, cek langsung saat kembali aktif
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(mangaPollTimer);
            mangaPollTimer = null;
        } else {
            pollForMangaUpdates();
            if (!mangaPollTimer) mangaPollTimer = setInterval(pollForMangaUpdates, POLL_INTERVAL_MS);
        }
    });

    // Multi-tab: kalau user sync manual manga ini lewat crawl.php di tab lain,
    // halaman ini langsung ikut ter-update juga tanpa nunggu polling interval.
    if (window.BroadcastChannel) {
        const updateChannel = new BroadcastChannel('manga_reader_updates');
        updateChannel.addEventListener('message', (event) => {
            const msg = event.data;
            if (msg && msg.type === 'manga_updated' && msg.manga_id === currentMangaId) {
                pollForMangaUpdates();
            }
        });
    }
</script>
</body>
</html>