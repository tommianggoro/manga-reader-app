<?php
require_once "config.php";
requireAdmin();

header("Content-Type: application/json; charset=utf-8");

$targetUserId = (int) ($_POST["user_id"] ?? 0);
$makeAdmin = isset($_POST["is_admin"]) ? (int) $_POST["is_admin"] : null;

if (!$targetUserId || $makeAdmin === null) {
    echo json_encode(["success" => false, "error" => "user_id dan is_admin wajib diisi"]);
    exit;
}

// Cegah admin terakhir mencabut status adminnya sendiri (biar tidak ada yang
// terkunci total dari fitur manage manga).
if ($makeAdmin === 0) {
    $totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = :id");
    $stmt->execute([":id" => $targetUserId]);
    $target = $stmt->fetch();

    if (!$target) {
        echo json_encode(["success" => false, "error" => "User tidak ditemukan"]);
        exit;
    }

    if ($target["is_admin"] && $totalAdmins <= 1) {
        echo json_encode(["success" => false, "error" => "Tidak bisa mencabut admin terakhir. Jadikan user lain admin dulu."]);
        exit;
    }
}

$stmt = $pdo->prepare("UPDATE users SET is_admin = :val WHERE id = :id");
$stmt->execute([":val" => $makeAdmin, ":id" => $targetUserId]);

// Kalau yang diubah adalah diri sendiri (mis. admin lain menaikkan dirinya --
// jarang terjadi tapi tetap ditangani), refresh session supaya konsisten.
if ($targetUserId === (int) currentUserId()) {
    $_SESSION["is_admin"] = (bool) $makeAdmin;
}

echo json_encode(["success" => true, "user_id" => $targetUserId, "is_admin" => (bool) $makeAdmin]);
