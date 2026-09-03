<?php
/**
 * Fungsi-fungsi integrasi Telegram Bot:
 *  - Generate & simpan kode link akun (expiry, single-use, cooldown anti-spam)
 *  - sendMessage() & getUpdates() ke Telegram Bot API
 *  - Notifikasi chapter baru KHUSUS utk manga yang di-bookmark user
 *
 * LAYER PROTEKSI di file ini:
 *  - Token bot HANYA dibaca dari .env server-side, tidak pernah dikirim ke browser.
 *  - Kode link: acak, TTL pendek, single-use, ada cooldown antar-generate.
 *  - Judul manga (hasil scraping sumber eksternal, TIDAK bisa dipercaya penuh)
 *    di-escape htmlspecialchars() sebelum dikirim sbg pesan parse_mode=HTML,
 *    supaya tidak merusak format pesan / menyuntik tag asing.
 */

const TELEGRAM_LINK_CODE_TTL_MINUTES = 10;
const TELEGRAM_LINK_COOLDOWN_SECONDS = 30;

/** Generate kode link baru utk user (overwrite kode lama -- cuma 1 kode aktif per user). */
function generateTelegramLinkCode(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT telegram_link_code_expires FROM users WHERE id = :id");
    $stmt->execute([":id" => $userId]);
    $row = $stmt->fetch();

    // Proteksi cooldown: cegah spam klik "Generate Kode" berturut-turut.
    if ($row && $row["telegram_link_code_expires"]) {
        $expiresAt = strtotime($row["telegram_link_code_expires"]);
        $issuedAt = $expiresAt - (TELEGRAM_LINK_CODE_TTL_MINUTES * 60);
        if (time() - $issuedAt < TELEGRAM_LINK_COOLDOWN_SECONDS) {
            $wait = TELEGRAM_LINK_COOLDOWN_SECONDS - (time() - $issuedAt);
            return ["success" => false, "error" => "Tunggu {$wait} detik sebelum generate kode baru."];
        }
    }

    // Kode acak uppercase alfanumerik. TTL singkat + single-use bikin brute force tidak praktis.
    $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    $expiresAt = date("Y-m-d H:i:s", time() + TELEGRAM_LINK_CODE_TTL_MINUTES * 60);

    $stmt = $pdo->prepare("
        UPDATE users
        SET telegram_link_code = :code, telegram_link_code_expires = :exp
        WHERE id = :id
    ");
    $stmt->execute([":code" => $code, ":exp" => $expiresAt, ":id" => $userId]);

    return ["success" => true, "code" => $code, "ttl_minutes" => TELEGRAM_LINK_CODE_TTL_MINUTES];
}

function sendTelegramMessage(string $botToken, string $chatId, string $text): bool
{
    $ch = curl_init("https://api.telegram.org/bot{$botToken}/sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        "chat_id" => $chatId,
        "text" => $text,
        "parse_mode" => "HTML",
        "disable_web_page_preview" => true,
    ]));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200 && $response !== false;
}

/** Ambil update baru dari Telegram. Timeout pendek krn dipanggil dari cron, bukan long-poll interaktif. */
function telegramGetUpdates(string $botToken, int $offset): ?array
{
    $url = "https://api.telegram.org/bot{$botToken}/getUpdates?offset={$offset}&timeout=5&allowed_updates=" . urlencode('["message"]');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;
    $data = json_decode($response, true);
    if (!$data || empty($data["ok"]) || !isset($data["result"])) return null;

    return $data["result"];
}

/**
 * Kirim notifikasi chapter baru ke semua user yang bookmark manga tsb DAN sudah
 * link + aktifkan notif Telegram. 1 pesan per user (dirangkum semua manga-nya),
 * bukan per chapter, supaya tidak spam.
 *
 * $updatedMangaMap: [manga_id => jumlah_chapter_baru]
 * Return: array user_id yang berhasil dikirimi.
 */
function notifyBookmarkUpdates(PDO $pdo, array $updatedMangaMap): array
{
    if (empty($updatedMangaMap)) return [];

    $botToken = env("TELEGRAM_BOT_TOKEN", "");
    if (!$botToken) return [];

    $mangaIds = array_keys($updatedMangaMap);
    $placeholders = implode(",", array_fill(0, count($mangaIds), "?"));

    $stmt = $pdo->prepare("
        SELECT u.id AS user_id, u.telegram_chat_id, m.manga_id, m.title, m.latest_chapter_number
        FROM user_manga_state s
        JOIN users u ON u.id = s.user_id
        JOIN mangas m ON m.manga_id = s.manga_id
        WHERE s.is_favorite = 1
          AND s.manga_id IN ($placeholders)
          AND u.telegram_chat_id IS NOT NULL
          AND u.telegram_notify_enabled = 1
    ");
    $stmt->execute($mangaIds);

    $perUser = [];
    foreach ($stmt->fetchAll() as $row) {
        $uid = (int) $row["user_id"];
        if (!isset($perUser[$uid])) {
            $perUser[$uid] = ["chat_id" => $row["telegram_chat_id"], "items" => []];
        }
        $perUser[$uid]["items"][] = $row;
    }

    $sentTo = [];
    foreach ($perUser as $uid => $data) {
        $lines = [];
        foreach ($data["items"] as $it) {
            $safeTitle = htmlspecialchars($it["title"], ENT_QUOTES);
            $lines[] = "• <b>{$safeTitle}</b> — Chapter " . formatChapterNumber($it["latest_chapter_number"]);
        }
        $text = "📖 <b>Chapter Baru di Bookmark Kamu!</b>\n\n" . implode("\n", $lines);

        try {
            if (sendTelegramMessage($botToken, $data["chat_id"], $text)) {
                $sentTo[] = $uid;
            }
        } catch (Exception $e) {
            continue; // 1 user gagal kirim tidak boleh menghentikan user lain
        }
    }

    return $sentTo;
}