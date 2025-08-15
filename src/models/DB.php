<?php
namespace App\Models;
use mysqli;

class DB {
    public function getConnection(): mysqli {
        $mysqli = new mysqli("localhost", "root", "jojoyrl8", "plataforma");

        if ($mysqli->connect_error) {
            die("Conexión fallida: " . $mysqli->connect_error);
        }

        return $mysqli;
    }
}