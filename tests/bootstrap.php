<?php

// PHPUnit bootstrap. Mirrors the autoloading public/index.php sets up
// (Composer's vendor autoloader + a small App\ -> src/ mapper), so tests
// don't need a real HTTP request or session to load app classes.

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../src/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
