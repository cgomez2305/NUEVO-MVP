<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cliente;
use App\Models\ClienteDeOtroNombre;
use App\Models\CodigoBarras;
use App\Models\Fiado;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDuplicada;

/**
 * Venta de mostrador (tiendas, fase 4): lo que se cobra en el local con
 * lector de códigos, cámara o buscando por nombre. Para todo el equipo de
 * un negocio de pedidos; anular una venta es solo del dueño.
 *
 * Sin JS, el tiquete en curso vive en la sesión (por sede) y cada botón es
 * un formulario real. Con JS (assets/js/tiendas.js) el tiquete se arma en
 * el navegador y se copia a la sesión en segundo plano; al cobrar, el
 * formulario lleva las líneas y el servidor vuelve a armar todo con los
 * precios de la base (nunca los del navegador).
 */
class MostradorController
{
    public function ver(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $sedeId = (int) $negocio['id'];
        $busqueda = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        $pesar = isset($_GET['pesar']) ? Producto::buscar((int) $_GET['pesar'], $sedeId) : null;
        if ($pesar !== null && $pesar['vende_por'] !== 'peso') {
            $pesar = null;
        }
        $ventaReciente = isset($_GET['venta']) ? Venta::buscar((int) $_GET['venta'], $sedeId) : null;
        $carrito = $this->carrito($sedeId);

        ver('panel/mostrador', [
            'titulo'        => 'Mostrador · Veci',
            'activo'        => 'mostrador',
            'negocio'       => $negocio,
            'lineas'        => Venta::armarLineas($sedeId, $carrito['cantidades']),
            'token'         => $carrito['token'],
            'busqueda'      => $busqueda,
            'resultados'    => $busqueda !== '' ? Producto::buscarPorNombre($sedeId, $busqueda, 12) : [],
            'pesar'         => $pesar,
            'reemplazar'    => isset($_GET['reemplazar']),
            'ventaReciente' => $ventaReciente,
            'ventasHoy'     => Venta::delDia($sedeId, date('Y-m-d'), 30),
            'clientes'      => Fiado::clientesParaElegir((int) $negocio['negocio_id'], 300, Fiado::filtroSedes($negocio)),
            'formAnterior'  => $this->sacarFormAnterior(),
            'codigoNuevo'   => $_SESSION['mostrador_codigo_nuevo'] ?? null,
            'ok'            => flash_obtener('ok'),
            'error'         => flash_obtener('error'),
        ], 'panel');
        unset($_SESSION['mostrador_codigo_nuevo']);
    }

