<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Estudiante;
use App\Models\Empresa;

header('Content-Type: application/json');

$telefono = $_GET['telefono'] ?? '';

if (!preg_match('/^\d{10}$/', $telefono)) {
    echo json_encode(['available' => false]);
    exit;
}

// Buscar en ambas tablas
$existsInEstudiante = Estudiante::findByTelefono($telefono);
$existsInEmpresa = Empresa::findByTelefono($telefono);

echo json_encode([
    'available' => !$existsInEstudiante && !$existsInEmpresa
]);
?>