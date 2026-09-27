<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Negocio;
use App\Models\Producto;
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
            'titulo'  => 'Foto del menú · Parroquia',
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

        Negocio::guardarFotoMenu((int) $negocio['id'], 'uploads/menus/' . $nombreArchivo);

        redirigir('/panel/onboarding/productos');
    }

    public function mostrarProductos(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (empty($negocio['menu_foto'])) {
            redirigir('/panel/onboarding/foto');
        }

        ver('onboarding/productos', [
            'titulo'    => 'Revisa tu catálogo · Parroquia',
            'negocio'   => $negocio,
            'productos' => Producto::listarPorNegocio((int) $negocio['id']),
            'volver'    => '/panel/onboarding/productos',
        ], 'onboarding');
    }

    public function analizar(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (!csrf_verificar() || empty($negocio['menu_foto'])) {
            redirigir('/panel/onboarding/productos');
        }

        // Solo analiza si el catálogo todavía está vacío: evita duplicar
        // productos si el dueño recarga la página después de analizar.
        if (Producto::contarPorNegocio((int) $negocio['id']) === 0) {
            $rutaImagen = __DIR__ . '/../../public/' . $negocio['menu_foto'];
            foreach (ExtractorMenu::extraer($rutaImagen) as $producto) {
                Producto::crear(
                    (int) $negocio['id'],
                    $producto['nombre'],
                    $producto['precio'],
                    $producto['categoria']
                );
            }
        }

        redirigir('/panel/onboarding/productos');
    }

    public function mostrarPago(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (Producto::contarPorNegocio((int) $negocio['id']) === 0) {
            redirigir('/panel/onboarding/productos');
        }

        ver('onboarding/pago', [
            'titulo'  => 'Cómo cobras · Parroquia',
            'negocio' => $negocio,
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

        Negocio::guardarLlaveBreB((int) $negocio['id'], $tipo, $valor);
        Negocio::publicar((int) $negocio['id']);

        ver('onboarding/publicada', [
            'titulo'  => '¡Tienda publicada! · Parroquia',
            'negocio' => Negocio::buscarPorId((int) $negocio['id']),
        ], 'onboarding');
    }
}
