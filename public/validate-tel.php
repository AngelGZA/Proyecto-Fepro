<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\DB;

header('Content-Type: application/json');

try {
  $raw = $_GET['telefono'] ?? '';
  $tel = preg_replace('/\D+/', '', $raw); // solo dígitos

  // Si viene vacío o longitud inesperada, NO bloquees por defecto:
  if ($tel === '' || strlen($tel) !== 10) {
    echo json_encode(['available' => true]);
    exit;
  }

  $db = new DB();
  $mysqli = $db->getConnection();

  // Unicidad global (estudiante, empresa y maestro). Si la quieres por tabla, filtra según tipo.
  $sql = "
    SELECT 1 FROM (
      SELECT telefono FROM estudiante
      UNION ALL
      SELECT telefono FROM empresa
      UNION ALL
      SELECT telefono FROM maestro
    ) t
    WHERE REPLACE(REPLACE(REPLACE(COALESCE(t.telefono,''), ' ', ''), '-', ''), '(', '') 
          = ?
    LIMIT 1
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('s', $tel);
  $stmt->execute();
  $exists = (bool)$stmt->get_result()->fetch_row();

  echo json_encode(['available' => !$exists]);
} catch (Throwable $e) {
  // En errores de servidor, no bloquees el formulario
  echo json_encode(['available' => true]);
}
?>