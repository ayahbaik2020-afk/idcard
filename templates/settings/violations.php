<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Master Data Jenis Pelanggaran</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createViolationModal">Tambah Pelanggaran</button>
</div>

<div class="table-responsive">
<table class="table table-striped">
    <thead>
        <tr>
            <th>Nama Pelanggaran</th>
            <th>Deskripsi</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($violations as $violation): ?>
        <tr>
            <td><?php echo htmlspecialchars($violation['name']); ?></td>
            <td><?php echo htmlspecialchars($violation['description']); ?></td>
            <td>
                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editViolationModal" onclick="editViolation(<?php echo $violation['id']; ?>, '<?php echo htmlspecialchars($violation['name']); ?>', '<?php echo htmlspecialchars($violation['description']); ?>')">Edit</button>
                <a href="index.php?page=settings&action=deleteViolation&id=<?php echo $violation['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus pelanggaran ini?')">Hapus</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- Create Violation Modal -->
<div class="modal fade" id="createViolationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="index.php?page=settings&action=createViolation" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pelanggaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Pelanggaran</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Violation Modal -->
<div class="modal fade" id="editViolationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" id="editViolationForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pelanggaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nama Pelanggaran</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editViolation(id, name, description) {
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('editViolationForm').action = 'index.php?page=settings&action=updateViolation&id=' + id;
}
</script>
