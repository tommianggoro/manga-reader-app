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
 }
 
 body { 
 padding-bottom: 100px; 
 font-family: 'Inter', system-ui, sans-serif; 
 letter-spacing: -0.01em;
 }

 .topbar { 
 background: #131824; 
 border-bottom: 1px solid var(--bs-border-color); 
 backdrop-filter: blur(8px);
 }
 .topbar a { 
 color: #adb5bd; 
 font-weight: 500;
 transition: color 0.15s ease;
 }
 .topbar a:hover { 
 color: var(--bs-primary); 
 }

 .reader-container {
 max-width: 720px; /* Lebar optimal strip komik agar tidak terlalu melar di monitor besar */
 width: 100%;
 margin: 0 auto;
 background: #000;
 box-shadow: 0 0 30px rgba(0, 0, 0, 0.6);
 }
 .reader img { 
 width: 100%; 
 height: auto; 
 display: block; 
 }

 .floating-nav {
 position: fixed; 
 bottom: 24px; 
 left: 50%; 
 transform: translateX(-50%);
 background: rgba(19, 24, 36, 0.88); 
 backdrop-filter: blur(12px);
 border: 1px solid var(--bs-border-color);
 border-radius: 50rem; 
 padding: 0.6rem 0.8rem;
 display: flex; 
 align-items: center; 
 gap: 0.5rem;
 box-shadow: 0 10px 30px rgba(0,0,0,0.5); 
 z-index: 100;
 }
 .floating-nav .btn {
 width: 40px; 
 height: 40px; 
 border-radius: 50%; 
 padding: 0;
 display: flex; 
 align-items: center; 
 justify-content: center;
 background: transparent; 
 border: none; 
 color: #adb5bd; 
 font-size: 1.15rem;
 transition: all 0.2s ease;
 }
 .floating-nav .btn:hover:not(:disabled) { 
 background: #1c2333; 
 color: #fff;
 }
 .floating-nav .btn:disabled { 
 opacity: 0.2; 
 }
 .floating-nav select {
 background: #1c2333; 
 color: #f1f3f5; 
 border: 1px solid var(--bs-border-color); 
 border-radius: 50rem;
 padding: 0.4rem 0.8rem; 
 font-size: 0.875rem; 
 max-width: 120px;
 font-weight: 500;
 outline: none;
 }
 .floating-nav select:focus {
 border-color: var(--bs-primary);
 }
 </style>
</head>
<body>
 <nav class="topbar sticky-top d-flex justify-content-between align-items-center px-3 py-2.5">
 <a class="d-inline-flex align-items-center gap-2 text-decoration-none" href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>">
 <i class="bi bi-arrow-left"></i> <span class="text-truncate" style="max-width: 200px;"><?= htmlspecialchars($manga['title']) ?></span>
 </a>
 <span class="badge text-bg-primary text-dark fw-semibold px-2.5 py-1.5 rounded-3">Chapter <?= (int) $chapter['chapter_number'] ?></span>
 </nav>

 <div class="reader-container">
 <div class="reader d-flex flex-column align-items-center">
 <?php foreach ($images as $img): ?>
 <img src="<?= htmlspecialchars($chapter['base_url'] . $chapter['image_path'] . $img['filename']) ?>"
 alt="Halaman <?= (int) $img['page_number'] ?>" loading="lazy">
 <?php endforeach; ?>
 </div>
 </div>

 <div class="floating-nav">
 <a href="index.php" class="btn" title="Beranda"><i class="bi bi-house-fill"></i></a>
 <a href="manga.php?manga_id=<?= urlencode($manga['manga_id']) ?>" class="btn" title="Detail Manga"><i class="bi bi-collection-fill"></i></a>

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