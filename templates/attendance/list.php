<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">Daftar Kehadiran</h2>
    <div>
        <?php
            $qs = $_GET;
            $qs['page'] = 'attendance';
            $qs['action'] = 'export';
            $base_qs = http_build_query($qs);
        ?>
        <a href="index.php?<?php echo $base_qs; ?>&format=csv" class="btn btn-success">Export CSV</a>
        <a href="index.php?<?php echo $base_qs; ?>&format=xlsx" class="btn btn-success">Export XLSX</a>
    </div>
</div>

<!-- Filters -->
<div class="filter-section">
<form method="GET" class="row g-3">
    <input type="hidden" name="page" value="attendance">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control" placeholder="Cari nama, ID Card..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-md-2">
        <select name="company_id" class="form-select">
            <option value="">Semua Perusahaan</option>
            <?php foreach ($companies as $comp): ?>
                <option value="<?php echo $comp['id']; ?>" <?php echo isset($company_id) && $company_id == $comp['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($comp['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="plant" class="form-select">
            <option value="">Semua Plant</option>
            <option value="CA PLANT" <?php echo $plant == 'CA PLANT' ? 'selected' : ''; ?>>CA PLANT</option>
            <option value="EDC PLANT" <?php echo $plant == 'EDC PLANT' ? 'selected' : ''; ?>>EDC PLANT</option>
            <option value="VCM PLANT" <?php echo $plant == 'VCM PLANT' ? 'selected' : ''; ?>>VCM PLANT</option>
            <option value="PVC PLANT" <?php echo $plant == 'PVC PLANT' ? 'selected' : ''; ?>>PVC PLANT</option>

        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
    </div>
    <div class="col-md-2">
        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
    </div>
    <div class="col-md-1">
        <button type="submit" class="btn btn-secondary">Filter</button>
    </div>
</form>
</div>

<!-- Table -->
<div class="table-responsive">
    <table class="table log-table">
        <thead>
            <tr>
                <th>ID Card</th>
                <th>Nama</th>
                <th>Perusahaan</th>
                <th>Plant</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Jam Kerja</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attendances as $attendance): ?>
            <tr>
                <td><?php echo htmlspecialchars($attendance['id_card']); ?></td>
                <td><?php echo htmlspecialchars($attendance['contractor_name']); ?></td>
                <td><?php echo htmlspecialchars($attendance['company_name']); ?></td>
                <td><?php echo htmlspecialchars($attendance['plant_location']); ?></td>
                <td><?php echo htmlspecialchars($attendance['check_in_time']); ?></td>
                <td><?php echo $attendance['check_out_time'] !== null ? htmlspecialchars($attendance['check_out_time']) : '-'; ?></td>
                <td><?php echo $attendance['work_hours'] !== null ? number_format($attendance['work_hours'], 2) : '-'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../partials/pagination.php'; ?>

<!-- Totals -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clock me-2 text-primary"></i>Total Jam Kerja
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Hari Ini
                    <span class="badge bg-primary rounded-pill"><?php echo number_format($totals['today'] ?? 0, 2); ?> jam</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Minggu Ini
                    <span class="badge bg-primary rounded-pill"><?php echo number_format($totals['week'] ?? 0, 2); ?> jam</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Bulan Ini
                    <span class="badge bg-primary rounded-pill"><?php echo number_format($totals['month'] ?? 0, 2); ?> jam</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Tahun Ini
                    <span class="badge bg-primary rounded-pill"><?php echo number_format($totals['year'] ?? 0, 2); ?> jam</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center fw-bold">
                    Keseluruhan
                    <span class="badge bg-success rounded-pill"><?php echo number_format($totals['all'] ?? 0, 2); ?> jam</span>
                </li>
            </ul>
        </div>
    </div>
</div>
