<?php
// Carga variables de entorno si existen
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv->load();
    }
}

// Helpers
if (!function_exists('env')) {
    function env($key, $default = null) {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}