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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($manga['title']) ?> - Chapter <?= (int) $chapter['chapter_number'] ?></title>
    <style>
        body { font-family: sans-serif; background: #000; color: #eee; margin: 0; padding-bottom: 90px; }
        .topbar { position: sticky; top: 0; background: #1a1a1a; padding: 0.8rem 1rem; display: flex; justify-content: space-between; align-items: center; z-index: 10; }
        .topbar a { color: #4a90d9; text-decoration: none; }
        .reader { display: flex; flex-direction: column; align-items: center; }
        .reader img { width: 100%; max-width: 800px; display: block; }

        .floating-nav {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(30, 30, 30, 0.92);
            backdrop-filter: blur(6px);
            border-radius: 50px;
            padding: 0.5rem 0.7rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.5);
            z-index: 100;
        }
        .floating-nav button, .floating-nav a {
            background: transparent;
            border: none;
            color: #eee;
            font-size: 1.3rem;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
        }
        .floating-nav button:hover, .floating-nav a:hover { background: #3a3a3a; }
        .floating-nav button:disabled { opacity: 0.25; cursor: not-allowed; }
        .floating-nav select {
            background: #2a2a2a; color: #eee; border: none; border-radius: 20px;
            padding: 0.5rem 0.7rem; font-size: 0.85rem; max-width: 110px;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <a href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>">&larr; <?= htmlspecialchars($manga['title']) ?></a>
        <span>Chapter <?= (int) $chapter['chapter_number'] ?></span>
    </div>

    <div class="reader">
        <?php foreach ($images as $img): ?>
            <img src="<?= htmlspecialchars($chapter['base_url'] . $chapter['image_path'] . $img['filename']) ?>"
                 alt="Halaman <?= (int) $img['page_number'] ?>" loading="lazy">
        <?php endforeach; ?>
    </div>

    <div class="floating-nav">
        <a href="index.php" title="Beranda">🏠</a>
        <a href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>" title="Detail Manga">📖</a>

        <button type="button" onclick="goPrev()" title="Chapter Sebelumnya" <?= $prevCh ? '' : 'disabled' ?>>⬅</button>

        <select id="jumpSelect" title="Loncat ke chapter">
            <?php foreach ($allChapters as $c): ?>
                <option value="<?= urlencode($c['chapter_id']) ?>" <?= $c['chapter_id'] === $chapter['chapter_id'] ? 'selected' : '' ?>>
                    Ch. <?= (int) $c['chapter_number'] ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="button" onclick="goNext()" title="Chapter Selanjutnya" <?= $nextCh ? '' : 'disabled' ?>>➡</button>
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
