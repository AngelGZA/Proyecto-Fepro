<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Empresa;
use App\Models\Estudiante;

header('Content-Type: application/json'); // Añade esta línea

$email = $_GET['email'] ?? null;

if (!$email) {
    echo json_encode(['available' => false, 'message' => 'inválido']);
    exit;
}

$empresa = Empresa::findByEmail($email);
$estudiante = Estudiante::findByEmail($email);

echo json_encode([
    'available' => !$empresa && !$estudiante,
    'message' => ($empresa || $estudiante) ? 'ocupado' : 'disponible'
]);
?>