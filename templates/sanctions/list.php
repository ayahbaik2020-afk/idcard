<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">DAFTAR "BANNED" KONTRAKTOR</h2>
    <a href="index.php?page=sanctions&action=create" class="btn btn-danger">Input Pelanggaran</a>
</div>

<div class="row">
    <?php foreach ($banned_contractors as $banned): ?>
    <div class="col-12 mb-3">
        <div class="card border-danger">
        <div class="card-body">
            <div class="d-flex align-items-start">
                    <?php if (!empty($banned['photo'])): ?>
                        <img src="/idcard/uploads/photos/<?php echo htmlspecialchars($banned['photo']); ?>" alt="Photo of <?php echo htmlspecialchars($banned['name']); ?>" class="banned-photo me-4" />
                    <?php else: ?>
                        <i class="bi bi-person-x-fill fs-1 text-danger me-4" style="font-size: 8rem !important;"></i>
                    <?php endif; ?>
                <div class="flex-grow-1">
                    <div class="row">
                        <div class="col-md-4">
                            <strong class="text-muted-custom">NAMA:</strong><br>
                            <span class="fs-5 fw-bold"><?php echo htmlspecialchars($banned['name']); ?></span>
                        </div>
                        <div class="col-md-4">
                            <strong class="text-muted-custom">ID CARD:</strong><br>
                            <span class="fs-5 fw-bold text-primary"><?php echo htmlspecialchars($banned['id_card']); ?></span>
                        </div>
                        <div class="col-md-4">
                            <strong class="text-muted-custom">PERUSAHAAN:</strong><br>
                            <span class="fs-5 fw-bold"><?php echo htmlspecialchars($banned['company_name']); ?></span>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="row">
                        <div class="col-md-4">
                            <strong class="text-muted-custom">SANKSI:</strong><br>
                            <span class="badge bg-danger"><?php echo htmlspecialchars($banned['sanction_type']); ?></span>
                        </div>
                        <div class="col-md-4">
                            <strong class="text-muted-custom">REASON:</strong><br>
                            <?php echo htmlspecialchars($banned['reason']); ?>
                        </div>
                        <div class="col-md-4">
                            <strong class="text-muted-custom">PERIODE:</strong><br>
                            <strong>START:</strong> <?php echo htmlspecialchars($banned['start_date']); ?><br>
                            <?php if ($banned['is_permanent']): ?>
                            <span class="badge bg-dark">PERMANENT</span>
                            <?php else: ?>
                            <strong>END:</strong> <?php echo htmlspecialchars($banned['end_date']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="index.php?page=sanctions&action=edit&id=<?php echo $banned['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($banned_contractors)):
 ?>
<div class="alert alert-info">Tidak ada kontraktor yang di-banned saat ini.</div>
<?php endif; ?>