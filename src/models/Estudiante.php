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
            $_ENV['DB_PASSWORD'] ?? 'Mitelefono12',
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
}
