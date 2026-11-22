<?php
/**
 * Reading Statistics Dashboard - Statistik bacaan user (hanya untuk user login).
 * Menampilkan: total chapter dibaca, estimasi waktu, streak, genre favorit, recap bulanan/tahunan.
 */
require_once "config.php";
requireAuth();

$userId = currentUserId();
$username = currentUsername();

// ============================================================
// 1. DATA QUERIES
// ============================================================

// Total chapter yang sudah dibaca (unique chapter_id di reading_progress)
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT chapter_id) FROM reading_progress WHERE user_id = :uid");
$stmt->execute([":uid" => $userId]);
$totalChaptersRead = (int) $stmt->fetchColumn();

// Total manga yang sudah dibaca (punya progress)
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT manga_id) FROM user_manga_state WHERE user_id = :uid AND last_read_chapter_id IS NOT NULL");
$stmt->execute([":uid" => $userId]);
$totalMangaRead = (int) $stmt->fetchColumn();

// Estimasi total waktu baca (asumsi: 2 menit per chapter rata-rata)
$estimatedMinutes = $totalChaptersRead * 2;
$estimatedHours = floor($estimatedMinutes / 60);
$estimatedMins = $estimatedMinutes % 60;

// Manga yang sedang dibaca (punya progress tapi belum selesai - latest_chapter < total_chapters)
$stmt = $pdo->prepare("
    SELECT m.manga_id, m.title, m.cover_image_url, m.latest_chapter_number,
           s.last_read_chapter_number, s.last_read_at
    FROM user_manga_state s
    JOIN mangas m ON m.manga_id = s.manga_id
    WHERE s.user_id = :uid AND s.last_read_chapter_id IS NOT NULL
    ORDER BY s.last_read_at DESC
    LIMIT 10
");
$stmt->execute([":uid" => $userId]);
$currentlyReading = $stmt->fetchAll();

// Genre favorit (dari manga yang dibaca, weighted by chapters read per manga)
$stmt = $pdo->prepare("
    SELECT m.genres
    FROM user_manga_state s
    JOIN mangas m ON m.manga_id = s.manga_id
    WHERE s.user_id = :uid AND s.last_read_chapter_id IS NOT NULL
");
$stmt->execute([":uid" => $userId]);
$readMangaGenres = $stmt->fetchAll(PDO::FETCH_COLUMN);

$genreCounts = [];
foreach ($readMangaGenres as $genresStr) {
    if (empty($genresStr)) continue;
    foreach (explode(",", $genresStr) as $g) {
        $g = trim($g);
        if ($g !== "") $genreCounts[$g] = ($genreCounts[$g] ?? 0) + 1;
    }
}
arsort($genreCounts);
$topGenres = array_slice($genreCounts, 0, 8, true);

// Riwayat baca per hari (30 hari terakhir) untuk heatmap/chart
// Join reading_progress (user_manga_state tidak punya chapter_id)
// reading_progress menggunakan kolom updated_at, bukan read_at
$stmt = $pdo->prepare("
    SELECT DATE(rp.updated_at) as read_date, COUNT(DISTINCT rp.chapter_id) as chapters
    FROM reading_progress rp
    JOIN chapters c ON c.id = rp.chapter_id
    WHERE rp.user_id = :uid AND rp.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(rp.updated_at)
    ORDER BY read_date ASC
");
$stmt->execute([":uid" => $userId]);
$dailyReads = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Hitung streak (hari berurutan dengan aktivitas baca)
$streak = 0;
$currentStreak = 0;
$today = date("Y-m-d");
$yesterday = date("Y-m-d", strtotime("-1 day"));

$datesWithReads = array_keys($dailyReads);
// Cek apakah hari ini atau kemarin ada aktivitas
$hasToday = in_array($today, $datesWithReads);
$hasYesterday = in_array($yesterday, $datesWithReads);

if ($hasToday || $hasYesterday) {
    $checkDate = $hasToday ? $today : $yesterday;
    $currentStreak = 1;
    $checkDate = date("Y-m-d", strtotime($checkDate . " -1 day"));
    while (in_array($checkDate, $datesWithReads)) {
        $currentStreak++;
        $checkDate = date("Y-m-d", strtotime($checkDate . " -1 day"));
    }
}

// Longest streak ever (hitung dari semua history)
$stmt = $pdo->prepare("
    SELECT DATE(last_read_at) as read_date
    FROM user_manga_state
    WHERE user_id = :uid AND last_read_at IS NOT NULL
    GROUP BY DATE(last_read_at)
    ORDER BY read_date ASC
");
$stmt->execute([":uid" => $userId]);
$allReadDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

$longestStreak = 0;
$tempStreak = 0;
$prevDate = null;
foreach ($allReadDates as $d) {
    if ($prevDate === null) {
        $tempStreak = 1;
    } else {
        $diff = (strtotime($d) - strtotime($prevDate)) / 86400;
        if ($diff === 1) {
            $tempStreak++;
        } else {
            $tempStreak = 1;
        }
    }
    $longestStreak = max($longestStreak, $tempStreak);
    $prevDate = $d;
}

// Monthly recap (6 bulan terakhir)
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(last_read_at, '%Y-%m') as month, COUNT(DISTINCT chapter_id) as chapters, COUNT(DISTINCT manga_id) as manga_count
    FROM user_manga_state
    WHERE user_id = :uid AND last_read_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(last_read_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([":uid" => $userId]);
$monthlyStats = $stmt->fetchAll();

// Yearly total
$stmt = $pdo->prepare("
    SELECT YEAR(last_read_at) as year, COUNT(DISTINCT chapter_id) as chapters, COUNT(DISTINCT manga_id) as manga_count
    FROM user_manga_state
    WHERE user_id = :uid AND last_read_at IS NOT NULL
    GROUP BY YEAR(last_read_at)
    ORDER BY year DESC
");
$stmt->execute([":uid" => $userId]);
$yearlyStats = $stmt->fetchAll();

// Favorite manga (paling banyak chapter dibaca)
$stmt = $pdo->prepare("
    SELECT m.manga_id, m.title, m.cover_image_url, COUNT(DISTINCT rp.chapter_id) as chapters_read
    FROM reading_progress rp
    JOIN chapters c ON c.chapter_id = rp.chapter_id
    JOIN mangas m ON m.manga_id = c.manga_id
    WHERE rp.user_id = :uid
    GROUP BY m.manga_id, m.title, m.cover_image_url
    ORDER BY chapters_read DESC
    LIMIT 5
");
$stmt->execute([":uid" => $userId]);
$topManga = $stmt->fetchAll();

// Reading velocity (chapter per minggu, 8 minggu terakhir)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT chapter_id) as chapters
    FROM user_manga_state
    WHERE user_id = :uid AND last_read_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
");
$stmt->execute([":uid" => $userId]);
$recentChapters = (int) $stmt->fetchColumn();
$weeklyVelocity = round($recentChapters / 8, 1);

// Helper
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
    <title>Statistik Bacaan - <?= htmlspecialchars($username) ?></title>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('manga_theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
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
            --bs-body-bg: #10131a; --bs-body-color: #eae7e0; --bs-primary: #f2a541;
            --bs-primary-rgb: 242, 165, 65; --bs-border-color: #242938; --bs-secondary-bg: #171b26; --bs-tertiary-bg: #202636;
        }
        [data-bs-theme="light"] {
            --bs-body-bg: #f5f6f8; --bs-body-color: #212529; --bs-primary: #e08b18;
            --bs-primary-rgb: 224, 139, 24; --bs-border-color: #e0e4eb; --bs-secondary-bg: #ffffff; --bs-tertiary-bg: #f0f2f5;
        }
        body { font-family: 'Inter', system-ui, sans-serif; }
        .brand-font { font-family: 'Bitter', Georgia, serif; }

        .stat-card { background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); border-radius: 16px; padding: 1.5rem; transition: transform .15s ease, border-color .15s ease; }
        .stat-card:hover { border-color: var(--bs-primary); transform: translateY(-2px); }
        .stat-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .stat-value { font-size: 2.5rem; font-weight: 700; line-height: 1.1; }
        .stat-label { font-size: 0.85rem; color: var(--bs-secondary-color); }

        .genre-tag { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.8rem; border-radius: 50rem; background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); font-size: 0.85rem; }
        .genre-tag .bar { width: 60px; height: 6px; background: var(--bs-border-color); border-radius: 3px; overflow: hidden; }
        .genre-tag .bar-fill { height: 100%; background: linear-gradient(90deg, var(--bs-primary), #f7bc70); border-radius: 3px; transition: width 0.5s ease; }

        .manga-stat-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bs-tertiary-bg); border-radius: 10px; border: 1px solid var(--bs-border-color); transition: border-color .15s ease; }
        .manga-stat-item:hover { border-color: var(--bs-primary); }
        .manga-stat-cover { width: 50px; aspect-ratio: 2/3; border-radius: 6px; object-fit: cover; }

        .heatmap { display: flex; flex-wrap: wrap; gap: 3px; }
        .heatmap-day { width: 12px; height: 12px; border-radius: 2px; transition: transform .1s ease; }
        .heatmap-day:hover { transform: scale(1.5); z-index: 1; }
        .heatmap-day.level-0 { background: var(--bs-border-color); }
        .heatmap-day.level-1 { background: #1a5c2e; }
        .heatmap-day.level-2 { background: #238636; }
        .heatmap-day.level-3 { background: #2ea043; }
        .heatmap-day.level-4 { background: #3fb950; }

        .monthly-bar { height: 32px; border-radius: 6px; background: linear-gradient(90deg, var(--bs-primary), #f7bc70); transition: width 0.5s ease; display: flex; align-items: center; padding: 0 0.75rem; font-size: 0.8rem; font-weight: 600; color: #10131a; min-width: 2rem; justify-content: flex-end; }

        .streak-card { background: linear-gradient(135deg, var(--bs-secondary-bg), var(--bs-tertiary-bg)); border: 1px solid var(--bs-primary); border-radius: 16px; padding: 1.5rem; text-align: center; }
        .streak-value { font-size: 4rem; font-weight: 800; color: var(--bs-primary); line-height: 1; }

        .empty-state { text-align: center; padding: 3rem; color: var(--bs-secondary-color); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

        @media (max-width: 576px) {
            .stat-value { font-size: 2rem; }
            .streak-value { font-size: 3rem; }
            .heatmap-day { width: 10px; height: 10px; }
        }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 900px;">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a class="d-inline-flex align-items-center gap-1 text-decoration-none fw-medium" href="index.php">
            <i class="bi bi-arrow-left"></i> Kembali ke koleksi
        </a>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="theme-toggle-btn" id="themeToggle" title="Ganti Mode Gelap/Terang">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
            </button>
            <button type="button" class="theme-toggle-btn" onclick="logoutUser()" title="Keluar (<?= htmlspecialchars($username) ?>)">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </div>
    </div>

    <h1 class="brand-font h3 mb-1"><i class="bi bi-graph-up-arrow text-primary me-1"></i> Statistik Bacaan</h1>
    <p class="text-secondary mb-4">Ringkasan aktivitas membaca manga kamu, <?= htmlspecialchars($username) ?>.</p>

    <?php if ($totalChaptersRead === 0): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="bi bi-book-half"></i>
            <h3 class="h5 brand-font mt-3">Belum Ada Data Bacaan</h3>
            <p class="text-secondary mx-auto" style="max-width: 400px;">
                Mulai baca manga dari koleksimu untuk melihat statistik di sini.
                Progres bacaan, streak, dan genre favorit akan otomatis tercatat.
            </p>
            <a href="index.php" class="btn btn-primary mt-3"><i class="bi bi-house-fill me-1"></i> Buka Koleksi</a>
        </div>
    <?php else: ?>

    <!-- Overview Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-primary-subtle text-primary mx-auto mb-2"><i class="bi bi-journals"></i></div>
                <div class="stat-value"><?= number_format($totalChaptersRead) ?></div>
                <div class="stat-label">Chapter Dibaca</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-info-subtle text-info mx-auto mb-2"><i class="bi bi-collection"></i></div>
                <div class="stat-value"><?= number_format($totalMangaRead) ?></div>
                <div class="stat-label">Manga Dibaca</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-warning-subtle text-warning mx-auto mb-2"><i class="bi bi-clock"></i></div>
                <div class="stat-value"><?= $estimatedHours > 0 ? "{$estimatedHours}j {$estimatedMins}m" : "{$estimatedMins}m" ?></div>
                <div class="stat-label">Estimasi Waktu</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-success-subtle text-success mx-auto mb-2"><i class="bi bi-speedometer2"></i></div>
                <div class="stat-value"><?= $weeklyVelocity ?></div>
                <div class="stat-label">Ch/Minggu (8 wk)</div>
            </div>
        </div>
    </div>

    <!-- Streak Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="streak-card">
                <div class="streak-value"><?= $currentStreak ?></div>
                <div class="text-secondary mt-1">Hari Berturut-turut</div>
                <div class="small text-secondary mt-2">Streak saat ini<?= $currentStreak > 0 ? " (mulai " . date("d M", strtotime("-" . ($currentStreak - 1) . " days")) . ")" : "" ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="streak-card" style="border-color: var(--bs-warning);">
                <div class="streak-value" style="color: var(--bs-warning);"><?= $longestStreak ?></div>
                <div class="text-secondary mt-1">Rekor Terpanjang</div>
                <div class="small text-secondary mt-2">Panjang streak tertinggi sepanjang masa</div>
            </div>
        </div>
    </div>

    <!-- Genre Favorit -->
    <?php if (!empty($topGenres)): ?>
    <div class="card mb-4" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
        <div class="card-header bg-transparent border-bottom" style="border-color: var(--bs-border-color);">
            <h5 class="brand-font mb-0"><i class="bi bi-tags text-primary me-1"></i> Genre Favorit</h5>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <?php
                $maxCount = max($topGenres);
                foreach ($topGenres as $genre => $count):
                    $pct = $maxCount > 0 ? round(($count / $maxCount) * 100) : 0;
                ?>
                    <div class="genre-tag">
                        <span><?= htmlspecialchars($genre) ?></span>
                        <span class="badge text-bg-secondary"><?= $count ?></span>
                        <div class="bar"><div class="bar-fill" style="width: <?= $pct ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Manga -->
    <?php if (!empty($topManga)): ?>
    <div class="card mb-4" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
        <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--bs-border-color);">
            <h5 class="brand-font mb-0"><i class="bi bi-trophy-fill text-warning me-1"></i> Manga Paling Sering Dibaca</h5>
            <small class="text-secondary">Berdasarkan jumlah chapter dibaca</small>
        </div>
        <div class="card-body p-0">
            <?php foreach ($topManga as $i => $m): ?>
                <a href="manga.php?manga_id=<?= urlencode($m['manga_id']) ?>" class="text-decoration-none color-inherit manga-stat-item d-flex align-items-center" style="margin: 0.5rem; border-radius: 10px;">
                    <span class="badge text-bg-primary fw-normal" style="font-size: 1rem; min-width: 2.5rem;">#<?= $i + 1 ?></span>
                    <img src="<?= htmlspecialchars($m['cover_image_url']) ?>" class="manga-stat-cover" alt="" loading="lazy">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate"><?= htmlspecialchars($m['title']) ?></div>
                        <div class="small text-secondary"><?= number_format($m['chapters_read']) ?> chapter dibaca</div>
                    </div>
                    <i class="bi bi-chevron-right text-secondary"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Currently Reading -->
    <?php if (!empty($currentlyReading)): ?>
    <div class="card mb-4" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
        <div class="card-header bg-transparent border-bottom" style="border-color: var(--bs-border-color);">
            <h5 class="brand-font mb-0"><i class="bi bi-bookmark-heart-fill text-danger me-1"></i> Sedang Dibaca</h5>
        </div>
        <div class="card-body p-0">
            <?php foreach ($currentlyReading as $m):
                $progress = $m['latest_chapter_number'] > 0 ? round(($m['last_read_chapter_number'] / $m['latest_chapter_number']) * 100) : 0;
            ?>
                <a href="reader.php?chapter_id=<?= urlencode($m['last_read_chapter_id'] ?? '') ?>" class="text-decoration-none color-inherit manga-stat-item d-flex align-items-center" style="margin: 0.5rem; border-radius: 10px;">
                    <img src="<?= htmlspecialchars($m['cover_image_url']) ?>" class="manga-stat-cover" alt="" loading="lazy">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate"><?= htmlspecialchars($m['title']) ?></div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <div class="flex-grow-1" style="height: 6px; background: var(--bs-border-color); border-radius: 3px; overflow: hidden;">
                                <div style="width: <?= $progress ?>%; height: 100%; background: linear-gradient(90deg, var(--bs-primary), #f7bc70); border-radius: 3px;"></div>
                            </div>
                            <span class="small text-secondary fw-medium"><?= $progress ?>%</span>
                        </div>
                        <div class="small text-secondary">Ch. <?= (float) $m['last_read_chapter_number'] ?> / Ch. <?= (float) $m['latest_chapter_number'] ?> · <?= formatTanggalIndo($m['last_read_at']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Activity Heatmap (30 hari) -->
    <div class="card mb-4" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
        <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--bs-border-color);">
            <h5 class="brand-font mb-0"><i class="bi bi-calendar-heart-fill text-danger me-1"></i> Aktivitas 30 Hari Terakhir</h5>
            <div class="d-flex align-items-center gap-2 small text-secondary">
                <span><i class="bi bi-square" style="color: var(--bs-border-color)"></i> 0</span>
                <span><i class="bi bi-square-fill" style="color: #3fb950"></i> 4+</span>
            </div>
        </div>
        <div class="card-body">
            <div class="heatmap" id="heatmap">
                <?php
                // Generate 30 hari grid (minggu sebagai baris)
                $startDate = new DateTime("-29 days");
                $endDate = new DateTime("today");
                $days = [];
                while ($startDate <= $endDate) {
                    $key = $startDate->format("Y-m-d");
                    $chapters = $dailyReads[$key] ?? 0;
                    $level = 0;
                    if ($chapters > 0) $level = 1;
                    if ($chapters >= 2) $level = 2;
                    if ($chapters >= 3) $level = 3;
                    if ($chapters >= 4) $level = 4;
                    $days[] = ['date' => $key, 'level' => $level, 'chapters' => $chapters];
                    $startDate->modify("+1 day");
                }
                // Render per minggu (kolom = hari, baris = minggu)
                // Kita render sebagai grid flex wrap dengan 7 kolom per minggu
                foreach ($days as $i => $day):
                ?>
                    <div class="heatmap-day level-<?= $day['level'] ?>"
                         title="<?= date("d M Y", strtotime($day['date'])) ?>: <?= $day['chapters'] ?> chapter"
                         data-bs-toggle="tooltip" data-bs-placement="top"></div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex justify-content-between small text-secondary mt-2 px-1">
                <span>Lebih lama</span>
                <span>Hari ini</span>
            </div>
        </div>
    </div>

    <!-- Monthly Recap -->
    <?php if (!empty($monthlyStats)): ?>
    <div class="card mb-4" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
        <div class="card-header bg-transparent border-bottom" style="border-color: var(--bs-border-color);">
            <h5 class="brand-font mb-0"><i class="bi bi-calendar3 text-info me-1"></i> Recap Bulanan (6 Bulan)</h5>
        </div>
        <div class="card-body">
            <?php
            $maxMonthly = max(array_column($monthlyStats, 'chapters'));
            foreach ($monthlyStats as $ms):
                $pct = $maxMonthly > 0 ? round(($ms['chapters'] / $maxMonthly) * 100) : 0;
                $monthLabel = date("M Y", strtotime($ms['month'] . "-01"));
            ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= $monthLabel ?></span>
                        <span class="fw-semibold"><?= $ms['chapters'] ?> ch · <?= $ms['manga_count'] ?> manga</span>
                    </div>
                    <div style="height: 28px; background: var(--bs-tertiary-bg); border-radius: 6px; overflow: hidden; border: 1px solid var(--bs-border-color);">
                        <div class="monthly-bar" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Yearly Stats -->
    <?php if (!empty($yearlyStats)): ?>
    <div class="card" style="background: var(--bs-secondary-bg); border-color: var(--bs-border-color);">
        <div class="card-header bg-transparent border-bottom" style="border-color: var(--bs-border-color);">
            <h5 class="brand-font mb-0"><i class="bi bi-award-fill text-warning me-1"></i> Recap Tahunan</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($yearlyStats as $ys): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="stat-card text-center" style="padding: 1rem;">
                            <div class="stat-value" style="font-size: 2rem;"><?= $ys['chapters'] ?></div>
                            <div class="stat-label"><?= $ys['year'] ?></div>
                            <div class="small text-secondary mt-1"><?= $ys['manga_count'] ?> manga</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php include __DIR__ . "/partials/login_modal.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
    updateThemeUI(localStorage.getItem("manga_theme") || "dark");
    themeToggleBtn.addEventListener("click", () => {
        const newTheme = document.documentElement.getAttribute("data-bs-theme") === "dark" ? "light" : "dark";
        updateThemeUI(newTheme);
    });

    // Tooltips untuk heatmap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
</script>
</body>
</html>