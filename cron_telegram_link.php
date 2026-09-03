<?php
/**
 * Poll Telegram getUpdates(), cocokkan pesan "/link KODE" ke users.telegram_link_code,
 * lalu simpan chat_id user tsb. Dijalankan berkala lewat GitHub Actions (protected
 * CRON_SECRET_KEY sama spt cron_update.php) sebelum sync manga berjalan.
 *
 * PROTEKSI:
 *  - Butuh CRON_SECRET_KEY (kecuali CLI) -- pola sama dgn cron_update.php.
 *  - offset getUpdates disimpan di telegram_bot_state -> anti re-process/replay.
 *  - Hanya proses pesan dari chat PRIVAT (tolak grup/channel/supergroup).
 *  - Kode wajib exact match & belum expired.
 *  - chat_id unik: kalau sudah dipakai user lain, DITOLAK (tidak menimpa binding lama).
 *  - Kode langsung dibersihkan (single-use) setelah dipakai, baik sukses maupun gagal.
 */

require_once "config.php";
require_once "telegram_functions.php";

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

function tlog($msg) {
    if (php_sapi_name() === 'cli') echo $msg . "\n";
}

$botToken = env("TELEGRAM_BOT_TOKEN", "");
if (!$botToken) {
    $result = ["success" => false, "error" => "TELEGRAM_BOT_TOKEN belum diatur di .env"];
    tlog($result["error"]);
    if (!$isCli) echo json_encode($result);
    exit;
}

$stmt = $pdo->query("SELECT last_update_id FROM telegram_bot_state WHERE id = 1");
$state = $stmt->fetch();
$lastUpdateId = $state ? (int) $state["last_update_id"] : 0;

$updates = telegramGetUpdates($botToken, $lastUpdateId + 1);
if ($updates === null) {
    $result = ["success" => false, "error" => "Gagal memanggil Telegram getUpdates API."];
    tlog($result["error"]);
    if (!$isCli) echo json_encode($result);
    exit;
}

$processed = 0;
$linked = 0;
$maxUpdateId = $lastUpdateId;

foreach ($updates as $update) {
    $updateId = (int) ($update["update_id"] ?? 0);
    if ($updateId > $maxUpdateId) $maxUpdateId = $updateId;
    $processed++;

    $message = $update["message"] ?? null;
    if (!$message) continue;

    // Proteksi: tolak apapun yang bukan chat privat 1-on-1.
    if (($message["chat"]["type"] ?? "") !== "private") continue;

    $chatId = (string) ($message["chat"]["id"] ?? "");
    $text = trim((string) ($message["text"] ?? ""));
    if ($chatId === "" || $text === "") continue;

    if (!preg_match('/^\/(?:start|link)\s+([A-Za-z0-9]{6,16})$/i', $text, $m)) continue;
    $code = strtoupper($m[1]);

    $stmt = $pdo->prepare("
        SELECT id, username FROM users
        WHERE telegram_link_code = :code AND telegram_link_code_expires > NOW()
        LIMIT 1
    ");
    $stmt->execute([":code" => $code]);
    $user = $stmt->fetch();

    if (!$user) {
        sendTelegramMessage($botToken, $chatId, "❌ Kode tidak valid atau sudah kedaluwarsa. Generate kode baru dari halaman profil.");
        continue;
    }

    // Proteksi: 1 akun Telegram cuma boleh terhubung ke 1 akun Manga Reader.
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_chat_id = :cid AND id != :uid");
    $stmt->execute([":cid" => $chatId, ":uid" => $user["id"]]);
    if ($stmt->fetch()) {
        sendTelegramMessage($botToken, $chatId, "❌ Akun Telegram ini sudah terhubung ke akun lain di Manga Reader.");
        $clear = $pdo->prepare("UPDATE users SET telegram_link_code = NULL, telegram_link_code_expires = NULL WHERE id = :id");
        $clear->execute([":id" => $user["id"]]);
        continue;
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET telegram_chat_id = :cid, telegram_notify_enabled = 1, telegram_linked_at = NOW(),
            telegram_link_code = NULL, telegram_link_code_expires = NULL
        WHERE id = :id
    ");
    $stmt->execute([":cid" => $chatId, ":id" => $user["id"]]);

    $safeUsername = htmlspecialchars($user["username"], ENT_QUOTES);
    sendTelegramMessage($botToken, $chatId, "✅ Berhasil! Akun Telegram kamu terhubung ke Manga Reader ({$safeUsername}). Kamu akan dapat notifikasi saat manga bookmark-mu update.");
    $linked++;
    tlog("Linked user #{$user['id']} ({$user['username']}) -> chat_id $chatId");
}

$stmt = $pdo->prepare("UPDATE telegram_bot_state SET last_update_id = :id WHERE id = 1");
$stmt->execute([":id" => $maxUpdateId]);

$result = ["success" => true, "processed" => $processed, "linked" => $linked];
tlog("Selesai. Diproses: $processed update, berhasil link: $linked");
if (!$isCli) echo json_encode($result);