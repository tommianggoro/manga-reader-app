<?php
/**
 * Profile Avatar Section
 * Used by: profile.php
 * Variables: $errorPhoto, $successPhoto
 */
?>
<!-- Foto Profil -->
<div class="profile-card mb-3">
    <h2 class="h6 fw-bold mb-3">Foto Profil</h2>
    <?php if (!empty($errorPhoto)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($errorPhoto) ?></div><?php endif; ?>
    <?php if (!empty($successPhoto)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($successPhoto) ?></div><?php endif; ?>

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