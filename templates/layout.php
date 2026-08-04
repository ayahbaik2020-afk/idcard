<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card System</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/css/animate.min.css">
    <link rel="stylesheet" href="assets/css/hover-min.css">
    <link rel="stylesheet" href="assets/dashboard.css?v=4">
</head>
<body class="<?php echo (($_GET['page'] ?? 'dashboard') === 'dashboard') ? 'page-dashboard' : ''; ?>">
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="bi bi-person-vcard-fill"></i>
            <span>ID CARD SYSTEM</span>
        </div>
        <div class="sidebar-inner">
            <?php $currentPage = $_GET['page'] ?? 'dashboard'; ?>
            <?php $currentAction = $_GET['action'] ?? ''; ?>
            <ul class="nav flex-column">
                <li class="sidebar-group-title">Menu</li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>" href="index.php?page=dashboard"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'contractors') ? 'active' : ''; ?>" href="index.php?page=contractors"><i class="bi bi-people-fill"></i><span>Kontraktor</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'attendance') ? 'active' : ''; ?>" href="index.php?page=attendance"><i class="bi bi-calendar-check-fill"></i><span>Kehadiran</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'plant_contractors') ? 'active' : ''; ?>" href="index.php?page=plant_contractors"><i class="bi bi-building"></i><span>Kontraktor di Plant</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'expired_contractors') ? 'active' : ''; ?>" href="index.php?page=expired_contractors"><i class="bi bi-exclamation-triangle-fill text-warning"></i><span>Man Power Expired</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'sanctions') ? 'active' : ''; ?>" href="index.php?page=sanctions"><i class="bi bi-exclamation-triangle-fill"></i><span>Sanksi</span></a>
                </li>
                <li class="sidebar-group-title">Pengaturan</li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'settings' && $currentAction == 'companies') ? 'active' : ''; ?>" href="index.php?page=settings&action=companies"><i class="bi bi-buildings-fill"></i><span>Perusahaan</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'settings' && $currentAction == 'violations') ? 'active' : ''; ?>" href="index.php?page=settings&action=violations"><i class="bi bi-shield-fill-exclamation"></i><span>Jenis Pelanggaran</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'settings' && $currentAction == 'user') ? 'active' : ''; ?>" href="index.php?page=settings&action=user"><i class="bi bi-person-fill-gear"></i><span>User</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'settings' && $currentAction == 'system') ? 'active' : ''; ?>" href="index.php?page=settings&action=system"><i class="bi bi-gear-fill"></i><span>Setting</span></a>
                </li>
                <li class="sidebar-group-title">Lainnya</li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'plant-display') ? 'active' : ''; ?>" href="https://192.168.20.17:8443/idcard/index.php?page=plant-display"><i class="bi bi-display"></i><span>Plant Display</span></a>
                </li>
            </ul>
        </div>
    </div>
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <button class="btn btn-light me-3" type="button" id="sidebar-toggle" title="Toggle Sidebar" aria-label="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <span class="navbar-brand"><?php echo ucfirst($currentPage); ?></span>
                <div class="dropdown ms-auto">
                    <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(mb_substr($_SESSION['user_name'], 0, 1))); ?></span>
                        <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser1">
                        <li><a class="dropdown-item" href="index.php?page=logout">Sign out</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <main class="content<?php echo ($currentPage == 'dashboard') ? ' content-dashboard' : ''; ?>">
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

            function isMobile() {
                return window.matchMedia('(max-width: 768px)').matches;
            }

            function applyCollapsedState() {
                const collapsed = localStorage.getItem('sidebar-collapsed') === '1';
                document.body.classList.toggle('sidebar-collapsed', collapsed && !isMobile());
            }

            function syncCollapseState() {
                if (!isMobile()) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                } else {
                    document.body.classList.remove('sidebar-collapsed');
                }
            }

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    if (isMobile()) {
                        sidebar.classList.toggle('active');
                        overlay.classList.toggle('active');
                    } else {
                        const collapsed = document.body.classList.toggle('sidebar-collapsed');
                        localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0');
                    }
                });
            }

            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            }

            window.addEventListener('resize', syncCollapseState);
            applyCollapsedState();

            // Button ripple micro-interaction
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.btn');
                if (!btn) return;
                const rect = btn.getBoundingClientRect();
                const d = Math.max(rect.width, rect.height);
                const span = document.createElement('span');
                span.className = 'ripple';
                span.style.width = span.style.height = d + 'px';
                span.style.left = (e.clientX - rect.left - d / 2) + 'px';
                span.style.top = (e.clientY - rect.top - d / 2) + 'px';
                btn.appendChild(span);
                setTimeout(function () { span.remove(); }, 600);
            });
        });
    </script>
</body>
</html>
