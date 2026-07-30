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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root, [data-bs-theme="dark"] {
            --bs-body-bg: #000;
            --bs-body-color: #eae7e0;
            --bs-primary: #f2a541;
            --bs-primary-rgb: 242, 165, 65;
        }
        body { padding-bottom: 90px; font-family: 'Inter', system-ui, sans-serif; }

        .topbar { background: #14171f; border-bottom: 1px solid #2a2f3d; }
        .topbar a { color: #eae7e0; }

        .reader img { width: 100%; max-width: 800px; display: block; }

        .floating-nav {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: rgba(20, 23, 31, 0.85); backdrop-filter: blur(8px);
            border-radius: 50rem; padding: 0.5rem 0.7rem;
            display: flex; align-items: center; gap: 0.4rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5); z-index: 100;
        }
        .floating-nav .btn {
            width: 42px; height: 42px; border-radius: 50%; padding: 0;
            display: flex; align-items: center; justify-content: center;
            background: transparent; border: none; color: #eae7e0; font-size: 1.2rem;
        }
        .floating-nav .btn:hover:not(:disabled) { background: #2a2f3d; }
        .floating-nav .btn:disabled { opacity: 0.25; }
        .floating-nav select {
            background: #1c2029; color: #eae7e0; border: none; border-radius: 50rem;
            padding: 0.5rem 0.8rem; font-size: 0.85rem; max-width: 110px;
        }
    </style>
</head>
<body>
    <nav class="topbar sticky-top d-flex justify-content-between align-items-center px-3 py-2">
        <a class="d-inline-flex align-items-center gap-1 text-decoration-none" href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>">
            <i class="bi bi-arrow-left"></i> <?= htmlspecialchars($manga['title']) ?>
        </a>
        <span class="badge text-bg-secondary">Chapter <?= (int) $chapter['chapter_number'] ?></span>
    </nav>

    <div class="reader d-flex flex-column align-items-center">
        <?php foreach ($images as $img): ?>
            <img src="<?= htmlspecialchars($chapter['base_url'] . $chapter['image_path'] . $img['filename']) ?>"
                 alt="Halaman <?= (int) $img['page_number'] ?>" loading="lazy">
        <?php endforeach; ?>
    </div>

    <div class="floating-nav">
        <a href="index.php" class="btn" title="Beranda"><i class="bi bi-house-fill"></i></a>
        <a href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>" class="btn" title="Detail Manga"><i class="bi bi-book-fill"></i></a>

        <button type="button" class="btn" onclick="goPrev()" title="Chapter Sebelumnya" <?= $prevCh ? '' : 'disabled' ?>>
            <i class="bi bi-chevron-left"></i>
        </button>

        <select id="jumpSelect" title="Loncat ke chapter">
            <?php foreach ($allChapters as $c): ?>
                <option value="<?= urlencode($c['chapter_id']) ?>" <?= $c['chapter_id'] === $chapter['chapter_id'] ? 'selected' : '' ?>>
                    Ch. <?= (int) $c['chapter_number'] ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="button" class="btn" onclick="goNext()" title="Chapter Selanjutnya" <?= $nextCh ? '' : 'disabled' ?>>
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>

    <script>
        const prevChapterId = <?= $prevCh ? json_encode($prevCh['chapter_id']) : 'null' ?>;
        const nextChapterId = <?= $nextCh ? json_encode($nextCh['chapter_id']) : 'null' ?>;

        function goPrev() {
            if (prevChapterId) window.location.href = "reader.php?chapter_id=" + encodeURIComponent(prevChapterId);
        }
        function goNext() {
            if (nextChapterId) window.location.href = "reader.php?chapter_id=" + encodeURIComponent(nextChapterId);
        }

        document.getElementById("jumpSelect").addEventListener("change", (e) => {
            window.location.href = "reader.php?chapter_id=" + encodeURIComponent(e.target.value);
        });
    </script>
</body>
</html>