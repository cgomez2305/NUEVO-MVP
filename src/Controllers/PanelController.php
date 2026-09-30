<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Copiloto;
use App\Models\Empleado;
use App\Models\FechaBloqueada;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\PushSubscripcion;
use App\Models\Sede;
use App\Models\Servicio;
use App\Models\Usuario;
use App\Services\RecordatorioWhatsapp;

/**
 * $negocio, en todos los métodos de abajo, es el contexto que devuelve
 * Auth::exigirSesion(): la SEDE activa de la sesión (con id = sede_id),
 * más tres llaves extra fundidas en el mismo arreglo: negocio_id (la
 * marca, para todo lo que es de negocio entero: clientes, copiloto,
 * sedes, colaboradores), usuario_id (para push) y rol ('dueno' o
 * 'colaborador'). Todo lo que antes se guardaba "por negocio" (catálogo,
 * horario, pedidos, citas, empleados, fechas bloqueadas) ahora es "por
 * sede", así que $negocio['id'] sigue siendo la llave correcta para esas
 * consultas. Donde se necesita el negocio real (marca) se usa
 * $negocio['negocio_id'] explícitamente.
 */
class PanelController
{
    public function dashboard(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $sedeId = (int) $negocio['id'];
        $negocioId = (int) $negocio['negocio_id'];
        $esReservas = $negocio['tipo_negocio'] === 'reservas';

        ver('panel/dashboard', [
            'titulo'          => 'Panel · Veci',
            'activo'          => 'panel',
            'negocio'         => $negocio,
            'esReservas'      => $esReservas,
            'pedidosHoy'      => $esReservas ? Cita::contarHoy($sedeId) : Pedido::contarHoy($sedeId),
            'recompraPct'     => Copiloto::recompraMensualPct($negocioId, $negocio['tipo_negocio']),
            'aReactivar'      => count(Copiloto::clientesAReactivar($negocioId, $negocio['tipo_negocio'])),
            'ultimosPedidos'  => $esReservas ? [] : array_slice(Pedido::listarPorSede($sedeId), 0, 5),
            'proximasCitas'   => $esReservas ? array_slice(Cita::listarProximas($sedeId), 0, 5) : [],
        ], 'panel');
    }

