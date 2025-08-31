<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../src/models/DB.php';
use App\Models\DB;

// Solo estudiantes
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'estudiante') {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit;
}

$idest = (int)$_SESSION['user_id'];

try {
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $db = (new DB())->getConnection();
  $db->set_charset('utf8mb4');

  // Acepta JSON o x-www-form-urlencoded
  $body = json_decode(file_get_contents('php://input'), true);
  if (!is_array($body)) $body = $_POST;

  $mode       = $body['mode'] ?? 'save'; // por compatibilidad
  $idproy     = isset($body['idproyecto']) ? (int)$body['idproyecto'] : 0;
  $estrellas  = isset($body['estrellas']) ? (int)$body['estrellas'] : 0;
  $comentario = isset($body['comentario']) ? trim((string)$body['comentario']) : '';

  if ($mode !== 'save') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Parámetros inválidos (mode)']); exit;
  }
  if ($idproy <= 0 || $estrellas < 1 || $estrellas > 5) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Datos inválidos']); exit;
  }
  if ($comentario !== '' && mb_strlen($comentario) > 300) {
    $comentario = mb_substr($comentario, 0, 300);
  }

  // Validar que el proyecto exista y sea PÚBLICO
  $stmt = $db->prepare("SELECT id, idest, visibilidad FROM proyectos WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $idproy);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'Proyecto no encontrado']); exit;
  }
  if ($row['visibilidad'] !== 'publico') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Proyecto no es público']); exit;
  }
  if ((int)$row['idest'] === $idest) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'No puedes calificar tu propio proyecto']); exit;
  }

  // UPSERT (requiere UNIQUE (idest,idproyecto))
  // CREATE UNIQUE INDEX uq_est_proy ON proyecto_rating_estudiante (idest,idproyecto);
  $stmt = $db->prepare("
    INSERT INTO proyecto_rating_estudiante (idproyecto, idest, estrellas, comentario, created_at)
    VALUES (?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
      estrellas=VALUES(estrellas),
      comentario=VALUES(comentario),
      updated_at=CURRENT_TIMESTAMP
  ");
  $stmt->bind_param("iiis", $idproy, $idest, $estrellas, $comentario);
  $stmt->execute();
  $stmt->close();

  // Recalcular resumen SOLO de estudiantes (o usa tu vista agregada si prefieres)
  $stmt = $db->prepare("
    SELECT COALESCE(AVG(estrellas),0) AS promedio, COUNT(*) AS total
    FROM proyecto_rating_estudiante
    WHERE idproyecto = ?
  ");
  $stmt->bind_param("i", $idproy);
  $stmt->execute();
  $sum = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  echo json_encode([
    'ok'          => true,
    'promedio'    => (float)($sum['promedio'] ?? 0),
    'total_votos' => (int)($sum['total'] ?? 0),
    'fecha'       => date('c'),
    'comentario'  => $comentario
  ]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
