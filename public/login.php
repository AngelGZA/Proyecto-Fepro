<?php
require __DIR__ . '/../vendor/autoload.php';
session_start();

use App\Controllers\AuthController;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['correo'];
    $pass  = $_POST['password'];

    if (AuthController::login($email, $pass)) {
        header("Location: ../index.php");
        exit;
    }

    $error = "Correo o contraseña incorrectos.";
}
?>
