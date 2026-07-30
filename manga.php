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

// Cari ID chapter paling pertama (nomor chapter terkecil) untuk tombol "Baca dari Awal"
$firstChapterId = null;
if (!empty($chapters)) {
    // Karena query di atas ORDER BY DESC, chapter pertama berada di baris paling akhir
    $firstChapter = end($chapters);
    $firstChapterId = $firstChapter['chapter_id'];
    reset($chapters); // Kembalikan internal pointer array
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

        .back-link {
            color: #adb5bd;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        .back-link:hover { color: var(--bs-primary); }

        .manga-header-card {
            background: var(--bs-secondary-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .cover-img { 
            width: 140px; 
            border-radius: 10px; 
            object-fit: cover; 
            aspect-ratio: 2/3;
            box-shadow: 0 8px 16px rgba(0,0,0,0.4);
        }

        .fav-star-btn {
            width: 40px; height: 40px; border: 1px solid var(--bs-border-color); 
            background: #1c2333; color: #adb5bd; font-size: 1.1rem;
            transition: all 0.2s ease;
        }
        .fav-star-btn.active { color: var(--bs-primary); background: rgba(242, 165, 65, 0.1); border-color: var(--bs-primary); }
        .fav-star-btn:hover { background: #252f44; transform: scale(1.05); }

        .meta-badge {
            background: #1c2333;
            color: #adb5bd;
            border: 1px solid var(--bs-border-color);
            font-weight: 500;
        }

        .description-card { 
            background: #131824; 
            border: 1px solid var(--bs-border-color);
            border-radius: 12px;
        }
        .description-title {
            font-size: 0.85rem;
            text-uppercase: true;
            letter-spacing: 0.05em;
            color: #6c757d;
            font-weight: 600;
        }

        .form-control { border-radius: 8px; padding: 0.6rem 1rem; }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(242, 165, 65, 0.25);
            border-color: var(--bs-primary);
        }

        .chapter-list-wrap { 
            max-height: 520px; 
            overflow-y: auto;
            border: 1px solid var(--bs-border-color);
            border-radius: 12px;
            background: var(--bs-secondary-bg);
        }
        
        /* Custom scrollbar untuk list chapter agar estetik */
        .chapter-list-wrap::-webkit-scrollbar { width: 6px; }
        .chapter-list-wrap::-webkit-scrollbar-track { background: transparent; }
        .chapter-list-wrap::-webkit-scrollbar-thumb { background: #252f44; border-radius: 10px; }

        .list-group-item { 
            background: transparent; 
            border-color: var(--bs-border-color); 
            padding: 0.9rem 1.2rem;
            color: #f1f3f5;
            transition: all 0.15s ease;
        }
        .list-group-item.current-read { 
            background: rgba(242, 165, 65, 0.05);
            border-left: 4px solid var(--bs-primary); 
        }
        .list-group-item-action:hover { 
            background: #1c2333; 
            color: #fff;
        }

        .no-result { color: #6c757d; }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 850px;">

    <a class="d-inline-flex align-items-center gap-2 text-decoration-none mb-3 back-link" href="index.php">
        <i class="bi bi-arrow-left-short fs-4"></i> Kembali ke Koleksi
    </a>

    <!-- Detail Informasi Utama Manga -->
    <div class="manga-header-card mb-4">
        <div class="d-flex flex-column flex-sm-row gap-4">
            <img class="cover-img align-self-center align-self-sm-start" src="<?= htmlspecialchars($manga['cover_image_url']) ?>" alt="<?= htmlspecialchars($manga['title']) ?>">
            <div class="flex-grow-1">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                    <div>
                        <h1 class="brand-font h3 mb-1 text-white"><?= htmlspecialchars($manga['title']) ?></h1>
                        <?php if (!empty($manga['alternative_title'])): ?>
                            <p class="text-secondary small mb-0"><?= htmlspecialchars($manga['alternative_title']) ?></p>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn rounded-circle fav-star-btn flex-shrink-0 <?= $manga['is_favorite'] ? 'active' : '' ?>"
                            id="favBtn" data-manga-id="<?= htmlspecialchars($manga['manga_id']) ?>">
                        <i class="bi <?= $manga['is_favorite'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                    </button>
                </div>

                <div class="d-flex flex-wrap gap-1.5 row-gap-2 mb-3">
                    <?php if (!empty($manga['author'])): ?><span class="badge meta-badge rounded-pill px-2.5 py-1.5">Author: <?= htmlspecialchars($manga['author']) ?></span><?php endif; ?>
                    <?php if (!empty($manga['release_year'])): ?><span class="badge meta-badge rounded-pill px-2.5 py-1.5">Tahun: <?= htmlspecialchars($manga['release_year']) ?></span><?php endif; ?>
                    <?php if (!empty($manga['rating'])): ?><span class="badge text-bg-warning text-dark rounded-pill px-2.5 py-1.5 fw-semibold"><i class="bi bi-star-fill"></i> <?= htmlspecialchars($manga['rating']) ?></span><?php endif; ?>
                    <span class="badge text-bg-primary text-dark rounded-pill px-2.5 py-1.5 fw-semibold"><?= count($chapters) ?> Ch. Tersimpan</span>
                </div>

                <?php if (!empty($manga['genres'])): ?>
                    <div class="small text-secondary mb-0">
                        <i class="bi bi-tags-fill text-muted me-1"></i> <?= htmlspecialchars($manga['genres']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sinopsis / Deskripsi -->
    <?php if (!empty($manga['description'])): ?>
        <div class="card description-card mb-4">
            <div class="card-body p-3.5">
                <div class="description-title mb-2">Sinopsis</div>
                <div class="small text-secondary-subtle" style="line-height: 1.6; text-align: justify;"><?= nl2br(htmlspecialchars($manga['description'])) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tombol Aksi Membaca -->
    <?php if (!empty($firstChapterId)): ?>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <!-- Tombol Baca Dari Awal -->
            <a class="btn btn-outline-primary px-3 fw-semibold d-inline-flex align-items-center gap-2" href="reader.php?chapter_id=<?= urlencode($firstChapterId) ?>">
                <i class="bi bi-book"></i> Baca dari Awal
            </a>

            <!-- Tombol Lanjutkan Terakhir Dibaca -->
            <?php if (!empty($manga['last_read_chapter_id'])): ?>
                <a class="btn btn-primary px-4 fw-semibold d-inline-flex align-items-center gap-2 text-dark" href="reader.php?chapter_id=<?= urlencode($manga['last_read_chapter_id']) ?>">
                    <i class="bi bi-play-fill fs-5"></i> Lanjutkan Ch. <?= (int) $manga['last_read_chapter_number'] ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Pencarian Sederhana Daftar Bab -->
    <div class="input-group mb-3">
        <span class="input-group-text bg-secondary-bg border-secondary-subtle text-secondary"><i class="bi bi-search"></i></span>
        <input type="text" id="searchInput" class="form-control bg-secondary-bg border-secondary-subtle" placeholder="Cari nomor bab atau judul spesifik...">
    </div>

    <!-- Daftar Konten Chapter -->
    <div class="chapter-list-wrap">
        <div class="list-group list-group-flush" id="chapterList">
            <?php foreach ($chapters as $ch): ?>
                <?php $isCurrent = $manga['last_read_chapter_id'] === $ch['chapter_id']; ?>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $isCurrent ? 'current-read' : '' ?>"
                   href="reader.php?chapter_id=<?= urlencode($ch['chapter_id']) ?>"
                   data-search="<?= htmlspecialchars(mb_strtolower($ch['chapter_number'] . ' ' . ($ch['chapter_title'] ?? ''))) ?>">
                    <span class="fw-medium">
                        Chapter <?= (int) $ch['chapter_number'] ?>
                        <?= $ch['chapter_title'] ? " <span class='text-secondary fw-normal ms-1'>— " . htmlspecialchars($ch['chapter_title']) . "</span>" : "" ?>
                    </span>
                    <?php if ($isCurrent): ?>
                        <span class="badge badge-chapter px-2.5 py-1 rounded-pill small text-bg-primary text-dark fw-semibold">
                            <i class="bi bi-bookmark-fill me-1"></i> Terakhir
                        </span>
                    <?php else: ?>
                        <i class="bi bi-chevron-right text-muted small"></i>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="no-result text-center py-5 mb-0" id="noResult" style="display:none">
            <i class="bi bi-patch-question display-6 d-block mb-2 text-muted"></i>
            Chapter tidak ditemukan.
        </p>
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

    // Toggle status favorit asinkronus tanpa muat ulang halaman
    const favBtn = document.getElementById("favBtn");
    favBtn.addEventListener("click", async () => {
        try {
            const res = await fetch("toggle_favorite.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "manga_id=" + encodeURIComponent(favBtn.dataset.mangaId),
            });
            const data = await res.json();
            if (!data.success) { alert("Gagal memperbarui favorit: " + data.error); return; }

            const icon = favBtn.querySelector("i");
            favBtn.classList.toggle("active", data.is_favorite);
            icon.classList.toggle("bi-star-fill", data.is_favorite);
            icon.classList.toggle("bi-star", !data.is_favorite);
        } catch (err) {
            alert("Terjadi kesalahan jaringan: " + err.message);
        }
    });
</script>
</body>
</html>