    /** El catálogo compacto de la sede para que el JS escanee sin esperar al servidor. */
    public function catalogo(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['productos' => Producto::catalogoCompacto((int) $negocio['id'])], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Lo que manda el lector (o lo que se escribió): un código exacto de la
     * sede agrega el producto; si no, se busca por nombre.
     */
    public function escanear(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $sedeId = (int) $negocio['id'];
        if (!csrf_verificar()) {
            redirigir('/panel/mostrador');
        }
        $texto = mb_substr(trim((string) ($_POST['codigo'] ?? '')), 0, 60);
        if ($texto === '') {
            redirigir('/panel/mostrador');
        }

        $codigo = Producto::normalizarCodigo($texto);
        $producto = $codigo !== null ? Producto::buscarPorCodigo($sedeId, $codigo) : null;
        if ($producto === null) {
            $encontrados = Producto::buscarPorNombre($sedeId, $texto, 12);
            if (count($encontrados) === 1) {
                $producto = $encontrados[0];
            } elseif (count($encontrados) > 1) {
                redirigir('/panel/mostrador?q=' . rawurlencode($texto));
            }
        }
        if ($producto === null) {
            if ($codigo !== null && ctype_digit($codigo)) {
                $sugerencia = CodigoBarras::sugerencia($codigo);
                $_SESSION['mostrador_codigo_nuevo'] = $codigo;
                flash_set('error', "No tienes ningún producto con el código {$codigo}." . ($sugerencia !== null ? " Otras tiendas lo llaman: «{$sugerencia}»." : ''));
            } else {
                flash_set('error', "No encontramos «{$texto}» en tus productos.");
            }
            redirigir('/panel/mostrador');
        }
        if ($producto['vende_por'] === 'peso') {
            redirigir('/panel/mostrador?pesar=' . (int) $producto['id']);
        }
        $this->sumar($sedeId, $producto, 1.0, false);
        redirigir('/panel/mostrador');
    }

    /** Agregar desde los resultados de búsqueda o desde la báscula (gramos). */
    public function agregar(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $sedeId = (int) $negocio['id'];
        if (!csrf_verificar()) {
            redirigir('/panel/mostrador');
        }
        $producto = Producto::buscar((int) ($_POST['producto_id'] ?? 0), $sedeId);
        if ($producto === null) {
            redirigir('/panel/mostrador');
        }
        if ($producto['vende_por'] === 'peso') {
            $gramos = (int) ($_POST['gramos'] ?? 0);
            if ($gramos <= 0) {
                $gramos = (int) preg_replace('/\D+/', '', (string) ($_POST['gramos_otro'] ?? ''));
            }
            if ($gramos <= 0 || $gramos > Venta::MAX_GRAMOS) {
                flash_set('error', 'Escribe cuántos gramos lleva (entre 1 g y 50 kg).');
                redirigir('/panel/mostrador?pesar=' . (int) $producto['id']);
            }
            $this->sumar($sedeId, $producto, $gramos / Producto::GRAMOS_POR_KILO, isset($_POST['reemplazar']));
        } else {
            $this->sumar($sedeId, $producto, 1.0, false);
        }
        redirigir('/panel/mostrador');
    }

    /** +, − o quitar en una línea del tiquete. */
    public function linea(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $sedeId = (int) $negocio['id'];
        if (!csrf_verificar()) {
            redirigir('/panel/mostrador');
        }
        $id = (int) ($_POST['producto_id'] ?? 0);
        $carrito = $this->carrito($sedeId);
        if (isset($carrito['cantidades'][$id])) {
            $accion = (string) ($_POST['accion'] ?? '');
            $producto = Producto::buscar($id, $sedeId);
            if ($accion === 'quitar' || $producto === null) {
                unset($carrito['cantidades'][$id]);
            } elseif ($accion === 'menos') {
                $carrito['cantidades'][$id] -= 1;
                if ($carrito['cantidades'][$id] < 1 || $producto['vende_por'] === 'peso') {
                    unset($carrito['cantidades'][$id]);
                }
            } elseif ($accion === 'mas' && $producto['vende_por'] !== 'peso') {
                $this->sumar($sedeId, $producto, 1.0, false);
                redirigir('/panel/mostrador');
            }
            $this->guardarCarrito($sedeId, $carrito);
        }
        redirigir('/panel/mostrador');
    }

    /** El JS copia aquí el tiquete que arma en el navegador (por si se recarga la página). */
    public function sincronizar(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $sedeId = (int) $negocio['id'];
        header('Content-Type: application/json; charset=utf-8');
        if (!csrf_verificar()) {
            http_response_code(403);
            echo json_encode(['ok' => false]);
            return;
        }
        $carrito = $this->carrito($sedeId);
        $carrito['cantidades'] = $this->soloDeLaSede($sedeId, (array) ($_POST['items'] ?? []));
        $this->guardarCarrito($sedeId, $carrito);
        echo json_encode(['ok' => true]);
    }

    public function vaciar(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        if (csrf_verificar()) {
            // Token nuevo también: un formulario de cobro viejo no cobra lo que ya no está.
            $this->guardarCarrito((int) $negocio['id'], ['cantidades' => [], 'token' => Venta::tokenNuevo()]);
        }
        redirigir('/panel/mostrador');
    }

    public function cobrar(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $sedeId = (int) $negocio['id'];
        $negocioId = (int) $negocio['negocio_id'];
        if (!csrf_verificar()) {
            flash_set('error', 'La página llevaba mucho tiempo abierta. Revisa el tiquete y cobra otra vez.');
            redirigir('/panel/mostrador');
        }
        $carrito = $this->carrito($sedeId);
        $tokenEnviado = (string) ($_POST['token'] ?? '');
        $enviados = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];

        // Las líneas que llegan del formulario son las que la persona vio.
        // items_presentes va siempre: un tiquete vaciado en el navegador llega
        // sin items y NO debe cobrar lo que quedaba en la sesión.
        if (isset($_POST['items_presentes'])) {
            $carrito['cantidades'] = $this->soloDeLaSede($sedeId, $enviados);
            $this->guardarCarrito($sedeId, $carrito);
        }

        // El token del formulario es la llave contra el doble cobro. Si ya hizo
        // una venta con estos mismos productos y cantidades, es el mismo
        // formulario que llegó dos veces: se muestra esa venta. Si la venta de
        // ese token es otra (otra pestaña, formulario viejo), esta es una venta
        // nueva y va con un token nuevo: no se pierde por tener el mismo total.
        $token = preg_match('/^[0-9a-f]{32}$/', $tokenEnviado) === 1 ? $tokenEnviado : Venta::tokenNuevo();
        $yaHecha = Venta::buscarPorToken($token, $sedeId);
        if ($yaHecha !== null) {
            if (Venta::mismasLineas((int) $yaHecha['id'], $enviados)) {
                $this->despuesDeVender($sedeId);
                flash_set('ok', 'Esa venta ya estaba registrada: no se cobró dos veces.');
                redirigir('/panel/mostrador?venta=' . (int) $yaHecha['id']);
            }
            $token = Venta::tokenNuevo();
        }

        $lineas = Venta::armarLineas($sedeId, $carrito['cantidades']);
        $metodo = (string) ($_POST['metodo'] ?? '');
        $textoRecibido = trim((string) ($_POST['recibido'] ?? ''));
        $recibido = $textoRecibido !== '' ? dinero_desde_texto($textoRecibido) : null;
        $_SESSION['mostrador_form'] = [
            'metodo' => $metodo, 'recibido' => $textoRecibido,
            'cliente_id' => (string) ($_POST['cliente_id'] ?? ''),
            'cliente_nombre' => mb_substr((string) ($_POST['cliente_nombre'] ?? ''), 0, 120),
            'cliente_telefono' => mb_substr((string) ($_POST['cliente_telefono'] ?? ''), 0, 20),
        ];

        $clienteId = null;
        $clienteNuevo = null;
        if ($metodo === 'fiado') {
            $elegido = (string) ($_POST['cliente_id'] ?? '');
            if ($elegido === 'nuevo') {
                // Se crea (o se reconoce) dentro de la transacción de la venta.
                $clienteNuevo = [
                    'nombre'     => (string) ($_POST['cliente_nombre'] ?? ''),
                    'telefono'   => (string) ($_POST['cliente_telefono'] ?? ''),
                    'autorizo'   => isset($_POST['cliente_autorizo']),
                    'confirmado' => ctype_digit((string) ($_POST['cliente_confirmado'] ?? '')) ? (int) $_POST['cliente_confirmado'] : null,
                ];
            } elseif (ctype_digit($elegido) && Cliente::buscar((int) $elegido, $negocioId) !== null && Fiado::clienteVisible($negocio, (int) $elegido)) {
                $clienteId = (int) $elegido;
            }
        }

        try {
            $ventaId = Venta::crear($sedeId, $negocioId, $lineas, $metodo, $recibido, $clienteId, (int) $negocio['usuario_id'], $token, $clienteNuevo);
        } catch (VentaDuplicada $e) {
            $this->despuesDeVender($sedeId);
            if ($e->ventaId === null) {
                flash_set('error', 'No pudimos confirmar la venta. Revisa "Ventas de hoy" antes de cobrar otra vez.');
                redirigir('/panel/mostrador#ventas-hoy');
            }
            flash_set('ok', 'Esa venta ya estaba registrada: no se cobró dos veces.');
            redirigir('/panel/mostrador?venta=' . $e->ventaId);
        } catch (ClienteDeOtroNombre $e) {
            // Se pregunta: ese WhatsApp ya es de otra persona guardada.
            $_SESSION['mostrador_form']['cliente_confirmar'] = ['id' => $e->clienteId, 'nombre' => $e->nombre];
            flash_set('error', $e->getMessage());
            redirigir('/panel/mostrador#cobrar');
        } catch (\DomainException $e) {
            flash_set('error', $e->getMessage());
            redirigir('/panel/mostrador#cobrar');
        }

        unset($_SESSION['mostrador_form']);
        $this->despuesDeVender($sedeId);
        if ($metodo === 'fiado') {
            // Si tenía saldo a favor (abonó y luego se anuló una venta), esta
            // venta lo usa primero: se dice, no pasa en silencio.
            $venta = Venta::buscar($ventaId, $sedeId);
            $saldo = Fiado::saldo($negocioId, (int) $venta['cliente_id']);
            $antes = $saldo - (int) $venta['total'];
            if ($antes < 0) {
                flash_set('ok', 'Se usó su saldo a favor de ' . pesos(min(-$antes, (int) $venta['total'])) . '. '
                    . ($saldo > 0 ? 'Ahora debe ' . pesos($saldo) . '.' : ($saldo < 0 ? 'Le quedan ' . pesos(-$saldo) . ' a favor.' : 'Queda a paz y salvo.')));
            }
        }
        redirigir('/panel/mostrador?venta=' . $ventaId);
    }

