<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../src/models/DB.php';
use App\Models\DB;

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'empresa') {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit;
}

$idemp = (int)$_SESSION['user_id'];

try {
  $db = (new DB())->getConnection();
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $db->set_charset('utf8mb4');

  $body = json_decode(file_get_contents('php://input'), true);
  if (!is_array($body)) $body = $_POST;

  $mode      = $body['mode'] ?? '';
  $idproy    = isset($body['idproyecto']) ? (int)$body['idproyecto'] : 0;
  $estrellas = isset($body['estrellas']) ? (int)$body['estrellas'] : 0;
  $coment    = isset($body['comentario']) ? trim((string)$body['comentario']) : '';

  if ($mode !== 'save' || $idproy <= 0 || $estrellas < 1 || $estrellas > 5) {
    throw new Exception('Parámetros inválidos');
  }

  // UPSERT por (idemp, idproyecto)
$stmt = $db->prepare("
  INSERT INTO proyecto_rating_empresa (idemp, idproyecto, estrellas, comentario)
  VALUES (?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    estrellas = VALUES(estrellas),
    comentario = VALUES(comentario)
");
$stmt->bind_param('iiis', $idemp, $idproy, $estrellas, $coment); // <-- orden correcto
$stmt->execute();


  // Traer promedio/total actualizados desde la vista
  $stmt = $db->prepare("SELECT promedio, total_votos FROM v_proyecto_rating_resumen WHERE idproyecto=?");
  $stmt->bind_param('i', $idproy);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();

  echo json_encode([
    'ok' => true,
    'promedio' => (float)($res['promedio'] ?? 0),
    'total_votos' => (int)($res['total_votos'] ?? 0)
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
