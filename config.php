<?php
require_once __DIR__ . "/env_loader.php";
loadEnv(__DIR__ . "/.env");

// Kredensial database sekarang diambil dari file .env (lihat .env.example untuk template).
// Kalau .env tidak ditemukan, config akan jatuh ke nilai default di bawah ini.
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

// Password disimpan dalam bentuk HASH, bukan teks biasa, diambil dari .env.
// Cara ganti password:
//   1. Upload sementara generate_password_hash.php ke server
//   2. Buka di browser: yoursite.com/generate_password_hash.php?pw=passwordbarukamu
//   3. Salin hash yang muncul, tempel ke file .env sebagai ACCESS_PASSWORD_HASH
//   4. HAPUS generate_password_hash.php dari server
define("ACCESS_PASSWORD_HASH", env("ACCESS_PASSWORD_HASH", ""));

// Rate limiting: berapa kali percobaan salah sebelum dikunci sementara, dan berapa lama.
define("MAX_LOGIN_ATTEMPTS", (int) env("MAX_LOGIN_ATTEMPTS", 5));
define("LOGIN_LOCKOUT_MINUTES", (int) env("LOGIN_LOCKOUT_MINUTES", 15));

// Secret Key untuk otentikasi eksekusi Cronjob Web (misal: GitHub Actions / Webhook)
define("CRON_SECRET_KEY", env("CRON_SECRET_KEY", ""));

function requireAuth() {
    session_start();
    if (!isset($_SESSION["authenticated"]) || $_SESSION["authenticated"] !== true) {
        header("Location: login.php");
        exit;
    }
}

function getClientIp() {
    // Catatan: kalau hosting kamu di belakang reverse proxy/CDN (mis. Cloudflare),
    // REMOTE_ADDR mungkin perlu diganti dengan header X-Forwarded-For dari proxy tsb.
    return $_SERVER["REMOTE_ADDR"] ?? "unknown";
}

// Mengecek apakah IP ini sedang dikunci karena terlalu banyak percobaan gagal.
// Mengembalikan timestamp "locked_until" (string) kalau masih terkunci, atau null kalau tidak.
function checkLoginLock($pdo, $ip) {
    $stmt = $pdo->prepare("SELECT locked_until FROM login_attempts WHERE ip_address = :ip");
    $stmt->execute([":ip" => $ip]);
    $row = $stmt->fetch();

    if ($row && $row["locked_until"] && strtotime($row["locked_until"]) > time()) {
        return $row["locked_until"];
    }
    return null;
}

// Dipanggil setiap kali password yang dimasukkan salah.
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

// Dipanggil setelah login berhasil, supaya hitungan gagal sebelumnya direset.
function clearLoginAttempts($pdo, $ip) {
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
    $stmt->execute([":ip" => $ip]);
}