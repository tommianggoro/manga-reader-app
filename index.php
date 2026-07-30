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
            --bs-body-bg: #14171f;
            --bs-body-color: #eae7e0;
            --bs-primary: #f2a541;
            --bs-primary-rgb: 242, 165, 65;
            --bs-border-color: #2a2f3d;
            --bs-secondary-bg: #1c2029;
            --bs-tertiary-bg: #242938;
            --bs-link-color: #f2a541;
            --bs-link-hover-color: #f7bc70;
            --bs-card-bg: #1c2029;
        }
        body { font-family: 'Inter', system-ui, sans-serif; }
        .brand-font { font-family: 'Bitter', Georgia, serif; }

        .app-header { padding: 2rem 0 1.5rem; position: relative; overflow: hidden; }
        .app-header::before {
            content: ""; position: absolute; top: -60px; left: -60px; width: 260px; height: 260px;
            background: radial-gradient(circle, rgba(242,165,65,0.25) 0%, rgba(242,165,65,0) 70%);
            pointer-events: none;
        }
        .app-header h1 { position: relative; margin: 0; }
        .app-header .subtitle { color: #9a9fb0; font-size: 0.95rem; }

        .add-form .form-control::placeholder { color: #7c8194; }

        .chip { border-radius: 50rem; }

        .manga-card { transition: transform 0.15s ease; border-color: var(--bs-border-color); }
        .manga-card:hover { transform: translateY(-4px); }
        .manga-card img { aspect-ratio: 2/3; object-fit: cover; }
        .manga-card .card-title { font-size: 0.9rem; line-height: 1.3; }

        .fav-star {
            width: 36px; height: 36px; padding: 0; border: none;
            background: rgba(20, 23, 31, 0.72); backdrop-filter: blur(2px);
            color: #ccc; font-size: 1rem;
        }
        .fav-star.active { color: var(--bs-primary); }
        .fav-star:hover { background: rgba(20, 23, 31, 0.9); }

        .no-result { color: #7c8194; }
    </style>
</head>
<body>
<div class="container py-3">

    <div class="app-header">
        <h1 class="brand-font"><i class="bi bi-book-half text-primary"></i> Koleksi Manga Pribadi</h1>
        <div class="subtitle"><?= count($mangas) ?> judul tersimpan</div>
    </div>

    <form class="row g-2 add-form mb-3" action="crawl.php" method="GET">
        <div class="col">
            <input type="text" name="manga_id" class="form-control" placeholder="Tempel manga_id di sini untuk menambah/update..." required>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah / Update</button>
        </div>
    </form>

    <div class="input-group mb-3">
        <span class="input-group-text bg-body-tertiary border-secondary-subtle"><i class="bi bi-search"></i></span>
        <input type="text" id="searchInput" class="form-control" placeholder="Cari manga (judul atau manga_id)...">
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4" id="filterChips">
        <button type="button" class="btn btn-sm btn-outline-secondary chip fav-chip" id="favChip" data-filter="favorite">
            <i class="bi bi-star-fill"></i> Favorit
        </button>
        <?php foreach ($allGenres as $genre): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary chip genre-chip" data-genre="<?= htmlspecialchars(mb_strtolower($genre)) ?>">
                <?= htmlspecialchars($genre) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3" id="mangaGrid">
        <?php foreach ($mangas as $m): ?>
            <div class="col">
                <a class="card h-100 text-decoration-none manga-card"
                   href="manga.php?manga_id=<?= urlencode($m['manga_id']) ?>"
                   data-title="<?= htmlspecialchars(mb_strtolower($m['title'])) ?>"
                   data-id="<?= htmlspecialchars(mb_strtolower($m['manga_id'])) ?>"
                   data-genres="<?= htmlspecialchars(mb_strtolower($m['genres'] ?? '')) ?>"
                   data-favorite="<?= $m['is_favorite'] ? '1' : '0' ?>">
                    <div class="position-relative">
                        <img src="<?= htmlspecialchars($m['cover_image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($m['title']) ?>" loading="lazy">
                        <button type="button" class="btn position-absolute top-0 end-0 m-2 rounded-circle fav-star <?= $m['is_favorite'] ? 'active' : '' ?>"
                                data-manga-id="<?= htmlspecialchars($m['manga_id']) ?>">
                            <i class="bi <?= $m['is_favorite'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                        </button>
                    </div>
                    <div class="card-body p-2">
                        <div class="card-title text-truncate"><?= htmlspecialchars($m['title']) ?></div>
                        <span class="badge text-bg-secondary">Ch. <?= (int) $m['latest_chapter_number'] ?></span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
        <?php if (empty($mangas)): ?>
            <div class="col-12 text-center py-5 text-secondary">
                Belum ada manga. Tambahkan lewat form di atas (pakai manga_id dari URL Shinigami).
            </div>
        <?php endif; ?>
    </div>
    <p class="no-result text-center py-4" id="noResult" style="display:none">Tidak ada manga yang cocok.</p>

</div>

<script>
    const searchInput = document.getElementById("searchInput");
    const cards = Array.from(document.querySelectorAll("#mangaGrid .manga-card"));
    const noResult = document.getElementById("noResult");
    const chips = Array.from(document.querySelectorAll(".chip"));

    let activeGenre = null;
    let favOnly = false;

    function setChipActive(chip, active) {
        chip.classList.toggle("btn-primary", active);
        chip.classList.toggle("btn-outline-secondary", !active);
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