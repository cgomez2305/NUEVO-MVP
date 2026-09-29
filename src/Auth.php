<?php

declare(strict_types=1);

namespace App;

use App\Models\Negocio;

class Auth
{
    /**
     * false puede significar credenciales inválidas o cuenta bloqueada
     * temporalmente; usa estaBloqueado() antes de intentar el login para
     * distinguir el mensaje que le muestras al comerciante.
     */
    public static function intentarLogin(string $whatsapp, string $password): bool
    {
        $negocio = Negocio::buscarPorWhatsapp($whatsapp);
        if ($negocio === null || Negocio::bloqueado($negocio)) {
            return false;
        }

        if (!password_verify($password, $negocio['password_hash'])) {
            Negocio::registrarIntentoFallido((int) $negocio['id']);
            return false;
        }

        Negocio::registrarLoginExitoso((int) $negocio['id']);
        session_regenerate_id(true);
        $_SESSION['negocio_id'] = $negocio['id'];

        return true;
    }

    /** Máximo 5 intentos fallidos seguidos antes de bloquear la cuenta 15 minutos. */
    public static function estaBloqueado(string $whatsapp): bool
    {
        $negocio = Negocio::buscarPorWhatsapp($whatsapp);
        return $negocio !== null && Negocio::bloqueado($negocio);
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
