<?php

date_default_timezone_set('Asia/Jakarta');

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Composer autoloader (endroid/qr-code, phpoffice/phpspreadsheet, etc.)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Basic Autoloader for src folder
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../src/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Dependency Injection Container (basic)
$container = [];
$container['db_config'] = require __DIR__ . '/../config/database.php';

$container['pdo'] = function($c) {
    $db = $c['db_config'];
    $dsn = "mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
         return new PDO($dsn, $db['username'], $db['password'], $options);
    } catch (PDOException $e) {
         die("Database connection failed: " . $e->getMessage());
    }
};

// Simple Router
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

// Authentication check
$public_pages = ['login', 'plant-display', 'attendance'];
if (!isset($_SESSION['user_id']) && !in_array($page, $public_pages)) {
    header('Location: index.php?page=login');
    exit();
}

// Route to controllers
switch ($page) {
    case 'dashboard':
        $controller = new App\Controllers\DashboardController($container['pdo']($container));
        $controller->index();
        break;

    case 'contractors':
        $controller = new App\Controllers\ContractorController($container['pdo']($container));
        switch ($action) {
            case 'create':
                $controller->create();
                break;
                case 'history':
                    $controller->history($_GET['contractor_id'] ?? null);
                    break;
            case 'store':
                $controller->store();
                break;
            case 'import':
                $controller->import();
                break;
            case 'handleImport':
                $controller->handleImport();
                break;
            case 'edit':
                $controller->edit($id);
                break;
            case 'update':
                $controller->update($id);
                break;
            case 'delete':
                $controller->delete($id);
                break;
            case 'export':
                $controller->export();
                break;
            case 'printIdCard':
                $controller->printIdCard($id);
                break;
            case 'bulkPrint':
                $controller->bulkPrint();
                break;
            default:
                $controller->index();
                break;
        }
        break;

    case 'plant_contractors':
        $controller = new App\Controllers\ContractorController($container['pdo']($container));
        $controller->inPlant();
        break;

    case 'sanctions':
        $controller = new App\Controllers\SanctionController($container['pdo']($container));
        switch ($action) {
            case 'create':
                $controller->create();
                break;
            case 'store':
                $controller->store();
                break;
            case 'edit':
                $controller->edit($id);
                break;
            case 'update':
                $controller->update($id);
                break;
            case 'release':
                $controller->release($id);
                break;
            default:
                $controller->index();
                break;
        }
        break;

    case 'settings':
        $controller = new App\Controllers\SettingController($container['pdo']($container));
        switch ($action) {
            case 'system':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $controller->updateSystem();
                } else {
                    $controller->system();
                }
                break;
            case 'user':
                $controller->user();
                break;
            case 'createUser':
                $controller->createUser();
                break;
            case 'updateUser':
                if ($id) {
                    $controller->updateUser($id);
                }
                break;
            case 'deleteUser':
                if ($id) {
                    $controller->deleteUser($id);
                }
                break;
            case 'companies':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    if (isset($_POST['name'])) {
                        $controller->createCompany();
                    } else {
                        $controller->updateCompany($id);
                    }
                } else {
                    $controller->companies();
                }
                break;
            case 'deleteCompany':
                if ($id) {
                    $controller->deleteCompany($id);
                } else {
                    header('Location: index.php?page=settings&action=companies');
                    exit();
                }
                break;
            case 'violations':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    if (isset($_POST['name'])) {
                        $controller->createViolation();
                    } else {
                        $controller->updateViolation($id);
                    }
                } else {
                    $controller->violations();
                }
                break;
            case 'deleteViolation':
                if ($id) {
                    $controller->deleteViolation($id);
                } else {
                    header('Location: index.php?page=settings&action=violations');
                    exit();
                }
                break;
            case 'idCard':
                $controller->idCard();
                break;
            case 'updateIdCard':
                $controller->updateIdCard();
                break;
            default:
                $controller->index();
                break;
        }
        break;

    case 'plant-display':
        $plant_slug = $_GET['plant'] ?? null;
        $controller = new App\Controllers\PlantDisplayController($container['pdo']($container));
        if ($action === 'getUpdate') {
            $controller->getUpdate();
        } else {
            $controller->index($plant_slug);
        }
        break;

    case 'attendance':
        $controller = new App\Controllers\AttendanceController($container['pdo']($container));
        switch ($action) {
            case 'scan':
                $controller->scan();
                break;
            case 'export':
                $controller->export();
                break;
            case 'index':
            default:
                $controller->index();
                break;
        }
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new App\Controllers\AuthController($container['pdo']($container));
            $controller->login($_POST['email'], $_POST['password']);
        } else {
            include __DIR__ . '/../templates/login.php';
        }
        break;

    case 'logout':
        $controller = new App\Controllers\AuthController($container['pdo']($container));
        $controller->logout();
        break;

    default:
        http_response_code(404);
        echo "<h1>404 Page Not Found</h1>";
        break;
}
