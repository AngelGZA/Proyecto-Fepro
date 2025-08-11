<?php
require __DIR__ . '/vendor/autoload.php';
session_start();

use App\Controllers\AuthController;

header('Content-Type: application/json');

if (AuthController::isLogged()) {
    echo json_encode([
        'logged' => true,
        'username' => $_SESSION['username']
    ]);
} else {
    echo json_encode(['logged' => false]);
}