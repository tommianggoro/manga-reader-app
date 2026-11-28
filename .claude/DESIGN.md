# Design System — Manga Reader App

> **Design language**: Warm-dark editorial, "paper meets night mode"
> **Last Updated**: 2026-11-28

---

## 1. Design Principles

| Principle | What it means in practice |
|-----------|---------------------------|
| **Content-first** | Cover art & chapter text are the heroes. Chrome recedes; UI auto-hides while reading. |
| **Warm, not cold** | Dark background is warm charcoal (`#10131a`), not pure black. Accent is amber/orange, never blue. |
| **Two-speed UI** | Browsing = dense & informative. Reading = immersive & minimal. Never mix the two. |
| **Instant feedback** | Skeleton shimmer, flash-on-update, optimistic UI. Every async action shows state immediately. |
| **Graceful degradation** | Guest works (localStorage). No-JS still renders server-side. Image fails → retry card, not blank box. |
| **Thumb-first mobile** | Tap zones, floating bottom nav, swipe gestures, ≥44px targets. |

---

## 2. Color System

### 2.1 Dark Theme (default)

```css
:root, [data-bs-theme="dark"] {
  /* Surfaces — warm charcoal ramp */
  --bs-body-bg:        #10131a;  /* page background */
  --bs-secondary-bg:   #171b26;  /* cards, panels */
  --bs-tertiary-bg:    #202636;  /* inputs, chips, hover wells */
  --bs-border-color:   #242938;  /* hairlines */

  /* Text */
  --bs-body-color:     #eae7e0;  /* warm off-white, never pure #fff */

  /* Brand accent — amber */
  --bs-primary:        #f2a541;
  --bs-primary-rgb:    242, 165, 65;
  --bs-link-color:     #f2a541;
  --bs-link-hover-color: #f7bc70;

  /* Reader-only surfaces (darker than app chrome) */
  --topbar-bg:         #14171f;
  --topbar-border:     #2a2f3d;

  /* Skeleton shimmer */
  --shimmer-bg-1:      #1f2533;
  --shimmer-bg-2:      #2b3347;
}
```

### 2.2 Light Theme

```css
[data-bs-theme="light"] {
  --bs-body-bg:        #f5f6f8;
  --bs-secondary-bg:   #ffffff;
  --bs-tertiary-bg:    #f0f2f5;
  --bs-border-color:   #e0e4eb;
  --bs-body-color:     #212529;

  --bs-primary:        #e08b18;  /* darker amber for contrast on white */
  --bs-primary-rgb:    224, 139, 24;
  --bs-link-hover-color: #c4760e;

  --shimmer-bg-1:      #e9ecef;
  --shimmer-bg-2:      #f8f9fa;
}
```

> **Reader light theme is deliberately gray** (`#e5e7eb`) rather than white — a pure-white page behind manga art causes glare and flattens contrast.

### 2.3 Semantic Colors (Bootstrap defaults, used sparingly)

| Token | Use |
|-------|-----|
| `text-bg-primary` | "Baru" badge, active chip |
| `text-bg-secondary` | Source badge, neutral chip |
| `text-success` | Sync success, read progress ≥ 100% |
| `text-warning` | Star rating, error-notice for partial source failure |
| `text-danger` | Hard errors, delete actions |
| `text-info` | Monthly recap header |

**Rule**: Never introduce a new hue. If a new state is needed, reach for opacity/tint of an existing token.

---

## 3. Typography

### 3.1 Font Stack

| Role | Family | Weights | Where |
|------|--------|---------|-------|
| **Display / Brand** | `Bitter` (serif) | 600, 700 | Page titles, section headers, modal titles, brand wordmark |
| **Body / UI** | `Inter` (sans) | 400, 500, 600, 700 | Everything else |

```html
<link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
```

```css
body { font-family: 'Inter', system-ui, sans-serif; }
.brand-font { font-family: 'Bitter', Georgia, serif; }
```

**Why serif for headings?** Reading app → editorial, library, "book" feeling. Serif headings + sans body is the classic long-form pairing and keeps the app from looking like a generic dashboard.

### 3.2 Scale

| Element | Class | Size |
|---------|-------|------|
| Page title | `h1.brand-font.h4` | 1.5rem |
| Section header | `h5.brand-font` / `h6.fw-bold` | 1.25rem / 1rem |
| Card title | `.fw-semibold.small` | 0.875rem |
| Body / meta | `.small.text-secondary` | 0.875rem, muted |
| Chip / badge | `.source-tag`, `.source-badge` | 0.62–0.68rem |
| Stat value | `.stat-value` | 1.75–2rem, tight |

