<?php
namespace App\Models;

use App\Models\DB;

class Solicitud {
    public static function existeSolicitud($idEstudiante, $idEmpleo) {
        $db = new DB();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT COUNT(*) FROM solicitudes WHERE idest = ? AND idof = ?");
        $stmt->bind_param("ii", $idEstudiante, $idEmpleo);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();

        return $count > 0;
    }

    public static function crear($idEstudiante, $idEmpleo) {
        $db = new DB();
        $conn = $db->getConnection();

        if (self::existeSolicitud($idEstudiante, $idEmpleo)) {
            return false;
        }

        $stmt = $conn->prepare("INSERT INTO solicitudes (idest, idof) VALUES (?, ?)");
        $stmt->bind_param("ii", $idEstudiante, $idEmpleo);
        return $stmt->execute();
    }

    public static function getSolicitudesPorEstudiante($idEstudiante) {
        $db = new DB();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT s.idsol, e.puesto, emp.name AS empresa, e.modalidad, e.horario
                              FROM solicitudes s
                              JOIN empleos e ON s.idof = e.idof
                              JOIN empresa emp ON e.idemp = emp.idemp
                              WHERE s.idest = ?");
        $stmt->bind_param("i", $idEstudiante);
        $stmt->execute();
        $result = $stmt->get_result();

        $solicitudes = [];
        while ($row = $result->fetch_assoc()) {
            $solicitudes[] = $row;
        }

        return $solicitudes;
    }
}