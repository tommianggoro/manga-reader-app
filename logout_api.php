<?php
/**
 * Logout via AJAX. Menggantikan logout.php (yang redirect ke login.php) untuk
 * dipanggil dari halaman manapun -- setelah sukses, JS pemanggil cukup reload
 * halaman yang sama (guest bisa tetap baca) atau redirect ke index.php kalau
 * halaman saat ini butuh login (mis. profile.php).
 * logout.php (redirect langsung) tetap ada sbg fallback.
 */
require_once "config.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

$_SESSION = [];
session_destroy();

echo json_encode(["success" => true]);
