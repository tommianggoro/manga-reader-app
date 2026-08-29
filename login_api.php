<?php
/**
 * Endpoint login lewat AJAX (dipanggil dari modal login di partials/login_modal.php)
 * supaya login TIDAK berpindah halaman -- user tetap di halaman yang sama
 * (index.php/manga.php/reader.php), tinggal reload utk menampilkan state login.
 *
 * Logika rate-limit/lockout SAMA PERSIS dengan login.php (dipakai bersama lewat
 * config.php: checkLoginLock/recordFailedLogin/clearLoginAttempts).
 */
require_once "config.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

$ip = getClientIp();
$lockedUntil = checkLoginLock($pdo, $ip);

if ($lockedUntil) {
    echo json_encode([
        "success" => false,
        "error" => "Terlalu banyak percobaan salah. Coba lagi setelah " . date("H:i:s", strtotime($lockedUntil)) . ".",
    ]);
    exit;
}

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($username === "" || $password === "") {
    echo json_encode(["success" => false, "error" => "Username dan password wajib diisi."]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, password_hash, is_admin, profile_photo_url FROM users WHERE username = :u");
$stmt->execute([":u" => $username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user["password_hash"])) {
    clearLoginAttempts($pdo, $ip);
    session_regenerate_id(true);
    $_SESSION["user_id"] = (int) $user["id"];
    $_SESSION["username"] = $user["username"];
    $_SESSION["is_admin"] = (bool) $user["is_admin"];
    $_SESSION["profile_photo_url"] = $user["profile_photo_url"];

    echo json_encode(["success" => true]);
    exit;
}

$attempts = recordFailedLogin($pdo, $ip);
$remaining = MAX_LOGIN_ATTEMPTS - $attempts;

if ($remaining <= 0) {
    $lockedUntil = checkLoginLock($pdo, $ip);
    echo json_encode([
        "success" => false,
        "error" => "Terlalu banyak percobaan salah. Coba lagi setelah " . date("H:i:s", strtotime($lockedUntil)) . ".",
    ]);
} else {
    echo json_encode(["success" => false, "error" => "Username atau password salah. Sisa percobaan: $remaining."]);
}
