<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';


use App\Controllers\AuthController;
use App\Models\Estudiante;

$auth = new AuthController();
if (!$auth->isLogged() || $auth->getUserType() !== 'estudiante') {
    header("Location: ../views/formulario.php");
    exit;
}

$user = $auth->getCurrentUser();
$idest = intval($user['idest'] ?? 0);
if ($idest <= 0) { http_response_code(403); echo "No autorizado."; exit; }

// ---- CARGA DE PERFIL (desde la vista v_perfil_estudiante) ----
$estudianteData = Estudiante::findPerfilById($idest); // incluye 'universidad' (JOIN)

$mensajeExito = $mensajeError = null;

// ---- POST: actualizar perfil + subir Kardex ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {

    // Normalizamos nombres desde el form
    $payload = [
        'name'        => trim($_POST['nombre'] ?? ''),
        'email'       => trim($_POST['email'] ?? ''),
        'matricula'   => trim($_POST['matricula'] ?? ''),
        'telefono'    => trim($_POST['telefono'] ?? ''),
        'facultad'    => trim($_POST['facultad'] ?? ''),
        'carrera'     => trim($_POST['carrera'] ?? ''),
        'github'      => trim($_POST['repositorio'] ?? ''),
        'linkedin'    => trim($_POST['linkedin'] ?? ''),
        'portfolio'   => trim($_POST['portfolio'] ?? ''),
        'universidad' => trim($_POST['universidad'] ?? ''),  // <- NUEVO campo libre
    ];

    // Validaciones básicas
    if ($payload['name'] === '' || $payload['email'] === '') {
        $mensajeError = "Nombre y correo son obligatorios.";
    } elseif (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        $mensajeError = "El correo no es válido.";
    } elseif ($payload['telefono'] !== '' && !preg_match('/^[0-9]{7,15}$/', $payload['telefono'])) {
        $mensajeError = "El teléfono debe ser numérico (7–15 dígitos).";
    } elseif (Estudiante::emailExists($payload['email'], $idest)) {
        $mensajeError = "El correo electrónico ya está en uso por otro usuario.";
    }

    // Subida de Kardex (PDF) → guarda en columna 'kardex_pdf' (recomendado)
    $kardexFilename = null;
    if (!$mensajeError && isset($_FILES['kardex_pdf']) && $_FILES['kardex_pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['kardex_pdf']['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($_FILES['kardex_pdf']['tmp_name']);
            if ($mime !== 'application/pdf') {
                $mensajeError = "El Kardex debe ser PDF.";
            } elseif ($_FILES['kardex_pdf']['size'] > 2 * 1024 * 1024) {
                $mensajeError = "El Kardex no debe exceder 2MB.";
            } else {
                $dir = __DIR__ . '/../storage/kardex';
                if (!is_dir($dir)) { mkdir($dir, 0775, true); }
                $safe = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['kardex_pdf']['name']);
                if (move_uploaded_file($_FILES['kardex_pdf']['tmp_name'], $dir . '/' . $safe)) {
                    $kardexFilename = $safe;
                } else {
                    $mensajeError = "No se pudo guardar el archivo de Kardex.";
                }
            }
        } else {
            $mensajeError = "Error al subir el Kardex (código ".$_FILES['kardex_pdf']['error'].").";
        }
    }

    // Guardado
    if (!$mensajeError) {
        $ok = Estudiante::updateProfileExtended($idest, $payload);
        if (!$ok) {
            $mensajeError = "No se pudo actualizar el perfil.";
        } else {
            if ($kardexFilename) {
                // Si usas 'kardex_pdf' (recomendado)
                Estudiante::updateKardex($idest, $kardexFilename);
            }
            $mensajeExito   = "Perfil actualizado correctamente.";
            $estudianteData = Estudiante::findPerfilById($idest); // refresca
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Perfil Estudiante - Lobo Chamba</title>
  <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
  <link rel="stylesheet" href="../assets/styleEstudiante.css">
</head>

<body>
  <!-- BARRA LATERAL (igual a tu archivo original) -->
  <div class="barra-lateral">
    <div>
      <div class="nombre-pagina">
        <div class="image"><img id="Lobo" src="../multimedia/logo_pagina.png" alt="Logo"></div>
        <span>Lobo Chamba</span>
      </div>
    </div>
    <nav class="navegacion">
      <ul class="menu-superior">
        <li><a href="../index.php"><ion-icon name="home-outline"></ion-icon><span>Inicio</span></a></li>
        <li><a href="estudiante.php"><ion-icon name="school"></ion-icon><span>Estudiante</span></a></li>
      </ul>
      <ul class="menu-inferior">
        <li class="menu-item">
          <a href="estudiante_perfil.php" class="menu-link active">
            <ion-icon name="person-circle-outline"></ion-icon><span>Mi Perfil</span>
          </a>
        </li>
        <li class="menu-item">
          <a href="../public/logout.php" class="menu-link logout-link">
            <ion-icon name="log-out-outline"></ion-icon><span>Cerrar Sesión</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>

  <main class="perfil-main">
    <div class="perfil-header">
      <h1><ion-icon name="person-circle-outline"></ion-icon> Mi Perfil de Estudiante</h1>
      <?php if ($mensajeExito): ?>
        <div class="alert alert-success"><ion-icon name="checkmark-circle-outline"></ion-icon> <?= htmlspecialchars($mensajeExito) ?></div>
      <?php endif; ?>
      <?php if ($mensajeError): ?>
        <div class="alert alert-error"><ion-icon name="close-circle-outline"></ion-icon> <?= htmlspecialchars($mensajeError) ?></div>
      <?php endif; ?>
    </div>

    <div class="perfil-content">
      <div class="perfil-card">
        <div class="perfil-avatar"><ion-icon name="person-circle-outline"></ion-icon></div>

        <form method="POST" enctype="multipart/form-data" class="perfil-form">
          <input type="hidden" name="actualizar_perfil" value="1">

          <div class="form-row">
            <div class="form-group">
              <label for="nombre"><ion-icon name="person-outline"></ion-icon> Nombre completo</label>
              <input id="nombre" name="nombre" value="<?= htmlspecialchars($estudianteData['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label for="email"><ion-icon name="mail-outline"></ion-icon> Correo electrónico</label>
              <input type="email" id="email" name="email" value="<?= htmlspecialchars($estudianteData['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label for="matricula"><ion-icon name="id-card-outline"></ion-icon> Matrícula</label>
              <input id="matricula" name="matricula" maxlength="40" value="<?= htmlspecialchars($estudianteData['matricula'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="facultad"><ion-icon name="school-outline"></ion-icon> Facultad</label>
              <input id="facultad" name="facultad" value="<?= htmlspecialchars($estudianteData['facultad'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="carrera"><ion-icon name="book-outline"></ion-icon> Carrera</label>
              <input id="carrera" name="carrera" value="<?= htmlspecialchars($estudianteData['carrera'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="universidad"><ion-icon name="business-outline"></ion-icon> Universidad</label>
                <input id="universidad" name="universidad"
                        value="<?= htmlspecialchars($estudianteData['universidad'] ?? '') ?>"
                        placeholder="Nombre de tu universidad (libre)">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="telefono"><ion-icon name="call-outline"></ion-icon> Teléfono</label>
              <input id="telefono" name="telefono" maxlength="15" value="<?= htmlspecialchars($estudianteData['telefono'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="repositorio"><ion-icon name="logo-github"></ion-icon> GitHub</label>
              <input type="url" id="repositorio" name="repositorio" placeholder="https://github.com/usuario"
                     value="<?= htmlspecialchars($estudianteData['github'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="linkedin"><ion-icon name="logo-linkedin"></ion-icon> LinkedIn</label>
              <input type="url" id="linkedin" name="linkedin" placeholder="https://www.linkedin.com/in/usuario"
                     value="<?= htmlspecialchars($estudianteData['linkedin'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group full-width">
              <label for="portfolio"><ion-icon name="globe-outline"></ion-icon> Portafolio</label>
              <input type="url" id="portfolio" name="portfolio" placeholder="https://mi-sitio.com"
                     value="<?= htmlspecialchars($estudianteData['portfolio'] ?? '') ?>">
            </div>
          </div>

          <fieldset class="form-group full-width">
            <label for="kardex_pdf"><ion-icon name="document-attach-outline"></ion-icon> Actualizar Kardex (PDF)</label>
            <input type="file" id="kardex_pdf" name="kardex_pdf" accept="application/pdf">
            <small class="text-muted">Tamaño máximo: 2MB</small>
            <?php if (!empty($estudianteData['kardex_pdf'])): ?>
              <div class="cv-actions" style="margin-top:8px">
                <a class="btn-descargar" target="_blank" href="../storage/kardex/<?= urlencode($estudianteData['kardex_pdf']) ?>">
                  <ion-icon name="download-outline"></ion-icon> Descargar Kardex
                </a>
                <span class="cv-filename"><?= htmlspecialchars($estudianteData['kardex_pdf']) ?></span>
              </div>
            <?php endif; ?>
          </fieldset>

          <div class="form-actions">
            <button type="submit" class="btn-actualizar"><ion-icon name="save-outline"></ion-icon> Actualizar Perfil</button>
            <a href="estudiante.php" class="btn-regresar"><ion-icon name="arrow-back-outline"></ion-icon> Regresar</a>
          </div>
        </form>
      </div>
    </div>
  </main>

  <footer>
    <p>&copy; Error 404 | Todos los derechos reservados.</p>
  </footer>

  <script src="https://code.jquery.com/jquery-3.3.1.min.js"
          integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>