**Rules**
- Never go below **0.62rem** (10px) — smallest used is the source tag.
- Titles use `text-truncate` + `title` attribute, never wrap to 3 lines in a card.
- Numbers in stats use `fw-bold` and tabular alignment where compared vertically.

---

## 4. Spacing, Radius, Elevation

### 4.1 Spacing
Bootstrap's 4px scale (`.p-2`, `.g-3`, `.mb-3`…). Project-specific habits:

| Context | Value |
|---------|-------|
| Page container padding | `py-4`, max-width `1000px` (content pages) |
| Card inner padding | `p-2` (grid cards) / `p-3` (panels) |
| Section gap | `mb-3` / `mb-4` |
| Chip gap | `gap-1` / `gap-2` |
| Reader nav bar inset | `1rem` from edges |

### 4.2 Radius

| Element | Radius |
|---------|--------|
| Cards, panels | `12px` |
| Cover images | `0.75rem` (12px) |
| Inputs, buttons | Bootstrap default (6px) |
| Chips, pills | `50rem` (full pill) |
| Circular icon buttons | `50%` |

### 4.3 Elevation
Deliberately **flat**. Only three shadows exist:

```css
/* Cover art lift */
.cover-img-wrap { box-shadow: 0 4px 15px rgba(0,0,0,0.2); }

/* Hover lift on result cards */
.result-card:hover { transform: translateY(-2px); border-color: var(--bs-primary); }

/* Floating reader nav */
.reader-nav { box-shadow: 0 4px 24px rgba(0,0,0,0.35); }
```

Borders, not shadows, do the separating work. Hover = border color change + 2px lift. That's it.

---

## 5. Components

### 5.1 Manga Card (Grid)
```
┌─────────────────┐
│                 │  ← cover, aspect-ratio 2/3, object-fit: cover
│     COVER       │    skeleton-shimmer while loading, fade-in on load
│                 │
├─────────────────┤
│ TITLE           │  fw-semibold small, text-truncate
│ Ch. 123 · ★ 8.5 │  small text-secondary
│ [❤] [⋯]         │  actions: favorite toggle, menu
└─────────────────┘
```
- `background: var(--bs-secondary-bg)`, `border: 1px solid var(--bs-border-color)`, `border-radius: 12px`
- Whole card is a link; actions are `position-absolute` and stop propagation.
- Favorite state: heart icon filled + `text-danger`.

### 5.2 Manga Row (List View)
```
┌────┬──────────────────────────────────────────────┬──────────┐
│ 60 │ Title                                        │ Ch. 123  │
│ px │ Alternative title · Genres                   │ 2 hari   │
│    │ [src1] [src2] [tag]                          │ [Baca]   │
└────┴──────────────────────────────────────────────┴──────────┘
```
- Cover 60×90, rounded 8px.
- Chapter badges (up to 3 recent) shown as pills on the right.
- `just-updated` class → brief primary border flash + `chapter-badge.just-flashed`.

### 5.3 Source Tag / Chip
```css
.source-tag {
  font-size: 0.62rem; font-weight: 600;
  background: var(--bs-tertiary-bg);
  color: var(--bs-primary);
  border: 1px solid var(--bs-border-color);
  padding: 0.15rem 0.5rem; border-radius: 50rem;
  display: inline-flex; align-items: center; white-space: nowrap;
}
```
Amber text on neutral well = "this is metadata, not an action". Custom user tags reuse the same shape with a user-chosen color as `color` and 12% alpha background.

### 5.4 Buttons

| Variant | Use |
|---------|-----|
| `.btn-primary` | Primary CTA: "Tambah ke Koleksi", "Masuk", "Simpan" |
| `.btn-outline-secondary` | Secondary/neutral: "Muat Lebih Banyak", "Kembali" |
| `.btn-outline-danger` | Destructive but reversible: "Kembalikan ke Foto Default" |
| `.btn-secondary` (disabled) | Blocked state: "Tidak ada sumber" |

**Rules**
- Buttons carry an icon + label; icon-only allowed only for icon buttons (`theme-toggle-btn`, `btn-close`).
- Loading state: disable + `spinner-border spinner-border-sm` + text ("Memproses...", "Menghapus..."), always restore original HTML in `finally`.
- Minimum target 38px (icon) / 44px (mobile primary).

### 5.5 Icon Button (Theme Toggle / Header Action)
```css
.theme-toggle-btn {
  width: 38px; height: 38px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--bs-border-color);
  background: var(--bs-secondary-bg);
  color: var(--bs-body-color);
  transition: all 0.2s ease;
}
.theme-toggle-btn:hover { border-color: var(--bs-primary); color: var(--bs-primary); }
@media (max-width: 576px) { .theme-toggle-btn { width: 34px; height: 34px; } }
```

