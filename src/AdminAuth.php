<?php

declare(strict_types=1);

namespace App;

use App\Models\Admin;

/**
 * Sesión del equipo de Veci en el panel interno (/admin), completamente
 * aparte de la sesión de un negocio (App\Auth usa $_SESSION['usuario_id'];
 * esta usa $_SESSION['admin_id']): un mismo navegador puede, en teoría,
 * tener ambas sesiones activas sin pisarse.
 */
class AdminAuth
{
    /**
     * false puede significar credenciales inválidas o cuenta bloqueada
     * temporalmente; usa estaBloqueado() antes de intentar el login para
     * distinguir el mensaje que le muestras al admin.
     */
    public static function intentarLogin(string $correo, string $password): bool
    {
        $admin = Admin::buscarPorCorreo($correo);
        if ($admin === null || Admin::bloqueado($admin)) {
            return false;
        }

        if (!password_verify($password, $admin['password_hash'])) {
            Admin::registrarIntentoFallido((int) $admin['id']);
            return false;
        }

        Admin::registrarLoginExitoso((int) $admin['id']);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];

        return true;
    }

    /** Máximo 5 intentos fallidos seguidos antes de bloquear la cuenta 15 minutos (ver Admin::MAX_INTENTOS_LOGIN). */
    public static function estaBloqueado(string $correo): bool
    {
        $admin = Admin::buscarPorCorreo($correo);
        return $admin !== null && Admin::bloqueado($admin);
    }

    public static function cerrarSesion(): void
    {
        unset($_SESSION['admin_id']);
        session_regenerate_id(true);
    }

    public static function adminId(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    public static function adminActual(): ?array
    {
        $id = self::adminId();
        return $id === null ? null : Admin::buscarPorId($id);
    }

    public static function exigirSesion(): array
    {
        $admin = self::adminActual();
        if ($admin === null) {
            redirigir('/admin/login');
        }
        return $admin;
    }
}