    /** Tiquete vacío y token nuevo: el formulario viejo ya no puede cobrar otra vez. */
    private function despuesDeVender(int $sedeId): void
    {
        unset($_SESSION['mostrador_form']);
        $this->guardarCarrito($sedeId, ['cantidades' => [], 'token' => Venta::tokenNuevo()]);
    }

    public function tiquete(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $venta = Venta::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($venta === null) {
            flash_set('error', 'Esa venta no existe en esta sede.');
            redirigir('/panel/mostrador');
        }

        // Si se anula una venta fiada a la que ya abonó, queda saldo a favor: se avisa antes.
        $aFavorSiAnula = 0;
        if ($venta['metodo'] === 'fiado' && (int) $venta['anulada'] === 0 && $venta['cliente_id'] !== null) {
            $aFavorSiAnula = max(0, (int) $venta['total'] - Fiado::saldo((int) $negocio['negocio_id'], (int) $venta['cliente_id']));
        }

        ver('panel/venta_tiquete', [
            'aFavorSiAnula' => $aFavorSiAnula,
            'titulo'  => 'Venta #' . (int) $venta['id'] . ' · Veci',
            'activo'  => 'mostrador',
            'negocio' => $negocio,
            'venta'   => $venta,
            'items'   => Venta::items((int) $venta['id']),
            'ok'      => flash_obtener('ok'),
            'error'   => flash_obtener('error'),
        ], 'panel');
    }