    public function pedidos(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $todos = Pedido::listarPorSede((int) $negocio['id']);

        $conteos = [];
        foreach ($todos as $pedido) {
            $conteos[$pedido['estado']] = ($conteos[$pedido['estado']] ?? 0) + 1;
        }

        $filtro = (string) ($_GET['estado'] ?? '');
        $estadosValidos = ['pendiente', 'pagado', 'en_cocina', 'en_camino', 'entregado', 'cancelado'];
        if (!in_array($filtro, $estadosValidos, true)) {
            $filtro = '';
        }
        $pedidos = $filtro === '' ? $todos : array_values(array_filter($todos, fn ($p) => $p['estado'] === $filtro));

        ver('panel/pedidos', [
            'titulo'  => 'Pedidos · Veci',
            'activo'  => 'pedidos',
            'negocio' => $negocio,
            'pedidos' => $pedidos,
            'todos'   => $todos,
            'total'   => count($todos),
            'conteos' => $conteos,
            'filtro'  => $filtro,
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
        Auth::exigirDueno($negocio);
        $pedidos = Pedido::listarPorSede((int) $negocio['id'], 100000);

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
            'productos' => Producto::listarPorSede((int) $negocio['id']),
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
            'servicios' => Servicio::listarPorSede((int) $negocio['id']),
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

    public function actualizarDepositoServicio(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (csrf_verificar()) {
            $tipo = (string) ($_POST['deposito_tipo'] ?? 'ninguno');
            $valor = (int) ($_POST['deposito_valor'] ?? 0);
            Servicio::actualizarDeposito((int) $parametros['id'], (int) $negocio['id'], $tipo, $valor);
        }

        redirigir($volver);
    }

    public function marcarAnticipoPagado(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            Cita::marcarAnticipoPagado((int) $parametros['id'], (int) $negocio['id']);
            flash_set('ok', 'Anticipo marcado como pagado.');
        }

        redirigir('/panel/citas');
    }

    public function citas(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/citas', [
            'titulo'  => 'Agenda · Veci',
            'activo'  => 'citas',
            'negocio' => $negocio,
            'citas'   => Cita::listarProximas((int) $negocio['id']),
            'ok'      => flash_obtener('ok'),
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
        Auth::exigirDueno($negocio);
        $citas = Cita::listarPorSede((int) $negocio['id'], 100000);

        $salida = $this->abrirDescargaCsv('citas');
        fputcsv($salida, ['ID', 'Fecha y hora', 'Cliente', 'Teléfono', 'Servicio', 'Empleado', 'Precio', 'Duración (min)', 'Estado', 'Anticipo', 'Estado anticipo']);
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
                $cita['anticipo_monto'],
                $cita['anticipo_estado'],
            ]);
        }
        fclose($salida);
        exit;
    }

    public function exportarClientesCsv(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $clientes = Cliente::listarPorNegocio((int) $negocio['negocio_id']);

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
        Auth::exigirDueno($negocio);

        ver('panel/horario', [
            'titulo'  => 'Horario de atención · Veci',
            'activo'  => 'horario',
            'negocio' => $negocio,
            'horario' => Sede::horario($negocio),
            'ok'      => flash_obtener('ok'),
        ], 'panel');
    }

    public function guardarHorario(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        if (csrf_verificar()) {
            Sede::guardarHorario(
                (int) $negocio['id'],
                Sede::horarioDesdePost($_POST),
                Sede::intervaloDesdePost($_POST)
            );
            flash_set('ok', 'Horario actualizado.');
        }

        redirigir('/panel/horario');
    }

    public function empleados(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        ver('panel/empleados', [
            'titulo'    => 'Empleados · Veci',
            'activo'    => 'empleados',
            'negocio'   => $negocio,
            'empleados' => Empleado::listarPorSede((int) $negocio['id']),
        ], 'panel');
    }

    public function crearEmpleado(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

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
        Auth::exigirDueno($negocio);

        if (csrf_verificar()) {
            Empleado::eliminar((int) $parametros['id'], (int) $negocio['id']);
        }

        redirigir('/panel/empleados');
    }

    public function fechasBloqueadas(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        ver('panel/fechas_bloqueadas', [
            'titulo'  => 'Días no disponibles · Veci',
            'activo'  => 'fechas_bloqueadas',
            'negocio' => $negocio,
            'fechas'  => FechaBloqueada::listarPorSede((int) $negocio['id']),
        ], 'panel');
    }

    public function crearFechaBloqueada(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

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
        Auth::exigirDueno($negocio);

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
        $sedeId = (int) $negocio['id'];

        $desdePedido = (int) ($_GET['desde_pedido'] ?? 0);
        $desdeCita = (int) ($_GET['desde_cita'] ?? 0);

        $pedidos = $negocio['tipo_negocio'] === 'pedidos' ? Pedido::nuevosDesde($sedeId, $desdePedido) : [];
        $citas = $negocio['tipo_negocio'] === 'reservas' ? Cita::nuevasDesde($sedeId, $desdeCita) : [];

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

    /** Llave pública VAPID para que el JS del panel arme la suscripción push. Null si no está configurada. */
    public function pushClavePublica(array $parametros): void
    {
        Auth::exigirSesion();

        header('Content-Type: application/json');
        $clave = config('push_vapid.public_key');
        echo json_encode(['clave' => is_string($clave) && $clave !== '' ? $clave : null]);
        exit;
    }

    public function pushSuscribir(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $endpoint = (string) ($_POST['endpoint'] ?? '');
            $p256dh = (string) ($_POST['p256dh'] ?? '');
            $auth = (string) ($_POST['auth'] ?? '');

            if ($endpoint !== '' && $p256dh !== '' && $auth !== '') {
                PushSubscripcion::guardar((int) $negocio['usuario_id'], $endpoint, $p256dh, $auth);
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    public function pushDesuscribir(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $endpoint = (string) ($_POST['endpoint'] ?? '');
            if ($endpoint !== '') {
                PushSubscripcion::eliminarPorEndpointYUsuario($endpoint, (int) $negocio['usuario_id']);
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    public function recordatorios(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/recordatorios', [
            'titulo'       => 'Recordatorios de cita · Veci',
            'activo'       => 'recordatorios',
            'negocio'      => $negocio,
            'citas'        => Cita::pendientesDeRecordatorio((int) $negocio['id']),
            'apiConectada' => RecordatorioWhatsapp::disponible(),
            'ok'           => flash_obtener('ok'),
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
        Auth::exigirDueno($negocio);
        $negocioId = (int) $negocio['negocio_id'];
        $esReservas = $negocio['tipo_negocio'] === 'reservas';

        $segmentos = Copiloto::segmentar($negocioId, $negocio['tipo_negocio']);
        $conteos = ['inactivo' => 0, 'vip' => 0, 'nuevo' => 0, 'recurrente' => 0];
        foreach ($segmentos as $fila) {
            foreach ($fila['tags'] as $tag) {
                $conteos[$tag]++;
            }
        }

        $filtro = (string) ($_GET['segmento'] ?? 'inactivo');
        if (!array_key_exists($filtro, $conteos) && $filtro !== 'todos') {
            $filtro = 'inactivo';
        }

        $lista = $filtro === 'todos'
            ? $segmentos
            : array_values(array_filter($segmentos, fn ($fila) => in_array($filtro, $fila['tags'], true)));

        ver('panel/copiloto', [
            'titulo'      => 'Copiloto de recompra · Veci',
            'activo'      => 'copiloto',
            'negocio'     => $negocio,
            'lista'       => $lista,
            'filtro'      => $filtro,
            'conteos'     => $conteos,
            'pedidosHoy'  => $esReservas ? Cita::contarHoy((int) $negocio['id']) : Pedido::contarHoy((int) $negocio['id']),
            'recompraPct' => Copiloto::recompraMensualPct($negocioId, $negocio['tipo_negocio']),
        ], 'panel');
    }

    public function mensajeCopiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $cliente = Cliente::buscar((int) $parametros['cliente'], (int) $negocio['negocio_id']);

        if ($cliente === null) {
            redirigir('/panel/copiloto');
        }

        $segmento = $this->segmentoValido($_GET['segmento'] ?? null);
        $mensaje = Copiloto::mensajeSugerido($cliente, $segmento);
        $telefonoWa = preg_replace('/\D+/', '', (string) $cliente['telefono']);
        $enlaceWhatsapp = 'https://wa.me/57' . $telefonoWa . '?text=' . rawurlencode($mensaje);

        ver('panel/copiloto_mensaje', [
            'titulo'         => 'Mensaje sugerido · Veci',
            'activo'         => 'copiloto',
            'negocio'        => $negocio,
            'cliente'        => $cliente,
            'segmento'       => $segmento,
            'mensaje'        => $mensaje,
            'enlaceWhatsapp' => $enlaceWhatsapp,
        ], 'panel');
    }

    public function registrarEnvioCopiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        if (csrf_verificar()) {
            $cliente = Cliente::buscar((int) $parametros['cliente'], (int) $negocio['negocio_id']);
            if ($cliente !== null) {
                $segmento = $this->segmentoValido($_POST['segmento'] ?? null);
                Copiloto::registrarEnvio(
                    (int) $negocio['negocio_id'],
                    (int) $cliente['id'],
                    Copiloto::mensajeSugerido($cliente, $segmento)
                );
                flash_set('ok', 'Mensaje marcado como enviado a ' . $cliente['nombre'] . '.');
            }
        }

        redirigir('/panel/copiloto');
    }

    /** Todas las sedes del negocio, con el botón para cambiar de una a otra. Cualquier rol puede entrar. */
    public function sedes(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $todas = Sede::listarPorNegocio((int) $negocio['negocio_id']);

        $sedesVisibles = $negocio['rol'] === 'dueno'
            ? $todas
            : array_values(array_filter($todas, fn ($s) => in_array((int) $s['id'], Usuario::sedeIdsAsignadas((int) $negocio['usuario_id']), true)));

        ver('panel/sedes', [
            'titulo'  => 'Sedes · Veci',
            'activo'  => 'sedes',
            'negocio' => $negocio,
            'sedes'   => $sedesVisibles,
            'ok'      => flash_obtener('ok'),
        ], 'panel');
    }

    public function cambiarSede(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $sedeId = (int) ($_POST['sede_id'] ?? 0);
            Auth::cambiarSede($sedeId);
        }

        redirigir($this->destinoSeguro($_POST['volver'] ?? null));
    }

    public function crearSede(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        if (csrf_verificar()) {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $whatsapp = preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? '')) ?? '';

            if ($nombre !== '' && $whatsapp !== '') {
                $nuevaSedeId = Sede::crear((int) $negocio['negocio_id'], $nombre, $whatsapp);
                Auth::cambiarSede($nuevaSedeId);
                flash_set('ok', 'Sede creada. Termina de configurarla: catálogo, horario y Bre-B.');
            }
        }

        redirigir('/panel/sedes');
    }

    /** Colaboradores del negocio y a qué sedes tiene acceso cada uno. Solo el dueño. */
    public function colaboradores(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        $colaboradores = Usuario::listarColaboradores((int) $negocio['negocio_id']);
        foreach ($colaboradores as &$colaborador) {
            $colaborador['sede_ids'] = Usuario::sedeIdsAsignadas((int) $colaborador['id']);
        }
        unset($colaborador);

        ver('panel/colaboradores', [
            'titulo'        => 'Colaboradores · Veci',
            'activo'        => 'colaboradores',
            'negocio'       => $negocio,
            'colaboradores' => $colaboradores,
            'sedes'         => Sede::listarPorNegocio((int) $negocio['negocio_id']),
            'ok'            => flash_obtener('ok'),
            'error'         => flash_obtener('error'),
        ], 'panel');
    }

    public function crearColaborador(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        if (csrf_verificar()) {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $whatsapp = preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? '')) ?? '';
            $password = (string) ($_POST['password'] ?? '');
            $sedeIds = array_map('intval', (array) ($_POST['sedes'] ?? []));

            if ($nombre === '' || $whatsapp === '' || strlen($password) < 6 || $sedeIds === []) {
                flash_set('error', 'Completa nombre, WhatsApp, una contraseña de al menos 6 caracteres y elige al menos una sede.');
                redirigir('/panel/colaboradores');
            }

            if (Usuario::buscarPorWhatsapp($whatsapp) !== null) {
                flash_set('error', 'Ya existe una cuenta con ese número de WhatsApp.');
                redirigir('/panel/colaboradores');
            }

            // Solo deja asignar sedes que en verdad son de este negocio.
            $sedesDelNegocio = array_column(Sede::listarPorNegocio((int) $negocio['negocio_id']), 'id');
            $sedeIds = array_values(array_intersect($sedeIds, array_map('intval', $sedesDelNegocio)));

            $colaboradorId = Usuario::crear((int) $negocio['negocio_id'], $nombre, $whatsapp, $password, 'colaborador');
            Usuario::asignarSedes($colaboradorId, $sedeIds);
            flash_set('ok', 'Colaborador creado.');
        }

        redirigir('/panel/colaboradores');
    }

    public function actualizarSedesColaborador(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        if (csrf_verificar()) {
            $colaborador = Usuario::buscarPorIdYNegocio((int) $parametros['id'], (int) $negocio['negocio_id']);
            if ($colaborador !== null && $colaborador['rol'] === 'colaborador') {
                $sedesDelNegocio = array_column(Sede::listarPorNegocio((int) $negocio['negocio_id']), 'id');
                $sedeIds = array_map('intval', (array) ($_POST['sedes'] ?? []));
                $sedeIds = array_values(array_intersect($sedeIds, array_map('intval', $sedesDelNegocio)));
                Usuario::asignarSedes((int) $colaborador['id'], $sedeIds);
                flash_set('ok', 'Sedes actualizadas para ' . $colaborador['nombre'] . '.');
            }
        }

        redirigir('/panel/colaboradores');
    }

    public function eliminarColaborador(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        if (csrf_verificar()) {
            Usuario::eliminar((int) $parametros['id'], (int) $negocio['negocio_id']);
        }

        redirigir('/panel/colaboradores');
    }

    /** Correo (para recuperar contraseña) y cambio de contraseña del usuario de la sesión. Cualquier rol puede entrar. */
    public function cuenta(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $usuario = Usuario::buscarPorId((int) $negocio['usuario_id']);

        ver('panel/cuenta', [
            'titulo'  => 'Mi cuenta · Veci',
            'activo'  => 'cuenta',
            'negocio' => $negocio,
            'usuario' => $usuario,
            'ok'      => flash_obtener('ok'),
            'error'   => flash_obtener('error'),
        ], 'panel');
    }

    public function actualizarCorreo(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $correo = trim((string) ($_POST['correo'] ?? ''));

            if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                flash_set('error', 'Ese correo no es válido.');
                redirigir('/panel/cuenta');
            }

            if (Usuario::guardarCorreo((int) $negocio['usuario_id'], $correo === '' ? null : $correo)) {
                flash_set('ok', $correo === '' ? 'Correo eliminado de tu cuenta.' : 'Correo guardado. Ya puedes recuperar tu contraseña con él.');
            } else {
                flash_set('error', 'Ese correo ya está en uso por otra cuenta.');
            }
        }

        redirigir('/panel/cuenta');
    }

    public function actualizarPasswordCuenta(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $actual = (string) ($_POST['password_actual'] ?? '');
            $nueva = (string) ($_POST['password_nueva'] ?? '');
            $usuario = Usuario::buscarPorId((int) $negocio['usuario_id']);

            if ($usuario === null || !password_verify($actual, $usuario['password_hash'])) {
                flash_set('error', 'Tu contraseña actual no coincide.');
            } elseif (strlen($nueva) < 6) {
                flash_set('error', 'La contraseña nueva debe tener al menos 6 caracteres.');
            } else {
                Usuario::cambiarPassword((int) $negocio['usuario_id'], $nueva);
                flash_set('ok', 'Contraseña actualizada.');
            }
        }

        redirigir('/panel/cuenta');
    }

    /** Derecho de eliminación de datos (habeas data): borra al cliente y todo su historial. Solo el dueño. */
    public function eliminarCliente(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        if (csrf_verificar()) {
            $cliente = Cliente::buscar((int) $parametros['cliente'], (int) $negocio['negocio_id']);
            if ($cliente !== null) {
                Cliente::eliminar((int) $cliente['id'], (int) $negocio['negocio_id']);
                flash_set('ok', 'Se eliminaron los datos de ' . $cliente['nombre'] . ' y todo su historial.');
            }
        }

        redirigir('/panel/copiloto');
    }

    /** Valida el segmento recibido por GET/POST antes de usarlo para elegir plantilla de mensaje. */
    private function segmentoValido(mixed $segmento): string
    {
        return in_array($segmento, ['vip', 'nuevo', 'inactivo', 'recurrente'], true) ? $segmento : 'inactivo';
    }

    /** Solo deja volver a rutas propias del panel, nunca a una URL externa. */
    private function destinoSeguro(mixed $ruta): string
    {
        if (!is_string($ruta) || ($ruta !== '/panel' && !str_starts_with($ruta, '/panel/'))) {
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
