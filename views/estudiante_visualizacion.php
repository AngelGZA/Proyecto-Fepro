<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Models\Estudiante;
use App\Models\DB;

$db   = new DB();
/** @var mysqli $conn  Asumiendo que DB::getConnection() retorna MySQLi */
$conn = $db->getConnection();

if ($conn instanceof mysqli) {
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $conn->set_charset('utf8mb4');
}

$auth = new AuthController();

if (!$auth->isLogged() || $auth->getUserType() !== 'estudiante') {
  header("Location: ../views/formulario.php");
  exit;
}

$user       = $auth->getCurrentUser();
$loggedIn   = $auth->isLogged();
$userType   = $auth->getUserType();
$miIdest    = (int)($user['idest'] ?? 0);
$estudiante = Estudiante::findById($miIdest);

/* =========================
   FAVORITOS (estudiante) - AJAX POST
   ========================= */
function isAjaxReq(): bool {
  $xrw = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
  return isset($_POST['ajax']) || $xrw === 'xmlhttprequest' || $xrw === 'fetch';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
  // Solo estudiantes
  if ($auth->getUserType() !== 'estudiante') {
    if (isAjaxReq()) {
      http_response_code(401);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit;
    }
    header('Location: estudiante_visualizacion.php'); exit;
  }

  $accion = $_POST['accion'];
  $idp    = (int)($_POST['idproyecto'] ?? 0);

  if (($accion === 'guardar_proyecto' || $accion === 'eliminar_proyecto') && $idp > 0) {
    try {
      // Valida que el proyecto sea público y NO sea mío
      $stmt = $conn->prepare("SELECT id, idest FROM proyectos WHERE id=? AND visibilidad='publico' LIMIT 1");
      $stmt->bind_param("i", $idp);
      $stmt->execute();
      $proj = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if (!$proj) {
        if (isAjaxReq()) { http_response_code(404); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'Proyecto no existe o no es público']); exit; }
        header('Location: estudiante_visualizacion.php'); exit;
      }
      if ((int)$proj['idest'] === $miIdest) {
        if (isAjaxReq()) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'No puedes guardar tu propio proyecto']); exit; }
        header('Location: estudiante_visualizacion.php'); exit;
      }

      if ($accion === 'guardar_proyecto') {
        // Evita duplicados
        $chk = $conn->prepare("SELECT 1 FROM estudiante_proyecto_favorito WHERE idest=? AND idproyecto=?");
        $chk->bind_param("ii", $miIdest, $idp);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();

        if (!$exists) {
          $ins = $conn->prepare("INSERT INTO estudiante_proyecto_favorito (idest, idproyecto) VALUES (?, ?)");
          $ins->bind_param("ii", $miIdest, $idp);
          $ins->execute();
          $ins->close();
        }

        // Opcional: devolver título
        $tit = '';
        $ts = $conn->prepare("SELECT titulo FROM proyectos WHERE id=?");
        $ts->bind_param("i", $idp);
        $ts->execute();
        $tres = $ts->get_result()->fetch_assoc();
        if ($tres) $tit = (string)$tres['titulo'];
        $ts->close();

        if (isAjaxReq()) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>true,'accion'=>'guardar','idproyecto'=>$idp,'titulo'=>$tit]); exit; }
        header('Location: estudiante_visualizacion.php'); exit;
      }

      if ($accion === 'eliminar_proyecto') {
        $del = $conn->prepare("DELETE FROM estudiante_proyecto_favorito WHERE idest=? AND idproyecto=?");
        $del->bind_param("ii", $miIdest, $idp);
        $del->execute();
        $del->close();

        if (isAjaxReq()) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>true,'accion'=>'eliminar','idproyecto'=>$idp]); exit; }
        header('Location: estudiante_visualizacion.php'); exit;
      }

    } catch (Throwable $e) {
      if (isAjaxReq()) { http_response_code(500); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'Error del servidor']); exit; }
      header('Location: estudiante_visualizacion.php'); exit;
    }
  }
  // Si era otra 'accion', continúa al siguiente handler
}

