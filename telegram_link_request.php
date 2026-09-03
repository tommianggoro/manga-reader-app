<?php
require_once "config.php";
require_once "telegram_functions.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "Metode request harus POST"]);
    exit;
}

$result = generateTelegramLinkCode($pdo, currentUserId());

// BARU: sertakan deep link siap-klik & username bot, supaya frontend
// tidak perlu tahu cara merakit URL Telegram sendiri.
if ($result["success"]) {
    if (TELEGRAM_BOT_USERNAME !== "") {
        $result["bot_username"] = TELEGRAM_BOT_USERNAME;
        $result["deep_link"] = "https://t.me/" . TELEGRAM_BOT_USERNAME . "?start=" . urlencode($result["code"]);
    }
}

echo json_encode($result);