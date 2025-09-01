<?php
session_start();
ini_set('display_errors',1); error_reporting(E_ALL);

require __DIR__ . '/../src/models/DB.php';
use App\Models\DB;

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'docente') {
  header("Location: formulario.php"); exit;
}

$idmae      = (int)$_SESSION['user_id'];
$idproyecto = (int)($_POST['idproyecto'] ?? 0);
$mensaje    = trim($_POST['mensaje'] ?? '');

if ($idproyecto <= 0) { header("Location: docente.php?err=proy"); exit; }

$conn = (new DB())->getConnection();

// El proyecto no debe estar ya asignado
$st = $conn->prepare("SELECT idmae FROM proyectos WHERE id=? LIMIT 1");
$st->bind_param("i", $idproyecto);
$st->execute();
$cur = $st->get_result()->fetch_assoc(); $st->close();
if (!$cur) { header('Location: docente.php?err=noexiste'); exit; }
if (!empty($cur['idmae'])) { header('Location: ver_proyecto_docente.php?id='.$idproyecto.'&err=asignado'); exit; }

// Inserta/actualiza solicitud pendiente
$st = $conn->prepare("
  INSERT INTO proyecto_asociacion_profesor (idproyecto, idmae, estado, mensaje)
  VALUES (?, ?, 'pendiente', ?)
  ON DUPLICATE KEY UPDATE estado='pendiente', mensaje=VALUES(mensaje)
");
$st->bind_param("iis", $idproyecto, $idmae, $mensaje);
$ok = $st->execute(); $st->close();

header('Location: ver_proyecto_docente.php?id='.$idproyecto . ($ok ? '&ok=sol' : '&err=sol'));
exit;
