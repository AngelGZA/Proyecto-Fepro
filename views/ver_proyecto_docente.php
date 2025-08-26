<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'docente') {
  header("Location: formulario.php");
  exit;
}

require_once __DIR__ . '/../src/models/DB.php';
use App\Models\DB;

$db   = (new DB())->getConnection();
$idmae = (int)$_SESSION['user_id'];
$id    = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: docente.php"); exit; }

/* Proyecto (desde la vista pública) */
$stmt = $db->prepare("
  SELECT
    v.id, v.titulo, v.descripcion_previa,
    v.repo_url, v.video_url, v.archivo_zip,
    v.created_at, v.estudiante,
    COALESCE(v.promedio,0) AS promedio,
    COALESCE(v.total_votos,0) AS total_votos
  FROM v_proyectos_publicos v
  WHERE v.id = ?
  LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$proy = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$proy) { header("Location: docente.php"); exit; }

/* ¿Favorito de este docente? */
$stmt = $db->prepare("SELECT 1 FROM maestro_proyecto_favorito WHERE idmae=? AND idproyecto=? LIMIT 1");
$stmt->bind_param("ii", $idmae, $id);
$stmt->execute();
$esGuardado = (bool)$stmt->get_result()->num_rows;
$stmt->close();

/* Mi rating (docente) */
$miRating = ['estrellas'=>0, 'comentario'=>''];
$stmt = $db->prepare("SELECT estrellas, comentario FROM proyecto_rating_maestro WHERE idmae=? AND idproyecto=?");
$stmt->bind_param("ii", $idmae, $id);
$stmt->execute();
if ($r = $stmt->get_result()->fetch_assoc()) {
  $miRating['estrellas']  = (int)$r['estrellas'];
  $miRating['comentario'] = (string)$r['comentario'];
}
$stmt->close();

