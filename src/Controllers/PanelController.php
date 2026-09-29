<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Copiloto;
use App\Models\Empleado;
use App\Models\FechaBloqueada;
use App\Models\Negocio;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Servicio;
use App\Services\RecordatorioWhatsapp;

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

    public function exportarPedidosCsv(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $pedidos = Pedido::listarPorNegocio((int) $negocio['id'], 100000);

        $salida = $this->abrirDescargaCsv('pedidos');
        fputcsv($salida, ['ID', 'Fecha', 'Cliente', 'Teléfono', 'Total', 'Método de pago', 'Estado']);
        foreach ($pedidos as $pedido) {
            fputcsv($salida, [
                $pedido['id'],
                $pedido['creado_en'],
                $pedido['cliente_nombre'],
                $pedido['cliente_telefono'],
                $pedido['total'],
                $pedido['metodo_pago'],
                $pedido['estado'],
            ]);
        }
        fclose($salida);
        exit;
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

    public function alternarAgotadoProducto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (csrf_verificar()) {
            Producto::alternarAgotado((int) $parametros['id'], (int) $negocio['id']);
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

    public function alternarAgotadoServicio(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (csrf_verificar()) {
            Servicio::alternarAgotado((int) $parametros['id'], (int) $negocio['id']);
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

    public function exportarCitasCsv(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $citas = Cita::listarPorNegocio((int) $negocio['id'], 100000);

        $salida = $this->abrirDescargaCsv('citas');
        fputcsv($salida, ['ID', 'Fecha y hora', 'Cliente', 'Teléfono', 'Servicio', 'Empleado', 'Precio', 'Duración (min)', 'Estado']);
        foreach ($citas as $cita) {
            fputcsv($salida, [
                $cita['id'],
                $cita['fecha_hora'],
                $cita['cliente_nombre'],
                $cita['cliente_telefono'],
                $cita['nombre_servicio'],
                $cita['empleado_nombre'] ?? '',
                $cita['precio'],
                $cita['duracion_min'],
                $cita['estado'],
            ]);
        }
        fclose($salida);
        exit;
    }

    public function exportarClientesCsv(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $clientes = Cliente::listarPorNegocio((int) $negocio['id']);

        $salida = $this->abrirDescargaCsv('clientes');
        fputcsv($salida, ['ID', 'Nombre', 'Teléfono', 'Autorizó datos', 'Cliente desde']);
        foreach ($clientes as $cliente) {
            fputcsv($salida, [
                $cliente['id'],
                $cliente['nombre'],
                $cliente['telefono'],
                ((int) $cliente['autorizo_datos'] === 1) ? 'Sí' : 'No',
                $cliente['creado_en'],
            ]);
        }
        fclose($salida);
        exit;
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

    public function empleados(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/empleados', [
            'titulo'    => 'Empleados · Veci',
            'activo'    => 'empleados',
            'negocio'   => $negocio,
            'empleados' => Empleado::listarPorNegocio((int) $negocio['id']),
        ], 'panel');
    }

    public function crearEmpleado(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            if ($nombre !== '') {
                Empleado::crear((int) $negocio['id'], $nombre);
            }
        }

        redirigir('/panel/empleados');
    }

    public function eliminarEmpleado(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            Empleado::eliminar((int) $parametros['id'], (int) $negocio['id']);
        }

        redirigir('/panel/empleados');
    }

    public function fechasBloqueadas(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/fechas_bloqueadas', [
            'titulo'    => 'Días no disponibles · Veci',
            'activo'    => 'fechas_bloqueadas',
            'negocio'   => $negocio,
            'fechas'    => FechaBloqueada::listarPorNegocio((int) $negocio['id']),
        ], 'panel');
    }

    public function crearFechaBloqueada(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $fecha = (string) ($_POST['fecha'] ?? '');
            $motivo = trim((string) ($_POST['motivo'] ?? '')) ?: null;

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && $fecha >= date('Y-m-d')) {
                FechaBloqueada::crear((int) $negocio['id'], $fecha, $motivo);
            }
        }

        redirigir('/panel/horario/fechas');
    }

    public function eliminarFechaBloqueada(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            FechaBloqueada::eliminar((int) $parametros['id'], (int) $negocio['id']);
        }

        redirigir('/panel/horario/fechas');
    }

    /**
     * Polling ligero para avisar al dueño de pedidos/citas nuevas mientras
     * tiene el panel abierto (Notification API del navegador + sonido).
     * Sin WhatsApp Business API ni Web Push configurados, esto solo funciona
     * con la pestaña abierta; no llega si el navegador está cerrado.
     */
    public function notificacionesNuevas(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $negocioId = (int) $negocio['id'];

        $desdePedido = (int) ($_GET['desde_pedido'] ?? 0);
        $desdeCita = (int) ($_GET['desde_cita'] ?? 0);

        $pedidos = $negocio['tipo_negocio'] === 'pedidos' ? Pedido::nuevosDesde($negocioId, $desdePedido) : [];
        $citas = $negocio['tipo_negocio'] === 'reservas' ? Cita::nuevasDesde($negocioId, $desdeCita) : [];

        header('Content-Type: application/json');
        echo json_encode([
            'pedidos' => array_map(fn ($p) => [
                'id'     => (int) $p['id'],
                'texto'  => $p['cliente_nombre'] . ' · ' . pesos((int) $p['total']),
            ], $pedidos),
            'citas' => array_map(fn ($c) => [
                'id'    => (int) $c['id'],
                'texto' => $c['cliente_nombre'] . ' · ' . $c['nombre_servicio'] . ' · ' . date('d M g:i a', strtotime((string) $c['fecha_hora'])),
            ], $citas),
        ]);
        exit;
    }

    public function recordatorios(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/recordatorios', [
            'titulo'        => 'Recordatorios de cita · Veci',
            'activo'        => 'recordatorios',
            'negocio'       => $negocio,
            'citas'         => Cita::pendientesDeRecordatorio((int) $negocio['id']),
            'apiConectada'  => RecordatorioWhatsapp::disponible(),
            'ok'            => flash_obtener('ok'),
        ], 'panel');
    }

    public function mensajeRecordatorio(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $cita = Cita::buscar((int) $parametros['cita'], (int) $negocio['id']);

        if ($cita === null) {
            redirigir('/panel/recordatorios');
        }

        $mensaje = RecordatorioWhatsapp::mensajeRecordatorio($cita);
        $telefonoWa = preg_replace('/\D+/', '', (string) $cita['cliente_telefono']);
        $enlaceWhatsapp = 'https://wa.me/57' . $telefonoWa . '?text=' . rawurlencode($mensaje);

        ver('panel/recordatorio_mensaje', [
            'titulo'         => 'Mensaje de recordatorio · Veci',
            'activo'         => 'recordatorios',
            'negocio'        => $negocio,
            'cita'           => $cita,
            'mensaje'        => $mensaje,
            'enlaceWhatsapp' => $enlaceWhatsapp,
        ], 'panel');
    }

    public function registrarEnvioRecordatorio(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $cita = Cita::buscar((int) $parametros['cita'], (int) $negocio['id']);
            if ($cita !== null) {
                Cita::marcarRecordatorioEnviado((int) $cita['id'], (int) $negocio['id']);
                flash_set('ok', 'Recordatorio marcado como enviado a ' . $cita['cliente_nombre'] . '.');
            }
        }

        redirigir('/panel/recordatorios');
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

    /** Envía las cabeceras de descarga y devuelve el stream donde escribir las filas del CSV. */
    private function abrirDescargaCsv(string $nombreBase)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreBase . '-' . date('Y-m-d') . '.csv"');

        $salida = fopen('php://output', 'w');
        fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel abra los acentos bien.

        return $salida;
    }
}
