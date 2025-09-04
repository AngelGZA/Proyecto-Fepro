<?php
namespace App\Models;

class Maestro {
    /** Conexión mysqli reutilizable (mismo patrón que Estudiante/Empresa) */
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

    /** Crea un maestro/docente. Devuelve el id insertado o false */
    public static function create(array $data) {
        $m = self::db();

        // Campos esperados (ajústalos a tu formulario)
        $nombreRaw   = $data['name']         ?? '';  // en BD usamos "nombre"
        $email       = $data['email']        ?? '';
        $telefonoRaw = $data['telefono']     ?? '';
        $especialidad= $data['especialidad'] ?? null;
        $bio         = $data['bio']          ?? null;
        $institucion = $data['institucion']  ?? null;

        // Normaliza
        $nombre   = $nombreRaw;
        $telefono = preg_replace('/\D+/', '', $telefonoRaw);

        // Hash de contraseña
        $password = $data['password'] ?? '';
        $hash     = password_hash($password, PASSWORD_DEFAULT);

        // IMPORTANTE: si en tu tabla se llama "name" en lugar de "nombre",
        // cambia la columna en el INSERT a "name".
        $sql = "INSERT INTO maestro (nombre, email, telefono, password_hash, especialidad, bio, institucion_nombre)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $m->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param('sssssss', $nombre, $email, $telefono, $hash, $especialidad, $bio, $institucion);
        if (!$stmt->execute()) {
            return false;
        }
        return $m->insert_id; // o true si prefieres
    }

    /** Buscar por id */
    public static function findById(int $idmae): ?array {
        $m = self::db();
        $stmt = $m->prepare("SELECT * FROM maestro WHERE idmae=? LIMIT 1");
        $stmt->bind_param("i", $idmae);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ?: null;
    }

    /** Buscar por email (útil para login) */
    public static function findByEmail(string $email): ?array {
        $m = self::db();
        $stmt = $m->prepare("SELECT * FROM maestro WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ?: null;
    }

    /** ¿Existe otro maestro con el mismo email? (excluyendo un id) */
    public static function emailExists(string $email, int $excludeId): bool {
        $m = self::db();
        $stmt = $m->prepare("SELECT 1 FROM maestro WHERE email=? AND idmae<>? LIMIT 1");
        $stmt->bind_param("si", $email, $excludeId);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_row();
    }

    /** Teléfono exacto (normalizado a dígitos) ya existe */
    public static function findByTelefono(string $telefono): bool {
        $m = self::db();
        $tel = preg_replace('/\D+/', '', $telefono);
        // Compara contra el valor normalizado; si almacenas con guiones/espacios, usa REPLACE.
        $sql = "SELECT 1 FROM maestro
                WHERE REPLACE(REPLACE(REPLACE(COALESCE(telefono,''), ' ', ''), '-', ''), '(', '') = ?
                LIMIT 1";
        $stmt = $m->prepare($sql);
        $stmt->bind_param('s', $tel);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_row();
    }
}
