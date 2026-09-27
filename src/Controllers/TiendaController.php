<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Negocio;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Servicio;

/**
 * El flujo B de la maqueta: el cliente entra a la tienda y, según el tipo
 * de negocio, arma su carrito y pide por WhatsApp (pedidos) o reserva un
 * horario para un servicio (reservas). El carrito/las citas viven en sesión
 * o en base de datos según corresponda, separados por negocio.
 */
class TiendaController
{
    public function mostrar(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);

        if ($negocio['tipo_negocio'] === 'reservas') {
            ver('tienda/servicios', [
                'titulo'    => $negocio['nombre'] . ' · Veci',
                'negocio'   => $negocio,
                'servicios' => Servicio::listarPorNegocio((int) $negocio['id'], true),
            ], 'tienda');
            return;
        }

        $productos = Producto::listarPorNegocio((int) $negocio['id'], true);
        $carrito = $this->resumenCarrito($negocio, $productos);

        ver('tienda/mostrar', [
            'titulo'    => $negocio['nombre'] . ' · Veci',
            'negocio'   => $negocio,
            'productos' => $productos,
            'carrito'   => $carrito,
        ], 'tienda');
    }

    public function reservar(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        if ($negocio['tipo_negocio'] !== 'reservas') {
            abortar404();
        }

        $servicio = Servicio::buscar((int) $parametros['servicio'], (int) $negocio['id']);
        if ($servicio === null || (int) $servicio['activo'] !== 1) {
            abortar404();
        }

        $fecha = (string) ($_GET['fecha'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < date('Y-m-d')) {
            $fecha = date('Y-m-d');
        }

        $horario = Negocio::horario($negocio);
        $intervalo = (int) $negocio['intervalo_citas_min'];
        $ocupados = Cita::ocupadosEnFecha((int) $negocio['id'], $fecha);
        $slots = Cita::calcularDisponibilidad($horario, $intervalo, $fecha, (int) $servicio['duracion_min'], $ocupados);

        $horaElegida = (string) ($_GET['hora'] ?? '');
        $slotValido = $horaElegida !== '' && in_array($horaElegida, $slots, true);

        // Los próximos 14 días, para que el cliente pueda cambiar de fecha sin escribirla a mano.
        $fechasDisponibles = [];
        for ($i = 0; $i < 14; $i++) {
            $fechasDisponibles[] = date('Y-m-d', strtotime("+{$i} days"));
        }

        ver('tienda/reservar', [
            'titulo'            => 'Reservar ' . $servicio['nombre'] . ' · ' . $negocio['nombre'],
            'negocio'           => $negocio,
            'servicio'          => $servicio,
            'fecha'             => $fecha,
            'fechasDisponibles' => $fechasDisponibles,
            'slots'             => $slots,
            'horaElegida'       => $slotValido ? $horaElegida : null,
            'error'             => flash_obtener('error'),
        ], 'tienda');
    }

    public function crearCita(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        if ($negocio['tipo_negocio'] !== 'reservas') {
            abortar404();
        }

        $servicioId = (int) ($_POST['servicio_id'] ?? 0);
        $servicio = Servicio::buscar($servicioId, (int) $negocio['id']);
        $fecha = (string) ($_POST['fecha'] ?? '');
        $hora = (string) ($_POST['hora'] ?? '');

        $volverAReservar = '/t/' . $negocio['slug'] . '/reservar/' . $servicioId . '?fecha=' . rawurlencode($fecha);

        if (!csrf_verificar() || $servicio === null) {
            redirigir('/t/' . $negocio['slug']);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !preg_match('/^\d{2}:\d{2}$/', $hora)) {
            flash_set('error', 'Elige una fecha y una hora válidas.');
            redirigir($volverAReservar);
        }

        // Vuelve a calcular disponibilidad justo antes de guardar, por si alguien más
        // tomó ese horario mientras el cliente llenaba el formulario.
        $horario = Negocio::horario($negocio);
        $ocupados = Cita::ocupadosEnFecha((int) $negocio['id'], $fecha);
        $slots = Cita::calcularDisponibilidad(
            $horario,
            (int) $negocio['intervalo_citas_min'],
            $fecha,
            (int) $servicio['duracion_min'],
            $ocupados
        );

        if (!in_array($hora, $slots, true)) {
            flash_set('error', 'Ese horario ya no está disponible. Elige otro.');
            redirigir($volverAReservar);
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $telefono = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? '')) ?? '';
        $autorizo = isset($_POST['autorizo_datos']);

        if ($nombre === '' || $telefono === '' || !$autorizo) {
            flash_set('error', 'Escribe tu nombre, tu WhatsApp y autoriza el tratamiento de tus datos para continuar.');
            redirigir($volverAReservar . '&hora=' . rawurlencode($hora));
        }

        $clienteId = Cliente::buscarOCrear((int) $negocio['id'], $nombre, $telefono, true);

        $citaId = Cita::crear(
            (int) $negocio['id'],
            $clienteId,
            (int) $servicio['id'],
            $servicio['nombre'],
            (int) $servicio['precio'],
            "{$fecha} {$hora}:00",
            (int) $servicio['duracion_min'],
        );
        $cita = Cita::buscar($citaId, (int) $negocio['id']);

        $resumenTexto = "Reserva nueva de {$nombre}:\n"
            . "- {$servicio['nombre']} el " . date('d/m/Y', strtotime($fecha)) . " a las {$hora}\n"
            . 'Valor: ' . pesos((int) $servicio['precio']);

        $telefonoNegocio = preg_replace('/\D+/', '', (string) $negocio['whatsapp']) ?? '';
        $enlaceWhatsapp = 'https://wa.me/57' . $telefonoNegocio . '?text=' . rawurlencode($resumenTexto);

        ver('tienda/cita_confirmada', [
            'titulo'         => 'Cita reservada · ' . $negocio['nombre'],
            'negocio'        => $negocio,
            'cita'           => $cita,
            'enlaceWhatsapp' => $enlaceWhatsapp,
        ], 'tienda');
    }

    public function agregarAlCarrito(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);

        if (csrf_verificar()) {
            $productoId = (int) ($_POST['producto_id'] ?? 0);
            $producto = Producto::buscar($productoId, (int) $negocio['id']);

            if ($producto !== null && (int) $producto['activo'] === 1) {
                $carrito = $this->carritoDeSesion((int) $negocio['id']);
                $carrito[$productoId] = ($carrito[$productoId] ?? 0) + 1;
                $this->guardarCarrito((int) $negocio['id'], $carrito);
            }
        }

        redirigir('/t/' . $negocio['slug']);
    }

    public function quitarDelCarrito(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);

        if (csrf_verificar()) {
            $productoId = (int) ($_POST['producto_id'] ?? 0);
            $carrito = $this->carritoDeSesion((int) $negocio['id']);
            unset($carrito[$productoId]);
            $this->guardarCarrito((int) $negocio['id'], $carrito);
        }

        redirigir('/t/' . $negocio['slug'] . '/carrito');
    }

    public function verCarrito(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        $productos = Producto::listarPorNegocio((int) $negocio['id'], true);
        $carrito = $this->resumenCarrito($negocio, $productos);

        ver('tienda/carrito', [
            'titulo'  => 'Tu carrito · ' . $negocio['nombre'],
            'negocio' => $negocio,
            'carrito' => $carrito,
            'error'   => flash_obtener('error'),
        ], 'tienda');
    }

    public function crearPedido(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        $productos = Producto::listarPorNegocio((int) $negocio['id'], true);
        $carrito = $this->resumenCarrito($negocio, $productos);

        if (!csrf_verificar()) {
            redirigir('/t/' . $negocio['slug'] . '/carrito');
        }

        if ($carrito['lineas'] === []) {
            flash_set('error', 'Tu carrito está vacío.');
            redirigir('/t/' . $negocio['slug']);
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $telefono = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? '')) ?? '';
        $autorizo = isset($_POST['autorizo_datos']);
        $metodoPago = (string) ($_POST['metodo_pago'] ?? 'breb');

        if ($nombre === '' || $telefono === '' || !$autorizo) {
            flash_set('error', 'Escribe tu nombre, tu WhatsApp y autoriza el tratamiento de tus datos para continuar.');
            redirigir('/t/' . $negocio['slug'] . '/carrito');
        }

        if (!in_array($metodoPago, ['breb', 'nequi', 'efectivo'], true)) {
            $metodoPago = 'breb';
        }

        $clienteId = Cliente::buscarOCrear((int) $negocio['id'], $nombre, $telefono, true);

        $items = array_map(fn ($linea) => [
            'producto_id' => $linea['producto']['id'],
            'nombre'      => $linea['producto']['nombre'],
            'precio'      => (int) $linea['producto']['precio'],
            'cantidad'    => $linea['cantidad'],
        ], $carrito['lineas']);

        $pedidoId = Pedido::crear((int) $negocio['id'], $clienteId, $metodoPago, $items);
        $pedido = Pedido::buscar($pedidoId, (int) $negocio['id']);

        $this->guardarCarrito((int) $negocio['id'], []);

        $resumenTexto = "Pedido nuevo de {$nombre}:\n";
        foreach ($items as $item) {
            $resumenTexto .= "- {$item['cantidad']} x {$item['nombre']}\n";
        }
        $resumenTexto .= 'Total: ' . pesos((int) $pedido['total']);

        $telefonoNegocio = preg_replace('/\D+/', '', (string) $negocio['whatsapp']) ?? '';
        $enlaceWhatsapp = 'https://wa.me/57' . $telefonoNegocio . '?text=' . rawurlencode($resumenTexto);

        ver('tienda/pedido_confirmado', [
            'titulo'         => 'Pedido listo · ' . $negocio['nombre'],
            'negocio'        => $negocio,
            'pedido'         => $pedido,
            'items'          => $items,
            'enlaceWhatsapp' => $enlaceWhatsapp,
        ], 'tienda');
    }

    private function negocioOAbortar(string $slug): array
    {
        $negocio = Negocio::buscarPorSlugPublicada($slug);
        if ($negocio === null) {
            abortar404();
        }
        return $negocio;
    }

    /** @return array<int, int> productoId => cantidad */
    private function carritoDeSesion(int $negocioId): array
    {
        return $_SESSION['carrito'][$negocioId] ?? [];
    }

    /** @param array<int, int> $carrito */
    private function guardarCarrito(int $negocioId, array $carrito): void
    {
        $_SESSION['carrito'][$negocioId] = $carrito;
    }

    /**
     * Cruza el carrito de la sesión con los productos actuales del negocio,
     * así un producto que el dueño borró o desactivó no revienta el carrito.
     *
     * @param array<int, array<string, mixed>> $productos
     * @return array{lineas: array<int, array{producto: array<string, mixed>, cantidad: int}>, cantidad: int, total: int}
     */
    private function resumenCarrito(array $negocio, array $productos): array
    {
        $porId = [];
        foreach ($productos as $producto) {
            $porId[(int) $producto['id']] = $producto;
        }

        $lineas = [];
        $cantidadTotal = 0;
        $total = 0;

        foreach ($this->carritoDeSesion((int) $negocio['id']) as $productoId => $cantidad) {
            if (!isset($porId[$productoId]) || $cantidad < 1) {
                continue;
            }
            $producto = $porId[$productoId];
            $lineas[] = ['producto' => $producto, 'cantidad' => $cantidad];
            $cantidadTotal += $cantidad;
            $total += (int) $producto['precio'] * $cantidad;
        }

        return ['lineas' => $lineas, 'cantidad' => $cantidadTotal, 'total' => $total];
    }
}
