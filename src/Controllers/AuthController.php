<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\DispositivoConfianza;
use App\Models\Identidad;
use App\Models\OrigenRegistro;
use App\Models\EventoSeguridad;
use App\Models\Negocio;
use App\Models\LimiteTasa;
use App\Models\Sede;
use App\Models\Usuario;
use App\Services\Correo;

class AuthController
{
    public function formularioRegistro(array $parametros): void
    {
        if (Auth::usuarioActual() !== null) {
            redirigir('/panel');
        }

        // ?ref=CODIGO de otro negocio: se recuerda en la sesión hasta que
        // termine el registro (aunque el formulario vuelva con un error).
        $invita = isset($_GET['ref']) ? \App\Models\Referido::negocioPorCodigo((string) $_GET['ref']) : null;
        if ($invita !== null) {
            $_SESSION['referido_por'] = (int) $invita['id'];
        }
        $invitaId = (int) ($_SESSION['referido_por'] ?? 0);
        $invitaNombre = $invita['nombre'] ?? ($invitaId > 0 ? (\App\Models\Negocio::buscarPorId($invitaId)['nombre'] ?? null) : null);
        // Lo que manda el sitio web (utm_*, plan, ciclo, modo, oferta): igual
        // que ?ref=, queda en la sesión hasta terminar el registro.
        OrigenRegistro::captar($_GET);
        $origen = OrigenRegistro::actual();

        ver('auth/registro', [
            'titulo' => 'Crear tu tienda · Veci',
            'error'  => flash_obtener('error'),
            'invitadoPor' => $invitaNombre,
            'modoInicial' => $origen['modo'] ?? 'pedidos',
            'planElegido' => in_array($origen['plan'] ?? '', ['barrio', 'pro'], true) ? $origen['plan'] : null,
        ], 'auth');
    }

    public function registrar(array $parametros): void
    {
        if (!csrf_verificar()) {
            flash_set('error', 'El formulario expiró, intenta de nuevo.');
            redirigir('/registro');
        }

        $nombre = mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 120);
        $whatsapp = whatsapp_normalizado((string) ($_POST['whatsapp'] ?? '')) ?? '';
        $password = (string) ($_POST['password'] ?? '');
        $tipoNegocio = (string) ($_POST['tipo_negocio'] ?? 'pedidos');
        $correo = mb_substr(trim((string) ($_POST['correo'] ?? '')), 0, 160);

