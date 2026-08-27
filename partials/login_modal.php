<?php
/**
 * Partial: modal "harus login" -- ditampilkan lewat JS (bukan otomatis saat load)
 * ketika GUEST mencoba aksi yang butuh login (mis. klik tombol bookmark/favorit).
 * Include file ini di halaman publik (index.php, manga.php), lalu panggil
 * showLoginRequiredModal() dari JS di halaman tsb.
 *
 * $redirectAfterLogin (opsional, string) -- diisi oleh halaman pemanggil kalau
 * mau login.php redirect balik ke halaman ini setelah berhasil login.
 */
$redirectAfterLogin = $redirectAfterLogin ?? basename($_SERVER["PHP_SELF"]);
?>
<div class="modal fade" id="loginRequiredModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center pt-0 pb-4">
                <div class="mb-3" style="font-size: 2.2rem; color: var(--bs-primary);"><i class="bi bi-bookmark-star-fill"></i></div>
                <h5 class="brand-font mb-2">Login Dulu, Yuk</h5>
                <p class="text-secondary small mb-4">Untuk menandai (bookmark) manga & menyimpan progres bacamu ke akun, silakan masuk atau daftar dulu.</p>
                <div class="d-flex flex-column gap-2">
                    <a href="login.php?redirect=<?= urlencode($redirectAfterLogin) ?>" class="btn btn-primary fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                    </a>
                    <a href="register.php" class="btn btn-outline-secondary">
                        <i class="bi bi-person-plus me-1"></i> Daftar Akun Baru
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function showLoginRequiredModal() {
        const modalEl = document.getElementById('loginRequiredModal');
        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
</script>
