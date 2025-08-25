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

$user     = $auth->getCurrentUser();
$loggedIn = $auth->isLogged();
$userType = $auth->getUserType();
$miIdest  = (int)($user['idest'] ?? 0);
$estudiante = Estudiante::findById($miIdest);

//Handler AJAX (guardar calificación/comentario)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // 2) Upsert en proyecto_rating_estudiante
    $stmt = $conn->prepare("
      INSERT INTO proyecto_rating_estudiante (idproyecto, idest, estrellas, comentario)
      VALUES (?,?,?,?)
      ON DUPLICATE KEY UPDATE
        estrellas=VALUES(estrellas),
        comentario=VALUES(comentario),
        created_at=NOW()
    ");
    // idproyecto (i), idest (i), estrellas (i), comentario (s) => "iiis"
    $stmt->bind_param("iiis", $idproy, $miIdest, $estrellas, $coment);
    $stmt->execute();
    $stmt->close();

    // 3) Resumen global (promedio / total_votos)
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

// ===== GET: cargar proyectos + mis calificaciones =====
try {
  // Proyectos públicos
  $proyectos = [];
  $sql = "SELECT id, titulo, descripcion_previa, repo_url, video_url, archivo_zip,
                 created_at, idest, estudiante,
                 COALESCE(promedio,0) AS promedio,
                 COALESCE(total_votos,0) AS total_votos
          FROM v_proyectos_publicos
          ORDER BY COALESCE(promedio,0) DESC, created_at DESC";
  if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) { $proyectos[] = $row; }
    $res->free();
  } else {
    throw new Exception($conn->error);
  }

  // Mis ratings previos
  $misRatings = [];
  $stmt = $conn->prepare("SELECT idproyecto, estrellas, comentario FROM proyecto_rating_estudiante WHERE idest=?");
  $stmt->bind_param("i", $miIdest);
  $stmt->execute();
  $rres = $stmt->get_result();
  while ($r = $rres->fetch_assoc()) {
    $misRatings[(int)$r['idproyecto']] = [
      'estrellas'  => (int)$r['estrellas'],
      'comentario' => (string)$r['comentario']
    ];
  }
  $stmt->close();

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
  <link rel="stylesheet" href="../assets/styleEstudianteIndex.css">
  <link rel="stylesheet" href="../assets/styleVistaPro.css">
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

  <!-- ===== HEADER con buscador ===== -->
  <header>
    <div class="header-title">
      <h2>Descubre Ideas</h2>
    </div>
    <div class="search-container">
      <ion-icon name="search-outline"></ion-icon>
      <input type="text" id="searchInput" placeholder="Buscar proyectos...">
    </div>
  </header>

  <!-- ===== CONTENIDO: grid de proyectos ===== -->
  <main class="wrap">
    <section class="grid" id="gridProyectos">
      <?php foreach ($proyectos as $p):
        $pid    = (int)$p['id'];
        $esMio  = ((int)$p['idest'] === $miIdest);
        $prev   = $misRatings[$pid] ?? null;
        $miEst  = $prev['estrellas'] ?? 0;
        $miCom  = $prev['comentario'] ?? '';
      ?>
      <article class="card proyecto"
               data-id="<?= $pid ?>"
               data-title="<?= htmlspecialchars($p['titulo'], ENT_QUOTES) ?>"
               data-author="<?= htmlspecialchars($p['estudiante'], ENT_QUOTES) ?>">
        <header class="card__header">
          <h3 class="card__title"><?= htmlspecialchars($p['titulo']) ?></h3>
          <div class="card__author">Por <strong><?= htmlspecialchars($p['estudiante']) ?></strong></div>
        </header>

        <p class="card__desc"><?= nl2br(htmlspecialchars($p['descripcion_previa'] ?? '')) ?></p>

        <div class="card__row">
          <span class="badge avg" id="avg-<?= $pid ?>">
            ⭐ <?= number_format((float)$p['promedio'], 2) ?> (<?= (int)$p['total_votos'] ?>)
          </span>

          <div class="stars" role="radiogroup" aria-label="Tu calificación">
            <?php for ($i=1; $i<=5; $i++): ?>
              <?php $active = $miEst >= $i ? ' active' : ''; ?>
              <button type="button"
                      class="star<?= $active ?>"
                      data-val="<?= $i ?>"
                      aria-checked="<?= $miEst === $i ? 'true':'false' ?>"
                      aria-label="<?= $i ?> estrella<?= $i>1?'s':'' ?>"
                      <?= $esMio ? 'disabled' : '' ?>
              >★</button>
            <?php endfor; ?>
          </div>
        </div>

        <div class="card__form">
          <textarea class="comment"
                    maxlength="300"
                    placeholder="<?= $esMio ? 'No puedes comentar tu propio proyecto' : 'Escribe un comentario (máx. 300)'; ?>"
                    <?= $esMio ? 'disabled' : '' ?>
          ><?= htmlspecialchars($miCom) ?></textarea>

          <button type="button" class="btn-save" <?= $esMio ? 'disabled' : '' ?>>
            Guardar
          </button>
          <?php if ($esMio): ?>
            <div class="msg info">No puedes calificar/comentar tu propio proyecto.</div>
          <?php else: ?>
            <div class="msg" aria-live="polite"></div>
          <?php endif; ?>
        </div>

        <footer class="card__links">
          <?php if ($p['repo_url']): ?>
            <a href="<?= htmlspecialchars($p['repo_url']) ?>" target="_blank" rel="noopener">Repo</a>
          <?php endif; ?>
          <?php if ($p['video_url']): ?>
            <a href="<?= htmlspecialchars($p['video_url']) ?>" target="_blank" rel="noopener">Video</a>
          <?php endif; ?>
          <?php if ($p['archivo_zip']): ?>
            <a href="<?= htmlspecialchars($p['archivo_zip']) ?>" target="_blank" rel="noopener">ZIP</a>
          <?php endif; ?>
        </footer>
      </article>
      <?php endforeach; ?>
    </section>
  </main>

  <footer>
    <p>&copy; Error 404 | Todos los derechos reservados.</p>
  </footer>

  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

  <script>
    // ====== Filtro de búsqueda por título/autor/descripción ======
    const searchInput = document.getElementById('searchInput');
    const cards = Array.from(document.querySelectorAll('.card.proyecto'));
    searchInput?.addEventListener('input', () => {
      const q = searchInput.value.trim().toLowerCase();
      cards.forEach(card => {
        const title = (card.dataset.title || '').toLowerCase();
        const author = (card.dataset.author || '').toLowerCase();
        const desc = (card.querySelector('.card__desc')?.textContent || '').toLowerCase();
        const show = !q || title.includes(q) || author.includes(q) || desc.includes(q);
        card.style.display = show ? '' : 'none';
      });
    });

    // ====== Interacción de estrellas + guardado ======
    document.querySelectorAll('.card').forEach(card => {
      const pid    = parseInt(card.dataset.id);
      const stars  = [...card.querySelectorAll('.star')];
      const txt    = card.querySelector('.comment');
      const btn    = card.querySelector('.btn-save');
      const msg    = card.querySelector('.msg');
      const badge  = document.getElementById('avg-' + pid);

      if (!btn) return; // (tarjeta propia deshabilitada)

      let current = stars.findIndex(s => s.classList.contains('active')) + 1;

      const paint = (n) => {
        stars.forEach((s, i) => {
          s.classList.toggle('active', i < n);
          s.setAttribute('aria-checked', (i + 1) === n ? 'true' : 'false');
        });
      };

      stars.forEach(s => {
        s.addEventListener('mouseenter', () => paint(parseInt(s.dataset.val)));
        s.addEventListener('mouseleave', () => paint(current));
        s.addEventListener('click', () => {
          current = parseInt(s.dataset.val);
          paint(current);
        });
      });

      btn.addEventListener('click', async () => {
        msg.textContent = '';
        if (current < 1 || current > 5) {
          msg.textContent = 'Selecciona de 1 a 5 estrellas.';
          msg.className = 'msg err';
          return;
        }
        const payload = {
          mode: 'save',
          idproyecto: pid,
          estrellas: current,
          comentario: (txt?.value || '').trim().slice(0, 300)
        };
        btn.disabled = true;
        try {
          const res = await fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const json = await res.json();
          if (!json.ok) throw new Error(json.error || 'Error');

          // Refrescar promedio y votos
          badge.textContent = `⭐ ${Number(json.promedio).toFixed(2)} (${parseInt(json.total_votos)})`;
          msg.textContent = '¡Guardado!';
          msg.className = 'msg ok';
        } catch (e) {
          msg.textContent = e.message || 'Error al guardar';
          msg.className = 'msg err';
        } finally {
          btn.disabled = false;
        }
      });
    });
  </script>
</body>
</html>
