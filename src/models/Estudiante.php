<?php
namespace App\Models;

class Estudiante {
    /** Conexión mysqli reutilizable */
    protected static function db(): \mysqli {
        static $m = null;
        if ($m) return $m;

        $m = new \mysqli(
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_USERNAME'] ?? 'root',
            $_ENV['DB_PASSWORD'] ?? 'LHvWTenTZ+9S',
            $_ENV['DB_DATABASE'] ?? 'plataforma',
            intval($_ENV['DB_PORT'] ?? 3306)
        );
        if ($m->connect_errno) {
            throw new \RuntimeException("DB error: " . $m->connect_error);
        }
        $m->set_charset('utf8mb4');
        return $m;
    }

    /** Trae un estudiante por id (básico de la tabla) */
    public static function findById(int $idest): ?array {
        $m = self::db();
        $stmt = $m->prepare("SELECT * FROM estudiante WHERE idest=? LIMIT 1");
        $stmt->bind_param("i", $idest);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ?: null;
    }

    /** Trae un estudiante por email (lo usa AuthController::login) */
    public static function findByEmail(string $email): ?array {
        $m = self::db();
        $stmt = $m->prepare("SELECT * FROM estudiante WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ?: null;
    }

    /**
     * Perfil extendido sin depender de la vista ni del JOIN con institucion.
     * Lee el campo libre 'universidad' directamente de la tabla estudiante.
     */
    public static function findPerfilById(int $idest): ?array {
        $m = self::db();
        $stmt = $m->prepare("SELECT * FROM estudiante WHERE idest=? LIMIT 1");
        $stmt->bind_param("i", $idest);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ?: null;
    }

    /** Verifica si existe otro estudiante con el mismo email */
    public static function emailExists(string $email, int $excludeId): bool {
        $m = self::db();
        $stmt = $m->prepare("SELECT 1 FROM estudiante WHERE email=? AND idest<>? LIMIT 1");
        $stmt->bind_param("si", $email, $excludeId);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_row();
    }

    /** Actualiza el perfil con campos extendidos (incluye 'universidad' libre) */
    public static function updateProfileExtended(int $idest, array $data): bool {
        $m = self::db();
        $sql = "UPDATE estudiante
                SET name=?, email=?, matricula=?, telefono=?, facultad=?, carrera=?,
                    github=?, linkedin=?, portfolio=?, universidad=?
                WHERE idest=?";
        $stmt = $m->prepare($sql);
        // 10 strings + 1 int (idest)
        $stmt->bind_param(
            "ssssssssssi",
            $data['name'],
            $data['email'],
            $data['matricula'],
            $data['telefono'],
            $data['facultad'],
            $data['carrera'],
            $data['github'],
            $data['linkedin'],
            $data['portfolio'],
            $data['universidad'],
            $idest
        );
        return $stmt->execute();
    }

    /** Guarda nombre de archivo del Kardex (PDF) */
    public static function updateKardex(int $idest, string $filename): bool {
        $m = self::db();
        $stmt = $m->prepare("UPDATE estudiante SET kardex_pdf=? WHERE idest=?");
        $stmt->bind_param("si", $filename, $idest);
        return $stmt->execute();
    }

    /** Crea un estudiante y devuelve el id insertado o false */
    public static function create(array $data) {
        $m = self::db();

        // Campos esperados (ajusta a tu esquema real)
        $name        = $data['name']        ?? '';
        $email       = $data['email']       ?? '';
        $telefonoRaw = $data['telefono']    ?? '';
        $descripcion = $data['descripcion'] ?? null; // puede ser null
        $cv          = $data['cv']          ?? null; // ruta al pdf o null

        // Normaliza teléfono a solo dígitos (opcional pero recomendable)
        $telefono = preg_replace('/\D+/', '', $telefonoRaw);

        // Hash de contraseña
        $password   = $data['password'] ?? '';
        $passHash   = password_hash($password, PASSWORD_DEFAULT);

        // OJO: Ajusta las columnas al esquema de tu tabla `estudiante`.
        // Si tu tabla no tiene `descripcion`, `cv` o `password_hash`, quítalos del INSERT.
        $sql = "INSERT INTO estudiante (name, email, telefono, descripcion, cv, password_hash)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $m->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssssss', $name, $email, $telefono, $descripcion, $cv, $passHash);
        if (!$stmt->execute()) {
            return false;
        }
        return $m->insert_id; // o true, si prefieres
    }

    /** Busca si existe un estudiante por teléfono exacto (normalizado a dígitos) */
    public static function findByTelefono(string $telefono): bool {
        $m = self::db();
        $tel = preg_replace('/\D+/', '', $telefono);
        $sql = "SELECT 1 FROM estudiante
                WHERE REPLACE(REPLACE(REPLACE(COALESCE(telefono,''), ' ', ''), '-', ''), '(', '') = ?
                LIMIT 1";
        $stmt = $m->prepare($sql);
        $stmt->bind_param('s', $tel);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    }
}
