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

        .cover-img-wrap {
            width: 140px;
            aspect-ratio: 2/3;
            border-radius: 0.75rem;
            overflow: hidden;
            flex-shrink: 0;
            background: var(--shimmer-bg-1);
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .cover-img-wrap img {
            width: 100%; height: 100%; object-fit: cover; opacity: 0;
            transition: opacity 0.35s ease;
        }
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
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-secondary-bg);
            color: var(--bs-body-color);
            transition: all 0.2s ease;
        }

        .description-card { background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); border-radius: 12px; }

        .chapter-list-wrap { max-height: 520px; overflow-y: auto; border-radius: 12px; border: 1px solid var(--bs-border-color); }
        .list-group-item { background: var(--bs-secondary-bg); border-color: var(--bs-border-color); transition: all 0.15s ease; }
        .list-group-item.current-read { border-left: 4px solid var(--bs-primary); background: var(--bs-tertiary-bg); }
        .list-group-item-action:hover { background: var(--bs-tertiary-bg); transform: translateX(3px); }

        .no-result { color: #7c8194; }
    </style>
</head>
<body>
<div class="container py-3" style="max-width: 850px;">

    <!-- Top Bar Navigation -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <a class="d-inline-flex align-items-center gap-1 text-decoration-none fw-medium" href="index.php">
            <i class="bi bi-arrow-left"></i> Kembali ke koleksi
        </a>
        <button type="button" class="theme-toggle-btn" id="themeToggle" title="Ganti Mode Gelap/Terang">
            <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
        </button>
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
                <span class="badge rounded-pill text-bg-primary"><i class="bi bi-journals me-1"></i><?= (int) count($chapters) ?> Chapter</span>
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

    <!-- Action Buttons (Resume/Start) -->
    <?php if ($firstChapter): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-4 p-3 rounded-3" style="background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color);">
            <?php if (!empty($manga['last_read_chapter_id'])): ?>
                <a class="btn btn-primary fw-semibold px-3" href="reader.php?chapter_id=<?= urlencode($manga['last_read_chapter_id']) ?>">
                    <i class="bi bi-play-fill fs-5 me-1"></i> Lanjutkan Chapter <?= (int) $manga['last_read_chapter_number'] ?>
                </a>
                <a class="btn btn-outline-secondary px-3" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                    <i class="bi bi-book me-1"></i> Baca dari Awal
                </a>
                <span class="badge text-bg-secondary ms-sm-auto"><i class="bi bi-bookmark-check me-1"></i> Terakhir dibaca: Ch. <?= (int) $manga['last_read_chapter_number'] ?></span>
            <?php else: ?>
                <a class="btn btn-primary fw-semibold px-4" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                    <i class="bi bi-book me-1"></i> Mulai Baca dari Awal
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

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
                        Chapter <?= (int) $ch['chapter_number'] ?>
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
            item.style.display = match ? "" : "none";
            if (match) visibleCount++;
        });
        noResult.style.display = visibleCount === 0 ? "block" : "none";
    });

    // Toggle favorit tanpa reload halaman
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
        } catch (err) {
            alert("Gagal update favorit: " + err.message);
        }
    });
</script>
</body>
</html>