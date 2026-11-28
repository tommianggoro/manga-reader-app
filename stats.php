<?php
/**
 * Manga Reader - Stats Page (Thin Controller)
 * Displays reading statistics dashboard
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

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

// Manga yang sedang dibaca (punya progress tapi belum selesai)
$stmt = $pdo->prepare("
    SELECT m.manga_id, m.title, m.cover_image_url, m.latest_chapter_number,
           s.last_read_chapter_number, s.last_read_chapter_id, s.last_read_at
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

// Riwayat baca per hari (30 hari terakhir) untuk heatmap
$stmt = $pdo->prepare("
    SELECT DATE(rp.updated_at) as read_date, COUNT(DISTINCT rp.chapter_id) as chapters
    FROM reading_progress rp
    JOIN chapters c ON c.chapter_id = rp.chapter_id
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
    SELECT DATE_FORMAT(rp.updated_at, '%Y-%m') as month, COUNT(DISTINCT rp.chapter_id) as chapters, COUNT(DISTINCT c.manga_id) as manga_count
    FROM reading_progress rp
    JOIN chapters c ON c.chapter_id = rp.chapter_id
    WHERE rp.user_id = :uid AND rp.updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(rp.updated_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([":uid" => $userId]);
$monthlyStats = $stmt->fetchAll();

// Yearly total
$stmt = $pdo->prepare("
    SELECT YEAR(rp.updated_at) as year, COUNT(DISTINCT rp.chapter_id) as chapters, COUNT(DISTINCT c.manga_id) as manga_count
    FROM reading_progress rp
    JOIN chapters c ON c.chapter_id = rp.chapter_id
    WHERE rp.user_id = :uid AND rp.updated_at IS NOT NULL
    GROUP BY YEAR(rp.updated_at)
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
    SELECT COUNT(DISTINCT rp.chapter_id) as chapters
    FROM reading_progress rp
    JOIN chapters c ON c.chapter_id = rp.chapter_id
    WHERE rp.user_id = :uid AND rp.updated_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
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

    <!-- Pre-paint theme script (no flash) -->
    <script>
    (function() {
        const savedTheme = localStorage.getItem('manga_theme') || 'dark';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    })();
    </script>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts: Bitter (serif) + Inter (sans) -->
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Design Tokens -->
    <link href="assets/css/variables.css" rel="stylesheet">

    <link rel="icon" href="favicon.ico" sizes="any">

    <link rel="manifest" href="https://tommianggoro.github.io/manga-reader-app/manifest.json">
    <meta name="theme-color" content="#10131a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Manga Reader">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
</head>
<body>
<div class="container py-4" style="max-width: 900px;">

<?php
// Include header
include __DIR__ . '/views/stats/header.php';

// Include composed content view
$totalChaptersRead = $totalChaptersRead;
$totalMangaRead = $totalMangaRead;
$estimatedHours = $estimatedHours;
$estimatedMins = $estimatedMins;
$weeklyVelocity = $weeklyVelocity;
$currentStreak = $currentStreak;
$longestStreak = $longestStreak;
$topGenres = $topGenres;
$topManga = $topManga;
$currentlyReading = $currentlyReading;
$dailyReads = $dailyReads;
$monthlyStats = $monthlyStats;
$yearlyStats = $yearlyStats;
$username = $username;
$formatTanggalIndo = 'formatTanggalIndo';
include __DIR__ . '/views/stats/content.php';
?>

</div>

<?php include __DIR__ . '/views/partials/login_modal.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme Switcher (shared logic could be extracted to a module later)
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