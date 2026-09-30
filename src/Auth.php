<?php

declare(strict_types=1);

namespace App;

use App\Models\Sede;
use App\Models\Usuario;

class Auth
{
    /**
     * false puede significar credenciales inválidas o cuenta bloqueada
     * temporalmente; usa estaBloqueado() antes de intentar el login para
     * distinguir el mensaje que le muestras al usuario.
     */
    public static function intentarLogin(string $whatsapp, string $password): bool
    {
        $usuario = Usuario::buscarPorWhatsapp($whatsapp);
        if ($usuario === null || Usuario::bloqueado($usuario) || (int) $usuario['negocio_suspendido'] === 1) {
            return false;
        }

        if (!password_verify($password, $usuario['password_hash'])) {
            Usuario::registrarIntentoFallido((int) $usuario['id']);
            return false;
        }

        Usuario::registrarLoginExitoso((int) $usuario['id']);
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        unset($_SESSION['sede_id']); // se elige de nuevo, por si el usuario ya no tiene acceso a la que tenía antes

        return true;
    }

    /** Máximo 5 intentos fallidos seguidos antes de bloquear la cuenta 15 minutos. */
    public static function estaBloqueado(string $whatsapp): bool
    {
        $usuario = Usuario::buscarPorWhatsapp($whatsapp);
        return $usuario !== null && Usuario::bloqueado($usuario);
    }

    /** El negocio de esa cuenta fue suspendido por el equipo de Veci (ver AdminController). */
    public static function estaSuspendido(string $whatsapp): bool
    {
        $usuario = Usuario::buscarPorWhatsapp($whatsapp);
        return $usuario !== null && (int) $usuario['negocio_suspendido'] === 1;
    }

    public static function cerrarSesion(): void
    {
        unset($_SESSION['usuario_id'], $_SESSION['sede_id']);
        session_regenerate_id(true);
    }

    public static function usuarioId(): ?int
    {
        return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    }

    public static function usuarioActual(): ?array
    {
        $id = self::usuarioId();
        return $id === null ? null : Usuario::buscarPorId($id);
    }

    /**
     * Devuelve la sede "activa" de la sesión (con los datos de marca del
     * negocio ya incluidos: tipo_negocio, color_marca, negocio_nombre) más
     * el contexto del usuario (negocio_id, usuario_id, usuario_nombre, rol).
     * Es lo que usan el panel y el onboarding para saber "de quién y dónde"
     * es cada pedido/producto/cita que se lee o se guarda.
     *
     * Redirige a /login si no hay sesión, y a /panel/sedes si el usuario
     * (un colaborador, normalmente) no tiene ninguna sede asignada.
     */
    public static function exigirSesion(): array
    {
        $usuario = self::usuarioActual();
        if ($usuario === null) {
            redirigir('/login');
        }

        $contexto = self::contextoActual();
        if ($contexto === null) {
            // Usuario real pero sin ninguna sede asignada (colaborador que el
            // dueño todavía no puso en ninguna sede). No lo mandamos a /login
            // (haría un ciclo: ya tiene sesión), sino a una pantalla de aviso.
            ver('panel/sin_sede', ['titulo' => 'Sin sedes asignadas · Veci', 'usuario' => $usuario], null);
            exit;
        }

        return $contexto;
    }

    /** Corta la ejecución si el usuario de la sesión no es dueño (ajustes de negocio: sedes, colaboradores, etc). */
    public static function exigirDueno(array $contexto): void
    {
        if ($contexto['rol'] !== 'dueno') {
            flash_set('error', 'Esa opción solo la puede usar el dueño del negocio.');
            redirigir('/panel');
        }
    }

    /** Cambia la sede activa de la sesión, validando que el usuario tenga acceso a ella. */
    public static function cambiarSede(int $sedeId): bool
    {
        $usuario = self::usuarioActual();
        if ($usuario === null || !Usuario::tieneAccesoASede($usuario, $sedeId)) {
            return false;
        }
        $_SESSION['sede_id'] = $sedeId;
        return true;
    }

    private static function contextoActual(): ?array
    {
        $usuario = self::usuarioActual();
        if ($usuario === null) {
            return null;
        }

        $sedeId = isset($_SESSION['sede_id']) ? (int) $_SESSION['sede_id'] : null;
        $sede = $sedeId !== null ? Sede::buscarPorId($sedeId) : null;

        if ($sede === null || !Usuario::tieneAccesoASede($usuario, (int) $sede['id'])) {
            $sede = self::primeraSedeDisponible($usuario);
            if ($sede === null) {
                return null;
            }
            $_SESSION['sede_id'] = (int) $sede['id'];
        }

        return array_merge($sede, [
            'negocio_id'     => (int) $usuario['negocio_id'],
            'usuario_id'     => (int) $usuario['id'],
            'usuario_nombre' => $usuario['nombre'],
            'rol'            => $usuario['rol'],
        ]);
    }

    /** Sedes a las que puede entrar el usuario de la sesión (para el selector de sede del panel). */
    public static function sedesAccesibles(array $contexto): array
    {
        if ($contexto['rol'] === 'dueno') {
            return Sede::listarPorNegocio((int) $contexto['negocio_id']);
        }

        $sedeIds = Usuario::sedeIdsAsignadas((int) $contexto['usuario_id']);
        return array_values(array_filter(array_map(fn ($id) => Sede::buscarPorId($id), $sedeIds)));
    }

    private static function primeraSedeDisponible(array $usuario): ?array
    {
        if ($usuario['rol'] === 'dueno') {
            $sedes = Sede::listarPorNegocio((int) $usuario['negocio_id']);
            return $sedes[0] ?? null;
        }

        $sedeIds = Usuario::sedeIdsAsignadas((int) $usuario['id']);
        return $sedeIds === [] ? null : Sede::buscarPorId($sedeIds[0]);
    }
}
