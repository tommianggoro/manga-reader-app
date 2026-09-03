<?php
require_once "config.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "Metode request harus POST"]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE users
    SET telegram_chat_id = NULL, telegram_notify_enabled = 0,
        telegram_link_code = NULL, telegram_link_code_expires = NULL,
        telegram_linked_at = NULL
    WHERE id = :id
");
$stmt->execute([":id" => currentUserId()]);

echo json_encode(["success" => true]);