<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Models\Maestro;

$auth = new AuthController();

// Verifica si el usuario está logueado y es docente
if (!$auth->isLogged() || $auth->getUserType() !== 'docente') {
    header("Location: ../views/formulario.php");
    exit;
}

$user = $auth->getCurrentUser();
$loggedIn = $auth->isLogged();
$userType = $auth->getUserType();
$username = $user['nombre'] ?? null;

// Toma idmae desde el user o desde la sesión genérica
$idmae = (int)($user['idmae'] ?? ($_SESSION['user_id'] ?? 0));
$maestroData = Maestro::findById($idmae);

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['actualizar_perfil'])) {
        $updateData = [
            'nombre'       => $_POST['nombre'] ?? '',
            'email'        => $_POST['email'] ?? '',
            'telefono'     => $_POST['telefono'] ?? '',
            'institucion_nombre' => $_POST['institucion'] ?? '',
            'especialidad' => $_POST['especialidad'] ?? '',
            'bio'          => $_POST['bio'] ?? ''
        ];

        // Validaciones simples
        if (!preg_match('/^[0-9]{10}$/', $updateData['telefono'])) {
            $mensajeError = "El teléfono debe contener exactamente 10 dígitos numéricos.";
        }
        elseif (Maestro::emailExists($updateData['email'], $idmae)) {
            $mensajeError = "El correo electrónico ya está en uso por otro usuario.";
        }
        // Si todo es válido, actualizar
        else {
            if (Maestro::updateProfile($idmae, $updateData)) {
                $mensajeExito = "Perfil actualizado correctamente";
                $maestroData = Maestro::findById($idmae); // refrescar
            } else {
                $mensajeError = "Error al actualizar el perfil";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Perfil Docente - CodEval</title>
    <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
    <link rel="stylesheet" href="../assets/styleEstudiante.css">
</head>
  
<body>
    <!-- BARRA DE NAVEGACIÓN -->
    <div class="barra-lateral">
        <div>
            <div class="nombre-pagina">
                <div class="image">
                    <img id="Lobo" src="../multimedia/logo_pagina.png" alt="Logo">
                </div>
                <span style="color: #0097b2;">CodEval</span>
            </div>
        </div>
        <nav class="navegacion">
            <ul class="menu-superior">
                <li>
                    <a href="../index.php">
                        <ion-icon name="home-outline"></ion-icon>
                        <span>Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="docente.php">
                        <ion-icon name="create-outline"></ion-icon>
                        <span>Docentes</span>
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
                    <a href="docente_perfil.php"
                        class="menu-link <?= basename($_SERVER['PHP_SELF']) === 'docente_perfil.php' ? 'active' : '' ?>">
                        <ion-icon name="school-outline"></ion-icon>
                        <span>Mi Perfil</span>
                    </a>
                    <?php else: ?>
                    <a href="formulario.php" class="menu-link">
                        <ion-icon name="person-add"></ion-icon>
                        <span>Iniciar Sesión</span>
                    </a>
                    <?php endif; ?>
                </li>
                <?php if ($loggedIn): ?>
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
    
    <main class="perfil-main">
        <div class="perfil-header">
            <h1><ion-icon name="school-outline"></ion-icon> Mi Perfil de Docente</h1>
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
    
        <div class="perfil-content">
            <div class="perfil-card">
                <div class="perfil-avatar">
                    <ion-icon name="school-outline"></ion-icon>
                </div>
                <form method="POST" enctype="multipart/form-data" class="perfil-form" id="perfilForm">
                    <input type="hidden" name="actualizar_perfil" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">
                                <ion-icon name="person-outline"></ion-icon> Nombre completo
                            </label>
                            <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($maestroData['nombre'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">
                                <ion-icon name="mail-outline"></ion-icon> Correo electrónico
                            </label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($maestroData['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telefono">
                                <ion-icon name="call-outline"></ion-icon> Teléfono
                            </label>
                            <input type="tel" id="telefono" name="telefono" 
                                   value="<?= htmlspecialchars($maestroData['telefono'] ?? '') ?>"
                                   pattern="[0-9]{10}" 
                                   title="Debe contener exactamente 10 dígitos numéricos">
                            <small class="text-muted">10 dígitos numéricos</small>
                        </div>

                        <div class="form-group">
                            <label for="especialidad">
                                <ion-icon name="ribbon-outline"></ion-icon> Especialidad
                            </label>
                            <input type="text" id="especialidad" name="especialidad" 
                                   value="<?= htmlspecialchars($maestroData['especialidad'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="institucion">
                            <ion-icon name="business-outline"></ion-icon> Institución
                        </label>
                        <input type="text" id="institucion" name="institucion" value="<?= htmlspecialchars($maestroData['institucion_nombre'] ?? '') ?>">
                    </div>

                    <div class="form-group full-width">
                        <label for="bio">
                            <ion-icon name="document-text-outline"></ion-icon> Biografía
                        </label>
                        <textarea id="bio" name="bio" rows="4"><?= htmlspecialchars($maestroData['bio'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-actualizar">
                            <ion-icon name="save-outline"></ion-icon> Actualizar Perfil
                        </button>
                        <a href="docente.php" class="btn-regresar">
                            <ion-icon name="arrow-back-outline"></ion-icon> Regresar
                        </a>
                    </div>
                </form>
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
    
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"
        integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
        crossorigin="anonymous"></script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/scrollreveal"></script>
    <script>
    // Teléfono: solo números
    document.getElementById('telefono').addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '');
    });
    </script>
    <script src="../funciones/scriptDocente.js"></script>
</body>
</html>
