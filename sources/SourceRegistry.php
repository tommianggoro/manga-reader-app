<?php
require_once __DIR__ . "/MangaSourceInterface.php";
require_once __DIR__ . "/ShinigamiSource.php";
require_once __DIR__ . "/KomikuSource.php";
require_once __DIR__ . "/CosmicScansSource.php";
require_once __DIR__ . "/AsuraScansSource.php";

/**
 * Daftar pusat semua sumber manga yang aktif di aplikasi ini.
 *
 * UNTUK MENAMBAH SUMBER BARU DI MASA DEPAN:
 *   1. Buat class baru di folder ini yang implement MangaSourceInterface.
 *   2. Tambahkan 1 baris ke array di getAllSources() di bawah.
 *   Tidak ada file lain yang perlu disentuh -- crawler.php, cron_update.php,
 *   crawl_all.php, sync_functions.php semuanya generic lewat interface ini.
 *
 * URUTAN PENTING untuk detectSourceFromInput(): sumber yang mendeteksi lewat
 * pola URL/domain spesifik (mis. Komiku, CosmicScans, AsuraScans) harus ditaruh
 * SEBELUM sumber fallback (Shinigami, yang menerima sembarang string bukan-URL
 * sbg manga_id polos).
 */
function getAllSources(): array
{
    static $sources = null;
    if ($sources === null) {
        $sources = [
            'komiku' => new KomikuSource(),
            'cosmicscans' => new CosmicScansSource(),
            'asura' => new AsuraScansSource(),
            'shngm'  => new ShinigamiSource(), // fallback, harus di urutan TERAKHIR
        ];
    }
    return $sources;
}

function getSource(string $key): ?MangaSourceInterface
{
    return getAllSources()[$key] ?? null;
}

/**
 * Deteksi sumber mana yang cocok menangani input mentah dari form tambah manga.
 * Kembalikan ['source' => key, 'ref' => source_ref, 'adapter' => instance] atau null.
 */
function detectSourceFromInput(string $input): ?array
{
    foreach (getAllSources() as $key => $adapter) {
        $ref = $adapter->detect($input);
        if ($ref !== null) {
            return ['source' => $key, 'ref' => $ref, 'adapter' => $adapter];
        }
    }
    return null;
}

/** Urutkan daftar source key sesuai preferensi manga (kalau ada) -- preferensi jalan duluan. */
function orderSourcesByPreference(array $sourceKeys, ?string $preferredSource): array
{
    if (!$preferredSource) return $sourceKeys;
    usort($sourceKeys, function ($a, $b) use ($preferredSource) {
        if ($a === $preferredSource) return -1;
        if ($b === $preferredSource) return 1;
        return 0;
    });
    return $sourceKeys;
}