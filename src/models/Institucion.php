<?php
namespace App\Models;

class Institucion {
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
        throw new \RuntimeException("DB error: ".$m->connect_error);
    }
    $m->set_charset('utf8mb4');
    return $m;
}


    public static function all(): array {
        $m = self::db();
        $res = $m->query("SELECT idinst, nombre FROM institucion ORDER BY nombre");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}
