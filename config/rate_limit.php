<?php
/**
 * Rate Limiting Functions
 * Login and Registration rate limiting
 */

require_once __DIR__ . "/database.php";
require_once __DIR__ . "/constants.php";

// ============================================================================
// LOGIN RATE LIMITING
// ============================================================================

function checkLoginLock(PDO $pdo, string $ip): ?string
{
    $stmt = $pdo->prepare("SELECT locked_until FROM login_attempts WHERE ip_address = :ip");
    $stmt->execute([":ip" => $ip]);
    $row = $stmt->fetch();

    if ($row && $row["locked_until"] && strtotime($row["locked_until"]) > time()) {
        return $row["locked_until"];
    }
    return null;
}

function recordFailedLogin(PDO $pdo, string $ip): int
{
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

function clearLoginAttempts(PDO $pdo, string $ip): void
{
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
    $stmt->execute([":ip" => $ip]);
}

// ============================================================================
// REGISTRATION RATE LIMITING
// ============================================================================

/** true kalau IP ini masih boleh mendaftar, false kalau sudah kena limit. */
function checkRegistrationAllowed(PDO $pdo, string $ip): bool
{
    $stmt = $pdo->prepare("SELECT attempts, window_started_at FROM registration_attempts WHERE ip_address = :ip");
    $stmt->execute([":ip" => $ip]);
    $row = $stmt->fetch();

    if (!$row) return true;

    $windowExpired = (time() - strtotime($row["window_started_at"])) > (REGISTRATION_WINDOW_MINUTES * 60);
    if ($windowExpired) return true;

    return (int) $row["attempts"] < MAX_REGISTRATIONS_PER_WINDOW;
}

/** Catat 1 percobaan registrasi (sukses maupun gagal validasi) dari IP ini. */
function recordRegistrationAttempt(PDO $pdo, string $ip): void
{
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