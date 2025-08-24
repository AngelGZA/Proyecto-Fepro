<?php
namespace App\Controllers;

use App\Config\Database;

class EmpresaController {
    private $db;
    
    public function __construct() {
        $this->db = (new Database())->getConnection();
    }
    
    public function getEmpresaById($id) {
        $stmt = $this->db->prepare("SELECT * FROM empresa WHERE idemp = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function getProyectosGuardados($empresaId) {
        $stmt = $this->db->prepare("
            SELECT p.id, p.titulo 
            FROM proyectos p 
            INNER JOIN empresa_proyectos_guardados epg ON p.id = epg.idproyecto 
            WHERE epg.idemp = ?
            ORDER BY epg.fecha_guardado DESC
        ");
        $stmt->bind_param("i", $empresaId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function guardarProyecto($empresaId, $proyectoId) {
        $stmt = $this->db->prepare("INSERT INTO empresa_proyectos_guardados (idemp, idproyecto) VALUES (?, ?)");
        $stmt->bind_param("ii", $empresaId, $proyectoId);
        return $stmt->execute();
    }
    
    public function eliminarProyectoGuardado($empresaId, $proyectoId) {
        $stmt = $this->db->prepare("DELETE FROM empresa_proyectos_guardados WHERE idemp = ? AND idproyecto = ?");
        $stmt->bind_param("ii", $empresaId, $proyectoId);
        return $stmt->execute();
    }
}