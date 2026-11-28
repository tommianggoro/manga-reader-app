/**
 * Reader Page JavaScript Module
 * Handles: chapter navigation, progress persistence (dual-write), layout modes, reading progress bar, immersive UI
 * Used by: reader.php
 */

(function() {
    'use strict';

    // Configuration from PHP
    const cfg = window.MANGA_READER_CONFIG || {};
    const isLoggedIn = !!cfg.isLoggedIn;
    const currentMangaId = cfg.currentMangaId || '';
    const currentChapterId = cfg.currentChapterId || '';
    const currentChapterNumber = cfg.currentChapterNumber || 0;
    const prevChapterId = cfg.prevChapterId || null;
    const nextChapterId = cfg.nextChapterId || null;
    const nextChapterImageUrls = cfg.nextChapterImageUrls || [];
    const totalPages = cfg.totalPages || 0;
    const dbScrollPosition = cfg.dbScrollPosition ?? null;
    const dbSinglePageIndex = cfg.dbSinglePageIndex ?? null;

    let currentPageIndex = 0;

    // DOM Elements
    const readerContainer = document.getElementById('readerContainer');
    const pageIndicatorText = document.getElementById('pageIndicatorText');
    const settingWidthSelect = document.getElementById('settingWidthSelect');
    const settingLayoutSelect = document.getElementById('settingLayoutSelect');
    const jumpSelect = document.getElementById('jumpSelect');
    const progressBar = document.getElementById('progressBar');
    const pages = Array.from(document.querySelectorAll('.page-wrapper'));

    // localStorage keys
    const LS_PROGRESS_PREFIX = 'manga_progress:'; // + chapter_id -> {scroll, page}
    const LS_LASTREAD_PREFIX = 'manga_last_read:'; // + manga_id -> {chapter_id, chapter_number}

    // ===== Navigation =====
    function goPrev() {
        if (prevChapterId) {
            saveProgressBeforeNavigate();
            setTimeout(() => {
                window.location.href = 'reader.php?chapter_id=' + encodeURIComponent(prevChapterId);
            }, 100);
        }
    }

    function goNext() {
        if (nextChapterId) {
            saveProgressBeforeNavigate();
            setTimeout(() => {
                window.location.href = 'reader.php?chapter_id=' + encodeURIComponent(nextChapterId);
            }, 100);
        }
    }

    // Expose globally for inline onclick handlers
    window.goPrev = goPrev;
    window.goNext = goNext;

    // Chapter jump dropdown
    jumpSelect?.addEventListener('change', (e) => {
        window.location.href = 'reader.php?chapter_id=' + encodeURIComponent(e.target.value);
    });

    // ===== Reader Settings (Width & Layout) =====
    function applyReaderSettings() {
        const width = localStorage.getItem('manga_reader_width') || '800px';
        const layout = localStorage.getItem('manga_reader_layout') || 'webtoon';
        const endCard = document.querySelector('.end-chapter-card');

        if (readerContainer) readerContainer.style.maxWidth = width;
        if (settingWidthSelect) settingWidthSelect.value = width;
        if (settingLayoutSelect) settingLayoutSelect.value = layout;

        if (layout === 'single_page') {
            document.body.classList.add('mode-single-page');
            if (savedSinglePageIndex !== null && savedSinglePageIndex < totalPages) {
                currentPageIndex = savedSinglePageIndex;
            }
            updateSinglePageUI();
        } else {
            document.body.classList.remove('mode-single-page');
            if (endCard) endCard.classList.remove('active-end-card');
        }
    }

    settingWidthSelect?.addEventListener('change', (e) => {
        localStorage.setItem('manga_reader_width', e.target.value);
        applyReaderSettings();
    });

    settingLayoutSelect?.addEventListener('change', (e) => {
        localStorage.setItem('manga_reader_layout', e.target.value);
        applyReaderSettings();
    });

    function updateSinglePageUI() {
        const endCard = document.querySelector('.end-chapter-card');
        pages.forEach((p, idx) => {
            p.classList.toggle('active-page', idx === currentPageIndex);
        });

        if (pageIndicatorText) {
            pageIndicatorText.textContent = `Halaman ${currentPageIndex + 1} / ${totalPages}`;
        }

        if (endCard) {
            if (currentPageIndex === totalPages - 1) {
                endCard.classList.add('active-end-card');
            } else {
                endCard.classList.remove('active-end-card');
            }
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
        saveReadProgress(null, currentPageIndex);
        updateProgressBar();
    }

    function prevSinglePage() {
        if (currentPageIndex > 0) {
            currentPageIndex--;
            updateSinglePageUI();
        } else {
            goPrev();
        }
    }

    function nextSinglePage() {
        if (currentPageIndex < totalPages - 1) {
            currentPageIndex++;
            updateSinglePageUI();
        } else {
            goNext();
        }
    }

    // Expose globally for inline onclick handlers
    window.prevSinglePage = prevSinglePage;
    window.nextSinglePage = nextSinglePage;
    window.handleTapZone = function(direction) {
        const layout = localStorage.getItem('manga_reader_layout') || 'webtoon';
        if (layout === 'single_page') {
            if (direction === 'left') prevSinglePage();
            else nextSinglePage();
        } else {
            if (direction === 'left') window.scrollBy({ top: -400, behavior: 'smooth' });
            else window.scrollBy({ top: 400, behavior: 'smooth' });
        }
    };

    // ===== Progress Persistence (Dual-write) =====
    // Login  -> DB is source of truth, but also write to localStorage (dual-write for responsiveness)
    // Guest  -> localStorage only, no server requests
    function readLocalProgress(chapterId) {
        try {
            const raw = localStorage.getItem(LS_PROGRESS_PREFIX + chapterId);
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    function writeLocalProgress(chapterId, scrollPosition, singlePageIndex) {
        try {
            const existing = readLocalProgress(chapterId) || {};
            const merged = {
                scroll: scrollPosition !== null ? scrollPosition : (existing.scroll ?? null),
                page: singlePageIndex !== null ? singlePageIndex : (existing.page ?? null),
            };
            localStorage.setItem(LS_PROGRESS_PREFIX + chapterId, JSON.stringify(merged));
        } catch (e) { /* localStorage full/disabled, ignore */ }
    }

    function writeLocalLastRead(mangaId, chapterId, chapterNumber) {
        try {
            localStorage.setItem(LS_LASTREAD_PREFIX + mangaId, JSON.stringify({
                chapter_id: chapterId, chapter_number: chapterNumber, at: Date.now(),
            }));
        } catch (e) { /* ignore */ }
    }

    // Mark this chapter as last read (localStorage, all login states)
    writeLocalLastRead(currentMangaId, currentChapterId, currentChapterNumber);

    async function saveReadProgress(scrollPosition, singlePageIndex) {
        // Always save to localStorage first -- instant, no network
        writeLocalProgress(currentChapterId, scrollPosition, singlePageIndex);

        if (!isLoggedIn) return; // guest stops at localStorage

        try {
            await fetch('save_progress.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `chapter_id=${encodeURIComponent(currentChapterId)}` +
                    `&scroll_position=${scrollPosition === null ? '' : scrollPosition}` +
                    `&single_page_index=${singlePageIndex === null ? '' : singlePageIndex}`,
            });
        } catch (err) { /* not critical, ignore */ }
    }
    window.saveReadProgress = saveReadProgress;

    function saveProgressBeforeNavigate() {
        const layout = localStorage.getItem('manga_reader_layout') || 'webtoon';
        let scrollPos = null, pageIdx = null;
        if (layout === 'webtoon') {
            scrollPos = window.scrollY > 100 ? Math.round(window.scrollY) : 0;
        } else {
            pageIdx = currentPageIndex;
        }
        // Write to localStorage first (instant)
        writeLocalProgress(currentChapterId, scrollPos, pageIdx);
        if (!isLoggedIn) return;
        // Use sendBeacon for reliable delivery during navigation
        const body = new URLSearchParams();
        body.append('chapter_id', currentChapterId);
        if (scrollPos !== null) body.append('scroll_position', scrollPos);
        if (pageIdx !== null) body.append('single_page_index', pageIdx);
        navigator.sendBeacon('save_progress.php', body);
    }
    window.saveProgressBeforeNavigate = saveProgressBeforeNavigate;

    // Determine saved position: login uses DB (server-rendered), guest uses localStorage.
    // If login but DB empty (e.g. new device), fallback to localStorage as well.
    const localFallback = readLocalProgress(currentChapterId);
    const savedScrollPosition = dbScrollPosition !== null ? dbScrollPosition : (localFallback ? localFallback.scroll : null);
    const savedSinglePageIndex = dbSinglePageIndex !== null ? dbSinglePageIndex : (localFallback ? localFallback.page : null);

    applyReaderSettings();

    // ===== Reading Progress Bar =====
    function updateProgressBar() {
        if (!progressBar) return;
        const layout = localStorage.getItem('manga_reader_layout') || 'webtoon';
        if (layout === 'single_page') {
            const pct = Math.min(100, Math.round(((currentPageIndex + 1) / totalPages) * 100));
            progressBar.style.width = pct + '%';
        } else {
            const scrollTotal = document.documentElement.scrollHeight - window.innerHeight;
            if (scrollTotal <= 0) { progressBar.style.width = '100%'; return; }
            const percentage = Math.min(100, Math.max(0, (window.scrollY / scrollTotal) * 100));
            progressBar.style.width = percentage + '%';
        }
    }
    window.addEventListener('scroll', updateProgressBar);
    window.addEventListener('resize', updateProgressBar);
    updateProgressBar();

    // ===== Immersive Reading Mode =====
    (function () {
        let lastScrollY = window.scrollY;
        const SCROLL_THRESHOLD = 10;
        const EDGE_ZONE = 120;

        function hideUI() { document.body.classList.add('reader-hide-ui'); }
        function showUI() { document.body.classList.remove('reader-hide-ui'); }
        function toggleUI() { document.body.classList.toggle('reader-hide-ui'); }

        window.addEventListener('scroll', () => {
            const currentScrollY = window.scrollY;
            const delta = currentScrollY - lastScrollY;
            const scrollBottom = document.documentElement.scrollHeight - window.innerHeight - currentScrollY;

            if (currentScrollY < EDGE_ZONE || scrollBottom < EDGE_ZONE) {
                showUI();
            } else if (Math.abs(delta) > SCROLL_THRESHOLD) {
                if (delta > 0) hideUI(); else showUI();
            }

            lastScrollY = currentScrollY;
        }, { passive: true });

        let touchStartX = 0, touchStartY = 0;
        let wasTouchInteraction = false;
        const TAP_MOVE_TOLERANCE = 10;

        readerContainer?.addEventListener('touchstart', (e) => {
            const t = e.touches[0];
            touchStartX = t.clientX;
            touchStartY = t.clientY;
        }, { passive: true });

        readerContainer?.addEventListener('touchend', (e) => {
            if (e.target.closest('button, a, .image-error-card, .single-page-controls')) return;

            const t = e.changedTouches[0];
            const movedX = t.clientX - touchStartX;
            const movedY = Math.abs(t.clientY - touchStartY);
            const absMovedX = Math.abs(movedX);

            // Swipe gesture: horizontal swipe > 50px with less vertical movement
            if (absMovedX > 50 && movedY < absMovedX) {
                if (movedX < 0) {
                    // Swipe left -> Next
                    const layout = localStorage.getItem('manga_reader_layout') || 'webtoon';
                    if (layout === 'single_page') nextSinglePage(); else goNext();
                } else {
                    // Swipe right -> Previous
                    const layout = localStorage.getItem('manga_reader_layout') || 'webtoon';
                    if (layout === 'single_page') prevSinglePage(); else goPrev();
                }
                return;
            }

            // Tap -> Toggle UI
            if (absMovedX < TAP_MOVE_TOLERANCE && movedY < TAP_MOVE_TOLERANCE) {
                wasTouchInteraction = true;
                toggleUI();
                setTimeout(() => { wasTouchInteraction = false; }, 400);
            }
        });

        readerContainer?.addEventListener('click', (e) => {
            if (wasTouchInteraction) return;
            if (e.target.closest('button, a, .image-error-card, .single-page-controls')) return;
            toggleUI();
        });
    })();

    // ===== Keyboard Shortcuts =====
    document.addEventListener('keydown', (e) => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;
        const layout = localStorage.getItem('manga_reader_layout') || 'webtoon';

        if (e.key === 'ArrowRight' || e.key === 'd' || e.key === 'D') {
            if (layout === 'single_page') nextSinglePage(); else goNext();
        } else if (e.key === 'ArrowLeft' || e.key === 'a' || e.key === 'A') {
            if (layout === 'single_page') prevSinglePage(); else goPrev();
        } else if (e.key === '?' || e.key === 'k' || e.key === 'K') {
            const toast = document.getElementById('shortcutToast');
            if (toast) toast.style.display = toast.style.display === 'block' ? 'none' : 'block';
        }
    });

    document.getElementById('shortcutHelpBtn')?.addEventListener('click', () => {
        const toast = document.getElementById('shortcutToast');
        if (toast) toast.style.display = toast.style.display === 'block' ? 'none' : 'block';
    });

    // ===== Image Error Fallback & Retry =====
    window.handleImageError = function(imgEl, pageNum, originalSrc) {
        const wrapper = imgEl.parentElement;
        wrapper.classList.remove('skeleton-shimmer');
        wrapper.style.minHeight = 'auto';

        const errorCard = document.createElement('div');
        errorCard.className = 'image-error-card';
        errorCard.innerHTML = `
            <i class="bi bi-exclamation-octagon-fill text-danger fs-3 mb-2 d-block"></i>
            <div class="small fw-semibold mb-2">Gambar Halaman ${pageNum} Gagal Dimuat</div>
            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="reloadChapterImage(this, '${originalSrc}')">
                <i class="bi bi-arrow-repeat me-1"></i> Muat Ulang Gambar
            </button>
        `;
        imgEl.style.display = 'none';
        wrapper.appendChild(errorCard);
    };

    window.reloadChapterImage = function(btnEl, originalSrc) {
        const errorCard = btnEl.closest('.image-error-card');
        const wrapper = errorCard.parentElement;
        const imgEl = wrapper.querySelector('img');

        errorCard.remove();
        wrapper.classList.add('skeleton-shimmer');
        imgEl.style.display = 'block';
        imgEl.src = originalSrc + '?retry=' + new Date().getTime();
    };

    // ===== Background Preloading Next Chapter Images =====
    if (nextChapterImageUrls && nextChapterImageUrls.length > 0) {
        window.addEventListener('load', () => {
            setTimeout(() => {
                nextChapterImageUrls.forEach(url => { const img = new Image(); img.src = url; });
            }, 1000);
        });
    }

    // ===== Scroll Position Restore (Webtoon Mode) =====
    window.addEventListener('load', () => {
        const layout = localStorage.getItem('manga_reader_layout') || 'webtoon';
        if (layout === 'webtoon' && savedScrollPosition) {
            setTimeout(() => {
                window.scrollTo({ top: savedScrollPosition, behavior: 'smooth' });
            }, 300);
        }
    });

    // ===== Real-time Scroll Position Save (debounced) =====
    let scrollSaveTimeout;
    window.addEventListener('scroll', () => {
        const layout = localStorage.getItem('manga_reader_layout') || 'webtoon';
        if (layout !== 'webtoon') return;

        clearTimeout(scrollSaveTimeout);
        scrollSaveTimeout = setTimeout(() => {
            saveReadProgress(window.scrollY > 100 ? Math.round(window.scrollY) : 0, null);
        }, 400);
    });

    // ===== Save progress on page unload/navigation =====
    function saveProgressOnUnload() {
        saveProgressBeforeNavigate();
    }
    window.addEventListener('pagehide', saveProgressOnUnload);
    window.addEventListener('beforeunload', saveProgressOnUnload);

    // Expose for debugging
    window.Reader = {
        goPrev, goNext, prevSinglePage, nextSinglePage,
        applyReaderSettings, updateProgressBar, saveReadProgress
    };
})();