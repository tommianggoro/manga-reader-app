<?php
require_once "config.php";
requireAuth();

$mangas = $pdo->query("SELECT * FROM mangas ORDER BY is_favorite DESC, title ASC")->fetchAll();

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root, [data-bs-theme="dark"] {
            --bs-body-bg: #0b0f17;
            --bs-body-color: #f1f3f5;
            --bs-primary: #f2a541;
            --bs-primary-rgb: 242, 165, 65;
            --bs-border-color: #1e2433;
            --bs-secondary-bg: #131824;
            --bs-tertiary-bg: #1b2234;
            --bs-link-color: #f2a541;
            --bs-link-hover-color: #f7bc70;
            --bs-card-bg: #131824;
        }
        
        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            letter-spacing: -0.01em;
        }
        .brand-font { font-family: 'Bitter', Georgia, serif; }

        .app-header { 
            padding: 1.5rem 0 1.25rem; 
            position: relative; 
            border-bottom: 1px solid var(--bs-border-color);
            margin-bottom: 1.5rem;
        }
        .app-header::before {
            content: ""; position: absolute; top: -80px; left: -80px; width: 320px; height: 320px;
            background: radial-gradient(circle, rgba(242,165,65,0.18) 0%, rgba(242,165,65,0) 70%);
            pointer-events: none;
        }
        /* Menghapus efek bulatan radial-gradient lama yang membuat tidak simetris */
        .app-header h1 { 
            margin: 0; 
            font-size: 1.75rem; 
            font-weight: 700;
            line-height: 1.2;
        }
        .app-header .subtitle { 
            color: #9a9fb0; 
            font-size: 0.875rem; 
            margin-top: 0.25rem;
            font-weight: 500;
        }

        .search-container {
            background: var(--bs-secondary-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 12px;
            padding: 1.25rem;
        }

        .form-control, .btn { border-radius: 8px; padding: 0.6rem 1rem; }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(242, 165, 65, 0.25);
            border-color: var(--bs-primary);
        }

        .add-form .form-control::placeholder { color: #5c6370; }

        .chip { 
            border-radius: 50rem; 
            padding: 0.4rem 1rem; 
            font-size: 0.85rem; 
            font-weight: 500;
            transition: all 0.2s ease;
            background: #1c2333;
            border-color: transparent;
            color: #adb5bd;
        }
        .chip:hover {
            background: #252f44;
            color: #fff;
        }
        .chip.active {
            background: var(--bs-primary) !important;
            color: #000 !important;
            font-weight: 600;
        }

        .manga-card { 
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
            border: 1px solid var(--bs-border-color); 
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .manga-card:hover { 
            transform: translateY(-6px); 
            box-shadow: 0 12px 20px rgba(0,0,0,0.3);
            border-color: rgba(242, 165, 65, 0.4);
        }
        .manga-card .img-container {
            position: relative;
            overflow: hidden;
        }
        .manga-card img { 
            aspect-ratio: 2/3; 
            object-fit: cover; 
            transition: transform 0.3s ease;
        }
        .manga-card:hover img {
            transform: scale(1.04);
        }
        .manga-card .card-title { 
            font-size: 0.95rem; 
            line-height: 1.4; 
            font-weight: 600;
            color: #f1f3f4;
        }

        .fav-star {
            width: 38px; height: 38px; padding: 0; border: none;
            background: rgba(11, 15, 23, 0.75); backdrop-filter: blur(8px);
            color: #adb5bd; font-size: 1.05rem;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .fav-star.active { color: var(--bs-primary); background: rgba(11, 15, 23, 0.85); }
        .fav-star:hover { background: rgba(11, 15, 23, 0.95); transform: scale(1.1); }

        .no-result { color: #6c757d; font-size: 1.1rem; }
        
        .badge-chapter {
            background-color: rgba(242, 165, 65, 0.12);
            color: var(--bs-primary);
            font-weight: 600;
            border: 1px solid rgba(242, 165, 65, 0.25);
        }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="app-header d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center gap-2">
        <div>
            <h1 class="brand-font d-flex align-items-center gap-2">
                <i class="bi bi-book-half text-primary"></i> Koleksi Manga Pribadi
            </h1>
            <div class="subtitle text-secondary">
                Aplikasi pembaca dan pengarsipan manga mandiri
            </div>
        </div>
        <div class="align-self-start align-self-sm-center">
            <span class="badge text-bg-secondary px-3 py-2 rounded-pill">
                <i class="bi bi-collection-play me-1"></i> <?= count($mangas) ?> Judul Tersimpan
            </span>
        </div>
    </div>

    <div class="search-container mb-4">
        <form class="row g-2 add-form mb-3" action="crawl.php" method="GET">
            <div class="col-md-9 col-sm-8">
                <input type="text" name="manga_id" class="form-control bg-body-bg border-secondary-subtle" placeholder="Tempel manga_id di sini untuk menambah atau memperbarui bab..." required>
            </div>
            <div class="col-md-3 col-sm-4 d-grid">
                <button type="submit" class="btn btn-primary fw-semibold text-dark"><i class="bi bi-plus-circle-fill me-1"></i> Tambah / Update</button>
            </div>
        </form>

        <div class="input-group">
            <span class="input-group-text bg-body-bg border-secondary-subtle text-secondary"><i class="bi bi-search"></i></span>
            <input type="text" id="searchInput" class="form-control bg-body-bg border-secondary-subtle" placeholder="Cari manga berdasarkan judul atau ID manga...">
        </div>
    </div>

    <div class="mb-3 small text-secondary fw-semibold text-uppercase tracking-wider"><i class="bi bi-filter-left me-1"></i> Filter Genre</div>
    <div class="d-flex flex-wrap gap-2 mb-4" id="filterChips">
        <button type="button" class="btn btn-sm chip fav-chip" id="favChip" data-filter="favorite">
            <i class="bi bi-star-fill me-1"></i> Favorit
        </button>
        <?php foreach ($allGenres as $genre): ?>
            <button type="button" class="btn btn-sm chip genre-chip" data-genre="<?= htmlspecialchars(mb_strtolower($genre)) ?>">
                <?= htmlspecialchars($genre) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-4" id="mangaGrid">
        <?php foreach ($mangas as $m): ?>
            <div class="col">
                <a class="card h-100 text-decoration-none manga-card bg-card-bg"
                   href="manga.php?manga_id=<?= urlencode($m['manga_id']) ?>"
                   data-title="<?= htmlspecialchars(mb_strtolower($m['title'])) ?>"
                   data-id="<?= htmlspecialchars(mb_strtolower($m['manga_id'])) ?>"
                   data-genres="<?= htmlspecialchars(mb_strtolower($m['genres'] ?? '')) ?>"
                   data-favorite="<?= $m['is_favorite'] ? '1' : '0' ?>">
                    <div class="img-container">
                        <img src="<?= htmlspecialchars($m['cover_image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($m['title']) ?>" loading="lazy">
                        <button type="button" class="btn position-absolute top-0 end-0 m-2 rounded-circle fav-star <?= $m['is_favorite'] ? 'active' : '' ?>"
                                data-manga-id="<?= htmlspecialchars($m['manga_id']) ?>">
                            <i class="bi <?= $m['is_favorite'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                        </button>
                    </div>
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div class="card-title mb-2 text-line-clamp"><?= htmlspecialchars($m['title']) ?></div>
                        <div>
                            <span class="badge badge-chapter px-2 py-1.5 rounded-3 small">Ch. <?= (int) $m['latest_chapter_number'] ?></span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
        <?php if (empty($mangas)): ?>
            <div class="col-12 text-center py-5 text-secondary">
                <i class="bi bi-journal-x display-1 mb-3 text-muted"></i>
                <p>Belum ada manga yang tersimpan. Tambahkan lewat formulir pencarian di atas.</p>
            </div>
        <?php endif; ?>
    </div>
    <div class="no-result text-center py-5" id="noResult" style="display:none">
        <i class="bi bi-patch-question display-4 d-block mb-3 text-muted"></i>
        Tidak ada manga yang cocok dengan kriteria Anda.
    </div>

</div>

<script>
    const searchInput = document.getElementById("searchInput");
    const cards = Array.from(document.querySelectorAll("#mangaGrid .manga-card"));
    const noResult = document.getElementById("noResult");
    const chips = Array.from(document.querySelectorAll(".chip"));

    let activeGenre = null;
    let favOnly = false;

    function setChipActive(chip, active) {
        chip.classList.toggle("active", active);
    }

    function applyFilters() {
        const q = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        cards.forEach(card => {
            const col = card.closest(".col");
            const matchSearch = card.dataset.title.includes(q) || card.dataset.id.includes(q);
            const matchGenre = !activeGenre || card.dataset.genres.includes(activeGenre);
            const matchFav = !favOnly || card.dataset.favorite === "1";
            const match = matchSearch && matchGenre && matchFav;

            col.style.display = match ? "" : "none";
            if (match) visibleCount++;
        });

        noResult.style.display = visibleCount === 0 ? "block" : "none";
    }

    searchInput.addEventListener("input", applyFilters);

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
                const icon = star.querySelector("i");
                star.classList.toggle("active", isFav);
                icon.classList.toggle("bi-star-fill", isFav);
                icon.classList.toggle("bi-star", !isFav);
                star.closest(".manga-card").dataset.favorite = isFav ? "1" : "0";
                applyFilters();
            } catch (err) {
                alert("Gagal update favorit: " + err.message);
            }
        });
    });
</script>
</body>
</html>