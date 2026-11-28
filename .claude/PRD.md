# Product Requirements Document (PRD) — Manga Reader App

> **Project**: Personal Manga Collection & Reader  
> **Stack**: PHP 8.3, MySQL/MariaDB, Bootstrap 5, Vanilla JS (ES6)  
> **Hosting**: InfinityFree (shared hosting) + GitHub Actions (cron)  
> **Last Updated**: 2026-11-28

---

## 1. Ringkasan Produk

**Manga Reader App** adalah aplikasi web self-hosted untuk mengelola koleksi manga pribadi, mencatat progres baca, dan menerima notifikasi chapter baru. Dibangun untuk dijalankan di shared hosting (InfinityFree) dengan biaya nol, memanfaatkan GitHub Actions untuk scheduled sync & cron job.

**Target User**: Pembaca manga individu / kelompok kecil yang ingin koleksi terpusat, sinkronisasi multi-sumber, dan tracking progres baca tanpa bergantung platform komersial.

---

## 2. Arsitektur & Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER FACING PAGES                         │
├─────────────────────────────────────────────────────────────────┤
│  index.php      ──▶  Home: Grid/List koleksi, filter, search    │
│  manga.php      ──▶  Detail manga, daftar chapter, progres      │
│  reader.php     ──▶  Baca chapter (webtoon / single-page mode)  │
│  discover.php   ──▶  Pencarian publik multi-sumber              │
│  stats.php      ──▶  Dashboard statistik bacaan (login required)│
│  profile.php    ──▶  Profil, avatar, link Telegram              │
│  login/register ──▶  Auth (session-based, rate-limited)         │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      CORE LIBRARIES                              │
├─────────────────────────────────────────────────────────────────┤
│  config.php       ──▶  DB, session, auth helpers, constants     │
│  sync_functions.php ──▶  Generic multi-source sync logic        │
│  sources/         ──▶  Adapter pattern: Shinigami, Komiku,      │
│                      CosmicScans, AsuraScans (MangaSourceInterface)│
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      AUTOMATION (GitHub Actions)                │
├─────────────────────────────────────────────────────────────────┤
│  manga_cron.yml       ──▶  Daily 23:00 WIB: sync all manga      │
│  manga_trending_cron.yml ──▶  Daily 23:15 WIB: refresh trending │
│  telegram_poller.yml  ──▶  Every 5 min: bot commands            │
│  keepalive.yml        ──▶  Weekly: prevent 60-day disable       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      DATABASE (MySQL)                           │
├─────────────────────────────────────────────────────────────────┤
│  mangas, manga_sources, chapters, chapter_images                │
│  chapter_sync_cursor, manga_sync_checkpoint                     │
│  users, user_manga_state, reading_progress                      │
│  trending_manga, login_attempts, registration_attempts          │
│  telegram_bot_state, user_tags, manga_user_tags                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Fitur Utama & User Flow

### 3.1 Koleksi Manga (index.php)
- **Grid & List View** — toggle persist via localStorage
- **Search & Filter** — real-time: judul, ID, pengarang, genre, favorit, custom tag
- **Batch Manage (Admin)** — multi-select + hapus massal dengan konfirmasi modal
- **Stats Bar** — total manga, chapter, favorit, update mingguan
- **Auto-update Background** — polling 45s ke `check_updates.php`, toast notifikasi chapter baru tanpa reload
- **PWA Ready** — manifest, service worker, installable

### 3.2 Detail Manga (manga.php)
- **Info Lengkap** — cover, judul alternatif, author/artist, genre, rating, deskripsi
- **Multi-source Binding** — 1 manga bisa terikat ke beberapa sumber (Shinigami, Komiku, CosmicScans, AsuraScans)
- **Preferred Source** — admin pilih sumber prioritas untuk sync
- **Reading Progress** — ringkasan % dibaca, filter chapter (sudah/belum/semua)
- **Custom Tags** — user buat tag sendiri (warna custom), assign ke manga, filter di index
- **Resume Button** — lanjut baca dari chapter terakhir (DB untuk login, localStorage untuk guest)

### 3.3 Reader (reader.php)
- **Dual Layout Mode** — Webtoon (vertical scroll) / Single Page (tap/keyboard nav)
- **Immersive UI** — auto-hide topbar & floating nav saat scroll, tap untuk toggle
- **Progress Persistence** — dual-write: localStorage (instant) + DB via `save_progress.php` (login)
- **Navigation** — floating bar (prev/next, dropdown jump, keyboard shortcuts), tap zones (desktop)
- **Image Error Handling** — retry button per gambar
- **Preload Next Chapter** — background fetch gambar chapter berikutnya
- **Guest Notice** — banner progres hanya lokal, CTA login untuk sync cross-device

