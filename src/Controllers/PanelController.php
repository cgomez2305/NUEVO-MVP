<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cliente;
use App\Models\Copiloto;
use App\Models\Pedido;
use App\Models\Producto;

class PanelController
{
    public function dashboard(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $negocioId = (int) $negocio['id'];

        ver('panel/dashboard', [
            'titulo'          => 'Panel · Veci',
            'activo'          => 'panel',
            'negocio'         => $negocio,
            'pedidosHoy'      => Pedido::contarHoy($negocioId),
            'recompraPct'     => Copiloto::recompraMensualPct($negocioId),
            'aReactivar'      => count(Copiloto::clientesAReactivar($negocioId)),
            'ultimosPedidos'  => array_slice(Pedido::listarPorNegocio($negocioId), 0, 5),
        ], 'panel');
    }

    public function pedidos(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/pedidos', [
            'titulo'  => 'Pedidos · Veci',
            'activo'  => 'pedidos',
            'negocio' => $negocio,
            'pedidos' => Pedido::listarPorNegocio((int) $negocio['id']),
        ], 'panel');
    }

    public function cambiarEstadoPedido(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $estado = (string) ($_POST['estado'] ?? '');
            Pedido::actualizarEstado((int) $parametros['id'], (int) $negocio['id'], $estado);
        }

        redirigir('/panel/pedidos');
    }

    public function productos(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/productos', [
            'titulo'    => 'Tu menú · Veci',
            'activo'    => 'productos',
            'negocio'   => $negocio,
            'productos' => Producto::listarPorNegocio((int) $negocio['id']),
            'volver'    => '/panel/productos',
        ], 'panel');
    }

    public function crearProducto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (!csrf_verificar()) {
            redirigir($volver);
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $precio = (int) ($_POST['precio'] ?? 0);
        $categoria = trim((string) ($_POST['categoria'] ?? '')) ?: 'General';

        if ($nombre !== '' && $precio > 0) {
            Producto::crear((int) $negocio['id'], $nombre, $precio, $categoria);
        }

        redirigir($volver);
    }

    public function actualizarProducto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (!csrf_verificar()) {
            redirigir($volver);
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $precio = (int) ($_POST['precio'] ?? 0);
        $categoria = trim((string) ($_POST['categoria'] ?? '')) ?: 'General';

        if ($nombre !== '' && $precio > 0) {
            Producto::actualizar((int) $parametros['id'], (int) $negocio['id'], $nombre, $precio, $categoria);
        }

        redirigir($volver);
    }

    public function eliminarProducto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (csrf_verificar()) {
            Producto::eliminar((int) $parametros['id'], (int) $negocio['id']);
        }

        redirigir($volver);
    }

    public function copiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/copiloto', [
            'titulo'      => 'Copiloto de recompra · Veci',
            'activo'      => 'copiloto',
            'negocio'     => $negocio,
            'lista'       => Copiloto::clientesAReactivar((int) $negocio['id']),
            'pedidosHoy'  => Pedido::contarHoy((int) $negocio['id']),
            'recompraPct' => Copiloto::recompraMensualPct((int) $negocio['id']),
        ], 'panel');
    }

    public function mensajeCopiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $cliente = Cliente::buscar((int) $parametros['cliente'], (int) $negocio['id']);

        if ($cliente === null) {
            redirigir('/panel/copiloto');
        }

        $mensaje = Copiloto::mensajeSugerido($cliente);
        $telefonoWa = preg_replace('/\D+/', '', (string) $cliente['telefono']);
        $enlaceWhatsapp = 'https://wa.me/57' . $telefonoWa . '?text=' . rawurlencode($mensaje);

        ver('panel/copiloto_mensaje', [
            'titulo'         => 'Mensaje sugerido · Veci',
            'activo'         => 'copiloto',
            'negocio'        => $negocio,
            'cliente'        => $cliente,
            'mensaje'        => $mensaje,
            'enlaceWhatsapp' => $enlaceWhatsapp,
        ], 'panel');
    }

    public function registrarEnvioCopiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $cliente = Cliente::buscar((int) $parametros['cliente'], (int) $negocio['id']);
            if ($cliente !== null) {
                Copiloto::registrarEnvio(
                    (int) $negocio['id'],
                    (int) $cliente['id'],
                    Copiloto::mensajeSugerido($cliente)
                );
                flash_set('ok', 'Mensaje marcado como enviado a ' . $cliente['nombre'] . '.');
            }
        }

        redirigir('/panel/copiloto');
    }

    /** Solo deja volver a rutas propias del panel, nunca a una URL externa. */
    private function destinoSeguro(mixed $ruta): string
    {
        if (!is_string($ruta) || !str_starts_with($ruta, '/panel/')) {
            return '/panel/productos';
        }
        return $ruta;
    }
}
