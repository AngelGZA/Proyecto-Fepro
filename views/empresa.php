<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Incluir autoload de Composer si existe
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

// Incluir tu clase DB
require_once __DIR__ . '/../src/models/DB.php';

use App\Models\DB;

// Verifica si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../views/formulario.php");
    exit;
}

// Obtener información del usuario desde la sesión
$userType = $_SESSION['user_type'] ?? '';
$userId = $_SESSION['user_id'] ?? '';
$username = $_SESSION['user_name'] ?? '';

// Verificar que sea una empresa
if ($userType !== 'empresa') {
    header("Location: ../views/formulario.php");
    exit;
}

// Conexión a base de datos usando tu clase DB
$dbModel = new DB();
$db = $dbModel->getConnection();

// Obtener información de la empresa
$empresaInfo = null;
$stmt = $db->prepare("SELECT * FROM empresa WHERE idemp = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$empresaInfo = $result->fetch_assoc();

// Obtener proyectos públicos
$proyectos = [];
$stmt = $db->prepare("
    SELECT p.*, e.name as estudiante 
    FROM proyectos p 
    INNER JOIN estudiante e ON p.idest = e.idest 
    WHERE p.visibilidad = 'publico' 
    ORDER BY p.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$proyectos = $result->fetch_all(MYSQLI_ASSOC);

// Obtener proyectos guardados por la empresa (usando la tabla existente empresa_proyecto_favorito)
$proyectosGuardados = [];
$stmt = $db->prepare("
    SELECT p.id as idproyecto, p.titulo 
    FROM proyectos p 
    INNER JOIN empresa_proyecto_favorito epf ON p.id = epf.idproyecto 
    WHERE epf.idemp = ?
    ORDER BY epf.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$proyectosGuardados = $result->fetch_all(MYSQLI_ASSOC);

// Manejar guardar/eliminar proyectos favoritos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'guardar_proyecto' && isset($_POST['idproyecto'])) {
        // Verificar si ya existe
        $checkStmt = $db->prepare("SELECT * FROM empresa_proyecto_favorito WHERE idemp = ? AND idproyecto = ?");
        $checkStmt->bind_param("ii", $userId, $_POST['idproyecto']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            $stmt = $db->prepare("INSERT INTO empresa_proyecto_favorito (idemp, idproyecto) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $_POST['idproyecto']);
            $stmt->execute();
        }
    } elseif ($_POST['accion'] === 'eliminar_proyecto' && isset($_POST['idproyecto'])) {
        $stmt = $db->prepare("DELETE FROM empresa_proyecto_favorito WHERE idemp = ? AND idproyecto = ?");
        $stmt->bind_param("ii", $userId, $_POST['idproyecto']);
        $stmt->execute();
    }
    // Recargar la página para ver los cambios
    header("Location: empresa.php");
    exit;
}

// Búsqueda de proyectos
$terminoBusqueda = '';
$proyectosFiltrados = $proyectos;

if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
    $terminoBusqueda = trim($_GET['busqueda']);
    $proyectosFiltrados = array_filter($proyectos, function($proyecto) use ($terminoBusqueda) {
        return stripos($proyecto['titulo'], $terminoBusqueda) !== false || 
               stripos($proyecto['descripcion'], $terminoBusqueda) !== false ||
               stripos($proyecto['estudiante'], $terminoBusqueda) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Lobo Chamba - Panel Empresa</title>
    <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
    <link rel="stylesheet" href="../assets/styleEmpresa.css">
    <link rel="stylesheet" href="../assets/empresaStyles.css">
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Opción Mi Perfil -->
                    <a href="<?= htmlspecialchars($userType === 'estudiante' ? 'estudiante_perfil.php' : 'empresa_perfil.php') ?>" class="menu-link">
                        <ion-icon name="<?= htmlspecialchars($userType === 'estudiante' ? 'person-circle-outline' : 'business-outline') ?>"></ion-icon>
                        <span>Mi Perfil</span>
                    </a>
                    <?php else: ?>
                    <!-- Opción Iniciar Sesión -->
                    <a href="../views/formulario.php" class="menu-link">
                        <ion-icon name="person-add"></ion-icon>
                        <span>Iniciar Sesión</span>
                    </a>
                    <?php endif; ?>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
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
      <!-- Header con nombre de empresa y búsqueda -->
      <div class="empresa-header">
        <div class="empresa-titulo">
          <h1><ion-icon name="business"></ion-icon> <?= htmlspecialchars($empresaInfo['name'] ?? 'Empresa') ?></h1>
          <p>Panel de administración de proyectos</p>
        </div>
        <div class="empresa-busqueda">
          <form method="GET" action="empresa.php" class="busqueda-form">
            <input type="text" name="busqueda" placeholder="Buscar proyectos..." value="<?= htmlspecialchars($terminoBusqueda) ?>">
            <button type="submit">
              <ion-icon name="search"></ion-icon>
            </button>
          </form>
        </div>
      </div>
      
      <div class="dashboard-container">
        <!-- Panel lateral izquierdo -->
        <div class="sidebar-panel">
          <!-- Información de la empresa -->
          <div class="datos-empresa">
            <h3><ion-icon name="information-circle"></ion-icon> Datos de la Empresa</h3>
            <?php if ($empresaInfo): ?>
              <p><ion-icon name="business"></ion-icon> <?= htmlspecialchars($empresaInfo['name']) ?></p>
              <?php if (!empty($empresaInfo['email'])): ?>
                <p><ion-icon name="mail"></ion-icon> <?= htmlspecialchars($empresaInfo['email']) ?></p>
              <?php endif; ?>
              <?php if (!empty($empresaInfo['telefono'])): ?>
                <p><ion-icon name="call"></ion-icon> <?= htmlspecialchars($empresaInfo['telefono']) ?></p>
              <?php endif; ?>
              <?php if (!empty($empresaInfo['direccion'])): ?>
                <p><ion-icon name="location"></ion-icon> <?= htmlspecialchars($empresaInfo['direccion']) ?></p>
              <?php endif; ?>
              <?php if (!empty($empresaInfo['rfc'])): ?>
                <p><ion-icon name="document-text"></ion-icon> RFC: <?= htmlspecialchars($empresaInfo['rfc']) ?></p>
              <?php endif; ?>
            <?php else: ?>
              <p>Complete la información de su empresa en su perfil.</p>
            <?php endif; ?>
            <a href="empresa_perfil.php" class="btn-editar-perfil">
              <ion-icon name="create"></ion-icon> Editar Perfil
            </a>
          </div>
          
          <!-- Proyectos guardados -->
          <div class="proyectos-guardados">
            <h3><ion-icon name="bookmark"></ion-icon> Proyectos Guardados</h3>
            
            <?php if (!empty($proyectosGuardados)): ?>
              <?php foreach ($proyectosGuardados as $proyecto): ?>
                <div class="proyecto-guardado">
                  <span><?= htmlspecialchars($proyecto['titulo']) ?></span>
                  <div class="acciones-guardado">
                    <a href="ver_proyecto.php?id=<?= $proyecto['idproyecto'] ?>" class="btn-ver" title="Ver proyecto">
                      <ion-icon name="eye"></ion-icon>
                    </a>
                    <form method="POST">
                      <input type="hidden" name="idproyecto" value="<?= $proyecto['idproyecto'] ?>">
                      <input type="hidden" name="accion" value="eliminar_proyecto">
                      <button type="submit" class="btn-eliminar" title="Eliminar de guardados">
                        <ion-icon name="trash"></ion-icon>
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-state">
                <ion-icon name="bookmark-outline"></ion-icon>
                <p>No tienes proyectos guardados.</p>
                <p>Haz clic en el icono de marcador para guardar proyectos interesantes.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Panel principal de proyectos -->
        <div class="proyectos-panel">
          <div class="panel-header">
            <h2><ion-icon name="folder-open"></ion-icon> Proyectos de Estudiantes</h2>
            <?php if (!empty($terminoBusqueda)): ?>
              <p class="resultados-busqueda">Mostrando resultados para: <strong>"<?= htmlspecialchars($terminoBusqueda) ?>"</strong></p>
            <?php endif; ?>
          </div>
          
          <?php if (!empty($proyectosFiltrados)): ?>
            <div class="proyectos-lista">
              <?php foreach ($proyectosFiltrados as $proyecto): 
                $esGuardado = false;
                foreach ($proyectosGuardados as $guardado) {
                  if ($guardado['idproyecto'] == $proyecto['id']) {
                    $esGuardado = true;
                    break;
                  }
                }
              ?>
                <div class="proyecto-card fade-in">
                  <div class="proyecto-header">
                    <h3 class="proyecto-titulo"><?= htmlspecialchars($proyecto['titulo']) ?></h3>
                    <form method="POST" class="form-guardar">
                      <input type="hidden" name="idproyecto" value="<?= $proyecto['id'] ?>">
                      <input type="hidden" name="accion" value="<?= $esGuardado ? 'eliminar_proyecto' : 'guardar_proyecto' ?>">
                      <button type="submit" class="btn-guardar <?= $esGuardado ? 'activo' : '' ?>" title="<?= $esGuardado ? 'Eliminar de guardados' : 'Guardar proyecto' ?>">
                        <ion-icon name="bookmark"></ion-icon>
                      </button>
                    </form>
                  </div>
                  <p class="proyecto-descripcion">
                    <?= !empty($proyecto['descripcion']) ? 
                        (strlen($proyecto['descripcion']) > 150 ? 
                        htmlspecialchars(substr($proyecto['descripcion'], 0, 150)) . '...' : 
                        htmlspecialchars($proyecto['descripcion'])) : 
                        'Sin descripción' ?>
                  </p>
                  <p class="proyecto-estudiante">
                    <ion-icon name="person-circle"></ion-icon>
                    <strong>Estudiante:</strong> <?= htmlspecialchars($proyecto['estudiante']) ?>
                  </p>
                  <div class="proyecto-acciones">
                    <a href="ver_proyecto.php?id=<?= $proyecto['id'] ?>" class="btn-ver-mas">Ver detalles</a>
                    <?php if ($esGuardado): ?>
                      <form method="POST" class="form-eliminar">
                        <input type="hidden" name="idproyecto" value="<?= $proyecto['id'] ?>">
                        <input type="hidden" name="accion" value="eliminar_proyecto">
                        <button type="submit" class="btn-eliminar" title="Eliminar de guardados">
                          <ion-icon name="trash"></ion-icon> Eliminar
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <ion-icon name="search-outline"></ion-icon>
              <p><?= empty($terminoBusqueda) ? 'No hay proyectos disponibles en este momento.' : 'No se encontraron proyectos que coincidan con tu búsqueda.' ?></p>
              <p><?= empty($terminoBusqueda) ? 'Vuelve más tarde para ver nuevos proyectos de estudiantes.' : 'Intenta con otros términos de búsqueda.' ?></p>
            </div>
          <?php endif; ?>
        </div>
      </div>
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
    <script src="../funciones/scriptEmpresa.js"></script>
    <script src="../funciones/empresaScripts.js"></script>
  </body>
</html>