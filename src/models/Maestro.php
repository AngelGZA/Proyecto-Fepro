<?php
namespace App\Models;

class Maestro {
    protected static function db(): \mysqli {
        static $m = null;
        if ($m instanceof \mysqli) return $m;

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? 'Mitelefono12';
        $name = $_ENV['DB_NAME'] ?? 'plataforma';
        $port = intval($_ENV['DB_PORT'] ?? 3306);

        $m = new \mysqli($host, $user, $pass, $name, $port);
        if ($m->connect_errno) {
            throw new \RuntimeException("DB connection error: ".$m->connect_error);
        }
        $m->set_charset('utf8mb4');
        return $m;
    }

    /** Trae empresa por email (para login de empresa) */
    public static function findByEmail(string $email): ?array {
        $m = self::db();
        $stmt = $m->prepare("SELECT * FROM maestro WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ?: null;
    }

    /** Trae empresa por id (para getCurrentUser) */
    public static function findById(int $idmae): ?array {
        $m = self::db();
        $stmt = $m->prepare("SELECT * FROM maestro WHERE idmae=? LIMIT 1");
        $stmt->bind_param("i", $idmae);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ?: null;
    }

    public static function emailExists(string $email, int $excludeId = 0): bool {
        $m = self::db();
        if ($excludeId > 0) {
            $stmt = $m->prepare("SELECT 1 FROM maestro WHERE email=? AND idmae<>? LIMIT 1");
            $stmt->bind_param("si", $email, $excludeId);
        } else {
            $stmt = $m->prepare("SELECT 1 FROM maestro WHERE email=? LIMIT 1");
            $stmt->bind_param("s", $email);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        return (bool)$res->fetch_row();
    }

    public static function updateProfile(int $idmae, array $data): bool {
        $m = self::db();
        $stmt = $m->prepare("
            UPDATE maestro
            SET nombre=?, email=?, telefono=?, institucion_nombre=?, especialidad=?, bio=?
            WHERE idmae=?
        ");
        $stmt->bind_param(
            "ssssssi",
            $data['nombre'],
            $data['email'],
            $data['telefono'],
            $data['institucion_nombre'],
            $data['especialidad'],
            $data['bio'],
            $idmae
        );
        return $stmt->execute();
    }

}