### 3.4 Temukan Manga (discover.php)
- **Multi-source Search** — query gabungan ke semua adapter yang support `search()`
- **Deduplication** — hasil digabung, duplikat dibuang berdasarkan judul/cover
- **Infinite Scroll** — load more per sumber yang masih punya hasil
- **Direct Add** — tombol "Tambah ke Koleksi" langsung ke `add_manga.php` dengan source_ref

### 3.5 Statistik (stats.php) — *Login Required*
- **Overview** — total chapter, manga, estimasi waktu, velocity (ch/minggu)
- **Streak** — current & longest streak (berbasis `reading_progress.updated_at`)
- **Genre Favorit** — weighted by chapter read per manga, progress bar visual
- **Top Manga** — paling banyak chapter dibaca
- **Currently Reading** — manga dengan progres < 100%
- **Activity Heatmap** — 30 hari grid (GitHub-style)
- **Monthly/Yearly Recap** — bar chart chapters & manga per bulan/tahun

### 3.6 Autentikasi & Profil
- **Session-based** — `config.php` helpers: `requireAuth()`, `optionalAuth()`, `requireAdmin()`
- **Rate Limiting** — login (5 attempts/15min lockout), registrasi (3/jam per IP)
- **Profil** — username, avatar upload (2MB, dicebear fallback), password change
- **Telegram Link** — generate code, link chat ID, enable/disable notifikasi

### 3.7 Admin Features
- **Manual Add Manga** — via manga_id atau URL (auto-detect source)
- **Crawl Single** — `crawl.php?manga_id=X` (per chapter step, UI progress)
- **Crawl All** — `crawl_all.php` batch update semua manga
- **Backup/Restore** — Export/Import JSON (manga, chapter, user state, progress)
- **Manage Admin** — promosi/demosi user

---

## 4. Multi-Source Sync Architecture (Core Innovation)

### 4.1 Adapter Pattern (`sources/MangaSourceInterface.php`)
```php
interface MangaSourceInterface {
    getKey(): string;              // "shngm", "komiku", "cosmicscans", "asura"
    getLabel(): string;            // Display name
    detect(string $input): ?string;// Return source_ref or null
    fetchMangaInfo(string $ref): array;
    fetchChapterStep(string $sourceRef, string $chapterRef): array;
    search(string $query): array;
    getTrending(int $limit): array;
    getRateLimitDelayMs(): int;    // Per-source politeness delay
}
```
**Adding New Source** = 1 class + 1 line di `SourceRegistry.php`. Zero changes to sync logic.

### 4.2 Deduplication Cross-Source
- **manga_sources** table: `UNIQUE(manga_id, source)` — 1 manga bisa punya 4 binding
- **chapters** table: `UNIQUE(manga_id, chapter_number)` — chapter number WINS, first source to insert owns `chapter_id`
- **Sync Chain** — tiap sumber jalan-mundur lewat `prev_source_chapter_ref` sendiri, independen

### 4.3 Sync Functions (`sync_functions.php`)
| Function | Purpose |
|----------|---------|
| `genericSyncChapterStep()` | 1 chapter, 1 source, resume via `chapter_sync_cursor` |
| `genericSyncSourceFull()` | Full manga dari 1 sumber, support `maxChaptersPerRun` (batching) |
| `genericSyncMangaAllSources()` | Fan-out ke semua binding, ordered by `preferred_source` |
| `genericSyncMangaAllSourcesParallel()` | `pcntl_fork` per source (Linux only) |
| `manga_sync_checkpoint` | Resume mid-fanout across sources |

### 4.4 Cron Entry Points
- **`cron_update.php`** — loop all manga → `genericSyncMangaAllSources()` → trigger `cron_notify_users.php` async
- **`cron_trending.php`** — daily refresh `trending_manga` table (3 per source)
- **`cron_notify_users.php`** — per-user Telegram notif chapter baru (dedup via `last_notified_chapter_number`)

---

## 5. Database Schema Highlights

| Table | Key Design |
|-------|------------|
| `mangas` | `manga_id` (slug from title) PK, metadata + `preferred_source` |
| `manga_sources` | Composite PK `(manga_id, source)`, `source_ref` per source |
| `chapters` | `chapter_id` = `source:source_ref`, `UNIQUE(manga_id, chapter_number)` for dedup |
| `chapter_sync_cursor` | Per-source resume pointer `(source, source_chapter_ref)` |
| `manga_sync_checkpoint` | Mid-fanout resume state JSON |
| `reading_progress` | `(user_id, chapter_id)` unique, `scroll_position` + `single_page_index` |
| `user_manga_state` | Favorite, last read, last notified chapter |
| `user_tags` / `manga_user_tags` | Custom tagging system (many-to-many) |
| `trending_manga` | Daily cache from `getTrending()` |

