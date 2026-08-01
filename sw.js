/**
 * Service Worker untuk Manga Reader PWA.
 * Fungsi utama: memungkinkan app di-"install" ke home screen Android dan
 * dibuka dalam mode standalone (tanpa address bar), plus caching ringan
 * untuk asset statis supaya loading lebih cepat.
 *
 * CATATAN: Ini BUKAN untuk offline reading penuh (chapter image tetap perlu
 * internet), hanya mempercepat load shell app (CSS/JS/font) dan bikin app
 * installable sebagai PWA.
 */

const CACHE_NAME = "manga-reader-shell-v1";
const SHELL_ASSETS = [
  // Asset statis yang aman di-cache lama (tidak sering berubah)
  "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css",
  "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css",
  "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const url = new URL(event.request.url);

  // Halaman PHP (index.php, manga.php, reader.php, check_updates.php, dll)
  // SELALU ambil dari network -- ini data dinamis (daftar manga, chapter baru,
  // status baca), tidak boleh di-cache supaya user tidak lihat data basi.
  if (url.pathname.endsWith(".php") || url.pathname === "/") {
    event.respondWith(fetch(event.request));
    return;
  }

  // Asset statis (CSS/JS/font dari CDN): cache-first, biar cepat & hemat kuota.
  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) return cached;
      return fetch(event.request).then((response) => {
        if (response.ok && event.request.method === "GET") {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
        }
        return response;
      });
    })
  );
});