### 5.6 Skeleton Loading
```css
.skeleton-shimmer {
  background: linear-gradient(90deg,
    var(--shimmer-bg-1) 25%, var(--shimmer-bg-2) 50%, var(--shimmer-bg-1) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
```
- Applied to cover wrappers, not to text lines (text arrives with the response anyway).
- 8 skeleton cards on search; cleared the moment results render.

### 5.7 Toast
Single shared toast element `#newChapterToast`, `delay: 6000`, content:
```
<i class="bi bi-stars me-1"></i> 3 manga punya chapter baru!
```
Used by polling on `index.php` (manga count) and `manga.php` (chapter count). Never use `alert()` for success paths.

### 5.8 Modal
- `modal-dialog-centered modal-sm` for the login modal.
- Header border removed (`border-0 pb-0`), close button only.
- Icon at top in `var(--bs-primary)`, size `2.2rem`.
- Inline error box: `.alert.alert-danger.py-2.small.d-none` toggled by class.
- **Login is inline everywhere** — no page navigation. On success → `window.location.reload()`.

### 5.9 Alert (inline form feedback)
`.alert.alert-danger.py-2.small` / `.alert.alert-success.py-2.small`. Rendered server-side in `profile.php`, toggled client-side in the login modal. Never a toast for form validation — always in-place near the field.

---

## 6. Reader UI (the most opinionated surface)

### 6.1 Two Layouts

| Mode | Behavior | Persistence |
|------|----------|-------------|
| **Webtoon** (default) | Vertical scroll, images stacked, scroll position saved | `localStorage.manga_reader_layout = "webtoon"` |
| **Single Page** | One image at a time, tap/swipe/keyboard to advance | `localStorage.manga_reader_layout = "single_page"` |

### 6.2 Immersive Chrome
- Topbar (`--topbar-bg`) + floating bottom nav are **auto-hidden** while scrolling down, revealed on scroll up or tap.
- `toggleUI()` flips a class on `<body>`; all chrome transitions `0.25s ease`.
- Desktop: clicking the image area toggles chrome. Mobile: tap does the same, but horizontal swipe > 50px navigates instead.

### 6.3 Progress Bar
```css
.reading-progress-bar {
  position: fixed; top: 0; left: 0; width: 0%; height: 4px;
  background: linear-gradient(90deg, var(--bs-primary), #f7bc70);
  z-index: 1060;
}
```
Scroll-driven, no animation delay — it must track the finger exactly.

### 6.4 Floating Navigation
Bottom-centered pill, `border-radius: 50rem`, `background: var(--bs-secondary-bg)` with border, containing:
`[‹ Prev] [Chapter 12 ▾] [Next ›]`
Chapter dropdown is a native `<select>` styled to match — no custom listbox.

### 6.5 Image Error Card
```
┌────────────────────────────────┐
│  ⚠  Gambar Halaman 5 Gagal     │
│     Dimuat                     │
│  [ ⟳ Muat Ulang Gambar ]       │
└────────────────────────────────┘
```
Replaces the broken `<img>` in place (skeleton removed, min-height collapsed). Retry appends `?retry=<timestamp>` to bust cache.

### 6.6 Guest Notice
Thin banner above the chapter: "Progres baca hanya tersimpan di perangkat ini. Masuk untuk sinkron ke akun." with an inline login link. Uses `text-secondary`, not a warning color — it's informational, not an error.

### 6.7 Keyboard Shortcuts (desktop)
| Key | Action |
|-----|--------|
| `→` / `D` | Next chapter (or next page in single-page) |
| `←` / `A` | Previous |
| `?` / `K` | Toggle shortcut help toast |

Suppressed when focus is inside `INPUT`, `TEXTAREA`, `SELECT`.

---

## 7. Motion

| Interaction | Duration | Easing |
|-------------|----------|--------|
| Theme switch (bg/color) | `0.3s` | ease |
| Icon button hover | `0.2s` | ease |
| Card hover lift | `0.15s` | ease |
| Cover image fade-in | `0.35s` | ease |
| Reader chrome hide/show | `0.25s` | ease |
| Chapter flash (`just-flashed`) | 1.5s then remove | — |
| Card update flash (`just-updated`) | 2.5s then remove | — |
| Skeleton shimmer | 1.5s infinite | linear |

**Rules**
- No motion on page load beyond image fade-in and skeletons.
- No bounce, no spring, no parallax. Motion only confirms state changes.
- All animation is CSS-class-driven; JS only adds/removes classes (plus `setTimeout` for one-shot flashes).

---

## 8. Layout & Responsive

