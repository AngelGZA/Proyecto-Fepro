<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Estudiante;
use App\Models\Empresa;

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo_usuario'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validación básica de campos requeridos
    if (!$tipo || !$name || !$email || !$password) {
        http_response_code(400);
        echo "Faltan campos requeridos.";
        exit;
    }

    // Crear directorio uploads si no existe
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if ($tipo === 'estudiante') {
        // Procesamiento para estudiantes
        $telefono = $_POST['telefono_estudiante'] ?? '';
        $descripcion = $_POST['descripcion_estudiante'] ?? '';
        $cv = null;

        // Procesar CV si se subió
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['cv']['tmp_name']);

            if ($mime === 'application/pdf') {
                $filename = uniqid() . '.pdf';
                $cvPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['cv']['tmp_name'], $cvPath)) {
                    $cv = 'uploads/' . $filename; // Guardamos la ruta relativa
                } else {
                    http_response_code(500);
                    echo "Error al subir el archivo.";
                    exit;
                }
            } else {
                http_response_code(400);
                echo "El archivo debe ser un PDF válido.";
                exit;
            }
        }

        // Registrar estudiante
        $success = Estudiante::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'telefono' => $telefono,
            'descripcion' => $descripcion,
            'cv' => $cv  // Usamos 'cv' que es el nombre de tu columna
        ]);

        if (!$success) {
            http_response_code(500);
            echo "Error al registrar estudiante.";
            exit;
        }

    } elseif ($tipo === 'empresa') {
        // Procesamiento para empresas
        $telefono = $_POST['telefono_empresa'] ?? '';
        $rfc = $_POST['rfc'] ?? '';
        $direccion = $_POST['direccion_empresa'] ?? '';

        $success = Empresa::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'telefono' => $telefono,
            'rfc' => $rfc,
            'direccion' => $direccion
        ]);

        if (!$success) {
            http_response_code(500);
            echo "Error al registrar empresa.";
            exit;
        }

    } else {
        http_response_code(400);
        echo "Tipo de usuario no válido.";
        exit;
    }

    // Redirección exitosa
    header("Location: index.php");
    exit;
}

http_response_code(405);
echo "Método no permitido.";
?>