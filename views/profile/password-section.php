<?php
/**
 * Profile Password Section
 * Used by: profile.php
 * Variables: $errorPassword, $successPassword
 */
?>
<!-- Ganti Password -->
<div class="profile-card">
    <h2 class="h6 fw-bold mb-3">Ganti Password</h2>
    <?php if (!empty($errorPassword)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($errorPassword) ?></div><?php endif; ?>
    <?php if (!empty($successPassword)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($successPassword) ?></div><?php endif; ?>

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