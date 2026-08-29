<?php
require_once "config.php";
session_start();

$error = "";
$success = "";
$ip = getClientIp();

// Captcha matematika sederhana (tanpa dependensi eksternal). Dibuat ulang
// tiap kali form di-render (GET, atau POST yang gagal) supaya tidak bisa dipakai berulang.
function newCaptcha() {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION["captcha_answer"] = $a + $b;
    $_SESSION["register_form_time"] = time();
    return [$a, $b];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";
    $honeypot = trim($_POST["website"] ?? ""); // field jebakan, harus KOSONG kalau manusia
    $captchaInput = trim($_POST["captcha"] ?? "");

    if (!checkRegistrationAllowed($pdo, $ip)) {
        $error = "Terlalu banyak percobaan pendaftaran dari alamat ini. Coba lagi dalam beberapa saat.";
    } elseif ($honeypot !== "") {
        // Diam-diam anggap sukses palsu utk bot (jangan kasih tahu ini honeypot).
        recordRegistrationAttempt($pdo, $ip);
        $error = "Pendaftaran gagal. Silakan coba lagi.";
    } elseif (!isset($_SESSION["register_form_time"]) || (time() - $_SESSION["register_form_time"]) < 3) {
        // Form diisi & dikirim < 3 detik -> ciri khas bot/script otomatis.
        recordRegistrationAttempt($pdo, $ip);
        $error = "Pengisian form terlalu cepat, sepertinya otomatis. Silakan coba lagi.";
    } elseif (!isset($_SESSION["captcha_answer"]) || $captchaInput === "" || (int) $captchaInput !== (int) $_SESSION["captcha_answer"]) {
        recordRegistrationAttempt($pdo, $ip);
        $error = "Jawaban verifikasi salah, silakan coba lagi.";
    } elseif (strlen($username) < 3) {
        $error = "Username minimal 3 karakter.";
    } elseif (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
        $error = "Username hanya boleh huruf, angka, underscore, dan titik.";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } elseif ($password !== $confirm) {
        $error = "Konfirmasi password tidak cocok.";
    } else {
        recordRegistrationAttempt($pdo, $ip);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u");
        $stmt->execute([":u" => $username]);
        if ($stmt->fetch()) {
            $error = "Username sudah dipakai, silakan pilih yang lain.";
        } else {
            // is_admin SELALU 0 utk akun baru lewat form publik ini. Jadikan admin
            // hanya lewat akun admin lain via manage_admin.php.
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, is_admin) VALUES (:u, :p, 0)");
            $stmt->execute([":u" => $username, ":p" => password_hash($password, PASSWORD_DEFAULT)]);
            $success = "Akun berhasil dibuat! Silakan login.";
        }
    }
}

[$captchaA, $captchaB] = newCaptcha();
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Akun - Manga Reader</title>
    <link rel="icon" href="favicon.ico" sizes="any">
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
        /* Honeypot: disembunyikan dari manusia lewat CSS (bukan hidden/display:none murni,
           supaya bot sederhana yg cuma skip field type=hidden tetap kejebak). */
        .hp-field { position: absolute; left: -9999px; top: -9999px; opacity: 0; height: 0; overflow: hidden; }
    </style>
</head>
<body>
    <div class="auth-card">
        <h2 class="brand-font h4 text-center mb-1"><i class="bi bi-person-plus-fill text-primary me-1"></i> Daftar Akun</h2>
        <p class="text-secondary text-center small mb-4">Buat akun baru untuk menandai (bookmark) & menyimpan progres baca</p>

        <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" autocomplete="off">
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

            <!-- Honeypot: JANGAN dihapus, JANGAN diisi manusia. Nama field sengaja
                 dibuat generik ("website") krn nama itu sering diisi otomatis oleh bot form-filler. -->
            <div class="hp-field" aria-hidden="true">
                <label>Website (biarkan kosong)</label>
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Verifikasi: berapa <?= $captchaA ?> + <?= $captchaB ?> ?</label>
                <input type="text" inputmode="numeric" name="captcha" class="form-control" required placeholder="Jawaban">
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-semibold"><i class="bi bi-person-check me-1"></i> Daftar</button>
        </form>
        <?php endif; ?>

        <p class="text-center small text-secondary mt-3 mb-0">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </p>
        <p class="text-center small mt-1 mb-0">
            <a href="index.php" class="text-secondary"><i class="bi bi-arrow-left me-1"></i>Jelajahi dulu sebagai tamu</a>
        </p>
    </div>
</body>
</html>
