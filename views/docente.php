<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;

$auth = new AuthController();

// Verifica si el usuario está logueado y es DOCENTE
if (!$auth->isLogged() || $auth->getUserType() !== 'docente') {
    header("Location: ../views/formulario.php");
    exit;
}

$user = $auth->getCurrentUser();
$loggedIn = $auth->isLogged(); // Definimos la variable que usa el menú
$userType = $auth->getUserType(); // Usamos el tipo de sesión en lugar de $user['tipo']
$username = $user['nombre'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>CodEval</title>
    <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
    <link rel="stylesheet" href="../assets/styleDocente.css">
  </head>
  
  <body>

    <!-- BARRA DE NAVEGACIÓN  -->
    <div class="barra-lateral">
        <div>
            <div class="nombre-pagina">
                <div class="image">
                    <img id="Code" src="../multimedia/logo_pagina.png" alt="Logo">
                </div>
                <span style="color: #0097b2;">CodEval</span>
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
                    <a id="perfil" href="empresa.php" class="<?= basename($_SERVER['PHP_SELF']) == 'empresa.php' ? 'active' : '' ?>">
                        <ion-icon name="library-outline"></ion-icon>
                        <span>Organizaciones</span>
                    </a>
                </li>
                <li>
                    <a href="../view/graficos.php">
                        <ion-icon name="podium"></ion-icon>
                        <span>Graficos</span>
                    </a>
                </li>
            </ul>
            <ul class="menu-inferior">
                <li class="menu-item">
                    <?php if ($loggedIn): ?>
                    <!-- Opción Mi Perfil -->
                      <a href="<?=
                        htmlspecialchars(
                          $userType === 'estudiante' ? 'estudiante_perfil.php' :
                          ($userType === 'docente' ? 'docente_perfil.php' : 'empresa_perfil.php')
                        )
                      ?>" class="menu-link">
                        <ion-icon name="<?=
                          htmlspecialchars(
                            $userType === 'estudiante' ? 'person-circle-outline' :
                            ($userType === 'docente' ? 'school-outline' : 'business-outline')
                          )
                        ?>"></ion-icon>
                        <span>Mi Perfil</span>
                      </a>
                    <?php endif; ?>
                </li>
                <?php if ($loggedIn): ?>
                <!-- Opción Cerrar Sesión -->
                <li class="menu-item">
                    <a href="../public/logout.php" class="menu-link logout-link">
                        <ion-icon name="log-out-outline"></ion-icon>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <main>
        
    </main>
    <footer>
  <p>&copy; CodEval | Todos los derechos reservados.</p>
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
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"
      integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
      crossorigin="anonymous"></script>
    <!-- Lógica del Frontend -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/scrollreveal"></script>
    <script src="../funciones/scriptDocente.js"></script>
  </body>
</html>