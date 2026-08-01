<?php
/**
 * Menyajikan service worker lewat PHP, alasan sama seperti manifest.php:
 * menghindari Content-Type salah dari static file handler InfinityFree
 * yang bisa membuat browser menolak/salah memuat service worker.
 */
header("Content-Type: application/javascript; charset=utf-8");
header("Service-Worker-Allowed: /");
header("Cache-Control: no-cache");
readfile(__DIR__ . "/sw.js");