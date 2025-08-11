<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;

// 1. Verificar autenticación
$auth = new AuthController();
if (!$auth->isLogged()) {
    header('HTTP/1.0 403 Forbidden');
    exit('Acceso denegado');
}

// 2. Obtener parámetro seguro
$file = isset($_GET['file']) ? 'uploads/' . basename($_GET['file']) : '';
$path = __DIR__ . '/' . $file;

// 3. Verificar existencia
if (!file_exists($path)) {
    header('HTTP/1.0 404 Not Found');
    exit('Archivo no encontrado');
}

// 4. Forzar descarga
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($path).'"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
?>