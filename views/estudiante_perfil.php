<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Models\Estudiante;

$auth = new AuthController();

// Verifica si el usuario está logueado y es estudiante
if (!$auth->isLogged() || $auth->getUserType() !== 'estudiante') {
    header("Location: ../views/formulario.php");
    exit;
}

$user = $auth->getCurrentUser();
$loggedIn = $auth->isLogged();
$userType = $auth->getUserType();
$username = $user['nombre'] ?? null;

// Obtener datos completos del estudiante
$estudianteData = Estudiante::findById($user['idest'] ?? 0);

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['actualizar_perfil'])) {
        $updateData = [
            'name' => $_POST['nombre'] ?? '',
            'email' => $_POST['email'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? ''
        ];
        
        // Validar email
        if (Estudiante::emailExists($updateData['email'], $user['idest'])) {
            $mensajeError = "El correo electrónico ya está en uso por otro usuario.";
        } else {
            // Procesar archivo CV si se subió uno nuevo
            if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../public/uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Validar tipo y tamaño del archivo
                $fileType = mime_content_type($_FILES['cv']['tmp_name']);
                $fileSize = $_FILES['cv']['size'];
                
                if ($fileType !== 'application/pdf') {
                    $mensajeError = "Solo se permiten archivos PDF.";
                } elseif ($fileSize > 2 * 1024 * 1024) { // 2MB máximo
                    $mensajeError = "El archivo es demasiado grande. Tamaño máximo: 2MB.";
                } else {
                    $filename = uniqid() . '_' . basename($_FILES['cv']['name']);
                    $targetPath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($_FILES['cv']['tmp_name'], $targetPath)) {
                        // Eliminar el CV anterior si existe
                        if (!empty($estudianteData['cv'])) {
                            $oldFilePath = __DIR__ . '/../public/uploads/' . basename($estudianteData['cv']);
                            if (file_exists($oldFilePath)) {
                                unlink($oldFilePath);
                            }
                        }
                        
                        // Actualizar en la base de datos
                        Estudiante::updateCV($user['idest'], 'uploads/' . $filename);
                    } else {
                        $mensajeError = "Error al subir el archivo.";
                    }
                }
            }
            
            // Actualizar datos del perfil si no hay errores
            if (!isset($mensajeError)) {
                if (Estudiante::updateProfile($user['idest'], $updateData)) {
                    $mensajeExito = "Perfil actualizado correctamente";
                    $estudianteData = Estudiante::findById($user['idest']); // Refrescar datos
                } else {
                    $mensajeError = "Error al actualizar el perfil";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Perfil Estudiante - Lobo Chamba</title>
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
                    <a href="estudiante.php">
                        <ion-icon name="school"></ion-icon>
                        <span>Estudiante</span>
                    </a>
                </li>
                <li>
                    <a href="graficos.php">
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
        </nav>
    </div>
    
    <main class="perfil-main">
        <div class="perfil-header">
            <h1><ion-icon name="person-circle-outline"></ion-icon> Mi Perfil de Estudiante</h1>
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
                    <ion-icon name="person-circle-outline"></ion-icon>
                </div>
                <form method="POST" enctype="multipart/form-data" class="perfil-form">
                    <input type="hidden" name="actualizar_perfil" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">
                                <ion-icon name="person-outline"></ion-icon> Nombre completo
                            </label>
                            <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($estudianteData['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">
                                <ion-icon name="mail-outline"></ion-icon> Correo electrónico
                            </label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($estudianteData['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telefono">
                                <ion-icon name="call-outline"></ion-icon> Teléfono
                            </label>
                            <input type="tel" id="telefono" name="telefono" value="<?= htmlspecialchars($estudianteData['telefono'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="cv">
                                <ion-icon name="document-attach-outline"></ion-icon> Actualizar CV (PDF)
                            </label>
                            <input type="file" id="cv" name="cv" accept=".pdf">
                            <small class="text-muted">Tamaño máximo: 2MB</small>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="descripcion">
                            <ion-icon name="document-text-outline"></ion-icon> Descripción sobre ti
                        </label>
                        <textarea id="descripcion" name="descripcion" rows="4"><?= htmlspecialchars($estudianteData['descripcion'] ?? '') ?></textarea>
                    </div>
                    
                    <?php if (!empty($estudianteData['cv'])): ?>
                    <div class="form-group full-width">
                        <label>
                            <ion-icon name="document-outline"></ion-icon> CV actual
                        </label>
                        <div class="cv-actions">
                            <a href="../public/uploads/<?= basename($estudianteData['cv']) ?>" 
                               class="btn-descargar" target="_blank" download>
                                <ion-icon name="download-outline"></ion-icon> Descargar CV
                            </a>
                            <span class="cv-filename"><?= htmlspecialchars(basename($estudianteData['cv'])) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-actualizar">
                            <ion-icon name="save-outline"></ion-icon> Actualizar Perfil
                        </button>
                        <a href="estudiante.php" class="btn-regresar">
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
    <script src="../funciones/scriptEstudiante.js"></script>
</body>
</html>