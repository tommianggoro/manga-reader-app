<?php
/**
 * Manga Reader - Profile Page (Thin Controller)
 * User profile management: avatar, telegram, password
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

requireAuth();

$errorPassword = "";
$successPassword = "";
$errorPhoto = "";
$successPhoto = "";
$errorTelegram = "";
$successTelegram = "";

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

                // Nama file: user_{id}_{waktu}.{ext}
                $ext = $allowedTypes[$mimeType];
                $filename = "user_" . $userId . "_" . time() . "." . $ext;
                $destination = AVATAR_UPLOAD_DIR . "/" . $filename;

                // Hapus foto lama milik user ini
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

    // ==== Set Telegram Chat ID ====
    if ($action === "set_telegram") {
        $chatId = trim($_POST["telegram_chat_id"] ?? "");
        if ($chatId === "") {
            $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = NULL WHERE id = :id");
            $stmt->execute([":id" => $userId]);
            $_SESSION["telegram_chat_id"] = null;
            $successTelegram = "Telegram Chat ID dihapus. Notifikasi per-user dinonaktifkan.";
        } elseif (!preg_match('/^-?\d+$/', $chatId)) {
            $errorTelegram = "Chat ID harus berupa angka (contoh: 123456789 atau -1001234567890 untuk grup).";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = :chat_id WHERE id = :id");
            $stmt->execute([":chat_id" => $chatId, ":id" => $userId]);
            $_SESSION["telegram_chat_id"] = $chatId;

            // Reset notification baseline for existing favorites
            $stmt = $pdo->prepare("
                UPDATE user_manga_state ums
                JOIN (
                    SELECT manga_id, MAX(chapter_number) AS maxnum
                    FROM chapters
                    GROUP BY manga_id
                ) c ON c.manga_id = ums.manga_id
                SET ums.last_notified_chapter_number = c.maxnum
                WHERE ums.user_id = :uid
                  AND ums.is_favorite = 1
            ");
            $stmt->execute([":uid" => $userId]);
            $baselineUpdated = $stmt->rowCount();

            $msg = "Telegram Chat ID disimpan. Notifikasi per-user diaktifkan.";
            if ($baselineUpdated > 0) {
                $msg .= " Notifikasi akan dikirim untuk chapter baru mulai sekarang ({$baselineUpdated} manga favorit).";
            }
            $successTelegram = $msg;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil Saya - Manga Reader</title>

    <!-- Pre-paint theme script (no flash) -->
    <script>
    (function() {
        const savedTheme = localStorage.getItem('manga_theme') || 'dark';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    })();
    </script>

    <link rel="icon" href="favicon.ico" sizes="any">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts: Bitter (serif) + Inter (sans) -->
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Design Tokens -->
    <link href="assets/css/variables.css" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width: 520px;">

<?php
// Include composed content view
$errorPhoto = $errorPhoto;
$successPhoto = $successPhoto;
$errorTelegram = $errorTelegram;
$successTelegram = $successTelegram;
$errorPassword = $errorPassword;
$successPassword = $successPassword;
include __DIR__ . '/views/profile/content.php';
?>

</div>

<script>
    // Theme Switcher
    const themeToggleBtn = document.getElementById("themeToggle");
    const themeIcon = document.getElementById("themeIcon");

    function updateThemeUI(theme) {
        document.documentElement.setAttribute("data-bs-theme", theme);
        localStorage.setItem("manga_theme", theme);
        if (theme === "dark") {
            themeIcon.className = "bi bi-moon-stars-fill";
            themeToggleBtn.title = "Ganti ke Mode Terang";
        } else {
            themeIcon.className = "bi bi-sun-fill text-warning";
            themeToggleBtn.title = "Ganti ke Mode Gelap";
        }
    }
    updateThemeUI(localStorage.getItem("manga_theme") || "dark");
    themeToggleBtn.addEventListener("click", () => {
        const newTheme = document.documentElement.getAttribute("data-bs-theme") === "dark" ? "light" : "dark";
        updateThemeUI(newTheme);
    });

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