<?php
require_once "config.php";
requireAuth();
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cari & Tambah Manga</title>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('manga_theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root, [data-bs-theme="dark"] {
            --bs-body-bg: #10131a; --bs-body-color: #eae7e0; --bs-primary: #f2a541;
            --bs-primary-rgb: 242, 165, 65; --bs-border-color: #242938; --bs-secondary-bg: #171b26; --bs-tertiary-bg: #202636;
        }
        body { font-family: 'Inter', system-ui, sans-serif; padding-bottom: 3rem; }
        .brand-font { font-family: 'Bitter', Georgia, serif; }
        .result-card { background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); border-radius: 12px; overflow: hidden; transition: border-color .15s ease, transform .15s ease; }
        .result-card:hover { border-color: var(--bs-primary); transform: translateY(-2px); }
        .result-cover-wrap { width: 100%; aspect-ratio: 2/3; background: var(--bs-tertiary-bg); overflow: hidden; }
        .result-cover-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .source-badge { font-size: .68rem; }
        #searchStatus { min-height: 1.5rem; }
        .skeleton-card { background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); border-radius: 12px; overflow: hidden; }
        .skeleton-shimmer { background: linear-gradient(90deg, #1f2533 25%, #2b3347 50%, #1f2533 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <a class="d-inline-flex align-items-center gap-1 text-decoration-none fw-medium" href="index.php">
            <i class="bi bi-arrow-left"></i> Kembali ke koleksi
        </a>
    </div>

    <h1 class="brand-font h4 mb-1"><i class="bi bi-search-heart text-primary me-1"></i> Cari & Tambah Manga</h1>
    <p class="text-secondary small mb-3">Cari judul manga/manhwa — hasil digabung dari semua sumber yang mendukung pencarian.</p>

    <div class="input-group input-group-lg mb-2">
        <span class="input-group-text bg-body-tertiary border-secondary-subtle"><i class="bi bi-search"></i></span>
        <input type="text" id="searchInput" class="form-control" placeholder="Contoh: solo leveling..." autofocus>
    </div>
    <div id="searchStatus" class="small text-secondary mb-3"></div>

    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 g-3" id="resultsGrid"></div>
    <div class="text-center mt-3" id="loadMoreWrap" style="display:none;">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" id="loadMoreBtn">
            <i class="bi bi-arrow-down-circle me-1"></i> Muat Lebih Banyak
        </button>
    </div>
    <div id="errorNotice" class="small text-warning mt-3"></div>
</div>

<script>
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
    let sourcesWithMore = new Set(); // key sumber yg masih punya halaman berikutnya

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
        return (s ?? "").toString().replace(/[&<>"']/g, c => ({ "&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#39;" }[c]));
    }

    function appendResultCard(key, group, item) {
        const col = document.createElement("div");
        col.className = "col";
        const addUrl = `add_manga.php?source=${encodeURIComponent(key)}&source_ref=${encodeURIComponent(item.ref)}`;
        const chapterText = item.latest_chapter_number ? `Ch. ${item.latest_chapter_number}` : "-";
        col.innerHTML = `
            <div class="result-card h-100 d-flex flex-column">
                <div class="result-cover-wrap">
                    <img src="${escapeHtml(item.cover_image_url || "")}" alt="${escapeHtml(item.title)}" loading="lazy" onerror="this.style.opacity=0">
                </div>
                <div class="p-2 d-flex flex-column flex-grow-1">
                    <span class="badge text-bg-secondary source-badge align-self-start mb-1"><i class="bi bi-hdd-network me-1"></i>${escapeHtml(group.label)}</span>
                    <div class="fw-semibold small mb-1 text-truncate" title="${escapeHtml(item.title)}">${escapeHtml(item.title)}</div>
                    <div class="small text-secondary mb-2">${chapterText}${item.rating ? ` &middot; <i class="bi bi-star-fill text-warning"></i> ${item.rating}` : ""}</div>
                    <a href="${addUrl}" class="btn btn-primary btn-sm mt-auto fw-semibold">
                        <i class="bi bi-plus-lg me-1"></i> Tambah
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

        const groups = data.results || {};
        Object.keys(groups).forEach(key => {
            const group = groups[key];
            group.items.forEach(item => appendResultCard(key, group, item));
            totalShown += group.items.length;

            if (group.has_more) sourcesWithMore.add(key);
            else sourcesWithMore.delete(key);
        });

        searchStatus.innerHTML = totalShown === 0
            ? `<i class="bi bi-emoji-frown me-1"></i> Tidak ada hasil ditemukan.`
            : `<i class="bi bi-check-circle me-1 text-success"></i> ${totalShown} hasil ditampilkan.`;

        if (data.errors && Object.keys(data.errors).length > 0) {
            errorNotice.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> Beberapa sumber gagal dicari: ${Object.keys(data.errors).map(escapeHtml).join(", ")}`;
        }

        loadMoreWrap.style.display = sourcesWithMore.size > 0 ? "block" : "none";
    }

    async function runSearch(query, page, append) {
        if (currentController) currentController.abort();
        currentController = new AbortController();

        if (!append) {
            skeletonCards();
            loadMoreWrap.style.display = "none";
        }
        // Saat append (Load More), loadMoreWrap TIDAK disembunyikan di sini --
        // tombolnya sendiri yg mengatur state loading (disabled + spinner) lewat
        // event listener di bawah, supaya spinner tetap terlihat user.

        searchStatus.innerHTML = `<span class="spinner-border spinner-border-sm text-primary me-1"></span> Mencari...`;

        try {
            let url = `search_manga_api.php?q=${encodeURIComponent(query)}&page=${page}`;
            // Load More: hanya query ulang sumber yg masih punya halaman berikutnya,
            // supaya sumber tanpa paging (Komiku/CosmicScans) tidak dikembalikan lagi.
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
</script>
</body>
</html>