/* =========================
   RATING (estudiante) - AJAX POST (tu lógica existente)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['accion'])) {
  header('Content-Type: application/json; charset=utf-8');

  $body = json_decode(file_get_contents('php://input'), true);
  if (!is_array($body)) { $body = $_POST; }

  $mode      = $body['mode']      ?? '';
  $idproy    = isset($body['idproyecto']) ? (int)$body['idproyecto'] : 0;
  $estrellas = isset($body['estrellas'])  ? (int)$body['estrellas']  : 0;
  $coment    = isset($body['comentario']) ? trim((string)$body['comentario']) : '';

  if ($mode !== 'save') {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Modo inválido']); exit;
  }
  if ($idproy <= 0 || $estrellas < 1 || $estrellas > 5) {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Datos inválidos']); exit;
  }
  if (mb_strlen($coment) > 300) {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Comentario demasiado largo (máx. 300)']); exit;
  }

  try {
    $stmt = $conn->prepare("SELECT p.id, p.idest FROM proyectos p WHERE p.id=? AND p.visibilidad='publico' LIMIT 1");
    $stmt->bind_param("i", $idproy);
    $stmt->execute();
    $proj = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$proj) {
      http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Proyecto no existe o no es público']); exit;
    }
    if ((int)$proj['idest'] === $miIdest) {
      http_response_code(403); echo json_encode(['ok'=>false,'error'=>'No puedes calificar tu propio proyecto']); exit;
    }

    // Upsert rating
    $stmt = $conn->prepare("
      INSERT INTO proyecto_rating_estudiante (idproyecto, idest, estrellas, comentario)
      VALUES (?,?,?,?)
      ON DUPLICATE KEY UPDATE
        estrellas=VALUES(estrellas),
        comentario=VALUES(comentario),
        created_at=NOW()
    ");
    $stmt->bind_param("iiis", $idproy, $miIdest, $estrellas, $coment);
    $stmt->execute();
    $stmt->close();

    // Resumen global
    $stmt = $conn->prepare("SELECT total_votos, promedio FROM v_proyecto_rating_resumen WHERE idproyecto=? LIMIT 1");
    $stmt->bind_param("i", $idproy);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $row = $row ?: ['total_votos'=>0, 'promedio'=>0];
    echo json_encode([
      'ok'           => true,
      'idproyecto'   => $idproy,
      'mi_rating'    => $estrellas,
      'mi_comentario'=> $coment,
      'total_votos'  => (int)$row['total_votos'],
      'promedio'     => (float)$row['promedio']
    ]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Error del servidor']);
  }
  exit;
}

/* =========================
   GET: cargar proyectos (solo otros) + mis guardados
   ========================= */
