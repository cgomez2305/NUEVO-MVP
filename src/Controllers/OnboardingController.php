<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Sede;
use App\Models\Producto;
use App\Models\Servicio;
use App\Services\ExtractorMenu;

/**
 * El flujo A de la maqueta: foto del menú → la IA arma la tienda →
 * Bre-B → publicada. Cada paso lee y escribe directo sobre el negocio
 * en sesión, así que el dueño puede cerrar y volver sin perder nada.
 */
class OnboardingController
{
    public function mostrarFoto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('onboarding/foto', [
            'titulo'  => 'Foto del menú · Veci',
            'negocio' => $negocio,
            'error'   => flash_obtener('error'),
        ], 'onboarding');
    }

    public function subirFoto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (!csrf_verificar()) {
            flash_set('error', 'El formulario expiró, intenta de nuevo.');
            redirigir('/panel/onboarding/foto');
        }

        $archivo = $_FILES['foto'] ?? null;
        if ($archivo === null || $archivo['error'] !== UPLOAD_ERR_OK) {
            flash_set('error', 'No pudimos leer la foto. Intenta de nuevo.');
            redirigir('/panel/onboarding/foto');
        }

        $tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = mime_content_type($archivo['tmp_name']) ?: '';
        if (!isset($tiposPermitidos[$mime])) {
            flash_set('error', 'Sube una imagen JPG, PNG o WEBP.');
            redirigir('/panel/onboarding/foto');
        }

        if ($archivo['size'] > 8 * 1024 * 1024) {
            flash_set('error', 'La imagen pesa demasiado (máximo 8 MB).');
            redirigir('/panel/onboarding/foto');
        }

        $nombreArchivo = 'menu-' . $negocio['id'] . '-' . time() . '.' . $tiposPermitidos[$mime];
        $destino = __DIR__ . '/../../public/uploads/menus/' . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            flash_set('error', 'No pudimos guardar la foto. Intenta de nuevo.');
            redirigir('/panel/onboarding/foto');
        }

        Sede::guardarFotoMenu((int) $negocio['id'], 'uploads/menus/' . $nombreArchivo);

        redirigir('/panel/onboarding/productos');
    }

    public function mostrarProductos(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (empty($negocio['menu_foto'])) {
            redirigir('/panel/onboarding/foto');
        }

        if ($negocio['tipo_negocio'] === 'reservas') {
            ver('onboarding/servicios', [
                'titulo'    => 'Revisa tus servicios · Veci',
                'negocio'   => $negocio,
                'servicios' => Servicio::listarPorSede((int) $negocio['id']),
                'volver'    => '/panel/onboarding/productos',
            ], 'onboarding');
            return;
        }

        ver('onboarding/productos', [
            'titulo'    => 'Revisa tu catálogo · Veci',
            'negocio'   => $negocio,
            'productos' => Producto::listarPorSede((int) $negocio['id']),
            'volver'    => '/panel/onboarding/productos',
        ], 'onboarding');
    }

    public function analizar(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (!csrf_verificar() || empty($negocio['menu_foto'])) {
            redirigir('/panel/onboarding/productos');
        }

        $sedeId = (int) $negocio['id'];
        $rutaImagen = __DIR__ . '/../../public/' . $negocio['menu_foto'];

        // Solo analiza si el catálogo todavía está vacío: evita duplicar
        // productos/servicios si el dueño recarga la página después de analizar.
        if ($negocio['tipo_negocio'] === 'reservas') {
            if (Servicio::contarPorSede($sedeId) === 0) {
                foreach (ExtractorMenu::extraerServicios($rutaImagen) as $servicio) {
                    Servicio::crear($sedeId, $servicio['nombre'], $servicio['precio'], $servicio['duracion_min']);
                }
            }
        } elseif (Producto::contarPorSede($sedeId) === 0) {
            foreach (ExtractorMenu::extraer($rutaImagen) as $producto) {
                Producto::crear($sedeId, $producto['nombre'], $producto['precio'], $producto['categoria']);
            }
        }

        redirigir('/panel/onboarding/productos');
    }

    public function mostrarHorario(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if ($negocio['tipo_negocio'] !== 'reservas') {
            redirigir('/panel/onboarding/pago');
        }
        if (Servicio::contarPorSede((int) $negocio['id']) === 0) {
            redirigir('/panel/onboarding/productos');
        }

        ver('onboarding/horario', [
            'titulo'  => 'Tu horario de atención · Veci',
            'negocio' => $negocio,
            'horario' => Sede::horario($negocio),
        ], 'onboarding');
    }

    public function guardarHorario(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (!csrf_verificar()) {
            redirigir('/panel/onboarding/horario');
        }

        Sede::guardarHorario(
            (int) $negocio['id'],
            Sede::horarioDesdePost($_POST),
            Sede::intervaloDesdePost($_POST)
        );

        redirigir('/panel/onboarding/pago');
    }

    public function mostrarPago(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $esReservas = $negocio['tipo_negocio'] === 'reservas';

        if ($esReservas) {
            if (Servicio::contarPorSede((int) $negocio['id']) === 0) {
                redirigir('/panel/onboarding/productos');
            }
            if (Sede::horario($negocio) === []) {
                redirigir('/panel/onboarding/horario');
            }
        } elseif (Producto::contarPorSede((int) $negocio['id']) === 0) {
            redirigir('/panel/onboarding/productos');
        }

        ver('onboarding/pago', [
            'titulo'         => 'Cómo cobras · Veci',
            'negocio'        => $negocio,
            'tieneAnticipos' => $esReservas && Servicio::tieneAnticipoActivo((int) $negocio['id']),
        ], 'onboarding');
    }

    public function publicar(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (!csrf_verificar()) {
            redirigir('/panel/onboarding/pago');
        }

        $tipo = $_POST['llave_tipo'] ?? 'celular';
        if (!in_array($tipo, ['celular', 'cedula', 'correo'], true)) {
            $tipo = 'celular';
        }
        $valor = trim((string) ($_POST['llave_valor'] ?? $negocio['whatsapp']));

        Sede::guardarLlaveBreB((int) $negocio['id'], $tipo, $valor);
        Sede::publicar((int) $negocio['id']);

        $sedeId = (int) $negocio['id'];
        $esReservas = $negocio['tipo_negocio'] === 'reservas';

        ver('onboarding/publicada', [
            'titulo'          => '¡Tienda publicada! · Veci',
            'negocio'         => Sede::buscarPorId($sedeId),
            'totalCatalogo'   => $esReservas ? Servicio::contarPorSede($sedeId) : Producto::contarPorSede($sedeId),
        ], 'onboarding');
    }
}
