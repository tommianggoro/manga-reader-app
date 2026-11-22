<?php
require_once "config.php";
optionalAuth();

$mangaId = $_GET["manga_id"] ?? die("manga_id wajib diisi");
$userId = currentUserId(); // null kalau guest

$stmt = $pdo->prepare("
    SELECT m.*, COALESCE(s.is_favorite, 0) AS is_favorite,
           s.last_read_chapter_id, s.last_read_chapter_number, s.last_read_at
    FROM mangas m
    LEFT JOIN user_manga_state s ON s.manga_id = m.manga_id AND s.user_id = :uid
    WHERE m.manga_id = :id
");
$stmt->execute([":uid" => $userId, ":id" => $mangaId]);
$manga = $stmt->fetch();
if (!$manga) die("Manga tidak ditemukan di database.");

require_once "sync_functions.php";
$mangaSourceBindings = getMangaSources($pdo, $mangaId); // [['source'=>..,'source_ref'=>..], ...]
$sourceLabelsMap = [];
foreach (getAllSources() as $key => $adapter) $sourceLabelsMap[$key] = $adapter->getLabel();

$stmt = $pdo->prepare("SELECT * FROM chapters WHERE manga_id = :id ORDER BY chapter_number DESC");
$stmt->execute([":id" => $mangaId]);
$chapters = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT chapter_id, chapter_number FROM chapters WHERE manga_id = :id ORDER BY chapter_number ASC LIMIT 1");
$stmt->execute([":id" => $mangaId]);
$firstChapter = $stmt->fetch();

// ==== Reading history untuk manga ini (user login) ====
$readChapterIds = []; // chapter_id -> updated_at (waktu baca terakhir)
if ($userId) {
    $stmt = $pdo->prepare("
        SELECT rp.chapter_id, rp.updated_at
        FROM reading_progress rp
        JOIN chapters c ON c.id = rp.chapter_id
        WHERE rp.user_id = :uid AND c.manga_id = :mid
        ORDER BY rp.updated_at DESC
    ");
    $stmt->execute([":uid" => $userId, ":mid" => $mangaId]);
    foreach ($stmt->fetchAll() as $row) {
        $readChapterIds[$row["chapter_id"]] = $row["updated_at"];
    }
}
$totalChaptersInManga = count($chapters);
$readChaptersCount = count($readChapterIds);
$readPercent = $totalChaptersInManga > 0 ? round(($readChaptersCount / $totalChaptersInManga) * 100) : 0;

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
    <link rel="icon" href="favicon.ico" sizes="any">
    
    <link rel="manifest" href="https://tommianggoro.github.io/manga-reader-app/manifest.json">
    <meta name="theme-color" content="#10131a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Manga Reader">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    

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
            width: 140px; aspect-ratio: 2/3; border-radius: 0.75rem; overflow: hidden; flex-shrink: 0;
            background: var(--shimmer-bg-1); position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .cover-img-wrap img { width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 0.35s ease; }
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

        /* Tag chips */
        .tag-chip {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.2rem 0.6rem; border-radius: 50rem;
            font-size: 0.72rem; font-weight: 600; line-height: 1;
            border: 1px solid transparent; cursor: default;
            transition: all 0.15s ease; white-space: nowrap;
        }
        .tag-chip .tag-remove { display: none; width: 16px; height: 16px; border-radius: 50%; background: rgba(0,0,0,0.1); flex-shrink: 0; align-items: center; justify-content: center; font-size: 0.65rem; line-height: 1; }
        .tag-chip:hover .tag-remove { display: flex; }
        .tag-chip .tag-remove:hover { background: rgba(0,0,0,0.2); }
        .tag-chips-wrap { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.25rem; }
        .tag-add-btn { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border-radius: 50rem; font-size: 0.72rem; font-weight: 600; border: 1px dashed var(--bs-border-color); background: transparent; color: var(--bs-secondary-color); cursor: pointer; transition: all 0.15s ease; }
        .tag-add-btn:hover { border-color: var(--bs-primary); color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), 0.08); }

        .theme-toggle-btn {
            width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--bs-border-color); background: var(--bs-secondary-bg); color: var(--bs-body-color);
            transition: all 0.2s ease;
        }

        .description-card { background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); border-radius: 12px; }

        .chapter-list-wrap { max-height: 520px; overflow-y: auto; border-radius: 12px; border: 1px solid var(--bs-border-color); overflow-x: hidden; background: var(--bs-secondary-bg); }
        .list-group-item { background: var(--bs-secondary-bg); border-color: var(--bs-border-color); transition: all 0.15s ease; }
        .list-group-item.current-read { border-left: 4px solid var(--bs-primary); background: var(--bs-tertiary-bg); }
        .list-group-item.chapter-read { background: var(--bs-secondary-bg); }
        .list-group-item.chapter-read:hover { background: var(--bs-tertiary-bg); }
        .list-group-item-action:hover { background: var(--bs-tertiary-bg); transform: translateX(3px); }

        .no-result { color: #7c8194; }
        /* Highlight chapter baru hasil sync background */
        @keyframes newChapterRowFlash {
            0%   { background-color: rgba(var(--bs-primary-rgb), 0.35); }
            100% { background-color: var(--bs-secondary-bg); }
        }
        .list-group-item.just-added {
            animation: newChapterRowFlash 2s ease-out;
        }

        /* Progress Summary */
        .progress-summary { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .progress-circle { position: relative; }

        @media (max-width: 576px) {
            .theme-toggle-btn { width: 42px; height: 42px; }
            .fav-star-btn { width: 44px; height: 44px; }

            .action-buttons-row { flex-direction: column; align-items: stretch !important; }
            .action-buttons-row .btn { width: 100%; margin-left: 0 !important; }
            .progress-summary .d-flex { flex-direction: column; align-items: flex-start !important; }
        }
    </style>
</head>
<body>
<div class="container py-3" style="max-width: 850px;">

    <!-- Top Bar Navigation -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <a class="d-inline-flex align-items-center gap-1 text-decoration-none fw-medium" href="index.php">
            <i class="bi bi-arrow-left"></i> Kembali ke koleksi
        </a>
        <div class="d-flex align-items-center gap-2">
            <?php if (isAdmin()): ?>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteMangaModal" title="Hapus Manga Ini">
                <i class="bi bi-trash3 me-1"></i> <span class="d-none d-sm-inline">Hapus Manga</span>
            </button>
            <?php endif; ?>
            <button type="button" class="theme-toggle-btn" id="themeToggle" title="Ganti Mode Gelap/Terang">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
            </button>
            <?php if ($userId): ?>
                <a href="discover.php" class="btn btn-outline-secondary btn-sm" title="Temukan Manga Baru">
                    <i class="bi bi-compass"></i> <span class="d-none d-sm-inline">Temukan</span>
                </a>
                <a href="stats.php" class="btn btn-outline-secondary btn-sm" title="Statistik Bacaan">
                    <i class="bi bi-graph-up-arrow"></i> <span class="d-none d-sm-inline">Statistik</span>
                </a>
                <button type="button" class="theme-toggle-btn" onclick="logoutUser()" title="Keluar (<?= htmlspecialchars(currentUsername()) ?>)">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            <?php else: ?>
                <a href="discover.php" class="btn btn-outline-secondary btn-sm" title="Temukan Manga Baru">
                    <i class="bi bi-compass"></i> <span class="d-none d-sm-inline">Temukan</span>
                </a>
                <button type="button" class="theme-toggle-btn" onclick="showLoginRequiredModal()" title="Login">
                    <i class="bi bi-box-arrow-in-right"></i>
                </button>
            <?php endif; ?>
        </div>
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
                <span class="badge rounded-pill text-bg-primary" id="totalChapterBadge"><i class="bi bi-journals me-1"></i><span id="totalChapterCount"><?= (int) count($chapters) ?></span> Chapter</span>
            </div>
            <?php if (!empty($manga['genres'])): ?>
                <div class="small text-secondary"><i class="bi bi-tags me-1"></i><?= htmlspecialchars($manga['genres']) ?></div>
            <?php endif; ?>
            <!-- User Custom Tags -->
            <?php if ($userId): ?>
            <div class="mt-2">
                <div id="mangaTagsContainer" class="tag-chips-wrap"></div>
                <button type="button" class="tag-add-btn" id="tagAddBtn" title="Tambah tag ke manga ini">
                    <i class="bi bi-plus-lg"></i> Tag
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Deskripsi Manga -->
    <?php if (!empty($manga['description'])): ?>
        <div class="card description-card mb-4">
            <div class="card-body small" style="line-height: 1.65; color: var(--bs-body-color);"><?= nl2br(htmlspecialchars($manga['description'])) ?></div>
        </div>
    <?php endif; ?>

    <!-- Action Buttons (Resume/Start/Update) -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4 p-3 rounded-3 action-buttons-row" style="background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color);" id="actionButtonsRow">
        <?php if ($firstChapter): ?>
            <?php if ($userId && !empty($manga['last_read_chapter_id'])): ?>
                <a class="btn btn-primary fw-semibold px-3" id="resumeBtn" href="reader.php?chapter_id=<?= urlencode($manga['last_read_chapter_id']) ?>">
                    <i class="bi bi-play-fill fs-5 me-1"></i> <span id="resumeBtnText">Lanjutkan Chapter <?= (float) $manga['last_read_chapter_number'] ?></span>
                </a>
                <a class="btn btn-outline-secondary px-3" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                    <i class="bi bi-book me-1"></i> Baca dari Awal
                </a>
            <?php else: ?>
                <!-- Guest (atau user login tanpa histori DB): tombol default "mulai dari awal",
                     akan DIGANTI oleh JS jadi "Lanjutkan" kalau localStorage punya histori utk manga ini. -->
                <a class="btn btn-primary fw-semibold px-4" id="resumeBtn" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                    <i class="bi bi-book me-1"></i> <span id="resumeBtnText">Mulai Baca dari Awal</span>
                </a>
                <a class="btn btn-outline-secondary px-3 d-none" id="startOverBtn" href="reader.php?chapter_id=<?= urlencode($firstChapter['chapter_id']) ?>">
                    <i class="bi bi-book me-1"></i> Baca dari Awal
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (isAdmin() && count($mangaSourceBindings) > 1): ?>
        <select class="form-select form-select-sm ms-auto" style="max-width: 220px;" id="preferredSourceSelect">
            <option value="">Sumber Prioritas: Otomatis</option>
            <?php foreach ($mangaSourceBindings as $b): ?>
                <option value="<?= htmlspecialchars($b['source']) ?>" <?= ($manga['preferred_source'] ?? '') === $b['source'] ? 'selected' : '' ?>>
                    Prioritas: <?= htmlspecialchars($sourceLabelsMap[$b['source']] ?? $b['source']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if (isAdmin()): ?>
        <a class="btn btn-outline-warning <?= count($mangaSourceBindings) > 1 ? '' : 'ms-auto' ?>" href="crawl.php?manga_id=<?= urlencode($manga['manga_id']) ?>" target="_blank" title="Cek & Sync Chapter Baru">
            <i class="bi bi-arrow-repeat me-1"></i> Update / Sync Chapter (<?= count($mangaSourceBindings) ?> sumber)
        </a>
        <?php endif; ?>
    </div>

    <!-- Reading Progress Summary (Logged-in Users) -->
    <?php if ($userId): ?>
    <div class="progress-summary mb-3 p-3 rounded-3" style="background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color);">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="progress-circle" style="width: 48px; height: 48px; border-radius: 50%; background: conic-gradient(var(--bs-primary) <?= $readPercent * 3.6 ?>deg, var(--bs-border-color) 0deg); display: flex; align-items: center; justify-content: center;">
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--bs-tertiary-bg); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem;"><?= $readPercent ?>%</div>
                </div>
                <div>
                    <div class="fw-semibold"><?= $readChaptersCount ?> / <?= $totalChaptersInManga ?> chapter dibaca</div>
                    <div class="small text-secondary">Progres baca manga ini</div>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="filterReadBtn" title="Tampilkan hanya chapter sudah dibaca">
                    <i class="bi bi-check-circle me-1"></i> Sudah Dibaca
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="filterUnreadBtn" title="Tampilkan hanya chapter belum dibaca">
                    <i class="bi bi-circle me-1"></i> Belum Dibaca
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="filterAllBtn" title="Tampilkan semua chapter">
                    <i class="bi bi-grid me-1"></i> Semua
                </button>
            </div>
        </div>
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
            <?php foreach ($chapters as $ch):
                $isCurrent = $userId && !empty($manga['last_read_chapter_id']) && $manga['last_read_chapter_id'] === $ch['chapter_id'];
                $isRead = $userId && isset($readChapterIds[$ch['chapter_id']]);
                $readAt = $isRead ? $readChapterIds[$ch['chapter_id']] : null;
            ?>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 <?= $isCurrent ? 'current-read' : '' ?> <?= $isRead ? 'chapter-read' : '' ?>"
                   href="reader.php?chapter_id=<?= urlencode($ch['chapter_id']) ?>"
                   data-chapter-id="<?= htmlspecialchars($ch['chapter_id']) ?>"
                   data-search="<?= htmlspecialchars(mb_strtolower($ch['chapter_number'] . ' ' . ($ch['chapter_title'] ?? ''))) ?>"
                   data-read="<?= $isRead ? '1' : '0' ?>">
                    <span class="fw-medium d-flex align-items-center gap-2">
                        <?php if ($isRead): ?>
                            <i class="bi bi-check-circle-fill text-success" title="Dibaca pada <?= formatTanggalIndo($readAt) ?>"></i>
                        <?php else: ?>
                            <i class="bi bi-circle text-secondary" style="opacity: 0.4;" title="Belum dibaca"></i>
                        <?php endif; ?>
                        Chapter <?= (float) $ch['chapter_number'] ?>
                        <?= $ch['chapter_title'] ? " — <span class='text-secondary font-normal'>" . htmlspecialchars($ch['chapter_title']) . "</span>" : "" ?>
                    </span>
                    <span class="d-flex align-items-center gap-2 flex-shrink-0">
                        <small class="text-secondary"><?= formatTanggalIndo($ch['updated_at'] ?? $ch['created_at']) ?></small>
                        <?php if ($isCurrent): ?><i class="bi bi-bookmark-fill text-primary current-read-icon" title="Chapter terakhir dibaca"></i><?php endif; ?>
                        <?php if ($isRead): ?><small class="text-success">✓ Dibaca</small><?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="no-result text-center py-4 mb-0" id="noResult" style="display:none">Tidak ada chapter yang cocok.</p>
    </div>

</div>

<?php if (isAdmin()): ?>
<!-- Modal Konfirmasi Hapus Manga -->
<div class="modal fade" id="deleteMangaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header text-danger">
                <h5 class="modal-title brand-font"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Manga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus manga <strong><?= htmlspecialchars($manga['title']) ?></strong> dari koleksi Anda?
                <p class="text-danger small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i> Tindakan ini akan menghapus seluruh data chapter dan histori bacaan secara permanen.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn"><i class="bi bi-trash3 me-1"></i> Hapus Permanen</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast Notifikasi Chapter Baru (hasil sync background/cron) -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="newChapterToast" class="toast align-items-center border-0 text-bg-primary" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="newChapterToastText">
                <i class="bi bi-stars me-1"></i> Ada chapter baru!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<?php include __DIR__ . "/partials/login_modal.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const isLoggedIn = <?= $userId ? 'true' : 'false' ?>;
    const currentMangaId = <?= json_encode($mangaId) ?>;

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
            item.classList.toggle("d-none", !match);
            if (match) visibleCount++;
        });
        noResult.style.display = visibleCount === 0 ? "block" : "none";
    });

    // Chapter read filter (Sudah Dibaca / Belum Dibaca / Semua)
    const filterReadBtn = document.getElementById("filterReadBtn");
    const filterUnreadBtn = document.getElementById("filterUnreadBtn");
    const filterAllBtn = document.getElementById("filterAllBtn");

    function setFilterMode(mode) {
        // mode: 'read', 'unread', 'all'
        items.forEach(item => {
            const isRead = item.dataset.read === '1';
            const isHiddenBySearch = item.classList.contains('d-none'); // already hidden by search

            let show = false;
            if (mode === 'read') show = isRead;
            else if (mode === 'unread') show = !isRead;
            else show = true; // all

            // Combine with search filter
            item.classList.toggle("d-none", !show || isHiddenBySearch);
        });

        // Update button active states
        [filterReadBtn, filterUnreadBtn, filterAllBtn].forEach(b => b?.classList.remove('active'));
        if (mode === 'read') filterReadBtn?.classList.add('active');
        else if (mode === 'unread') filterUnreadBtn?.classList.add('active');
        else filterAllBtn?.classList.add('active');
    }

    filterReadBtn?.addEventListener("click", () => setFilterMode('read'));
    filterUnreadBtn?.addEventListener("click", () => setFilterMode('unread'));
    filterAllBtn?.addEventListener("click", () => setFilterMode('all'));

    // Also apply filter when search changes
    const originalSearchHandler = searchInput.oninput;
    searchInput.addEventListener("input", () => {
        // Re-apply current filter after search
        const activeBtn = document.querySelector("#filterReadBtn.active, #filterUnreadBtn.active, #filterAllBtn.active");
        if (activeBtn === filterReadBtn) setFilterMode('read');
        else if (activeBtn === filterUnreadBtn) setFilterMode('unread');
        else setFilterMode('all');
    });

    // Toggle favorit -- kalau GUEST, tampilkan modal login dulu (tidak hit API).
    const favBtn = document.getElementById("favBtn");
    favBtn.addEventListener("click", async () => {
        if (!isLoggedIn) { showLoginRequiredModal(); return; }
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
        } catch (err) { alert("Gagal update favorit: " + err.message); }
    });

    // ===== CUSTOM TAGS SYSTEM =====
    // Load and display tags for this manga
    const mangaTagsContainer = document.getElementById("mangaTagsContainer");
    const tagAddBtn = document.getElementById("tagAddBtn");
    let allUserTags = []; // cache all user tags from API

    async function loadUserTags() {
        if (!isLoggedIn) return;
        try {
            const res = await fetch("api_tags.php?action=list");
            const data = await res.json();
            if (data.success) {
                allUserTags = data.tags || [];
            }
        } catch (err) {
            console.warn("Gagal load tags:", err.message);
        }
    }

    async function loadMangaTags() {
        if (!isLoggedIn || !mangaTagsContainer) return;
        try {
            const res = await fetch("api_tags.php?action=manga_tags&manga_id=" + encodeURIComponent(currentMangaId));
            const data = await res.json();
            if (data.success) {
                renderMangaTags(data.tags || []);
            }
        } catch (err) {
            console.warn("Gagal load manga tags:", err.message);
        }
    }

    function renderMangaTags(tags) {
        if (!mangaTagsContainer) return;
        mangaTagsContainer.innerHTML = "";
        tags.forEach(tag => {
            const chip = document.createElement("span");
            chip.className = "tag-chip";
            chip.style.background = tag.color + "20"; // 12% opacity
            chip.style.borderColor = tag.color;
            chip.style.color = tag.color;
            chip.dataset.tagId = tag.id;
            chip.innerHTML = `
                ${escapeHtml(tag.name)}
                <span class="tag-remove" title="Hapus tag dari manga ini"><i class="bi bi-x"></i></span>
            `;
            chip.querySelector(".tag-remove").addEventListener("click", (e) => {
                e.stopPropagation();
                removeTagFromManga(tag.id);
            });
            mangaTagsContainer.appendChild(chip);
        });
    }

    function escapeHtml(s) {
        const div = document.createElement("div");
        div.textContent = s ?? "";
        return div.innerHTML;
    }

    // Show tag picker modal
    function showTagPicker() {
        if (!isLoggedIn) { showLoginRequiredModal(); return; }
        // Build modal HTML
        const modalId = "tagPickerModal";
        let modal = document.getElementById(modalId);
        if (!modal) {
            modal = document.createElement("div");
            modal.id = modalId;
            modal.className = "modal fade";
            modal.tabIndex = -1;
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title brand-font"><i class="bi bi-tag-fill me-2"></i>Kelola Tag</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Buat Tag Baru</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="newTagName" class="form-control" placeholder="Nama tag (mis. Prioritas, Baca Nanti...)" maxlength="64">
                                    <input type="color" id="newTagColor" class="form-control form-control-color" value="#6c757d" title="Warna tag" style="width: 48px; height: 38px; padding: 0;">
                                    <button type="button" class="btn btn-primary" id="createTagBtn">Buat</button>
                                </div>
                                <div id="createTagError" class="text-danger small mt-1"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Tag Saya (klik untuk tambah/hapus dari manga ini)</label>
                                <div id="tagPickerList" class="tag-chips-wrap" style="min-height: 60px;"></div>
                                <div class="text-secondary small" id="noTagsHint" style="display:none;">Belum ada tag. Buat tag baru di atas.</div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Create tag handler
            document.getElementById("createTagBtn").addEventListener("click", createTag);
            document.getElementById("newTagName").addEventListener("keypress", (e) => {
                if (e.key === "Enter") createTag();
            });
        }

        // Populate tag picker list
        populateTagPickerList();

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

    function populateTagPickerList() {
        const tagPickerList = document.getElementById("tagPickerList");
        const noTagsHint = document.getElementById("noTagsHint");

        if (allUserTags.length === 0) {
            tagPickerList.innerHTML = "";
            noTagsHint.style.display = "block";
        } else {
            noTagsHint.style.display = "none";
            tagPickerList.innerHTML = "";
            // Get currently assigned tag IDs for this manga
            const assignedTagIds = new Set();
            mangaTagsContainer.querySelectorAll(".tag-chip").forEach(chip => {
                assignedTagIds.add(chip.dataset.tagId);
            });

            allUserTags.forEach(tag => {
                const isAssigned = assignedTagIds.has(String(tag.id));
                const chip = document.createElement("span");
                chip.className = "tag-chip " + (isAssigned ? "active" : "");
                chip.style.background = tag.color + (isAssigned ? "40" : "20");
                chip.style.borderColor = tag.color;
                chip.style.color = tag.color;
                chip.dataset.tagId = tag.id;
                chip.innerHTML = `
                    ${escapeHtml(tag.name)}
                    ${isAssigned ? '<span class="tag-remove" style="display:flex;" title="Hapus dari manga"><i class="bi bi-check-lg"></i></span>' : ''}
                `;
                chip.addEventListener("click", () => toggleTagOnManga(tag.id, tag.name, tag.color, !isAssigned));
                tagPickerList.appendChild(chip);
            });
        }
    }

    async function createTag() {
        const nameInput = document.getElementById("newTagName");
        const colorInput = document.getElementById("newTagColor");
        const errorEl = document.getElementById("createTagError");
        const name = nameInput.value.trim();
        const color = colorInput.value;

        if (!name) { errorEl.textContent = "Nama tag tidak boleh kosong"; return; }
        errorEl.textContent = "";

        try {
            const res = await fetch("api_tags.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=create&name=" + encodeURIComponent(name) + "&color=" + encodeURIComponent(color)
            });
            const data = await res.json();
            if (!data.success) { errorEl.textContent = data.error; return; }

            // Add to cache and update UI
            allUserTags.push(data.tag);
            nameInput.value = "";

            // Just refresh the tag picker list, don't recreate modal
            populateTagPickerList();
        } catch (err) {
            errorEl.textContent = "Gagal: " + err.message;
        }
    }

    async function toggleTagOnManga(tagId, tagName, tagColor, assign) {
        try {
            const action = assign ? "assign" : "remove";
            const res = await fetch("api_tags.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=" + action + "&manga_id=" + encodeURIComponent(currentMangaId) + "&tag_id=" + encodeURIComponent(tagId)
            });
            const data = await res.json();
            if (!data.success) { alert("Gagal: " + data.error); return; }

            // Reload manga tags display
            await loadMangaTags();
            // Update picker UI (toggle active state)
            const pickerChip = document.querySelector("#tagPickerList .tag-chip[data-tag-id='" + tagId + "']");
            if (pickerChip) {
                pickerChip.classList.toggle("active", assign);
                pickerChip.style.background = tagColor + (assign ? "40" : "20");
                // Update remove/check icon
                const removeEl = pickerChip.querySelector(".tag-remove");
                if (assign) {
                    if (!removeEl) {
                        const newRemove = document.createElement("span");
                        newRemove.className = "tag-remove";
                        newRemove.style.display = "flex";
                        newRemove.title = "Hapus dari manga";
                        newRemove.innerHTML = '<i class="bi bi-check-lg"></i>';
                        pickerChip.appendChild(newRemove);
                    } else {
                        removeEl.style.display = "flex";
                        removeEl.innerHTML = '<i class="bi bi-check-lg"></i>';
                    }
                } else {
                    if (removeEl) removeEl.remove();
                }
            }
        } catch (err) {
            alert("Gagal: " + err.message);
        }
    }

    async function removeTagFromManga(tagId) {
        try {
            const res = await fetch("api_tags.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=remove&manga_id=" + encodeURIComponent(currentMangaId) + "&tag_id=" + encodeURIComponent(tagId)
            });
            const data = await res.json();
            if (!data.success) { alert("Gagal: " + data.error); return; }
            await loadMangaTags();
            // Also update picker if open
            const pickerChip = document.querySelector("#tagPickerList .tag-chip[data-tag-id='" + tagId + "']");
            if (pickerChip) {
                pickerChip.classList.remove("active");
                const tag = allUserTags.find(t => String(t.id) === String(tagId));
                if (tag) pickerChip.style.background = tag.color + "20";
                const removeEl = pickerChip.querySelector(".tag-remove");
                if (removeEl) removeEl.remove();
            }
        } catch (err) {
            alert("Gagal: " + err.message);
        }
    }

    // Initialize tags
    if (isLoggedIn) {
        (async () => {
            await loadUserTags();
            await loadMangaTags();
        })();
        tagAddBtn?.addEventListener("click", showTagPicker);
    }

    const preferredSourceSelect = document.getElementById("preferredSourceSelect");
    if (preferredSourceSelect) {
        preferredSourceSelect.addEventListener("change", async () => {
            try {
                await fetch("save_preferred_source.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "manga_id=" + encodeURIComponent(currentMangaId) +
                          "&source=" + encodeURIComponent(preferredSourceSelect.value),
                });
            } catch (err) { alert("Gagal simpan preferensi sumber: " + err.message); }
        });
    }

    // Hapus Single Manga (admin-only, tombol hanya ada kalau isAdmin())
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", async () => {
            try {
                confirmDeleteBtn.disabled = true;
                confirmDeleteBtn.textContent = "Menghapus...";

                const res = await fetch("delete_manga.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "manga_id=" + encodeURIComponent(currentMangaId)
                });
                const data = await res.json();
                if (!data.success) {
                    alert("Gagal menghapus manga: " + data.error);
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Hapus Permanen';
                    return;
                }
                window.location.href = "index.php";
            } catch (err) {
                alert("Error: " + err.message);
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Hapus Permanen';
            }
        });
    }

    // ==== GUEST: Resume/Last-Read dari localStorage ====
    // Kalau user LOGIN, tombol resume & badge "terakhir dibaca" sudah di-render
    // server-side dari DB (lihat PHP di atas). Kalau GUEST, kita tempelkan info
    // yg sama dari localStorage (ditulis oleh reader.php tiap kali baca chapter).
    if (!isLoggedIn) {
        try {
            const raw = localStorage.getItem("manga_last_read:" + currentMangaId);
            if (raw) {
                const info = JSON.parse(raw);
                const resumeBtn = document.getElementById("resumeBtn");
                const resumeBtnText = document.getElementById("resumeBtnText");
                const startOverBtn = document.getElementById("startOverBtn");

                if (resumeBtn && info.chapter_id) {
                    resumeBtn.href = "reader.php?chapter_id=" + encodeURIComponent(info.chapter_id);
                    if (resumeBtnText) resumeBtnText.textContent = "Lanjutkan Chapter " + info.chapter_number;
                    resumeBtn.querySelector("i").className = "bi bi-play-fill fs-5 me-1";
                    if (startOverBtn) startOverBtn.classList.remove("d-none");
                }

                // Tandai baris chapter yg sesuai di daftar sbg "current-read"
                const row = document.querySelector(`#chapterList a[data-chapter-id="${CSS.escape(info.chapter_id)}"]`);
                if (row) {
                    row.classList.add("current-read");
                    if (!row.querySelector(".current-read-icon")) {
                        const icon = document.createElement("i");
                        icon.className = "bi bi-bookmark-fill text-primary current-read-icon";
                        icon.title = "Chapter terakhir dibaca (tersimpan di perangkat ini)";
                        row.querySelector("span.d-flex").appendChild(icon);
                    }
                }
            }
        } catch (e) { /* localStorage nonaktif/rusak, diamkan */ }
    }

    // ==== Auto-Update Background (Polling, Tanpa Reload) ====
    const POLL_INTERVAL_MS = 45000;
    let knownLatestChapter = <?= (float) ($chapters[0]['chapter_number'] ?? 0) ?>;

    function showNewChapterToast(count) {
        const toastEl = document.getElementById('newChapterToast');
        const textEl = document.getElementById('newChapterToastText');
        textEl.innerHTML = `<i class="bi bi-stars me-1"></i> ` +
            (count === 1 ? '1 chapter baru tersedia!' : `${count} chapter baru tersedia!`);
        bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 6000 }).show();
    }

    function prependNewChapters(newChapters) {
        const list = document.getElementById("chapterList");
        const ordered = [...newChapters].reverse();

        ordered.forEach(ch => {
            const a = document.createElement("a");
            a.className = "list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 just-added";
            a.href = "reader.php?chapter_id=" + encodeURIComponent(ch.chapter_id);
            a.dataset.chapterId = ch.chapter_id;
            a.dataset.search = (ch.chapter_number + " " + (ch.chapter_title || "")).toLowerCase();

            const titlePart = ch.chapter_title
                ? ` — <span class="text-secondary font-normal">${ch.chapter_title.replace(/</g, "&lt;")}</span>`
                : "";

            a.innerHTML = `
                <span class="fw-medium">Chapter ${ch.chapter_number}${titlePart}</span>
                <span class="d-flex align-items-center gap-2 flex-shrink-0">
                    <small class="text-secondary">${ch.date_label}</small>
                    <span class="badge text-bg-primary">Baru</span>
                </span>
            `;

            list.prepend(a);
            items.unshift(a);

            setTimeout(() => a.classList.remove("just-added"), 2500);
        });
    }

    async function pollForMangaUpdates() {
        try {
            const res = await fetch(`check_manga_updates.php?manga_id=${encodeURIComponent(currentMangaId)}&since=${knownLatestChapter}`, { cache: 'no-store' });
            const data = await res.json();
            if (!data.success) return;

            if (data.new_chapters && data.new_chapters.length > 0) {
                prependNewChapters(data.new_chapters);
                knownLatestChapter = data.latest_chapter_number;

                const countEl = document.getElementById("totalChapterCount");
                if (countEl) countEl.textContent = data.total_chapters;

                showNewChapterToast(data.new_chapters.length);
            }
        } catch (err) {
            console.warn('Gagal memeriksa update background:', err.message);
        }
    }

    let mangaPollTimer = setInterval(pollForMangaUpdates, POLL_INTERVAL_MS);

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(mangaPollTimer);
            mangaPollTimer = null;
        } else {
            pollForMangaUpdates();
            if (!mangaPollTimer) mangaPollTimer = setInterval(pollForMangaUpdates, POLL_INTERVAL_MS);
        }
    });

    if (window.BroadcastChannel) {
        const updateChannel = new BroadcastChannel('manga_reader_updates');
        updateChannel.addEventListener('message', (event) => {
            const msg = event.data;
            if (msg && msg.type === 'manga_updated' && msg.manga_id === currentMangaId) {
                pollForMangaUpdates();
            }
        });
    }
</script>
</body>
</html>
