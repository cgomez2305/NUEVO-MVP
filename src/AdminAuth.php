<?php

declare(strict_types=1);

namespace App;

use App\Models\Admin;
use App\Models\EventoSeguridad;
use App\Models\LimiteTasa;

/**
 * Sesión del equipo de Veci en el panel interno (/admin), completamente
 * aparte de la sesión de un negocio (App\Auth usa $_SESSION['usuario_id'];
 * esta usa $_SESSION['admin_id']): un mismo navegador puede, en teoría,
 * tener ambas sesiones activas sin pisarse.
 */
class AdminAuth
{
    /**
     * Una sesión de /admin puede confirmar pagos y suspender cuentas: si
     * alguien deja el portátil abierto, a los 30 minutos sin actividad se
     * cierra sola (la de un negocio no, para no sacar al dueño a mitad de
     * un turno).
     */
    private const INACTIVIDAD_MAXIMA_SEGUNDOS = 30 * 60;

    /** Y aunque siga activa, a las 10 horas se vuelve a pedir contraseña y código. */
    private const DURACION_MAXIMA_SEGUNDOS = 10 * 3600;

    /** Por qué falló el último intentarLogin(): null (datos incorrectos), 'frenado' o 'sin_2fa'. */
    public static ?string $motivoFallo = null;

    /**
     * Contraseña Y código de la app autenticadora (TOTP): un admin confirma
     * pagos y suspende negocios, con solo una contraseña filtrada no se
     * entra. Sin segundo factor activado no se entra (bin/admin_2fa.php).
     *
     * El freno es por correo + IP (5 fallos, 15 min) para que nadie deje por
     * fuera a un admin desde afuera, y por correo en total (20 por hora)
     * porque aquí un ataque repartido sí justifica cerrar la puerta: un
     * admin siempre puede entrar al servidor y revisar.
     */
    public static function intentarLogin(string $correo, string $password, string $codigo): bool
    {
        self::$motivoFallo = null;
        $correo = mb_strtolower(trim($correo));
        $claveLugar = mb_substr($correo, 0, 50) . '|' . ip_cliente();
        $claveCorreo = mb_substr($correo, 0, 80);
        if (LimiteTasa::excedido('admin_cuenta', $claveLugar, 5, 15 * 60) || LimiteTasa::excedido('admin_correo', $claveCorreo, 20, 3600)) {
            self::$motivoFallo = 'frenado';
            return false;
        }

        $admin = Admin::buscarPorCorreo($correo);
        $hash = $admin['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuJ1lYcjS8Yl2bJ0n1tq5bWm1N3oQ5rS.';
        $claveBien = password_verify($password, (string) $hash) && $admin !== null;
        $secreto = (string) ($admin['totp_secreto'] ?? '');
        $paso = $claveBien && $secreto !== '' ? Totp::verificar($secreto, $codigo, isset($admin['totp_ultimo_paso']) ? (int) $admin['totp_ultimo_paso'] : null) : null;

        if (!$claveBien || ($secreto !== '' && ($paso === null || !Admin::consumirPasoTotp((int) $admin['id'], $paso)))) {
            LimiteTasa::registrar('admin_cuenta', $claveLugar);
            LimiteTasa::registrar('admin_correo', $claveCorreo);
            return false;
        }
        if ($secreto === '') {
            // La contraseña era correcta, pero sin segundo factor no se entra.
            self::$motivoFallo = 'sin_2fa';
            return false;
        }

        LimiteTasa::limpiar('admin_cuenta', $claveLugar);
        EventoSeguridad::registrar('admin_login', null, null, '', (int) $admin['id']);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_ultima_actividad'] = time();
        $_SESSION['admin_inicio'] = time();

        return true;
    }

    public static function cerrarSesion(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['admin_ultima_actividad'], $_SESSION['admin_inicio']);
        session_regenerate_id(true);
    }

    public static function adminId(): ?int
    {
        if (!isset($_SESSION['admin_id'])) {
            return null;
        }

        $ultima = (int) ($_SESSION['admin_ultima_actividad'] ?? 0);
        $inicio = (int) ($_SESSION['admin_inicio'] ?? 0);
        if (time() - $ultima > self::INACTIVIDAD_MAXIMA_SEGUNDOS || time() - $inicio > self::DURACION_MAXIMA_SEGUNDOS) {
            unset($_SESSION['admin_id'], $_SESSION['admin_ultima_actividad'], $_SESSION['admin_inicio']);
            return null;
        }

        $_SESSION['admin_ultima_actividad'] = time();
        return (int) $_SESSION['admin_id'];
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
