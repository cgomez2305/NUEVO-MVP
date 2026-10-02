<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cupon;
use App\Models\Fidelidad;

/**
 * Herramientas para que el negocio venda más y que el cliente vuelva:
 * cupones (y, en sus propias secciones, fidelidad, reseñas y referidos).
 * Todo es del NEGOCIO (no de una sede): un cupón sirve en todas sus sedes,
 * como el cliente es el mismo en todas. Solo el dueño las administra.
 */
class CrecimientoController
{
    public function cupones(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        $negocioId = (int) $negocio['negocio_id'];

        ver('panel/cupones', [
            'titulo'      => 'Cupones · Veci',
            'activo'      => 'cupones',
            'negocio'     => $negocio,
            'cupones'     => Cupon::listarPorNegocio($negocioId, 'panel'),
            'personales'  => array_slice(Cupon::listarPorNegocio($negocioId, 'copiloto'), 0, 20),
            'sugerido'    => $this->codigoSugerido($negocioId, (string) $negocio['negocio_nombre']),
            'ok'          => flash_obtener('ok'),
            'error'       => flash_obtener('error'),
            'abierto'     => isset($_GET['nuevo']),
        ], 'panel');
    }

    public function crearCupon(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        $negocioId = (int) $negocio['negocio_id'];

        if (!csrf_verificar()) {
            redirigir('/panel/cupones');
        }

        $codigo = Cupon::normalizarCodigo((string) ($_POST['codigo'] ?? ''));
        $tipo = ($_POST['tipo'] ?? '') === 'monto' ? 'monto' : 'porcentaje';
        $valor = dinero_desde_texto((string) ($_POST['valor'] ?? ''));
        $minimo = dinero_desde_texto((string) ($_POST['minimo_compra'] ?? ''));
        $vence = (string) ($_POST['vence_en'] ?? '');
        $usos = (int) ($_POST['usos_maximos'] ?? 0);

        $error = match (true) {
            strlen($codigo) < 3                                         => 'El código necesita al menos 3 letras o números.',
            Cupon::existeCodigo($negocioId, $codigo)                    => 'Ya tienes un cupón con el código ' . $codigo . '. Usa otro.',
            $tipo === 'porcentaje' && ($valor < 1 || $valor > 90)       => 'El porcentaje va de 1% a 90%.',
            $tipo === 'monto' && $valor < 100                           => 'Escribe cuántos pesos descuenta el cupón.',
            $vence !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $vence) || $vence < date('Y-m-d')) => 'La fecha de vencimiento ya pasó.',
            default                                                     => null,
        };
        if ($error !== null) {
            flash_set('error', $error);
            redirigir('/panel/cupones?nuevo=1');
        }

        Cupon::crear($negocioId, [
            'codigo'              => $codigo,
            'tipo'                => $tipo,
            'valor'               => $valor,
            'minimo_compra'       => $minimo,
            'vence_en'            => $vence !== '' ? $vence : null,
            'usos_maximos'        => $usos > 0 ? $usos : null,
            'una_vez_por_cliente' => isset($_POST['una_vez_por_cliente']),
        ]);
        flash_set('ok', 'Cupón ' . $codigo . ' creado. Compártelo en tu estado de WhatsApp.');
        redirigir('/panel/cupones');
    }

    public function alternarCupon(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        if (csrf_verificar()) {
            Cupon::alternarActivo((int) $parametros['id'], (int) $negocio['negocio_id']);
        }
        redirigir('/panel/cupones');
    }

    public function eliminarCupon(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        if (csrf_verificar()) {
            if (Cupon::eliminar((int) $parametros['id'], (int) $negocio['negocio_id'])) {
                flash_set('ok', 'Cupón eliminado.');
            } else {
                flash_set('error', 'Ese cupón ya se usó: no se borra para que tus pedidos lo sigan nombrando. Puedes pausarlo.');
            }
        }
        redirigir('/panel/cupones');
    }

    public function fidelidad(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        $negocioId = (int) $negocio['negocio_id'];
        $config = Fidelidad::config($negocioId);
        $tarjetas = $config !== null ? Fidelidad::tarjetas($negocioId, $config) : [];
        $meta = (int) ($config['meta'] ?? 8);

        ver('panel/fidelidad', [
            'titulo'    => 'Tarjeta de sellos · Veci',
            'activo'    => 'fidelidad',
            'negocio'   => $negocio,
            'config'    => $config,
            'listos'    => array_values(array_filter($tarjetas, fn ($t) => $t['sellos'] >= $meta)),
            'cerca'     => array_values(array_filter($tarjetas, fn ($t) => $t['sellos'] < $meta && $t['sellos'] >= $meta - 2)),
            'conSellos' => count(array_filter($tarjetas, fn ($t) => $t['sellos'] > 0)),
            'premios'   => Fidelidad::premiosEntregados($negocioId),
            'ok'        => flash_obtener('ok'),
            'error'     => flash_obtener('error'),
        ], 'panel');
    }

    public function guardarFidelidad(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        if (!csrf_verificar()) {
            redirigir('/panel/fidelidad');
        }

        $premio = trim((string) ($_POST['premio'] ?? ''));
        $activa = isset($_POST['activa']);
        if ($activa && mb_strlen($premio) < 3) {
            flash_set('error', 'Escribe el premio: es lo que el cliente quiere ganarse (p. ej. "un almuerzo gratis").');
            redirigir('/panel/fidelidad');
        }
        $antes = Fidelidad::config((int) $negocio['negocio_id']);
        Fidelidad::guardar(
            (int) $negocio['negocio_id'],
            $activa,
            (int) ($_POST['meta'] ?? 8),
            $premio !== '' ? $premio : (string) ($antes['premio'] ?? ''),
            dinero_desde_texto((string) ($_POST['minimo_compra'] ?? ''))
        );
        flash_set('ok', match (true) {
            $antes === null => 'Tarjeta de sellos activada. Tus clientes la ven en la tienda y al confirmar cada compra.',
            !$activa        => 'Tarjeta en pausa: no se suman sellos nuevos y los que llevan se guardan.',
            default         => 'Cambios guardados.',
        });
        redirigir('/panel/fidelidad');
    }

    /**
     * Entregar el premio lo puede hacer quien atiende (dueño o colaborador):
     * pasa en el mostrador. Vuelve a donde se pulsó (detalle del pedido o
     * la página de la tarjeta).
     */
    public function entregarPremio(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $volver = (string) ($_POST['volver'] ?? '');
        $volver = preg_match('#^/panel/[a-z0-9/_-]*$#', $volver) ? $volver : '/panel/fidelidad';

        $config = Fidelidad::config((int) $negocio['negocio_id']);
        $cliente = \App\Models\Cliente::buscar((int) $parametros['cliente'], (int) $negocio['negocio_id']);
        if (!csrf_verificar() || $config === null || $cliente === null) {
            redirigir($volver);
        }

        if (Fidelidad::entregarPremio((int) $negocio['negocio_id'], $config, (int) $cliente['id'], (int) $negocio['usuario_id'])) {
            flash_set('ok', 'Premio entregado a ' . $cliente['nombre'] . '. Su tarjeta vuelve a empezar.');
        } else {
            flash_set('error', $cliente['nombre'] . ' todavía no completa la tarjeta.');
        }
        redirigir($volver);
    }

    private function exigirDueno(): array
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        return $negocio;
    }

    /** Un código fácil de dictar a partir del nombre del negocio: "Doña María" → "DONAMARIA10". */
    private function codigoSugerido(int $negocioId, string $nombre): string
    {
        $ascii = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        $base = substr(strtoupper((string) preg_replace('/[^A-Za-z]/', '', $ascii)), 0, 12);
        $base = $base !== '' ? $base : 'VECI';
        $codigo = $base . '10';

        return Cupon::existeCodigo($negocioId, $codigo) ? Cupon::codigoAleatorio($base) : $codigo;
    }
}
