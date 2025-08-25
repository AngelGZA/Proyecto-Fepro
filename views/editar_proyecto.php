<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;

$auth = new AuthController();
if (!$auth->isLogged() || $auth->getUserType() !== 'estudiante') {
    header("Location: ../views/formulario.php");
    exit;
}

$id = (int)($_POST['idproyecto'] ?? 0);
if ($id <= 0) {
    header("Location: estudiante.php?err=ProyectoInvalido");
    exit;
}

header("Location: estudiante_proyecto.php?id=" . $id);
exit;
