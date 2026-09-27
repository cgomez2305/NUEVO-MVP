<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Cliente;
use App\Models\Negocio;
use App\Models\Pedido;
use App\Models\Producto;

/**
 * El flujo B de la maqueta: el cliente entra a la tienda, arma su carrito
 * y pide por WhatsApp. El carrito vive en la sesión, separado por negocio,
 * para que ver dos tiendas en pestañas distintas no las mezcle.
 */
class TiendaController
{
    public function mostrar(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        $productos = Producto::listarPorNegocio((int) $negocio['id'], true);
        $carrito = $this->resumenCarrito($negocio, $productos);

        ver('tienda/mostrar', [
            'titulo'    => $negocio['nombre'] . ' · Parroquia',
            'negocio'   => $negocio,
            'productos' => $productos,
            'carrito'   => $carrito,
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
