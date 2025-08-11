<?php
namespace App\Models;

use App\Models\DB;

class Empresa {
    public static function findByEmail(string $email): ?array {
        $db = new DB();
        $mysqli = $db->getConnection();

        $stmt = $mysqli->prepare("SELECT * FROM empresa WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    public static function findById(int $id): ?array {
        $db = new DB();
        $mysqli = $db->getConnection();

        $stmt = $mysqli->prepare("SELECT * FROM empresa WHERE idemp = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    public static function findByRFC($rfc): ?array {
    $db = new DB();
    $mysqli = $db->getConnection();

    $stmt = $mysqli->prepare("SELECT * FROM empresa WHERE rfc = ?");
    $stmt->bind_param("s", $rfc);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_assoc() ?: null;
    }

    public static function findByTelefono($telefono): ?array {
        $db = new DB(); // ✅ Crea una instancia primero
        $mysqli = $db->getConnection(); // ✅ Luego obtén la conexión
    
        $stmt = $mysqli->prepare("SELECT * FROM empresa WHERE telefono = ?");
        $stmt->bind_param("s", $telefono);
        $stmt->execute();
    
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }
    public static function create(array $data): bool {
        $db = new DB();
        $mysqli = $db->getConnection();

        $stmt = $mysqli->prepare("INSERT INTO empresa (name, email, password_hash, telefono, rfc, direccion) VALUES (?, ?, ?, ?, ?, ?)");
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt->bind_param(
            "ssssss",
            $data['name'],
            $data['email'],
            $passwordHash,
            $data['telefono'],
            $data['rfc'],
            $data['direccion']
        );
        return $stmt->execute();
    }
}
