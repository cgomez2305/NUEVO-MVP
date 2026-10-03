<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\CodigoBarras;
use App\Models\Compra;
use App\Models\Producto;

/**
 * Compras a proveedor (tiendas, fase 4, solo el dueño): registrar lo que
 * llegó del distribuidor para que suba el inventario y quede el costo real.
 *
 * Toda la compra en curso es UN formulario (proveedor, líneas con cantidad
 * y costo, y el campo del lector): el primer botón es "Agregar", así el
 * Enter del lector agrega el código y de paso guarda lo que se haya
 * corregido en las líneas. Funciona igual sin JS.
 */
class ComprasController
{
    public function ver(array $parametros): void
    {
        $negocio = $this->exigirDuenoDePedidos();
        $sedeId = (int) $negocio['id'];
        $borrador = $this->borrador($sedeId);
        $busqueda = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        $codigoNuevo = isset($_GET['nuevo']) ? Producto::normalizarCodigo((string) $_GET['nuevo']) : null;

        ver('panel/compras', [
            'titulo'      => 'Compras · Veci',
            'activo'      => 'compras',
            'negocio'     => $negocio,
            'borrador'    => $borrador,
            'lineas'      => $this->lineasParaMostrar($sedeId, $borrador['lineas']),
            'busqueda'    => $busqueda,
            'resultados'  => $busqueda !== '' ? array_values(array_filter(Producto::buscarPorNombre($sedeId, $busqueda, 12), fn ($p) => $p['combo'] === [])) : [],
            'codigoNuevo' => $codigoNuevo,
            'crearNuevo'  => isset($_GET['crear']),
            'sugerencia'  => $codigoNuevo !== null ? CodigoBarras::sugerencia($codigoNuevo) : null,
            'historial'   => Compra::historial($sedeId),
            'ok'          => flash_obtener('ok'),
            'error'       => flash_obtener('error'),
        ], 'panel');
    }

    /** El formulario de la compra en curso: agregar, quitar una línea, vaciar o guardar. */
    public function enviar(array $parametros): void
    {
        $negocio = $this->exigirDuenoDePedidos();
        $sedeId = (int) $negocio['id'];
        if (!csrf_verificar()) {
            redirigir('/panel/compras');
        }
        $borrador = $this->borrador($sedeId);
        $borrador['proveedor'] = mb_substr(trim((string) ($_POST['proveedor'] ?? $borrador['proveedor'])), 0, 120);
        $borrador['lineas'] = $this->lineasDelPost();
        $accion = (string) ($_POST['accion'] ?? 'agregar');

        if (str_starts_with($accion, 'quitar-')) {
            $quitar = (int) substr($accion, 7);
            $borrador['lineas'] = array_values(array_filter($borrador['lineas'], fn ($l) => (int) $l['producto_id'] !== $quitar));
            $this->guardarBorrador($sedeId, $borrador);
            redirigir('/panel/compras#compra-lineas');
        }
        if ($accion === 'vaciar') {
            $this->guardarBorrador($sedeId, ['proveedor' => '', 'lineas' => [], 'token' => bin2hex(random_bytes(16))]);
            redirigir('/panel/compras');
        }
        if ($accion === 'guardar') {
            if (!hash_equals($borrador['token'], (string) ($_POST['token'] ?? ''))) {
                flash_set('error', 'Esa compra ya se guardó o cambió en otra pestaña. Revisa el historial.');
                redirigir('/panel/compras');
            }
            try {
                $id = Compra::crear($sedeId, $borrador['proveedor'], Compra::armarLineas($sedeId, $borrador['lineas']), (int) $negocio['usuario_id'], $borrador['token']);
            } catch (\DomainException $e) {
                $this->guardarBorrador($sedeId, $borrador);
                flash_set('error', $e->getMessage());
                redirigir('/panel/compras#compra-lineas');
            }
            $this->guardarBorrador($sedeId, ['proveedor' => '', 'lineas' => [], 'token' => bin2hex(random_bytes(16))]);
            flash_set('ok', 'Compra guardada: el inventario y los costos quedaron al día.');
            redirigir('/panel/compras/' . $id);
        }

        // Agregar: lo que mandó el lector o lo que se escribió.
        $texto = mb_substr(trim((string) ($_POST['codigo'] ?? '')), 0, 60);
        $this->guardarBorrador($sedeId, $borrador);
        if ($texto === '') {
            redirigir('/panel/compras#compra-lineas');
        }
        $codigo = Producto::normalizarCodigo($texto);
        $producto = $codigo !== null ? Producto::buscarPorCodigo($sedeId, $codigo) : null;
        if ($producto === null) {
            $encontrados = array_values(array_filter(Producto::buscarPorNombre($sedeId, $texto, 12), fn ($p) => $p['combo'] === []));
            if (count($encontrados) === 1) {
                $producto = $encontrados[0];
            } elseif (count($encontrados) > 1) {
                redirigir('/panel/compras?q=' . rawurlencode($texto) . '#compra-buscar');
            } elseif ($codigo !== null && ctype_digit($codigo)) {
                // Código que la tienda no tiene: se crea ahí mismo.
                redirigir('/panel/compras?nuevo=' . rawurlencode($codigo) . '#compra-nuevo');
            } else {
                flash_set('error', "No encontramos «{$texto}». Búscalo por otro nombre o créalo.");
                redirigir('/panel/compras?crear=1#compra-nuevo');
            }
        }
        if ($producto['combo'] !== []) {
            flash_set('error', 'Un combo no se compra: agrega sus partes.');
            redirigir('/panel/compras#compra-lineas');
        }
        $this->agregarAlBorrador($sedeId, $producto);
        redirigir('/panel/compras#compra-escanear');
    }

