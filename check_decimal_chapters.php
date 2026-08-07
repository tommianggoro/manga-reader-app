<?php
/**
 * check_decimal_chapters.php
 *
 * Skrip diagnostik SEKALI PAKAI untuk mendeteksi chapter yang kena masalah
 * pembulatan/pemotongan angka desimal (mis. chapter "1.1" tersimpan sebagai "1")
 * akibat kolom `chapter_number` masih bertipe INT.
 *
 * Tandanya: dalam satu manga, ada 2+ baris chapter dengan chapter_number yang
 * SAMA tapi chapter_id BERBEDA (karena chapter_id dari API selalu unik, row
 * tidak saling menimpa -- hanya nomornya yang kelihatan sama/bentrok).
 *
 * CARA PAKAI:
 *   1. Upload file ini ke folder yang sama dengan config.php di server kamu.
 *   2. Buka lewat browser: https://situskamu.com/check_decimal_chapters.php
 *      (perlu login dulu / sudah authenticated, sama seperti halaman lain)
 *   3. Baca laporan, lalu HAPUS file ini dari server setelah selesai dipakai.
 *
 * Script ini HANYA membaca data (read-only), tidak mengubah apapun di database.
 */

require_once "config.php";
requireAuth();

header("Content-Type: text/html; charset=utf-8");

// Ambil semua manga
$mangas = $pdo->query("SELECT manga_id, title FROM mangas ORDER BY title ASC")->fetchAll();

$report = []; // manga_id => [ 'title' => ..., 'groups' => [ chapter_number => [rows...] ] ]

foreach ($mangas as $m) {
    $stmt = $pdo->prepare("
        SELECT chapter_id, chapter_number, chapter_title, created_at, updated_at
        FROM chapters
        WHERE manga_id = :mid
        ORDER BY chapter_number ASC, created_at ASC
    ");
    $stmt->execute([":mid" => $m["manga_id"]]);
    $chapters = $stmt->fetchAll();

    // Kelompokkan berdasarkan chapter_number
    $groups = [];
    foreach ($chapters as $ch) {
        $groups[$ch["chapter_number"]][] = $ch;
    }

    // Simpan hanya grup yang punya lebih dari 1 chapter_id berbeda (indikasi bentrok)
    $suspicious = array_filter($groups, fn($g) => count($g) > 1);

    if (!empty($suspicious)) {
        $report[$m["manga_id"]] = [
            "title" => $m["title"],
            "groups" => $suspicious,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Diagnostik Chapter Desimal</title>
<style>
    body { font-family: system-ui, sans-serif; background: #10131a; color: #eae7e0; padding: 2rem; line-height: 1.5; }
    h1 { color: #f2a541; }
    .manga-block { background: #171b26; border: 1px solid #242938; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; }
    .manga-block h2 { margin-top: 0; font-size: 1.1rem; color: #fff; }
    .manga-id { color: #7c8194; font-size: 0.85rem; }
    table { width: 100%; border-collapse: collapse; margin: 0.75rem 0 1.25rem; font-size: 0.9rem; }
    th, td { text-align: left; padding: 0.4rem 0.6rem; border-bottom: 1px solid #242938; }
    th { color: #f2a541; }
    .group-label { color: #f7bc70; font-weight: 600; margin-top: 1rem; }
    code { background: #202636; padding: 0.1rem 0.4rem; border-radius: 4px; }
    .ok { color: #4caf50; font-weight: 600; }
    .warn-box { background: #2a1f14; border: 1px solid #f2a541; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; }
    .count-badge { display: inline-block; background: #f2a541; color: #10131a; border-radius: 50rem; padding: 0.1rem 0.6rem; font-size: 0.8rem; font-weight: 700; }
</style>
</head>
<body>

<h1>🔍 Diagnostik Chapter Desimal (chapter_number bentrok)</h1>

<?php if (empty($report)): ?>
    <p class="ok">✅ Tidak ditemukan chapter_number yang bentrok. Kemungkinan koleksi kamu belum ada yang kena masalah ini, atau memang belum ada chapter desimal sama sekali.</p>
<?php else: ?>
    <div class="warn-box">
        Ditemukan <span class="count-badge"><?= count($report) ?> manga</span> dengan indikasi chapter desimal yang bentrok akibat kolom <code>chapter_number</code> masih INT.
        <br><br>
        <strong>Yang perlu kamu lakukan setelah ini:</strong>
        <ol>
            <li>Jalankan migrasi kolom (ubah <code>chapter_number</code> jadi <code>DECIMAL(8,2)</code>) seperti yang sudah dijelaskan sebelumnya.</li>
            <li>Untuk tiap manga di daftar bawah ini, <strong>hapus baris-baris chapter yang bentrok</strong> dari tabel <code>chapters</code> (pakai phpMyAdmin, cukup hapus salah satu atau semua chapter_id dalam grup yang bentrok — data desimal aslinya sudah hilang dari kolom lama, jadi harus ditarik ulang dari API).</li>
            <li>Setelah baris dihapus, buka <code>crawl.php?manga_id=...</code> untuk manga tersebut agar chapter-chapter itu di-crawl ulang dengan nomor desimal yang benar.</li>
        </ol>
        <em>Catatan: script ini tidak menghapus apapun secara otomatis, supaya kamu bisa cek manual dulu sebelum bertindak.</em>
    </div>

    <?php foreach ($report as $mangaId => $data): ?>
        <div class="manga-block">
            <h2><?= htmlspecialchars($data["title"]) ?></h2>
            <div class="manga-id">manga_id: <code><?= htmlspecialchars($mangaId) ?></code></div>

            <?php foreach ($data["groups"] as $chapterNumber => $rows): ?>
                <div class="group-label">⚠️ chapter_number = <?= (int) $chapterNumber ?> &mdash; <?= count($rows) ?> baris berbeda</div>
                <table>
                    <thead>
                        <tr>
                            <th>chapter_id</th>
                            <th>chapter_title</th>
                            <th>created_at</th>
                            <th>updated_at</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($r["chapter_id"]) ?></code></td>
                                <td><?= htmlspecialchars($r["chapter_title"] ?: "-") ?></td>
                                <td><?= htmlspecialchars($r["created_at"]) ?></td>
                                <td><?= htmlspecialchars($r["updated_at"]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<p style="color:#7c8194; margin-top:2rem; font-size:0.85rem;">
    ⚠️ Ingat: hapus file <code>check_decimal_chapters.php</code> ini dari server setelah selesai dipakai, supaya tidak ada endpoint diagnostik yang menganggur di produksi.
</p>

</body>
</html>
