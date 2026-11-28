<?php
/**
 * Authentication & Session Helpers
 */

require_once __DIR__ . "/database.php";

/**
 * Dipakai di halaman yg WAJIB login (mis. profile.php, save_progress.php).
 * Redirect ke login.php kalau belum login.
 */
function requireAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
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
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
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
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
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