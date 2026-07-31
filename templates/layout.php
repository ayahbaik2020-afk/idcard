<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card System</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css?v=2">
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="bi bi-person-vcard-fill"></i>
            <span>ID CARD SYSTEM</span>
        </div>
        <?php $currentPage = $_GET['page'] ?? 'dashboard'; ?>
        <?php $currentAction = $_GET['action'] ?? ''; ?>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>" href="index.php?page=dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'contractors') ? 'active' : ''; ?>" href="index.php?page=contractors"><i class="bi bi-people-fill"></i> Kontraktor</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'attendance') ? 'active' : ''; ?>" href="index.php?page=attendance"><i class="bi bi-calendar-check-fill"></i> Kehadiran</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'plant_contractors') ? 'active' : ''; ?>" href="index.php?page=plant_contractors"><i class="bi bi-building"></i> Kontraktor di Plant</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'sanctions') ? 'active' : ''; ?>" href="index.php?page=sanctions"><i class="bi bi-exclamation-triangle-fill"></i> Sanksi</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'settings' && $currentAction == 'companies') ? 'active' : ''; ?>" href="index.php?page=settings&action=companies"><i class="bi bi-buildings-fill"></i> Perusahaan</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'settings' && $currentAction == 'violations') ? 'active' : ''; ?>" href="index.php?page=settings&action=violations"><i class="bi bi-shield-fill-exclamation"></i> Jenis Pelanggaran</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'settings' && $currentAction == 'user') ? 'active' : ''; ?>" href="index.php?page=settings&action=user"><i class="bi bi-person-fill-gear"></i> User</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'settings' && $currentAction == 'system') ? 'active' : ''; ?>" href="index.php?page=settings&action=system"><i class="bi bi-gear-fill"></i> Setting</a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'plant-display') ? 'active' : ''; ?>" href="https://192.168.20.17:8443/idcard/index.php?page=plant-display"><i class="bi bi-display"></i> Plant Display</a>
            </li>
        </ul>
    </div>
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <button class="btn btn-light d-md-none me-3" type="button" id="sidebar-toggle">
                    <i class="bi bi-list"></i>
                </button>
                <span class="navbar-brand"><?php echo ucfirst($currentPage); ?></span>
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-4 me-2"></i>
                        <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end text-small shadow" aria-labelledby="dropdownUser1">
                        <li><a class="dropdown-item" href="index.php?page=logout">Sign out</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <main class="content">
            <?php if (!empty($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php echo $content; ?>
        </main>
    </div>
    <div class="sidebar-overlay d-md-none"></div>
    <?php else: ?>
        <main class="container-fluid">
            <?php echo $content; ?>
        </main>
    <?php endif; ?>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/chart.umd.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                });
            }

            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            }
        });
    </script>
</body>
</html>
