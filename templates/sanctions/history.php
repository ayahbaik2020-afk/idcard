<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">HISTORI SANKSI</h2>
    <a href="index.php?page=contractors" class="btn btn-secondary">Kembali ke Daftar Kontraktor</a>
</div>

<?php if ($contractor): ?>
<div class="card border-primary mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                <?php if (!empty($contractor['photo'])): ?>
                    <img src="/idcard/uploads/photos/<?php echo htmlspecialchars($contractor['photo']); ?>" alt="Photo of <?php echo htmlspecialchars($contractor['name']); ?>" class="rounded-circle" style="width:72px;height:72px;object-fit:cover;" />
                <?php else: ?>
                    <i class="bi bi-person-fill fs-1 text-primary" style="font-size:5rem !important;"></i>
                <?php endif; ?>
            </div>
            <div class="col">
                <span class="fs-5 fw-bold"><?php echo htmlspecialchars($contractor['name']); ?></span>
                <span class="ms-2 badge bg-<?php echo $contractor['status'] == 'Active' ? 'success' : ($contractor['status'] == 'Banned' ? 'danger' : 'secondary'); ?>">
                    <?php echo htmlspecialchars($contractor['status']); ?>
                </span>
                <div class="text-muted">
                    ID Card: <strong><?php echo htmlspecialchars($contractor['id_card']); ?></strong> &nbsp;|&nbsp;
                    Perusahaan: <?php echo htmlspecialchars($contractor['company_name']); ?> &nbsp;|&nbsp;
                    Plant: <?php echo htmlspecialchars($contractor['plant_location']); ?>
                </div>
            </div>
            <div class="col-auto text-center">
                <div class="fs-2 fw-bold text-danger"><?php echo $total_count; ?></div>
                <div class="text-muted small">kali kena sanksi</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($sanctions)): ?>
<div class="alert alert-info">Kontraktor ini tidak punya catatan sanksi.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table log-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Jenis Sanksi</th>
                <th>Pelanggaran</th>
                <th>Periode</th>
                <th>Status</th>
                <th>Alasan</th>
                <th>Asal Data</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sanctions as $i => $s): ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td>
                    <span class="badge bg-<?php echo $s['sanction_type'] == 'BANNED' ? 'danger' : 'warning text-dark'; ?>">
                        <?php echo htmlspecialchars($s['sanction_type']); ?>
                    </span>
                    <?php if ($s['is_permanent'] == 1): ?>
                        <span class="badge bg-dark">PERMANEN</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($s['violation_name'] ?? '-'); ?></td>
                <td>
                    <?php echo htmlspecialchars($s['start_date']); ?>
                    <?php if ($s['is_permanent'] == 1): ?>
                        <span class="text-muted">(permanen)</span>
                    <?php else: ?>
                        s/d <?php echo htmlspecialchars($s['end_date'] ?? '-'); ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($s['status'] == 'Dicabut'): ?>
                        <span class="badge bg-secondary">Dicabut</span>
                    <?php elseif ($s['status'] == 'Berlaku' || $s['status'] == 'Berlaku (permanen)'): ?>
                        <span class="badge bg-success"><?php echo htmlspecialchars($s['status']); ?></span>
                    <?php else: ?>
                        <span class="badge bg-dark">Selesai</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($s['reason'] ?? '-'); ?></td>
                <td>
                    <span class="badge bg-<?php echo ($s['source'] ?? 'local') == 'mobile' ? 'info' : 'secondary'; ?>">
                        <?php echo htmlspecialchars($s['source'] ?? 'local'); ?>
                    </span>
                </td>
                <td>
                    <a href="index.php?page=sanctions&action=edit&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                    <?php if (empty($s['revoked_at']) && ($s['is_permanent'] == 1 || empty($s['end_date']) || $s['end_date'] >= date('Y-m-d'))): ?>
                        <a href="index.php?page=sanctions&action=release&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-dark" onclick="return confirm('Akhiri sanksi ini sekarang?')">Release</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
