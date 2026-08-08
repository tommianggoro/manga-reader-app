<?php
require_once "config.php";
session_start();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";
    $secretKey = $_POST["secret_key"] ?? "";

    if (empty(REGISTRATION_SECRET_KEY)) {
        $error = "Pendaftaran akun baru sedang dinonaktifkan. Atur REGISTRATION_SECRET_KEY di .env untuk mengaktifkan.";
    } elseif (!hash_equals(REGISTRATION_SECRET_KEY, $secretKey)) {
        $error = "Kunci pendaftaran salah.";
    } elseif (strlen($username) < 3) {
        $error = "Username minimal 3 karakter.";
    } elseif (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
        $error = "Username hanya boleh huruf, angka, underscore, dan titik.";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } elseif ($password !== $confirm) {
        $error = "Konfirmasi password tidak cocok.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u");
        $stmt->execute([":u" => $username]);
        if ($stmt->fetch()) {
            $error = "Username sudah dipakai, silakan pilih yang lain.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:u, :p)");
            $stmt->execute([":u" => $username, ":p" => password_hash($password, PASSWORD_DEFAULT)]);
            $success = "Akun berhasil dibuat! Silakan login.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Akun - Manga Reader</title>
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root, [data-bs-theme="dark"] {
            --bs-body-bg: #10131a; --bs-body-color: #eae7e0; --bs-primary: #f2a541;
            --bs-primary-rgb: 242, 165, 65; --bs-border-color: #242938; --bs-secondary-bg: #171b26;
        }
        body { font-family: 'Inter', system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .brand-font { font-family: 'Bitter', Georgia, serif; }
        .auth-card { width: 100%; max-width: 400px; background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); border-radius: 16px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
    <div class="auth-card">
        <h2 class="brand-font h4 text-center mb-1"><i class="bi bi-person-plus-fill text-primary me-1"></i> Daftar Akun</h2>
        <p class="text-secondary text-center small mb-4">Buat akun baru untuk koleksi manga pribadi</p>

        <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Username</label>
                <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Password</label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Konfirmasi Password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Kunci Pendaftaran</label>
                <input type="password" name="secret_key" class="form-control" required placeholder="Dari admin / .env REGISTRATION_SECRET_KEY">
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-semibold"><i class="bi bi-person-check me-1"></i> Daftar</button>
        </form>
        <?php endif; ?>

        <p class="text-center small text-secondary mt-3 mb-0">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </p>
    </div>
</body>
</html>