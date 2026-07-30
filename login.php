<?php
require_once "config.php";
session_start();

$ip = getClientIp();
$error = "";

$lockedUntil = checkLoginLock($pdo, $ip);

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$lockedUntil) {
    if (password_verify($_POST["password"] ?? "", ACCESS_PASSWORD_HASH)) {
        clearLoginAttempts($pdo, $ip);

        // Regenerate session ID supaya session lama (sebelum login) tidak bisa dipakai lagi.
        session_regenerate_id(true);
        $_SESSION["authenticated"] = true;

        header("Location: index.php");
        exit;
    } else {
        $attempts = recordFailedLogin($pdo, $ip);
        $remaining = MAX_LOGIN_ATTEMPTS - $attempts;

        if ($remaining <= 0) {
            $lockedUntil = checkLoginLock($pdo, $ip);
            $error = "Terlalu banyak percobaan salah. Coba lagi setelah " . LOGIN_LOCKOUT_MINUTES . " menit.";
        } else {
            $error = "Password salah. Sisa percobaan: $remaining.";
        }
    }
}

if ($lockedUntil) {
    $error = "Terlalu banyak percobaan salah. Coba lagi setelah "
        . date("H:i:s", strtotime($lockedUntil)) . ".";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Manga Reader</title>
    <style>
        body { font-family: sans-serif; background: #1a1a1a; color: #eee; display: flex; height: 100vh; align-items: center; justify-content: center; }
        form { background: #2a2a2a; padding: 2rem; border-radius: 8px; }
        input { display: block; width: 100%; padding: 0.5rem; margin: 0.5rem 0; box-sizing: border-box; }
        button { padding: 0.5rem 1.5rem; background: #4a90d9; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        .error { color: #ff6b6b; }
    </style>
</head>
<body>
    <form method="POST">
        <h2>Manga Reader Pribadi</h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <input type="password" name="password" placeholder="Password" required autofocus <?= $lockedUntil ? 'disabled' : '' ?>>
        <button type="submit" <?= $lockedUntil ? 'disabled' : '' ?>>Masuk</button>
    </form>
</body>
</html>
