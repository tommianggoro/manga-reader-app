<?php
require_once "config.php";
requireAuth();

$mangaId = $_GET["manga_id"] ?? die("manga_id wajib diisi");
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proses Sinkronisasi Manga...</title>
    <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%23f2a541'/%3E%3Ctext x='50' y='68' font-size='55' text-anchor='middle'%3E%F0%9F%93%96%3C/text%3E%3C/svg%3E">
    <style>
        :root, [data-bs-theme="dark"] {
            --bs-body-bg: #0b0f17;
            --bs-body-color: #f1f3f5;
            --bs-primary: #f2a541;
            --bs-primary-rgb: 242, 165, 65;
            --bs-border-color: #1e2433;
            --bs-secondary-bg: #131824;
        }
        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            letter-spacing: -0.01em;
        }
        .brand-font { font-family: 'Bitter', Georgia, serif; }

        .crawler-card {
            background: var(--bs-secondary-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .progress {
            background-color: #1c2333;
            border-radius: 50rem;
            height: 16px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .progress-bar {
            background: linear-gradient(90deg, #f2a541, #f7bc70);
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #log { 
            background: #111; 
            border-radius: 8px; 
            padding: 1rem; 
            margin-top: 1rem; 
            max-height: 300px; /* Membatasi tinggi kotak log agar tidak melar ke bawah */
            overflow-y: auto;  /* Mengaktifkan scrollbar vertikal saat teks penuh */
            font-family: monospace; 
            font-size: 0.85rem; 
        }
        
        /* Custom scrollbar untuk panel log */
        #log::-webkit-scrollbar { width: 6px; }
        #log::-webkit-scrollbar-track { background: transparent; }
        #log::-webkit-scrollbar-thumb { background: #1e2433; border-radius: 10px; }

        #log div {
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.02);
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .back-btn {
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            font-weight: 500;
            transition: all 0.2s;
        }
    </style>
</head>
<body>
<div class="container py-5" style="max-width: 750px;">

    <div class="crawler-card">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="spinner-border text-primary spinner-border-sm" id="spinner" role="status"></div>
            <h1 class="brand-font h4 mb-0 text-white" id="title">Memulai crawling...</h1>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2 small text-secondary fw-medium">
            <span>Status Progres Pengunduhan</span>
            <span id="percent" class="text-primary fw-semibold">0%</span>
        </div>
        
        <div class="progress mb-4">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="bar" role="progressbar" style="width: 0%"></div>
        </div>

        <div class="small text-secondary fw-semibold text-uppercase tracking-wider mb-2">
            <i class="bi bi-terminal me-1"></i> Konsol Aktivitas
        </div>
        <div id="log"></div>

        <div class="text-end mt-4">
            <a class="btn btn-outline-secondary back-btn" id="doneLink" href="index.php" style="display:none">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Koleksi
            </a>
        </div>
    </div>

</div>

<script>
    const mangaId = <?= json_encode($mangaId) ?>;
    const logEl = document.getElementById("log");
    const barEl = document.getElementById("bar");
    const percentEl = document.getElementById("percent");
    const titleEl = document.getElementById("title");
    const doneLink = document.getElementById("doneLink");
    const spinner = document.getElementById("spinner");

    function log(msg) {
        const p = document.createElement("div");
        p.textContent = msg;
        logEl.appendChild(p); // Menggunakan appendChild agar log baru berada di bawah

        // Otomatis menggulirkan scrollbar ke posisi paling bawah setiap ada log baru
        logEl.scrollTop = logEl.scrollHeight;
    }

    async function run() {
        try {
            titleEl.textContent = "Mengambil info manga...";
            const initRes = await fetch(`crawler.php?action=init&manga_id=${encodeURIComponent(mangaId)}`);
            const initData = await initRes.json();
            if (!initData.success) { log("ERROR: " + initData.error); spinner.remove(); return; }

            titleEl.textContent = initData.title;
            const total = initData.latest_chapter_number || 1;
            let currentChapterId = initData.latest_chapter_id;
            let done = 0;
            let skipped = 0;

            while (currentChapterId) {
                let data = null;
                let attempt = 0;
                const maxAttempts = 3;

                while (attempt < maxAttempts) {
                    attempt++;
                    try {
                        const res = await fetch(
                            `crawler.php?action=step&manga_id=${encodeURIComponent(mangaId)}&chapter_id=${encodeURIComponent(currentChapterId)}`
                        );
                        data = await res.json();
                        if (data.success) break;
                        log(`Percobaan ${attempt} gagal: ${data.error}`);
                    } catch (err) {
                        log(`Percobaan ${attempt} gagal: ${err.message}`);
                    }
                    if (attempt < maxAttempts) await new Promise(r => setTimeout(r, 1000));
                }

                if (!data || !data.success) {
                    log("❌ Berhenti karena error berulang. Kembalilah nanti untuk melanjutkan progress aman.");
                    break;
                }

                if (data.repaired) {
                    skipped++;
                    log(`🔧 Chapter ${data.chapter_number} ditambal (link diperbaiki)`);
                } else if (data.skipped) {
                    skipped++;
                    log(`— Chapter ${data.chapter_number} sudah ada, dilewati`);
                } else {
                    done++;
                    log(`✓ Chapter ${data.chapter_number} berhasil disimpan`);
                }

                const percent = Math.min(100, Math.round(((done + skipped) / total) * 100));
                barEl.style.width = percent + "%";
                percentEl.textContent = `${percent}% (${done} baru, ${skipped} dilewati)`;

                currentChapterId = data.prev_chapter_id;
            }

            titleEl.textContent += " — Selesai!";
            barEl.style.width = "100%";
            barEl.classList.remove("progress-bar-striped", "progress-bar-animated");
            spinner.className = "bi bi-check-circle-fill text-success fs-5";
            log(`Proses Selesai. Total: ${done} chapter baru, ${skipped} chapter dilewati.`);
            doneLink.style.display = "inline-block";
        } catch (err) {
            log("ERROR: " + err.message);
            spinner.remove();
        }
    }

    run();
</script>
</body>
</html>