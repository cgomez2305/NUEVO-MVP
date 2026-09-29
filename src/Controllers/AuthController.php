<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Negocio;

class AuthController
{
    public function formularioRegistro(array $parametros): void
    {
        if (Auth::negocioActual() !== null) {
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

        if ($nombre === '' || $whatsapp === '' || strlen($password) < 6) {
            flash_set('error', 'Completa el nombre del negocio, tu WhatsApp y una contraseña de al menos 6 caracteres.');
            redirigir('/registro');
        }

        if (Negocio::buscarPorWhatsapp($whatsapp) !== null) {
            flash_set('error', 'Ya existe una cuenta con ese número de WhatsApp. Inicia sesión.');
            redirigir('/login');
        }

        $id = Negocio::crear($nombre, $whatsapp, $password, $tipoNegocio);
        session_regenerate_id(true);
        $_SESSION['negocio_id'] = $id;

        redirigir('/panel/onboarding/foto');
    }

    public function formularioLogin(array $parametros): void
    {
        if (Auth::negocioActual() !== null) {
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

        if (Auth::estaBloqueado($whatsapp)) {
            flash_set('error', 'Demasiados intentos fallidos. Espera unos minutos e intenta de nuevo.');
            redirigir('/login');
        }

        if (!Auth::intentarLogin($whatsapp, $password)) {
            flash_set('error', 'WhatsApp o contraseña incorrectos.');
            redirigir('/login');
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
}
