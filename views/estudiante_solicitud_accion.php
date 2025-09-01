<?php
session_start();
ini_set('display_errors',1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'estudiante') {
  header("Location: formulario.php");
  exit;
}

require_once __DIR__ . '/../src/models/DB.php';
use App\Models\DB;

$conn  = (new DB())->getConnection();
$idest = (int)$_SESSION['user_id'];

$idsol  = (int)($_POST['idsolicitud'] ?? 0);
$accion = $_POST['accion'] ?? ''; // 'aceptar' | 'rechazar'

if ($idsol <= 0 || !in_array($accion, ['aceptar','rechazar'], true)) {
  header('Location: estudiante.php?err=params');
  exit;
}

// Trae solicitud + proyecto y valida ownership del alumno
$st = $conn->prepare("
  SELECT a.id, a.estado, a.idproyecto, a.idmae,
         p.idest, p.estado AS estado_proy
  FROM proyecto_asociacion_profesor a
  JOIN proyectos p ON p.id = a.idproyecto
  WHERE a.id = ?
  LIMIT 1
");
$st->bind_param("i", $idsol);
$st->execute();
$s = $st->get_result()->fetch_assoc();
$st->close();

if (!$s || (int)$s['idest'] !== $idest) {
  header('Location: estudiante.php?err=forbidden');
  exit;
}
if ($s['estado'] !== 'pendiente') {
  header('Location: estudiante.php?err=no_pending');
  exit;
}

if ($accion === 'rechazar') {
  $u = $conn->prepare("UPDATE proyecto_asociacion_profesor SET estado='rechazada' WHERE id=? AND estado='pendiente'");
  $u->bind_param("i", $idsol);
  $ok = $u->execute();
  $u->close();
  header('Location: estudiante.php?'.($ok ? 'ok=rech' : 'err=upderr'));
  exit;
}

if ($accion === 'aceptar') {
  $conn->begin_transaction();
  try {
    // 1) aceptar la solicitud
    $u1 = $conn->prepare("UPDATE proyecto_asociacion_profesor SET estado='aceptada' WHERE id=? AND estado='pendiente'");
    $u1->bind_param("i", $idsol);
    if (!$u1->execute() || $u1->affected_rows < 1) {
      throw new Exception('No se pudo aceptar');
    }
    $u1->close();

    // 2) asignar docente al proyecto + cambiar estado del proyecto
    $u2 = $conn->prepare("UPDATE proyectos SET idmae=?, estado='aceptado', updated_at=NOW() WHERE id=?");
    $u2->bind_param("ii", $s['idmae'], $s['idproyecto']);
    if (!$u2->execute()) {
      throw new Exception('No se pudo actualizar el proyecto');
    }
    $u2->close();

    // 3) (opcional) rechazar otras solicitudes pendientes del mismo proyecto
    $u3 = $conn->prepare("UPDATE proyecto_asociacion_profesor SET estado='rechazada' WHERE idproyecto=? AND estado='pendiente' AND id<>?");
    $u3->bind_param("ii", $s['idproyecto'], $idsol);
    $u3->execute();
    $u3->close();

    $conn->commit();
    header('Location: estudiante.php?ok=acept');
    exit;

  } catch (Throwable $e) {
    $conn->rollback();
    header('Location: estudiante.php?err=tx');
    exit;
  }
}

header('Location: estudiante.php');
