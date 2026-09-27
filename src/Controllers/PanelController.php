<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Copiloto;
use App\Models\Negocio;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Servicio;

class PanelController
{
    public function dashboard(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $negocioId = (int) $negocio['id'];
        $esReservas = $negocio['tipo_negocio'] === 'reservas';

        ver('panel/dashboard', [
            'titulo'          => 'Panel · Veci',
            'activo'          => 'panel',
            'negocio'         => $negocio,
            'esReservas'      => $esReservas,
            'pedidosHoy'      => $esReservas ? Cita::contarHoy($negocioId) : Pedido::contarHoy($negocioId),
            'recompraPct'     => Copiloto::recompraMensualPct($negocioId, $negocio['tipo_negocio']),
            'aReactivar'      => count(Copiloto::clientesAReactivar($negocioId, $negocio['tipo_negocio'])),
            'ultimosPedidos'  => $esReservas ? [] : array_slice(Pedido::listarPorNegocio($negocioId), 0, 5),
            'proximasCitas'   => $esReservas ? array_slice(Cita::listarProximas($negocioId), 0, 5) : [],
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

    public function servicios(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/servicios', [
            'titulo'    => 'Tus servicios · Veci',
            'activo'    => 'servicios',
            'negocio'   => $negocio,
            'servicios' => Servicio::listarPorNegocio((int) $negocio['id']),
            'volver'    => '/panel/servicios',
        ], 'panel');
    }

    public function crearServicio(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (!csrf_verificar()) {
            redirigir($volver);
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $precio = (int) ($_POST['precio'] ?? 0);
        $duracion = (int) ($_POST['duracion_min'] ?? 30);

        if ($nombre !== '' && $precio > 0 && $duracion >= 5) {
            Servicio::crear((int) $negocio['id'], $nombre, $precio, $duracion);
        }

        redirigir($volver);
    }

    public function actualizarServicio(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (!csrf_verificar()) {
            redirigir($volver);
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $precio = (int) ($_POST['precio'] ?? 0);
        $duracion = (int) ($_POST['duracion_min'] ?? 30);

        if ($nombre !== '' && $precio > 0 && $duracion >= 5) {
            Servicio::actualizar((int) $parametros['id'], (int) $negocio['id'], $nombre, $precio, $duracion);
        }

        redirigir($volver);
    }

    public function eliminarServicio(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (csrf_verificar()) {
            Servicio::eliminar((int) $parametros['id'], (int) $negocio['id']);
        }

        redirigir($volver);
    }

    public function citas(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/citas', [
            'titulo'  => 'Agenda · Veci',
            'activo'  => 'citas',
            'negocio' => $negocio,
            'citas'   => Cita::listarProximas((int) $negocio['id']),
        ], 'panel');
    }

    public function cambiarEstadoCita(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $estado = (string) ($_POST['estado'] ?? '');
            Cita::actualizarEstado((int) $parametros['id'], (int) $negocio['id'], $estado);
        }

        redirigir('/panel/citas');
    }

    public function horario(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/horario', [
            'titulo'  => 'Horario de atención · Veci',
            'activo'  => 'horario',
            'negocio' => $negocio,
            'horario' => Negocio::horario($negocio),
            'ok'      => flash_obtener('ok'),
        ], 'panel');
    }

    public function guardarHorario(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            Negocio::guardarHorario(
                (int) $negocio['id'],
                Negocio::horarioDesdePost($_POST),
                Negocio::intervaloDesdePost($_POST)
            );
            flash_set('ok', 'Horario actualizado.');
        }

        redirigir('/panel/horario');
    }

    public function copiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $negocioId = (int) $negocio['id'];
        $esReservas = $negocio['tipo_negocio'] === 'reservas';

        ver('panel/copiloto', [
            'titulo'      => 'Copiloto de recompra · Veci',
            'activo'      => 'copiloto',
            'negocio'     => $negocio,
            'lista'       => Copiloto::clientesAReactivar($negocioId, $negocio['tipo_negocio']),
            'pedidosHoy'  => $esReservas ? Cita::contarHoy($negocioId) : Pedido::contarHoy($negocioId),
            'recompraPct' => Copiloto::recompraMensualPct($negocioId, $negocio['tipo_negocio']),
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
