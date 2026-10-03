<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Consentimiento;
use App\Models\Copiloto;
use App\Models\Cupon;
use App\Models\Empleado;
use App\Models\FechaBloqueada;
use App\Models\Imprevisto;
use App\Models\Fidelidad;
use App\Models\ListaEspera;
use App\Models\Negocio;
use App\Models\PagoPlan;
use App\Models\Pedido;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\PushSubscripcion;
use App\Models\Sede;
use App\Models\Servicio;
use App\Models\UsoIA;
use App\Models\Usuario;
use App\Models\Venta;
use App\Services\AvisoEstado;
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

        // null = plan sin límite (Barrio/Pro); solo Gratis lo tiene, así que
        // solo ahí vale la pena contar cuánto se lleva usado este mes.
        $limitePedidosMes = $negocio['limite_pedidos_mes'] ?? null;
        $usadosEsteMes = $limitePedidosMes !== null
            ? ($esReservas ? Cita::contarEsteMesPorNegocio($negocioId) : Pedido::contarEsteMesPorNegocio($negocioId))
            : null;

        $aReactivar = count(Copiloto::clientesAReactivar($negocioId, $negocio['tipo_negocio']));
        $esDueno = ($negocio['rol'] ?? '') === 'dueno';

        ver('panel/dashboard', [
            'titulo'          => 'Panel · Veci',
            'activo'          => 'panel',
            'negocio'         => $negocio,
            'esReservas'      => $esReservas,
            // Acción primero: lo que hay que hacer ahora, antes que las cifras.
            'tareas'          => \App\Models\Hoy::tareas(
                $negocio,
                $aReactivar,
                $esReservas && $esDueno && Copiloto::disponiblePara($negocio) ? count(\App\Models\Huecos::paraManana($negocio)) : 0
            ),
            'recuperado'      => $esDueno && Copiloto::disponiblePara($negocio) ? Copiloto::recuperadoEsteMes($negocioId, $negocio['tipo_negocio']) : null,
            'pedidosHoy'      => $esReservas ? Cita::contarHoy($sedeId) : Pedido::contarHoy($sedeId),
            'ventasHoy'       => $esReservas ? Cita::ventasHoy($sedeId) : Pedido::ventasHoy($sedeId),
            'recompraPct'     => Copiloto::recompraMensualPct($negocioId, $negocio['tipo_negocio']),
            'aReactivar'      => $aReactivar,
            'ultimosPedidos'  => $esReservas ? [] : array_slice(Pedido::listarPorSede($sedeId), 0, 5),
            'proximasCitas'   => $esReservas ? array_slice(Cita::listarProximas($sedeId), 0, 5) : [],
            'listaEsperaCount' => $esReservas ? ListaEspera::contarPendientesPorSede($sedeId) : 0,
            'resumenSemana'   => $esReservas ? Cita::resumenSemana($sedeId) : Pedido::resumenSemana($sedeId),
            'limitePedidosMes' => $limitePedidosMes,
            'usadosEsteMes'    => $usadosEsteMes,
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

        // Gratis y Barrio solo ven los últimos 30 días de historial (Pro trae
        // el histórico completo, ver planes.incluye_estadisticas_completas).
        // Se recorta cualquier filtro que pida más atrás, nunca se oculta
        // en silencio: la vista avisa cuando esto recortó lo que se pidió.
        $historialLimitado = !($negocio['incluye_estadisticas_completas'] ?? false);
        if ($historialLimitado) {
            $limiteDesde = (new \DateTimeImmutable('-30 days midnight'))->format('Y-m-d H:i:s');
            if ($desde === null || $desde < $limiteDesde) {
                $desde = $limiteDesde;
            }
        }

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
            'porPagina'    => $porPagina,
            'historialLimitado' => $historialLimitado,
            'ok'             => flash_obtener('ok'),
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
            try {
                Pedido::actualizarEstado((int) $parametros['id'], (int) $negocio['id'], $estado);
            } catch (\DomainException $e) {
                flash_set('error', $e->getMessage());
                redirigir($volver);
            }
            // Con la API de WhatsApp configurada, el cliente se entera solo;
            // si no, el panel le ofrece al dueño "Avisarle" con el texto listo.
            $pedido = Pedido::buscar((int) $parametros['id'], (int) $negocio['id']);
            if ($pedido !== null && AvisoEstado::automatico('pedido', $pedido, $negocio)) {
                flash_set('ok', 'Le avisamos a ' . $pedido['cliente_nombre'] . ' por WhatsApp.');
            }
        }

        redirigir($volver);
    }

    /** "Avisarle": marca el aviso como dado y abre WhatsApp con el texto del estado actual. */
    public function avisarPedido(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $pedido = Pedido::buscar((int) $parametros['id'], (int) $negocio['id']);
        $texto = $pedido !== null ? AvisoEstado::texto('pedido', $pedido, $negocio) : null;
        if ($texto === null || !csrf_verificar()) {
            redirigir('/panel/pedidos');
        }
        AvisoEstado::marcar('pedido', (int) $pedido['id'], (string) $pedido['estado']);
        header('Location: ' . AvisoEstado::enlace($pedido, $texto));
        exit;
    }

    public function avisarCita(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $cita = Cita::buscar((int) $parametros['id'], (int) $negocio['id']);
        $texto = $cita !== null ? AvisoEstado::texto('cita', $cita, $negocio) : null;
        if ($texto === null || !csrf_verificar()) {
            redirigir('/panel/citas');
        }
        AvisoEstado::marcar('cita', (int) $cita['id'], (string) $cita['estado']);
        header('Location: ' . AvisoEstado::enlace($cita, $texto));
        exit;
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
            'premio'  => $this->premioPendiente($negocio, (int) $pedido['cliente_id']),
            'ok'      => flash_obtener('ok'),
            'error'   => flash_obtener('error'),
        ], 'panel');
    }

    /**
     * Si el cliente completó la tarjeta de sellos, lo que le toca: así quien
     * despacha el pedido lo ve y lo entrega ahí mismo.
     *
     * @return array{premio: string, meta: int}|null
     */
    private function premioPendiente(array $negocio, int $clienteId): ?array
    {
        $config = Fidelidad::config((int) $negocio['negocio_id']);
        if ($config === null || Fidelidad::sellosDe((int) $negocio['negocio_id'], $config, $clienteId) < (int) $config['meta']) {
            return null;
        }

        return ['premio' => (string) $config['premio'], 'meta' => (int) $config['meta']];
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
        $this->escribirFilaCsv($salida, ['ID', 'Fecha', 'Cliente', 'Teléfono', 'Total', 'Método de pago', 'Estado']);
        foreach ($pedidos as $pedido) {
            $this->escribirFilaCsv($salida, [
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
            'masVendidos'    => Producto::masPedidos($sedeId, 5, 1),
            // Tiendas (fase 4): ganancia real de 30 días (vacío si no hay datos suficientes).
            'masDeja'        => Venta::loQueMasDeja($sedeId),
            'error'          => flash_obtener('error'),
            'nombresPorId'   => array_column(Producto::listarPorSede($sedeId), 'nombre', 'id'),
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
            'partesPosibles' => $this->partesPosiblesDeCombo((int) $negocio['id'], null),
            // Tiendas (fase 4): "Crear un producto con este código" desde el mostrador.
            'codigoInicial'  => Producto::normalizarCodigo((string) ($_GET['codigo'] ?? '')),
            'error'          => flash_obtener('error'),
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
            'partesPosibles' => $this->partesPosiblesDeCombo((int) $negocio['id'], (int) $producto['id']),
            'error'      => flash_obtener('error'),
        ], 'panel');
    }

    /**
     * Productos que pueden ir dentro de un combo: los de la sede que no son
     * combos ni el producto que se está editando. Un combo de combos se
     * vuelve imposible de explicar en la tienda.
     *
     * @return array<int, array<string, mixed>>
     */
    private function partesPosiblesDeCombo(int $sedeId, ?int $productoId): array
    {
        // Lo que va por peso no entra a combos (el combo tiene precio fijo y
        // el peso no); si un combo viejo ya lo trae, se sigue mostrando.
        $yaEnEste = $productoId !== null ? array_map('intval', array_column(Producto::componentesPorCombo($sedeId)[$productoId] ?? [], 'id')) : [];

        return array_values(array_filter(
            Producto::listarPorSede($sedeId),
            fn ($p) => (int) $p['id'] !== $productoId && $p['combo'] === []
                && (!Producto::esPorPeso($p) || in_array((int) $p['id'], $yaEnEste, true))
        ));
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
        if ($nombre === '' || $precio <= 0) {
            flash_set('error', 'Escribe el nombre y un precio mayor que $0: no se guardó nada.');
            redirigir('/panel/productos/nuevo');
        }

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
            $aviso = $this->guardarDatosTienda($id, (int) $negocio['id']);
            Producto::establecerStock($id, (int) $negocio['id'], $this->stockDelFormulario());
            Producto::guardarComponentes($id, (int) $negocio['id'], (array) ($_POST['combo'] ?? []));
            flash_set('ok', "«{$nombre}» se agregó a tu catálogo.");
            if ($aviso !== null) {
                flash_set('error', $aviso);
            }
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
        if ($nombre === '' || $precio <= 0) {
            flash_set('error', 'Escribe el nombre y un precio mayor que $0: no se guardaron los cambios.');
            redirigir('/panel/productos/' . (int) $parametros['id'] . '/editar');
        }

        if ($nombre !== '' && $precio > 0) {
            $id = (int) $parametros['id'];
            $sedeId = (int) $negocio['id'];
            // Tiendas (fase 4): pasar de unidad a peso (o al revés) cambia en qué
            // se cuenta el inventario (unidades o gramos). Si las existencias no
            // se volvieron a escribir, "2,5" kilos quedaría como 25 unidades: no
            // se guarda nada y se pide escribirlas en la unidad nueva.
            $actual = Producto::buscar($id, $sedeId);
            if ($actual === null) {
                redirigir('/panel/productos');
            }
            $vendePorNuevo = ($_POST['vende_por'] ?? 'unidad') === 'peso' ? 'peso' : 'unidad';
            if ($actual !== null && $actual['stock'] !== null && $actual['combo'] === [] && $actual['vende_por'] !== $vendePorNuevo
                && in_array(trim((string) ($_POST['stock'] ?? '')), ['', trim((string) ($_POST['stock_antes'] ?? ''))], true)) {
                flash_set('error', 'Cambiaste cómo vendes «' . $actual['nombre'] . '»: vuelve a escribir cuántos hay, ahora en '
                    . ($vendePorNuevo === 'peso' ? 'kilos' : 'unidades') . '. No se guardó ningún cambio.');
                redirigir('/panel/productos/' . $id . '/editar#stock');
            }
            Producto::actualizar($id, $sedeId, $nombre, $precio, $categoria, $descripcion);
            Producto::establecerAgotado($id, $sedeId, !isset($_POST['disponible']));
            Producto::establecerActivo($id, $sedeId, isset($_POST['visible']));
            $aviso = $this->guardarDatosTienda($id, $sedeId);
            Producto::establecerStock($id, $sedeId, $this->stockDelFormulario());
            // Solo si el formulario trae la sección: un producto que ya está
            // dentro de un combo no la muestra y no debe perder nada.
            if (isset($_POST['combo_presente'])) {
                Producto::guardarComponentes($id, $sedeId, (array) ($_POST['combo'] ?? []));
            }
            if (!empty($_POST['quitar_imagen'])) {
                Producto::eliminarImagen($id, $sedeId);
            }
            $imagen = $this->subirImagenProducto();
            if ($imagen !== null) {
                Producto::actualizarImagen($id, $sedeId, $imagen);
            }
            flash_set('ok', "«{$nombre}» se actualizó.");
            if ($aviso !== null) {
                flash_set('error', $aviso);
            }
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

        // Re-codificada como JPEG (sin EXIF/GPS ni bytes extra), como las
        // fotos del equipo: nunca se publica el archivo tal como llegó.
        return \App\Services\Subida::imagen($archivo, 'productos', 'producto');
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

    /** "Se acabó por hoy": mañana vuelve a estar disponible solo. */
    public function agotarHoyProducto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = $this->destinoSeguro($_POST['volver'] ?? null);

        if (csrf_verificar()) {
            Producto::agotarPorHoy((int) $parametros['id'], (int) $negocio['id']);
            flash_set('ok', 'Agotado por hoy: mañana vuelve a estar disponible solo.');
        }

        redirigir($volver);
    }

    /**
     * Unidades del formulario: vacío = no llevar inventario de este producto.
     * Por peso se escriben kilos ("2,5") y se guardan gramos (2500).
     */
    private function stockDelFormulario(): ?int
    {
        $texto = trim((string) ($_POST['stock'] ?? ''));
        if ($texto === '') {
            return null;
        }
        if (($_POST['vende_por'] ?? '') === 'peso') {
            $kilos = (float) str_replace(',', '.', (string) preg_replace('/[^\d,.]/', '', $texto));

            return max(0, (int) round($kilos * Producto::GRAMOS_POR_KILO));
        }

        return max(0, (int) preg_replace('/\D+/', '', $texto));
    }

    /**
     * Tiendas (fase 4): código de barras, costo y si se vende por unidad o
     * por peso. Devuelve un aviso si el código ya lo tenía otro producto.
     */
    private function guardarDatosTienda(int $id, int $sedeId): ?string
    {
        $textoCodigo = trim((string) ($_POST['codigo_barras'] ?? ''));
        $codigo = Producto::normalizarCodigo($textoCodigo);
        if ($textoCodigo !== '' && $codigo === null) {
            // Un código mal escrito no borra el que ya tenía.
            $codigo = Producto::buscar($id, $sedeId)['codigo_barras'] ?? null;
        }
        $textoCosto = trim((string) ($_POST['costo'] ?? ''));
        $aviso = Producto::guardarDatosTienda(
            $id,
            $sedeId,
            $codigo,
            $textoCosto === '' ? null : dinero_desde_texto($textoCosto),
            (string) ($_POST['vende_por'] ?? 'unidad')
        );
        if ($textoCodigo !== '' && $codigo === null) {
            return 'El código «' . mb_substr($textoCodigo, 0, 40) . '» no se guardó: solo números, letras, puntos o guiones (de 3 a 32).';
        }

        return $aviso;
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
            'duraciones' => Imprevisto::duracionesReales((int) $negocio['id']),
            'adicionales' => \App\Models\Adicional::listar((int) $negocio['id']),
            'volver'    => '/panel/servicios',
            'ok'        => flash_obtener('ok'),
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
        // Igual que en productos: el campo llega como "20.000" (data-precio-cop
        // y el valor precargado), y (int) "20.000" es 20, no 20000.
        $precio = dinero_desde_texto((string) ($_POST['precio'] ?? ''));
        $duracion = (int) ($_POST['duracion_min'] ?? 30);

        if ($nombre !== '' && $precio > 0 && $duracion >= 5) {
            $servicioId = Servicio::crear((int) $negocio['id'], $nombre, $precio, $duracion);
            if (isset($_POST['precio_tipo'])) {
                Servicio::guardarTipoPrecio($servicioId, (int) $negocio['id'], (string) $_POST['precio_tipo'], dinero_desde_texto((string) ($_POST['precio_max'] ?? '')));
            }
            flash_set('ok', "«{$nombre}» se agregó a tu lista.");
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
        $precio = dinero_desde_texto((string) ($_POST['precio'] ?? ''));
        $duracion = (int) ($_POST['duracion_min'] ?? 30);

        if ($nombre !== '' && $precio > 0 && $duracion >= 5) {
            Servicio::actualizar((int) $parametros['id'], (int) $negocio['id'], $nombre, $precio, $duracion);
            if (isset($_POST['repetir_cada_meses'])) {
                Servicio::guardarRepetir((int) $parametros['id'], (int) $negocio['id'], (int) $_POST['repetir_cada_meses']);
            }
            if (isset($_POST['precio_tipo'])) {
                $tipoGuardado = Servicio::guardarTipoPrecio((int) $parametros['id'], (int) $negocio['id'], (string) $_POST['precio_tipo'], dinero_desde_texto((string) ($_POST['precio_max'] ?? '')));
                $rangoInvalido = $tipoGuardado !== $_POST['precio_tipo'];
            }

            // El panel guarda servicio y anticipo con un solo botón. El anticipo
            // es plata del negocio: solo el dueño lo cambia (igual que en
            // actualizarDepositoServicio); a un colaborador se le ignora.
            if (isset($_POST['deposito_tipo']) && $negocio['rol'] === 'dueno') {
                Servicio::actualizarDeposito(
                    (int) $parametros['id'],
                    (int) $negocio['id'],
                    (string) $_POST['deposito_tipo'],
                    dinero_desde_texto((string) ($_POST['deposito_valor'] ?? ''))
                );
            }
            if ($volver === '/panel/servicios') {
                flash_set('ok', !empty($rangoInvalido)
                    ? 'Guardado como «Desde»: para un rango, el valor «hasta» tiene que ser mayor al precio.'
                    : 'Servicio actualizado.'); // el onboarding no muestra avisos
            }
        }

        redirigir($volver);
    }

    public function eliminarServicio(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        // Borrar un servicio se lleva sus paquetes y adicionales: decide el dueño.
        Auth::exigirDueno($negocio);
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
            'porAvisar'   => Imprevisto::porAvisar((int) $negocio['id']),
            'retrasoHoy'  => Imprevisto::retrasoDeHoy((int) $negocio['id']),
            'atendidas'   => Cita::completadasRecientes((int) $negocio['id']),
            'equipo'      => Empleado::listarPorSede((int) $negocio['id'], true),
            'ok'          => flash_obtener('ok'),
            'error'       => flash_obtener('error'),
        ], 'panel');
    }

    public function cambiarEstadoCita(array $parametros): void
    {
        $negocio = Auth::exigirSesion();

        if (csrf_verificar()) {
            $estado = (string) ($_POST['estado'] ?? '');
            // "No vino" aplica la regla del anticipo (ver Imprevisto::noAsistio).
            if ($estado === 'no_asistio') {
                (new AgendaController())->noVino($parametros);
            }
            // Salir de "No vino" (fue un error) deshace el abono y la sesión devuelta.
            $antes = Cita::buscar((int) $parametros['id'], (int) $negocio['id']);
            if ($antes !== null && $antes['estado'] === 'no_asistio' && in_array($estado, Cita::ESTADOS, true)) {
                if (!Imprevisto::deshacerNoAsistio($antes)) {
                    flash_set('error', 'No se puede cambiar: el cliente ya usó el cupón de su anticipo o la sesión de su bono.');
                    redirigir('/panel/citas');
                }
            }
            Cita::actualizarEstado((int) $parametros['id'], (int) $negocio['id'], $estado);
            $cita = Cita::buscar((int) $parametros['id'], (int) $negocio['id']);
            if ($cita !== null && AvisoEstado::automatico('cita', $cita, $negocio)) {
                flash_set('ok', 'Le avisamos a ' . $cita['cliente_nombre'] . ' por WhatsApp.');
            }
        }

        redirigir(destino_agenda());
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
        $this->escribirFilaCsv($salida, ['ID', 'Fecha y hora', 'Cliente', 'Teléfono', 'Servicio', 'Empleado', 'Precio reservado', 'Descuento', 'Valor (cobrado)', 'Duración (min)', 'Estado', 'Anticipo', 'Estado anticipo']);
        foreach ($citas as $cita) {
            $this->escribirFilaCsv($salida, [
                $cita['id'],
                $cita['fecha_hora'],
                $cita['cliente_nombre'],
                $cita['cliente_telefono'],
                $cita['nombre_servicio'],
                $cita['empleado_nombre'] ?? '',
                $cita['precio'],
                $cita['descuento'],
                in_array($cita['estado'], Cita::ESTADOS_SIN_VENTA, true) ? 0 : Cita::valor($cita),
                $cita['duracion_min'],
                Cita::ETIQUETAS[$cita['estado']] ?? $cita['estado'],
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
        // "Acepta promociones" va en el archivo: quien exporta la lista para
        // escribir por fuera de Veci tiene que saber a quién sí puede.
        $this->escribirFilaCsv($salida, ['ID', 'Nombre', 'Teléfono', 'Autorizó datos', 'Acepta promociones', 'Promociones desde', 'Cliente desde']);
        foreach ($clientes as $cliente) {
            $acepta = (int) ($cliente['acepta_marketing'] ?? 0) === 1;
            $this->escribirFilaCsv($salida, [
                $cliente['id'],
                $cliente['nombre'],
                $cliente['telefono'],
                ((int) $cliente['autorizo_datos'] === 1) ? 'Sí' : 'No',
                $acepta ? 'Sí' : 'No',
                $acepta ? (string) ($cliente['marketing_actualizado_en'] ?? '') : '',
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
            'error'   => flash_obtener('error'),
        ], 'panel');
    }

    public function guardarHorario(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        if (csrf_verificar()) {
            $avisos = [];
            Sede::guardarHorario(
                (int) $negocio['id'],
                Sede::horarioDesdePost($_POST, $avisos),
                Sede::intervaloDesdePost($_POST)
            );
            $aviso = aviso_pausas_invalidas($avisos);
            if ($aviso !== null) {
                flash_set('error', $aviso);
            } else {
                flash_set('ok', 'Horario actualizado.');
            }
        }

        redirigir('/panel/horario');
    }

    public function fechasBloqueadas(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        ver('panel/fechas_bloqueadas', [
            'titulo'  => 'Días no disponibles · Veci',
            'activo'  => 'horario',
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
                'texto' => $c['cliente_nombre'] . ' · ' . $c['nombre_servicio'] . ' · ' . fecha_corta((string) $c['fecha_hora'], ', '),
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
            $endpoint = is_string($_POST['endpoint'] ?? null) ? $_POST['endpoint'] : '';
            $p256dh = is_string($_POST['p256dh'] ?? null) ? $_POST['p256dh'] : '';
            $auth = is_string($_POST['auth'] ?? null) ? $_POST['auth'] : '';

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

        $mensaje = RecordatorioWhatsapp::mensajeRecordatorio($cita, $negocio);
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
        $this->exigirCopiloto($negocio);
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

        $sinPermiso = count(array_filter($lista, fn ($fila) => !$fila['contactable']));

        ver('panel/copiloto', [
            'titulo'          => 'Copiloto de recompra · Veci',
            'sinPermiso'      => $sinPermiso,
            'recuperado'      => Copiloto::recuperadoEsteMes($negocioId, $negocio['tipo_negocio']),
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
        $this->exigirCopiloto($negocio);
        $negocioId = (int) $negocio['negocio_id'];
        $cliente = Cliente::buscar((int) $parametros['cliente'], $negocioId);

        if ($cliente === null) {
            redirigir('/panel/copiloto');
        }

        $segmento = $this->segmentoValido($_GET['segmento'] ?? null);
        $this->exigirPermisoPromociones($cliente, $segmento);
        $descuento = $this->descuentoValido($_GET['descuento'] ?? null);
        $cupon = $descuento > 0 ? $this->cuponCopiloto($negocioId, (int) $cliente['id'], $descuento) : null;
        $mensaje = Copiloto::mensajeSugerido($cliente, $segmento, $descuento, $cupon['codigo'] ?? null, $cupon['vence_en'] ?? null, $this->enlacePreferencias($cliente));
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
            'cupon'          => $cupon,
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
        $this->exigirCopiloto($negocio);
        $segmento = $this->segmentoValido($_POST['segmento'] ?? null);

        if (csrf_verificar()) {
            $cliente = Cliente::buscar((int) $parametros['cliente'], (int) $negocio['negocio_id']);
            if ($cliente !== null) {
                $this->exigirPermisoPromociones($cliente, $segmento);
                $descuento = $this->descuentoValido($_POST['descuento'] ?? null);
                // "Ya le escribí" también vale si copió el texto en vez de
                // usar el botón de WhatsApp: el cupón que nombra tiene que existir.
                $cupon = $descuento > 0 ? $this->crearCuponCopiloto((int) $negocio['negocio_id'], (int) $cliente['id'], $descuento) : null;
                Copiloto::registrarEnvio(
                    (int) $negocio['negocio_id'],
                    (int) $cliente['id'],
                    Copiloto::mensajeSugerido($cliente, $segmento, $descuento, $cupon['codigo'] ?? null, $cupon['vence_en'] ?? null, $this->enlacePreferencias($cliente))
                );
                flash_set('ok', 'Quedó registrado el contacto con ' . $cliente['nombre'] . ' hoy.');
            }
        }

        redirigir('/panel/copiloto?segmento=' . $segmento);
    }

    /**
     * El botón "Abrir WhatsApp" pasa por aquí antes de ir a wa.me: con
     * descuento, crea el cupón personal que el mensaje nombra (no antes: ver
     * otra versión o cambiar el % no deja cupones sueltos) y luego abre el
     * chat con el texto tal como el dueño lo dejó.
     */
    public function abrirWhatsappCopiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $this->exigirCopiloto($negocio);
        $negocioId = (int) $negocio['negocio_id'];
        $cliente = Cliente::buscar((int) $parametros['cliente'], $negocioId);

        if ($cliente === null || !csrf_verificar()) {
            redirigir('/panel/copiloto');
        }
        $this->exigirPermisoPromociones($cliente, 'inactivo');

        $descuento = $this->descuentoValido($_POST['descuento'] ?? null);
        if ($descuento > 0) {
            $this->crearCuponCopiloto($negocioId, (int) $cliente['id'], $descuento);
        }
        $texto = mb_substr(trim((string) ($_POST['text'] ?? '')), 0, 700);
        $telefonoWa = preg_replace('/\D+/', '', (string) $cliente['telefono']);
        // Abrir el chat con el mensaje ya es el contacto: así "lo que Veci
        // ayudó a recuperar" no depende de acordarse de tocar "Ya le escribí".
        Copiloto::registrarEnvio($negocioId, (int) $cliente['id'], $texto);

        header('Location: https://wa.me/57' . $telefonoWa . '?text=' . rawurlencode($texto));
        exit;
    }

    /**
     * A quien no autorizó promociones solo se le puede pedir permiso, una
     * vez: abre WhatsApp con la pregunta y su enlace para activarlo él
     * mismo (así la autorización queda con su IP y la versión de la
     * política, no "porque el negocio dijo").
     */
    public function pedirPermisoCopiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $this->exigirCopiloto($negocio);
        $negocioId = (int) $negocio['negocio_id'];
        $cliente = Cliente::buscar((int) $parametros['cliente'], $negocioId);
        if ($cliente === null || !csrf_verificar() || empty($cliente['telefono'])) {
            redirigir('/panel/copiloto');
        }
        if (Cliente::contactable($cliente)) {
            redirigir('/panel/copiloto/' . (int) $cliente['id'] . '/mensaje');
        }
        if (!Cliente::marcarPermisoPedido((int) $cliente['id'], $negocioId)) {
            flash_set('error', 'Ya le pediste permiso a ' . $cliente['nombre'] . '. Si no lo activó, no se le insiste.');
            redirigir('/panel/copiloto?segmento=todos');
        }
        $texto = Copiloto::mensajePermiso($cliente, (string) ($negocio['negocio_nombre'] ?? $negocio['nombre']), $this->enlacePreferencias($cliente));
        $telefonoWa = preg_replace('/\D+/', '', (string) $cliente['telefono']);

        header('Location: https://wa.me/57' . $telefonoWa . '?text=' . rawurlencode($texto));
        exit;
    }

    /** Llenar huecos: los espacios libres de mañana y a quién ya le toca volver (solo reservas). */
    public function huecosCopiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $this->exigirCopiloto($negocio);
        if ($negocio['tipo_negocio'] !== 'reservas') {
            redirigir('/panel/copiloto');
        }

        ver('panel/huecos', [
            'titulo'  => 'Llenar huecos de mañana · Veci',
            'activo'  => 'copiloto',
            'negocio' => $negocio,
            'huecos'  => \App\Models\Huecos::paraManana($negocio),
            'ok'      => flash_obtener('ok'),
        ], 'panel');
    }

    /**
     * Abre WhatsApp ofreciéndole el espacio. Se recalcula aquí (no se confía
     * en el formulario): si ese espacio ya se ocupó, se ofrece el siguiente.
     */
    public function huecoWhatsapp(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $this->exigirCopiloto($negocio);
        if (!csrf_verificar()) {
            redirigir('/panel/copiloto/huecos');
        }
        foreach (\App\Models\Huecos::paraManana($negocio) as $hueco) {
            if ((int) $hueco['cliente']['id'] !== (int) $parametros['cliente']) {
                continue;
            }
            $texto = \App\Models\Huecos::mensaje($hueco, (string) ($negocio['negocio_nombre'] ?? $negocio['nombre']), $this->enlacePreferencias($hueco['cliente']));
            Copiloto::registrarEnvio((int) $negocio['negocio_id'], (int) $hueco['cliente']['id'], $texto);
            header('Location: https://wa.me/57' . preg_replace('/\D+/', '', (string) $hueco['cliente']['telefono']) . '?text=' . rawurlencode($texto));
            exit;
        }
        flash_set('ok', 'Ese espacio ya no está disponible o ya tiene cita. La lista se actualizó.');
        redirigir('/panel/copiloto/huecos');
    }

    /** "Me pidió que no le escribiera más": se retira su permiso y queda en el registro. */
    public function quitarPromocionesCopiloto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $negocioId = (int) $negocio['negocio_id'];
        $cliente = Cliente::buscar((int) $parametros['cliente'], $negocioId);
        if ($cliente !== null && csrf_verificar()) {
            Consentimiento::cambiarMarketing($negocioId, (int) $cliente['id'], false, 'panel', (int) $negocio['usuario_id']);
            flash_set('ok', 'Listo: ' . $cliente['nombre'] . ' ya no aparece para promociones.');
        }

        redirigir('/panel/copiloto');
    }

    /** Sin permiso de promociones no se arma ni se registra un mensaje comercial. */
    private function exigirPermisoPromociones(array $cliente, string $segmento): void
    {
        if (Cliente::contactable($cliente)) {
            return;
        }
        flash_set('error', $cliente['nombre'] . ' no ha autorizado promociones por WhatsApp. Puedes pedirle permiso una vez desde la lista.');
        redirigir('/panel/copiloto?segmento=' . $segmento);
    }

    private function enlacePreferencias(array $cliente): string
    {
        return url_publica('/preferencias/' . Cliente::tokenPreferencias((int) $cliente['id'], (int) $cliente['negocio_id']));
    }

    /**
     * Código y vencimiento que el mensaje va a nombrar: el del cupón personal
     * que ya tiene sin usar, o uno reservado en la sesión (todavía sin crear)
     * para que "Otra versión" y recargar no cambien el código a cada rato.
     *
     * @return array{codigo: string, vence_en: string}
     */
    private function cuponCopiloto(int $negocioId, int $clienteId, int $porcentaje): array
    {
        $vigente = Cupon::personalVigente($negocioId, $clienteId, $porcentaje);
        if ($vigente !== null) {
            return ['codigo' => (string) $vigente['codigo'], 'vence_en' => (string) $vigente['vence_en']];
        }
        $codigo = $_SESSION['copiloto_cupon'][$clienteId][$porcentaje] ?? null;
        if (!is_string($codigo) || Cupon::existeCodigo($negocioId, $codigo)) {
            do {
                $codigo = Cupon::codigoAleatorio('VUELVE');
            } while (Cupon::existeCodigo($negocioId, $codigo));
            $_SESSION['copiloto_cupon'][$clienteId][$porcentaje] = $codigo;
        }

        return ['codigo' => $codigo, 'vence_en' => date('Y-m-d', strtotime('+' . Cupon::DIAS_COPILOTO . ' days'))];
    }

    /** @return array<string, mixed> el cupón personal ya guardado */
    private function crearCuponCopiloto(int $negocioId, int $clienteId, int $porcentaje): array
    {
        $reservado = $this->cuponCopiloto($negocioId, $clienteId, $porcentaje);
        $cupon = Cupon::asegurarPersonal($negocioId, $clienteId, $porcentaje, $reservado['codigo']);
        unset($_SESSION['copiloto_cupon'][$clienteId][$porcentaje]);

        return $cupon;
    }

    /** Todas las sedes del negocio, con el botón para cambiar de una a otra. Cualquier rol puede entrar. */
    public function sedes(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $todas = Sede::listarPorNegocio((int) $negocio['negocio_id']);

        $sedesVisibles = $negocio['rol'] === 'dueno'
            ? $todas
            : array_values(array_filter($todas, fn ($s) => in_array((int) $s['id'], Usuario::sedeIdsAsignadas((int) $negocio['usuario_id']), true)));

        $precioExtra = (int) ($negocio['precio_sede_extra'] ?? 0);
        $vigente = !empty($negocio['plan_vence_en']) && $negocio['plan_vence_en'] >= date('Y-m-d');

        ver('panel/sedes', [
            'titulo'      => 'Sedes · Veci',
            'activo'      => 'sedes',
            'negocio'     => $negocio,
            'sedes'       => $sedesVisibles,
            'totalSedes'  => count($todas),
            'cupo'        => Sede::cupo($negocio),
            // Las más nuevas por encima del cupo (el plan bajó): su tienda está en pausa.
            'enPausa'     => array_map('intval', array_column(array_slice($this->ordenarPorId($todas), Sede::cupo($negocio)), 'id')),
            'precioExtra' => $precioExtra,
            // Solo se vende sede extra con un plan que la ofrece y vigente:
            // se prorratea hasta su vencimiento.
            'prorrateo'   => $precioExtra > 0 && $vigente ? Plan::prorrateoSedeExtra($precioExtra, (string) $negocio['plan_vence_en'], ($negocio['plan_ciclo'] ?? 'mensual') === 'anual') : null,
            'pendiente'   => $negocio['rol'] === 'dueno' ? PagoPlan::pendientePorNegocio((int) $negocio['negocio_id']) : null,
            'ok'          => flash_obtener('ok'),
            'error'       => flash_obtener('error'),
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
            $this->exigirCupoDeSedes($negocio);

            $nombre = mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 120);
            $whatsapp = whatsapp_normalizado((string) ($_POST['whatsapp'] ?? '')) ?? '';

            if ($nombre === '' || $whatsapp === '') {
                flash_set('error', 'Escribe el nombre de la sede y su WhatsApp (10 dígitos que empiecen por 3).');
            } else {
                $nuevaSedeId = Sede::crear((int) $negocio['negocio_id'], $nombre, $whatsapp);
                Auth::cambiarSede($nuevaSedeId);
                flash_set('ok', 'Sede creada correctamente. Termina de configurarla: catálogo, horario y Bre-B.');
            }
        }

        redirigir('/panel/sedes');
    }

    /**
     * Corta la ejecución si el negocio ya llegó al número de sedes que
     * incluye su plan (planes.sedes_incluidas — 1 en Gratis/Barrio, 3 en
     * Pro). Sin este chequeo cualquier plan podía crear sedes públicas
     * ilimitadas gratis, que es justo la función que debería costar.
     * Cuenta TODAS las sedes (publicadas o no): una sin publicar ya ocupa
     * el cupo igual.
     */
    /** @param array<int, array<string, mixed>> $filas */
    private function ordenarPorId(array $filas): array
    {
        usort($filas, fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);

        return $filas;
    }

    private function exigirCupoDeSedes(array $negocio): void
    {
        $cupo = Sede::cupo($negocio);
        if (Sede::contarPorNegocio((int) $negocio['negocio_id']) < $cupo) {
            return;
        }

        if (!empty($negocio['precio_sede_extra'])) {
            flash_set('error', 'Ya usas las ' . $cupo . ' sedes de tu plan. Agrega una sede extra para crear otra.');
            redirigir('/panel/sedes');
        }
        flash_set(
            'error',
            'Tu plan incluye ' . $cupo . ' sede' . ($cupo === 1 ? '' : 's') . '. Sube a Pro para tener varias sedes.'
        );
        redirigir('/panel/plan');
    }

    /**
     * Pide una sede extra (solo planes que la venden, como Pro): deja un
     * pago pendiente prorrateado hasta que vence el plan (Plan::
     * prorrateoSedeExtra). El cupo sube cuando se confirma el pago, por
     * Bre-B (admin) o Wompi, igual que un plan.
     */
    public function solicitarSedeExtra(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $negocioId = (int) $negocio['negocio_id'];

        if (!csrf_verificar()) {
            flash_set('error', 'El formulario expiró, intenta de nuevo.');
            redirigir('/panel/sedes');
        }
        $precio = (int) ($negocio['precio_sede_extra'] ?? 0);
        $venceEn = (string) ($negocio['plan_vence_en'] ?? '');
        if ($precio <= 0 || $venceEn === '' || $venceEn < date('Y-m-d')) {
            flash_set('error', 'Las sedes extra son del plan Pro vigente.');
            redirigir('/panel/plan');
        }
        if (PagoPlan::pendientePorNegocio($negocioId) !== null) {
            flash_set('error', 'Ya tienes un pago pendiente: págalo o cancélalo antes de pedir otra cosa.');
            redirigir('/panel/plan');
        }
        // Mientras quede cupo incluido, la sede nueva no cuesta nada.
        if (Sede::contarPorNegocio($negocioId) < Sede::cupo($negocio)) {
            flash_set('error', 'Todavía tienes sedes incluidas en tu plan: créala sin costo.');
            redirigir('/panel/sedes');
        }

        $prorrateo = Plan::prorrateoSedeExtra($precio, $venceEn, ($negocio['plan_ciclo'] ?? 'mensual') === 'anual');
        PagoPlan::crearPendiente(
            $negocioId,
            (int) $negocio['plan_id'],
            $prorrateo['monto'],
            (string) ($negocio['plan_ciclo'] ?? 'mensual'),
            date('Y-m-d'),
            $venceEn,
            'sede_extra',
            1
        );

        flash_set('ok', 'Listo: paga ' . pesos($prorrateo['monto']) . ' por la sede extra (los ' . $prorrateo['dias'] . ' días que le quedan a tu plan) y podrás crearla.');
        redirigir('/panel/plan');
    }

    public function editarSede(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        $sede = Sede::buscarPorIdYNegocio((int) $parametros['sede'], (int) $negocio['negocio_id']);
        if ($sede === null) {
            redirigir('/panel/sedes');
        }

        ver('panel/sede_editar', [
            'titulo'  => 'Editar sede · Veci',
            'activo'  => 'sedes',
            'negocio' => $negocio,
            'sede'    => $sede,
            'error'   => flash_obtener('error'),
        ], 'panel');
    }

    public function actualizarSede(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        $sede = Sede::buscarPorIdYNegocio((int) $parametros['sede'], (int) $negocio['negocio_id']);
        if ($sede === null) {
            redirigir('/panel/sedes');
        }

        if (csrf_verificar()) {
            $nombre = mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 120);
            $whatsapp = whatsapp_normalizado((string) ($_POST['whatsapp'] ?? '')) ?? '';
            $aceptaMesa = isset($_POST['acepta_mesa']);
            $direccion = mb_substr(trim((string) ($_POST['direccion'] ?? '')), 0, 200);

            if ($nombre === '' || $whatsapp === '') {
                flash_set('error', 'No se guardó: escribe el nombre de la sede y su WhatsApp (10 dígitos que empiecen por 3).');
            } else {
                Sede::actualizar((int) $sede['id'], $nombre, $whatsapp, $aceptaMesa, $direccion !== '' ? $direccion : null);
                // El color es del negocio (todas sus sedes), no de esta sede.
                if (isset($_POST['color_marca'])) {
                    \App\Models\Negocio::actualizarColor((int) $negocio['negocio_id'], (string) $_POST['color_marca']);
                }
                // La llave Bre-B solo se elegía al abrir la tienda: si el
                // dueño cambiaba de cuenta, no tenía dónde corregirla.
                if (isset($_POST['llave_tipo'])) {
                    $tipo = in_array($_POST['llave_tipo'], ['celular', 'cedula', 'correo'], true) ? $_POST['llave_tipo'] : 'celular';
                    $llave = llave_breb_normalizada($tipo, (string) ($_POST['llave_valor'] ?? ''));
                    if ($llave === null) {
                        flash_set('error', 'Guardamos lo demás, pero la llave Bre-B no parece válida: un celular tiene 10 dígitos y empieza por 3; una cédula, solo números.');
                        redirigir('/panel/sedes/' . $sede['id'] . '/editar');
                    }
                    Sede::guardarLlaveBreB((int) $sede['id'], $tipo, $llave);
                }
                flash_set('ok', 'Datos de ' . $nombre . ' actualizados.');
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
            $nombre = mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 120);
            $whatsapp = whatsapp_normalizado((string) ($_POST['whatsapp'] ?? '')) ?? '';
            $password = (string) ($_POST['password'] ?? '');
            // Solo sedes que en verdad son de este negocio (antes de validar que haya al menos una).
            $sedesDelNegocio = array_map('intval', array_column(Sede::listarPorNegocio((int) $negocio['negocio_id']), 'id'));
            $sedeIds = array_values(array_intersect(array_map('intval', (array) ($_POST['sedes'] ?? [])), $sedesDelNegocio));

            if ($nombre === '' || $whatsapp === '' || strlen($password) < 8 || $sedeIds === []) {
                flash_set('error', 'Completa nombre, WhatsApp (10 dígitos que empiecen por 3), una contraseña de al menos 8 caracteres y elige al menos una sede.');
                redirigir('/panel/colaboradores');
            }

            if (Usuario::buscarPorWhatsapp($whatsapp) !== null) {
                flash_set('error', 'Ya existe una cuenta con ese número de WhatsApp.');
                redirigir('/panel/colaboradores');
            }

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
            } elseif (strlen($nueva) < 8) {
                flash_set('error', 'La contraseña nueva debe tener al menos 8 caracteres.');
            } else {
                Usuario::cambiarPassword((int) $negocio['usuario_id'], $nueva);
                Auth::renovarVersionDeSesion();
                flash_set('ok', 'Contraseña actualizada.');
            }
        }

        redirigir('/panel/cuenta');
    }

    /**
     * Plan actual, cuánto se lleva usado este mes (solo importa en Gratis,
     * que es el único con límites) y los 3 planes para subir o bajar. Solo
     * el dueño: es dinero del negocio, no algo que un colaborador toque.
     */
    public function plan(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $negocioId = (int) $negocio['negocio_id'];
        $esReservas = $negocio['tipo_negocio'] === 'reservas';

        $limitePedidosMes = $negocio['limite_pedidos_mes'] ?? null;
        $usadosEsteMes = $limitePedidosMes !== null
            ? ($esReservas ? Cita::contarEsteMesPorNegocio($negocioId) : Pedido::contarEsteMesPorNegocio($negocioId))
            : null;
        $limiteIa = $negocio['limite_ia_mes'] ?? null;
        $iaUsadaEsteMes = $limiteIa !== null ? UsoIA::contarEsteMesPorNegocio($negocioId) : null;

        ver('panel/plan', [
            'titulo'            => 'Tu plan · Veci',
            'activo'            => 'plan',
            'negocio'           => $negocio,
            'planes'            => Plan::listarTodos(),
            'totalSedes'        => Sede::contarPorNegocio($negocioId),
            'pendiente'         => PagoPlan::pendientePorNegocio($negocioId),
            'limitePedidosMes'  => $limitePedidosMes,
            'usadosEsteMes'     => $usadosEsteMes,
            'limiteIaMes'       => $limiteIa,
            'iaUsadaEsteMes'    => $iaUsadaEsteMes,
            'sustantivo'        => $esReservas ? 'citas' : 'pedidos',
            // Lo que el copiloto ayudó a recuperar: la mejor razón para renovar.
            'recuperado'        => Copiloto::disponiblePara($negocio) ? Copiloto::recuperadoEsteMes($negocioId, $negocio['tipo_negocio']) : null,
            'llaveBreb'         => config('cobro_planes.llave_breb'),
            'wompi'             => \App\Services\Wompi::disponible(),
            'ok'                => flash_obtener('ok'),
            'error'             => flash_obtener('error'),
        ], 'panel');
    }

    /**
     * Pide el cambio a un plan pago: deja una fila sin confirmar en
     * pagos_plan con lo que se espera que transfiera (ver PagoPlan), para
     * que un admin la reconozca y confirme desde /admin. El plan del
     * negocio NO cambia todavía — eso solo pasa cuando se confirma. Bajar a
     * Gratis es la excepción: es instantáneo, no hay nada que cobrar.
     */
    public function solicitarCambioPlan(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        if (!csrf_verificar()) {
            flash_set('error', 'El formulario expiró, intenta de nuevo.');
            redirigir('/panel/plan');
        }

        $plan = Plan::buscarPorId((int) ($_POST['plan_id'] ?? 0));
        $ciclo = (string) ($_POST['ciclo'] ?? 'mensual');
        if (!in_array($ciclo, ['mensual', 'anual'], true)) {
            $ciclo = 'mensual';
        }

        if ($plan === null) {
            flash_set('error', 'Elige un plan válido.');
            redirigir('/panel/plan');
        }

        if ($plan['nombre'] === 'gratis') {
            Negocio::cambiarAGratis((int) $negocio['negocio_id']);
            flash_set('ok', 'Tu negocio pasó al plan Gratis.');
            redirigir('/panel/plan');
        }

        if (PagoPlan::pendientePorNegocio((int) $negocio['negocio_id']) !== null) {
            flash_set('error', 'Ya tienes una solicitud de cambio de plan pendiente de confirmación.');
            redirigir('/panel/plan');
        }

        // Con más sedes que las incluidas, la renovación cobra también las
        // extra (las que el negocio tiene hoy: si borró una, ya no se cobra).
        $sedesExtra = Plan::sedesExtraNecesarias($plan, Sede::contarPorNegocio((int) $negocio['negocio_id']));
        $monto = Plan::precio($plan, $ciclo, $sedesExtra);
        $inicio = new \DateTimeImmutable('today');
        $fin = $inicio->modify($ciclo === 'anual' ? '+1 year' : '+30 days');

        PagoPlan::crearPendiente(
            (int) $negocio['negocio_id'],
            (int) $plan['id'],
            $monto,
            $ciclo,
            $inicio->format('Y-m-d'),
            $fin->format('Y-m-d'),
            'plan',
            $sedesExtra
        );

        flash_set('ok', \App\Services\Wompi::disponible()
            ? 'Listo: paga ' . pesos($monto) . ' con Wompi y tu plan se activa solo, o transfiere por Bre-B.'
            : 'Listo, dejamos tu solicitud registrada. Transfiere ' . pesos($monto) . ' por Bre-B y confirmamos tu plan apenas lo veamos.');
        redirigir('/panel/plan');
    }

    /** El dueño retira su solicitud pendiente (p. ej. eligió el plan o el ciclo equivocado) para poder pedir otra. */
    /**
     * Regreso del checkout de Wompi (?id=transacción). No se confía en lo
     * que diga la URL: se consulta la transacción a la API de Wompi. Si no
     * se puede consultar todavía, el webhook la confirma en un momento.
     */
    public function regresoPagoPlan(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        // Sin Wompi configurado (o sin transacción en la URL) no hay nada que
        // confirmar: no se promete "se activa en unos minutos".
        if (!\App\Services\Wompi::disponible() || (string) ($_GET['id'] ?? '') === '') {
            redirigir('/panel/plan');
        }
        // Aquí NO se activa nada: la API pública responde por transacciones
        // de cualquier comercio de Wompi, así que alguien podría cobrarse a
        // sí mismo con nuestra referencia. Solo el webhook firmado (o un
        // admin) confirma el pago; esto solo informa cómo va.
        $transaccion = \App\Services\Wompi::consultarTransaccion((string) ($_GET['id'] ?? ''));
        $estado = (string) ($transaccion['status'] ?? '');
        $pendiente = PagoPlan::pendientePorNegocio((int) $negocio['negocio_id']);
        if ($pendiente === null && $estado === 'APPROVED') {
            flash_set('ok', '¡Pago recibido! Tu plan ya está activo.');
        } elseif (in_array($estado, ['DECLINED', 'VOIDED', 'ERROR'], true)) {
            flash_set('error', 'El pago no se aprobó. Puedes intentarlo otra vez o transferir por Bre-B.');
        } else {
            flash_set('ok', 'Estamos confirmando tu pago con Wompi: tu plan se activa solo en unos minutos.');
        }
        redirigir('/panel/plan');
    }

    public function cancelarSolicitudPlan(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        if (csrf_verificar() && PagoPlan::cancelarPendienteDeNegocio((int) $negocio['negocio_id'])) {
            flash_set('ok', 'Cancelamos tu solicitud. Si ya transferiste, escríbenos a soporte@tuveci.co antes de pedir otra.');
        }

        redirigir('/panel/plan');
    }

    /** Derecho de eliminación de datos (habeas data): borra al cliente y todo su historial. Solo el dueño. */
    public function eliminarCliente(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        if (csrf_verificar()) {
            $cliente = Cliente::buscar((int) $parametros['cliente'], (int) $negocio['negocio_id']);
            // Tiendas (fase 4): borrarlo se llevaría su cuenta de fiado (cascada).
            $razon = $cliente !== null ? \App\Models\Fiado::razonParaNoBorrar((int) $negocio['negocio_id'], (int) $cliente['id']) : null;
            if ($razon !== null) {
                flash_set('error', $cliente['nombre'] . ': ' . lcfirst($razon));
            } elseif ($cliente !== null) {
                Cliente::eliminar((int) $cliente['id'], (int) $negocio['negocio_id']);
                flash_set('ok', 'Se eliminaron los datos de ' . $cliente['nombre'] . ' y todo su historial.');
            }
        }

        redirigir('/panel/copiloto');
    }

    /** Corta la ejecución si el plan del negocio no incluye el copiloto de recompra (planes.incluye_copiloto). */
    private function exigirCopiloto(array $negocio): void
    {
        if (!Copiloto::disponiblePara($negocio)) {
            flash_set('error', 'El copiloto de recompra es parte de los planes Barrio y Pro.');
            redirigir('/panel/plan');
        }
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
        // Solo rutas del panel, sin saltos de línea ni caracteres de control
        // (irían a parar a la cabecera Location).
        if (!is_string($ruta) || ($ruta !== '/panel' && !str_starts_with($ruta, '/panel/')) || preg_match('/[\x00-\x1F\x7F\\\\]/', $ruta) === 1) {
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

    /**
     * Un cliente puede llamarse "=HYPERLINK(...)" en la tienda pública: si el
     * dueño abre el CSV en Excel/Sheets, esa celda se ejecuta como fórmula
     * (CSV injection). Toda celda que empiece con un carácter de fórmula se
     * antepone con una comilla simple, que la hoja muestra como texto plano.
     *
     * @param resource $salida
     * @param array<int, mixed> $fila
     */
    private function escribirFilaCsv($salida, array $fila): void
    {
        $segura = array_map(static function (mixed $celda): mixed {
            if (is_string($celda) && $celda !== '' && strpbrk($celda[0], "=+-@\t\r") !== false) {
                return "'" . $celda;
            }
            return $celda;
        }, $fila);

        fputcsv($salida, $segura, ',', '"', '');
    }
}
