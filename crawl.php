<?php
require_once "config.php";
requireAuth();

$mangaId = $_GET["manga_id"] ?? die("manga_id wajib diisi");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crawling Manga...</title>
    <style>
        body { font-family: sans-serif; background: #1a1a1a; color: #eee; margin: 0; padding: 1.5rem; max-width: 700px; margin: 0 auto; }
        h1 { font-size: 1.3rem; }
        .progress-wrap { background: #2a2a2a; border-radius: 8px; height: 24px; overflow: hidden; margin: 1rem 0; }
        .progress-bar { background: linear-gradient(90deg, #4a90d9, #6ab0f3); height: 100%; width: 0%; transition: width 0.2s; }
        #percent { font-weight: bold; }
        #log { background: #111; border-radius: 8px; padding: 1rem; margin-top: 1rem; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 0.85rem; }
        #log div { padding: 2px 0; border-bottom: 1px solid #222; }
        a.back { display: inline-block; margin-top: 1.5rem; color: #4a90d9; }
    </style>
</head>
<body>
    <h1 id="title">Memulai crawling...</h1>
    <div class="progress-wrap"><div class="progress-bar" id="bar"></div></div>
    <p id="percent">0%</p>
    <div id="log"></div>
    <a class="back" id="doneLink" href="index.php" style="display:none">&larr; Kembali ke koleksi</a>

    <script>
        const mangaId = <?= json_encode($mangaId) ?>;
        const logEl = document.getElementById("log");
        const barEl = document.getElementById("bar");
        const percentEl = document.getElementById("percent");
        const titleEl = document.getElementById("title");
        const doneLink = document.getElementById("doneLink");

        function log(msg) {
            const p = document.createElement("div");
            p.textContent = msg;
            logEl.prepend(p);
        }

        async function run() {
            try {
                titleEl.textContent = "Mengambil info manga...";
                const initRes = await fetch(`crawler.php?action=init&manga_id=${encodeURIComponent(mangaId)}`);
                const initData = await initRes.json();
                if (!initData.success) { log("ERROR: " + initData.error); return; }

                titleEl.textContent = initData.title;
                const total = initData.latest_chapter_number || 1;
                let currentChapterId = initData.latest_chapter_id;
                let done = 0;
                let skipped = 0;

                while (currentChapterId) {
                    let data = null;
                    let attempt = 0;
                    const maxAttempts = 3;

                    // Retry otomatis kalau ada gangguan jaringan sesaat
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
                        log("❌ Berhenti karena error berulang. Klik 'Tambah/Update' lagi nanti untuk melanjutkan dari titik ini (progress sejauh ini aman tersimpan).");
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
                        log(`✓ Chapter ${data.chapter_number} tersimpan`);
                    }

                    const percent = Math.min(100, Math.round(((done + skipped) / total) * 100));
                    barEl.style.width = percent + "%";
                    percentEl.textContent = `${percent}% (${done} baru, ${skipped} dilewati)`;

                    currentChapterId = data.prev_chapter_id;
                }

                titleEl.textContent += " — Selesai!";
                barEl.style.width = "100%";
                log(`Total: ${done} chapter baru, ${skipped} chapter dilewati (sudah ada sebelumnya).`);
                doneLink.style.display = "inline-block";
            } catch (err) {
                log("ERROR: " + err.message);
            }
        }

        run();
    </script>
</body>
</html>
