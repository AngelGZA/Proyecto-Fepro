<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Models\Estudiante;
use App\Models\DB;
$db = new DB();
$conn = $db->getConnection();

$auth = new AuthController();

// Verifica si el usuario está logueado y es estudiante
if (!$auth->isLogged() || $auth->getUserType() !== 'estudiante') {
    header("Location: ../views/formulario.php");
    exit;
}

// Obtener el usuario actual
$user = $auth->getCurrentUser();
$loggedIn = $auth->isLogged();
$userType = $auth->getUserType();
$username = $user['name'] ?? null;

$estudianteData = Estudiante::findById($user['idest'] ?? 0);

$auth = new AuthController();
if (!$auth->isLogged() || $auth->getUserType() !== 'estudiante') {
  header("Location: ../views/formulario.php"); exit;
}
$user  = $auth->getCurrentUser();
$idest = (int)($user['idest'] ?? 0);

$db = new DB(); $conn = $db->getConnection();

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editRow = null;
if ($editId > 0) {
  $stmt = $conn->prepare("SELECT * FROM certificaciones WHERE id=? AND idest=?");
  $stmt->bind_param("ii", $editId, $idest);
  $stmt->execute(); $res = $stmt->get_result();
  $editRow = $res->fetch_assoc(); $stmt->close();
  if (!$editRow) { $_SESSION['flash_err'] = 'No autorizado'; header("Location: estudiante_certificacion.php"); exit; }
}

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $titulo = trim($_POST['titulo'] ?? '');
  $emisor = trim($_POST['emisor'] ?? '');
  $fecha  = trim($_POST['fecha_emision'] ?? '');

  if ($titulo==='') $errores[]='Título obligatorio';
  if ($emisor==='') $errores[]='Emisor obligatorio';
  if ($fecha==='')  $errores[]='Fecha obligatoria';

  // archivo (PDF/PNG/JPG)
  $rutaPublica = $editRow['archivo_pdf'] ?? '';
  $hash        = $editRow['hash_sha256'] ?? '';

  $hayArchivo = isset($_FILES['archivo_pdf']) && ($_FILES['archivo_pdf']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
  if ($hayArchivo) {
    $tmp  = $_FILES['archivo_pdf']['tmp_name'];
    $name = $_FILES['archivo_pdf']['name'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf','png','jpg','jpeg'])) {
      $errores[] = 'El archivo debe ser PDF o imagen (PNG/JPG).';
    } else {
      // carpeta FÍSICA dentro de public/
      $dir = realpath(__DIR__ . '/../public') . '/certificados';
      if (!is_dir($dir)) @mkdir($dir, 0775, true);

      $safe = 'cert_' . $idest . '_' . time() . '.' . $ext;
      $dest = $dir . '/' . $safe;

      if (!@move_uploaded_file($tmp, $dest)) {
        $errores[] = 'No se pudo mover el archivo.';
      } else {
        // ruta pública que guardas en BD
        $rutaPublica = '/certificados/' . $safe;
        $hash = hash_file('sha256', $dest);
      }
    }
  } else if (!$editRow) {
    $errores[] = 'Debes adjuntar el archivo del certificado.';
  }

  if (!$errores) {
    if ($editRow) {
      $stmt = $conn->prepare("UPDATE certificaciones SET titulo=?, emisor=?, fecha_emision=?, archivo_pdf=?, hash_sha256=?, updated_at=NOW() WHERE id=? AND idest=?");
      $stmt->bind_param("ssssssi", $titulo, $emisor, $fecha, $rutaPublica, $hash, $editRow['id'], $idest);
      $ok = $stmt->execute(); $stmt->close();
      $_SESSION['flash_'.($ok?'ok':'err')] = $ok ? 'Certificación actualizada' : 'No se pudo actualizar';
      header("Location: estudiante_certificacion.php"); exit;
    } else {
      $stmt = $conn->prepare("INSERT INTO certificaciones (idest, titulo, emisor, fecha_emision, archivo_pdf, hash_sha256, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
      $stmt->bind_param("isssss", $idest, $titulo, $emisor, $fecha, $rutaPublica, $hash);
      $ok = $stmt->execute(); $stmt->close();
      $_SESSION['flash_'.($ok?'ok':'err')] = $ok ? 'Certificación agregada' : 'No se pudo crear';
      header("Location: estudiante_certificacion.php"); exit;
    }
  }
}

// listado
$stmt = $conn->prepare("SELECT * FROM certificaciones WHERE idest=? ORDER BY created_at DESC");
$stmt->bind_param("i", $idest);
$stmt->execute(); $certs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Eliminar certificación ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_certificacion'])) {
    $idcert = (int)($_POST['idcert'] ?? 0);

    if ($idcert > 0) {
        // Buscar la cert para validar dueño y traer archivo
        $stmt = $conn->prepare("SELECT archivo_pdf FROM certificaciones WHERE id=? AND idest=?");
        $stmt->bind_param("ii", $idcert, $idest);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            // Eliminar registro
            $stmt = $conn->prepare("DELETE FROM certificaciones WHERE id=? AND idest=?");
            $stmt->bind_param("ii", $idcert, $idest);
            $stmt->execute();
            $rows = $stmt->affected_rows;
            $stmt->close();

            if ($rows > 0) {
                // Eliminar archivo físico
                if (!empty($row['archivo_pdf'])) {
                    $fileName = basename($row['archivo_pdf']);
                    $absPath  = realpath(__DIR__ . '/../public') . '/certificados/' . $fileName;
                    if ($absPath && is_file($absPath)) {
                        @unlink($absPath);
                    }
                }
                $_SESSION['flash_ok'] = "Certificación eliminada";
            } else {
                $_SESSION['flash_err'] = "No se pudo eliminar la certificación";
            }
        } else {
            $_SESSION['flash_err'] = "No autorizado";
        }
    } else {
        $_SESSION['flash_err'] = "ID inválido";
    }

    header("Location: estudiante_certificacion.php");
    exit;
}

