<?php
require_once "config.php";
requireAdmin();

$users = $pdo->query("SELECT id, username, is_admin, created_at FROM users ORDER BY is_admin DESC, username ASC")->fetchAll();
$totalAdmins = count(array_filter($users, fn($u) => (int) $u["is_admin"] === 1));
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Admin - Manga Reader</title>
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
        .user-row { background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); border-radius: 12px; padding: 0.85rem 1rem; }
        .user-avatar-sm { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: var(--bs-tertiary-bg); }
        .you-badge { font-size: 0.68rem; }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 720px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <a class="d-inline-flex align-items-center gap-1 text-decoration-none fw-medium" href="index.php">
            <i class="bi bi-arrow-left"></i> Kembali ke koleksi
        </a>
        <button type="button" class="theme-toggle-btn" style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid var(--bs-border-color);background:var(--bs-secondary-bg);color:var(--bs-body-color);" onclick="logoutUser('index.php')" title="Keluar">
            <i class="bi bi-box-arrow-right"></i>
        </button>
    </div>

    <h1 class="brand-font h4 mb-1"><i class="bi bi-people-fill text-primary me-1"></i> Kelola Admin</h1>
    <p class="text-secondary small mb-4">Jadikan user lain admin, atau cabut status admin. Minimal harus ada 1 admin aktif.</p>

    <div id="alertBox"></div>

    <div class="d-flex flex-column gap-2" id="userList">
        <?php foreach ($users as $u): ?>
            <div class="user-row d-flex align-items-center gap-3" data-user-id="<?= (int) $u['id'] ?>">
                <img src="https://api.dicebear.com/7.x/identicon/svg?seed=<?= urlencode($u['username']) ?>" class="user-avatar-sm" alt="">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate">
                        <?= htmlspecialchars($u['username']) ?>
                        <?php if ((int) $u['id'] === (int) currentUserId()): ?>
                            <span class="badge text-bg-secondary you-badge ms-1">Kamu</span>
                        <?php endif; ?>
                    </div>
                    <div class="small text-secondary">Bergabung <?= htmlspecialchars(date("j M Y", strtotime($u['created_at']))) ?></div>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input admin-toggle" type="checkbox" role="switch"
                           style="width: 2.5em; height: 1.4em;"
                           data-user-id="<?= (int) $u['id'] ?>"
                           <?= $u['is_admin'] ? 'checked' : '' ?>>
                    <label class="form-check-label small ms-1">Admin</label>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    const alertBox = document.getElementById("alertBox");

    function showAlert(type, message) {
        alertBox.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show py-2 small" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
    }

    document.querySelectorAll(".admin-toggle").forEach(toggle => {
        toggle.addEventListener("change", async (e) => {
            const userId = toggle.dataset.userId;
            const wantAdmin = toggle.checked ? 1 : 0;
            toggle.disabled = true;

            try {
                const res = await fetch("toggle_admin.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `user_id=${encodeURIComponent(userId)}&is_admin=${wantAdmin}`,
                });
                const data = await res.json();
                if (!data.success) {
                    toggle.checked = !toggle.checked; // rollback tampilan
                    showAlert("danger", data.error || "Gagal mengubah status admin.");
                    return;
                }
                showAlert("success", wantAdmin
                    ? "User berhasil dijadikan admin."
                    : "Status admin user berhasil dicabut.");
            } catch (err) {
                toggle.checked = !toggle.checked;
                showAlert("danger", "Error: " + err.message);
            } finally {
                toggle.disabled = false;
            }
        });
    });

    // Logout via AJAX -- halaman ini wajib admin, jadi setelah logout diarahkan
    // ke index.php (bukan reload di tempat, krn reload akan langsung mental ke
    // login.php lagi oleh requireAdmin()).
    async function logoutUser(fallbackUrl) {
        try {
            await fetch('logout_api.php', { method: 'POST' });
        } catch (err) { /* tetap lanjut redirect walau request gagal */ }
        window.location.href = fallbackUrl || 'index.php';
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
