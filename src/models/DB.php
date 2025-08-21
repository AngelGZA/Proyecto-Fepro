<?php
namespace App\Models;
use mysqli;

require_once __DIR__ . '/../bootstrap.php';

class DB {
    public function getConnection(): mysqli {
        // Lee credenciales desde variables de entorno o usa valores por defecto
        $host = env('DB_HOST', 'localhost');
        $user = env('DB_USERNAME', 'root');
        $pass = env('DB_PASSWORD', 'Mitelefono12'); // valor por defecto del remoto
        $name = env('DB_DATABASE', 'plataforma');
        $port = intval(env('DB_PORT', '3306'));

        $mysqli = new mysqli($host, $user, $pass, $name, $port);

        if ($mysqli->connect_error) {
            die("Conexión fallida: " . $mysqli->connect_error);
        }
        // Ajuste de charset
        $mysqli->set_charset('utf8mb4');
        return $mysqli;
    }
}