<?php
namespace App\Models;

class Empresa {
    /** Conexión mysqli reutilizable (mismo patrón que Estudiante) */
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

    /** Crea una empresa. Devuelve el id insertado o false */
    public static function create(array $data) {
        $m = self::db();

        // Campos esperados (ajusta a tus columnas reales)
        $name        = $data['name']        ?? '';
        $email       = $data['email']       ?? '';
        $telefonoRaw = $data['telefono']    ?? '';
        $rfcRaw      = $data['rfc']         ?? '';
        $direccion   = $data['direccion']   ?? null;

        // Normalizaciones recomendadas
        $telefono = preg_replace('/\D+/', '', $telefonoRaw);         // solo dígitos
        $rfc      = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $rfcRaw));

        // Hash de contraseña
        $password = $data['password'] ?? '';
        $hash     = password_hash($password, PASSWORD_DEFAULT);

        // IMPORTANTE: si en tu tabla la columna es `nombre` y no `name`, cambia el SQL
        $sql = "INSERT INTO empresa (name, email, telefono, rfc, direccion, password_hash)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $m->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param('ssssss', $name, $email, $telefono, $rfc, $direccion, $hash);
        if (!$stmt->execute()) {
            return false;
        }
        return $m->insert_id; // o true si prefieres
    }

    /** Busca por id */
    public static function findById(int $idemp): ?array {
        $m = self::db();
        $stmt = $m->prepare("SELECT * FROM empresa WHERE idemp=? LIMIT 1");
        $stmt->bind_param("i", $idemp);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ?: null;
    }

    /** Busca por email (útil para login/validación) */
    public static function findByEmail(string $email): ?array {
        $m = self::db();
        $stmt = $m->prepare("SELECT * FROM empresa WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ?: null;
    }

    /** ¿Existe otra empresa con el mismo email? (excluyendo un id) */
    public static function emailExists(string $email, int $excludeId): bool {
        $m = self::db();
        $stmt = $m->prepare("SELECT 1 FROM empresa WHERE email=? AND idemp<>? LIMIT 1");
        $stmt->bind_param("si", $email, $excludeId);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_row();
    }

    /** ¿RFC ya usado? (excluyendo un id) */
    public static function rfcExists(string $rfc, int $excludeId = 0): bool {
        $m = self::db();
        $rfcNorm = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $rfc));
        if ($excludeId > 0) {
            $stmt = $m->prepare("SELECT 1 FROM empresa WHERE UPPER(REPLACE(rfc, ' ', ''))=? AND idemp<>? LIMIT 1");
            $stmt->bind_param("si", $rfcNorm, $excludeId);
        } else {
            $stmt = $m->prepare("SELECT 1 FROM empresa WHERE UPPER(REPLACE(rfc, ' ', ''))=? LIMIT 1");
            $stmt->bind_param("s", $rfcNorm);
        }
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_row();
    }

    /** Teléfono exacto (normalizado a dígitos) ya existe */
    public static function findByTelefono(string $telefono): bool {
        $m = self::db();
        $tel = preg_replace('/\D+/', '', $telefono);
        $sql = "SELECT 1 FROM empresa
                WHERE REPLACE(REPLACE(REPLACE(COALESCE(telefono,''), ' ', ''), '-', ''), '(', '') = ?
                LIMIT 1";
        $stmt = $m->prepare($sql);
        $stmt->bind_param('s', $tel);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_row();
    }
}
