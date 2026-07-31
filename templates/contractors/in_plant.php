<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">Daftar Kontraktor di Dalam Plant</h2>
</div>

<!-- Filters -->
<div class="filter-section">
<form method="GET" class="row g-3">
    <input type="hidden" name="page" value="plant_contractors">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Cari nama, ID Card..." value="<?php echo htmlspecialchars($search); ?>">
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
                <th>Durasi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contractors as $contractor): ?>
            <?php
                $check_in_time = new DateTime($contractor['check_in_time']);
                $now = new DateTime();
                $duration = $now->diff($check_in_time);
            ?>
            <tr>
                <td><?php echo htmlspecialchars($contractor['id_card']); ?></td>
                <td><?php echo htmlspecialchars($contractor['name']); ?></td>
                <td><?php echo htmlspecialchars($contractor['company_name']); ?></td>
                <td><?php echo htmlspecialchars($contractor['plant_location']); ?></td>
                <td><?php echo htmlspecialchars($contractor['check_in_time']); ?></td>
                <td><?php echo $duration->format('%h jam %i menit'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../partials/pagination.php'; ?>
