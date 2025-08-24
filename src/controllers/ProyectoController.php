<?php
namespace App\Controllers;

use App\Config\Database;

class ProyectoController {
    private $db;
    
    public function __construct() {
        $this->db = (new Database())->getConnection();
    }
    
    public function getProyectosPublicos() {
        $stmt = $this->db->prepare("
            SELECT p.*, e.name as estudiante 
            FROM proyectos p 
            INNER JOIN estudiante e ON p.idest = e.idest 
            WHERE p.visibilidad = 'publico' 
            ORDER BY p.created_at DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getProyectoById($id) {
        $stmt = $this->db->prepare("
            SELECT p.*, e.name as estudiante 
            FROM proyectos p 
            INNER JOIN estudiante e ON p.idest = e.idest 
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}