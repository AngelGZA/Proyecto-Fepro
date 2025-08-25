<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Opcional pero útil: que mysqli lance excepciones si hay error SQL
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Models\DB;

$auth = new AuthController();
if (!$auth->isLogged() || $auth->getUserType() !== 'estudiante') {
    header("Location: ../views/formulario.php");
    exit;
}

$user = $auth->getCurrentUser();
$idest = (int)($user['idest'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: estudiante.php?err=MetodoNoPermitido");
    exit;
}

$idproyecto = (int)($_POST['idproyecto'] ?? 0);
if ($idproyecto <= 0) {
    header("Location: estudiante.php?err=ProyectoInvalido");
    exit;
}

$db = new DB();
$conn = $db->getConnection();

// 1) Validar dueño y obtener ruta del zip
$sql = "SELECT id, idest, archivo_zip FROM proyectos WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idproyecto);
$stmt->execute();
$result = $stmt->get_result();
$proy = $result->fetch_assoc();
$stmt->close();

if (!$proy) {
    header("Location: estudiante.php?err=ProyectoNoExiste");
    exit;
}
if ((int)$proy['idest'] !== $idest) {
    header("Location: estudiante.php?err=NoAutorizado");
    exit;
}

// 2) Borrar proyecto
$sqlDel = "DELETE FROM proyectos WHERE id = ? AND idest = ?";
$stmt = $conn->prepare($sqlDel);
$stmt->bind_param("ii", $idproyecto, $idest);
$ok = $stmt->execute();
$rows = $stmt->affected_rows; 
$stmt->close();

if (!$ok) {
    header("Location: estudiante.php?err=ErrorSQL");
    exit;
}
if ($rows === 0) {
    // Llegó a ejecutar, pero no coincidió WHERE (id/ dueñ@)
    header("Location: estudiante.php?err=NoSeElimino");
    exit;
}

// 3) Intentar borrar el archivo físico si existía
if (!empty($proy['archivo_zip'])) {
    $publicPath = $proy['archivo_zip']; 


    $baseUploads = realpath(__DIR__ . '/../uploads'); // carpeta real de uploads
    $fileName = basename($publicPath);                 // "xxx.zip"
    $absPath = $baseUploads ? ($baseUploads . DIRECTORY_SEPARATOR . $fileName) : null;

    if ($absPath && is_file($absPath)) {
        @unlink($absPath);
    }
}

// 4) Listo
header("Location: estudiante.php?ok=ProyectoEliminado");
exit;

