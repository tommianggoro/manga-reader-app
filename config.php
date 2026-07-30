<?php
// Isi sesuai kredensial database dari panel hosting kamu (cPanel/hPanel/dll)
$DB_HOST = "localhost";
$DB_NAME = "manga_reader";
$DB_USER = "root";
$DB_PASS = "";

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

// Password sederhana untuk lindungi akses (karena hosting bisa diakses publik)
// Ganti dengan password kamu sendiri
define("ACCESS_PASSWORD", "asikinaja");

function requireAuth() {
    session_start();
    if (!isset($_SESSION["authenticated"]) || $_SESSION["authenticated"] !== true) {
        header("Location: login.php");
        exit;
    }
}
