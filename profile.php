<?php
require_once "config.php";
requireAuth();

$errorPassword = "";
$successPassword = "";
$errorPhoto = "";
$successPhoto = "";

$userId = currentUserId();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // ==== Ganti Password ====
    if ($action === "change_password") {
        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute([":id" => $userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user["password_hash"])) {
            $errorPassword = "Password saat ini salah.";
        } elseif (strlen($newPassword) < 6) {
            $errorPassword = "Password baru minimal 6 karakter.";
        } elseif ($newPassword !== $confirmPassword) {
            $errorPassword = "Konfirmasi password baru tidak cocok.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmt->execute([":hash" => password_hash($newPassword, PASSWORD_DEFAULT), ":id" => $userId]);
            $successPassword = "Password berhasil diubah.";
        }
    }

    // ==== Upload Foto Profil ====
    if ($action === "upload_photo" && isset($_FILES["photo"])) {
        $file = $_FILES["photo"];
        $allowedTypes = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];

        if ($file["error"] !== UPLOAD_ERR_OK) {
            $errorPhoto = "Gagal mengunggah foto. Kode error: " . $file["error"];
        } elseif ($file["size"] > AVATAR_MAX_BYTES) {
            $errorPhoto = "Ukuran foto maksimal 2MB.";
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file["tmp_name"]);
            finfo_close($finfo);

            if (!isset($allowedTypes[$mimeType])) {
                $errorPhoto = "Format foto harus JPG, PNG, atau WEBP.";
            } else {
                if (!is_dir(AVATAR_UPLOAD_DIR)) {
                    mkdir(AVATAR_UPLOAD_DIR, 0755, true);
                }

                // Nama file: user_{id}_{waktu}.{ext} -- supaya browser tidak nyangkut
                // cache foto lama, dan tidak ada bentrok antar-user.
                $ext = $allowedTypes[$mimeType];
                $filename = "user_" . $userId . "_" . time() . "." . $ext;
                $destination = AVATAR_UPLOAD_DIR . "/" . $filename;

                // Hapus foto lama milik user ini (kalau ada) supaya folder tidak menumpuk.
                $stmt = $pdo->prepare("SELECT profile_photo_url FROM users WHERE id = :id");
                $stmt->execute([":id" => $userId]);
                $oldPhoto = $stmt->fetchColumn();
                if ($oldPhoto && file_exists(AVATAR_UPLOAD_DIR . "/" . $oldPhoto)) {
                    @unlink(AVATAR_UPLOAD_DIR . "/" . $oldPhoto);
                }

                if (move_uploaded_file($file["tmp_name"], $destination)) {
                    $stmt = $pdo->prepare("UPDATE users SET profile_photo_url = :photo WHERE id = :id");
                    $stmt->execute([":photo" => $filename, ":id" => $userId]);
                    $_SESSION["profile_photo_url"] = $filename;
                    $successPhoto = "Foto profil berhasil diperbarui.";
                } else {
                    $errorPhoto = "Gagal menyimpan foto ke server.";
                }
            }
        }
    }

    // ==== Hapus Foto Profil (kembali ke avatar default) ====
    if ($action === "remove_photo") {
        $stmt = $pdo->prepare("SELECT profile_photo_url FROM users WHERE id = :id");
        $stmt->execute([":id" => $userId]);
        $oldPhoto = $stmt->fetchColumn();
        if ($oldPhoto && file_exists(AVATAR_UPLOAD_DIR . "/" . $oldPhoto)) {
            @unlink(AVATAR_UPLOAD_DIR . "/" . $oldPhoto);
        }
        $stmt = $pdo->prepare("UPDATE users SET profile_photo_url = NULL WHERE id = :id");
        $stmt->execute([":id" => $userId]);
        $_SESSION["profile_photo_url"] = null;
        $successPhoto = "Foto profil dikembalikan ke default.";
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil Saya - Manga Reader</title>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('manga_theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link rel="icon" href="favicon.ico" sizes="any">
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root, [data-bs-theme="dark"] {
            --bs-body-bg: #10131a; --bs-body-color: #eae7e0; --bs-primary: #f2a541;
            --bs-primary-rgb: 242, 165, 65; --bs-border-color: #242938; --bs-secondary-bg: #171b26; --bs-tertiary-bg: #202636;
        }
        body { font-family: 'Inter', system-ui, sans-serif; }
        .brand-font { font-family: 'Bitter', Georgia, serif; }
        .profile-card { background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); border-radius: 16px; padding: 1.5rem; }
        .avatar-preview {
            width: 96px; height: 96px; border-radius: 50%; object-fit: cover;
            border: 2px solid var(--bs-border-color); background: var(--bs-tertiary-bg);
        }
        .theme-toggle-btn {
            width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--bs-border-color); background: var(--bs-secondary-bg); color: var(--bs-body-color);
        }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 520px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <a class="d-inline-flex align-items-center gap-1 text-decoration-none fw-medium" href="index.php">
            <i class="bi bi-arrow-left"></i> Kembali ke koleksi
        </a>
        <div class="d-flex align-items-center gap-2">
            <?php if (isAdmin()): ?>
                <a href="manage_admin.php" class="theme-toggle-btn" title="Kelola Admin"><i class="bi bi-people-fill"></i></a>
            <?php endif; ?>
            <button type="button" class="theme-toggle-btn" onclick="logoutUser('index.php')" title="Keluar"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <h1 class="brand-font h4 mb-4"><i class="bi bi-person-circle text-primary me-1"></i> Profil Saya</h1>

    <!-- Foto Profil -->
    <div class="profile-card mb-3">
        <h2 class="h6 fw-bold mb-3">Foto Profil</h2>
        <?php if ($errorPhoto): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($errorPhoto) ?></div><?php endif; ?>
        <?php if ($successPhoto): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($successPhoto) ?></div><?php endif; ?>

        <div class="d-flex align-items-center gap-3 mb-3">
            <img src="<?= htmlspecialchars(currentUserAvatarUrl()) ?>?t=<?= time() ?>" class="avatar-preview" alt="Foto profil" id="avatarPreview">
            <div class="flex-grow-1">
                <div class="fw-semibold"><?= htmlspecialchars(currentUsername()) ?></div>
                <div class="small text-secondary">JPG/PNG/WEBP, maks 2MB</div>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-2">
            <input type="hidden" name="action" value="upload_photo">
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                <i class="bi bi-upload me-1"></i> Unggah Foto Baru
            </button>
        </form>
        <?php if (currentUserPhoto()): ?>
        <form method="POST" class="mt-2">
            <input type="hidden" name="action" value="remove_photo">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Kembalikan ke Foto Default
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Ganti Password -->
    <div class="profile-card">
        <h2 class="h6 fw-bold mb-3">Ganti Password</h2>
        <?php if ($errorPassword): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($errorPassword) ?></div><?php endif; ?>
        <?php if ($successPassword): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($successPassword) ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="mb-2">
                <label class="form-label small fw-semibold">Password Saat Ini</label>
                <input type="password" name="current_password" class="form-control form-control-sm" required>
            </div>
            <div class="mb-2">
                <label class="form-label small fw-semibold">Password Baru</label>
                <input type="password" name="new_password" class="form-control form-control-sm" required minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Konfirmasi Password Baru</label>
                <input type="password" name="confirm_password" class="form-control form-control-sm" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary btn-sm fw-semibold w-100">
                <i class="bi bi-key-fill me-1"></i> Ubah Password
            </button>
        </form>
    </div>
</div>

<script>
    // Logout via AJAX -- profile.php wajib login, jadi setelah logout diarahkan
    // ke index.php (bukan reload di tempat, krn halaman ini akan langsung
    // redirect ke login.php lagi kalau di-reload sbg guest).
    async function logoutUser(fallbackUrl) {
        try {
            await fetch('logout_api.php', { method: 'POST' });
        } catch (err) { /* tetap lanjut redirect walau request gagal */ }
        window.location.href = fallbackUrl || 'index.php';
    }
</script>
</body>
</html>
