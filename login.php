<?php
require_once "config.php";
session_start();

$ip = getClientIp();
$error = "";

$lockedUntil = checkLoginLock($pdo, $ip);

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$lockedUntil) {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :u");
    $stmt->execute([":u" => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password_hash"])) {
        clearLoginAttempts($pdo, $ip);

        // Regenerate session ID supaya session lama (sebelum login) tidak bisa dipakai lagi.
        session_regenerate_id(true);
        $_SESSION["user_id"] = (int) $user["id"];
        $_SESSION["username"] = $user["username"];

        header("Location: index.php");
        exit;
    } else {
        $attempts = recordFailedLogin($pdo, $ip);
        $remaining = MAX_LOGIN_ATTEMPTS - $attempts;

        if ($remaining <= 0) {
            $lockedUntil = checkLoginLock($pdo, $ip);
            $error = "Terlalu banyak percobaan salah. Coba lagi setelah " . LOGIN_LOCKOUT_MINUTES . " menit.";
        } else {
            $error = "Username atau password salah. Sisa percobaan: $remaining.";
        }
    }
}

if ($lockedUntil) {
    $error = "Terlalu banyak percobaan salah. Coba lagi setelah "
        . date("H:i:s", strtotime($lockedUntil)) . ".";
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Manga Reader</title>
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="manifest" href="https://tommianggoro.github.io/manga-reader-app/manifest.json">
    <meta name="theme-color" content="#10131a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Manga Reader">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root, [data-bs-theme="dark"] {
            --bs-body-bg: #10131a;
            --bs-body-color: #eae7e0;
            --bs-primary: #f2a541;
            --bs-primary-rgb: 242, 165, 65;
            --bs-border-color: #242938;
            --bs-secondary-bg: #171b26;
            --bs-tertiary-bg: #202636;
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .brand-font { font-family: 'Bitter', Georgia, serif; }
        .auth-card {
            width: 100%; max-width: 380px; background: var(--bs-secondary-bg);
            border: 1px solid var(--bs-border-color); border-radius: 16px;
            padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .auth-icon {
            width: 60px; height: 60px; border-radius: 50%; background: var(--bs-tertiary-bg);
            color: var(--bs-primary); display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; margin: 0 auto 1rem;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-icon"><i class="bi bi-book-half"></i></div>
        <h2 class="brand-font h4 text-center mb-1">Manga Reader</h2>
        <p class="text-secondary text-center small mb-4">Masuk ke koleksi pribadi Anda</p>

        <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Username</label>
                <input type="text" name="username" class="form-control" required autofocus <?= $lockedUntil ? 'disabled' : '' ?>>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Password</label>
                <input type="password" name="password" class="form-control" required <?= $lockedUntil ? 'disabled' : '' ?>>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-semibold" <?= $lockedUntil ? 'disabled' : '' ?>>
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
            </button>
        </form>

        <p class="text-center small text-secondary mt-3 mb-0">
            Belum punya akun? <a href="register.php">Daftar di sini</a>
        </p>
    </div>
</body>
</html>