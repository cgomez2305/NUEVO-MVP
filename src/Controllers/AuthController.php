<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
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

        ver('auth/registro', [
            'titulo' => 'Crear tu tienda · Veci',
            'error'  => flash_obtener('error'),
        ], 'auth');
    }

    public function registrar(array $parametros): void
    {
        if (!csrf_verificar()) {
            flash_set('error', 'El formulario expiró, intenta de nuevo.');
            redirigir('/registro');
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $whatsapp = preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? '')) ?? '';
        $password = (string) ($_POST['password'] ?? '');
        $tipoNegocio = (string) ($_POST['tipo_negocio'] ?? 'pedidos');
        $correo = trim((string) ($_POST['correo'] ?? ''));

        if ($nombre === '' || $whatsapp === '' || strlen($password) < 8) {
            flash_set('error', 'Completa el nombre del negocio, tu WhatsApp y una contraseña de al menos 8 caracteres.');
            redirigir('/registro');
        }

        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'El correo no es válido. Puedes dejarlo en blanco si prefieres.');
            redirigir('/registro');
        }

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
        $negocioId = Negocio::crear($nombre, $tipoNegocio);
        $sedeId = Sede::crear($negocioId, $nombre, $whatsapp);
        $usuarioId = Usuario::crear($negocioId, $nombre, $whatsapp, $password, 'dueno');
        if ($correo !== '') {
            Usuario::guardarCorreo($usuarioId, $correo);
        }

        LimiteTasa::registrar('registro', $ip);

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuarioId;
        $_SESSION['sede_id'] = $sedeId;

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

        $whatsapp = preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? '')) ?? '';
        $password = (string) ($_POST['password'] ?? '');
        $ip = ip_cliente();

        // El bloqueo por cuenta (5 intentos) no ve a quien prueba UNA
        // contraseña común contra cientos de números distintos: eso se
        // frena por IP. 20 fallos en 15 minutos es mucho más de lo que hace
        // una persona equivocándose, aun detrás de una IP compartida.
        if (Auth::estaBloqueado($whatsapp) || LimiteTasa::excedido('login', $ip, 20, 15 * 60)) {
            flash_set('error', 'Demasiados intentos fallidos. Espera unos minutos e intenta de nuevo.');
            redirigir('/login');
        }

        if (Auth::estaSuspendido($whatsapp)) {
            flash_set('error', 'Esta cuenta está suspendida. Escríbenos a soporte@tuveci.co para resolverlo.');
            redirigir('/login');
        }

        if (!Auth::intentarLogin($whatsapp, $password)) {
            LimiteTasa::registrar('login', $ip);
            flash_set('error', 'WhatsApp o contraseña incorrectos.');
            redirigir('/login');
        }

        $sede = Auth::exigirSesion();
        if ((int) $sede['publicada'] !== 1) {
            flash_set('ok', 'Termina de configurar tu negocio.');
            redirigir('/panel/onboarding/' . Sede::siguientePasoOnboarding($sede));
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

        $whatsapp = preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? '')) ?? '';
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

        Usuario::restablecerPassword((int) $usuario['id'], $password);
        flash_set('ok', 'Tu contraseña quedó actualizada. Ya puedes iniciar sesión.');
        redirigir('/login');
    }
}
