<?php
/**
 * Database Connection Factory
 * Provides PDO connection for main process and child processes
 */

require_once __DIR__ . "/constants.php";

/**
 * Main PDO connection (created once per request/process).
 * Global variable for backward compatibility with existing code.
 */
$DB_HOST = DB_HOST;
$DB_NAME = DB_NAME;
$DB_USER = DB_USER;
$DB_PASS = DB_PASS;

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

/**
 * Buat koneksi PDO baru (dipakai utk parallel processing di child process).
 * Setiap child process HARUS punya koneksi sendiri sendiri.
 */
function createPdoConnection(): PDO
{
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}