    /** Agregar un producto desde los resultados de búsqueda. */
    public function agregar(array $parametros): void
    {
        $negocio = $this->exigirDuenoDePedidos();
        $sedeId = (int) $negocio['id'];
        if (csrf_verificar()) {
            $producto = Producto::buscar((int) ($_POST['producto_id'] ?? 0), $sedeId);
            if ($producto !== null && $producto['combo'] === []) {
                $this->agregarAlBorrador($sedeId, $producto);
            }
        }
        redirigir('/panel/compras#compra-escanear');
    }

    /** Producto que llegó y la tienda no tenía: se crea con su código, precio de venta y costo. */
    public function crearProducto(array $parametros): void
    {
        $negocio = $this->exigirDuenoDePedidos();
        $sedeId = (int) $negocio['id'];
        if (!csrf_verificar()) {
            redirigir('/panel/compras');
        }
        $nombre = mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 120);
        $precio = dinero_desde_texto((string) ($_POST['precio'] ?? ''));
        $costo = dinero_desde_texto((string) ($_POST['costo'] ?? ''));
        $codigoTexto = trim((string) ($_POST['codigo'] ?? ''));
        $codigo = $codigoTexto !== '' ? Producto::normalizarCodigo($codigoTexto) : null;
        $volver = '/panel/compras?' . ($codigo !== null ? 'nuevo=' . rawurlencode($codigo) : 'crear=1') . '#compra-nuevo';
        $error = match (true) {
            $nombre === ''                          => 'Escribe el nombre del producto.',
            $precio <= 0                            => 'Escribe el precio al que lo vas a vender.',
            $codigoTexto !== '' && $codigo === null => 'Ese código no se ve bien: solo números, letras, puntos o guiones.',
            $codigo !== null && Producto::buscarPorCodigo($sedeId, $codigo) !== null => 'Ya tienes un producto con ese código.',
            default                                 => null,
        };
        if ($error !== null) {
            flash_set('error', $error);
            redirigir($volver);
        }
        $categoria = mb_substr(trim((string) ($_POST['categoria'] ?? '')), 0, 60);
        $id = Producto::crear($sedeId, $nombre, $precio, $categoria !== '' ? $categoria : 'General');
        $aviso = Producto::guardarDatosTienda($id, $sedeId, $codigo, $costo > 0 ? $costo : null, (string) ($_POST['vende_por'] ?? 'unidad'));
        // Nace sin inventario: la compra se lo pone al guardarla.
        $producto = Producto::buscar($id, $sedeId);
        if ($producto !== null) {
            $this->agregarAlBorrador($sedeId, $producto);
        }
        flash_set($aviso !== null ? 'error' : 'ok', $aviso ?? "«{$nombre}» quedó en tu catálogo y en la compra. Escribe cuántos llegaron.");
        redirigir('/panel/compras#compra-lineas');
    }

    public function detalle(array $parametros): void
    {
        $negocio = $this->exigirDuenoDePedidos();
        $compra = Compra::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($compra === null) {
            flash_set('error', 'Esa compra no existe en esta sede.');
            redirigir('/panel/compras');
        }

        ver('panel/compra_detalle', [
            'titulo'  => 'Compra #' . (int) $compra['id'] . ' · Veci',
            'activo'  => 'compras',
            'negocio' => $negocio,
            'compra'  => $compra,
            'items'   => Compra::items((int) $compra['id']),
            'ok'      => flash_obtener('ok'),
        ], 'panel');
    }

    /**
     * ¿Qué es este código? Para el formulario de producto y el JS: el
     * producto de la sede si ya lo tiene y, si no, cómo lo llaman otras
     * tiendas Veci (solo el nombre). Todo el equipo puede consultarlo.
     */
    public function consultarCodigo(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $codigo = Producto::normalizarCodigo((string) ($parametros['codigo'] ?? ''));
        if ($codigo === null) {
            echo json_encode(['codigo' => null, 'producto' => null, 'sugerencia' => null]);
            return;
        }
        $producto = Producto::buscarPorCodigo((int) $negocio['id'], $codigo);
        echo json_encode([
            'codigo'     => $codigo,
            'producto'   => $producto !== null ? ['id' => (int) $producto['id'], 'nombre' => $producto['nombre']] : null,
            'sugerencia' => $producto === null ? CodigoBarras::sugerencia($codigo) : null,
        ], JSON_UNESCAPED_UNICODE);
    }

    // ------------------------------------------------------------------

    private function exigirDuenoDePedidos(): array
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        if (($negocio['tipo_negocio'] ?? 'pedidos') !== 'pedidos') {
            flash_set('error', 'Las compras a proveedor son para negocios que venden productos.');
            redirigir('/panel');
        }

        return $negocio;
    }

    /** @return array{proveedor: string, lineas: array<int, array<string, string>>, token: string} */
    private function borrador(int $sedeId): array
    {
        $borrador = $_SESSION['compra'][$sedeId] ?? null;
        if (!is_array($borrador) || !isset($borrador['token'])) {
            $borrador = ['proveedor' => '', 'lineas' => [], 'token' => bin2hex(random_bytes(16))];
            $this->guardarBorrador($sedeId, $borrador);
        }

        return $borrador;
    }

    private function guardarBorrador(int $sedeId, array $borrador): void
    {
        $_SESSION['compra'][$sedeId] = $borrador;
    }

    /** Las líneas tal como quedaron escritas en el formulario (texto: se validan al guardar). */
    private function lineasDelPost(): array
    {
        $lineas = [];
        foreach ((array) ($_POST['lineas'] ?? []) as $fila) {
            if (!is_array($fila) || !ctype_digit((string) ($fila['producto_id'] ?? ''))) {
                continue;
            }
            $lineas[] = [
                'producto_id' => (string) (int) $fila['producto_id'],
                'cantidad'    => mb_substr(trim((string) ($fila['cantidad'] ?? '')), 0, 12),
                'costo'       => mb_substr(trim((string) ($fila['costo'] ?? '')), 0, 14),
            ];
            if (count($lineas) >= 200) {
                break;
            }
        }

        return $lineas;
    }

    /** Si ya está en la compra, suma 1 (por unidad); si no, entra con el último costo conocido. */
    private function agregarAlBorrador(int $sedeId, array $producto): void
    {
        $borrador = $this->borrador($sedeId);
        $id = (string) (int) $producto['id'];
        foreach ($borrador['lineas'] as $i => $linea) {
            if ($linea['producto_id'] === $id) {
                if ($producto['vende_por'] !== 'peso') {
                    $borrador['lineas'][$i]['cantidad'] = (string) ((int) Compra::cantidadDesdeTexto($linea['cantidad'], false) + 1);
                }
                $lineaMovida = $borrador['lineas'][$i];
                unset($borrador['lineas'][$i]);
                array_unshift($borrador['lineas'], $lineaMovida);
                $this->guardarBorrador($sedeId, $borrador);

                return;
            }
        }
        array_unshift($borrador['lineas'], [
            'producto_id' => $id,
            'cantidad'    => $producto['vende_por'] === 'peso' ? '' : '1',
            'costo'       => $producto['costo'] !== null ? number_format((int) $producto['costo'], 0, '', '.') : '',
        ]);
        $this->guardarBorrador($sedeId, $borrador);
    }

    /**
     * Las líneas del borrador con los datos del producto para pintarlas
     * (las de productos que ya no existen se caen solas).
     *
     * @return array<int, array<string, mixed>>
     */
    private function lineasParaMostrar(int $sedeId, array $lineas): array
    {
        $productos = [];
        foreach (Producto::listarPorSede($sedeId) as $p) {
            $productos[(int) $p['id']] = $p;
        }
        $salida = [];
        foreach ($lineas as $linea) {
            $p = $productos[(int) $linea['producto_id']] ?? null;
            if ($p !== null) {
                $salida[] = $linea + ['producto' => $p];
            }
        }

        return $salida;
    }
}
