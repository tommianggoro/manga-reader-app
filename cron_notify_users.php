<?php
/**
 * Per-user Telegram Notification Cron
 * Runs after cron_update.php, sends notifications for new chapters
 * in manga that users have favorited (bookmarked).
 *
 * Usage:
 *   php cron_notify_users.php           # CLI
 *   cron_notify_users.php?key=...       # Web request
 *   require_once "cron_notify_users.php"; runNotifyUsers($pdo); // As include
 */

if (!function_exists('runNotifyUsers')) {
    require_once "config.php";
    require_once "sync_functions.php";
}

// Shared logging function - guarded to avoid redeclare when included by cron_full.php
if (!function_exists('cronLog')) {
    function cronLog($msg) {
        if (php_sapi_name() === 'cli') {
            echo $msg . "\n";
        }
    }
}

// Shared Telegram sender - guarded
if (!function_exists('sendTelegram')) {
    function sendTelegram(string $chatId, string $text, string $token): bool {
        $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "chat_id" => $chatId,
            "text" => $text,
            "parse_mode" => "HTML",
            "disable_web_page_preview" => true
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }
}

function runNotifyUsers(PDO $pdo): array {
    $isCli = (php_sapi_name() === 'cli');
    $keyInput = $_GET['key'] ?? null;

    if (!$isCli && $keyInput !== CRON_SECRET_KEY) {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["success" => false, "error" => "Akses ditolak: Key tidak valid."]);
        exit;
    }

    if (!$isCli) {
        header("Content-Type: application/json; charset=utf-8");
    }

    set_time_limit(0);

    $botToken = getenv("TELEGRAM_BOT_TOKEN") ?: $_ENV["TELEGRAM_BOT_TOKEN"] ?? "";
    if (!$botToken) {
        $msg = "TELEGRAM_BOT_TOKEN not configured";
        cronLog($msg);
        if (!$isCli) echo json_encode(["success" => false, "error" => $msg]);
        return ["success" => false, "error" => $msg];
    }

    cronLog("=== MEMULAI NOTIFIKASI PER-USER ===");

    $startTime = microtime(true);

    // Initialize baseline for users who never had notifications (NULL -> current max chapter)
    // Prevents backlog spam on first run for existing favorites
    $stmt = $pdo->prepare("
        UPDATE user_manga_state ums
        JOIN (
            SELECT manga_id, MAX(chapter_number) AS maxnum
            FROM chapters
            GROUP BY manga_id
        ) c ON c.manga_id = ums.manga_id
        SET ums.last_notified_chapter_number = c.maxnum
        WHERE ums.last_notified_chapter_number IS NULL
          AND ums.is_favorite = 1
    ");
    $stmt->execute();
    $baselineUpdated = $stmt->rowCount();
    if ($baselineUpdated > 0) {
        cronLog("Initialized notification baseline for {$baselineUpdated} user-manga pairs");
    }

    // 1. Ambil semua user yang punya telegram_chat_id DAN punya manga favorit
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.telegram_chat_id, ums.manga_id
        FROM users u
        JOIN user_manga_state ums ON u.id = ums.user_id
        WHERE u.telegram_chat_id IS NOT NULL
          AND ums.is_favorite = 1
    ");
    $stmt->execute();
    $userMangas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($userMangas)) {
        cronLog("Tidak ada user dengan Telegram Chat ID dan manga favorit.");
        return [
            "success" => true,
            "message" => "No users with Telegram Chat ID and favorites",
            "execution_time_seconds" => round(microtime(true) - $startTime, 2),
            "total_users" => 0,
            "sent" => 0,
            "errors" => 0
        ];
    }

    // Group by user
    $userGroups = [];
    foreach ($userMangas as $row) {
        $uid = $row['id'];
        if (!isset($userGroups[$uid])) {
            $userGroups[$uid] = [
                'username' => $row['username'],
                'chat_id' => $row['telegram_chat_id'],
                'manga_ids' => []
            ];
        }
        $userGroups[$uid]['manga_ids'][] = $row['manga_id'];
    }

    cronLog("User dengan notifikasi aktif: " . count($userGroups));

    // 2. Untuk tiap user, cek chapter baru di manga favoritnya
    $totalSent = 0;
    $totalErrors = 0;
    $results = [];

    foreach ($userGroups as $uid => $userData) {
        $chatId = $userData['chat_id'];
        $username = $userData['username'];
        $mangaIds = $userData['manga_ids'];

        // Cari chapter baru untuk manga ini
        // Gunakan last_notified_chapter_number sebagai baseline, kalau null sudah di-initialize di atas
        // last_notified_chapter_number TERPISAH dari last_read_chapter_number (yg dipakai utk "Continue Reading")
        $placeholders = implode(',', array_fill(0, count($mangaIds), '?'));

        // Ambil chapter terbaru per manga (semua parameter posisional: ?...? untuk IN, lalu ? untuk uid di subquery)
        $sql = "
            SELECT c.manga_id, c.chapter_number, c.chapter_title, c.chapter_id, m.title as manga_title
            FROM chapters c
            JOIN mangas m ON c.manga_id = m.manga_id
            WHERE c.manga_id IN ($placeholders)
              AND c.chapter_number > (
                  SELECT COALESCE(ums.last_notified_chapter_number, 0)
                  FROM user_manga_state ums
                  WHERE ums.user_id = ? AND ums.manga_id = c.manga_id
              )
            ORDER BY c.manga_id, c.chapter_number ASC
        ";
        $stmt = $pdo->prepare($sql);
        // Params: manga_ids... then uid
        $params = array_merge($mangaIds, [$uid]);
        $stmt->execute($params);
        $newChapters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($newChapters)) {
            $results[] = ["username" => $username, "sent" => 0, "skipped" => true];
            continue;
        }

        // Group by manga
        $byManga = [];
        foreach ($newChapters as $ch) {
            $mid = $ch['manga_id'];
            if (!isset($byManga[$mid])) {
                $byManga[$mid] = [
                    'title' => $ch['manga_title'],
                    'chapters' => []
                ];
            }
            $byManga[$mid]['chapters'][] = $ch;
        }

        // Build message (escape title for HTML safety)
        $lines = [];
        foreach ($byManga as $mid => $data) {
            $title = htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8');
            $chapters = $data['chapters'];
            $chapNums = array_map(fn($c) => formatChapterNumber($c['chapter_number']), $chapters);
            $lines[] = "• <b>$title</b>: Chapter " . implode(", ", $chapNums) . " (" . count($chapters) . " baru)";
        }

        $message = "📚 <b>Chapter Baru di Favoritmu</b>\n\n" . implode("\n", $lines);
        $message .= "\n\nBuka: <a href=\"" . (getenv("SITE_URL") ?: "https://mangareader.infinityfreeapp.com") . "\">Manga Reader</a>";

        if (sendTelegram($chatId, $message, $botToken)) {
            $totalSent++;
            $results[] = ["username" => $username, "sent" => count($newChapters), "mangas" => count($byManga)];
            cronLog("✓ Notifikasi ke $username: " . count($newChapters) . " chapter di " . count($byManga) . " manga");
        } else {
            $totalErrors++;
            $results[] = ["username" => $username, "error" => "Failed to send"];
            cronLog("❌ Gagal kirim ke $username");
        }

        // Update last_notified_chapter_number untuk manga yang sudah dikirim notifnya
        // Agar tidak dikirim ulang di run berikutnya
        // INI TERPISAH dari last_read_chapter_number (yg dipakai utk "Continue Reading" button)
        foreach ($byManga as $mid => $data) {
            $latestChapter = max(array_map(fn($c) => (float)$c['chapter_number'], $data['chapters']));
            $stmt = $pdo->prepare("
                UPDATE user_manga_state
                SET last_notified_chapter_number = :num
                WHERE user_id = :uid AND manga_id = :mid
            ");
            $stmt->execute([":num" => $latestChapter, ":uid" => $uid, ":mid" => $mid]);
        }

        // Rate limit: delay antar user
        if (count($userGroups) > 1) {
            usleep(100000); // 100ms
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);
    cronLog("\n=== NOTIFIKASI SELESAI ({$executionTime}s) ===");
    cronLog("Total user diproses: " . count($userGroups));
    cronLog("Berhasil dikirim: $totalSent");
    cronLog("Gagal: $totalErrors");

    $result = [
        "success" => true,
        "execution_time_seconds" => $executionTime,
        "total_users" => count($userGroups),
        "sent" => $totalSent,
        "errors" => $totalErrors,
        "results" => $results
    ];

    if (!$isCli) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    return $result;
}

// Auto-run if accessed directly (not included) - check by script entry point
// $_SERVER['argv'][0] contains the script name in CLI; in web it's not set or different
$isDirectRun = (php_sapi_name() === 'cli' && isset($_SERVER['argv'][0]) && basename($_SERVER['argv'][0]) === basename(__FILE__))
            || (php_sapi_name() !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__));

if ($isDirectRun) {
    require_once "config.php";
    runNotifyUsers($pdo);
}