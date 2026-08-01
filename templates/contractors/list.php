<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">Daftar Kontraktor</h2>
    <div>
        <a href="index.php?page=contractors&action=create" class="btn btn-primary">Tambah Kontraktor</a>
        <a href="index.php?page=contractors&action=import" class="btn btn-info">Import CSV</a>
        <!-- Export buttons: preserve current filters via query string -->
        <?php
            // Build current query string preserving filters
            $qs = $_GET;
            $qs['page'] = 'contractors';
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
    <input type="hidden" name="page" value="contractors">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control" placeholder="Cari nama, ID Card, KTP..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">Semua Status</option>
            <option value="Active" <?php echo $status == 'Active' ? 'selected' : ''; ?>>Active</option>
            <option value="Banned" <?php echo $status == 'Banned' ? 'selected' : ''; ?>>Banned</option>
            <option value="Non-Active" <?php echo $status == 'Non-Active' ? 'selected' : ''; ?>>Non-Active</option>
            <option value="Expired" <?php echo $status == 'Expired' ? 'selected' : ''; ?>>⚠ Expired</option>
        </select>
    </div>
    <div class="col-md-2">
        <select name="company_id" class="form-select">
            <option value="">Semua Perusahaan</option>
            <?php foreach ($companies as $comp): ?>
                <option value="<?php echo $comp['id']; ?>" <?php echo isset($company_id) && $company_id == $comp['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($comp['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-1">
        <input type="number" name="day" class="form-control" placeholder="Hari" min="1" max="31" value="<?php echo htmlspecialchars($day ?? ''); ?>">
    </div>
    <div class="col-md-1">
        <input type="number" name="month" class="form-control" placeholder="Bulan" min="1" max="12" value="<?php echo htmlspecialchars($month ?? ''); ?>">
    </div>
    <div class="col-md-1">
        <input type="number" name="year" class="form-control" placeholder="Tahun" min="1900" max="2100" value="<?php echo htmlspecialchars($year ?? ''); ?>">
    </div>
    <div class="col-md-2">
        <select name="sanksi" class="form-select">
            <option value="">Semua Sanksi</option>
            <option value="with" <?php echo isset($sanksi) && $sanksi === 'with' ? 'selected' : ''; ?>>Dengan Sanksi</option>
            <option value="without" <?php echo isset($sanksi) && $sanksi === 'without' ? 'selected' : ''; ?>>Tanpa Sanksi</option>
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
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="index.php?page=contractors" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>
</div>

<!-- Table -->
<form action="index.php?page=contractors&action=bulkPrint" method="POST" target="_blank">
    <div class="table-responsive">
        <table class="table log-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>Photo</th>
                    <th>ID Card</th>
                    <th>Nama</th>
                    <th>Perusahaan</th>
                    <th>Plant</th>
                    <th>Status</th>
                    <th>Tanggal Registrasi</th>
                    <th>Tanggal Expired</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contractors as $contractor): ?>
                <tr>
                    <td><input type="checkbox" name="contractor_ids[]" value="<?php echo $contractor['id']; ?>"></td>
                    <td>
                        <?php
                            $photo_src = !empty($contractor['photo'])
                                ? 'uploads/photos/' . htmlspecialchars($contractor['photo'])
                                : 'assets/images/placeholder-avatar.svg';
                        ?>
                        <img src="<?php echo $photo_src; ?>" alt="Photo" width="50" height="50" class="rounded-circle">
                    </td>
                    <td><?php echo htmlspecialchars($contractor['id_card']); ?></td>
                    <td><?php echo htmlspecialchars($contractor['name']); ?></td>
                    <td><?php echo htmlspecialchars($contractor['company_name']); ?></td>
                    <td><?php echo htmlspecialchars($contractor['plant_location']); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $contractor['status'] == 'Active' ? 'success' : ($contractor['status'] == 'Banned' ? 'danger' : 'secondary'); ?>">
                            <?php echo htmlspecialchars($contractor['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($contractor['registration_date']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($contractor['expiry_date']); ?>
                        <?php if (!empty($contractor['expiry_date']) && $contractor['expiry_date'] < date('Y-m-d')): ?>
                            <span class="badge bg-warning text-dark ms-1">Expired</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="index.php?page=contractors&action=edit&id=<?php echo $contractor['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="index.php?page=contractors&action=delete&id=<?php echo $contractor['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus?')">Hapus</a>
                        <a href="index.php?page=contractors&action=printIdCard&id=<?php echo $contractor['id']; ?>" class="btn btn-sm btn-info" target="_blank">Print ID</a>
                        <a href="index.php?page=sanctions&action=history&contractor_id=<?php echo $contractor['id']; ?>" class="btn btn-sm btn-dark" target="_blank">History Sanksi</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <button type="submit" class="btn btn-primary">Print Selected ID Cards</button>
</form>

<script>
document.getElementById('select-all').addEventListener('click', function(event) {
    var checkboxes = document.querySelectorAll('input[name="contractor_ids[]"]');
    for (var checkbox of checkboxes) {
        checkbox.checked = event.target.checked;
    }
});
</script>