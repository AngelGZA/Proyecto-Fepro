<?php
session_start();
ini_set('display_errors', 1); 
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Models\Estudiante; 
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

// Obtener el usuario actual
$user = $auth->getCurrentUser();
$loggedIn = $auth->isLogged();
$userType = $auth->getUserType();
$username = $user['name'] ?? null;

$estudianteData = Estudiante::findById($user['idest'] ?? 0);

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

// ---- MODO EDICIÓN: si viene ?id=
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($editId <= 0 && isset($_POST['id'])) {
    $editId = (int)$_POST['id'];
}

$editProject = null;
if ($editId > 0) {
    $sql = "SELECT id, idest, titulo, descripcion, repo_url, video_url, archivo_zip, visibilidad, estado
            FROM proyectos
            WHERE id = ? AND idest = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $editId, $idest);
        $stmt->execute();
        $res = $stmt->get_result();
        $editProject = $res->fetch_assoc();
        $stmt->close();
    }
    if (!$editProject) {
        // no existe o no es del alumno
        header("Location: estudiante.php?err=NoAutorizado");
        exit;
    }
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
    // estado: si editas, conserva el existente; si creas, 'pendiente'
    $estado      = $editProject ? ($editProject['estado'] ?? 'pendiente') : 'pendiente';

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
    $hayArchivo = !empty($_FILES['archivo_zip']['name']);

    if ($hayArchivo) {
        $nombreOriginal = $_FILES['archivo_zip']['name'];
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

        if ($extension !== 'zip') {
            $errores[] = 'El archivo debe ser .zip';
        } else {
            $nombreSeguro = 'proj_' . uniqid() . '_' . time() . '.zip';

            $dirUploads = __DIR__ . '/../uploads';
            if (!is_dir($dirUploads)) {
                @mkdir($dirUploads, 0775, true);
            }
            $destinoFisico = $dirUploads . '/' . $nombreSeguro;

            if (!@move_uploaded_file($_FILES['archivo_zip']['tmp_name'], $destinoFisico)) {
                $errores[] = 'No se pudo mover el archivo subido.';
            } else {
                // Ruta PÚBLICA (URL) que se guarda en BD; tu app vive en /pro
                $rutaZip = '/pro/uploads/' . $nombreSeguro;
            }
        }
    }

    // Si es UPDATE y NO subes zip nuevo, conserva el actual
    if ($editProject && !$hayArchivo && !empty($editProject['archivo_zip'])) {
        $rutaZip = $editProject['archivo_zip'];
    }

    // 4) INSERT o UPDATE
    if (!$errores) {
        if ($editProject) {
            // --- UPDATE ---
            $sql = "UPDATE proyectos
                    SET titulo = ?, descripcion = ?, repo_url = ?, video_url = NULLIF(?, ''), 
                        archivo_zip = ?, visibilidad = ?, updated_at = NOW()
                    WHERE id = ? AND idest = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param(
                    "ssssssii",
                    $titulo,
                    $descripcion,
                    $repo_url,
                    $video_embed,
                    $rutaZip,
                    $visibilidad,
                    $editProject['id'],
                    $idest
                );
                if ($stmt->execute()) {
                    header('Location: estudiante.php?ok=ProyectoActualizado');
                    exit;
                } else {
                    $errores[] = 'No se pudo actualizar el proyecto.';
                }
                $stmt->close();
            } else {
                $errores[] = 'Error en la base de datos (prepare UPDATE).';
            }
        } else {
            // --- INSERT (tu lógica original) ---
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
}

