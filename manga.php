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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($manga['title']) ?></title>
    <style>
        body { font-family: sans-serif; background: #1a1a1a; color: #eee; margin: 0; padding: 1.5rem; max-width: 800px; margin: 0 auto; }
        a { color: #4a90d9; }
        .back-link { display: inline-block; margin-bottom: 1rem; }
        .header { display: flex; gap: 1.2rem; margin-bottom: 1rem; }
        .header img { width: 130px; border-radius: 8px; flex-shrink: 0; }
        .header h1 { margin: 0 0 0.3rem; font-size: 1.4rem; }
        .alt-title { color: #999; font-size: 0.9rem; margin: 0 0 0.5rem; }
        .meta { font-size: 0.85rem; color: #ccc; line-height: 1.6; }
        .meta b { color: #eee; }
        .description { background: #2a2a2a; padding: 1rem; border-radius: 8px; margin: 1rem 0; font-size: 0.9rem; line-height: 1.5; color: #ccc; }
        .continue-btn { display: inline-block; background: #4a90d9; color: white; padding: 0.7rem 1.2rem; border-radius: 6px; text-decoration: none; margin-bottom: 1rem; font-weight: bold; }
        .last-read-badge { display: inline-block; background: #333; color: #ffd166; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; margin-left: 0.5rem; }
        .search-box input { width: 100%; padding: 0.7rem 1rem; border-radius: 6px; border: none; box-sizing: border-box; background: #2a2a2a; color: #eee; font-size: 1rem; margin-bottom: 0.8rem; }
        .chapter-list-wrap { max-height: 480px; overflow-y: auto; border-radius: 8px; }
        .chapter-list { list-style: none; padding: 0; margin: 0; }
        .chapter-list li a { display: block; padding: 0.8rem; background: #2a2a2a; margin-bottom: 0.5rem; border-radius: 6px; text-decoration: none; color: #eee; }
        .chapter-list li a:hover { background: #3a3a3a; }
        .chapter-list li a.current-read { border-left: 4px solid #ffd166; }
        .no-result { color: #999; text-align: center; padding: 1.5rem; }
    </style>
</head>
<body>
    <a class="back-link" href="index.php">&larr; Kembali ke koleksi</a>

    <div class="header">
        <img src="<?= htmlspecialchars($manga['cover_image_url']) ?>" alt="">
        <div>
            <h1><?= htmlspecialchars($manga['title']) ?></h1>
            <?php if (!empty($manga['alternative_title'])): ?>
                <p class="alt-title"><?= htmlspecialchars($manga['alternative_title']) ?></p>
            <?php endif; ?>
            <div class="meta">
                <?php if (!empty($manga['author'])): ?><div><b>Author:</b> <?= htmlspecialchars($manga['author']) ?></div><?php endif; ?>
                <?php if (!empty($manga['artist'])): ?><div><b>Artist:</b> <?= htmlspecialchars($manga['artist']) ?></div><?php endif; ?>
                <?php if (!empty($manga['genres'])): ?><div><b>Genre:</b> <?= htmlspecialchars($manga['genres']) ?></div><?php endif; ?>
                <?php if (!empty($manga['release_year'])): ?><div><b>Tahun:</b> <?= htmlspecialchars($manga['release_year']) ?></div><?php endif; ?>
                <?php if (!empty($manga['rating'])): ?><div><b>Rating:</b> ⭐ <?= htmlspecialchars($manga['rating']) ?></div><?php endif; ?>
                <div><b>Total chapter tersimpan:</b> <?= (int) count($chapters) ?></div>
            </div>
        </div>
    </div>

    <?php if (!empty($manga['description'])): ?>
        <div class="description"><?= nl2br(htmlspecialchars($manga['description'])) ?></div>
    <?php endif; ?>

    <div>
        <?php if (!empty($manga['last_read_chapter_id'])): ?>
            <a class="continue-btn" href="reader.php?chapter_id=<?= urlencode($manga['last_read_chapter_id']) ?>">
                ▶ Lanjutkan Chapter <?= (int) $manga['last_read_chapter_number'] ?>
            </a>
            <span class="last-read-badge">Terakhir dibaca: Chapter <?= (int) $manga['last_read_chapter_number'] ?></span>
        <?php endif; ?>
    </div>

    <div class="search-box">
        <input type="text" id="searchInput" placeholder="🔍 Cari chapter (nomor atau judul)...">
    </div>

    <div class="chapter-list-wrap">
        <ul class="chapter-list" id="chapterList">
            <?php foreach ($chapters as $ch): ?>
                <?php $isCurrent = $manga['last_read_chapter_id'] === $ch['chapter_id']; ?>
                <li>
                    <a class="<?= $isCurrent ? 'current-read' : '' ?>"
                       href="reader.php?chapter_id=<?= urlencode($ch['chapter_id']) ?>"
                       data-search="<?= htmlspecialchars(mb_strtolower($ch['chapter_number'] . ' ' . ($ch['chapter_title'] ?? ''))) ?>">
                        Chapter <?= (int) $ch['chapter_number'] ?>
                        <?= $ch['chapter_title'] ? " - " . htmlspecialchars($ch['chapter_title']) : "" ?>
                        <?= $isCurrent ? " 📖" : "" ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="no-result" id="noResult" style="display:none">Tidak ada chapter yang cocok.</p>
    </div>

    <script>
        const searchInput = document.getElementById("searchInput");
        const items = Array.from(document.querySelectorAll("#chapterList li"));
        const noResult = document.getElementById("noResult");

        searchInput.addEventListener("input", () => {
            const q = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;
            items.forEach(li => {
                const match = li.querySelector("a").dataset.search.includes(q);
                li.style.display = match ? "" : "none";
                if (match) visibleCount++;
            });
            noResult.style.display = visibleCount === 0 ? "block" : "none";
        });
    </script>
</body>
</html>
