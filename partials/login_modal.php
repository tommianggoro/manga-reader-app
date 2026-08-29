<?php
/**
 * Partial: modal LOGIN INLINE (AJAX) -- dipakai di semua halaman publik
 * (index.php, manga.php, reader.php) supaya user bisa login TANPA pindah
 * halaman/URL. Setelah sukses, modal ditutup lalu halaman yang sama
 * di-reload (BUKAN redirect ke login.php) supaya status login (bookmark,
 * resume, avatar, dst) ter-render ulang dari server.
 *
 * Cara pakai di halaman pemanggil:
 *   include __DIR__ . "/partials/login_modal.php";
 *   ...lalu panggil showLoginRequiredModal('pesan opsional') dari JS
 *   (mis. saat guest klik tombol bookmark, atau klik ikon "Login" di header).
 *
 * File ini JUGA menyediakan logoutUser(fallbackUrl) -- dipakai tombol logout
 * di halaman publik supaya logout juga tidak pindah ke login.php:
 *   - Tanpa argumen -> reload halaman yang sama (utk index.php/manga.php/reader.php,
 *     yang tetap bisa diakses sbg guest).
 *   - Dengan argumen (mis. 'index.php') -> dipakai halaman yang WAJIB login
 *     (profile.php/manage_admin.php), krn reload di tempat akan langsung
 *     mental ke login.php lagi oleh requireAuth()/requireAdmin().
 */
?>
<div class="modal fade" id="loginRequiredModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0 pb-4">
                <div class="text-center mb-3">
                    <div class="mb-2" style="font-size: 2.2rem; color: var(--bs-primary);"><i class="bi bi-bookmark-star-fill"></i></div>
                    <h5 class="brand-font mb-1">Masuk ke Akun</h5>
                    <p class="text-secondary small mb-0" id="loginModalMessage">Untuk menandai (bookmark) manga & menyimpan progres bacamu ke akun.</p>
                </div>

                <div id="loginModalError" class="alert alert-danger py-2 small d-none"></div>

                <form id="loginModalForm" autocomplete="off">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Username</label>
                        <input type="text" name="username" class="form-control" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-semibold" id="loginModalSubmitBtn">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                    </button>
                </form>

                <p class="text-center small text-secondary mt-3 mb-0">
                    Belum punya akun? <a href="register.php">Daftar di sini</a>
                </p>
            </div>
        </div>
    </div>
</div>
<script>
    // message opsional -- dipakai halaman pemanggil utk kasih konteks kenapa
    // modal muncul, mis. showLoginRequiredModal('Login supaya progres bacamu tersimpan ke akun.')
    function showLoginRequiredModal(message) {
        const msgEl = document.getElementById('loginModalMessage');
        if (msgEl) {
            msgEl.textContent = message || 'Untuk menandai (bookmark) manga & menyimpan progres bacamu ke akun.';
        }
        const modalEl = document.getElementById('loginRequiredModal');
        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    (function () {
        const form = document.getElementById('loginModalForm');
        if (!form) return;
        const errorBox = document.getElementById('loginModalError');
        const submitBtn = document.getElementById('loginModalSubmitBtn');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorBox.classList.add('d-none');
            submitBtn.disabled = true;
            const originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memproses...';

            try {
                const res = await fetch('login_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(new FormData(form)),
                });
                const data = await res.json();

                if (!data.success) {
                    errorBox.textContent = data.error || 'Login gagal.';
                    errorBox.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                    return;
                }

                // Sukses -- reload HALAMAN YANG SAMA (URL tidak berubah, tidak pindah
                // ke login.php) supaya status login ter-render ulang dari server.
                window.location.reload();
            } catch (err) {
                errorBox.textContent = 'Terjadi kesalahan: ' + err.message;
                errorBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        });
    })();

    // Logout via AJAX. fallbackUrl opsional: kalau diisi, redirect ke situ
    // setelah logout (dipakai halaman yang WAJIB login). Kalau kosong, reload
    // halaman yang sama di tempat (dipakai halaman publik: index/manga/reader).
    async function logoutUser(fallbackUrl) {
        try {
            await fetch('logout_api.php', { method: 'POST' });
        } catch (err) { /* tetap lanjut walau request gagal */ }

        if (fallbackUrl) {
            window.location.href = fallbackUrl;
        } else {
            window.location.reload();
        }
    }
</script>