?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Certificaciones</title>
  <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
  <link rel="stylesheet" href="../assets/styleEstudianteIndex.css">
  <link rel="stylesheet" href="../assets/styleEstCertificado.css">
</head>
<body>
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
            <h2>Mis Certificaciones</h2>
        </div>
        <div class="search-container">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" id="searchInput" placeholder="Buscar proyectos...">
        </div>
    </header>

    <div class="wrap" style="max-width:900px;margin:20px auto;padding:16px;">
        <h2><?= $editRow ? 'Editar certificación' : 'Agregar certificación' ?></h2>

        <?php if (!empty($_SESSION['flash_ok'])): ?>
        <div class="alert-success" id="flashMessage"><?= htmlspecialchars($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_err'])): ?>
        <div class="alert-error" id="flashMessage"><?= htmlspecialchars($_SESSION['flash_err']); unset($_SESSION['flash_err']); ?></div>
        <?php endif; ?>
        <?php if ($errores): ?>
        <div class="alert-error"><?php foreach($errores as $e){ echo '<div>• '.htmlspecialchars($e).'</div>'; } ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" style="margin-bottom:24px;">
        <div class="form-row">
            <label>Título *</label>
            <input type="text" name="titulo" required value="<?= htmlspecialchars($editRow['titulo'] ?? ($_POST['titulo'] ?? '')) ?>">
        </div>
        <div class="form-row">
            <label>Emisor *</label>
            <input type="text" name="emisor" required value="<?= htmlspecialchars($editRow['emisor'] ?? ($_POST['emisor'] ?? '')) ?>">
        </div>
        <div class="form-row">
            <label>Fecha de emisión *</label>
            <input type="date" name="fecha_emision" required value="<?= htmlspecialchars($editRow['fecha_emision'] ?? ($_POST['fecha_emision'] ?? '')) ?>">
        </div>
        <div class="form-row">
            <label>Archivo (PDF/Imagen) <?= $editRow ? '(opcional para reemplazar)' : '*' ?></label>
            <input type="file" name="archivo_pdf" <?= $editRow ? '' : 'required' ?> accept=".pdf,.png,.jpg,.jpeg">
            <?php if ($editRow && !empty($editRow['archivo_pdf'])): ?>
            <small>Actual: <a href="<?= htmlspecialchars($editRow['archivo_pdf']) ?>" target="_blank">ver</a></small>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button class="btn-principal" type="submit"><?= $editRow ? 'Actualizar' : 'Guardar' ?></button>
            <a class="btn-principal" href="estudiante.php" style="background:#64748b;">Volver</a>
        </div>
        </form>

        <h3>Mis certificaciones</h3>
        <?php if ($certs): ?>
        <?php foreach($certs as $c): ?>
            <div class="certificacion-item" style="border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:10px;">
            <div><strong><?= htmlspecialchars($c['titulo']) ?></strong></div>
            <div>Emisor: <?= htmlspecialchars($c['emisor']) ?> · Fecha: <?= htmlspecialchars($c['fecha_emision']) ?></div>
            <?php if (!empty($c['archivo_pdf'])): ?>
                <div><a href="<?= htmlspecialchars($c['archivo_pdf']) ?>" target="_blank">Ver archivo</a></div>
            <?php endif; ?>
            <div style="font-size:12px;color:#64748b;">SHA-256: <code><?= htmlspecialchars($c['hash_sha256']) ?></code></div>
            <div style="margin-top:8px;display:flex;gap:8px;">
                <form method="get" action="estudiante_certificacion.php">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn-principal" type="submit" style="background:#3b82f6;">Editar</button>
                </form>
                <form method="post" style="display:inline;">
                <input type="hidden" name="idcert" value="<?= (int)$c['id'] ?>">
                <button type="submit" name="eliminar_certificacion" class="boton-eliminar">
                    Eliminar
                </button>
                </form>
            </div>
            </div>
        <?php endforeach; ?>
        <?php else: ?>
        <p>No tienes certificaciones aún.</p>
        <?php endif; ?>
    </div>
    
    <footer>
    <p>&copy; Error 404 | Todos los derechos reservados.</p>
  </footer>

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', ()=>{
        const f=document.getElementById('flashMessage');
        if(f){ setTimeout(()=>{ f.style.transition='opacity .5s'; f.style.opacity='0'; setTimeout(()=>f.remove(),500); }, 3500); }
    });
    </script>
</body>
</html>