// Helper para prellenar valores desde POST o $editProject
function v($postKey, $editProject, $field) {
    if (isset($_POST[$postKey])) return $_POST[$postKey];
    if ($editProject && isset($editProject[$field])) return $editProject[$field];
    return '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title><?= $editProject ? 'Editar Proyecto' : 'Subir Proyecto' ?> - CodEval</title>
  <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
  <link rel="stylesheet" href="../assets/styleEstudianteIndex.css">
  <link rel="stylesheet" href="../assets/styleProyectoEst.css">
</head>
<body>
   <!-- BARRA DE NAVEGACIÓN -->
    <div class="barra-lateral">
        <div>
            <div class="nombre-pagina">
                <div class="image">
                    <img id="Lobo" src="../multimedia/logo_pagina.png" alt="Logo">
                </div>
                <span>CodEval</span>
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
                    <a id="estudiante" href="estudiante.php" class="<?= basename($_SERVER['PHP_SELF']) == 'estudiante.php' ? 'active' : '' ?>">
                        <ion-icon name="school"></ion-icon>
                        <span>Estudiante</span>
                    </a>
                </li>
                <li>
                    <a id="estudiante" href="estudiante_visualizacion.php" class="<?= basename($_SERVER['PHP_SELF']) == 'estudiante.php' ? 'active' : '' ?>">
                        <ion-icon name="telescope-outline"></ion-icon>
                        <span>Descubrir proyectos</span>
                    </a>
                </li>
            </ul>
            <ul class="menu-inferior">
                <li class="menu-item">
                    <?php if ($loggedIn): ?>
                    <!-- Opción Mi Perfil -->
                    <a href="<?= htmlspecialchars($userType === 'estudiante' ? 'estudiante_perfil.php' : 'empresa_perfil.php') ?>" class="menu-link">
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
    <header>
        <div class="header-title">
            <h2><?= $editProject ? 'Editar Proyecto' : 'Publicar Proyecto' ?></h2>
        </div>
        <div class="search-container">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" id="searchInput" placeholder="Buscar proyectos...">
        </div>
    </header>

  <div class="card">
    <h1><?= $editProject ? 'Actualizar Proyecto' : 'Subir Proyecto' ?></h1>

    <?php if ($errores): ?>
      <div class="alert error">
        <?php foreach ($errores as $e): ?>
          <div>• <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="estudiante_proyecto.php<?= $editProject ? ('?id='.(int)$editProject['id']) : '' ?>">
      <?php if ($editProject): ?>
        <input type="hidden" name="id" value="<?= (int)$editProject['id'] ?>">
      <?php endif; ?>

      <div class="form-row">
        <label for="titulo">Título *</label>
        <input type="text" id="titulo" name="titulo" maxlength="150" required value="<?= htmlspecialchars(v('titulo', $editProject, 'titulo')) ?>">
      </div>

      <div class="form-row">
        <label for="descripcion">Descripción *</label>
        <textarea id="descripcion" name="descripcion" required><?= htmlspecialchars(v('descripcion', $editProject, 'descripcion')) ?></textarea>
      </div>

      <div class="form-row">
        <label for="repo_url">Repositorio (opcional)</label>
        <input type="text" id="repo_url" name="repo_url" placeholder="https://github.com/usuario/repo" value="<?= htmlspecialchars(v('repo_url', $editProject, 'repo_url')) ?>">
      </div>

      <div class="form-row">
        <label for="video_url">Video de YouTube (opcional)</label>
        <input type="text" id="video_url" name="video_url" placeholder="https://youtu.be/VIDEO_ID o https://www.youtube.com/watch?v=VIDEO_ID" value="<?= htmlspecialchars(v('video_url', $editProject, 'video_url')) ?>">
        <small>Puedes pegar cualquier enlace de YouTube.</small>
      </div>

        <div class="form-row">
            <label for="archivo_zip">Archivo ZIP (opcional)</label>
            
            <div class="file-input-container">
                <div class="file-input-wrapper" id="fileInputWrapper">
                    <input type="file" id="archivo_zip" name="archivo_zip" accept=".zip">
                    
                    <div class="file-input-content">
                        <button type="button" class="file-select-button" id="fileSelectBtn">
                            <svg class="upload-icon" viewBox="0 0 24 24">
                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                            </svg>
                            Seleccionar archivo
                        </button>
                        
                        <p class="file-status" id="fileStatus">Sin archivos seleccionados</p>
                    </div>
                </div>
                
                <div class="file-info">
                    Tamaño máximo: 50MB. Solo .zip
                </div>
            </div>
            <?php if ($editProject && !empty($editProject['archivo_zip'])): ?>
              <small>Archivo actual: <a href="<?= htmlspecialchars($editProject['archivo_zip']) ?>" target="_blank" rel="noopener">descargar</a>. Si no adjuntas uno nuevo, se conservará.</small>
            <?php endif; ?>
        </div>

      <div class="form-row">
        <label for="visibilidad">Visibilidad</label>
        <?php $vis = v('visibilidad', $editProject, 'visibilidad'); ?>
        <select id="visibilidad" name="visibilidad">
          <option value="privado" <?= ($vis === 'publico') ? '' : 'selected' ?>>Privado</option>
          <option value="publico" <?= ($vis === 'publico') ? 'selected' : '' ?>>Público</option>
        </select>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn">
          <ion-icon name="cloud-upload-outline"></ion-icon>
          <?= $editProject ? 'Actualizar Proyecto' : 'Guardar Proyecto' ?>
        </button>

        <a href="estudiante.php" class="btn" style="margin-left:8px;background:#64748b;">
          <ion-icon name="arrow-back-outline"></ion-icon>
          Volver
        </a>
      </div>
    </form>
  </div>

  <footer>
    <p>&copy; Error 404 | Todos los derechos reservados.</p>
  </footer>

  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <script>document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('archivo_zip');
    const fileWrapper = document.getElementById('fileInputWrapper');
    const fileSelectBtn = document.getElementById('fileSelectBtn');
    const fileStatus = document.getElementById('fileStatus');

    function setInitialStatus() {
        <?php if ($editProject && !empty($editProject['archivo_zip'])): ?>
            fileStatus.textContent = 'Archivo actual cargado (opcional reemplazar)';
            fileStatus.classList.add('has-file');
        <?php else: ?>
            fileStatus.textContent = 'Sin archivos seleccionados';
            fileStatus.classList.remove('has-file');
        <?php endif; ?>
    }
    setInitialStatus();
    fileSelectBtn.addEventListener('click', function(e) {
        e.preventDefault();
        fileInput.click();
    });

    fileWrapper.addEventListener('click', function() {
        fileInput.click();
    });

    fileInput.addEventListener('change', function() {
        updateFileStatus();
    });

    // Drag and drop
    fileWrapper.addEventListener('dragover', function(e) {
        e.preventDefault();
        fileWrapper.classList.add('dragover');
    });

    fileWrapper.addEventListener('dragleave', function(e) {
        e.preventDefault();
        fileWrapper.classList.remove('dragover');
    });

    fileWrapper.addEventListener('drop', function(e) {
        e.preventDefault();
        fileWrapper.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateFileStatus();
        }
    });

    function updateFileStatus() {
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            const fileName = file.name;
            const fileSize = (file.size / (1024 * 1024)).toFixed(2);
            
            fileStatus.textContent = `${fileName} (${fileSize} MB)`;
            fileStatus.classList.add('has-file');
        } else {
            setInitialStatus();
        }
    }
});</script>
</body>
</html>
