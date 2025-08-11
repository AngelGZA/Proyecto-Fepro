<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Models\Estudiante;
use App\Models\Empleo;
use App\Models\Solicitud;

$auth = new AuthController();

if (!$auth->isLogged() || $auth->getUserType() !== 'estudiante') {
    header("Location: ../views/formulario.php");
    exit;
}

$user = $auth->getCurrentUser();
$loggedIn = $auth->isLogged();
$userType = $auth->getUserType();
$username = $user['name'] ?? null;

$estudianteData = Estudiante::findById($user['idest'] ?? 0);

$idEmpleo = $_POST['idof'] ?? 0;
$empleo = Empleo::findById($idEmpleo);

if (!$empleo) {
    header("Location: estudiante.php");
    exit;
}

$yaPostulado = Solicitud::existeSolicitud($user['idest'], $idEmpleo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['postularme'])) {
    if (empty($estudianteData['cv'])) {
        $mensajeError = "Debes subir tu CV antes de postularte";
    } else {
        if (Solicitud::crear($user['idest'], $idEmpleo)) {
            $mensajeExito = "¡Postulación exitosa!";
            $yaPostulado = true;
        } else {
            $mensajeError = "Error al postularse o ya te has postulado anteriormente";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($empleo['puesto']) ?> - Lobo Chamba</title>
    <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
    <link rel="stylesheet" href="../assets/styleEstudianteEmpleos.css">
</head>
  
<body>
    <div class="barra-lateral">
        <div>
            <div class="nombre-pagina">
                <div class="image">
                    <img id="Lobo" src="../multimedia/logo_pagina.png" alt="Logo">
                </div>
                <span>Lobo Chamba</span>
            </div>
        </div>
        <nav class="navegacion">
            <ul class="menu-superior">
                <li>
                    <a href="../public/index.php">
                        <ion-icon name="home-outline"></ion-icon>
                        <span>Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="empresa.php">
                        <ion-icon name="briefcase"></ion-icon>
                        <span>Empresa</span>
                    </a>
                </li>
                <li>
                    <a id="estudiante" href="estudiante_empleos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'estudiante_empleos.php' ? 'active' : '' ?>">
                        <ion-icon name="school"></ion-icon>
                        <span>Estudiante</span>
                    </a>
                </li>
                <li>
                    <a href="graficos.php">
                        <ion-icon name="podium"></ion-icon>
                        <span>Gráficos</span>
                    </a>
                </li>
            </ul>
            <ul class="menu-inferior">
                <li class="menu-item">
                    <a href="estudiante_perfil.php" class="menu-link">
                        <ion-icon name="person-circle-outline"></ion-icon>
                        <span>Mi Perfil</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="../public/logout.php" class="menu-link logout-link">
                        <ion-icon name="log-out-outline"></ion-icon>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    
    <main class="empleo-detalle">
        <div class="empleo-header">
            <h1><ion-icon name="briefcase-outline"></ion-icon> <?= htmlspecialchars($empleo['puesto']) ?></h1>
            <p class="empresa-nombre"><?= htmlspecialchars($empleo['empresa']) ?></p>
            
            <?php if (isset($mensajeExito)): ?>
                <div class="alert alert-success">
                    <ion-icon name="checkmark-circle-outline"></ion-icon> <?= $mensajeExito ?>
                </div>
            <?php endif; ?>
            <?php if (isset($mensajeError)): ?>
                <div class="alert alert-error">
                    <ion-icon name="close-circle-outline"></ion-icon> <?= $mensajeError ?>
                </div>
            <?php endif; ?>
        </div>
    
        <div class="empleo-content">
            <div class="empleo-card">
                <div class="empleo-info">
                    <div class="info-row">
                        <span class="info-label"><ion-icon name="cash-outline"></ion-icon> Salario:</span>
                        <span class="info-value"><?= $empleo['salario'] > 0 ? '$' . number_format($empleo['salario'], 2) : 'A convenir' ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label"><ion-icon name="time-outline"></ion-icon> Horario:</span>
                        <span class="info-value"><?= htmlspecialchars($empleo['horario']) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label"><ion-icon name="business-outline"></ion-icon> Modalidad:</span>
                        <span class="info-value"><?= htmlspecialchars($empleo['modalidad']) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label"><ion-icon name="school-outline"></ion-icon> Carrera deseada:</span>
                        <span class="info-value"><?= htmlspecialchars($empleo['carrera_deseada']) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label"><ion-icon name="calendar-outline"></ion-icon> Publicado:</span>
                        <span class="info-value"><?= date('d/m/Y', strtotime($empleo['fecha_publicacion'])) ?></span>
                    </div>
                </div>
                
                <div class="empleo-descripcion">
                    <h3><ion-icon name="document-text-outline"></ion-icon> Descripción del puesto</h3>
                    <p><?= nl2br(htmlspecialchars($empleo['descripcion'])) ?></p>
                </div>
                
                <div class="empleo-requisitos">
                    <h3><ion-icon name="list-outline"></ion-icon> Requisitos</h3>
                    <p><?= nl2br(htmlspecialchars($empleo['requisitos'])) ?></p>
                </div>
                
                <div class="empleo-actions">
                    <?php if (!$yaPostulado): ?>
                        <form method="POST">
                            <input type="hidden" name="idof" value="<?= htmlspecialchars($empleo['idof']) ?>">
                            <button type="submit" name="postularme" class="btn-postular">
                                <ion-icon name="send-outline"></ion-icon> Postularme
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn-postulado" disabled>
                            <ion-icon name="checkmark-circle-outline"></ion-icon> Ya te has postulado
                        </button>
                    <?php endif; ?>
                    
                    <a href="estudiante.php" class="btn-regresar">
                        <ion-icon name="arrow-back-outline"></ion-icon> Regresar
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; Error 404 | Todos los derechos reservados.</p>
        <p>
            Síguenos en nuestras redes:
            <a href="https://www.facebook.com/profile.php?id=61569699028545&mibextid=ZbWKwL" target="_blank">
                <ion-icon name="logo-facebook"></ion-icon>
            </a>
            <a href="https://www.instagram.com/error404_ods7?igsh=MTU4dHJrajBybWFxeQ==" target="_blank">
                <ion-icon name="logo-instagram"></ion-icon>
            </a>
            <a href="https://youtube.com/@gabrielcorona2000?si=As0KyE0q-QfsmlW0" target="_blank">
                <ion-icon name="logo-youtube"></ion-icon>
            </a>
            <a href="https://x.com/Error_404_ODS7?t=YAwltMat_BqnCXRHr-tIYQ&s=08" target="_blank">
                <ion-icon name="logo-twitter"></ion-icon>
            </a>
        </p>
    </footer>
    
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/scrollreveal"></script>
    <script src="../funciones/scriptEstudianteEmpleos.js"></script>

</body>
</html>