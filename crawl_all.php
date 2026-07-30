<?php
require_once "config.php";
requireAuth();

$mangas = $pdo->query("SELECT manga_id, title, latest_chapter_number FROM mangas ORDER BY title ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sinkronisasi Massal Semua Manga</title>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('manga_theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
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
            --bs-tertiary-bg: #202636;
        }
        [data-bs-theme="light"] {
            --bs-body-bg: #f5f6f8;
            --bs-body-color: #212529;
            --bs-primary: #e08b18;
            --bs-border-color: #e0e4eb;
            --bs-secondary-bg: #ffffff;
            --bs-tertiary-bg: #f0f2f5;
        }

        body { font-family: 'Inter', system-ui, sans-serif; }
        .brand-font { font-family: 'Bitter', Georgia, serif; }

        .crawler-card {
            background: var(--bs-secondary-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .progress {
            background-color: var(--bs-tertiary-bg);
            border-radius: 50rem;
            height: 14px;
            overflow: hidden;
        }
        .progress-bar {
            background: linear-gradient(90deg, #f2a541, #f7bc70);
            transition: width 0.3s ease;
        }

        #log { 
            background: #111;
            color: #ddd;
            border-radius: 8px; 
            padding: 1rem; 
            margin-top: 1rem; 
            max-height: 320px; 
            overflow-y: auto;  
            font-family: monospace; 
            font-size: 0.85rem; 
        }
        
        #log::-webkit-scrollbar { width: 6px; }
        #log::-webkit-scrollbar-thumb { background: #2a2f3d; border-radius: 10px; }

        #log div {
            padding: 3px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            line-height: 1.4;
        }
    </style>
</head>
<body>
<div class="container py-5" style="max-width: 800px;">

    <div class="crawler-card">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="spinner-border text-primary spinner-border-sm" id="spinner" role="status"></div>
                <h1 class="brand-font h4 mb-0" id="mainTitle">Memulai Sinkronisasi Massal...</h1>
            </div>
            <span class="badge text-bg-primary" id="mangaCounter">0 / <?= count($mangas) ?> Manga</span>
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1 small text-secondary fw-medium">
                <span>Progres Keseluruhan Koleksi</span>
                <span id="overallPercent" class="text-primary fw-semibold">0%</span>
            </div>
            <div class="progress mb-3">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="overallBar" role="progressbar" style="width: 0%"></div>
            </div>
        </div>

        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1 small text-secondary fw-medium">
                <span id="currentMangaTitle" class="text-truncate" style="max-width: 80%;">Menyiapkan antrean manga...</span>
                <span id="mangaPercent" class="fw-semibold">0%</span>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-info" id="mangaBar" role="progressbar" style="width: 0%"></div>
            </div>
        </div>

        <div class="small text-secondary fw-semibold text-uppercase tracking-wider mb-2">
            <i class="bi bi-terminal me-1"></i> Log Aktivitas Sinkronisasi Massal
        </div>
        <div id="log"></div>

        <div class="text-end mt-4">
            <a class="btn btn-outline-secondary px-4" id="doneLink" href="index.php">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Koleksi
            </a>
        </div>
    </div>

</div>

<script>
    const mangaQueue = <?= json_encode($mangas) ?>;
    const totalMangaCount = mangaQueue.length;

    const logEl = document.getElementById("log");
    const overallBar = document.getElementById("overallBar");
    const overallPercent = document.getElementById("overallPercent");
    const mangaBar = document.getElementById("mangaBar");
    const mangaPercent = document.getElementById("mangaPercent");
    const mainTitle = document.getElementById("mainTitle");
    const currentMangaTitle = document.getElementById("currentMangaTitle");
    const mangaCounter = document.getElementById("mangaCounter");
    const spinner = document.getElementById("spinner");

    function log(msg) {
        const p = document.createElement("div");
        p.textContent = msg;
        logEl.appendChild(p);
        logEl.scrollTop = logEl.scrollHeight;
    }

    async function runBatchSync() {
        if (totalMangaCount === 0) {
            mainTitle.textContent = "Tidak Ada Manga di Koleksi";
            spinner.style.display = "none";
            log("Belum ada manga untuk disinkronkan.");
            return;
        }

        let completedMangaCount = 0;
        let totalNewChaptersAll = 0;
        let totalSkippedChaptersAll = 0;

        for (let i = 0; i < totalMangaCount; i++) {
            const currentManga = mangaQueue[i];
            const mangaId = currentManga.manga_id;
            
            mangaCounter.textContent = `${i + 1} / ${totalMangaCount} Manga`;
            currentMangaTitle.textContent = `[${i + 1}/${totalMangaCount}] ${currentManga.title}`;
            log(`\n========================================`);
            log(`▶ Processing (${i + 1}/${totalMangaCount}): ${currentManga.title}`);

            mangaBar.style.width = "0%";
            mangaPercent.textContent = "0%";

            try {
                // Init manga
                const initRes = await fetch(`crawler.php?action=init&manga_id=${encodeURIComponent(mangaId)}`);
                const initData = await initRes.json();
                if (!initData.success) {
                    log(`❌ ERROR init ${currentManga.title}: ${initData.error}`);
                    continue;
                }

                const totalCh = initData.latest_chapter_number || 1;
                let currentChapterId = initData.latest_chapter_id;
                let done = 0;
                let skipped = 0;

                const updateChannel = window.BroadcastChannel ? new BroadcastChannel('manga_reader_updates') : null;

                while (currentChapterId) {
                    let data = null;
                    let attempt = 0;

                    while (attempt < 3) {
                        attempt++;
                        try {
                            const res = await fetch(`crawler.php?action=step&manga_id=${encodeURIComponent(mangaId)}&chapter_id=${encodeURIComponent(currentChapterId)}`);
                            data = await res.json();
                            if (data.success) break;
                        } catch (err) {}
                        if (attempt < 3) await new Promise(r => setTimeout(r, 800));
                    }

                    if (!data || !data.success) {
                        log(`❌ Terhenti di chapter ID ${currentChapterId} untuk manga ${currentManga.title}`);
                        break;
                    }

                    if (data.skipped) {
                        skipped++;
                    } else {
                        done++;
                        log(`✓ Chapter ${data.chapter_number} baru berhasil disimpan`);
                        if (updateChannel) {
                            updateChannel.postMessage({
                                type: 'manga_updated',
                                manga_id: mangaId,
                                title: currentManga.title,
                                latest_chapter_number: initData.latest_chapter_number,
                                chapter_number: data.chapter_number,
                                chapter_id: currentChapterId,
                                is_new: true,
                                timestamp: new Date().getTime()
                            });
                        }
                    }

                    const pct = Math.min(100, Math.round(((done + skipped) / totalCh) * 100));
                    mangaBar.style.width = pct + "%";
                    mangaPercent.textContent = `${pct}% (${done} baru)`;

                    currentChapterId = data.prev_chapter_id;
                }

                totalNewChaptersAll += done;
                totalSkippedChaptersAll += skipped;
                log(`✓ Selesai ${currentManga.title}: ${done} chapter baru, ${skipped} disinkronkan.`);

            } catch (err) {
                log(`❌ Gagal memproses ${currentManga.title}: ${err.message}`);
            }

            completedMangaCount++;
            const overallPct = Math.round((completedMangaCount / totalMangaCount) * 100);
            overallBar.style.width = overallPct + "%";
            overallPercent.textContent = `${overallPct}%`;
        }

        mainTitle.textContent = "Sinkronisasi Massal Selesai!";
        overallBar.style.width = "100%";
        overallBar.classList.remove("progress-bar-striped", "progress-bar-animated");
        spinner.className = "bi bi-check-circle-fill text-success fs-4";
        log(`\n========================================`);
        log(`🎉 SINKRONISASI MASSAL SELESAI!`);
        log(`Total Manga Diproses : ${completedMangaCount} dari ${totalMangaCount}`);
        log(`Total Chapter Baru   : ${totalNewChaptersAll}`);
    }

    runBatchSync();
</script>
</body>
</html>
