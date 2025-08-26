<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Autoload (si existe)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

// Clase DB
require_once __DIR__ . '/../src/models/DB.php';
use App\Models\DB;

/* =========================
   AUTENTICACIÓN / SESIÓN
   ========================= */
// Requiere sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../views/formulario.php");
    exit;
}

// Datos de sesión
$userType = $_SESSION['user_type'] ?? '';
$userId   = $_SESSION['user_id'] ?? 0;      // aquí asumimos que es idmae
$username = $_SESSION['user_name'] ?? '';

// Asegura que sea DOCENTE
if ($userType !== 'docente') {
    header("Location: ../views/formulario.php");
    exit;
}

/* =========================
   CONEXIÓN BD
   ========================= */
$dbModel = new DB();
$db = $dbModel->getConnection();

/* =========================
   PERFIL DEL DOCENTE
   ========================= */
// userId es idmae
$docenteInfo = null;
$stmt = $db->prepare("SELECT * FROM maestro WHERE idmae = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$docenteInfo = $result->fetch_assoc();
$stmt->close();

/* =========================
   PROYECTOS PÚBLICOS
   ========================= */
$proyectos = [];
$stmt = $db->prepare("
  SELECT
    id, titulo, descripcion_previa,
    repo_url, video_url, archivo_zip,
    created_at, estudiante,
    COALESCE(promedio,0)     AS promedio,
    COALESCE(total_votos,0)  AS total_votos
  FROM v_proyectos_publicos
  ORDER BY COALESCE(promedio,0) DESC, created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$proyectos = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* =========================
   PROYECTOS GUARDADOS (DOCENTE)
   ========================= */
$proyectosGuardados = [];
$stmt = $db->prepare("
    SELECT p.id AS idproyecto, p.titulo
    FROM proyectos p
    INNER JOIN maestro_proyecto_favorito mpf ON p.id = mpf.idproyecto
    WHERE mpf.idmae = ?
    ORDER BY mpf.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$proyectosGuardados = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* =========================
   MIS RATINGS (DOCENTE)
   ========================= */
$misRatingsDoc = [];
$stmt = $db->prepare("SELECT idproyecto, estrellas, comentario FROM proyecto_rating_maestro WHERE idmae=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
  $misRatingsDoc[(int)$r['idproyecto']] = [
    'estrellas'  => (int)$r['estrellas'],
    'comentario' => (string)$r['comentario']
  ];
}
$stmt->close();

/* =========================
   FAVORITOS: HANDLERS POST
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'guardar_proyecto' && isset($_POST['idproyecto'])) {
        // Si no existe, insertar
        $check = $db->prepare("SELECT 1 FROM maestro_proyecto_favorito WHERE idmae = ? AND idproyecto = ?");
        $check->bind_param("ii", $userId, $_POST['idproyecto']);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if (!$exists) {
            $stmt = $db->prepare("INSERT INTO maestro_proyecto_favorito (idmae, idproyecto) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $_POST['idproyecto']);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($_POST['accion'] === 'eliminar_proyecto' && isset($_POST['idproyecto'])) {
        $stmt = $db->prepare("DELETE FROM maestro_proyecto_favorito WHERE idmae = ? AND idproyecto = ?");
        $stmt->bind_param("ii", $userId, $_POST['idproyecto']);
        $stmt->execute();
        $stmt->close();
    }
    // Refresca
    header("Location: docente.php");
    exit;
}

/* =========================
   BÚSQUEDA
   ========================= */
$terminoBusqueda = '';
$proyectosFiltrados = $proyectos;
if (isset($_GET['busqueda']) && $_GET['busqueda'] !== '') {
    $terminoBusqueda = trim($_GET['busqueda']);
    $proyectosFiltrados = array_filter($proyectos, function($p) use ($terminoBusqueda) {
      return stripos($p['titulo'], $terminoBusqueda) !== false
          || stripos($p['descripcion_previa'] ?? '', $terminoBusqueda) !== false
          || stripos($p['estudiante'], $terminoBusqueda) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>CodEval - Panel Docentes</title>
    <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
    <!-- usa los mismos estilos que empresa-->
    <link rel="stylesheet" href="../assets/styleEmpresa.css">
    <link rel="stylesheet" href="../assets/styleVistaPro1.css">
  </head>

  <body>
    <!-- BARRA DE NAVEGACIÓN -->
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
            <a href="../index.php">
              <ion-icon name="home-outline"></ion-icon>
              <span>Inicio</span>
            </a>
          </li>
          <li>
            <a id="Docente" href="docente.php">
              <ion-icon name="create-outline"></ion-icon>
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
            <!-- Mi Perfil (docente) -->
            <a href="docente_perfil.php" class="menu-link">
              <ion-icon name="school-outline"></ion-icon>
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

    <!-- ENCABEZADO -->
    <header>
      <div class="empresa-header">
        <div class="empresa-titulo">
          <h1><ion-icon name="school-outline"></ion-icon> <?= htmlspecialchars($docenteInfo['nombre'] ?? 'Docente') ?></h1>
          <p>Panel de docentes</p>
        </div>
        <div class="empresa-busqueda">
          <div class="search-container">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" id="busquedaInput" placeholder="Buscar proyectos..." value="<?= htmlspecialchars($terminoBusqueda) ?>">
          </div>
        </div>
      </div>
    </header>

    <!-- CONTENIDO -->
    <main>
      <div class="dashboard-container">
        <!-- Panel lateral izquierdo -->
        <div class="sidebar-panel">
          <!-- Información del docente -->
          <div class="datos-empresa">
            <h3><ion-icon name="information-circle"></ion-icon> Datos del Docente</h3>
            <?php if ($docenteInfo): ?>
              <p><ion-icon name="person-circle-outline"></ion-icon> <?= htmlspecialchars($docenteInfo['nombre']) ?></p>
              <?php if (!empty($docenteInfo['email'])): ?>
                <p><ion-icon name="mail"></ion-icon> <?= htmlspecialchars($docenteInfo['email']) ?></p>
              <?php endif; ?>
              <?php if (!empty($docenteInfo['telefono'])): ?>
                <p><ion-icon name="call"></ion-icon> <?= htmlspecialchars($docenteInfo['telefono']) ?></p>
              <?php endif; ?>
              <?php if (!empty($docenteInfo['institucion_nombre'])): ?>
                <p><ion-icon name="business-outline"></ion-icon> <?= htmlspecialchars($docenteInfo['institucion_nombre']) ?></p>
              <?php endif; ?>
              <?php if (!empty($docenteInfo['especialidad'])): ?>
                <p><ion-icon name="ribbon-outline"></ion-icon> <?= htmlspecialchars($docenteInfo['especialidad']) ?></p>
              <?php endif; ?>
            <?php else: ?>
              <p>Completa tu información en tu perfil.</p>
            <?php endif; ?>
            <a href="docente_perfil.php" class="btn-editar-perfil">
              <ion-icon name="create"></ion-icon> Editar Perfil
            </a>
          </div>

          <!-- Proyectos guardados (docente) -->
          <div class="proyectos-guardados">
            <h3><ion-icon name="bookmark"></ion-icon> Proyectos Guardados</h3>
            <?php if (!empty($proyectosGuardados)): ?>
              <?php foreach ($proyectosGuardados as $proyecto): ?>
                <div class="proyecto-guardado" id="guardado-<?= $proyecto['idproyecto'] ?>">
                  <span><?= htmlspecialchars($proyecto['titulo']) ?></span>
                  <div class="acciones-guardado">
                    <a href="ver_proyecto_maestro.php?id=<?= (int)$proyecto['idproyecto'] ?>" class="btn-ver" title="Ver proyecto">
                      <ion-icon name="eye"></ion-icon>
                    </a>
                    <button class="btn-eliminar" title="Eliminar de guardados" onclick="eliminarProyectoGuardadoDoc(<?= (int)$proyecto['idproyecto'] ?>)">
                      <ion-icon name="trash"></ion-icon>
                    </button>
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

          <section class="grid" id="gridProyectos">
          <?php
          // Mapa rápido de guardados para pintar el estado del bookmark
          $guardadosMap = [];
          foreach ($proyectosGuardados as $g) $guardadosMap[(int)$g['idproyecto']] = true;

          foreach ($proyectosFiltrados as $proyecto):
            $pid = (int)$proyecto['id'];
            $esGuardado = !empty($guardadosMap[$pid]);

            // Comentarios recientes (si quieres mostrarlos como en empresas)
            $comentarios = [];
            $cstmt = $db->prepare("
              SELECT comentario
              FROM proyecto_rating_estudiante
              WHERE idproyecto = ? AND comentario IS NOT NULL AND comentario <> ''
              ORDER BY created_at DESC
              LIMIT 3
            ");
            $cstmt->bind_param("i", $pid);
            $cstmt->execute();
            $cres = $cstmt->get_result();
            while ($row = $cres->fetch_assoc()) { $comentarios[] = $row['comentario']; }
            $cstmt->close();
          ?>
            <article class="card proyecto" data-id="<?= $pid ?>">
              <div class="card__header">
                <h3 class="card__title"><?= htmlspecialchars($proyecto['titulo']) ?></h3>
                <div class="card__author">Por <strong><?= htmlspecialchars($proyecto['estudiante']) ?></strong></div>

                <!-- Botón Guardar (versión docente) -->
                <button class="btn-guardar <?= $esGuardado ? 'activo' : '' ?>"
                        title="<?= $esGuardado ? 'Eliminar de guardados' : 'Guardar proyecto' ?>"
                        onclick="toggleGuardarProyectoDoc(<?= $pid ?>, this)">
                  <ion-icon name="bookmark"></ion-icon>
                </button>
              </div>

              <p class="card__desc"><?= nl2br(htmlspecialchars($proyecto['descripcion_previa'] ?? 'Sin descripción')) ?></p>

              <div class="card__row">
                <span class="badge avg">⭐ <?= number_format((float)$proyecto['promedio'], 2) ?> (<?= (int)$proyecto['total_votos'] ?>)</span>
                <div class="stars" aria-label="Promedio">
                  <?php $filled = (int)round($proyecto['promedio']); for ($i=1; $i<=5; $i++): ?>
                    <button type="button" class="star <?= $i <= $filled ? 'active' : '' ?>" disabled>★</button>
                  <?php endfor; ?>
                </div>
              </div>

              <?php if (!empty($comentarios)): ?>
                <div class="card__form" style="margin-top:6px">
                  <strong style="display:block;margin-bottom:6px">Comentarios recientes</strong>
                  <ul style="padding-left:18px;margin:0">
                    <?php foreach ($comentarios as $c): ?><li><?= htmlspecialchars($c) ?></li><?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <div class="card__footer">
                <a class="btn btn-primary" href="ver_proyecto_docente.php?id=<?= $pid ?>">Ver detalles</a>
              </div>

              <footer class="card__links" style="margin-top:10px">
                <?php if (!empty($proyecto['repo_url'])): ?>
                  <a href="<?= htmlspecialchars($proyecto['repo_url']) ?>" target="_blank" rel="noopener">Repo</a>
                <?php endif; ?>
                <?php if (!empty($proyecto['video_url'])): ?>
                  <a href="<?= htmlspecialchars($proyecto['video_url']) ?>" target="_blank" rel="noopener">Video</a>
                <?php endif; ?>
                <?php if (!empty($proyecto['archivo_zip'])): ?>
                  <a href="<?= htmlspecialchars($proyecto['archivo_zip']) ?>" target="_blank" rel="noopener">ZIP</a>
                <?php endif; ?>
              </footer>
            </article>
          <?php endforeach; ?>
          </section>
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
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/scrollreveal"></script>

    <!-- JS específico para docentes (clon de scriptEmpresa.js pero a endpoints de maestro) -->
    <script>
      // Buscar en vivo
      const input = document.getElementById('busquedaInput');
      if (input) {
        input.addEventListener('keyup', (e) => {
          if (e.key === 'Enter') {
            const q = input.value.trim();
            const url = new URL(window.location.href);
            if (q) url.searchParams.set('busqueda', q); else url.searchParams.delete('busqueda');
            window.location.href = url.toString();
          }
        });
      }

      // Quitar guardado desde panel izquierdo
      function eliminarProyectoGuardadoDoc(id) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'docente.php';
        form.innerHTML = `
          <input type="hidden" name="accion" value="eliminar_proyecto">
          <input type="hidden" name="idproyecto" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
      }

      // Toggle guardado desde tarjeta
      function toggleGuardarProyectoDoc(id, btn) {
        const activo = btn.classList.contains('activo');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'docente.php';
        form.innerHTML = `
          <input type="hidden" name="accion" value="${activo ? 'eliminar_proyecto' : 'guardar_proyecto'}">
          <input type="hidden" name="idproyecto" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    </script>
  </body>
</html>
