<?php

declare(strict_types=1);

namespace App;

use App\Models\DispositivoConfianza;
use App\Models\EventoSeguridad;
use App\Models\LimiteTasa;
use App\Models\Sede;
use App\Models\Usuario;

class Auth
{
    /**
     * Por qué falló el último intentarLogin(): null (datos incorrectos),
     * 'frenado' (demasiados intentos desde aquí), 'solo_conocidos' (la
     * cuenta está bajo ataque: solo entra un celular conocido) o 'suspendido'.
     */
    public static ?string $motivoFallo = null;

    /** Intentos fallidos con un mismo número desde un mismo lugar (IP o celular conocido) antes de frenar 15 minutos. */
    private const MAX_INTENTOS = 5;

    /**
     * Intentos fallidos con un mismo número desde lugares DESCONOCIDOS en una
     * hora antes de aceptar solo celulares conocidos: eso ya es un ataque
     * repartido entre muchas IPs, no alguien que olvidó su contraseña.
     */
    private const MAX_DESCONOCIDOS_HORA = 30;

    /**
     * Antes el bloqueo era por cuenta: cualquiera que supiera el WhatsApp de
     * un dueño lo dejaba por fuera 15 minutos con 5 intentos. Ahora el freno
     * es por número + lugar, así que el atacante solo se frena a sí mismo, y
     * desde un celular donde el dueño ya entró se sigue entrando aunque la
     * cuenta esté bajo ataque. Todo va por el número escrito (exista o no la
     * cuenta): las respuestas no revelan qué números tienen cuenta.
     */
    public static function intentarLogin(string $whatsapp, string $password): bool
    {
        self::$motivoFallo = null;
        $usuario = Usuario::buscarPorWhatsapp($whatsapp);
        $conocido = $usuario !== null ? DispositivoConfianza::deEsteNavegador((int) $usuario['id']) : null;
        $claveLugar = 'w' . $whatsapp . '|' . ($conocido !== null ? 'd' . $conocido['id'] : ip_cliente());
        $claveNumero = 'w' . $whatsapp;

        if (LimiteTasa::excedido('login_cuenta', $claveLugar, self::MAX_INTENTOS, 15 * 60)) {
            self::$motivoFallo = 'frenado';
            return false;
        }
        if ($conocido === null && LimiteTasa::excedido('login_desconocido', $claveNumero, self::MAX_DESCONOCIDOS_HORA, 3600)) {
            self::$motivoFallo = 'solo_conocidos';
            return false;
        }

        // Con o sin cuenta, el mismo trabajo: por el tiempo de respuesta no
        // se puede saber qué números tienen cuenta.
        $hash = $usuario['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuJ1lYcjS8Yl2bJ0n1tq5bWm1N3oQ5rS.';
        if (!password_verify($password, (string) $hash) || $usuario === null) {
            LimiteTasa::registrar('login_cuenta', $claveLugar);
            if ($conocido === null) {
                LimiteTasa::registrar('login_desconocido', $claveNumero);
            }
            // Una sola vez por freno (el intento que lo activa), no por cada fallo.
            if ($usuario !== null && LimiteTasa::excedido('login_cuenta', $claveLugar, self::MAX_INTENTOS, 15 * 60)
                && !LimiteTasa::excedido('login_cuenta', $claveLugar, self::MAX_INTENTOS + 1, 15 * 60)) {
                EventoSeguridad::registrar('cuenta_frenada', (int) $usuario['negocio_id'], (int) $usuario['id'], self::MAX_INTENTOS . ' contraseñas incorrectas seguidas');
            }
            return false;
        }
        // "Suspendida" solo se le dice a quien ya demostró la contraseña.
        if ((int) $usuario['negocio_suspendido'] === 1) {
            self::$motivoFallo = 'suspendido';
            return false;
        }

        LimiteTasa::limpiar('login_cuenta', $claveLugar);
        EventoSeguridad::registrar($conocido !== null ? 'login' : 'login_nuevo', (int) $usuario['negocio_id'], (int) $usuario['id']);
        DispositivoConfianza::recordar((int) $usuario['id']);
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['sesion_version'] = (int) $usuario['sesion_version'];
        unset($_SESSION['sede_id']); // se elige de nuevo, por si el usuario ya no tiene acceso a la que tenía antes

        return true;
    }

    /**
     * Cierra la sesión del negocio y borra todo lo que quedó en ella
     * (borradores del mostrador o del fiado con nombre y teléfono de
     * clientes, cupones reservados del copiloto): en un celular compartido
     * nada de eso debe quedarle al siguiente. Solo se conserva una sesión
     * de admin abierta en el mismo navegador.
     */
    public static function cerrarSesion(): void
    {
        foreach (array_keys($_SESSION) as $clave) {
            if (!str_starts_with((string) $clave, 'admin')) {
                unset($_SESSION[$clave]);
            }
        }
        session_regenerate_id(true);
    }

    public static function usuarioId(): ?int
    {
        return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    }

    /**
     * El usuario de la sesión, si la sesión sigue valiendo: se cierra si el
     * equipo de Veci suspendió el negocio después de que entró, o si la
     * contraseña cambió desde otra sesión (sesion_version distinta).
     */
    public static function usuarioActual(): ?array
    {
        $id = self::usuarioId();
        $usuario = $id === null ? null : Usuario::buscarPorId($id);
        if ($id !== null && ($usuario === null
            || (int) $usuario['negocio_suspendido'] === 1
            || (int) ($_SESSION['sesion_version'] ?? 0) !== (int) $usuario['sesion_version'])) {
            self::cerrarSesion();

            return null;
        }

        return $usuario;
    }

    /** Minutos que dura una confirmación de identidad para acciones sensibles. */
    private const MINUTOS_IDENTIDAD = 10;

    /**
     * ¿El usuario escribió su contraseña hace poco? Las acciones que un
     * intruso con una sesión robada (celular prestado, sesión abierta en un
     * computador ajeno) usaría para quedarse con el negocio o su plata la
     * piden otra vez: cambiar la llave Bre-B o el correo de recuperación,
     * crear colaboradores, exportar la lista de clientes.
     */
    public static function identidadReciente(): bool
    {
        return isset($_SESSION['identidad_en']) && time() - (int) $_SESSION['identidad_en'] <= self::MINUTOS_IDENTIDAD * 60;
    }

    /**
     * true si ya la confirmó hace poco o si $password es la suya (y la deja
     * confirmada por 10 minutos). 5 intentos fallidos por 15 minutos: no se
     * puede adivinar la contraseña desde una sesión robada.
     */
    public static function confirmarIdentidad(array $contexto, string $password): bool
    {
        if (self::identidadReciente()) {
            return true;
        }
        $clave = 'u' . (int) $contexto['usuario_id'];
        if ($password === '' || LimiteTasa::excedido('identidad', $clave, 5, 15 * 60)) {
            return false;
        }
        $usuario = Usuario::buscarPorId((int) $contexto['usuario_id']);
        if ($usuario === null || !password_verify($password, (string) $usuario['password_hash'])) {
            LimiteTasa::registrar('identidad', $clave);
            return false;
        }
        $_SESSION['identidad_en'] = time();

        return true;
    }

    /** Tras cambiar la contraseña en esta sesión: esta sigue abierta, las demás no. */
    public static function renovarVersionDeSesion(): void
    {
        $usuario = self::usuarioId() !== null ? Usuario::buscarPorId((int) self::usuarioId()) : null;
        if ($usuario !== null) {
            $_SESSION['sesion_version'] = (int) $usuario['sesion_version'];
        }
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
