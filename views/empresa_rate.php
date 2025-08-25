<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
// Evita cache en respuestas POST/JSON
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../src/models/DB.php';
use App\Models\DB;

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'empresa') {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit;
}

$idemp = (int)$_SESSION['user_id'];

try {
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $db = (new DB())->getConnection();
  $db->set_charset('utf8mb4');

  // Lee JSON o application/x-www-form-urlencoded
  $body = json_decode(file_get_contents('php://input'), true);
  if (!is_array($body)) $body = $_POST;

  $mode   = $body['mode'] ?? '';
  $idproy = isset($body['idproyecto']) ? (int)$body['idproyecto'] : 0;

  if ($idproy <= 0) {
    throw new Exception('idproyecto inválido');
  }

  // -------------------------
  // MODE: recent -> lista JSON
  // -------------------------
  if ($mode === 'recent') {
    $comentarios = [];
    $stmt = $db->prepare("
      SELECT comentario,
             COALESCE(updated_at, created_at) AS fecha,
             'estudiante' AS tipo
      FROM proyecto_rating_estudiante
      WHERE idproyecto = ? AND comentario IS NOT NULL AND comentario <> ''

      UNION ALL

      SELECT comentario,
             COALESCE(updated_at, created_at) AS fecha,
             'empresa' AS tipo
      FROM proyecto_rating_empresa
      WHERE idproyecto = ? AND comentario IS NOT NULL AND comentario <> ''

      ORDER BY fecha DESC
      LIMIT 10
    ");
    $stmt->bind_param('ii', $idproy, $idproy);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      // Formatea fecha en ISO 8601 para JS
      $row['fecha'] = date('c', strtotime($row['fecha']));
      $comentarios[] = $row;
    }
    $stmt->close();

    echo json_encode(['ok'=>true, 'items'=>$comentarios]);
    exit;
  }

  // -------------------------
  // MODE: save -> guarda rating
  // -------------------------
  if ($mode !== 'save') {
    throw new Exception('Parámetros inválidos (mode)');
  }

  $estrellas = isset($body['estrellas']) ? (int)$body['estrellas'] : 0;
  $coment    = isset($body['comentario']) ? trim((string)$body['comentario']) : '';

  if ($estrellas < 1 || $estrellas > 5) {
    throw new Exception('Valor de estrellas inválido');
  }

  // Opcional: acota el tamaño del comentario para evitar entradas enormes
  if (mb_strlen($coment) > 3000) {
    $coment = mb_substr($coment, 0, 3000);
  }

  // UPSERT por (idemp, idproyecto)
  $stmt = $db->prepare("
    INSERT INTO proyecto_rating_empresa (idemp, idproyecto, estrellas, comentario, created_at, updated_at)
    VALUES (?, ?, ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
      estrellas = VALUES(estrellas),
      comentario = VALUES(comentario),
      updated_at = NOW()
  ");
  $stmt->bind_param('iiis', $idemp, $idproy, $estrellas, $coment);
  $stmt->execute();
  $stmt->close();

  // Obtén resumen (promedio/total)
  $stmt = $db->prepare("SELECT promedio, total_votos FROM v_proyecto_rating_resumen WHERE idproyecto=?");
  $stmt->bind_param('i', $idproy);
  $stmt->execute();
  $resumen = $stmt->get_result()->fetch_assoc() ?: ['promedio'=>0, 'total_votos'=>0];
  $stmt->close();

  // Obtén la fila actualizada (comentario y updated_at) para insertar en caliente
  $stmt = $db->prepare("
    SELECT comentario, COALESCE(updated_at, created_at) AS fecha
    FROM proyecto_rating_empresa
    WHERE idemp = ? AND idproyecto = ?
    LIMIT 1
  ");
  $stmt->bind_param('ii', $idemp, $idproy);
  $stmt->execute();
  $fila = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  echo json_encode([
    'ok' => true,
    'promedio' => (float)($resumen['promedio'] ?? 0),
    'total_votos' => (int)($resumen['total_votos'] ?? 0),
    // Para UI (insertar en caliente)
    'comentario' => (string)($fila['comentario'] ?? $coment),
    'fecha' => isset($fila['fecha']) ? date('c', strtotime($fila['fecha'])) : date('c'),
    'tipo' => 'empresa'
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