---

## 6. GitHub Actions Automation

| Workflow | Schedule (UTC) | WIB | Purpose |
|----------|----------------|-----|---------|
| `manga_cron.yml` | 16:00 | 23:00 | Sync all manga (Playwright bypass Cloudflare) |
| `manga_trending_cron.yml` | 16:15 | 23:15 | Refresh trending cache |
| `telegram_poller.yml` | */5 * * * * | — | Bot commands (/sync_now, /status, /enable_cron) |
| `keepalive.yml` | Sun 06:00 | 13:00 | Weekly commit to prevent 60-day disable |

**Secrets Required**: `CRON_SECRET_KEY`, `SITE_URL`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `TELEGRAM_ALLOWED_USERS`, `GH_PAT` (optional)

---

## 7. Telegram Bot Commands

| Command | Action |
|---------|--------|
| `/sync_now` / `/sync` | Trigger manga + trending sync |
| `/sync_manga` | Trigger manga sync only |
| `/sync_trending` | Trigger trending sync only |
| `/enable_cron` | Enable scheduled workflows |
| `/disable_cron` | Disable scheduled workflows |
| `/status` | Check workflow state |
| `/help` | Show command list |

---

## 8. Non-Functional Requirements

| Aspect | Detail |
|--------|--------|
| **Performance** | Shared hosting friendly: chunked sync, rate limit per source, trending cached daily |
| **Reliability** | Resume via cursor/checkpoint, per-source/per-chapter error isolation |
| **Offline-First Reader** | localStorage instant save, DB async (sendBeacon on unload) |
| **Security** | Rate limit login/registrasi, admin-only endpoints, CSRF via same-site cookies |
| **PWA** | Installable, service worker, offline shell |
| **Accessibility** | Semantic HTML, ARIA labels, focus management, color contrast |

---

## 9. Deployment Checklist

- [ ] `.env` configured (DB, CRON_SECRET_KEY, Telegram)
- [ ] `schema.sql` imported (fresh) or migrations applied (existing)
- [ ] First user registered → manual `UPDATE users SET is_admin=1 WHERE username='...'`
- [ ] GitHub secrets added
- [ ] Workflows enabled (Actions tab → enable if disabled)
- [ ] Test `cron_update.php?key=...` via browser
- [ ] Test Telegram bot `/status`

---

## 10. Future Enhancements (Backlog)

- [ ] **Multi-language** (i18n) — Indonesian/English
- [ ] **Reading Lists** — "Plan to Read", "Dropped", custom lists
- [ ] **Chapter Comments/Notes** — personal notes per chapter
- [ ] **Web Push Notifications** — VAPID, service worker push
- [ ] **API Tokens** — personal access token untuk external integration
- [ ] **Cover Art Proxy** — cache/resize cover images locally
- [ ] **Dark/Light Auto** — `prefers-color-scheme` + manual override
- [ ] **Keyboard Shortcuts Help Modal** — visual cheatsheet di reader
- [ ] **Export to CBZ/EPUB** — offline reading archive

---

## 11. File Map (Key Entry Points)

```
├── index.php              # Home: koleksi grid/list
├── manga.php              # Detail manga + chapter list
├── reader.php             # Baca chapter (immersive)
├── discover.php           # Public search multi-source
├── stats.php              # Dashboard statistik (auth)
├── profile.php            # Profil, avatar, telegram link
├── login.php / register.php / logout.php
├── config.php             # DB, auth, constants, helpers
├── sync_functions.php     # Core sync logic (generic)
├── sources/
│   ├── MangaSourceInterface.php
│   ├── SourceRegistry.php
│   ├── ShinigamiSource.php
│   ├── KomikuSource.php
│   ├── CosmicScansSource.php
│   └── AsuraScansSource.php
├── cron_update.php        # Daily manga sync (web/CLI)
├── cron_trending.php      # Daily trending refresh
├── cron_notify_users.php  # Per-user Telegram notif
├── telegram_poller.php    # GH Actions bot poller
├── .github/workflows/
│   ├── manga_cron.yml
│   ├── manga_trending_cron.yml
│   ├── telegram_poller.yml
│   └── keepalive.yml
└── schema.sql             # Full schema (fresh install)
```

---

*Generated by analyzing the codebase. Update this PRD when architecture or features change.*