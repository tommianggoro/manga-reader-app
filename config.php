<?php
require_once __DIR__ . "/env_loader.php";
loadEnv(__DIR__ . "/.env");

$DB_HOST = env("DB_HOST", "localhost");
$DB_NAME = env("DB_NAME", "manga_reader");
$DB_USER = env("DB_USER", "root");
$DB_PASS = env("DB_PASS", "");

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

define("MAX_LOGIN_ATTEMPTS", (int) env("MAX_LOGIN_ATTEMPTS", 5));
define("LOGIN_LOCKOUT_MINUTES", (int) env("LOGIN_LOCKOUT_MINUTES", 15));
define("CRON_SECRET_KEY", env("CRON_SECRET_KEY", ""));

// Batas registrasi per-IP (anti abuse bot/cron/curl -- registrasi publik,
// tidak lagi pakai secret key). Bisa disetel lewat .env kalau perlu.
define("MAX_REGISTRATIONS_PER_WINDOW", (int) env("MAX_REGISTRATIONS_PER_WINDOW", 3));
define("REGISTRATION_WINDOW_MINUTES", (int) env("REGISTRATION_WINDOW_MINUTES", 60));

// Folder & batas ukuran utk upload foto profil.
define("AVATAR_UPLOAD_DIR", __DIR__ . "/assets/avatars");
define("AVATAR_MAX_BYTES", 2 * 1024 * 1024); // 2MB

// ============================================================================
// SESSION / AUTH
// ============================================================================

/**
 * Dipakai di halaman yg WAJIB login (mis. profile.php, save_progress.php).
 * Redirect ke login.php kalau belum login.
 */
function requireAuth() {
    session_start();
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Dipakai di halaman yg WAJIB login DAN admin (mis. add_manga.php,
 * delete_manga.php, crawl.php, import.php, export.php, dst).
 */
function requireAdmin() {
    session_start();
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
    if (empty($_SESSION["is_admin"])) {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/plain; charset=utf-8");
        die("Akses ditolak: fitur ini hanya untuk admin.");
    }
}

/**
 * Dipakai di halaman PUBLIK (index.php, manga.php, reader.php). TIDAK
 * memaksa login -- cuma menyalakan session supaya kita tahu status login
 * user kalau ada (dipakai utk personalisasi: bookmark, progress baca, dll).
 */
function optionalAuth() {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION["user_id"]);
}

function isAdmin(): bool {
    return isLoggedIn() && !empty($_SESSION["is_admin"]);
}

// ID user yang sedang login (null kalau guest). Panggil setelah optionalAuth()/requireAuth().
function currentUserId() {
    return $_SESSION["user_id"] ?? null;
}

function currentUsername() {
    return $_SESSION["username"] ?? null;
}

function currentUserPhoto() {
    return $_SESSION["profile_photo_url"] ?? null;
}

/** URL foto profil siap-pakai (fallback ke avatar generik kalau belum upload). */
function currentUserAvatarUrl() {
    $photo = currentUserPhoto();
    if ($photo) return "assets/avatars/" . $photo;
    return "https://api.dicebear.com/7.x/identicon/svg?seed=" . urlencode(currentUsername() ?? "guest");
}

function getClientIp() {
    // Kalau hosting kamu di belakang reverse proxy/CDN (mis. Cloudflare),
    // REMOTE_ADDR mungkin perlu diganti dengan header X-Forwarded-For dari proxy tsb.
    return $_SERVER["REMOTE_ADDR"] ?? "unknown";
}

// ============================================================================
// LOGIN RATE LIMITING (sudah ada sebelumnya, tidak berubah)
// ============================================================================

function checkLoginLock($pdo, $ip) {
    $stmt = $pdo->prepare("SELECT locked_until FROM login_attempts WHERE ip_address = :ip");
    $stmt->execute([":ip" => $ip]);
    $row = $stmt->fetch();

    if ($row && $row["locked_until"] && strtotime($row["locked_until"]) > time()) {
        return $row["locked_until"];
    }
    return null;
}

function recordFailedLogin($pdo, $ip) {
    $stmt = $pdo->prepare("SELECT attempts FROM login_attempts WHERE ip_address = :ip");
    $stmt->execute([":ip" => $ip]);
    $row = $stmt->fetch();

    $attempts = $row ? ((int) $row["attempts"] + 1) : 1;
    $lockedUntil = null;
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $lockedUntil = date("Y-m-d H:i:s", time() + LOGIN_LOCKOUT_MINUTES * 60);
    }

    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (ip_address, attempts, locked_until)
        VALUES (:ip, :attempts, :locked_until)
        ON DUPLICATE KEY UPDATE attempts = :attempts, locked_until = :locked_until
    ");
    $stmt->execute([
        ":ip" => $ip,
        ":attempts" => $attempts,
        ":locked_until" => $lockedUntil,
    ]);

    return $attempts;
}

function clearLoginAttempts($pdo, $ip) {
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
    $stmt->execute([":ip" => $ip]);
}

// ============================================================================
// REGISTRATION RATE LIMITING (baru -- registrasi publik tanpa secret key,
// jadi butuh proteksi sendiri thd abuse bot/cron/curl)
// ============================================================================

/** true kalau IP ini masih boleh mendaftar, false kalau sudah kena limit. */
function checkRegistrationAllowed(PDO $pdo, string $ip): bool {
    $stmt = $pdo->prepare("SELECT attempts, window_started_at FROM registration_attempts WHERE ip_address = :ip");
    $stmt->execute([":ip" => $ip]);
    $row = $stmt->fetch();

    if (!$row) return true;

    $windowExpired = (time() - strtotime($row["window_started_at"])) > (REGISTRATION_WINDOW_MINUTES * 60);
    if ($windowExpired) return true;

    return (int) $row["attempts"] < MAX_REGISTRATIONS_PER_WINDOW;
}

/** Catat 1 percobaan registrasi (sukses maupun gagal validasi) dari IP ini. */
function recordRegistrationAttempt(PDO $pdo, string $ip): void {
    $stmt = $pdo->prepare("SELECT attempts, window_started_at FROM registration_attempts WHERE ip_address = :ip");
    $stmt->execute([":ip" => $ip]);
    $row = $stmt->fetch();

    $windowExpired = $row && (time() - strtotime($row["window_started_at"])) > (REGISTRATION_WINDOW_MINUTES * 60);

    if (!$row || $windowExpired) {
        $stmt = $pdo->prepare("
            INSERT INTO registration_attempts (ip_address, attempts, window_started_at)
            VALUES (:ip, 1, NOW())
            ON DUPLICATE KEY UPDATE attempts = 1, window_started_at = NOW()
        ");
        $stmt->execute([":ip" => $ip]);
        return;
    }

    $stmt = $pdo->prepare("UPDATE registration_attempts SET attempts = attempts + 1 WHERE ip_address = :ip");
    $stmt->execute([":ip" => $ip]);
}

function formatChapterNumber($num) {
    if ($num === null || $num === "") return "";
    $num = (float) $num;
    if ($num == (int) $num) {
        return (string) (int) $num;
    }
    return rtrim(rtrim(number_format($num, 2, ".", ""), "0"), ".");
}
