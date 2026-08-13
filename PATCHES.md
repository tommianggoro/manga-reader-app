# Patch manual: index.php & manga.php

File-file lain (crawler.php, crawl.php, crawl_all.php, cron_update.php,
sync_functions.php, sources/*, add_manga.php, save_preferred_source.php)
sudah lengkap menggantikan versi lama -- tinggal timpa.

Dua file ini TIDAK saya tulis ulang penuh (isinya besar & sebagian besar tidak
berubah) -- cukup terapkan potongan kecil berikut secara manual.

---

## 1. index.php

### a) Form "Tambah Manga" -- ganti target & placeholder

CARI:
```php
    <form class="row g-2 add-form mb-3" action="crawl.php" method="GET" id="addMangaForm">
        <div class="col-12 col-sm">
            <input type="text" name="manga_id" id="mangaIdInput" class="form-control form-control-lg fs-6" placeholder="Tempel manga_id di sini (contoh: solo-leveling) untuk menambah/update..." required>
        </div>
```

GANTI JADI:
```php
    <form class="row g-2 add-form mb-3" action="add_manga.php" method="GET" id="addMangaForm">
        <div class="col-12 col-sm">
            <input type="text" name="manga_id" id="mangaIdInput" class="form-control form-control-lg fs-6" placeholder="Tempel manga_id Shinigami (mis: solo-leveling) ATAU URL Komiku (https://komiku.org/manga/...)  untuk menambah/update..." required>
        </div>
```

Itu saja -- `add_manga.php` yang menangani deteksi sumber, cek manga mirip
(konfirmasi manual), dan redirect ke `crawl.php` seperti alur lama.

### b) (Opsional) Badge sumber di kartu manga

Kalau mau tiap kartu manga menampilkan badge sumber (Shinigami/Komiku/keduanya),
tambahkan query tambahan setelah query `$mangas` di awal file:

```php
$sourcesByManga = [];
$srcRows = $pdo->query("SELECT manga_id, source FROM manga_sources")->fetchAll();
foreach ($srcRows as $row) {
    $sourcesByManga[$row['manga_id']][] = $row['source'];
}
```

Lalu di dalam loop kartu grid/list, tambahkan badge kecil, contoh:
```php
<?php foreach (($sourcesByManga[$m['manga_id']] ?? []) as $src): ?>
    <span class="badge text-bg-dark" style="font-size:.65rem;"><?= htmlspecialchars($src) ?></span>
<?php endforeach; ?>
```
Bagian ini murni kosmetik, tidak wajib untuk fungsi utama.

---

## 2. manga.php

### a) Ambil daftar source binding + preferred_source

CARI baris ini (dekat atas file, setelah query `$manga`):
```php
$stmt = $pdo->prepare("SELECT * FROM chapters WHERE manga_id = :id ORDER BY chapter_number DESC");
```

TAMBAHKAN SEBELUM baris itu:
```php
require_once "sync_functions.php";
$mangaSourceBindings = getMangaSources($pdo, $mangaId); // [['source'=>..,'source_ref'=>..], ...]
$sourceLabelsMap = [];
foreach (getAllSources() as $key => $adapter) $sourceLabelsMap[$key] = $adapter->getLabel();
```

### b) Tampilkan pemilih "Sumber Prioritas" -- HANYA kalau manga ini py >1 sumber

CARI baris tombol "Update / Sync Chapter" di action-buttons-row:
```php
        <a class="btn btn-outline-warning ms-auto" href="crawl.php?manga_id=<?= urlencode($manga['manga_id']) ?>" target="_blank" title="Cek & Sync Chapter Baru">
            <i class="bi bi-arrow-repeat me-1"></i> Update / Sync Chapter
        </a>
    </div>
```

GANTI JADI (menambahkan dropdown source sebelum tombol sync):
```php
        <?php if (count($mangaSourceBindings) > 1): ?>
        <select class="form-select form-select-sm ms-auto" style="max-width: 220px;" id="preferredSourceSelect">
            <option value="">Sumber Prioritas: Otomatis</option>
            <?php foreach ($mangaSourceBindings as $b): ?>
                <option value="<?= htmlspecialchars($b['source']) ?>" <?= ($manga['preferred_source'] ?? '') === $b['source'] ? 'selected' : '' ?>>
                    Prioritas: <?= htmlspecialchars($sourceLabelsMap[$b['source']] ?? $b['source']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <a class="btn btn-outline-warning <?= count($mangaSourceBindings) > 1 ? '' : 'ms-auto' ?>" href="crawl.php?manga_id=<?= urlencode($manga['manga_id']) ?>" target="_blank" title="Cek & Sync Chapter Baru">
            <i class="bi bi-arrow-repeat me-1"></i> Update / Sync Chapter (<?= count($mangaSourceBindings) ?> sumber)
        </a>
    </div>
```

### c) Handler JS untuk simpan preferred_source

Tambahkan di blok `<script>` bawah (dekat handler `favBtn`):
```js
    const preferredSourceSelect = document.getElementById("preferredSourceSelect");
    if (preferredSourceSelect) {
        preferredSourceSelect.addEventListener("change", async () => {
            try {
                await fetch("save_preferred_source.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "manga_id=" + encodeURIComponent(<?= json_encode($mangaId) ?>) +
                          "&source=" + encodeURIComponent(preferredSourceSelect.value),
                });
            } catch (err) { alert("Gagal simpan preferensi sumber: " + err.message); }
        });
    }
```

Selesai -- tidak ada bagian lain di manga.php yang perlu diubah (chapter list,
reader, dsb tetap jalan seperti biasa karena `chapters.chapter_id` &
`base_url`/`image_path`/`filename` tetap kompatibel mundur, lihat catatan di
IMPLEMENTATION_NOTES.md).