        if ($nombre === '' || $whatsapp === '' || strlen($password) < 8) {
            flash_set('error', 'Completa el nombre del negocio, tu WhatsApp (10 dígitos que empiecen por 3) y una contraseña de al menos 8 caracteres.');
            redirigir('/registro');
        }

        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'El correo no es válido. Puedes dejarlo en blanco si prefieres.');
            redirigir('/registro');
        }
        $debil = password_debil($password, [$whatsapp, $nombre]);
        if ($debil !== null) {
            flash_set('error', $debil);
            redirigir('/registro');
        }

        // Revisar si un número o correo ya tiene cuenta cuesta un intento: así
        // este formulario no sirve para averiguar en masa quién usa Veci.
        if (LimiteTasa::excedido('registro_consulta', ip_cliente(), 15, 3600)) {
            flash_set('error', 'Hiciste muchos intentos seguidos. Espera un rato e intenta de nuevo.');
            redirigir('/registro');
        }
        LimiteTasa::registrar('registro_consulta', ip_cliente());

        if (Usuario::buscarPorWhatsapp($whatsapp) !== null) {
            flash_set('error', 'Ya existe una cuenta con ese número de WhatsApp. Inicia sesión.');
            redirigir('/login');
        }

        if ($correo !== '' && Usuario::buscarPorCorreo($correo) !== null) {
            flash_set('error', 'Ya existe una cuenta con ese correo.');
            redirigir('/registro');
        }

        $ip = ip_cliente();
        if (LimiteTasa::excedido('registro', $ip, 3, 24 * 3600)) {
            flash_set('error', 'Ya creaste varias cuentas nuevas en poco tiempo. Espera un día o escríbenos a soporte@tuveci.co si de verdad necesitas otra.');
            redirigir('/registro');
        }

        // Una cuenta nueva es: un negocio (la marca) + su primera sede (el
        // punto de venta, con el mismo nombre y WhatsApp) + un usuario dueño.
        // Así nadie tiene que enterarse de que existen "sedes" hasta que de
        // verdad abra una segunda.
        // Todo o nada: sin la transacción, una falla a mitad dejaba un
        // negocio sin dueño (o sin sede) que nadie podía usar.
        $pdo = \App\Database::conexion();
        $pdo->beginTransaction();
        try {
            $negocioId = Negocio::crear($nombre, $tipoNegocio);
            $sedeId = Sede::crear($negocioId, $nombre, $whatsapp);
            $usuarioId = Usuario::crear($negocioId, $nombre, $whatsapp, $password, 'dueno');
            if ($correo !== '') {
                Usuario::guardarCorreo($usuarioId, $correo);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        LimiteTasa::registrar('registro', $ip);
        if (!empty($_SESSION['referido_por'])) {
            \App\Models\Referido::registrar((int) $_SESSION['referido_por'], $negocioId);
            unset($_SESSION['referido_por']);
        }
        // De dónde llegó y qué plan quería (se guarda y sale de la sesión).
        $origen = OrigenRegistro::actual();
        OrigenRegistro::guardar($negocioId);
        // Este WhatsApp ya tuvo un negocio: las ofertas "solo negocios
        // nuevos" lo reconocen aunque mañana abra otra cuenta.
        Identidad::registrar('whatsapp', hash_identidad('whatsapp', $whatsapp), $negocioId);

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuarioId;
        $_SESSION['sede_id'] = $sedeId;

        // Llegó del sitio con un plan pago elegido: nace en Gratis (nunca se
        // cobra solo) y va directo a "Tu plan" con ese plan y ciclo marcados.
        if (in_array($origen['plan'] ?? '', ['barrio', 'pro'], true)) {
            flash_set('ok', 'Tu negocio ya está creado, en el plan Gratis. Aquí puedes activar el plan ' . ucfirst($origen['plan']) . ' cuando quieras; tu tienda la terminas de armar después.');
            redirigir('/panel/plan?' . http_build_query(['plan' => $origen['plan'], 'ciclo' => $origen['ciclo'] ?? 'mensual']) . '#plan-' . $origen['plan']);
        }

        redirigir('/panel/onboarding/foto');
    }

    public function formularioLogin(array $parametros): void
    {
        if (Auth::usuarioActual() !== null) {
            redirigir('/panel');
        }

        ver('auth/login', [
            'titulo' => 'Iniciar sesión · Veci',
            'error'  => flash_obtener('error'),
        ], 'auth');
    }

    public function iniciarSesion(array $parametros): void
    {
        if (!csrf_verificar()) {
            flash_set('error', 'El formulario expiró, intenta de nuevo.');
            redirigir('/login');
        }

        $whatsapp = whatsapp_normalizado((string) ($_POST['whatsapp'] ?? '')) ?? (preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? '')) ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $ip = ip_cliente();

        // El freno por número + lugar (ver Auth::intentarLogin) no ve a quien
        // prueba UNA contraseña común contra cientos de números distintos:
        // eso se frena por IP. 20 fallos en 15 minutos es mucho más de lo que
        // hace una persona equivocándose, aun detrás de una IP compartida.
        if (LimiteTasa::excedido('login', $ip, 20, 15 * 60)) {
            flash_set('error', 'Demasiados intentos fallidos. Espera unos minutos e intenta de nuevo.');
            redirigir('/login');
        }

        if (!Auth::intentarLogin($whatsapp, $password)) {
            $mensaje = match (Auth::$motivoFallo) {
                'suspendido'     => 'Esta cuenta está suspendida. Escríbenos a soporte@tuveci.co para resolverlo.',
                'frenado'        => 'Demasiados intentos fallidos. Espera 15 minutos e intenta de nuevo, o recupera tu contraseña.',
                'solo_conocidos' => 'Por seguridad, esta cuenta recibió muchos intentos fallidos y por ahora solo deja entrar desde un celular donde ya iniciaste sesión. Si no tienes uno a mano, recupera tu contraseña.',
                default          => 'WhatsApp o contraseña incorrectos.',
            };
            if (Auth::$motivoFallo === null) {
                LimiteTasa::registrar('login', $ip);
            }
            flash_set('error', $mensaje);
            redirigir('/login');
        }

        $sede = Auth::exigirSesion();
        // El alta la termina el dueño; un colaborador entra al panel, que le
        // explica que la tienda todavía no está abierta.
        if ((int) $sede['publicada'] !== 1 && $sede['rol'] === 'dueno') {
            flash_set('ok', 'Termina de configurar tu negocio: vas por aquí.');
            redirigir('/panel/onboarding');
        }

        redirigir('/panel');
    }

    public function cerrarSesion(array $parametros): void
    {
        if (csrf_verificar()) {
            Auth::cerrarSesion();
        }
        redirigir('/');
    }

    public function formularioOlvide(array $parametros): void
    {
        ver('auth/olvide', [
            'titulo' => 'Recuperar contraseña · Veci',
            'ok'     => flash_obtener('ok'),
            'error'  => flash_obtener('error'),
        ], 'auth');
    }

    /**
     * Siempre muestra el mismo mensaje genérico, exista o no la cuenta con
     * ese WhatsApp: así nadie puede usar este formulario para averiguar
     * qué números están registrados.
     */
    public function solicitarReset(array $parametros): void
    {
        if (!csrf_verificar()) {
            flash_set('error', 'El formulario expiró, intenta de nuevo.');
            redirigir('/olvide-password');
        }

        $whatsapp = whatsapp_normalizado((string) ($_POST['whatsapp'] ?? '')) ?? (preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? '')) ?? '');
        $usuario = Usuario::buscarPorWhatsapp($whatsapp);
        $ip = ip_cliente();

        // Sin límite, este formulario sirve para bombardear de correos a
        // cualquier dueño (y para quemar la cuota del SMTP de Veci). Por IP
        // y por cuenta; el mensaje de abajo es el mismo pase lo que pase,
        // para no revelar si el número existe.
        $limitado = LimiteTasa::excedido('reset_ip', $ip, 5, 3600)
            || LimiteTasa::excedido('reset_cuenta', $whatsapp, 3, 3600);
        LimiteTasa::registrar('reset_ip', $ip);

        if (!$limitado && $usuario !== null && !empty($usuario['correo']) && Correo::disponible()) {
            LimiteTasa::registrar('reset_cuenta', $whatsapp);
            $token = Usuario::generarTokenReset((int) $usuario['id']);
            EventoSeguridad::registrar('reset_solicitado', (int) $usuario['negocio_id'], (int) $usuario['id'], 'Enviado a ' . correo_enmascarado((string) $usuario['correo']));
            $enlace = url_publica('/reset-password/' . $token);
            Correo::enviar(
                (string) $usuario['correo'],
                'Recupera tu contraseña de Veci',
                "Hola {$usuario['nombre']},\n\nEntra a este enlace para elegir una nueva contraseña. "
                . "Vence en 1 hora:\n\n{$enlace}\n\nSi no pediste esto, ignora este correo."
            );
        }

        flash_set(
            'ok',
            'Si esa cuenta tiene un correo registrado, te enviamos un enlace para recuperar tu contraseña. '
            . 'Si no tienes correo guardado o no te llega, escríbenos por WhatsApp a soporte y te ayudamos.'
        );
        redirigir('/olvide-password');
    }

    public function formularioReset(array $parametros): void
    {
        $usuario = Usuario::buscarPorTokenReset((string) $parametros['token']);
        if ($usuario === null) {
            flash_set('error', 'Ese enlace ya venció o no es válido. Pide uno nuevo.');
            redirigir('/olvide-password');
        }

        ver('auth/reset', [
            'titulo' => 'Elegir nueva contraseña · Veci',
            'token'  => $parametros['token'],
            'error'  => flash_obtener('error'),
        ], 'auth');
    }

    public function restablecer(array $parametros): void
    {
        $token = (string) $parametros['token'];
        $usuario = Usuario::buscarPorTokenReset($token);

        if (!csrf_verificar() || $usuario === null) {
            flash_set('error', 'Ese enlace ya venció o no es válido. Pide uno nuevo.');
            redirigir('/olvide-password');
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirmar = (string) ($_POST['password_confirmar'] ?? '');

        if (strlen($password) < 8 || $password !== $confirmar) {
            flash_set('error', 'La contraseña debe tener al menos 8 caracteres y coincidir en ambos campos.');
            redirigir('/reset-password/' . $token);
        }
        $debil = password_debil($password, [(string) $usuario['whatsapp'], (string) $usuario['nombre']]);
        if ($debil !== null) {
            flash_set('error', $debil);
            redirigir('/reset-password/' . $token);
        }

        Usuario::restablecerPassword((int) $usuario['id'], $password);
        // Recuperar la cuenta es también echar a quien la tuviera: todas las
        // sesiones ya se cerraron (sesion_version) y ningún celular queda como
        // conocido salvo este, que acaba de demostrar que recibe el enlace.
        DispositivoConfianza::olvidarTodos((int) $usuario['id']);
        DispositivoConfianza::recordar((int) $usuario['id']);
        EventoSeguridad::registrar('password_restablecida', (int) $usuario['negocio_id'], (int) $usuario['id']);
        flash_set('ok', 'Tu contraseña quedó actualizada. Ya puedes iniciar sesión.');
        redirigir('/login');
    }
}
