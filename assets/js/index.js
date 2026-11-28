/**
 * Index Page JavaScript Module
 * Handles: filtering, search, view toggle, favorites, batch delete, auto-update polling
 * Used by: index.php
 */

(function() {
    'use strict';

    // Configuration from PHP
    const isLoggedIn = window.MANGA_READER_CONFIG?.isLoggedIn ?? false;
    const isAdminUser = window.MANGA_READER_CONFIG?.isAdmin ?? false;

    // DOM Elements
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const gridCols = Array.from(document.querySelectorAll('#mangaGrid .manga-item-col'));
    const listRows = Array.from(document.querySelectorAll('#mangaList .manga-item-row'));
    const noResultState = document.getElementById('noResultState');
    const resetFilterBtn = document.getElementById('resetFilterBtn');
    const chips = Array.from(document.querySelectorAll('.chip:not(.tag-filter-chip)'));
    const tagFilterChips = Array.from(document.querySelectorAll('.tag-filter-chip'));
    const viewGridBtn = document.getElementById('viewGridBtn');
    const viewListBtn = document.getElementById('viewListBtn');
    const mangaGrid = document.getElementById('mangaGrid');
    const mangaList = document.getElementById('mangaList');
    const themeToggleBtn = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

    // Filter state
    let activeGenre = null;
    let favOnly = false;
    let activeTagId = null;

    // Chapter snapshot for auto-update detection
    const mangaChapterSnapshot = {};

    // POLL_INTERVAL_MS: 45 seconds
    const POLL_INTERVAL_MS = 45000;
    let pollTimer = null;

    // Initialize chapter snapshot
    function initChapterSnapshot() {
        document.querySelectorAll('[data-manga-id] .chapter-badge').forEach(badge => {
            const col = badge.closest('[data-manga-id]');
            if (col) {
                mangaChapterSnapshot[col.dataset.mangaId] = parseFloat(badge.dataset.chapter || '0');
            }
        });
    }

    // Utility: Set chip active state
    function setChipActive(chip, active) {
        chip.classList.toggle('btn-primary', active);
        chip.classList.toggle('btn-outline-secondary', !active);
    }

    // Utility: Apply all filters
    function applyFilters() {
        const q = searchInput.value.trim().toLowerCase();
        clearSearchBtn.style.display = q ? 'block' : 'none';
        let visibleCount = 0;

        const checkMatch = (card) => {
            const matchSearch = !q || card.dataset.title.includes(q) || card.dataset.id.includes(q) ||
                (card.dataset.author && card.dataset.author.includes(q)) ||
                (card.dataset.genres && card.dataset.genres.includes(q));
            const matchGenre = !activeGenre || card.dataset.genres.includes(activeGenre);
            const matchFav = !favOnly || card.dataset.favorite === '1';
            const matchTag = !activeTagId || (card.dataset.tags || '').split(',').includes(String(activeTagId));
            return matchSearch && matchGenre && matchFav && matchTag;
        };

        gridCols.forEach(col => {
            const card = col.querySelector('a');
            const match = checkMatch(card);
            col.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        listRows.forEach(row => {
            const card = row.querySelector('a');
            const match = checkMatch(card);
            row.style.display = match ? '' : 'none';
        });

        noResultState.style.display = (visibleCount === 0 && gridCols.length > 0) ? 'block' : 'none';
    }

    // Search input handler
    searchInput.addEventListener('input', applyFilters);
    clearSearchBtn.addEventListener('click', () => {
        searchInput.value = '';
        applyFilters();
        searchInput.focus();
    });

    // Reset all filters
    resetFilterBtn.addEventListener('click', () => {
        searchInput.value = '';
        activeGenre = null;
        favOnly = false;
        activeTagId = null;
        chips.forEach(c => setChipActive(c, false));
        tagFilterChips.forEach(c => setChipActive(c, false));
        applyFilters();
    });

    // Genre & Favorite chips
    chips.forEach(chip => {
        chip.addEventListener('click', () => {
            if (chip.dataset.filter === 'favorite') {
                if (!isLoggedIn) {
                    if (window.showLoginRequiredModal) window.showLoginRequiredModal();
                    return;
                }
                favOnly = !favOnly;
                setChipActive(chip, favOnly);
            } else {
                const genre = chip.dataset.genre;
                if (activeGenre === genre) {
                    activeGenre = null;
                    setChipActive(chip, false);
                } else {
                    chips.forEach(c => { if (c.dataset.genre) setChipActive(c, false); });
                    activeGenre = genre;
                    setChipActive(chip, true);
                }
            }
            applyFilters();
        });
    });

    // Tag filter chips
    tagFilterChips.forEach(chip => {
        chip.addEventListener('click', () => {
            if (!isLoggedIn) {
                if (window.showLoginRequiredModal) window.showLoginRequiredModal();
                return;
            }
            const tagId = chip.dataset.tagId;
            if (activeTagId === tagId) {
                activeTagId = null;
                setChipActive(chip, false);
            } else {
                tagFilterChips.forEach(c => setChipActive(c, false));
                activeTagId = tagId;
                setChipActive(chip, true);
            }
            applyFilters();
        });
    });

    // Grid vs List view toggle
    function setViewMode(mode) {
        localStorage.setItem('manga_view_mode', mode);
        if (mode === 'grid') {
            mangaGrid.style.setProperty('display', 'flex', 'important');
            mangaList.style.setProperty('display', 'none', 'important');
            viewGridBtn.classList.add('active');
            viewListBtn.classList.remove('active');
        } else {
            mangaGrid.style.setProperty('display', 'none', 'important');
            mangaList.style.setProperty('display', 'flex', 'important');
            viewListBtn.classList.add('active');
            viewGridBtn.classList.remove('active');
        }
    }

    viewGridBtn.addEventListener('click', () => setViewMode('grid'));
    viewListBtn.addEventListener('click', () => setViewMode('list'));
    setViewMode(localStorage.getItem('manga_view_mode') || 'grid');

    // Toggle Favorite (AJAX)
    document.querySelectorAll('.fav-star').forEach(star => {
        star.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (!isLoggedIn) {
                if (window.showLoginRequiredModal) window.showLoginRequiredModal();
                return;
            }

            const mangaId = star.dataset.mangaId;
            try {
                const res = await fetch('toggle_favorite.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'manga_id=' + encodeURIComponent(mangaId),
                });
                const data = await res.json();
                if (!data.success) {
                    alert('Gagal update favorit: ' + data.error);
                    return;
                }

                const isFav = data.is_favorite;
                document.querySelectorAll(`.fav-star[data-manga-id="${CSS.escape(mangaId)}"]`).forEach(s => {
                    const icon = s.querySelector('i');
                    s.classList.toggle('active', isFav);
                    icon.classList.toggle('bi-star-fill', isFav);
                    icon.classList.toggle('bi-star', !isFav);
                    const item = s.closest('a');
                    if (item) item.dataset.favorite = isFav ? '1' : '0';
                });
                applyFilters();
            } catch (err) {
                alert('Gagal update favorit: ' + err.message);
            }
        });
    });

    // ==== Admin: Batch Delete & Manage Mode ====
    const toggleManageModeBtn = document.getElementById('toggleManageModeBtn');
    if (toggleManageModeBtn && isAdminUser) {
        const batchBar = document.getElementById('batchBar');
        const cancelManageBtn = document.getElementById('cancelManageBtn');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const batchDeleteBtn = document.getElementById('batchDeleteBtn');
        const selectedCountText = document.getElementById('selectedCountText');
        const confirmBatchDeleteModal = new bootstrap.Modal(document.getElementById('confirmBatchDeleteModal'));
        const executeBatchDeleteBtn = document.getElementById('executeBatchDeleteBtn');
        const deleteCountModalText = document.getElementById('deleteCountModalText');

        let isManageMode = false;

        function toggleManageMode(enable) {
            isManageMode = enable !== undefined ? enable : !isManageMode;
            document.body.classList.toggle('manage-mode', isManageMode);
            batchBar.style.display = isManageMode ? 'flex' : 'none';
            toggleManageModeBtn.classList.toggle('btn-primary', isManageMode);
            toggleManageModeBtn.classList.toggle('btn-outline-secondary', !isManageMode);

            if (!isManageMode) {
                document.querySelectorAll('.batch-checkbox').forEach(cb => cb.checked = false);
                updateBatchUI();
            }
        }

        toggleManageModeBtn.addEventListener('click', () => toggleManageMode());
        cancelManageBtn.addEventListener('click', () => toggleManageMode(false));

        function getSelectedMangaIds() {
            const checked = Array.from(document.querySelectorAll('.batch-checkbox:checked'));
            return Array.from(new Set(checked.map(cb => cb.value)));
        }

        function updateBatchUI() {
            const selected = getSelectedMangaIds();
            selectedCountText.textContent = `${selected.length} Terpilih`;
            batchDeleteBtn.disabled = selected.length === 0;
        }

        document.querySelectorAll('.batch-checkbox').forEach(cb => {
            cb.addEventListener('change', (e) => {
                const val = e.target.value;
                document.querySelectorAll(`.batch-checkbox[value="${CSS.escape(val)}"]`).forEach(c => c.checked = e.target.checked);
                updateBatchUI();
            });
        });

        selectAllBtn.addEventListener('click', () => {
            const allCheckboxes = Array.from(document.querySelectorAll('.batch-checkbox'));
            const visibleCols = gridCols.filter(col => col.style.display !== 'none');
            const visibleIds = visibleCols.map(col => col.dataset.mangaId);

            const areAllVisibleSelected = visibleIds.every(id => {
                const cb = document.querySelector(`.batch-checkbox[value="${CSS.escape(id)}"]`);
                return cb && cb.checked;
            });

            allCheckboxes.forEach(cb => {
                if (visibleIds.includes(cb.value)) {
                    cb.checked = !areAllVisibleSelected;
                }
            });
            updateBatchUI();
        });

        batchDeleteBtn.addEventListener('click', () => {
            const selected = getSelectedMangaIds();
            if (selected.length === 0) return;
            deleteCountModalText.textContent = selected.length;
            confirmBatchDeleteModal.show();
        });

        executeBatchDeleteBtn.addEventListener('click', async () => {
            const selected = getSelectedMangaIds();
            if (selected.length === 0) return;

            try {
                executeBatchDeleteBtn.disabled = true;
                executeBatchDeleteBtn.textContent = 'Menghapus...';

                const res = await fetch('delete_manga.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'manga_ids=' + encodeURIComponent(JSON.stringify(selected))
                });
                const data = await res.json();
                if (!data.success) {
                    alert('Gagal menghapus manga: ' + data.error);
                    return;
                }

                selected.forEach(mId => {
                    document.querySelectorAll(`[data-manga-id="${CSS.escape(mId)}"]`).forEach(el => el.remove());
                });

                confirmBatchDeleteModal.hide();
                toggleManageMode(false);

                const remaining = document.querySelectorAll('#mangaGrid .manga-item-col').length;
                document.getElementById('statMangaCount').textContent = `${remaining} Manga`;

                if (remaining === 0) {
                    window.location.reload();
                }
            } catch (err) {
                alert('Error: ' + err.message);
            } finally {
                executeBatchDeleteBtn.disabled = false;
                executeBatchDeleteBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Ya, Hapus Sekarang';
            }
        });
    }

    // ==== Auto-Update Background (Polling) ====
    function applyChapterUpdate(mangaId, newChapterNumber) {
        document.querySelectorAll(`[data-manga-id="${CSS.escape(mangaId)}"] .chapter-badge`).forEach(badge => {
            badge.textContent = badge.textContent.replace(/\d+(\.\d+)?/, newChapterNumber);
            badge.dataset.chapter = newChapterNumber;
            badge.classList.add('just-flashed');
            setTimeout(() => badge.classList.remove('just-flashed'), 1500);
        });

        document.querySelectorAll('.manga-card, .manga-list-item').forEach(card => {
            const wrapper = card.closest('[data-manga-id]');
            if (wrapper && wrapper.dataset.mangaId === mangaId) {
                card.classList.add('just-updated');
                setTimeout(() => card.classList.remove('just-updated'), 2500);
            }
        });
    }

    function showNewChapterToast(count) {
        const toastEl = document.getElementById('newChapterToast');
        const textEl = document.getElementById('newChapterToastText');
        textEl.innerHTML = '<i class="bi bi-stars me-1"></i> ' +
            (count === 1 ? '1 manga punya chapter baru!' : `${count} manga punya chapter baru!`);
        bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 6000 }).show();
    }

    async function pollForUpdates() {
        try {
            const res = await fetch('check_updates.php', { cache: 'no-store' });
            const data = await res.json();
            if (!data.success) return;

            document.getElementById('statMangaCount').textContent = `${data.stats.total_manga} Manga`;
            const favEl = document.getElementById('statFavCount');
            if (favEl) favEl.textContent = `${data.stats.total_favorites} Favorit`;
            const chEl = document.getElementById('statChapterCount');
            if (chEl) chEl.textContent = `${data.stats.total_chapters} Chapter`;

            let updatedCount = 0;
            for (const [mangaId, latestChapter] of Object.entries(data.mangas)) {
                const prev = mangaChapterSnapshot[mangaId];
                if (prev === undefined) continue;
                if (latestChapter > prev) {
                    mangaChapterSnapshot[mangaId] = latestChapter;
                    applyChapterUpdate(mangaId, latestChapter);
                    updatedCount++;
                }
            }

            if (updatedCount > 0) showNewChapterToast(updatedCount);
        } catch (err) {
            console.warn('Gagal memeriksa update background:', err.message);
        }
    }

    // Start polling
    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(pollForUpdates, POLL_INTERVAL_MS);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    // Pause when tab hidden
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            pollForUpdates();
            startPolling();
        }
    });

    // BroadcastChannel for cross-tab updates
    if (window.BroadcastChannel) {
        const updateChannel = new BroadcastChannel('manga_reader_updates');
        updateChannel.addEventListener('message', (event) => {
            const msg = event.data;
            if (msg && msg.type === 'manga_updated' && msg.manga_id && msg.chapter_number) {
                const prev = mangaChapterSnapshot[msg.manga_id] || 0;
                if (msg.chapter_number > prev) {
                    mangaChapterSnapshot[msg.manga_id] = msg.chapter_number;
                    applyChapterUpdate(msg.manga_id, msg.chapter_number);
                }
            }
        });
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        initChapterSnapshot();
        startPolling();
    });

    // Expose for other scripts
    window.MangaIndex = {
        applyFilters,
        setViewMode,
        pollForUpdates,
        startPolling,
        stopPolling
    };
})();