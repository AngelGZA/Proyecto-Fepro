<?php
namespace App\Controllers;

use App\Models\Empresa;
use App\Models\Estudiante;

class AuthController {
    public static function login($email, $password, $userType): bool {
        $user = null;
        
        if ($userType === 'empresa') {
            $user = Empresa::findByEmail($email);
        } elseif ($userType === 'estudiante') {
            $user = Estudiante::findByEmail($email);
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['idemp'] ?? $user['idest'];
            $_SESSION['user_type'] = $userType;
            $_SESSION['username'] = $user['name'];
            return true;
        }
        return false;
    }

    public static function isLogged(): bool {
        return !empty($_SESSION['user_id']);
    }

    public static function getUserType(): ?string {
        return $_SESSION['user_type'] ?? null;
    }

    public static function logout(): void {
        session_unset();
        session_destroy();
    }

    public static function getCurrentUser(): ?array {
    if (!self::isLogged()) {
        return null;
    }

    $userType = self::getUserType();
    $userId = $_SESSION['user_id'];

    if ($userType === 'empresa') {
        $user = Empresa::findById($userId);
        if ($user) {
            $user['tipo'] = 'empresa';
            return $user;
        }
    } elseif ($userType === 'estudiante') {
        $user = Estudiante::findById($userId);
        if ($user) {
            $user['tipo'] = 'estudiante';
            $user['idest'] = $user['idest'] ?? $userId;
            return $user;
        }
    }

    return null;
    }
    
}