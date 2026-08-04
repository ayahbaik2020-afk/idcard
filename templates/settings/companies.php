<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Master Data Perusahaan Kontraktor</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCompanyModal">Tambah Perusahaan</button>
</div>

<div class="table-responsive">
<table class="table table-striped">
    <thead>
        <tr>
            <th>Nama Perusahaan</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($companies as $company): ?>
        <tr>
            <td><?php echo htmlspecialchars($company['name']); ?></td>
            <td>
                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editCompanyModal" onclick="editCompany(<?php echo $company['id']; ?>, '<?php echo htmlspecialchars($company['name']); ?>')">Edit</button>
                <a href="index.php?page=settings&action=deleteCompany&id=<?php echo $company['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus perusahaan ini?')">Hapus</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- Create Company Modal -->
<div class="modal fade" id="createCompanyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="index.php?page=settings&action=createCompany" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Perusahaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Perusahaan</label>
                        <input type="text" class="form-control" name="name" required>
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

<!-- Edit Company Modal -->
<div class="modal fade" id="editCompanyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" id="editCompanyForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Perusahaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nama Perusahaan</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
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
function editCompany(id, name) {
    document.getElementById('edit_name').value = name;
    document.getElementById('editCompanyForm').action = 'index.php?page=settings&action=updateCompany&id=' + id;
}
</script>
