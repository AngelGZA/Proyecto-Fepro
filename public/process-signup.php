<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Estudiante;
use App\Models\Empresa;
use App\Models\Maestro;

session_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo "Método no permitido.";
        exit;
    }

    // Campos comunes
    $tipo     = $_POST['tipo_usuario'] ?? null;
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validación básica
    if (!$tipo || $name === '' || $email === '' || $password === '') {
        http_response_code(400);
        echo "Faltan campos requeridos.";
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Email no válido.";
        exit;
    }

    // Directorio de uploads (CV del estudiante)
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            http_response_code(500);
            echo "No se pudo crear el directorio de uploads.";
            exit;
        }
    }

    // Normalizadores simples (opcionales; los modelos también pueden normalizar)
    $normTel = static function (?string $t): string {
        return preg_replace('/\D+/', '', (string)$t);
    };

    switch ($tipo) {
        // ===================== ESTUDIANTE =====================
        case 'estudiante': {
            $telefono    = $normTel($_POST['telefono_estudiante'] ?? '');
            $descripcion = trim($_POST['descripcion_estudiante'] ?? '');
            $cv          = null;

            // Procesar CV (opcional) si viene archivo
            if (isset($_FILES['cv']) && $_FILES['cv']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo "Error al subir el archivo.";
                    exit;
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($_FILES['cv']['tmp_name']);
                if ($mime !== 'application/pdf') {
                    http_response_code(400);
                    echo "El archivo debe ser un PDF válido.";
                    exit;
                }

                $filename = uniqid('cv_', true) . '.pdf';
                $destPath = $uploadDir . $filename;
                if (!move_uploaded_file($_FILES['cv']['tmp_name'], $destPath)) {
                    http_response_code(500);
                    echo "Error al guardar el archivo.";
                    exit;
                }
                // Guarda ruta relativa (como ya hacías)
                $cv = 'uploads/' . $filename;
            }

            // Registrar estudiante (create debe devolver insert_id)
            $id = Estudiante::create([
                'name'        => $name,
                'email'       => $email,
                'password'    => $password,
                'telefono'    => $telefono,
                'descripcion' => $descripcion,
                'cv'          => $cv,
            ]);

            if (!$id) {
                http_response_code(500);
                echo "Error al registrar estudiante.";
                exit;
            }

            // Inicia sesión y redirige a su dashboard
            $_SESSION['user_id']   = $id;
            $_SESSION['user_type'] = 'estudiante';
            header("Location: ../views/estudiante.php", true, 303);
            exit;
        }

        // ======================= EMPRESA ======================
        case 'empresa': {
            $telefono  = $normTel($_POST['telefono_empresa'] ?? '');
            $rfc       = strtoupper(trim($_POST['rfc'] ?? ''));
            $direccion = trim($_POST['direccion_empresa'] ?? '');

            $id = Empresa::create([
                'name'      => $name,
                'email'     => $email,
                'password'  => $password,
                'telefono'  => $telefono,
                'rfc'       => $rfc,
                'direccion' => $direccion,
            ]);

            if (!$id) {
                http_response_code(500);
                echo "Error al registrar empresa.";
                exit;
            }

            $_SESSION['user_id']   = $id;
            $_SESSION['user_type'] = 'empresa';
            header("Location: ../views/empresa.php", true, 303);
            exit;
        }

        // ======================= DOCENTE ======================
        case 'docente': {
            $telefono     = $normTel($_POST['telefono_docente'] ?? '');
            $institucion  = trim($_POST['institucion_docente'] ?? '');
            $especialidad = trim($_POST['especialidad_docente'] ?? '');
            $bio          = trim($_POST['bio_docente'] ?? '');

            $id = Maestro::create([
                'name'        => $name,         // tu modelo lo inserta en columna "nombre"
                'email'       => $email,
                'password'    => $password,
                'telefono'    => $telefono,
                'institucion' => $institucion,  // mapea a "institucion_nombre" en DB
                'especialidad'=> $especialidad,
                'bio'         => $bio,
            ]);

            if (!$id) {
                http_response_code(500);
                echo "Error al registrar docente.";
                exit;
            }

            $_SESSION['user_id']   = $id;
            $_SESSION['user_type'] = 'docente';
            header("Location: ../views/docente.php", true, 303);
            exit;
        }

        default: {
            http_response_code(400);
            echo "Tipo de usuario no válido.";
            exit;
        }
    }
} catch (Throwable $e) {
    // En desarrollo puedes mostrar detalles; en producción, registra y muestra genérico.
    http_response_code(500);
    echo "Error inesperado: " . $e->getMessage();
    exit;
}
