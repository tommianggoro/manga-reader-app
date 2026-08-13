<?php
require_once "config.php";
require_once "sync_functions.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

$mangaId = $_POST["manga_id"] ?? null;
$source = $_POST["source"] ?? null; // boleh string kosong utk reset ke "otomatis"

if (!$mangaId) {
    echo json_encode(["success" => false, "error" => "manga_id wajib diisi"]);
    exit;
}

if ($source !== null && $source !== "" && !getSource($source)) {
    echo json_encode(["success" => false, "error" => "Sumber tidak dikenali"]);
    exit;
}

setPreferredSource($pdo, $mangaId, $source ?: null);
echo json_encode(["success" => true, "preferred_source" => $source ?: null]);
