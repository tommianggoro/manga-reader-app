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
    <title><?= htmlspecialchars($manga['title']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%23f2a541'/%3E%3Ctext x='50' y='68' font-size='55' text-anchor='middle'%3E%F0%9F%93%96%3C/text%3E%3C/svg%3E">
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

        .cover-img { width: 130px; border-radius: 0.6rem; object-fit: cover; aspect-ratio: 2/3; }

        .fav-star-btn {
            width: 40px; height: 40px; border: none; background: var(--bs-tertiary-bg);
            color: #9a9fb0; font-size: 1.2rem;
        }
        .fav-star-btn.active { color: var(--bs-primary); }
        .fav-star-btn:hover { background: #2a2f3d; }

        .description-card { background: var(--bs-tertiary-bg); border: none; }

        .chapter-list-wrap { max-height: 480px; overflow-y: auto; }
        .list-group-item { background: var(--bs-secondary-bg); border-color: var(--bs-border-color); }
        .list-group-item.current-read { border-left: 4px solid var(--bs-primary); }
        .list-group-item-action:hover { background: var(--bs-tertiary-bg); }

        .no-result { color: #7c8194; }
    </style>
</head>
<body>
<div class="container py-3" style="max-width: 800px;">

    <a class="d-inline-flex align-items-center gap-1 text-decoration-none mb-3" href="index.php">
        <i class="bi bi-arrow-left"></i> Kembali ke koleksi
    </a>

    <div class="d-flex gap-3 mb-3">
        <img class="cover-img flex-shrink-0" src="<?= htmlspecialchars($manga['cover_image_url']) ?>" alt="">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="brand-font h4 mb-0"><?= htmlspecialchars($manga['title']) ?></h1>
                <button type="button" class="btn rounded-circle fav-star-btn <?= $manga['is_favorite'] ? 'active' : '' ?>"
                        id="favBtn" data-manga-id="<?= htmlspecialchars($manga['manga_id']) ?>">
                    <i class="bi <?= $manga['is_favorite'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                </button>
            </div>
            <?php if (!empty($manga['alternative_title'])): ?>
                <p class="text-secondary small mb-2"><?= htmlspecialchars($manga['alternative_title']) ?></p>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-1">
                <?php if (!empty($manga['author'])): ?><span class="badge rounded-pill text-bg-secondary">Author: <?= htmlspecialchars($manga['author']) ?></span><?php endif; ?>
                <?php if (!empty($manga['artist'])): ?><span class="badge rounded-pill text-bg-secondary">Artist: <?= htmlspecialchars($manga['artist']) ?></span><?php endif; ?>
                <?php if (!empty($manga['release_year'])): ?><span class="badge rounded-pill text-bg-secondary">Tahun: <?= htmlspecialchars($manga['release_year']) ?></span><?php endif; ?>
                <?php if (!empty($manga['rating'])): ?><span class="badge rounded-pill text-bg-warning text-dark"><i class="bi bi-star-fill"></i> <?= htmlspecialchars($manga['rating']) ?></span><?php endif; ?>
                <span class="badge rounded-pill text-bg-secondary"><?= (int) count($chapters) ?> chapter tersimpan</span>
            </div>
            <?php if (!empty($manga['genres'])): ?>
                <div class="small text-secondary mt-2"><i class="bi bi-tags"></i> <?= htmlspecialchars($manga['genres']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($manga['description'])): ?>
        <div class="card description-card mb-3">
            <div class="card-body small" style="line-height: 1.6;"><?= nl2br(htmlspecialchars($manga['description'])) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($firstChapter): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <?php if (!empty($manga['last_read_chapter_id'])): ?>
                <a class="btn btn-primary" href="reader.php?chapter_id=<?= urlencode($manga['last_read_chapter_id']) ?>">
                    <i class="bi bi-play-fill"></i> Lanjutkan Chapter <?= (int) $manga['last_read_chapter_number'] ?>
                </a>
                <a class="btn btn-outline-secondary" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                    <i class="bi bi-book"></i> Baca dari Awal
                </a>
                <span class="badge text-bg-secondary">Terakhir dibaca: Ch. <?= (int) $manga['last_read_chapter_number'] ?></span>
            <?php else: ?>
                <a class="btn btn-primary" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                    <i class="bi bi-book"></i> Baca dari Awal
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="input-group mb-3">
        <span class="input-group-text bg-body-tertiary border-secondary-subtle"><i class="bi bi-search"></i></span>
        <input type="text" id="searchInput" class="form-control" placeholder="Cari chapter (nomor atau judul)...">
    </div>

    <div class="chapter-list-wrap">
        <div class="list-group" id="chapterList">
            <?php foreach ($chapters as $ch): ?>
                <?php $isCurrent = $manga['last_read_chapter_id'] === $ch['chapter_id']; ?>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $isCurrent ? 'current-read' : '' ?>"
                   href="reader.php?chapter_id=<?= urlencode($ch['chapter_id']) ?>"
                   data-search="<?= htmlspecialchars(mb_strtolower($ch['chapter_number'] . ' ' . ($ch['chapter_title'] ?? ''))) ?>">
                    <span>
                        Chapter <?= (int) $ch['chapter_number'] ?>
                        <?= $ch['chapter_title'] ? " - " . htmlspecialchars($ch['chapter_title']) : "" ?>
                    </span>
                    <span class="d-flex align-items-center gap-2 flex-shrink-0">
                        <small class="text-secondary"><?= formatTanggalIndo($ch['updated_at'] ?? $ch['created_at']) ?></small>
                        <?php if ($isCurrent): ?><i class="bi bi-bookmark-fill text-primary"></i><?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="no-result text-center py-4" id="noResult" style="display:none">Tidak ada chapter yang cocok.</p>
    </div>

</div>

<script>
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