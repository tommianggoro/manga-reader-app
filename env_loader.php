<?php
/**
 * Loader .env manual (tanpa library eksternal seperti vlucas/phpdotenv).
 * Membaca file .env di root project dan menyimpannya di array PHP internal
 * (BUKAN lewat putenv()/getenv()), karena banyak hosting shared (mis. InfinityFree)
 * mem-blokir atau tidak mempropagasi putenv()/getenv() dengan benar antar-request.
 *
 * Format yang didukung:
 *   KEY=value
 *   KEY="value dengan spasi"
 *   KEY='value juga bisa single quote'
 *   # ini komentar, diabaikan
 *   (baris kosong diabaikan)
 */

function loadEnv(string $path): void {
    global $__ENV_STORE;

    if (!isset($__ENV_STORE) || !is_array($__ENV_STORE)) {
        $__ENV_STORE = [];
    }

    if (!is_readable($path)) {
        // Tidak fatal: config.php akan jatuh ke nilai default di env().
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

        $__ENV_STORE[$key] = $value;
    }
}

/**
 * Helper ambil env var dengan default value.
 * Membaca dari array internal hasil loadEnv(), bukan dari getenv()/OS environment,
 * supaya tetap konsisten di hosting yang membatasi fungsi tersebut (mis. InfinityFree).
 */
function env(string $key, $default = null) {
    global $__ENV_STORE;

    if (isset($__ENV_STORE) && array_key_exists($key, $__ENV_STORE)) {
        return $__ENV_STORE[$key];
    }

    return $default;
}