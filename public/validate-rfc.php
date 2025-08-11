<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Empresa;

header('Content-Type: application/json');

$rfc = strtoupper($_GET['rfc'] ?? '');

$validFormat = preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc);
$exists = Empresa::findByRFC($rfc);

echo json_encode([
    'available' => !$exists,
    'valid_format' => (bool)$validFormat
]);
?>