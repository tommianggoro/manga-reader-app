<?php
require_once "config.php";
requireAuth();

$mangas = $pdo->query("SELECT * FROM mangas ORDER BY title ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Koleksi Manga Pribadi</title>
    <style>
        body { font-family: sans-serif; background: #1a1a1a; color: #eee; margin: 0; padding: 1.5rem; }
        h1 { margin-bottom: 1rem; }
        .add-form { background: #2a2a2a; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; gap: 0.5rem; }
        .add-form input { flex: 1; padding: 0.6rem; border-radius: 4px; border: none; }
        .add-form button { padding: 0.6rem 1.2rem; background: #4a90d9; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .search-box { margin-bottom: 1.5rem; }
        .search-box input { width: 100%; padding: 0.7rem 1rem; border-radius: 6px; border: none; box-sizing: border-box; background: #2a2a2a; color: #eee; font-size: 1rem; }
        .search-box input::placeholder { color: #888; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
        .card { background: #2a2a2a; border-radius: 8px; overflow: hidden; text-decoration: none; color: #eee; transition: transform 0.15s; }
        .card:hover { transform: scale(1.03); }
        .card img { width: 100%; aspect-ratio: 2/3; object-fit: cover; display: block; }
        .card-title { padding: 0.5rem; font-size: 0.9rem; }
        .card-ch { padding: 0 0.5rem 0.5rem; font-size: 0.8rem; color: #999; }
        .no-result { color: #999; grid-column: 1 / -1; text-align: center; padding: 2rem; }
    </style>
</head>
<body>
    <h1>📚 Koleksi Manga Pribadi</h1>

    <form class="add-form" action="crawl.php" method="GET">
        <input type="text" name="manga_id" placeholder="Tempel manga_id di sini untuk menambah/update..." required>
        <button type="submit">Tambah / Update</button>
    </form>

    <div class="search-box">
        <input type="text" id="searchInput" placeholder="🔍 Cari manga (judul atau manga_id)...">
    </div>

    <div class="grid" id="mangaGrid">
        <?php foreach ($mangas as $m): ?>
            <a class="card" href="manga.php?manga_id=<?= urlencode($m['manga_id']) ?>"
               data-title="<?= htmlspecialchars(mb_strtolower($m['title'])) ?>"
               data-id="<?= htmlspecialchars(mb_strtolower($m['manga_id'])) ?>">
                <img src="<?= htmlspecialchars($m['cover_image_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>" loading="lazy">
                <div class="card-title"><?= htmlspecialchars($m['title']) ?></div>
                <div class="card-ch">Ch. <?= (int) $m['latest_chapter_number'] ?></div>
            </a>
        <?php endforeach; ?>
        <?php if (empty($mangas)): ?>
            <p>Belum ada manga. Tambahkan lewat form di atas (pakai manga_id dari URL Shinigami).</p>
        <?php endif; ?>
    </div>
    <p class="no-result" id="noResult" style="display:none">Tidak ada manga yang cocok.</p>

    <script>
        const searchInput = document.getElementById("searchInput");
        const cards = Array.from(document.querySelectorAll("#mangaGrid .card"));
        const noResult = document.getElementById("noResult");

        searchInput.addEventListener("input", () => {
            const q = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;
            cards.forEach(card => {
                const match = card.dataset.title.includes(q) || card.dataset.id.includes(q);
                card.style.display = match ? "" : "none";
                if (match) visibleCount++;
            });
            noResult.style.display = visibleCount === 0 ? "block" : "none";
        });
    </script>
</body>
</html>
