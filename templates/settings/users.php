<?php $isSuperAdmin = ($_SESSION['user_role'] ?? '') === 'Super Admin'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">Pengaturan Pengguna</h2>
    <?php if ($isSuperAdmin): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">Tambah User</button>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['errors']) && !empty($_SESSION['errors'])): ?>
<div class="alert alert-danger">
    <ul>
        <?php foreach ($_SESSION['errors'] as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php unset($_SESSION['errors']); ?>
<?php endif; ?>

<table class="table log-table">
    <thead>
        <tr>
            <th>Nama</th>
            <th>Email</th>
            <th>Role</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
        <?php $isOwnSuperAdmin = ($user['role'] === 'Super Admin'); ?>
        <tr>
            <td><?php echo htmlspecialchars($user['name']); ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td><span class="badge bg-info"><?php echo htmlspecialchars($user['role']); ?></span></td>
            <td>
                <?php if ($isSuperAdmin): ?>
                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['role']); ?>')">Edit</button>
                <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#changePasswordModal" onclick="changeUserPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">Ganti Password</button>
                <?php if (!$isOwnSuperAdmin): ?>
                <a href="index.php?page=settings&action=deleteUser&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus user ini?')">Hapus</a>
                <?php endif; ?>
                <?php else: ?>
                <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="index.php?page=settings&action=createUser" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="Super Admin">Super Admin</option>
                            <option value="Admin Plant">Admin Plant</option>
                            <option value="User">User</option>
                        </select>
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

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" id="editUserForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nama</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="Super Admin">Super Admin</option>
                            <option value="Admin Plant">Admin Plant</option>
                            <option value="User">User</option>
                        </select>
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

<!-- Change Password Modal -->
<?php if ($isSuperAdmin): ?>
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" id="changePasswordForm">
                <div class="modal-header">
                    <h5 class="modal-title">Ganti Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Mengganti password untuk user: <strong id="cp_user_name"></strong></p>
                    <div class="mb-3">
                        <label for="cp_password" class="form-label">Password Baru</label>
                        <input type="password" class="form-control" id="cp_password" name="password" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label for="cp_password_confirm" class="form-label">Konfirmasi Password</label>
                        <input type="password" class="form-control" id="cp_password_confirm" name="password_confirm" minlength="6" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function editUser(id, name, email, role) {
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('editUserForm').action = 'index.php?page=settings&action=updateUser&id=' + id;
}
function changeUserPassword(id, name) {
    document.getElementById('cp_user_name').textContent = name;
    document.getElementById('cp_password').value = '';
    document.getElementById('cp_password_confirm').value = '';
    document.getElementById('changePasswordForm').action = 'index.php?page=settings&action=changeUserPassword&id=' + id;
}
</script>
