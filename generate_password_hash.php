<?php
/**
 * Jalankan file ini SEKALI lewat browser (misal: yoursite.com/generate_password_hash.php?pw=passwordbaru)
 * untuk dapat hash-nya, lalu tempel hasilnya ke config.php sebagai ACCESS_PASSWORD_HASH.
 *
 * PENTING: HAPUS file ini dari server setelah selesai dipakai. Jangan biarkan menggantung
 * karena siapapun yang tahu URL-nya bisa lihat hash (walau hash tidak bisa dibalik ke
 * password asli, tetap lebih rapi kalau file ini dihapus).
 */

$pw = $_GET["pw"] ?? null;

if (!$pw) {
    echo "Tambahkan ?pw=passwordbarukamu di URL untuk generate hash.";
    exit;
}

echo "Password : " . htmlspecialchars($pw) . "\n";
echo "Hash     : " . password_hash($pw, PASSWORD_DEFAULT) . "\n\n";
echo "Salin hash di atas ke config.php sebagai nilai ACCESS_PASSWORD_HASH.\n";
echo "Setelah itu, HAPUS file generate_password_hash.php ini dari server.";
