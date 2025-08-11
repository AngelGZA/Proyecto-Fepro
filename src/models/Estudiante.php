<?php
namespace App\Models;

use App\Models\DB;
use App\Models\Empleo;

$empleosDisponibles = Empleo::getAll();

class Estudiante {
    public static function findByEmail(string $email): ?array {
        $db = new DB();
        $mysqli = $db->getConnection();

        $stmt = $mysqli->prepare("SELECT * FROM estudiante WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    public static function findById(int $id): ?array {
        $db = new DB();
        $mysqli = $db->getConnection();

        $stmt = $mysqli->prepare("SELECT * FROM estudiante WHERE idest = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    public static function findByTelefono($telefono): ?array {
        $db = new DB();
        $mysqli = $db->getConnection();
    
        $stmt = $mysqli->prepare("SELECT * FROM estudiante WHERE telefono = ?");
        $stmt->bind_param("s", $telefono);
        $stmt->execute();
    
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    public static function create(array $data): bool {
        $db = new DB();
        $mysqli = $db->getConnection();

        $stmt = $mysqli->prepare("INSERT INTO estudiante 
            (name, email, password_hash, telefono, descripcion, cv) 
            VALUES (?, ?, ?, ?, ?, ?)");
    
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt->bind_param(
            "ssssss",
            $data['name'],
            $data['email'],
            $passwordHash,
            $data['telefono'],
            $data['descripcion'],
            $data['cv']
        );
    
        return $stmt->execute();
    }

    public static function updateProfile(int $idest, array $data): bool {
        $db = new DB();
        $mysqli = $db->getConnection();

        // Verificar si el email ya existe para otro usuario
        if (!empty($data['email'])) {
            $stmt = $mysqli->prepare("SELECT idest FROM estudiante WHERE email = ? AND idest != ?");
            $stmt->bind_param("si", $data['email'], $idest);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return false; // Email ya en uso
            }
        }

        // Actualizar datos básicos
        $query = "UPDATE estudiante SET name = ?, email = ?, telefono = ?, descripcion = ? WHERE idest = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param(
            "ssssi",
            $data['name'],
            $data['email'],
            $data['telefono'],
            $data['descripcion'],
            $idest
        );

        return $stmt->execute();
    }

    public static function updateCV(int $idest, string $cvPath): bool {
        $db = new DB();
        $mysqli = $db->getConnection();
        
        $stmt = $mysqli->prepare("UPDATE estudiante SET cv = ? WHERE idest = ?");
        $stmt->bind_param("si", $cvPath, $idest);
        
        return $stmt->execute();
    }

    public static function emailExists(string $email, int $excludeId = null): bool {
        $db = new DB();
        $mysqli = $db->getConnection();
        
        $query = "SELECT idest FROM estudiante WHERE email = ?";
        $params = [$email];
        $types = "s";
        
        if ($excludeId !== null) {
            $query .= " AND idest != ?";
            $params[] = $excludeId;
            $types .= "i";
        }
        
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}