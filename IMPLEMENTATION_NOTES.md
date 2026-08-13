# Catatan Implementasi: Dukungan Multi-Source (Shinigami + Komiku)

## Cara menerapkan

1. **Backup database dulu.**
2. Jalankan `migration_multisource.sql` di phpMyAdmin/mysql client.
3. Timpa file-file berikut ke server (semuanya menggantikan versi lama):
   - `sources/MangaSourceInterface.php` (baru)
   - `sources/ShinigamiSource.php` (baru)
   - `sources/KomikuSource.php` (baru)
   - `sources/SourceRegistry.php` (baru)
   - `sync_functions.php` (ganti total, generic)
   - `crawler.php` (ganti total, source-aware)
   - `crawl.php` (ganti total, loop semua sumber ter-bind)
   - `crawl_all.php` (ganti total, loop semua manga x semua sumber)
   - `cron_update.php` (ganti total)
   - `add_manga.php` (baru)
   - `save_preferred_source.php` (baru)
4. Terapkan patch kecil di `PATCHES.md` ke `index.php` dan `manga.php`.
5. Tes alur lengkap: tambah manga dari Shinigami (harus tetap jalan spt biasa),
   lalu tambah manga dari URL Komiku, lalu coba tambah manga Komiku yang
   judulnya mirip manga Shinigami yang sudah ada (harus muncul halaman
   konfirmasi linking).

## Koreksi dari analisis saya sebelumnya

Sewaktu diskusi awal saya sempat bilang ada kasus "chapter dgn nomor sama
tapi 2 baris berbeda" di Komiku (mis. dua entri "Chapter 152"). Setelah saya
cek ulang data asli lebih teliti: itu **tidak benar** -- tiap nomor chapter di
daftar chapter Komiku cuma muncul SATU KALI. Yang saya lihat sebelumnya adalah
slug URL-nya kadang punya akhiran "-2" (mis. `...-chapter-152-2/`) meski
nomor yang ditampilkan tetap "Chapter 152" -- kemungkinan sisa migrasi/rename
slug di pihak Komiku, bukan chapter duplikat sungguhan. Karena itu parser
`KomikuSource` mengambil chapter_number dari TEKS "Chapter N" (label yang
ditampilkan), bukan dari angka di slug URL, supaya kasus ini otomatis aman.

## Batasan yang perlu kamu tahu

1. **Parser Komiku belum diuji live.** Saya cuma bisa mengambil halaman lewat
   tool yang me-render ke markdown, bukan HTML mentah dgn class/id asli. Jadi
   `KomikuSource.php` saya tulis berbasis pola URL & teks yang stabil (regex
   pada domain `img.komiku.org/upload5/...`, teks "Chapter N", posisi section
   "Daftar Chapter"/"Baca Online"/"Komentar"), BUKAN class CSS spesifik.
   Ini lebih tahan-perubahan tapi tetap wajib kamu tes di server asli. Kalau
   ada yang gagal parse, titik yg ditandai komentar "SESUAIKAN" di file itu
   tempat pertama untuk dicek.

2. **Proteksi anti-bot.** `cron_update.php` versi sebelumnya sudah perlu
   Playwright karena situsmu sendiri kena JS-challenge InfinityFree. Kalau
   Komiku juga punya proteksi serupa, `curl` biasa di `KomikuSource::httpGet()`
   bisa gagal / diblokir. Belum saya tangani fallback browser otomatis untuk
   ini -- kalau ternyata perlu, kabari saya, saya bantu tambahkan.

3. **"Prioritas sumber" (`preferred_source`) v1 ini bekerja dengan cara:**
   sumber yang jadi prioritas disinkron LEBIH DULU saat proses sync jalan,
   jadi kalau dua sumber sama-sama baru menemukan chapter dengan nomor sama
   di run yang sama, sumber prioritas yang "menang" memiliki baris itu
   (`chapters.chapter_id`, gambar, dst). Ini BELUM termasuk fitur "ganti
   pemilik chapter yang sudah tersimpan" (mis. kalau gambar dari sumber A
   ternyata rusak, otomatis pindah ke sumber B) -- itu bisa ditambahkan
   sebagai fitur "coba sumber lain" per-chapter kalau nanti dibutuhkan.

4. **Kompatibilitas mundur:** `reader.php` tidak perlu diubah sama sekali.
   Chapter lama (dari Shinigami sebelum migrasi) tetap simpan
   `base_url + image_path + filename` terpisah; chapter baru (dari sumber
   manapun setelah refactor ini) simpan `filename` sbg URL gambar penuh dgn
   `base_url`/`image_path` kosong. Konkatenasi `base_url.image_path.filename`
   tetap menghasilkan URL yang benar di kedua kasus, jadi `reader.php` apa
   adanya sudah kompatibel dengan keduanya.

5. **Menambah sumber ke-3 di masa depan:** buat `sources/NamaSource.php` yang
   implement `MangaSourceInterface`, daftarkan 1 baris di
   `sources/SourceRegistry.php::getAllSources()`. Tidak ada file lain yang
   perlu disentuh.
