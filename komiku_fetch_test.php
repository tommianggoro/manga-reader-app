<?php
/**
 * Skrip diagnostik SEKALI PAKAI: fetch 1 URL Komiku persis seperti cara
 * KomikuSource melakukannya, lalu tampilkan ringkasan supaya bisa dibandingkan
 * dengan hasil "Save As" dari browser kamu. Berguna kalau parsing gagal tapi
 * kamu tidak yakin apakah itu karena struktur halaman berubah, atau karena
 * respons yang diterima SERVER (bukan browser kamu) ternyata bukan halaman
 * asli -- mis. diblokir proteksi anti-bot / IP hosting kena block.
 *
 * CARA PAKAI:
 *   1. Upload ke folder yang sama dengan config.php.
 *   2. Buka: https://situskamu.com/komiku_fetch_test.php?url=https://komiku.org/manga/xxx/
 *   3. Baca ringkasan. Kalau "Mengandung Daftar_Chapter/mangaData" = TIDAK,
 *      atau indikasi anti-bot = YA, berarti curl server kamu diblokir --
 *      bukan masalah di kode parser.
 *   4. HAPUS file ini dari server setelah selesai dipakai.
 */
require_once "config.php";
requireAuth();

$url = $_GET["url"] ?? "";
if (!$url || !preg_match('#^https://(?:www\.)?komiku\.org/#i', $url)) {
    die("Kasih parameter ?url= yang valid, contoh: ?url=https://komiku.org/manga/xxx/");
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 25);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept-Language: id-ID,id;q=0.9,en;q=0.8"]);
$body = curl_exec($ch);
$info = curl_getinfo($ch);
$err = curl_error($ch);
curl_close($ch);

$body = $body ?: "";
$dumpPath = sys_get_temp_dir() . "/komiku_fetch_test_" . time() . ".html";
@file_put_contents($dumpPath, $body);

$hasChapterTable = str_contains($body, "Daftar_Chapter") || str_contains($body, "daftarChapter");
$hasMangaData = str_contains($body, "mangaData");
$looksBlocked = (bool) preg_match('/cloudflare|captcha|checking your browser|just a moment|access denied/i', $body);

$textSnippet = preg_replace('#<script.*?</script>#is', ' ', $body);
$textSnippet = preg_replace('#<style.*?</style>#is', ' ', (string) $textSnippet);
$textSnippet = trim(preg_replace('/\s+/', ' ', strip_tags((string) $textSnippet)));
$textSnippet = mb_substr($textSnippet, 0, 400);

header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Komiku Fetch Test</title>
<style>
    body { font-family: monospace; background: #10131a; color: #eae7e0; padding: 2rem; line-height: 1.6; }
    pre { background: #171b26; padding: 1rem; border-radius: 8px; overflow: auto; white-space: pre-wrap; word-break: break-all; }
    .ok { color: #4caf50; font-weight: 700; }
    .bad { color: #e05252; font-weight: 700; }
    code { background: #202636; padding: 0.1rem 0.4rem; border-radius: 4px; }
</style>
</head>
<body>
<h2>Hasil Fetch: <?= htmlspecialchars($url) ?></h2>
<p>HTTP Code: <strong><?= (int) ($info['http_code'] ?? 0) ?></strong> | Ukuran respons: <?= strlen($body) ?> bytes | cURL Error: <?= $err ? htmlspecialchars($err) : '-' ?></p>

<p>Mengandung tabel "Daftar_Chapter" / "daftarChapter":
    <span class="<?= $hasChapterTable ? 'ok' : 'bad' ?>"><?= $hasChapterTable ? 'YA' : 'TIDAK' ?></span>
</p>
<p>Mengandung script "mangaData":
    <span class="<?= $hasMangaData ? 'ok' : 'bad' ?>"><?= $hasMangaData ? 'YA' : 'TIDAK' ?></span>
</p>
<p>Indikasi proteksi anti-bot (cloudflare/captcha/dsb):
    <span class="<?= $looksBlocked ? 'bad' : 'ok' ?>"><?= $looksBlocked ? 'YA -- KEMUNGKINAN DIBLOKIR' : 'Tidak terdeteksi' ?></span>
</p>

<h3>400 karakter pertama teks halaman (tag &amp; script dibuang):</h3>
<pre><?= htmlspecialchars($textSnippet) ?></pre>

<p>Raw HTML lengkap disimpan sementara di: <code><?= htmlspecialchars($dumpPath) ?></code></p>
<p style="color:#7c8194">⚠️ Hapus file <code>komiku_fetch_test.php</code> ini dari server setelah selesai dipakai.</p>
</body>
</html>
