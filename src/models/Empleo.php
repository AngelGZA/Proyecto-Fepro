<?php
namespace App\Models;

use App\Models\DB;

class Empleo {
    public static function getAll(): array {
        $db = new DB();
        $conn = $db->getConnection();

        $result = $conn->query("SELECT e.idof, e.puesto, e.descripcion, e.requisitos, emp.name AS empresa
                              FROM empleos e
                              JOIN empresa emp ON e.idemp = emp.idemp");

        $empleos = [];
        while ($row = $result->fetch_assoc()) {
            $empleos[] = $row;
        }

        return $empleos;
    }

    public static function findById($id) {
        $db = new DB();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT e.*, emp.name AS empresa 
                               FROM empleos e 
                               JOIN empresa emp ON e.idemp = emp.idemp 
                               WHERE e.idof = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

    public static function getNombreEmpresa($idEmpresa) {
        $db = new DB();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT name FROM empresa WHERE idemp = ?");
        $stmt->bind_param("i", $idEmpresa);
        $stmt->execute();
        $result = $stmt->get_result();
        $empresa = $result->fetch_assoc();

        return $empresa ? $empresa['name'] : 'Empresa no disponible';
    }
}