<?php
/**
 * Menyajikan manifest PWA lewat PHP (bukan file .json statis) karena InfinityFree
 * diketahui menyajikan file .json statis dengan Content-Type yang salah (text/html),
 * yang membuat Chrome gagal memvalidasi manifest saat proses "Add to Home Screen" -
 * akibatnya ikon custom tidak muncul dan Chrome jatuh ke fallback shortcut biasa.
 */
header("Content-Type: application/manifest+json; charset=utf-8");
header("Cache-Control: no-cache");
readfile(__DIR__ . "/manifest.json");