    public function anular(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        Auth::exigirDueno($negocio);
        $id = (int) $parametros['id'];
        if (!csrf_verificar()) {
            redirigir('/panel/mostrador/ventas/' . $id);
        }
        try {
            Venta::anular($id, (int) $negocio['id'], (int) $negocio['usuario_id']);
            flash_set('ok', 'Venta anulada: el inventario volvió y ya no cuenta en la caja.');
            $venta = Venta::buscar($id, (int) $negocio['id']);
            if ($venta !== null && $venta['metodo'] === 'fiado' && $venta['cliente_id'] !== null) {
                $saldo = Fiado::saldo((int) $negocio['negocio_id'], (int) $venta['cliente_id']);
                if ($saldo < 0) {
                    // Ya había abonado a esa venta: la plata queda a su favor, a la vista.
                    flash_set('error', 'Ojo: ' . $venta['cliente_nombre'] . ' ya había abonado, así que queda con un saldo a favor de ' . pesos(-$saldo)
                        . '. Su próxima compra fiada lo usa primero, o devuélveselo y anota un cargo a mano.');
                }
            }
        } catch (\DomainException $e) {
            flash_set('error', $e->getMessage());
        }
        redirigir('/panel/mostrador/ventas/' . $id);
    }

    // ------------------------------------------------------------------

