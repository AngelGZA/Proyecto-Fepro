<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Models\Empresa;

$auth = new AuthController();

// Verifica si el usuario está logueado y es empresa
if (!$auth->isLogged() || $auth->getUserType() !== 'empresa') {
    header("Location: ../views/formulario.php");
    exit;
}

$user = $auth->getCurrentUser();
$loggedIn = $auth->isLogged();
$userType = $auth->getUserType();
$username = $user['nombre'] ?? null;

// Obtener datos completos de la empresa
$empresaData = Empresa::findById($user['idemp'] ?? 0);

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['actualizar_perfil'])) {
        $updateData = [
            'name' => $_POST['nombre'] ?? '',
            'email' => $_POST['email'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'direccion' => $_POST['descripcion'] ?? '',
            'rfc' => strtoupper($_POST['rfc'] ?? '') // Convertir a mayúsculas
        ];
        
        // Validar formato de RFC
        $validFormat = preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $updateData['rfc']);
        if (!$validFormat) {
            $mensajeError = "Formato de RFC inválido. Debe seguir el patrón: XXXX999999XXX";
        }
        // Validar si el RFC ya existe (excepto si es el mismo de la empresa actual)
        elseif (Empresa::findByRFC($updateData['rfc']) && $empresaData['rfc'] !== $updateData['rfc']) {
            $mensajeError = "El RFC ya está registrado por otra empresa.";
        }
        // Validar teléfono (10 dígitos numéricos)
        elseif (!preg_match('/^[0-9]{10}$/', $updateData['telefono'])) {
            $mensajeError = "El teléfono debe contener exactamente 10 dígitos numéricos.";
        }
        // Validar email
        elseif (Empresa::emailExists($updateData['email'], $user['idemp'])) {
            $mensajeError = "El correo electrónico ya está en uso por otro usuario.";
        } 
        // Si todo es válido, actualizar
        else {
            if (Empresa::updateProfile($user['idemp'], $updateData)) {
                $mensajeExito = "Perfil actualizado correctamente";
                $empresaData = Empresa::findById($user['idemp']); // Refrescar datos
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
    <title>Perfil Organizacion - CodEval</title>
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
                    <a href="empresa.php" >
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
                    <a href="<?= htmlspecialchars($userType === 'estudiante' ? 'estudiante_perfil.php' : 'empresa_perfil.php') ?>"
                        class="menu-link <?= basename($_SERVER['PHP_SELF']) === ($userType === 'estudiante' ? 'estudiante_perfil.php' : 'empresa_perfil.php') ? 'active' : '' ?>">
                        <ion-icon name="<?= htmlspecialchars($userType === 'estudiante' ? 'person-circle-outline' : 'business-outline') ?>"></ion-icon>
                        <span>Mi Perfil</span>
                    </a>
                    <?php else: ?>
                    <!-- Opción Iniciar Sesión -->
                    <a href="formulario.php" class="menu-link">
                        <ion-icon name="person-add"></ion-icon>
                        <span>Iniciar Sesión</span>
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
            </ul>
        </nav>
    </div>
    
    <main class="perfil-main">
        <div class="perfil-header">
            <h1><ion-icon name="business-outline"></ion-icon> Mi Perfil de Empresa</h1>
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
                    <ion-icon name="business-outline"></ion-icon>
                </div>
                <form method="POST" enctype="multipart/form-data" class="perfil-form" id="perfilForm">
                    <input type="hidden" name="actualizar_perfil" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">
                                <ion-icon name="person-outline"></ion-icon> Nombre completo
                            </label>
                            <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($empresaData['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">
                                <ion-icon name="mail-outline"></ion-icon> Correo electrónico
                            </label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($empresaData['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telefono">
                                <ion-icon name="call-outline"></ion-icon> Teléfono
                            </label>
                            <input type="tel" id="telefono" name="telefono" 
                                   value="<?= htmlspecialchars($empresaData['telefono'] ?? '') ?>"
                                   pattern="[0-9]{10}" 
                                   title="Debe contener exactamente 10 dígitos numéricos">
                            <small class="text-muted">10 dígitos numéricos</small>
                        </div>

                        <div class="form-group">
                            <label for="rfc">
                                <ion-icon name="id-card-outline"></ion-icon> RFC
                            </label>
                            <input type="text" id="rfc" name="rfc" 
                                   value="<?= htmlspecialchars($empresaData['rfc'] ?? '') ?>"
                                   pattern="[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}"
                                   title="Formato: XXXX999999XXX (12-13 caracteres)">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="descripcion">
                            <ion-icon name="briefcase-outline"></ion-icon> Dirección
                        </label>
                        <textarea id="descripcion" name="descripcion" rows="4"><?= htmlspecialchars($empresaData['direccion'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-actualizar">
                            <ion-icon name="save-outline"></ion-icon> Actualizar Perfil
                        </button>
                        <a href="empresa.php" class="btn-regresar">
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
    // Validación en tiempo real del RFC
    document.getElementById('rfc').addEventListener('blur', function() {
        const rfc = this.value.toUpperCase();
        this.value = rfc; // Forzar mayúsculas
    });

    // Validación en tiempo real del teléfono
    document.getElementById('telefono').addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, ''); // Solo números
    });
    </script>
    <script src="../funciones/scriptEmpresa.js"></script>
</body>
</html>