### 8.1 Breakpoints (Bootstrap 5)
| Token | Width | Grid cards |
|-------|-------|-----------|
| xs | <576px | 2 cols |
| sm | ≥576px | 3 cols |
| md | ≥768px | 4 cols |
| lg | ≥992px | 5–6 cols |

Grid cards: `row-cols-2 row-cols-sm-3 row-cols-md-4 g-3`.

### 8.2 Container
`<div class="container py-4" style="max-width: 1000px;">` on content pages. Reader is full-bleed with no max-width.

### 8.3 Header Pattern (content pages)
```
[← Kembali]                    [🌙] [avatar] [→]
```
Left = back link (`text-decoration-none fw-medium`), right = theme toggle + avatar + logout, all 38px circles, `gap-2`.

### 8.4 Sticky/Stats Bar
Stats bar (`Manga · Chapter · Favorit · Update`) sits under the header, chips scroll horizontally on mobile (`overflow-x: auto`, no scrollbar).

---

## 9. Iconography

- **Bootstrap Icons 1.11.3** exclusively. No custom SVG icons except brand logo.
- Sizes: `fs-5` for nav/hero, default for inline, `0.75em` implied inside chips.
- Always paired with text except: theme toggle, close, favorite heart, dropdown caret.
- Semantic choices: `bi-book` manga, `bi-bookmark-star-fill` bookmark, `bi-telegram` Telegram, `bi-stars` new content, `bi-exclamation-octagon-fill` image error, `bi-arrow-repeat` retry.

---

## 10. PWA / App Chrome

```html
<link rel="manifest" href=".../manifest.json">
<meta name="theme-color" content="#10131a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="assets/icons/icon-192.png">
```

| Asset | Size |
|-------|------|
| `icon-192.png` | 192×192 |
| `icon-512.png` | 512×512 |
| `icon-maskable-192/512.png` | Maskable safe-zone variant |
| `apple-touch-icon.png` | 180×180 |
| `favicon.ico` | 32×32 |

**Theme bootstrap runs before paint** — a blocking inline script sets `data-bs-theme` from localStorage so there is no light→dark flash:
```js
(function() {
  const savedTheme = localStorage.getItem('manga_theme') || 'dark';
  document.documentElement.setAttribute('data-bs-theme', savedTheme);
})();
```

---

## 11. Accessibility

| Concern | Implementation |
|---------|----------------|
| Color contrast | `#eae7e0` on `#10131a` ≈ 14:1; amber `#f2a541` on dark ≈ 8:1; light-theme amber darkened to `#e08b18` for ≥4.5:1 on white |
| Focus | Native focus rings kept (never `outline: none`); modal traps focus via Bootstrap |
| Touch targets | ≥38px icon buttons, ≥44px mobile primary actions |
| Labels | Every input has a `<label>`; icon-only buttons have `title` + `aria-hidden` icons |
| Motion | No essential info conveyed by animation alone; flashes are decorative |
| Semantics | `<a>` for navigation, `<button>` for actions — never a clickable `<div>` |
| Alt text | Covers use manga title; decorative icons `aria-hidden` |

---

## 12. Design Tokens Quick Reference

```
COLOR
  bg-page      #10131a      bg-card      #171b26      bg-well      #202636
  border       #242938      text         #eae7e0      text-muted   (secondary)
  accent       #f2a541      accent-hover #f7bc70      accent-soft  rgba(242,165,65,.12)

TYPE
  display      Bitter 600/700      body      Inter 400/500/600/700
  min-size     0.62rem             title     .small / .h6 / .h5 / .h4

SPACE / SHAPE
  radius-card  12px   radius-cover 0.75rem   radius-chip 50rem
  lift         0 4px 15px rgba(0,0,0,.2)     float  0 4px 24px rgba(0,0,0,.35)

MOTION
  fast 0.15s   base 0.2–0.25s   slow 0.3s   shimmer 1.5s infinite
```

---

## 13. Do / Don't

**Do**
- Use `var(--bs-*)` tokens — never hard-code a hex in a new component.
- Pair every async action with a loading state and a restoring `finally`.
- Keep reading view chrome-free; keep browsing view information-dense.
- Use `text-truncate` + `title` for any title in a card.
- Reuse `.source-tag` shape for any new metadata chip.

**Don't**
- Introduce new colors, gradients (except progress bar & shimmer), or shadows.
- Use `alert()` / `confirm()` for anything except irreversible destructive confirmation.
- Put a shadow and a border on the same card — pick one.
- Add motion that isn't confirming a state change.
- Let a card's title wrap past two lines.
- Ship a page without the pre-paint theme script.

---

*Derived from the actual stylesheets and markup in `index.php`, `manga.php`, `reader.php`, `discover.php`, `stats.php`, `profile.php`, and `partials/login_modal.php`. Update when tokens change.*