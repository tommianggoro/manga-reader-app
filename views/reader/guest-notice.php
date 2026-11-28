<?php
/**
 * Guest Notice Banner
 * Used by: reader.php
 */
$userId = $userId ?? null;
?>
<?php if (!$userId): ?>
<!-- Guest Notice Banner -->
<div class="guest-notice px-3">
    <i class="bi bi-info-circle text-primary"></i>
    <span>Progres bacamu disimpan di perangkat ini saja. <a href="javascript:void(0)" onclick="showLoginRequiredModal('Login supaya progres bacamu tersimpan ke akun.')">Login</a> supaya tersimpan ke akun & bisa dilanjut dari perangkat lain.</span>
</div>
<?php endif; ?>