$terminoBusqueda = '';
try {
  // Proyectos públicos (solo otros)
  $proyectos = [];
  $stmt = $conn->prepare("
    SELECT id, titulo, descripcion_previa, repo_url, video_url, archivo_zip,
           created_at, idest, estudiante,
           COALESCE(promedio,0) AS promedio,
           COALESCE(total_votos,0) AS total_votos
    FROM v_proyectos_publicos
    WHERE idest <> ?
    ORDER BY COALESCE(promedio,0) DESC, created_at DESC
  ");
  $stmt->bind_param("i", $miIdest);
  $stmt->execute();
  $res = $stmt->get_result();
  $proyectos = $res->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // Filtro en memoria al estilo docente (si usas ?busqueda=)
  $proyectosFiltrados = $proyectos;
  if (isset($_GET['busqueda']) && $_GET['busqueda'] !== '') {
    $terminoBusqueda = trim($_GET['busqueda']);
    $proyectosFiltrados = array_filter($proyectos, function($p) use ($terminoBusqueda) {
      return stripos($p['titulo'], $terminoBusqueda) !== false
          || stripos($p['descripcion_previa'] ?? '', $terminoBusqueda) !== false
          || stripos($p['estudiante'], $terminoBusqueda) !== false;
    });
  }

  // Mis guardados (para la cajita y marcar el ícono)
  $proyectosGuardados = [];
  $st = $conn->prepare("
    SELECT p.id AS idproyecto, p.titulo
    FROM proyectos p
    INNER JOIN estudiante_proyecto_favorito epf ON p.id = epf.idproyecto
    WHERE epf.idest = ?
    ORDER BY epf.created_at DESC
  ");
  $st->bind_param("i", $miIdest);
  $st->execute();
  $r = $st->get_result();
  $proyectosGuardados = $r->fetch_all(MYSQLI_ASSOC);
  $st->close();

} catch (Throwable $e) {
  http_response_code(500);
  echo "Error al cargar proyectos.";
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Descubre Proyectos - CodEval</title>
  <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
  <link rel="stylesheet" href="../assets/styleEmpresa.css">
  <link rel="stylesheet" href="../assets/styleVistaPro1.css"><!-- (mismo que docente si lo usas) -->
</head>
<body>

  <!-- ====== NO TOCAR: SIDEBAR ORIGINAL ====== -->
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
          <a href="estudiante.php">
            <ion-icon name="school"></ion-icon>
            <span>Estudiante</span>
          </a>
        </li>
        <li>
          <a id="estudiante" href="estudiante_visualizacion.php" class="<?= basename($_SERVER['PHP_SELF']) == 'estudiante_visualizacion.php' ? 'active' : '' ?>">
            <ion-icon name="telescope-outline"></ion-icon>
            <span>Descubrir proyectos</span>
          </a>
        </li>
      </ul>
      <ul class="menu-inferior">
        <li class="menu-item">
          <?php if ($loggedIn): ?>
          <a href="<?= htmlspecialchars($userType === 'estudiante' ? 'estudiante_perfil.php' : 'empresa_perfil.php') ?>" class="menu-link">
            <ion-icon name="<?= htmlspecialchars($userType === 'estudiante' ? 'person-circle-outline' : 'business-outline') ?>"></ion-icon>
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

  <!-- ====== NO TOCAR: HEADER ORIGINAL ====== -->
  <header>
    <div class="header-title">
      <h2>Descubre Ideas</h2>
    </div>
    <div class="search-container">
      <ion-icon name="search-outline"></ion-icon>
      <input type="text" id="searchInput" placeholder="Buscar proyectos...">
    </div>
  </header>

  <!-- ===== CONTENIDO ===== -->
  <main>
    <div class="dashboard-container">
      <div class="sidebar-panel">
        <!-- Proyectos guardados (estudiante) -->
        <div class="proyectos-guardados">
          <h3><ion-icon name="bookmark"></ion-icon> Proyectos Guardados</h3>
          <?php if (!empty($proyectosGuardados)): ?>
            <?php foreach ($proyectosGuardados as $proyecto): ?>
              <div class="proyecto-guardado" id="guardado-<?= (int)$proyecto['idproyecto'] ?>">
                <span><?= htmlspecialchars($proyecto['titulo']) ?></span>
                <div class="acciones-guardado">
                  <a href="ver_proyecto_estudiante.php?id=<?= (int)$proyecto['idproyecto'] ?>" class="btn-ver" title="Ver proyecto">
                    <ion-icon name="eye"></ion-icon>
                  </a>
                  <button type="button" class="btn-eliminar" title="Eliminar de guardados" onclick="eliminarProyectoGuardadoEst(<?= (int)$proyecto['idproyecto'] ?>)">
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

      <!-- Columna derecha: Panel principal de proyectos (mismo diseño que pegaste) -->
      <div class="proyectos-panel">
        <div class="panel-header">
          <h2><ion-icon name="folder-open"></ion-icon> Proyectos de Estudiantes</h2>
          <?php if (!empty($terminoBusqueda)): ?>
            <p class="resultados-busqueda">Mostrando resultados para: <strong>"<?= htmlspecialchars($terminoBusqueda) ?>"</strong></p>
          <?php endif; ?>
        </div>

        <section class="grid" id="gridProyectos">
        <?php
          // Mapa guardados -> para pintar bookmark activo
          $guardadosMap = [];
          foreach ($proyectosGuardados as $g) $guardadosMap[(int)$g['idproyecto']] = true;

          foreach ($proyectosFiltrados as $proyecto):
            $pid = (int)$proyecto['id'];
            $esGuardado = !empty($guardadosMap[$pid]);

            // Comentarios recientes
            $comentarios = [];
            $cstmt = $conn->prepare("
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

              <!-- Botón Guardar (versión estudiante) -->
              <button type="button"
                      class="btn-guardar <?= $esGuardado ? 'activo' : '' ?>"
                      title="<?= $esGuardado ? 'Eliminar de guardados' : 'Guardar proyecto' ?>"
                      onclick="toggleGuardarProyectoEst(<?= $pid ?>, this)">
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
              <a class="btn btn-primary" href="ver_proyecto_estudiante.php?id=<?= $pid ?>">Ver detalles</a>
            </div>

            <footer class="card__links" style="margin-top:10px">
              <?php if (!empty($proyecto['repo_url'])): ?>
                <a href="<?= htmlspecialchars($proyecto['repo_url']) ?>" target="_blank" rel="noopener">Repo</a>
              <?php endif; ?>
              <?php if (!empty($proyecto['video_url'])): ?>
                <a href="<?= htmlspecialchars($proyecto['video_url']) ?>" target="_blank" rel="noopener">Video</a>
              <?php endif; ?>
              <?php if (!empty($proyecto['archivo_zip'])): ?>
                <a href="/Proyecto-Fepro/public<?= htmlspecialchars($proyecto['archivo_zip']) ?>" target="_blank" rel="noopener">ZIP</a>
              <?php endif; ?>
            </footer>
          </article>
        <?php endforeach; ?>
        </section>
      </div>
    </div>
  </main>

  <!--footer-->
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

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    /* ================ BÚSQUEDA EN VIVO (usa tu #searchInput) ================ */
    const input = document.getElementById('busquedaInput') || document.getElementById('searchInput');
    const debounce = (fn, d=150) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), d); }; };
    if (input) {
      const filtrar = () => {
        const q = (input.value || '').trim().toLowerCase();
        document.querySelectorAll('.card.proyecto').forEach(card => {
          const title = card.querySelector('.card__title')?.textContent || '';
          const desc  = card.querySelector('.card__desc')?.textContent  || '';
          const auth  = card.querySelector('.card__author')?.textContent|| '';
          const haystack = (title + ' ' + desc + ' ' + auth).toLowerCase();
          card.style.display = !q || haystack.includes(q) ? '' : 'none';
        });
      };
      input.addEventListener('input', debounce(filtrar, 150));
    }

    /* ================ FAVORITOS POR FETCH (SIN RECARGA) - ESTUDIANTE ================ */
    async function postAjaxEst(bodyObj){
      const body = new URLSearchParams({ ...bodyObj, ajax: '1' });
      const res  = await fetch('estudiante_visualizacion.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'fetch'
        },
        body
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json().catch(() => ({}));
      if (!data || data.ok !== true) throw new Error(data?.error || 'Operación fallida');
      return data;
    }

    function escapeHtml(str){
      return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    // Añadir a cajita (mismo markup que docente)
    function addGuardadoListEst(id, titulo){
      const cont = document.querySelector('.proyectos-guardados');
      if (!cont) return;

      const empty = cont.querySelector('.empty-state');
      if (empty) empty.remove();

      if (document.getElementById('guardado-' + id)) return;

      const div = document.createElement('div');
      div.className = 'proyecto-guardado';
      div.id = 'guardado-' + id;
      div.innerHTML = `
        <span>${escapeHtml(titulo || 'Proyecto')}</span>
        <div class="acciones-guardado">
          <a href="ver_proyecto_estudiante.php?id=${id}" class="btn-ver" title="Ver proyecto">
            <ion-icon name="eye"></ion-icon>
          </a>
          <button type="button" class="btn-eliminar" title="Eliminar de guardados" onclick="eliminarProyectoGuardadoEst(${id})">
            <ion-icon name="trash"></ion-icon>
          </button>
        </div>`;
      cont.appendChild(div);
    }

    function removeGuardadoListEst(id){
      const el = document.getElementById('guardado-' + id);
      if (el) el.remove();

      const cont = document.querySelector('.proyectos-guardados');
      if (cont && cont.querySelectorAll('.proyecto-guardado').length === 0) {
        const empty = document.createElement('div');
        empty.className = 'empty-state';
        empty.innerHTML = `
          <ion-icon name="bookmark-outline"></ion-icon>
          <p>No tienes proyectos guardados.</p>
          <p>Haz clic en el icono de marcador para guardar proyectos interesantes.</p>`;
        cont.appendChild(empty);
      }
    }

    // EXponer en window para usar en los onclick inline
    window.toggleGuardarProyectoEst = async (id, btn) => {
      const activo = btn.classList.contains('activo');
      const accion = activo ? 'eliminar_proyecto' : 'guardar_proyecto';

      try {
        const data = await postAjaxEst({ accion, idproyecto: id });
        // Actualiza botón
        btn.classList.toggle('activo', !activo);
        btn.title = !activo ? 'Eliminar de guardados' : 'Guardar proyecto';

        // Actualiza lista lateral
        if (!activo) addGuardadoListEst(id, data.titulo);
        else removeGuardadoListEst(id);
      } catch (e) {
        alert('No se pudo completar la acción: ' + e.message);
      }
    };

    window.eliminarProyectoGuardadoEst = async (id) => {
      try {
        await postAjaxEst({ accion: 'eliminar_proyecto', idproyecto: id });
        // Quita de la lista
        removeGuardadoListEst(id);
        // Y desactiva el botón en la tarjeta (si está)
        const cardBtn = document.querySelector(`.card.proyecto[data-id="${id}"] .btn-guardar`);
        if (cardBtn) {
          cardBtn.classList.remove('activo');
          cardBtn.title = 'Guardar proyecto';
        }
      } catch (e) {
        alert('No se pudo eliminar: ' + e.message);
      }
    };
  });
  </script>
</body>
</html>