/* Comentarios recientes: estudiantes + docentes */
$comentarios = [];
$cstmt = $db->prepare("
  SELECT comentario, COALESCE(updated_at, created_at) AS fecha, 'estudiante' AS tipo
    FROM proyecto_rating_estudiante
   WHERE idproyecto=? AND comentario IS NOT NULL AND comentario <> ''
  UNION ALL
  SELECT comentario, COALESCE(updated_at, created_at) AS fecha, 'docente' AS tipo
    FROM proyecto_rating_maestro
   WHERE idproyecto=? AND comentario IS NOT NULL AND comentario <> ''
  ORDER BY fecha DESC
  LIMIT 10
");
$cstmt->bind_param("ii", $id, $id);
$cstmt->execute();
$cres = $cstmt->get_result();
while ($row = $cres->fetch_assoc()) { $comentarios[] = $row; }
$cstmt->close();

/* Helper iframe */
function iframeSrc($url) {
  if (!$url) return null;
  if (str_contains($url, 'youtube.com/embed/')) return $url;
  if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_\-]{6,})~', $url, $m)) {
    return 'https://www.youtube.com/embed/' . $m[1];
  }
  if (preg_match('~vimeo\.com/(\d+)~', $url, $m)) {
    return 'https://player.vimeo.com/video/' . $m[1];
  }
  if (preg_match('~^https?://~i', $url)) return $url;
  return null;
}
$iframe = iframeSrc($proy['video_url'] ?? '');
$isMp4  = !empty($proy['video_url']) && preg_match('~\.(mp4|webm|ogg)(\?.*)?$~i', $proy['video_url']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Proyecto: <?= htmlspecialchars($proy['titulo']) ?> | CodEval</title>
  <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
  <link rel="stylesheet" href="../assets/styleEmpresa.css">
  <link rel="stylesheet" href="../assets/styleVistaPro1.css">
  <style>
    .wrap { margin-left: 80px; max-width: calc(100vw - 80px); padding: 30px 20px; }
    .detalle-grid { display:grid; grid-template-columns:1.6fr 1fr; gap:28px; }
    @media (max-width:1100px){ .detalle-grid { grid-template-columns:1fr; } }
    .card--detalle { padding:28px; }
    .btn-guardar { position:absolute; top:12px; right:12px; background:none; border:none; cursor:pointer; font-size:1.6rem; color:#ddd; transition:.2s; }
    .btn-guardar:hover { transform: scale(1.15); color: var(--card-warning-orange);}
    .btn-guardar.activo { color: var(--card-warning-orange); }
    .media { border:1px solid var(--card-border-light); border-radius:12px; overflow:hidden; background:#000; aspect-ratio:16/9; width:100%;}
    .media iframe, .media video { width:100%; height:100%; display:block; }
    .meta { display:grid; gap:10px; margin-top:16px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
    .meta .badge { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:10px; background:var(--color-light); border:1px solid var(--card-border-light); font-weight:500; }
    .links { display:flex; gap:12px; flex-wrap:wrap; margin-top:12px; }
    .links a { color:var(--color-boton); text-decoration:none; font-weight:500; padding:8px 14px; border:2px solid var(--color-boton); border-radius:20px; transition:.2s; display:inline-flex; align-items:center; gap:8px; }
    .links a:hover { background:var(--color-boton); color:#fff; transform:translateY(-2px); }
    .stars { display:flex; gap:4px; }
    .star { background:none; border:none; font-size:1.8rem; color:#ddd; cursor:pointer; line-height:1; }
    .star:not(:disabled):hover, .star.active { color: var(--card-warning-orange); transform: scale(1.05); }
    .comment { width:100%; min-height:100px; padding:12px; border:2px solid var(--card-border-light); border-radius:8px; font-size:.95rem; resize:vertical; margin-top:10px; }
    .btn { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:10px; font-weight:600; text-decoration:none; border:0; transition:transform .2s ease, box-shadow .2s ease, background .2s ease, color .2s ease; box-shadow: 0 5px 14px var(--card-shadow); }
    .btn-primary { background:linear-gradient(135deg, var(--color-boton), var(--card-light-blue)); color:#fff; }
    .btn-primary:hover{ transform:translateY(-1px); box-shadow:0 10px 22px var(--card-shadow-hover); }
    .btn-secondary{ background:#fff; color:var(--color-boton); border:2px solid var(--color-boton); }
    .btn-secondary:hover{ background:var(--color-boton); color:#fff; transform:translateY(-1px); box-shadow:0 10px 22px var(--card-shadow-hover); }
    .msg { margin-top:10px; padding:10px; border-radius:6px; font-size:.9rem; }
    .msg.ok{ background:#d4edda; color:#2e7d32; border:1px solid #c3e6cb; }
    .msg.err{ background:#f8d7da; color:#c62828; border:1px solid #f5c6cb; }
    .comments-list { margin-top:14px; display:grid; gap:10px; }
    .comments-list .item { background:var(--color-light); border:1px solid var(--card-border-light); border-radius:10px; padding:10px 12px; }
    .comments-list .who { font-weight:600; font-size:.9rem; color:#374151; }
    .comments-list .txt { margin-top:4px; color:#34495e; line-height:1.6; }
    .rate-actions{ display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-top:10px; }
  </style>
</head>
<body>

  <!-- Sidebar (igual que en empresa, cambia enlaces si quieres) -->
  <div class="barra-lateral">
    <div>
      <div class="nombre-pagina">
        <div class="image"><img src="../multimedia/logo_pagina.png" alt="Logo"></div>
        <span style="color:#0097b2;">CodEval</span>
      </div>
    </div>
    <nav class="navegacion">
      <ul class="menu-superior">
        <li><a href="../index.php"><ion-icon name="home-outline"></ion-icon><span>Inicio</span></a></li>
        <li><a href="docente.php" class="active"><ion-icon name="create-outline"></ion-icon><span>Docentes</span></a></li>
        <li><a href="../view/graficos.php"><ion-icon name="podium"></ion-icon><span>Gráficos</span></a></li>
      </ul>
      <ul class="menu-inferior">
        <li><a href="docente_perfil.php"><ion-icon name="school-outline"></ion-icon><span>Mi Perfil</span></a></li>
        <li><a href="../public/logout.php"><ion-icon name="log-out-outline"></ion-icon><span>Cerrar Sesión</span></a></li>
      </ul>
    </nav>
  </div>

  <!-- Header superior con botón de favorito -->
  <header>
    <div class="card__header">
      <h3 class="card__title"><?= htmlspecialchars($proy['titulo']) ?></h3>
      <div class="card__author">Por <strong><?= htmlspecialchars($proy['estudiante']) ?></strong></div>
      <div class="card__actions">
        <button id="btnFavorito" class="btn-guardar <?= $esGuardado ? 'activo':'' ?>"
                title="<?= $esGuardado ? 'Eliminar de guardados' : 'Guardar proyecto' ?>">
          <ion-icon name="bookmark"></ion-icon>
        </button>
      </div>
    </div>
  </header>

  <main class="wrap">
    <div class="detalle-grid">
      <!-- Columna principal -->
      <article class="card card--detalle">
        <div class="card__header">
          <h3 class="card__title"><?= htmlspecialchars($proy['titulo']) ?></h3>
          <div class="card__author">Por <strong><?= htmlspecialchars($proy['estudiante']) ?></strong></div>
        </div>

        <?php if (!empty($proy['video_url'])): ?>
          <div class="media" style="margin:10px 0 16px">
            <?php if ($isMp4): ?>
              <video controls preload="metadata">
                <source src="<?= htmlspecialchars($proy['video_url']) ?>">
                Tu navegador no soporta video HTML5.
              </video>
            <?php else: ?>
              <iframe src="<?= htmlspecialchars($iframe ?? $proy['video_url']) ?>" frameborder="0" allowfullscreen
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"></iframe>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <p class="card__desc"><?= nl2br(htmlspecialchars($proy['descripcion_previa'] ?? 'Sin descripción')) ?></p>

        <div class="meta">
          <span style="color:#0F130C" class="badge avg">⭐ <?= number_format((float)$proy['promedio'], 2) ?> (<?= (int)$proy['total_votos'] ?>)</span>
          <span class="badge"><ion-icon name="calendar"></ion-icon> Creado: <?= date('d/m/Y', strtotime($proy['created_at'] ?? 'now')) ?></span>
        </div>

        <div class="links">
          <?php if (!empty($proy['repo_url'])): ?>
            <a href="<?= htmlspecialchars($proy['repo_url']) ?>" target="_blank" rel="noopener"><ion-icon name="logo-github"></ion-icon> Repo</a>
          <?php endif; ?>
          <?php if (!empty($proy['archivo_zip'])): ?>
            <a href="<?= htmlspecialchars($proy['archivo_zip']) ?>" target="_blank" rel="noopener"><ion-icon name="download-outline"></ion-icon> ZIP</a>
          <?php endif; ?>
        </div>

        <!-- Tu valoración (docente) -->
        <section style="margin-top:18px">
          <h4 style="margin-bottom:8px">Tu valoración</h4>

          <form class="rate-form" onsubmit="return false">
            <div class="stars" id="stars" aria-label="Calificar">
              <?php for ($i=1; $i<=5; $i++): ?>
                <button type="button" class="star <?= ($miRating['estrellas'] >= $i) ? 'active' : '' ?>" data-value="<?= $i ?>">★</button>
              <?php endfor; ?>
            </div>

            <textarea id="comentario" class="comment" placeholder="Escribe un comentario para el equipo..." rows="4"><?= htmlspecialchars($miRating['comentario'] ?? '') ?></textarea>

            <div class="rate-actions">
              <button id="btnGuardarRating" type="button" class="btn btn-primary">
                <ion-icon name="save-outline"></ion-icon> Guardar valoración
              </button>
              <a href="docente.php" class="btn btn-secondary">
                <ion-icon name="arrow-back"></ion-icon> Regresar
              </a>
              <div id="msg" class="msg" hidden></div>
            </div>
          </form>
        </section>
      </article>

      <!-- Columna lateral: comentarios recientes -->
      <aside class="card card--detalle">
        <header class="card__header"><h3 class="card__title">Comentarios recientes</h3></header>
        <?php if ($comentarios): ?>
          <div class="comments-list">
            <?php foreach ($comentarios as $c): ?>
              <div class="item">
                <div class="who">
                  <?= $c['tipo']==='docente' ? 'Docente' : 'Estudiante' ?>
                  · <?= date('d/m/Y H:i', strtotime($c['fecha'])) ?>
                </div>
                <div class="txt"><?= nl2br(htmlspecialchars($c['comentario'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <ion-icon name="chatbubble-ellipses-outline"></ion-icon>
            <p>No hay comentarios aún.</p>
          </div>
        <?php endif; ?>
      </aside>
    </div>
  </main>

  <footer>
    <p>&copy; CodEval | Todos los derechos reservados.</p>
    <p>
      Síguenos:
      <a href="https://www.facebook.com/profile.php?id=61569699028545&mibextid=ZbWKwL" target="_blank"><ion-icon name="logo-facebook"></ion-icon></a>
      <a href="https://www.instagram.com/error404_ods7?igsh=MTU4dHJrajBybWFxeQ==" target="_blank"><ion-icon name="logo-instagram"></ion-icon></a>
      <a href="https://youtube.com/@gabrielcorona2000?si=As0KyE0q-QfsmlW0" target="_blank"><ion-icon name="logo-youtube"></ion-icon></a>
      <a href="https://x.com/Error_404_ODS7?t=YAwltMat_BqnCXRHr-tIYQ&s=08" target="_blank"><ion-icon name="logo-twitter"></ion-icon></a>
    </p>
  </footer>

  <script src="https://code.jquery.com/jquery-3.3.1.min.js"
          integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

  <script>
    // Toggle estrellas
    document.getElementById('stars').addEventListener('click', (e) => {
      const btn = e.target.closest('.star'); if (!btn) return;
      const val = parseInt(btn.dataset.value,10);
      document.querySelectorAll('#stars .star').forEach((s,idx)=>s.classList.toggle('active', idx < val));
    });

    // Guardar rating + comentario (docente)
    document.getElementById('btnGuardarRating').addEventListener('click', async () => {
      const estrellas = [...document.querySelectorAll('#stars .star')].filter(s=>s.classList.contains('active')).length;
      const comentario = document.getElementById('comentario').value.trim();
      const msg = document.getElementById('msg');
      const btn = document.getElementById('btnGuardarRating');

      if (estrellas < 1) {
        msg.hidden = false; msg.className = 'msg err'; msg.textContent = 'Selecciona al menos 1 estrella.'; return;
      }
      btn.disabled = true;
      try {
        const body = new URLSearchParams({ mode:'save', idproyecto:'<?= $id ?>', estrellas, comentario });
        const res  = await fetch('../public/docente_rate.php', {
          method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body
        });
        const json = await res.json().catch(()=>null);
        if (!res.ok || !json || json.ok !== true) throw new Error(json?.error || 'No se pudo guardar.');

        msg.hidden = false; msg.className = 'msg ok'; msg.textContent = '¡Valoración guardada!';
        if (json.promedio !== undefined) {
          const badge = document.querySelector('.badge.avg');
          if (badge) badge.textContent = `⭐ ${Number(json.promedio).toFixed(2)} (${json.total_votos ?? ''})`;
        }

        // Insertar comentario en caliente
        const aside = document.querySelector('aside.card.card--detalle');
        let list = document.querySelector('.comments-list');
        if (!list) {
          const empty = document.querySelector('.empty-state'); if (empty) empty.remove();
          list = document.createElement('div'); list.className = 'comments-list'; (aside || document.body).appendChild(list);
        }
        const item = document.createElement('div'); item.className = 'item';
        const fechaTxt = new Date(json.fecha || Date.now()).toLocaleString('es-MX', { hour12:false });
        item.innerHTML = `<div class="who">Docente · ${fechaTxt}</div><div class="txt"></div>`;
        item.querySelector('.txt').textContent = (json.comentario ?? comentario) || '';
        list.prepend(item);

      } catch (err) {
        msg.hidden = false; msg.className = 'msg err'; msg.textContent = err.message || 'Error desconocido';
      } finally {
        btn.disabled = false;
      }
    });

    // Guardar / Quitar favorito (docente)
    const favBtn = document.getElementById('btnFavorito');
    if (favBtn){
      favBtn.addEventListener('click', async () => {
        const activar = !favBtn.classList.contains('activo');
        try {
          const body = new URLSearchParams({ idproyecto:'<?= $id ?>', action: activar ? 'add':'del' });
          const res  = await fetch('../public/maestro_fav.php', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body
          });
          if (!res.ok) throw new Error('No se pudo actualizar favorito');
          favBtn.classList.toggle('activo', activar);
          favBtn.title = activar ? 'Eliminar de guardados' : 'Guardar proyecto';
        } catch (err) {
          alert(err.message || 'Error al actualizar favorito');
        }
      });
    }
  </script>
</body>
</html>