    /** Solo negocios de pedidos: una peluquería no tiene mostrador de productos. */
    private function exigirPedidos(): array
    {
        $negocio = Auth::exigirSesion();
        if (($negocio['tipo_negocio'] ?? 'pedidos') !== 'pedidos') {
            flash_set('error', 'El mostrador es para negocios que venden productos.');
            redirigir('/panel');
        }

        return $negocio;
    }

    /** @return array{cantidades: array<int, float>, token: string} */
    private function carrito(int $sedeId): array
    {
        $carrito = $_SESSION['mostrador'][$sedeId] ?? null;
        if (!is_array($carrito) || !isset($carrito['token'])) {
            $carrito = ['cantidades' => [], 'token' => Venta::tokenNuevo()];
            $this->guardarCarrito($sedeId, $carrito);
        }

        return $carrito;
    }

    private function guardarCarrito(int $sedeId, array $carrito): void
    {
        $_SESSION['mostrador'][$sedeId] = $carrito;
    }

    /**
     * Suma (o reemplaza, si se volvió a pesar) un producto en el tiquete.
     * Avisa si pasa de lo que hay en inventario, pero la última palabra la
     * tiene el cobro (FOR UPDATE).
     */
    private function sumar(int $sedeId, array $producto, float $cantidad, bool $reemplazar): void
    {
        $carrito = $this->carrito($sedeId);
        $id = (int) $producto['id'];
        $porPeso = $producto['vende_por'] === 'peso';
        $nueva = $reemplazar ? $cantidad : ($carrito['cantidades'][$id] ?? 0) + $cantidad;
        $tope = $porPeso ? Venta::MAX_GRAMOS / Producto::GRAMOS_POR_KILO : Venta::MAX_UNIDADES;
        $nueva = min($tope, $nueva);
        if ($producto['stock'] !== null) {
            $disponible = $porPeso ? (int) $producto['stock'] / Producto::GRAMOS_POR_KILO : (int) $producto['stock'];
            if ($nueva > $disponible) {
                flash_set('error', $disponible <= 0
                    ? "Según el inventario no hay {$producto['nombre']}. Si sí tienes, corrige las unidades en Productos."
                    : 'Según el inventario solo quedan ' . Producto::stockLegible($producto) . " de {$producto['nombre']}.");
                if ($disponible <= 0) {
                    return;
                }
                $nueva = $porPeso ? $disponible : floor($disponible);
            }
        }
        // Lo último agregado va de primero en el tiquete: es lo que se acaba de escanear.
        unset($carrito['cantidades'][$id]);
        $carrito['cantidades'] = [$id => $nueva] + $carrito['cantidades'];
        $this->guardarCarrito($sedeId, $carrito);
    }

    /**
     * producto_id => cantidad, solo de productos de esta sede.
     *
     * @return array<int, float>
     */
    private function soloDeLaSede(int $sedeId, array $items): array
    {
        $cantidades = [];
        foreach (Venta::armarLineas($sedeId, Venta::cantidadesValidas($items)) as $linea) {
            $cantidades[(int) $linea['producto_id']] = (float) $linea['cantidad'];
        }

        return $cantidades;
    }

    /** Lo que se había escrito en el cobro antes de un error (para no hacerlo escribir otra vez). */
    private function sacarFormAnterior(): array
    {
        $form = $_SESSION['mostrador_form'] ?? [];
        unset($_SESSION['mostrador_form']);

        return is_array($form) ? $form : [];
    }
}
