<?php
/**
 * Entry point BARU utk "Tambah / Update Manga" (menggantikan form yg tadinya
 * langsung ke crawl.php?manga_id=...). Input diterima generic: bisa manga_id
 * polos (Shinigami) ATAU URL lengkap (mis. Komiku) -- dideteksi otomatis lewat
 * SourceRegistry::detectSourceFromInput().
 *
 * Alur:
 *  - Kalau (source, source_ref) SUDAH terhubung ke manga tertentu -> langsung
 *    lanjut ke crawl.php (cuma resync), tidak perlu konfirmasi apapun.
 *  - Kalau BELUM & ada manga lain berjudul mirip -> tampilkan halaman konfirmasi
 *    manual (linking manual sesuai keputusan desain): user pilih "hubungkan ke
 *    manga yang sudah ada" atau "simpan sebagai manga baru".
 *  - Kalau BELUM & tidak ada yang mirip -> otomatis dibuat sbg manga baru.
 */

require_once "config.php";
require_once "sync_functions.php";
requireAuth();

$rawInput = trim($_GET["manga_id"] ?? $_POST["manga_id"] ?? "");
if ($rawInput === "") die("Input manga_id / URL wajib diisi.");

$detected = detectSourceFromInput($rawInput);
if (!$detected) {
    die("Input tidak dikenali sumbernya. Pastikan format manga_id Shinigami benar, atau tempel URL manga Komiku (https://komiku.org/manga/.../).");
}
$source = $detected["source"];
$sourceRef = $detected["ref"];
$adapter = $detected["adapter"];

// Sudah pernah di-bind sebelumnya? Langsung resync, tidak perlu apa-apa lagi.
$existingMangaId = findMangaBySource($pdo, $source, $sourceRef);
if ($existingMangaId) {
    header("Location: crawl.php?manga_id=" . urlencode($existingMangaId));
    exit;
}

// ==== Handle keputusan user dari form konfirmasi (POST) ====
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $decision = $_POST["decision"] ?? "";

    if ($decision === "link" && !empty($_POST["target_manga_id"])) {
        $targetMangaId = $_POST["target_manga_id"];
        $stmt = $pdo->prepare("SELECT 1 FROM mangas WHERE manga_id = :id");
        $stmt->execute([":id" => $targetMangaId]);
        if (!$stmt->fetch()) die("Manga tujuan tidak ditemukan.");

        bindMangaSource($pdo, $targetMangaId, $source, $sourceRef);
        header("Location: crawl.php?manga_id=" . urlencode($targetMangaId));
        exit;
    }

    if ($decision === "new") {
        $info = $adapter->fetchMangaInfo($sourceRef);
        $newMangaId = generateInternalMangaId($pdo, $info["title"]);
        saveMangaCore($pdo, $newMangaId, $info);
        bindMangaSource($pdo, $newMangaId, $source, $sourceRef);
        header("Location: crawl.php?manga_id=" . urlencode($newMangaId));
        exit;
    }

    die("Keputusan tidak valid.");
}

// ==== GET: cek kandidat mirip, tampilkan konfirmasi kalau perlu ====
try {
    $info = $adapter->fetchMangaInfo($sourceRef);
} catch (Exception $e) {
    die("Gagal mengambil info manga dari " . htmlspecialchars($adapter->getLabel()) . ": " . htmlspecialchars($e->getMessage()));
}

$similar = findSimilarMangas($pdo, $info["title"]);

