/**
 * Manga Detail Page JavaScript Module
 * Handles: chapter search/filter, favorites, custom tags, preferred source, delete manga, guest resume, auto-update polling
 * Used by: manga.php
 */

(function() {
    'use strict';

    // Configuration from PHP
    const isLoggedIn = window.MANGA_READER_CONFIG?.isLoggedIn ?? false;
    const currentMangaId = window.MANGA_READER_CONFIG?.currentMangaId ?? '';
    const knownLatestChapter = window.MANGA_READER_CONFIG?.knownLatestChapter ?? 0;

    // DOM Elements
    const searchInput = document.getElementById('searchInput');
    const items = Array.from(document.querySelectorAll('#chapterList a'));
    const noResult = document.getElementById('noResult');
    const filterReadBtn = document.getElementById('filterReadBtn');
    const filterUnreadBtn = document.getElementById('filterUnreadBtn');
    const filterAllBtn = document.getElementById('filterAllBtn');
    const favBtn = document.getElementById('favBtn');
    const mangaTagsContainer = document.getElementById('mangaTagsContainer');
    const tagAddBtn = document.getElementById('tagAddBtn');
    const preferredSourceSelect = document.getElementById('preferredSourceSelect');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    // State
    let currentSearchQuery = '';
    let currentFilterMode = 'all'; // 'read', 'unread', 'all'
    let allUserTags = []; // cache all user tags from API

    // POLL_INTERVAL_MS: 45 seconds
    const POLL_INTERVAL_MS = 45000;
    let mangaPollTimer = null;

    // Utility: escape HTML
    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s ?? '';
        return div.innerHTML;
    }

    // Initialize filter buttons
    filterAllBtn?.classList.add('active');

    // Apply combined filters (search + read status)
    function applyFilters() {
        let visibleCount = 0;
        items.forEach(item => {
            const matchSearch = !currentSearchQuery || item.dataset.search.includes(currentSearchQuery);
            const isRead = item.dataset.read === '1';

            let matchFilter = true;
            if (currentFilterMode === 'read') matchFilter = isRead;
            else if (currentFilterMode === 'unread') matchFilter = !isRead;

            const show = matchSearch && matchFilter;
            item.classList.toggle('d-none', !show);
            if (show) visibleCount++;
        });
        noResult.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    // Search input handler
    searchInput?.addEventListener('input', () => {
        currentSearchQuery = searchInput.value.trim().toLowerCase();
        applyFilters();
    });

    // Filter mode handlers
    function setFilterMode(mode) {
        currentFilterMode = mode;
        applyFilters();

        [filterReadBtn, filterUnreadBtn, filterAllBtn].forEach(b => b?.classList.remove('active'));
        if (mode === 'read') filterReadBtn?.classList.add('active');
        else if (mode === 'unread') filterUnreadBtn?.classList.add('active');
        else filterAllBtn?.classList.add('active');
    }

    filterReadBtn?.addEventListener('click', () => setFilterMode('read'));
    filterUnreadBtn?.addEventListener('click', () => setFilterMode('unread'));
    filterAllBtn?.addEventListener('click', () => setFilterMode('all'));

    // Toggle Favorite (AJAX)
    favBtn?.addEventListener('click', async () => {
        if (!isLoggedIn) {
            if (window.showLoginRequiredModal) window.showLoginRequiredModal();
            return;
        }
        try {
            const res = await fetch('toggle_favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'manga_id=' + encodeURIComponent(favBtn.dataset.mangaId),
            });
            const data = await res.json();
            if (!data.success) {
                alert('Gagal update favorit: ' + data.error);
                return;
            }

            const icon = favBtn.querySelector('i');
            favBtn.classList.toggle('active', data.is_favorite);
            icon.classList.toggle('bi-star-fill', data.is_favorite);
            icon.classList.toggle('bi-star', !data.is_favorite);
        } catch (err) {
            alert('Gagal update favorit: ' + err.message);
        }
    });

    // ===== CUSTOM TAGS SYSTEM =====
    async function loadUserTags() {
        if (!isLoggedIn) return;
        try {
            const res = await fetch('api_tags.php?action=list');
            const data = await res.json();
            if (data.success) {
                allUserTags = data.tags || [];
            }
        } catch (err) {
            console.warn('Gagal load tags:', err.message);
        }
    }

    async function loadMangaTags() {
        if (!isLoggedIn || !mangaTagsContainer) return;
        try {
            const res = await fetch('api_tags.php?action=manga_tags&manga_id=' + encodeURIComponent(currentMangaId));
            const data = await res.json();
            if (data.success) {
                renderMangaTags(data.tags || []);
            }
        } catch (err) {
            console.warn('Gagal load manga tags:', err.message);
        }
    }

    function renderMangaTags(tags) {
        if (!mangaTagsContainer) return;
        mangaTagsContainer.innerHTML = '';
        tags.forEach(tag => {
            const chip = document.createElement('span');
            chip.className = 'tag-chip';
            chip.style.background = tag.color + '20'; // 12% opacity
            chip.style.borderColor = tag.color;
            chip.style.color = tag.color;
            chip.dataset.tagId = tag.id;
            chip.innerHTML = `
                ${escapeHtml(tag.name)}
                <span class="tag-remove" title="Hapus tag dari manga ini"><i class="bi bi-x"></i></span>
            `;
            chip.querySelector('.tag-remove').addEventListener('click', (e) => {
                e.stopPropagation();
                removeTagFromManga(tag.id);
            });
            mangaTagsContainer.appendChild(chip);
        });
    }

    // Show tag picker modal
    function showTagPicker() {
        if (!isLoggedIn) {
            if (window.showLoginRequiredModal) window.showLoginRequiredModal();
            return;
        }
        const modalId = 'tagPickerModal';
        let modal = document.getElementById(modalId);
        if (!modal) {
            modal = document.createElement('div');
            modal.id = modalId;
            modal.className = 'modal fade';
            modal.tabIndex = -1;
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title brand-font"><i class="bi bi-tag-fill me-2"></i>Kelola Tag</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Buat Tag Baru</label>
                                <div class="input-group" style="border-radius: 0.5rem; overflow: hidden;">
                                    <input type="text" id="newTagName" class="form-control form-control-sm" placeholder="Nama tag (mis. Prioritas, Baca Nanti...)" maxlength="64" style="border-radius: 0; min-width: 0; flex: 1;">
                                    <input type="color" id="newTagColor" class="form-control form-control-color form-control-sm" value="#6c757d" title="Warna tag" style="width: 48px; height: 38px; padding: 0; border-radius: 0; border-left: none; border-right: none; flex-shrink: 0;">
                                    <button type="button" class="btn btn-primary btn-sm" id="createTagBtn" style="border-radius: 0;">Buat</button>
                                </div>
                                <div id="createTagError" class="text-danger small mt-1"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Tag Saya (klik untuk tambah/hapus dari manga ini)</label>
                                <div id="tagPickerList" class="tag-chips-wrap" style="min-height: 60px;"></div>
                                <div class="text-secondary small" id="noTagsHint" style="display:none;">Belum ada tag. Buat tag baru di atas.</div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Create tag handler
            document.getElementById('createTagBtn').addEventListener('click', createTag);
            document.getElementById('newTagName').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') createTag();
            });
        }

        populateTagPickerList();

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

    function populateTagPickerList() {
        const tagPickerList = document.getElementById('tagPickerList');
        const noTagsHint = document.getElementById('noTagsHint');

        if (allUserTags.length === 0) {
            tagPickerList.innerHTML = '';
            noTagsHint.style.display = 'block';
        } else {
            noTagsHint.style.display = 'none';
            tagPickerList.innerHTML = '';
            // Get currently assigned tag IDs for this manga
            const assignedTagIds = new Set();
            mangaTagsContainer?.querySelectorAll('.tag-chip').forEach(chip => {
                assignedTagIds.add(chip.dataset.tagId);
            });

            allUserTags.forEach(tag => {
                const isAssigned = assignedTagIds.has(String(tag.id));
                const chip = document.createElement('span');
                chip.className = 'tag-chip ' + (isAssigned ? 'active' : '');
                chip.style.background = tag.color + (isAssigned ? '40' : '20');
                chip.style.borderColor = tag.color;
                chip.style.color = tag.color;
                chip.dataset.tagId = tag.id;
                chip.innerHTML = `
                    ${escapeHtml(tag.name)}
                    ${isAssigned ? '<span class="tag-remove" style="display:flex;" title="Hapus dari manga"><i class="bi bi-check-lg"></i></span>' : ''}
                `;
                // Click on chip toggles assignment; click on inner .tag-remove should not bubble
                chip.addEventListener('click', (e) => {
                    if (e.target.closest('.tag-remove')) return; // ignore clicks on remove icon
                    toggleTagOnManga(tag.id, tag.name, tag.color, !isAssigned);
                });
                tagPickerList.appendChild(chip);
            });
        }
    }

    async function createTag() {
        const nameInput = document.getElementById('newTagName');
        const colorInput = document.getElementById('newTagColor');
        const errorEl = document.getElementById('createTagError');
        const name = nameInput.value.trim();
        const color = colorInput.value;

        if (!name) { errorEl.textContent = 'Nama tag tidak boleh kosong'; return; }
        errorEl.textContent = '';

        try {
            const res = await fetch('api_tags.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=create&name=' + encodeURIComponent(name) + '&color=' + encodeURIComponent(color)
            });
            const data = await res.json();
            if (!data.success) { errorEl.textContent = data.error; return; }

            // Add to cache and update UI
            allUserTags.push(data.tag);
            nameInput.value = '';
            populateTagPickerList();
        } catch (err) {
            errorEl.textContent = 'Gagal: ' + err.message;
        }
    }

    async function toggleTagOnManga(tagId, tagName, tagColor, assign) {
        try {
            const action = assign ? 'assign' : 'remove';
            const res = await fetch('api_tags.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=' + action + '&manga_id=' + encodeURIComponent(currentMangaId) + '&tag_id=' + encodeURIComponent(tagId)
            });
            const data = await res.json();
            if (!data.success) { alert('Gagal: ' + data.error); return; }

            // Reload manga tags display (main header)
            await loadMangaTags();
            // Re-render picker list with fresh state from main container
            populateTagPickerList();
        } catch (err) {
            alert('Gagal: ' + err.message);
        }
    }

    async function removeTagFromManga(tagId) {
        try {
            const res = await fetch('api_tags.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=remove&manga_id=' + encodeURIComponent(currentMangaId) + '&tag_id=' + encodeURIComponent(tagId)
            });
            const data = await res.json();
            if (!data.success) { alert('Gagal: ' + data.error); return; }
            await loadMangaTags();
            // Also update picker if open
            const pickerChip = document.querySelector('#tagPickerList .tag-chip[data-tag-id=\'' + tagId + '\']');
            if (pickerChip) {
                pickerChip.classList.remove('active');
                const tag = allUserTags.find(t => String(t.id) === String(tagId));
                if (tag) pickerChip.style.background = tag.color + '20';
                const removeEl = pickerChip.querySelector('.tag-remove');
                if (removeEl) removeEl.remove();
            }
        } catch (err) {
            alert('Gagal: ' + err.message);
        }
    }

    // Initialize tags
    if (isLoggedIn) {
        (async () => {
            await loadUserTags();
            await loadMangaTags();
        })();
        tagAddBtn?.addEventListener('click', showTagPicker);
    }

    // Preferred Source Select (Admin)
    if (preferredSourceSelect) {
        preferredSourceSelect.addEventListener('change', async () => {
            try {
                await fetch('save_preferred_source.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'manga_id=' + encodeURIComponent(currentMangaId) +
                          '&source=' + encodeURIComponent(preferredSourceSelect.value),
                });
            } catch (err) {
                alert('Gagal simpan preferensi sumber: ' + err.message);
            }
        });
    }

    // Delete Single Manga (Admin)
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async () => {
            try {
                confirmDeleteBtn.disabled = true;
                confirmDeleteBtn.textContent = 'Menghapus...';

                const res = await fetch('delete_manga.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'manga_id=' + encodeURIComponent(currentMangaId)
                });
                const data = await res.json();
                if (!data.success) {
                    alert('Gagal menghapus manga: ' + data.error);
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Hapus Permanen';
                    return;
                }
                window.location.href = 'index.php';
            } catch (err) {
                alert('Error: ' + err.message);
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Hapus Permanen';
            }
        });
    }

    // ==== GUEST: Resume/Last-Read from localStorage ====
    // If user is LOGIN, resume button & "last read" badge are already rendered
    // server-side from DB. If GUEST, we attach the same info from localStorage
    // (written by reader.php each time a chapter is read).
    if (!isLoggedIn) {
        try {
            const raw = localStorage.getItem('manga_last_read:' + currentMangaId);
            if (raw) {
                const info = JSON.parse(raw);
                const resumeBtn = document.getElementById('resumeBtn');
                const resumeBtnText = document.getElementById('resumeBtnText');
                const startOverBtn = document.getElementById('startOverBtn');

                if (resumeBtn && info.chapter_id) {
                    resumeBtn.href = 'reader.php?chapter_id=' + encodeURIComponent(info.chapter_id);
                    if (resumeBtnText) resumeBtnText.textContent = 'Lanjutkan Chapter ' + info.chapter_number;
                    resumeBtn.querySelector('i').className = 'bi bi-play-fill fs-5 me-1';
                    if (startOverBtn) startOverBtn.classList.remove('d-none');
                }

                // Mark matching chapter row as "current-read"
                const row = document.querySelector('#chapterList a[data-chapter-id="' + CSS.escape(info.chapter_id) + '"]');
                if (row) {
                    row.classList.add('current-read');
                    if (!row.querySelector('.current-read-icon')) {
                        const icon = document.createElement('i');
                        icon.className = 'bi bi-bookmark-fill text-primary current-read-icon';
                        icon.title = 'Chapter terakhir dibaca (tersimpan di perangkat ini)';
                        row.querySelector('span.d-flex').appendChild(icon);
                    }
                }
            }
        } catch (e) {
            // localStorage disabled/corrupted, ignore
        }
    }

    // ==== Auto-Update Background (Polling) ====
    function showNewChapterToast(count) {
        const toastEl = document.getElementById('newChapterToast');
        const textEl = document.getElementById('newChapterToastText');
        textEl.innerHTML = '<i class="bi bi-stars me-1"></i> ' +
            (count === 1 ? '1 chapter baru tersedia!' : count + ' chapter baru tersedia!');
        bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 6000 }).show();
    }

    function prependNewChapters(newChapters) {
        const list = document.getElementById('chapterList');
        const ordered = [...newChapters].reverse();

        ordered.forEach(ch => {
            const a = document.createElement('a');
            a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 just-added';
            a.href = 'reader.php?chapter_id=' + encodeURIComponent(ch.chapter_id);
            a.dataset.chapterId = ch.chapter_id;
            a.dataset.search = (ch.chapter_number + ' ' + (ch.chapter_title || '')).toLowerCase();

            const titlePart = ch.chapter_title
                ? ' — <span class="text-secondary font-normal">' + escapeHtml(ch.chapter_title) + '</span>'
                : '';

            a.innerHTML = `
                <span class="fw-medium">Chapter ${ch.chapter_number}${titlePart}</span>
                <span class="d-flex align-items-center gap-2 flex-shrink-0">
                    <small class="text-secondary">${ch.date_label}</small>
                    <span class="badge text-bg-primary">Baru</span>
                </span>
            `;

            list.prepend(a);
            items.unshift(a);

            setTimeout(() => a.classList.remove('just-added'), 2500);
        });
    }

    async function pollForMangaUpdates() {
        try {
            const res = await fetch('check_manga_updates.php?manga_id=' + encodeURIComponent(currentMangaId) + '&since=' + knownLatestChapter, { cache: 'no-store' });
            const data = await res.json();
            if (!data.success) return;

            if (data.new_chapters && data.new_chapters.length > 0) {
                prependNewChapters(data.new_chapters);
                knownLatestChapter = data.latest_chapter_number;

                const countEl = document.getElementById('totalChapterCount');
                if (countEl) countEl.textContent = data.total_chapters;

                showNewChapterToast(data.new_chapters.length);
            }
        } catch (err) {
            console.warn('Gagal memeriksa update background:', err.message);
        }
    }

    function startPolling() {
        if (mangaPollTimer) clearInterval(mangaPollTimer);
        mangaPollTimer = setInterval(pollForMangaUpdates, POLL_INTERVAL_MS);
    }

    function stopPolling() {
        if (mangaPollTimer) {
            clearInterval(mangaPollTimer);
            mangaPollTimer = null;
        }
    }

    // Pause when tab hidden
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            pollForMangaUpdates();
            startPolling();
        }
    });

    // BroadcastChannel for cross-tab updates
    if (window.BroadcastChannel) {
        const updateChannel = new BroadcastChannel('manga_reader_updates');
        updateChannel.addEventListener('message', (event) => {
            const msg = event.data;
            if (msg && msg.type === 'manga_updated' && msg.manga_id === currentMangaId) {
                pollForMangaUpdates();
            }
        });
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        startPolling();
    });

    // Expose for other scripts
    window.MangaDetail = {
        applyFilters,
        setFilterMode,
        loadMangaTags,
        pollForMangaUpdates,
        startPolling,
        stopPolling
    };
})();