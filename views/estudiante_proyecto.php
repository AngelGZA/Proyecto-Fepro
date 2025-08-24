<?php
// views/estudiante_proyecto.php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Models\DB;

$auth = new AuthController();
if (!$auth->isLogged() || $auth->getUserType() !== 'estudiante') {
    header("Location: ../views/formulario.php");
    exit;
}

$user = $auth->getCurrentUser();
$idest = (int)($user['idest'] ?? 0);

// DB
$db = new DB();
$conn = $db->getConnection();

// Utilidad: normaliza cualquier link de YouTube a formato /embed/VIDEO_ID
function yt_to_embed(?string $url): ?string {
    if (!$url) return null;
    $url = trim($url);

    if (preg_match('~youtu\.be/([A-Za-z0-9_-]{11})~', $url, $m)) {
        return "https://www.youtube.com/embed/{$m[1]}";
    }
    if (preg_match('~youtube\.com/watch\?[^ ]*v=([A-Za-z0-9_-]{11})~', $url, $m)) {
        return "https://www.youtube.com/embed/{$m[1]}";
    }
    if (preg_match('~youtube\.com/embed/([A-Za-z0-9_-]{11})~', $url)) {
        return preg_replace('~^http://~', 'https://', $url);
    }
    return null;
}

$errores = [];
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Inputs
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $repo_url    = trim($_POST['repo_url'] ?? '');
    $video_url   = trim($_POST['video_url'] ?? '');
    $visibilidad = trim($_POST['visibilidad'] ?? 'privado');
    $estado      = 'pendiente'; // estado inicial

    // 2) Validaciones
    if ($titulo === '')      $errores[] = 'El título es obligatorio.';
    if ($descripcion === '') $errores[] = 'La descripción es obligatoria.';

    if ($repo_url !== '' && !preg_match('~^https?://~i', $repo_url)) {
        $repo_url = 'https://' . $repo_url;
    }

    $video_embed = null;
    if ($video_url !== '') {
        $video_embed = yt_to_embed($video_url);
        if (!$video_embed) $errores[] = 'El enlace de YouTube no es válido.';
    }

    // 3) ZIP (opcional)
    $rutaZip = ''; 

    if (!empty($_FILES['archivo_zip']['name'])) {
        // Nombre seguro
        $nombreOriginal = $_FILES['archivo_zip']['name'];
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

        // Acepta solo .zip
        if ($extension !== 'zip') {
            $errores[] = 'El archivo debe ser .zip';
        } else {
            $nombreSeguro = 'proj_' . uniqid() . '_' . time() . '.zip';

            $dirUploads = __DIR__ . '/../uploads';
            if (!is_dir($dirUploads)) {
                mkdir($dirUploads, 0775, true);
            }
            $destinoFisico = $dirUploads . '/' . $nombreSeguro;

            if (!move_uploaded_file($_FILES['archivo_zip']['tmp_name'], $destinoFisico)) {
                $errores[] = 'No se pudo mover el archivo subido.';
            } else {
                // Ruta PÚBLICA (URL) que se guarda en BD; tu app vive en /pro
                $rutaZip = '/pro/uploads/' . $nombreSeguro;
            }
        }
    }



    // 4) Insert
    if (!$errores) {
        $sql = "INSERT INTO proyectos
                (titulo, descripcion, repo_url, video_url, archivo_zip, visibilidad, estado, idest, created_at)
                VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, NOW())";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param(
                "sssssssi",
                $titulo,
                $descripcion,
                $repo_url,
                $video_embed,   
                $rutaZip,       
                $visibilidad,
                $estado,
                $idest
            );
            if ($stmt->execute()) {
                header('Location: estudiante.php?ok=1');
                exit;
            } else {
                $errores[] = 'No se pudo guardar el proyecto.';
            }
            $stmt->close();
        } else {
            $errores[] = 'Error en la base de datos (prepare).';
        }
    }

}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Subir Proyecto - CodEval</title>
  <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
  <link rel="stylesheet" href="../assets/styleEstudianteIndex.css">
  <style>
    .card{max-width:760px;margin:24px auto;background:#fff;border-radius:14px;box-shadow:0 6px 22px rgba(16,24,40,.08);padding:24px}
    h1{margin:0 0 12px;color:#1976D2}
    .form-row{display:grid;gap:10px;margin-bottom:14px}
    label{font-weight:600;color:#344054}
    input[type="text"], textarea, select{width:100%;border:1px solid #D0D5DD;border-radius:10px;padding:10px 12px;font-size:15px}
    textarea{min-height:120px;resize:vertical}
    .btn{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(90deg,#1976D2,#1e88e5);color:#fff;border:0;padding:12px 16px;border-radius:999px;cursor:pointer;font-weight:700}
    .btn:hover{filter:brightness(1.05)}
    .alert{padding:10px 12px;border-radius:10px;margin-bottom:10px}
    .alert.error{background:#FEE4E2;color:#912018}
  </style>
</head>
<body>
  <div class="card">
    <h1>Subir Proyecto</h1>

    <?php if ($errores): ?>
      <div class="alert error">
        <?php foreach ($errores as $e): ?>
          <div>• <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="form-row">
        <label for="titulo">Título *</label>
        <input type="text" id="titulo" name="titulo" maxlength="150" required value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>">
      </div>

      <div class="form-row">
        <label for="descripcion">Descripción *</label>
        <textarea id="descripcion" name="descripcion" required><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
      </div>

      <div class="form-row">
        <label for="repo_url">Repositorio (opcional)</label>
        <input type="text" id="repo_url" name="repo_url" placeholder="https://github.com/usuario/repo" value="<?= htmlspecialchars($_POST['repo_url'] ?? '') ?>">
      </div>

      <div class="form-row">
        <label for="video_url">Video de YouTube (opcional)</label>
        <input type="text" id="video_url" name="video_url" placeholder="https://youtu.be/VIDEO_ID o https://www.youtube.com/watch?v=VIDEO_ID" value="<?= htmlspecialchars($_POST['video_url'] ?? '') ?>">
        <small>Puedes pegar cualquier enlace de YouTube; se convertirá a <code>/embed/VIDEO_ID</code>.</small>
      </div>

      <div class="form-row">
        <label for="archivo_zip">Archivo ZIP (opcional)</label>
        <input type="file" id="archivo_zip" name="archivo_zip" accept=".zip">
        <small>Máx. 50MB. Solo .zip</small>
      </div>

      <div class="form-row">
        <label for="visibilidad">Visibilidad</label>
        <select id="visibilidad" name="visibilidad">
          <option value="privado" <?= (($_POST['visibilidad'] ?? '') === 'publico') ? '' : 'selected' ?>>Privado</option>
          <option value="publico" <?= (($_POST['visibilidad'] ?? '') === 'publico') ? 'selected' : '' ?>>Público</option>
        </select>
      </div>

      <button type="submit" class="btn">
        <ion-icon name="cloud-upload-outline"></ion-icon>
        Guardar Proyecto
      </button>

      <a href="estudiante.php" class="btn" style="margin-left:8px;background:#64748b;">
        <ion-icon name="arrow-back-outline"></ion-icon>
        Volver
      </a>
    </form>
  </div>

  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>