if (empty($similar)) {
    // Tidak ada kandidat mirip -> langsung buat manga baru, tanpa perlu konfirmasi.
    $newMangaId = generateInternalMangaId($pdo, $info["title"]);
    saveMangaCore($pdo, $newMangaId, $info);
    bindMangaSource($pdo, $newMangaId, $source, $sourceRef);
    header("Location: crawl.php?manga_id=" . urlencode($newMangaId));
    exit;
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Konfirmasi Tambah Manga</title>
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --bs-body-bg: #10131a; --bs-body-color: #eae7e0; --bs-primary: #f2a541; --bs-border-color: #242938; --bs-secondary-bg: #171b26; }
        body { font-family: 'Inter', system-ui, sans-serif; }
        .brand-font { font-family: 'Bitter', Georgia, serif; }
        .candidate-card { background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); border-radius: 12px; padding: 1rem; cursor: pointer; transition: all .15s ease; }
        .candidate-card:hover, .candidate-card.selected { border-color: var(--bs-primary); background: #202636; }
        .candidate-cover { width: 56px; height: 80px; object-fit: cover; border-radius: 6px; flex-shrink: 0; background: #202636; }
    </style>
</head>
<body>
<div class="container py-5" style="max-width: 640px;">
    <h1 class="brand-font h4 mb-1"><i class="bi bi-signpost-split text-warning me-1"></i> Manga Ini Mirip yang Sudah Ada</h1>
    <p class="text-secondary small mb-4">
        Menambahkan dari <strong><?= htmlspecialchars($adapter->getLabel()) ?></strong>: "<?= htmlspecialchars($info["title"]) ?>".
        Ditemukan manga dgn judul mirip di koleksimu. Hubungkan sumber baru ini ke salah satunya,
        atau simpan sebagai manga baru yang terpisah.
    </p>

    <form method="POST" id="confirmForm">
        <input type="hidden" name="manga_id" value="<?= htmlspecialchars($rawInput) ?>">
        <input type="hidden" name="decision" id="decisionInput" value="">
        <input type="hidden" name="target_manga_id" id="targetMangaIdInput" value="">

        <div class="d-flex flex-column gap-2 mb-4">
            <?php foreach ($similar as $s): ?>
                <div class="candidate-card d-flex align-items-center gap-3" data-manga-id="<?= htmlspecialchars($s['manga_id']) ?>">
                    <img src="<?= htmlspecialchars($s['cover_image_url']) ?>" class="candidate-cover" alt="">
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= htmlspecialchars($s['title']) ?></div>
                        <div class="small text-secondary">Hubungkan sumber baru ke manga ini</div>
                    </div>
                    <i class="bi bi-circle fs-5 text-secondary check-icon"></i>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary flex-grow-1" id="saveNewBtn">
                <i class="bi bi-plus-lg me-1"></i> Simpan sbg Manga Baru (Terpisah)
            </button>
            <button type="submit" class="btn btn-primary flex-grow-1" id="linkBtn" disabled>
                <i class="bi bi-link-45deg me-1"></i> Hubungkan ke Pilihan
            </button>
        </div>
        <div class="text-center mt-3">
            <a href="index.php" class="small text-secondary">Batal, kembali ke koleksi</a>
        </div>
    </form>
</div>

<script>
    const cards = Array.from(document.querySelectorAll(".candidate-card"));
    const linkBtn = document.getElementById("linkBtn");
    const saveNewBtn = document.getElementById("saveNewBtn");
    const decisionInput = document.getElementById("decisionInput");
    const targetInput = document.getElementById("targetMangaIdInput");
    const form = document.getElementById("confirmForm");

    cards.forEach(card => {
        card.addEventListener("click", () => {
            cards.forEach(c => { c.classList.remove("selected"); c.querySelector(".check-icon").className = "bi bi-circle fs-5 text-secondary check-icon"; });
            card.classList.add("selected");
            card.querySelector(".check-icon").className = "bi bi-check-circle-fill fs-5 text-primary check-icon";
            targetInput.value = card.dataset.mangaId;
            linkBtn.disabled = false;
        });
    });

    form.addEventListener("submit", () => { decisionInput.value = "link"; });
    saveNewBtn.addEventListener("click", () => {
        decisionInput.value = "new";
        form.submit();
    });
</script>
</body>
</html>
