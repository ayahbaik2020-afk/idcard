<?php
    // Accent color per plant, computed early so it can be used in <head> too
    $plant_map_colors = [
        'CA PLANT' => 'plant_color_ca',
        'EDC/VCM PLANT' => 'plant_color_edc_vcm',
        'PVC PLANT' => 'plant_color_pvc'
    ];
    $color_key = $plant_map_colors[$plant_name] ?? null;
    $header_color = ($color_key && !empty($settings[$color_key])) ? $settings[$color_key] : '#f7f4ec';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plant Display - <?php echo htmlspecialchars($plant_name); ?></title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>:root { --accent-color: <?php echo htmlspecialchars($header_color); ?>; }</style>
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css?v=2">
    <style>
        html, body { margin: 0; height: 100%; overflow: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #000; }

        /* Full-screen video background */
        .bg-video {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            object-fit: cover;
            z-index: 0;
            background: #000;
        }
        .video-tint {
            position: fixed; inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.45) 0%, rgba(0,0,0,0.05) 18%, rgba(0,0,0,0.05) 78%, rgba(0,0,0,0.5) 100%);
            z-index: 1;
            pointer-events: none;
        }

        /* Header bar - always visible, thin & translucent */
        .header-bar {
            position: fixed; top: 0; left: 0; right: 0;
            z-index: 20;
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 24px;
            background: linear-gradient(90deg, color-mix(in srgb, var(--accent-color) 55%, rgba(15,20,25,0.55)) 0%, rgba(15, 20, 25, 0.5) 70%);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: #fff;
            border-bottom: 3px solid var(--accent-color);
        }
        .header-bar .brand { display: flex; align-items: center; gap: 14px; }
        .header-bar h1 { margin: 0; font-size: 1.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #fff; }
        .header-bar img.logo { height: 40px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }
        .clock { font-size: 1.6rem; font-weight: 700; color: #f1c40f; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        #fullscreen-btn { background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.3); color: #fff; transition: all 0.2s; }
        #fullscreen-btn:hover { background: rgba(255,255,255,0.22); }

        /* Bottom ticker bar */
        .ticker-bar {
            position: fixed; left: 0; right: 0; bottom: 0;
            z-index: 15;
            background: rgba(15, 20, 25, 0.45);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border-top: 1px solid rgba(255,255,255,0.08);
            padding: 14px 0;
        }
        .running-text { padding: 4px; font-size: 1.2rem; font-weight: 600; color: #fff; margin: 0; text-shadow: 0 1px 3px rgba(0,0,0,0.5); }

        /* Seamless ticker (no gap/pause between loops, regardless of text length) */
        .ticker-bar { overflow: hidden; white-space: nowrap; }
        .ticker-track { display: inline-flex; will-change: transform; }
        .ticker-track.looping { animation: ticker-scroll linear infinite; }
        .ticker-item { padding-right: 80px; font-size: 2.4rem; font-weight: 600; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.5); white-space: nowrap; }
        @keyframes ticker-scroll { from { transform: translateX(0); } to { transform: translateX(-33.3333%); } }

        /* Floating glass cards */
        .float-col {
            position: fixed; top: 82px; bottom: 130px;
            width: 270px;
            z-index: 10;
            display: flex; flex-direction: column; gap: 14px;
            overflow-y: auto;
            scrollbar-width: none;
        }
        .float-col::-webkit-scrollbar { display: none; }
        .float-col.left { left: 18px; }
        .float-col.right { right: 18px; }

        .glass-card {
            background: rgba(255, 255, 255, 0.10);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 16px;
            padding: 14px 16px;
            color: #fff;
            box-shadow: 0 8px 24px rgba(0,0,0,0.22);
            flex-shrink: 0;
        }
        .float-col.left .glass-card { border-left: 4px solid var(--card-color, var(--accent-color)); }
        .float-col.right .glass-card { border-right: 4px solid var(--card-color, var(--accent-color)); }
        .glass-card h5 { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.95; margin: 0 0 10px 0; border-bottom: 1px solid var(--card-color, var(--accent-color)); padding-bottom: 6px; color: var(--card-color, var(--accent-color)); }
        .glass-card h5 i { color: var(--card-color, var(--accent-color)); }
        .glass-card .stat-number { color: var(--card-color, var(--accent-color)); }
        .glass-card .stat-number { font-size: 2.1rem; font-weight: 800; text-align: center; text-shadow: 0 2px 6px rgba(0,0,0,0.4); }
        .glass-card.on-duty .content { text-align: center; font-size: 0.85rem; }
        .glass-card.on-duty img { width: 80px; height: 105px; object-fit: cover; border-radius: 8px; margin-bottom: 8px; border: 1px solid rgba(255,255,255,0.3); }
        .glass-card.info-plant .content { font-size: 0.85rem; opacity: 0.95; }

        /* Per-card theme colors (semantic: safety=green, activity=orange, duty/info=accent, alert=red, live=teal) */
        .glass-card.card-safety  { --card-color: #2ecc71; }
        .glass-card.card-activity { --card-color: #f39c12; }
        .glass-card.card-alert   { --card-color: #e74c3c; }
        .glass-card.card-live    { --card-color: #17c3b2; }

        /* Preview mini card (last scan / banned slideshow) */
        .glass-card.preview-card { min-height: 150px; display: flex; flex-direction: column; }
        .glass-card.preview-card .preview-body { flex-grow: 1; display: flex; align-items: center; justify-content: center; }
        .glass-card.preview-card .preview-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.15); }

        /* Scanner card - pinned to bottom of right column */
        .glass-card.scanner-card { margin-top: auto; padding: 10px 12px; }
        .glass-card.scanner-card h5 { margin-bottom: 6px; }
        #camera-select { border-radius: 6px; font-size: 0.72rem; padding: 2px 6px; }
        #error-log { font-size: 0.72rem; padding: 4px 8px; margin-top: 6px; }

        /* Big centered scan-result popup */
        .scan-popup {
            position: fixed; inset: 0;
            z-index: 30;
            display: none;
            align-items: center; justify-content: center;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(4px);
        }
        .scan-popup-inner {
            background: rgba(20, 25, 30, 0.85);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 24px;
            padding: 40px 60px;
            text-align: center;
            color: #fff;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: popIn 0.25s ease-out;
        }
        @keyframes popIn { from { transform: scale(0.85); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .scan-popup-inner img { width: 200px; height: 200px; object-fit: cover; border-radius: 50%; margin-bottom: 16px; border: 4px solid rgba(255,255,255,0.25); }

        /* Tablet */
        @media (max-width: 1024px) {
            .float-col { width: 220px; }
            .header-bar h1 { font-size: 1.3rem; }
            .clock { font-size: 1.3rem; }
        }
        /* Mobile: collapse floating columns to avoid unusable overlap on small screens */
        @media (max-width: 768px) {
            html, body { overflow-y: auto; }
            .float-col { position: static; width: auto; height: auto; margin: 8px; }
            .header-bar { position: sticky; }
            .bg-video { position: fixed; }
        }
    </style>
</head>
<body>
    <?php
        // $header_color already computed at top of file
        $logo_url = !empty($settings['app_logo']) ? htmlspecialchars($settings['app_logo']) : '';
        $video_url = !empty($settings['safety_video_url']) ? htmlspecialchars($settings['safety_video_url']) : '';
        // NOTE: this vhost's document root already points at the `public/`
        // folder, so on_duty_photo_url (already "uploads/settings/...")
        // must NOT be prefixed with an extra "public/" - that produced 404s.
        $on_duty_photo_url = !empty($settings['on_duty_photo_url']) ? htmlspecialchars($settings['on_duty_photo_url']) : '';
    ?>

    <!-- Full-screen background video -->
    <video id="safety-video" class="bg-video" muted loop autoplay playsinline>
        Your browser does not support the video tag.
    </video>
    <div class="video-tint"></div>

    <!-- Header: always visible -->
    <div class="header-bar">
        <div class="brand">
            <?php if ($logo_url): ?><img src="<?php echo $logo_url; ?>" alt="App Logo" class="logo"><?php endif; ?>
            <h1><?php echo htmlspecialchars($plant_name); ?></h1>
        </div>
        <div class="d-flex align-items-center">
            <button id="fullscreen-btn" class="btn btn-outline-light btn-sm me-4" title="Toggle Fullscreen" style="border-radius: 8px;">
                <i class="fas fa-expand"></i>
            </button>
            <div class="clock" id="clock"></div>
        </div>
    </div>

    <!-- Left floating cards -->
    <div class="float-col left">
        <div class="glass-card card-safety">
            <h5><i class="fas fa-shield-alt me-2"></i>MAN HOURS WITHOUT LTI</h5>
            <div class="content text-center stat-number" id="man-hours-lti"><?php echo htmlspecialchars(number_format($settings['plant_working_hours'] ?? 0, 0, ',', '.')); ?></div>
        </div>
        <div class="glass-card card-activity">
            <h5><i class="fas fa-users me-2"></i>QTY KONTRAKTOR DI PLANT</h5>
            <div class="content text-center stat-number" id="contractor-count"><?php echo $contractor_count; ?></div>
        </div>
        <div class="glass-card on-duty">
            <h5><i class="fas fa-user-shield me-2"></i>PETUGAS ON DUTY</h5>
            <div class="content text-center">
                <img src="<?php echo $on_duty_photo_url ?: 'assets/images/placeholder-avatar.svg'; ?>" alt="Petugas On Duty">
                <div><strong>Nama:</strong> <?php echo htmlspecialchars($settings['on_duty_name'] ?? '-'); ?></div>
                <div><strong>Jabatan:</strong> <?php echo htmlspecialchars($settings['on_duty_position'] ?? '-'); ?></div>
                <div><strong>Plant:</strong> <?php echo htmlspecialchars($settings['on_duty_plant'] ?? '-'); ?></div>
            </div>
        </div>
    </div>

    <!-- Right floating cards (scanner pinned to the bottom of this column) -->
    <div class="float-col right">
        <div class="glass-card info-plant">
            <h5><i class="fas fa-info-circle me-2"></i>INFORMASI</h5>
            <div class="content"><?php echo nl2br(htmlspecialchars($settings['plant_information'] ?? '-')); ?></div>
        </div>

        <div class="glass-card preview-card card-alert">
            <h5><i class="fas fa-id-badge me-2"></i>PREVIEW</h5>
            <div id="attendance-update-container" class="preview-body"></div>
            <div class="preview-footer">
                <small style="font-size: 0.68rem; opacity: 0.8;">Daftar Kontraktor Banned:</small>
                <button id="open-banned-list" class="btn btn-danger py-0 px-2" style="font-size: 0.72rem; height: 22px; border-radius: 6px;">Buka Daftar</button>
            </div>
        </div>

        <div class="glass-card scanner-card card-live">
            <h5><i class="fas fa-video me-2"></i>SCANNER</h5>
            <div style="position: relative; width: 100%; display: flex; justify-content: center;">
                <video id="video-preview" style="width: 100%; border-radius: 8px;"></video>
                <div id="scan-region-highlight" style="position: absolute; display: none; left: 0; top: 0; width: 100%; height: 100%; pointer-events: none; box-shadow: rgb(0 255 0 / 50%) 0px 0px 50px 15px inset;"></div>
            </div>
            <select id="camera-select" class="form-select form-select-sm mt-2"></select>
            <div id="error-log" class="alert alert-danger" style="display: none;"></div>
        </div>
    </div>

    <!-- Bottom ticker (seamless loop, no gap even for short text) -->
    <?php $running_text = htmlspecialchars($settings['running_text'] ?? 'Selamat datang di PT. Sulfindo Adiusaha. Utamakan keselamatan dan kesehatan kerja.'); ?>
    <div class="ticker-bar">
        <div class="ticker-track" id="ticker-track">
            <span class="ticker-item"><?php echo $running_text; ?></span>
            <span class="ticker-item"><?php echo $running_text; ?></span>
            <span class="ticker-item"><?php echo $running_text; ?></span>
        </div>
    </div>

    <!-- Big centered popup shown on a fresh scan -->
    <div id="scan-result-popup" class="scan-popup">
        <div class="scan-popup-inner">
            <img id="scan-popup-photo" src="" alt="Photo">
            <h1 class="display-4" id="scan-popup-name"></h1>
            <h2 id="scan-popup-company"></h2>
            <h1 class="display-2" id="scan-popup-type"></h1>
            <p class="fs-2" id="scan-popup-time"></p>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script type="module">
        import QrScanner from "./assets/qr-scanner/qr-scanner.min.js";
        QrScanner.WORKER_PATH = "./assets/qr-scanner/qr-scanner-worker.min.js";

        const videoElement = document.getElementById('video-preview');
        const cameraSelect = document.getElementById('camera-select');
        const errorLog = document.getElementById('error-log');
        const scanRegionHighlight = document.getElementById('scan-region-highlight');
        let currentQrScanner = null;
        let bannedContractorsList = <?php echo json_encode($banned_contractors ?? []); ?>;
        let lastActivityTime = Date.now();
        let slideshowInterval = null;
        let popupTimeout = null;

        function showScanFeedback() {
            scanRegionHighlight.style.display = 'block';
            setTimeout(() => { scanRegionHighlight.style.display = 'none'; }, 400);
        }

        function showBannedSlideshow(container) {
            if (slideshowInterval) clearInterval(slideshowInterval);
            let index = 0;
            const banned = bannedContractorsList;
            if (banned.length === 0) return;

            function showNext() {
                const contractor = banned[index];
                container.innerHTML = `
                    <div class="text-center position-relative">
                        <img src="${contractor.photo}" class="img-fluid mb-2 shadow-sm" style="width: 110px; height: 110px; object-fit: contain; object-position: top; background-color: rgba(255,255,255,0.08); border-radius: 8px;" alt="Photo">
                        <div class="position-absolute top-50 start-50 translate-middle fw-bold" style="color:#ff6b6b; transform: rotate(-30deg) translate(-50%, -50%); transform-origin: center; font-size: 22px; text-shadow: 1px 1px 2px black, -1px -1px 2px black; letter-spacing: 2px;">BANNED</div>
                        <h6 class="mb-0">${contractor.name}</h6>
                        <p class="mb-0" style="font-size: 0.8rem; opacity: 0.85;">${contractor.company_name}</p>
                    </div>
                `;
                index = (index + 1) % banned.length;
            }
            showNext();
            slideshowInterval = setInterval(showNext, 5000);
        }

        function onScanSuccess(result) {
            showScanFeedback();
            const contractorId = result.data.trim();

            const form = new FormData();
            form.append('id_card', contractorId);
            form.append('plant_location', '<?php echo addslashes($plant_name); ?>');

            fetch('index.php?page=attendance&action=scan', { method: 'POST', body: form })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        fetchUpdate();
                    } else {
                        const attendanceBox = document.getElementById('attendance-update-container');
                        let errorContent = '';
                        if (res.message.includes('BANNED')) {
                            errorContent = `<div class="text-center text-danger"><h6>ID CARD BANNED</h6><p class="mb-0" style="font-size:0.8rem;">Tidak boleh masuk.</p></div>`;
                        } else if (res.message.includes('EXPIRED')) {
                            errorContent = `<div class="text-center" style="color:#f39c12;"><h6>ID CARD EXPIRED</h6><p class="mb-0" style="font-size:0.8rem;">${res.message}</p></div>`;
                        } else {
                            errorContent = `<div class="text-center text-warning"><h6>Scan Error</h6><p class="mb-0" style="font-size:0.8rem;">${res.message}</p></div>`;
                        }
                        attendanceBox.innerHTML = errorContent;
                        setTimeout(() => { fetchUpdate(); }, 5000);
                    }
                })
                .catch(err => {
                    console.error('Scan fetch error:', err);
                    alert('A network error occurred during scan.');
                });
        }

        function startScanner(cameraId) {
            if (currentQrScanner) currentQrScanner.destroy();
            currentQrScanner = new QrScanner(videoElement, onScanSuccess, {
                highlightScanRegion: true,
                highlightCodeOutline: true,
                preferredCamera: cameraId
            });
            currentQrScanner.start().catch(err => {
                errorLog.textContent = `Failed to start scanner: ${err}`;
                errorLog.style.display = 'block';
            });
        }

        function updateClock() {
            const now = new Date();
            document.getElementById('clock').textContent = now.toLocaleTimeString('en-GB');
        }

        function fetchUpdate() {
            fetch('index.php?page=plant-display&action=getUpdate&plant=<?php echo urlencode($plant_name); ?>')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('contractor-count').textContent = data.contractor_count;
                    if (typeof data.plant_working_hours !== 'undefined') {
                        document.getElementById('man-hours-lti').textContent =
                            Math.round(data.plant_working_hours).toLocaleString('id-ID');
                    }
                    bannedContractorsList = data.banned_contractors;

                    const attendanceBox = document.getElementById('attendance-update-container');
                    const popup = document.getElementById('scan-result-popup');

                    if (popupTimeout) clearTimeout(popupTimeout);

                    if (data.last_scan) {
                        const scan = data.last_scan;
                        const scanType = scan.type === 'check-in' ? 'MASUK' : 'PULANG';
                        const scanColor = scan.type === 'check-in' ? '#2ecc71' : '#f1c40f';

                        // Small preview card
                        attendanceBox.innerHTML = `
                            <div class="text-center">
                                <img src="${scan.photo}" class="img-fluid mx-auto mb-2 shadow-sm" style="width: 110px; height: 110px; object-fit: contain; object-position: top; background-color: rgba(255,255,255,0.08); border-radius: 8px;" alt="Photo">
                                <h6 class="mt-2 fw-bold" style="font-size: 0.9rem;">${scan.name}</h6>
                                <p class="mb-1" style="font-size: 0.75rem; opacity: 0.8;">${scan.company_name}</p>
                                <h5 class="fw-bold" style="font-size: 0.9rem; color: ${scanColor};">${scanType}</h5>
                            </div>
                        `;

                        // Big centered popup over the video
                        document.getElementById('scan-popup-photo').src = scan.photo;
                        document.getElementById('scan-popup-name').textContent = scan.name;
                        document.getElementById('scan-popup-company').textContent = scan.company_name;
                        const typeEl = document.getElementById('scan-popup-type');
                        typeEl.textContent = scanType;
                        typeEl.style.color = scanColor;
                        document.getElementById('scan-popup-time').textContent = new Date(scan.time).toLocaleTimeString('en-GB');
                        popup.style.display = 'flex';

                        popupTimeout = setTimeout(() => { popup.style.display = 'none'; }, 10000);
                        lastActivityTime = Date.now();
                    } else {
                        popup.style.display = 'none';
                        if (Date.now() - lastActivityTime > 60000 && bannedContractorsList.length > 0) {
                            showBannedSlideshow(attendanceBox);
                        } else {
                            attendanceBox.innerHTML = '<div class="text-center" style="font-size:0.8rem; opacity:0.8;">Menunggu aktivitas scan...</div>';
                        }
                    }
                })
                .catch(error => console.error('Error fetching update:', error));
        }

        document.addEventListener('DOMContentLoaded', function () {
            const fullscreenBtn = document.getElementById('fullscreen-btn');
            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', () => {
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen().catch(err => console.error(`Error attempting to enable fullscreen: ${err.message}`));
                        fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
                    } else {
                        document.exitFullscreen();
                        fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
                    }
                });
            }

            updateClock();
            fetchUpdate();
            setInterval(updateClock, 1000);
            setInterval(fetchUpdate, 20000);

            // Ticker: keep a constant scroll speed (px/sec) regardless of
            // text length. Phase 1 (once): slide in from off-screen right.
            // Phase 2 (forever): seamless loop with no gap, continuing
            // exactly from where phase 1 ends.
            (function setupTicker() {
                const bar = document.querySelector('.ticker-bar');
                const track = document.getElementById('ticker-track');
                if (!bar || !track) return;
                const speedPxPerSec = 90;

                function start() {
                    const barWidth = bar.clientWidth;
                    const singleWidth = track.scrollWidth / 3; // three copies back-to-back

                    // Phase 1: start fully off-screen to the right, animate to -singleWidth.
                    track.classList.remove('looping');
                    track.style.transition = 'none';
                    track.style.transform = `translateX(${barWidth}px)`;
                    // Force reflow so the transition below actually animates.
                    void track.offsetWidth;

                    const introDistance = barWidth + singleWidth;
                    const introDuration = Math.max(introDistance / speedPxPerSec, 2);
                    track.style.transition = `transform ${introDuration}s linear`;
                    track.style.transform = `translateX(-${singleWidth}px)`;

                    track.addEventListener('transitionend', function onIntroEnd() {
                        track.removeEventListener('transitionend', onIntroEnd);
                        // Phase 2: hand off to the seamless infinite loop,
                        // starting at translateX(0) which looks identical
                        // to where phase 1 just ended (duplicated content).
                        track.style.transition = 'none';
                        track.style.transform = '';
                        const loopDuration = Math.max(singleWidth / speedPxPerSec, 4);
                        track.style.animationDuration = loopDuration + 's';
                        track.classList.add('looping');
                    }, { once: true });
                }

                start();
                window.addEventListener('resize', start);
            })();

            // Background safety video: load immediately since it's now the
            // persistent full-screen background (no longer needs lazy load).
            const safetyVideo = document.getElementById('safety-video');
            const safetyVideoUrl = '<?php echo $video_url; ?>';
            if (safetyVideo && safetyVideoUrl) {
                safetyVideo.src = safetyVideoUrl;
                safetyVideo.play().catch(() => {});
            }

            QrScanner.listCameras(true).then(cameras => {
                if (cameras.length > 0) {
                    cameras.forEach(camera => cameraSelect.add(new Option(camera.label, camera.id)));
                    startScanner(cameras[0].id);
                    cameraSelect.addEventListener('change', () => startScanner(cameraSelect.value));
                } else {
                    errorLog.textContent = 'No cameras found.';
                    errorLog.style.display = 'block';
                }
            }).catch(err => {
                errorLog.textContent = `Error listing cameras: ${err}`;
                errorLog.style.display = 'block';
            });
        });
    </script>

    <!-- Banned list modal -->
    <div class="modal fade" id="bannedListModal" tabindex="-1" aria-labelledby="bannedListModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bannedListModalLabel">Daftar Kontraktor BANNED</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm" id="banned-list-table">
                            <thead>
                                <tr><th>Photo</th><th>Nama</th><th>Perusahaan</th></tr>
                            </thead>
                            <tbody><!-- populated by JS --></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function(){
            const bannedListBtn = document.getElementById('open-banned-list');
            const bannedListTableBody = document.querySelector('#banned-list-table tbody');
            const bannedModalEl = document.getElementById('bannedListModal');
            const bannedModal = (typeof bootstrap !== 'undefined' && bannedModalEl) ? new bootstrap.Modal(bannedModalEl) : null;

            function renderBannedList() {
                if (!bannedListTableBody) return;
                bannedListTableBody.innerHTML = '';
                (bannedContractorsList || []).forEach(c => {
                    const tr = document.createElement('tr');
                    const photoTd = document.createElement('td');
                    const img = document.createElement('img');
                    img.src = c.photo || 'assets/images/placeholder-avatar.svg';
                    img.alt = c.name || '';
                    img.style.width = '64px'; img.style.height = '64px'; img.style.objectFit = 'cover';
                    img.className = 'rounded';
                    photoTd.appendChild(img);
                    tr.appendChild(photoTd);
                    const nameTd = document.createElement('td'); nameTd.textContent = c.name || '-'; tr.appendChild(nameTd);
                    const compTd = document.createElement('td'); compTd.textContent = c.company_name || '-'; tr.appendChild(compTd);
                    bannedListTableBody.appendChild(tr);
                });
            }

            if (bannedListBtn) {
                bannedListBtn.addEventListener('click', function(){
                    renderBannedList();
                    if (bannedModal) bannedModal.show();
                });
            }

            document.addEventListener('DOMContentLoaded', function(){
                if ((bannedContractorsList || []).length > 0) renderBannedList();
            });
        })();
    </script>
</body>
</html>
