/**
 * Theme Toggle Logic
 * Shared across all pages
 */

(function() {
    'use strict';

    const THEME_KEY = 'manga_theme';
    const THEME_ATTR = 'data-bs-theme';

    function getInitialTheme() {
        // Check localStorage first
        const saved = localStorage.getItem(THEME_KEY);
        if (saved) return saved;

        // Fallback to system preference
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute(THEME_ATTR, theme);
        localStorage.setItem(THEME_KEY, theme);
        updateThemeIcon(theme);

        // Dispatch event for other scripts (e.g., reader, charts)
        window.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
    }

    function updateThemeIcon(theme) {
        const icon = document.getElementById('themeIcon');
        if (!icon) return;

        icon.className = theme === 'dark' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5';
    }

    function toggleTheme() {
        const current = document.documentElement.getAttribute(THEME_ATTR) || getInitialTheme();
        const next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
    }

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Apply saved theme immediately (also done in pre-paint script, but this handles late loads)
        applyTheme(getInitialTheme());

        // Bind toggle button
        const toggleBtn = document.getElementById('themeToggleBtn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleTheme);
        }

        // Listen for system theme changes (if user hasn't set explicit preference)
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', function(e) {
            if (!localStorage.getItem(THEME_KEY)) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    });

    // Expose for manual use
    window.MangaTheme = {
        get: () => document.documentElement.getAttribute(THEME_ATTR) || getInitialTheme(),
        set: applyTheme,
        toggle: toggleTheme,
        onChange: (callback) => window.addEventListener('themechange', callback)
    };
})();