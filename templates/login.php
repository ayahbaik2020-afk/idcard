<?php
$content = '';
ob_start();
?>
<div class="login-wrapper">
    <div class="card login-card" style="width: min(22rem, 100%);">
        <div class="card-body">
            <h3 class="card-title text-center mb-4">ID Card System Login</h3>
            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger">Invalid email or password.</div>
            <?php endif; ?>
            <form action="index.php?page=login" method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
