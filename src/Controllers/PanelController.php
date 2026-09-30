<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Copiloto;
use App\Models\Empleado;
use App\Models\FechaBloqueada;
use App\Models\ListaEspera;
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
            'ventasHoy'       => $esReservas ? Cita::ventasHoy($sedeId) : Pedido::ventasHoy($sedeId),
            'recompraPct'     => Copiloto::recompraMensualPct($negocioId, $negocio['tipo_negocio']),
            'aReactivar'      => count(Copiloto::clientesAReactivar($negocioId, $negocio['tipo_negocio'])),
            'ultimosPedidos'  => $esReservas ? [] : array_slice(Pedido::listarPorSede($sedeId), 0, 5),
            'proximasCitas'   => $esReservas ? array_slice(Cita::listarProximas($sedeId), 0, 5) : [],
            'listaEsperaCount' => $esReservas ? ListaEspera::contarPendientesPorSede($sedeId) : 0,
        ], 'panel');
    }

    public function pedidos(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $sedeId = (int) $negocio['id'];

        $filtro = (string) ($_GET['estado'] ?? '');
        if (!in_array($filtro, Pedido::ESTADOS, true)) {
            $filtro = '';
        }
        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $rango = (string) ($_GET['rango'] ?? '');
        $desdePersonalizado = (string) ($_GET['desde'] ?? '');
        $hastaPersonalizado = (string) ($_GET['hasta'] ?? '');
        [$desde, $hasta] = $this->rangoFechasPedidos($rango, $desdePersonalizado, $hastaPersonalizado);

        $porPagina = 20;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

        $resultado = Pedido::buscarPorSede($sedeId, $filtro, $busqueda, $desde, $hasta, $pagina, $porPagina);
        $totalPaginas = max(1, (int) ceil($resultado['total'] / $porPagina));
        if ($pagina > $totalPaginas) {
            $pagina = $totalPaginas;
            $resultado = Pedido::buscarPorSede($sedeId, $filtro, $busqueda, $desde, $hasta, $pagina, $porPagina);
        }

        ver('panel/pedidos', [
            'titulo'   => 'Pedidos · Veci',
            'activo'   => 'pedidos',
            'negocio'  => $negocio,
            'activos'  => Pedido::listarActivosPorSede($sedeId),
            'conteosPorEstado' => Pedido::conteosPorEstado($sedeId),
            'filtro'   => $filtro,
            'busqueda' => $busqueda,
            'rango'    => $rango,
            'desdePersonalizado' => $desdePersonalizado,
            'hastaPersonalizado' => $hastaPersonalizado,
            'historial'      => $resultado['filas'],
            'historialTotal' => $resultado['total'],
            'historialSuma'  => $resultado['suma'],
            'pagina'       => $pagina,
            'totalPaginas' => $totalPaginas,
        ], 'panel');
    }

    /**
     * Traduce el filtro de fecha del historial (hoy/7 días/mes/mes
     * pasado/personalizado) a un rango [desde, hasta] en formato DATETIME
     * para la consulta. Ambos null significa "sin límite de fecha".
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function rangoFechasPedidos(string $rango, string $desdePersonalizado, string $hastaPersonalizado): array
    {
        return match ($rango) {
            'hoy' => [date('Y-m-d 00:00:00'), null],
            '7dias' => [date('Y-m-d 00:00:00', strtotime('-6 days')), null],
            'mes' => [date('Y-m-01 00:00:00'), null],
            'mes_pasado' => [
                date('Y-m-01 00:00:00', strtotime('first day of last month')),
                date('Y-m-t 23:59:59', strtotime('last day of last month')),
            ],
            'personalizado' => [
                $desdePersonalizado !== '' ? $desdePersonalizado . ' 00:00:00' : null,
                $hastaPersonalizado !== '' ? $hastaPersonalizado . ' 23:59:59' : null,
            ],
            default => [null, null],
        };
    }

    public function cambiarEstadoPedido(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volverPost = $_POST['volver'] ?? null;
        $volver = is_string($volverPost) && str_starts_with($volverPost, '/panel/pedidos') ? $volverPost : '/panel/pedidos';

        if (csrf_verificar()) {
            $estado = (string) ($_POST['estado'] ?? '');
            Pedido::actualizarEstado((int) $parametros['id'], (int) $negocio['id'], $estado);
        }

        redirigir($volver);
    }

    /** Detalle completo de un pedido: ítems, entrega, notas y acciones menos frecuentes. */
    public function detallePedido(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $pedido = Pedido::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($pedido === null) {
            redirigir('/panel/pedidos');
        }

        ver('panel/pedido_detalle', [
            'titulo'  => "Pedido #{$pedido['id']} · Veci",
            'activo'  => 'pedidos',
            'negocio' => $negocio,
            'pedido'  => $pedido,
            'items'   => Pedido::items((int) $pedido['id']),
            'siguientePaso' => Pedido::siguientePaso($pedido),
        ], 'panel');
    }

    /** Exporta exactamente lo que el filtro actual del historial está mostrando, no todo el histórico a ciegas. */
    public function exportarPedidosCsv(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        $filtro = (string) ($_GET['estado'] ?? '');
        if (!in_array($filtro, Pedido::ESTADOS, true)) {
            $filtro = '';
        }
        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $rango = (string) ($_GET['rango'] ?? '');
        [$desde, $hasta] = $this->rangoFechasPedidos($rango, (string) ($_GET['desde'] ?? ''), (string) ($_GET['hasta'] ?? ''));

        $resultado = Pedido::buscarPorSede((int) $negocio['id'], $filtro, $busqueda, $desde, $hasta, 1, null);
        $pedidos = $resultado['filas'];

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
        $sedeId = (int) $negocio['id'];

        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $categoria = trim((string) ($_GET['categoria'] ?? ''));
        $disponibilidad = (string) ($_GET['disponibilidad'] ?? '');
        if (!in_array($disponibilidad, ['disponibles', 'agotados'], true)) {
            $disponibilidad = '';
        }
        $orden = (string) ($_GET['orden'] ?? 'nombre');
        if (!in_array($orden, ['nombre', 'precio', 'recientes'], true)) {
            $orden = 'nombre';
        }

        $productos = Producto::buscarPorSede($sedeId, $busqueda, $categoria, $disponibilidad, $orden);
        $porCategoria = [];
        foreach ($productos as $producto) {
            $porCategoria[$producto['categoria']][] = $producto;
        }

        ver('panel/productos', [
            'titulo'         => 'Tu menú · Veci',
            'activo'         => 'productos',
            'negocio'        => $negocio,
            'porCategoria'   => $porCategoria,
            'totalProductos' => Producto::contarPorSede($sedeId),
            'categorias'     => Producto::categoriasPorSede($sedeId),
            'busqueda'       => $busqueda,
            'filtroCategoria' => $categoria,
            'disponibilidad' => $disponibilidad,
            'orden'          => $orden,
            'ok'             => flash_obtener('ok'),
        ], 'panel');
    }

    public function nuevoProducto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        ver('panel/producto_form', [
            'titulo'     => 'Nuevo producto · Veci',
            'activo'     => 'productos',
            'negocio'    => $negocio,
            'producto'   => null,
            'categorias' => Producto::categoriasPorSede((int) $negocio['id']),
        ], 'panel');
    }

    public function editarProducto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $producto = Producto::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($producto === null) {
            redirigir('/panel/productos');
        }

        ver('panel/producto_form', [
            'titulo'     => "Editar {$producto['nombre']} · Veci",
            'activo'     => 'productos',
            'negocio'    => $negocio,
            'producto'   => $producto,
            'categorias' => Producto::categoriasPorSede((int) $negocio['id']),
        ], 'panel');
    }

    /** El <select> de categoría manda '__otra__' cuando el dueño escribió una categoría nueva en vez de elegir una existente. */
    private function categoriaDelFormulario(): string
    {
        $categoria = trim((string) ($_POST['categoria'] ?? ''));
        if ($categoria === '__otra__') {
            $categoria = trim((string) ($_POST['categoria_otra'] ?? ''));
        }
        return $categoria !== '' ? mb_substr($categoria, 0, 60) : 'General';
    }

    public function crearProducto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (!csrf_verificar()) {
            redirigir($volver);
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $precio = dinero_desde_texto((string) ($_POST['precio'] ?? ''));
        $categoria = $this->categoriaDelFormulario();
        $descripcion = mb_substr(trim((string) ($_POST['descripcion'] ?? '')), 0, 160);

        if ($nombre !== '' && $precio > 0) {
            $id = Producto::crear((int) $negocio['id'], $nombre, $precio, $categoria, $descripcion);
            $imagen = $this->subirImagenProducto();
            if ($imagen !== null) {
                Producto::actualizarImagen($id, (int) $negocio['id'], $imagen);
            }
            if (!isset($_POST['disponible'])) {
                Producto::establecerAgotado($id, (int) $negocio['id'], true);
            }
            if (!isset($_POST['visible'])) {
                Producto::establecerActivo($id, (int) $negocio['id'], false);
            }
            flash_set('ok', "«{$nombre}» se agregó a tu catálogo.");
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
        $precio = dinero_desde_texto((string) ($_POST['precio'] ?? ''));
        $categoria = $this->categoriaDelFormulario();
        $descripcion = mb_substr(trim((string) ($_POST['descripcion'] ?? '')), 0, 160);

        if ($nombre !== '' && $precio > 0) {
            $id = (int) $parametros['id'];
            $sedeId = (int) $negocio['id'];
            Producto::actualizar($id, $sedeId, $nombre, $precio, $categoria, $descripcion);
            Producto::establecerAgotado($id, $sedeId, !isset($_POST['disponible']));
            Producto::establecerActivo($id, $sedeId, isset($_POST['visible']));
            if (!empty($_POST['quitar_imagen'])) {
                Producto::eliminarImagen($id, $sedeId);
            }
            $imagen = $this->subirImagenProducto();
            if ($imagen !== null) {
                Producto::actualizarImagen($id, $sedeId, $imagen);
            }
            flash_set('ok', "«{$nombre}» se actualizó.");
        }

        redirigir($volver);
    }

    /**
     * Sube la foto opcional de un producto (mismo criterio de validación que
     * la foto de menú del onboarding). Devuelve la ruta relativa a guardar,
     * o null si no venía ningún archivo (no es un error: la foto es opcional).
     */
    private function subirImagenProducto(): ?string
    {
        $archivo = $_FILES['imagen'] ?? null;
        if ($archivo === null || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = mime_content_type($archivo['tmp_name']) ?: '';
        if (!isset($tiposPermitidos[$mime]) || $archivo['size'] > 5 * 1024 * 1024) {
            return null;
        }

        $nombreArchivo = 'producto-' . bin2hex(random_bytes(8)) . '.' . $tiposPermitidos[$mime];
        $destino = __DIR__ . '/../../public/uploads/productos/' . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            return null;
        }

        return 'uploads/productos/' . $nombreArchivo;
    }

    public function eliminarProducto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (csrf_verificar()) {
            Producto::eliminar((int) $parametros['id'], (int) $negocio['id']);
            flash_set('ok', 'Producto eliminado.');
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

    /** "Visible en la tienda": distinto de agotado — esto lo saca por completo del catálogo público. */
    public function alternarActivoProducto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (csrf_verificar()) {
            Producto::alternarActivo((int) $parametros['id'], (int) $negocio['id']);
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
            'titulo'      => 'Agenda · Veci',
            'activo'      => 'citas',
            'negocio'     => $negocio,
            'citas'       => Cita::listarProximas((int) $negocio['id']),
            'listaEspera' => ListaEspera::listarPorSede((int) $negocio['id']),
            'ok'          => flash_obtener('ok'),
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

    public function marcarContactadoListaEspera(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            ListaEspera::marcarContactado((int) $parametros['id'], (int) $negocio['id']);
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
            'titulo'          => 'Copiloto de recompra · Veci',
            'activo'          => 'copiloto',
            'negocio'         => $negocio,
            'lista'           => $lista,
            'filtro'          => $filtro,
            'conteos'         => $conteos,
            'aReactivarCount' => $conteos['inactivo'],
            'vipCount'        => $conteos['vip'],
            'recompraPct'     => Copiloto::recompraMensualPct($negocioId, $negocio['tipo_negocio']),
        ], 'panel');
    }

    public function mensajeCopiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $negocioId = (int) $negocio['negocio_id'];
        $cliente = Cliente::buscar((int) $parametros['cliente'], $negocioId);

        if ($cliente === null) {
            redirigir('/panel/copiloto');
        }

        $segmento = $this->segmentoValido($_GET['segmento'] ?? null);
        $descuento = $this->descuentoValido($_GET['descuento'] ?? null);
        $mensaje = Copiloto::mensajeSugerido($cliente, $segmento, $descuento);
        $telefonoWa = preg_replace('/\D+/', '', (string) $cliente['telefono']);

        $clienteId = (int) $cliente['id'];
        $contacto = Copiloto::ultimoContacto($negocioId, $clienteId);
        $comproDespues = $contacto !== null
            ? Copiloto::comproDespuesDe($negocioId, $clienteId, $contacto['fecha'], $negocio['tipo_negocio'])
            : false;

        ver('panel/copiloto_mensaje', [
            'titulo'         => 'Mensaje sugerido · Veci',
            'activo'         => 'copiloto',
            'negocio'        => $negocio,
            'cliente'        => $cliente,
            'segmento'       => $segmento,
            'descuento'      => $descuento,
            'mensaje'        => $mensaje,
            'waBase'         => 'https://wa.me/57' . $telefonoWa,
            'contexto'       => Copiloto::contextoCliente($negocioId, $clienteId, $negocio['tipo_negocio']),
            'contacto'       => $contacto,
            'comproDespues'  => $comproDespues,
        ], 'panel');
    }

    public function registrarEnvioCopiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $segmento = $this->segmentoValido($_POST['segmento'] ?? null);

        if (csrf_verificar()) {
            $cliente = Cliente::buscar((int) $parametros['cliente'], (int) $negocio['negocio_id']);
            if ($cliente !== null) {
                $descuento = $this->descuentoValido($_POST['descuento'] ?? null);
                Copiloto::registrarEnvio(
                    (int) $negocio['negocio_id'],
                    (int) $cliente['id'],
                    Copiloto::mensajeSugerido($cliente, $segmento, $descuento)
                );
                flash_set('ok', 'Quedó registrado el contacto con ' . $cliente['nombre'] . ' hoy.');
            }
        }

        redirigir('/panel/copiloto?segmento=' . $segmento);
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

    /** El copiloto nunca inventa un descuento por su cuenta: solo usa el que la persona eligió explícitamente en el selector. */
    private function descuentoValido(mixed $descuento): int
    {
        $pct = (int) $descuento;
        return in_array($pct, [0, 5, 10, 15], true) ? $pct : 0;
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
