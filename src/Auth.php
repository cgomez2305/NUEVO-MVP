<?php

declare(strict_types=1);

namespace App;

use App\Models\Negocio;

class Auth
{
    public static function intentarLogin(string $whatsapp, string $password): bool
    {
        $negocio = Negocio::buscarPorWhatsapp($whatsapp);
        if ($negocio === null || !password_verify($password, $negocio['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['negocio_id'] = $negocio['id'];

        return true;
    }

    public static function cerrarSesion(): void
    {
        unset($_SESSION['negocio_id']);
        session_regenerate_id(true);
    }

    public static function negocioId(): ?int
    {
        return isset($_SESSION['negocio_id']) ? (int) $_SESSION['negocio_id'] : null;
    }

    public static function negocioActual(): ?array
    {
        $id = self::negocioId();
        return $id === null ? null : Negocio::buscarPorId($id);
    }

    /** Redirige a /login si no hay sesión y detiene la ejecución. */
    public static function exigirSesion(): array
    {
        $negocio = self::negocioActual();
        if ($negocio === null) {
            redirigir('/login');
        }
        return $negocio;
    }
}
