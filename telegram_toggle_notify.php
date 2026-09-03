<?php
require_once "config.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "Metode request harus POST"]);
    exit;
}

$userId = currentUserId();

$stmt = $pdo->prepare("SELECT telegram_chat_id, telegram_notify_enabled FROM users WHERE id = :id");
$stmt->execute([":id" => $userId]);
$user = $stmt->fetch();

// Proteksi: tidak bisa aktifkan notif kalau belum link akun Telegram.
if (!$user || !$user["telegram_chat_id"]) {
    echo json_encode(["success" => false, "error" => "Hubungkan akun Telegram terlebih dahulu."]);
    exit;
}

$newValue = $user["telegram_notify_enabled"] ? 0 : 1;
$stmt = $pdo->prepare("UPDATE users SET telegram_notify_enabled = :v WHERE id = :id");
$stmt->execute([":v" => $newValue, ":id" => $userId]);

echo json_encode(["success" => true, "telegram_notify_enabled" => (bool) $newValue]);