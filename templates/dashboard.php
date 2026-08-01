<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-1"><i class="fas fa-sync-alt me-2 text-primary"></i>Sinkronisasi Mobile App</h5>
                    <small class="text-muted">Tarik registrasi/sanksi baru dari HP, lalu kirim update blacklist &amp; daftar PT terbaru ke cloud.</small>
                </div>
                <div class="text-end">
                    <button id="sync-now-btn" class="btn btn-primary" type="button">
                        <i class="fas fa-sync-alt me-1"></i> Sync Now
                    </button>
                    <div id="sync-now-status" class="small mt-1"></div>
                </div>
            </div>
            <pre id="sync-now-log" class="d-none m-0 p-3 bg-dark text-light small" style="max-height:220px; overflow:auto;"></pre>
        </div>
    </div>
</div>

<div class="row">
    <!-- MAN HOURS WITHOUT LTI -->
    <div class="col-md-4">
        <div class="card stat-card bg-gradient-success mb-3">
            <i class="fas fa-shield-alt card-icon-bg"></i>
            <div class="card-body">
                <h5 class="card-title">MAN HOURS WITHOUT LTI</h5>
                <p class="card-text fs-2" id="man-hours"><?php echo number_format($settings['plant_working_hours'] ?? 0, 0, ',', '.'); ?></p>
                <a href="index.php?page=attendance">selengkapnya <i class="bi bi-arrow-right-circle"></i></a>
            </div>
        </div>
    </div>
    <!-- TOTAL KONTRAKTOR DALAM PLANT -->
    <div class="col-md-4">
        <div class="card stat-card bg-gradient-primary mb-3">
            <i class="fas fa-users card-icon-bg"></i>
            <div class="card-body">
                <h5 class="card-title">TOTAL KONTRAKTOR DALAM PLANT</h5>
                <p class="card-text fs-2" id="total-contractors"><?php echo $total_contractors_in_plant; ?></p>
                <a href="index.php?page=plant_contractors">selengkapnya <i class="bi bi-arrow-right-circle"></i></a>
            </div>
        </div>
    </div>
    <!-- Total Jenis Pelanggaran -->
    <div class="col-md-4">
        <div class="card stat-card bg-gradient-danger mb-3">
            <i class="fas fa-exclamation-triangle card-icon-bg"></i>
            <div class="card-body">
                <h5 class="card-title">TOTAL JENIS PELANGGARAN</h5>
                <p class="card-text fs-2" id="total-violations"><?php echo str_pad($total_violations, 3, '0', STR_PAD_LEFT); ?></p>
                <a href="index.php?page=settings&action=violations">selengkapnya <i class="bi bi-arrow-right-circle"></i></a>
            </div>
        </div>
    </div>
    <!-- Man Power Expired -->
    <div class="col-md-4">
        <div class="card stat-card bg-gradient-warning mb-3">
            <i class="fas fa-id-card-alt card-icon-bg"></i>
            <div class="card-body">
                <h5 class="card-title">MAN POWER EXPIRED</h5>
                <p class="card-text fs-2" id="total-expired"><?php echo str_pad($total_expired, 3, '0', STR_PAD_LEFT); ?></p>
                <a href="index.php?page=contractors&status=Expired">segera perpanjang <i class="bi bi-arrow-right-circle"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row">
<!-- Pie Chart -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2 text-primary"></i>Distribusi Kontraktor per Plant</h5>
            </div>
            <div class="card-body d-flex flex-column">
                <div class="chart-container" style="position: relative; height:300px; width:100%">
                    <canvas id="plantPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- Bar Chart -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar me-2 text-success"></i>Jumlah Kontraktor per Perusahaan</h5>
            </div>
            <div class="card-body d-flex flex-column">
                <div class="chart-container" style="position: relative; height:300px; width:100%">
                    <canvas id="companyBarChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Banned Contractors -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5><i class="fas fa-user-slash me-2"></i>DAFTAR "BANNED" KONTRAKTOR</h5>
            </div>
            <div class="card-body" id="banned-list">
                <?php foreach ($banned_contractors as $banned): ?>
                <div class="banned-item">
                    <?php if (!empty($banned['photo'])):
 ?>
                        <?php
                            // NOTE: this vhost's document root already points at the
                            // `public/` folder, so don't add an extra "/public" segment.
                            $base_url = 'http://192.168.20.17:8081/idcard';
                            $photo_src = $base_url . '/uploads/photos/' . htmlspecialchars($banned['photo']);
                        ?>
                        <img src="<?php echo $photo_src; ?>" alt="Photo of <?php echo htmlspecialchars($banned['name']); ?>" class="banned-photo rounded-circle" />
                    <?php else: ?>
                        <i class="bi bi-person-x-fill fs-1 text-danger"></i>
                    <?php endif; ?>
                    <div>
                        <strong>NAMA:</strong> <?php echo htmlspecialchars($banned['name']); ?><br>
                        <strong>ID CARD:</strong> <?php echo htmlspecialchars($banned['id_card']); ?><br>
                        <strong>PERUSAHAAN:</strong> <?php echo htmlspecialchars($banned['company_name']); ?><br>
                        <strong>REASON:</strong> <?php echo htmlspecialchars($banned['reason']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Pie Chart Data
    const pieCtx = document.getElementById('plantPieChart').getContext('2d');
    new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_keys($plant_distribution)); ?>,
            datasets: [{
                label: 'Kontraktor',
                data: <?php echo json_encode(array_values($plant_distribution)); ?>,
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)', // CA
                    'rgba(255, 193, 7, 0.7)',  // EDC
                    'rgba(220, 53, 69, 0.7)', // VCM
                    'rgba(0, 123, 255, 0.7)' // PVC
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });

    // Bar Chart Data
    const barCtx = document.getElementById('companyBarChart').getContext('2d');
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_keys($company_count)); ?>,
            datasets: [{
                label: 'Jumlah Kontraktor',
                data: <?php echo json_encode(array_values($company_count)); ?>,
                backgroundColor: [
                    'rgba(0, 123, 255, 0.7)',
                    'rgba(40, 167, 69, 0.7)',
                    'rgba(255, 193, 7, 0.7)',
                    'rgba(220, 53, 69, 0.7)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    function updateDashboard() {
        fetch('index.php?page=dashboard&action=getUpdate')
            .then(response => response.json())
            .then(data => {
                document.getElementById('man-hours').textContent = new Intl.NumberFormat('id-ID').format(data.plant_working_hours);
                document.getElementById('total-contractors').textContent = data.total_contractors_in_plant;
                document.getElementById('total-violations').textContent = String(data.total_violations).padStart(3, '0');
            })
            .catch(error => console.error('Error updating dashboard:', error));
    }

    // Update every hour (3600000 milliseconds)
    setInterval(updateDashboard, 3600000);
});

document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('sync-now-btn');
    const status = document.getElementById('sync-now-status');
    const log = document.getElementById('sync-now-log');
    if (!btn) return;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyinkronkan...';
        status.textContent = '';
        log.classList.add('d-none');

        fetch('sync_now.php', { method: 'POST' })
            .then((r) => r.json())
            .then((data) => {
                status.textContent = data.ok
                    ? 'Sync berhasil.'
                    : 'Sync gagal (lihat log di bawah).';
                status.className = 'small mt-1 ' + (data.ok ? 'text-success' : 'text-danger');
                if (data.log) {
                    log.textContent = data.log;
                    log.classList.remove('d-none');
                }
            })
            .catch((e) => {
                status.textContent = 'Gagal menghubungi server: ' + e;
                status.className = 'small mt-1 text-danger';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Sync Now';
            });
    });
});
</script>
