<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Man Power Expired</h2>
    <div>
        <a href="index.php?page=contractors" class="btn btn-outline-secondary">Kembali ke Semua Kontraktor</a>
    </div>
</div>

<div class="alert alert-warning">
    Kontraktor di bawah ini masa berlaku ID card-nya <strong>sudah habis</strong> dan
    <strong>tidak bisa lagi check-in</strong> di plant manapun sampai diperpanjang.
    Klik <strong>Perpanjang</strong> untuk update tanggal expired (dan KTP kalau perlu).
</div>

<!-- Filters -->
<div class="filter-section">
<form method="GET" class="row g-3">
    <input type="hidden" name="page" value="expired_contractors">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Cari nama, ID Card, KTP..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-md-3">
        <select name="company_id" class="form-select">
            <option value="">Semua Perusahaan</option>
            <?php foreach ($companies as $comp): ?>
                <option value="<?php echo $comp['id']; ?>" <?php echo isset($company_id) && $company_id == $comp['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($comp['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
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
        <a href="index.php?page=expired_contractors" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>
</div>

<!-- Table -->
<div class="table-responsive">
    <table class="table log-table">
        <thead>
            <tr>
                <th>Photo</th>
                <th>ID Card</th>
                <th>Nama</th>
                <th>Perusahaan</th>
                <th>Plant</th>
                <th>Status</th>
                <th>Tanggal Expired</th>
                <th>Sudah Lewat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contractors)): ?>
            <tr>
                <td colspan="9" class="text-center text-muted py-4">
                    Tidak ada man power yang expired saat ini. 🎉
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($contractors as $contractor): ?>
            <?php
                $photo_src = !empty($contractor['photo'])
                    ? 'uploads/photos/' . htmlspecialchars($contractor['photo'])
                    : 'assets/images/placeholder-avatar.svg';
                $days_overdue = (int) floor((strtotime('today') - strtotime($contractor['expiry_date'])) / 86400);
            ?>
            <tr>
                <td><img src="<?php echo $photo_src; ?>" alt="Photo" width="50" height="50" class="rounded-circle"></td>
                <td><?php echo htmlspecialchars($contractor['id_card']); ?></td>
                <td><?php echo htmlspecialchars($contractor['name']); ?></td>
                <td><?php echo htmlspecialchars($contractor['company_name']); ?></td>
                <td><?php echo htmlspecialchars($contractor['plant_location'] ?? ''); ?></td>
                <td>
                    <span class="badge bg-<?php echo $contractor['status'] == 'Active' ? 'success' : ($contractor['status'] == 'Banned' ? 'danger' : 'secondary'); ?>">
                        <?php echo htmlspecialchars($contractor['status']); ?>
                    </span>
                </td>
                <td><?php echo htmlspecialchars($contractor['expiry_date'] ?? ''); ?></td>
                <td>
                    <span class="badge bg-warning text-dark">
                        <?php echo $days_overdue; ?> hari
                    </span>
                </td>
                <td>
                    <a href="index.php?page=contractors&action=edit&id=<?php echo $contractor['id']; ?>" class="btn btn-sm btn-warning">
                        <i class="bi bi-arrow-repeat"></i> Perpanjang
                    </a>
                    <a href="index.php?page=sanctions&action=history&contractor_id=<?php echo $contractor['id']; ?>" class="btn btn-sm btn-dark" target="_blank">History</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../partials/pagination.php'; ?>
