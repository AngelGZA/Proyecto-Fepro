<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../src/models/DB.php';
use App\Models\DB;

// Solo docentes
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'docente') {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit;
}

$idmae = (int)$_SESSION['user_id'];

try {
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $db = (new DB())->getConnection();
  $db->set_charset('utf8mb4');

  // JSON body o x-www-form-urlencoded
  $body = json_decode(file_get_contents('php://input'), true);
  if (!is_array($body)) $body = $_POST;

  $mode   = $body['mode'] ?? '';
  $idproy = isset($body['idproyecto']) ? (int)$body['idproyecto'] : 0;

  if ($idproy <= 0) {
    throw new Exception('idproyecto inválido');
  }

  // -------------------------
  // MODE: recent -> lista JSON (comentarios recientes)
  // -------------------------
  if ($mode === 'recent') {
    $comentarios = [];
    $stmt = $db->prepare("
      SELECT comentario,
            COALESCE(updated_at, created_at) AS fecha,
            'docente' AS tipo
      FROM proyecto_rating_maestro
      WHERE idproyecto = ? AND comentario IS NOT NULL AND comentario <> ''

      UNION ALL

      SELECT comentario,
            created_at AS fecha,
            'docente' AS tipo
      FROM proyecto_rating_maestro
      WHERE idproyecto = ? AND comentario IS NOT NULL AND comentario <> ''

      ORDER BY fecha DESC
      LIMIT 10
    ");
    $stmt->bind_param('ii', $idproy, $idproy);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $row['fecha'] = date('c', strtotime($row['fecha'])); // ISO 8601
      $comentarios[] = $row;
    }
    $stmt->close();

    echo json_encode(['ok'=>true, 'items'=>$comentarios]);
    exit;
  }

  // -------------------------
  // MODE: save -> guarda/actualiza rating
  // -------------------------
  if ($mode !== 'save') {
    throw new Exception('Parámetros inválidos (mode)');
  }

  $estrellas = isset($body['estrellas']) ? (int)$body['estrellas'] : 0;
  $coment    = isset($body['comentario']) ? trim((string)$body['comentario']) : '';

  if ($estrellas < 1 || $estrellas > 5) {
    throw new Exception('Valor de estrellas inválido');
  }
  if (mb_strlen($coment) > 3000) {
    $coment = mb_substr($coment, 0, 3000);
  }

  // ¿Ya hay una valoración de este maestro para este proyecto?
  $stmt = $db->prepare("SELECT id FROM proyecto_rating_maestro WHERE idproyecto=? AND idmae=? LIMIT 1");
  $stmt->bind_param('ii', $idproy, $idmae);
  $stmt->execute();
  $existe = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($existe) {
    $idRating = (int)$existe['id'];
    $u = $db->prepare("UPDATE proyecto_rating_maestro SET estrellas=?, comentario=? WHERE id=?");
    $u->bind_param('isi', $estrellas, $coment, $idRating);
    if (!$u->execute()) throw new Exception('No se pudo actualizar la valoración.');
    $u->close();
  } else {
    $i = $db->prepare("INSERT INTO proyecto_rating_maestro (idproyecto, idmae, estrellas, comentario, created_at) VALUES (?, ?, ?, ?, NOW())");
    $i->bind_param('iiis', $idproy, $idmae, $estrellas, $coment);
    if (!$i->execute()) throw new Exception('No se pudo guardar la valoración.');
    $i->close();
  }

  // Resumen (promedio/total)
  $stmt = $db->prepare("SELECT promedio, total_votos FROM v_proyecto_rating_resumen WHERE idproyecto=?");
  $stmt->bind_param('i', $idproy);
  $stmt->execute();
  $resumen = $stmt->get_result()->fetch_assoc() ?: ['promedio'=>0, 'total_votos'=>0];
  $stmt->close();

  // Fila actual del docente (sin updated_at)
  $stmt = $db->prepare("
    SELECT comentario, created_at AS fecha
    FROM proyecto_rating_maestro
    WHERE idmae = ? AND idproyecto = ?
    LIMIT 1
  ");
  $stmt->bind_param('ii', $idmae, $idproy);
  $stmt->execute();
  $fila = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  echo json_encode([
    'ok'          => true,
    'promedio'    => (float)($resumen['promedio'] ?? 0),
    'total_votos' => (int)($resumen['total_votos'] ?? 0),
    'comentario'  => (string)($fila['comentario'] ?? $coment),
    'fecha'       => isset($fila['fecha']) ? date('c', strtotime($fila['fecha'])) : date('c'),
    'tipo'        => 'docente'
  ]);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
