<?php
/**
 * Shared Footer Layout
 */
?>
    </main>

    <!-- Footer -->
    <footer class="py-4 border-top text-center small text-secondary" style="border-color: var(--bs-border-color) !important;">
        <div class="container" style="max-width: 1000px;">
            <div class="d-flex justify-content-center align-items-center gap-3 flex-wrap">
                <span class="brand-font">Manga Reader</span>
                <span aria-hidden="true">|</span>
                <a href="https://github.com/tommianggoro/manga-reader-app" target="_blank" rel="noopener" class="text-decoration-none text-secondary">
                    <i class="bi bi-github me-1"></i> GitHub
                </a>
                <span aria-hidden="true">|</span>
                <span>Self-hosted manga collection</span>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Theme Toggle Script (shared) -->
    <script src="assets/js/theme.js"></script>

    <!-- Toast Container (shared) -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
        <div id="appToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="6000">
            <div class="toast-header bg-secondary text-white border-0">
                <strong class="me-auto" id="toastTitle">Notifikasi</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastBody"></div>
        </div>
    </div>

    <!-- Login Modal (shared) -->
    <?php include __DIR__ . '/../partials/login_modal.php'; ?>
</body>
</html>