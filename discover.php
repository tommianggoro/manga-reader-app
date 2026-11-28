<?php
/**
 * Manga Reader - Discover Page (Thin Controller)
 * Public manga search from all sources
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

optionalAuth();

$userId = currentUserId(); // null kalau guest
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Temukan Manga - Koleksi Manga Pribadi</title>

    <!-- Pre-paint theme script (no flash) -->
    <script>
    (function() {
        const savedTheme = localStorage.getItem('manga_theme') || 'dark';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    })();
    </script>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts: Bitter (serif) + Inter (sans) -->
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Design Tokens -->
    <link href="assets/css/variables.css" rel="stylesheet">

    <link rel="icon" href="favicon.ico" sizes="any">

    <link rel="manifest" href="https://tommianggoro.github.io/manga-reader-app/manifest.json">
    <meta name="theme-color" content="#10131a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Manga Reader">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
</head>
<body>
<div class="container py-4" style="max-width: 1000px;">

<?php
// Include composed content view
$userId = $userId;
include __DIR__ . '/views/discover/content.php';
?>

</div>

<?php include __DIR__ . '/views/partials/login_modal.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const isLoggedIn = <?= $userId ? 'true' : 'false' ?>;

    const searchInput = document.getElementById("searchInput");
    const resultsGrid = document.getElementById("resultsGrid");
    const searchStatus = document.getElementById("searchStatus");
    const errorNotice = document.getElementById("errorNotice");
    const loadMoreWrap = document.getElementById("loadMoreWrap");
    const loadMoreBtn = document.getElementById("loadMoreBtn");

    let debounceTimer = null;
    let currentController = null;
    let currentQuery = "";
    let currentPage = 1;
    let totalShown = 0;
    let sourcesWithMore = new Set();

    function skeletonCards(n = 8) {
        for (let i = 0; i < n; i++) {
            const col = document.createElement("div");
            col.className = "col skeleton-col";
            col.innerHTML = `<div class="skeleton-card"><div class="result-cover-wrap skeleton-shimmer"></div></div>`;
            resultsGrid.appendChild(col);
        }
    }
    function clearSkeletons() {
        resultsGrid.querySelectorAll(".skeleton-col").forEach(el => el.remove());
    }

    function escapeHtml(s) {
        const div = document.createElement("div");
        div.textContent = s ?? "";
        return div.innerHTML;
    }

    function appendResultCard(item) {
        const col = document.createElement("div");
        col.className = "col";
        const chapterText = item.latest_chapter_number ? `Ch. ${item.latest_chapter_number}` : "-";

        // Guard: if no sources, show placeholder
        if (!item.sources || item.sources.length === 0) {
            col.innerHTML = `
                <div class="result-card h-100 d-flex flex-column">
                    <div class="result-cover-wrap">
                        <img src="${escapeHtml(item.cover_image_url || "")}" alt="${escapeHtml(item.title)}" loading="lazy" onerror="this.style.opacity=0">
                    </div>
                    <div class="p-2 d-flex flex-column flex-grow-1">
                        <div class="fw-semibold small mb-1 text-truncate" title="${escapeHtml(item.title)}">${escapeHtml(item.title)}</div>
                        <div class="small text-secondary mb-2">${chapterText}${item.rating ? ` &middot; <i class="bi bi-star-fill text-warning"></i> ${item.rating}` : ""}</div>
                        <button class="btn btn-secondary btn-sm mt-auto fw-semibold" disabled>
                            <i class="bi bi-exclamation-triangle me-1"></i> Tidak ada sumber
                        </button>
                    </div>
                </div>
            `;
            resultsGrid.appendChild(col);
            return;
        }

        const sourceBadges = item.sources.map(s =>
            `<span class="source-tag"><i class="bi bi-hdd-network me-1"></i>${escapeHtml(s.label)}</span>`
        ).join("");

        // URL add_manga.php butuh source & source_ref. Kalau multi-source, default ke sumber pertama.
        const primarySource = item.sources[0];
        const addUrl = `add_manga.php?source=${encodeURIComponent(primarySource.key)}&source_ref=${encodeURIComponent(primarySource.ref)}`;

        col.innerHTML = `
            <div class="result-card h-100 d-flex flex-column">
                <div class="result-cover-wrap">
                    <img src="${escapeHtml(item.cover_image_url || "")}" alt="${escapeHtml(item.title)}" loading="lazy" onerror="this.style.opacity=0">
                </div>
                <div class="p-2 d-flex flex-column flex-grow-1">
                    <div class="source-tags mb-1">${sourceBadges}</div>
                    <div class="fw-semibold small mb-1 text-truncate" title="${escapeHtml(item.title)}">${escapeHtml(item.title)}</div>
                    <div class="small text-secondary mb-2">${chapterText}${item.rating ? ` &middot; <i class="bi bi-star-fill text-warning"></i> ${item.rating}` : ""}</div>
                    <a href="${addUrl}" class="btn btn-primary btn-sm mt-auto fw-semibold">
                        <i class="bi bi-plus-lg me-1"></i> Tambah ke Koleksi
                    </a>
                </div>
            </div>
        `;
        resultsGrid.appendChild(col);
    }

    function renderResults(data, append) {
        clearSkeletons();
        if (!append) {
            resultsGrid.innerHTML = "";
            errorNotice.textContent = "";
            totalShown = 0;
            sourcesWithMore = new Set();
        }

        (data.items || []).forEach(item => {
            appendResultCard(item);
            totalShown++;
        });

        searchStatus.innerHTML = totalShown === 0
            ? `<i class="bi bi-emoji-frown me-1"></i> Tidak ada hasil ditemukan.`
            : `<i class="bi bi-check-circle me-1 text-success"></i> ${totalShown} hasil ditampilkan.`;

        if (data.errors && Object.keys(data.errors).length > 0) {
            errorNotice.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> Beberapa sumber gagal dicari: ${Object.keys(data.errors).map(escapeHtml).join(", ")}`;
        }

        // Update sourcesWithMore
        (data.sources_with_more || []).forEach(src => sourcesWithMore.add(src));
        loadMoreWrap.style.display = sourcesWithMore.size > 0 ? "block" : "none";
    }

    async function runSearch(query, page, append) {
        if (currentController) currentController.abort();
        currentController = new AbortController();

        if (!append) {
            skeletonCards();
            loadMoreWrap.style.display = "none";
        }

        searchStatus.innerHTML = `<span class="spinner-border spinner-border-sm text-primary me-1"></span> Mencari...`;

        try {
            let url = `search_public_api.php?q=${encodeURIComponent(query)}&page=${page}`;
            if (append && sourcesWithMore.size > 0) {
                url += `&sources=${encodeURIComponent(Array.from(sourcesWithMore).join(","))}`;
            }

            const res = await fetch(url, { signal: currentController.signal });
            const data = await res.json();
            if (!data.success) {
                clearSkeletons();
                searchStatus.innerHTML = `<i class="bi bi-exclamation-triangle me-1 text-danger"></i> ${escapeHtml(data.error || "Gagal mencari.")}`;
                return;
            }
            renderResults(data, append);
        } catch (err) {
            if (err.name === "AbortError") return;
            clearSkeletons();
            searchStatus.innerHTML = `<i class="bi bi-exclamation-triangle me-1 text-danger"></i> Error: ${escapeHtml(err.message)}`;
        }
    }

    searchInput.addEventListener("input", () => {
        clearTimeout(debounceTimer);
        const q = searchInput.value.trim();
        if (q.length < 2) {
            resultsGrid.innerHTML = "";
            searchStatus.textContent = "";
            errorNotice.textContent = "";
            loadMoreWrap.style.display = "none";
            sourcesWithMore = new Set();
            return;
        }
        debounceTimer = setTimeout(() => {
            currentQuery = q;
            currentPage = 1;
            runSearch(currentQuery, currentPage, false);
        }, 500);
    });

    loadMoreBtn.addEventListener("click", async () => {
        const originalHtml = loadMoreBtn.innerHTML;
        loadMoreBtn.disabled = true;
        loadMoreBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Memuat...`;

        currentPage++;
        await runSearch(currentQuery, currentPage, true);

        loadMoreBtn.disabled = false;
        loadMoreBtn.innerHTML = originalHtml;
    });

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
</script>
</body>
</html>