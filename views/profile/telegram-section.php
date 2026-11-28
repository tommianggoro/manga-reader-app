<?php
/**
 * Profile Telegram Section
 * Used by: profile.php
 * Variables: $errorTelegram, $successTelegram
 */
?>
<!-- Telegram Chat ID untuk Notifikasi Per-User -->
<div class="profile-card mb-3">
    <h2 class="h6 fw-bold mb-3"><i class="bi bi-telegram text-primary me-1"></i> Notifikasi Telegram Pribadi</h2>
    <p class="small text-secondary mb-3">
        Masukkan <b>Chat ID</b> Telegram Anda untuk menerima notifikasi chapter baru
        hanya untuk manga yang Anda favoritkan (❤️).
        <br>Dapatkan Chat ID via <a href="https://t.me/userinfobot" target="_blank" rel="noopener">@userinfobot</a>
        atau <a href="https://t.me/RawDataBot" target="_blank" rel="noopener">@RawDataBot</a>.
        <br>Format: <code>123456789</code> (personal) atau <code>-1001234567890</code> (grup/channel).
    </p>
    <?php if (!empty($errorTelegram)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($errorTelegram) ?></div><?php endif; ?>
    <?php if (!empty($successTelegram)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($successTelegram) ?></div><?php endif; ?>

    <form method="POST" class="d-flex flex-column gap-2">
        <input type="hidden" name="action" value="set_telegram">
        <div class="mb-2">
            <label class="form-label small fw-semibold">Telegram Chat ID</label>
            <input type="text" name="telegram_chat_id" class="form-control form-control-sm"
                value="<?= htmlspecialchars($_SESSION["telegram_chat_id"] ?? "") ?>"
                placeholder="Contoh: 123456789 atau -1001234567890">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm fw-semibold flex-grow-1">
                <i class="bi bi-save me-1"></i> Simpan
            </button>
            <button type="submit" name="telegram_chat_id" value="" class="btn btn-outline-danger btn-sm"
                onclick="return confirm('Hapus Chat ID dan nonaktifkan notifikasi?')">
                <i class="bi bi-trash me-1"></i> Hapus
            </button>
        </div>
    </form>
</div>