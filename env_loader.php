<?php
/**
 * Loader .env manual (tanpa library eksternal seperti vlucas/phpdotenv).
 * Membaca file .env di root project dan memasukkan isinya ke $_ENV / getenv().
 *
 * Format yang didukung:
 *   KEY=value
 *   KEY="value dengan spasi"
 *   KEY='value juga bisa single quote'
 *   # ini komentar, diabaikan
 *   (baris kosong diabaikan)
 *
 * Variabel yang SUDAH di-set sebelumnya (misal lewat environment asli server/
 * Docker/panel hosting) tidak akan ditimpa oleh isi .env — .env hanya dipakai
 * sebagai fallback untuk pengembangan lokal / hosting yang tidak punya
 * mekanisme environment variable sendiri.
 */

function loadEnv(string $path): void {
    if (!is_readable($path)) {
        // Tidak fatal: mungkin environment variable sudah di-set lewat cara lain
        // (misal di panel hosting), jadi kita biarkan lanjut tanpa .env file.
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Lewati komentar dan baris kosong
        if ($line === "" || str_starts_with($line, "#")) {
            continue;
        }

        // Harus mengandung tanda '='
        if (!str_contains($line, "=")) {
            continue;
        }

        [$key, $value] = explode("=", $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Lepas quote di awal/akhir kalau ada ("value" atau 'value')
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($key === "") {
            continue;
        }

        // Jangan timpa kalau sudah pernah di-set dari environment asli server
        if (getenv($key) !== false) {
            continue;
        }

        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

/**
 * Helper ambil env var dengan default value, biar pemanggilan di config.php ringkas.
 */
function env(string $key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}