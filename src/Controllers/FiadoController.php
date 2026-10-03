<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cliente;
use App\Models\Fiado;

/**
 * El fiado del negocio (tiendas, fase 4): quién debe, cuánto y desde
 * cuándo; abonos, cargos a mano, el límite por cliente (solo el dueño) y
 * el recordatorio de pago por WhatsApp dentro de lo que permite la
 * Ley 2300 de 2023. Todo filtrado por el negocio de la sesión.
 */
class FiadoController
{
    public function lista(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $negocioId = (int) $negocio['negocio_id'];
        // "Abrir la cuenta de un cliente" (formulario GET, funciona sin JS).
        if (ctype_digit((string) ($_GET['cliente'] ?? ''))) {
            redirigir('/panel/fiado/' . (int) $_GET['cliente']);
        }
        $conSaldo = Fiado::clientesConSaldo($negocioId);
        $clientes = array_values(array_filter($conSaldo, fn ($c) => (int) $c['saldo'] > 0));

        ver('panel/fiado', [
            'titulo'   => 'Fiado · Veci',
            'activo'   => 'fiado',
            'negocio'  => $negocio,
            'clientes' => $clientes,
            // Saldo a favor (abonó y después se anuló una venta fiada): se ve, no se esconde.
            'aFavor'   => array_values(array_filter($conSaldo, fn ($c) => (int) $c['saldo'] < 0)),
            'todos'    => Fiado::clientesParaElegir($negocioId),
            'total'    => array_sum(array_map(fn ($c) => (int) $c['saldo'], $clientes)),
            'formAnterior' => $this->sacarFormAnterior(),
            'ok'       => flash_obtener('ok'),
            'error'    => flash_obtener('error'),
        ], 'panel');
    }

    /** Cliente nuevo para fiarle (con lo que ya debía en el cuaderno de papel, si se quiere). */
    public function crearCliente(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $negocioId = (int) $negocio['negocio_id'];
        if (!csrf_verificar()) {
            redirigir('/panel/fiado');
        }
        $_SESSION['fiado_form'] = [
            'nombre' => mb_substr((string) ($_POST['nombre'] ?? ''), 0, 120),
            'telefono' => mb_substr((string) ($_POST['telefono'] ?? ''), 0, 20),
            'saldo_inicial' => mb_substr((string) ($_POST['saldo_inicial'] ?? ''), 0, 20),
        ];
        try {
            $textoLimite = trim((string) ($_POST['limite'] ?? ''));
            $cliente = Fiado::crearCuenta(
                $negocioId,
                (int) $negocio['id'],
                (string) ($_POST['nombre'] ?? ''),
                (string) ($_POST['telefono'] ?? ''),
                isset($_POST['autorizo']),
                dinero_desde_texto((string) ($_POST['saldo_inicial'] ?? '')),
                $negocio['rol'] === 'dueno' && $textoLimite !== '' ? dinero_desde_texto($textoLimite) : null,
                (int) $negocio['usuario_id']
            );
        } catch (\DomainException $e) {
            flash_set('error', $e->getMessage());
            redirigir('/panel/fiado#nuevo-cliente');
        }
        unset($_SESSION['fiado_form']);
        if ($cliente['nuevo']) {
            flash_set('ok', 'Cliente listo para fiarle.');
        } else {
            // El WhatsApp ya era de alguien: se muestra su cuenta, sin cargarle nada.
            flash_set('error', 'Ese WhatsApp ya es de «' . $cliente['nombre'] . '»: esta es su cuenta. No se le cargó nada ni se cambió su límite; si te debía algo más, cárgalo aquí.');
        }
        redirigir('/panel/fiado/' . $cliente['id']);
    }

    public function detalle(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $negocioId = (int) $negocio['negocio_id'];
        $cliente = $this->cliente($negocioId, $parametros);
        $saldo = Fiado::saldo($negocioId, (int) $cliente['id']);
        $pendientes = Fiado::pendientes($negocioId, (int) $cliente['id']);

        ver('panel/fiado_cliente', [
            'titulo'      => $cliente['nombre'] . ' · Fiado · Veci',
            'activo'      => 'fiado',
            'negocio'     => $negocio,
            'cliente'     => $cliente,
            'saldo'       => $saldo,
            'libreta'     => Fiado::libreta($negocioId, (int) $cliente['id']),
            'pendientes'  => $pendientes,
            'recordar'    => Fiado::puedeRecordar($negocioId, $cliente, $saldo),
            'mensaje'     => $saldo > 0 ? Fiado::mensajeRecordatorio($negocio, $cliente, $saldo, $pendientes) : '',
            'ultimoRecordatorio' => Fiado::ultimoRecordatorio($negocioId, (int) $cliente['id']),
            'ok'          => flash_obtener('ok'),
            'error'       => flash_obtener('error'),
        ], 'panel');
    }

    public function abonar(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $negocioId = (int) $negocio['negocio_id'];
        $cliente = $this->cliente($negocioId, $parametros);
        $volver = '/panel/fiado/' . (int) $cliente['id'];
        if (!csrf_verificar()) {
            redirigir($volver);
        }
        try {
            $queda = Fiado::abonar(
                $negocioId,
                (int) $negocio['id'],
                (int) $cliente['id'],
                dinero_desde_texto((string) ($_POST['monto'] ?? '')),
                (string) ($_POST['metodo'] ?? ''),
                (string) ($_POST['nota'] ?? ''),
                (int) $negocio['usuario_id']
            );
            flash_set('ok', $queda === 0 ? 'Abono anotado: quedó a paz y salvo.' : 'Abono anotado. Queda debiendo ' . pesos($queda) . '.');
        } catch (\DomainException $e) {
            flash_set('error', $e->getMessage());
        }
        redirigir($volver);
    }

    public function cargar(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $negocioId = (int) $negocio['negocio_id'];
        $cliente = $this->cliente($negocioId, $parametros);
        $volver = '/panel/fiado/' . (int) $cliente['id'];
        if (!csrf_verificar()) {
            redirigir($volver);
        }
        try {
            $monto = dinero_desde_texto((string) ($_POST['monto'] ?? ''));
            // Mismo tope que en el mostrador: el límite no se salta con un cargo a mano.
            if ($cliente['fiado_limite'] !== null && Fiado::saldo($negocioId, (int) $cliente['id']) + $monto > (int) $cliente['fiado_limite']) {
                throw new \DomainException('Con ese cargo pasaría su límite de ' . pesos((int) $cliente['fiado_limite']) . '.' . ($negocio['rol'] === 'dueno' ? ' Si quieres, sube el límite primero.' : ''));
            }
            Fiado::cargar($negocioId, (int) $negocio['id'], (int) $cliente['id'], $monto, (string) ($_POST['nota'] ?? ''), (int) $negocio['usuario_id']);
            flash_set('ok', 'Cargo anotado en la cuenta.');
        } catch (\DomainException $e) {
            flash_set('error', $e->getMessage());
        }
        redirigir($volver);
    }

    /** Anular un abono o un cargo a mano: solo el dueño (un abono en efectivo, solo el mismo día). */
    public function anularMovimiento(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        Auth::exigirDueno($negocio);
        $negocioId = (int) $negocio['negocio_id'];
        $cliente = $this->cliente($negocioId, $parametros);
        $volver = '/panel/fiado/' . (int) $cliente['id'];
        if (!csrf_verificar()) {
            redirigir($volver);
        }
        try {
            $mov = Fiado::anularMovimiento($negocioId, (int) $cliente['id'], (int) ($parametros['movimiento'] ?? 0));
            $saldo = Fiado::saldo($negocioId, (int) $cliente['id']);
            flash_set('ok', ($mov['tipo'] === 'abono' ? 'Abono' : 'Cargo') . ' anulado. ' . ($saldo > 0 ? 'Ahora debe ' . pesos($saldo) . '.' : ($saldo < 0 ? 'Queda con saldo a favor de ' . pesos(-$saldo) . '.' : 'Queda a paz y salvo.')));
        } catch (\DomainException $e) {
            flash_set('error', $e->getMessage());
        }
        redirigir($volver);
    }

    /** Límite de fiado del cliente: solo el dueño decide cuánto se le fía. Vacío = sin límite. */
    public function limite(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        Auth::exigirDueno($negocio);
        $negocioId = (int) $negocio['negocio_id'];
        $cliente = $this->cliente($negocioId, $parametros);
        if (csrf_verificar()) {
            $texto = trim((string) ($_POST['limite'] ?? ''));
            Fiado::establecerLimite($negocioId, (int) $cliente['id'], $texto === '' ? null : dinero_desde_texto($texto));
            flash_set('ok', $texto === '' ? 'Sin límite de fiado para este cliente.' : 'Límite de fiado: ' . pesos(dinero_desde_texto($texto)) . '.');
        }
        redirigir('/panel/fiado/' . (int) $cliente['id']);
    }

    /**
     * Abre WhatsApp con el recordatorio, solo si la ley lo permite en este
     * momento (horario, festivos, uno por semana). Se anota antes de abrir:
     * así cuenta para el "uno por semana" aunque no llegue a enviarlo.
     */
    public function recordar(array $parametros): void
    {
        $negocio = $this->exigirPedidos();
        $negocioId = (int) $negocio['negocio_id'];
        $cliente = $this->cliente($negocioId, $parametros);
        $volver = '/panel/fiado/' . (int) $cliente['id'];
        if (!csrf_verificar()) {
            redirigir($volver);
        }
        $saldo = Fiado::saldo($negocioId, (int) $cliente['id']);
        $permiso = Fiado::puedeRecordar($negocioId, $cliente, $saldo);
        if (!$permiso['permitido']) {
            flash_set('error', (string) $permiso['razon']);
            redirigir($volver);
        }
        $texto = Fiado::mensajeRecordatorio($negocio, $cliente, $saldo, Fiado::pendientes($negocioId, (int) $cliente['id']));
        Fiado::registrarRecordatorio($negocioId, (int) $cliente['id'], (int) $negocio['usuario_id'], $saldo);
        header('Location: https://wa.me/57' . Fiado::telefonoValido((string) $cliente['telefono']) . '?text=' . rawurlencode($texto));
        exit;
    }

    // ------------------------------------------------------------------

    private function exigirPedidos(): array
    {
        $negocio = Auth::exigirSesion();
        if (($negocio['tipo_negocio'] ?? 'pedidos') !== 'pedidos') {
            flash_set('error', 'El fiado es para negocios que venden productos.');
            redirigir('/panel');
        }

        return $negocio;
    }

    /** El cliente de la URL, siempre de este negocio (un id ajeno no se ve). */
    private function cliente(int $negocioId, array $parametros): array
    {
        $cliente = ctype_digit((string) ($parametros['cliente'] ?? '')) ? Cliente::buscar((int) $parametros['cliente'], $negocioId) : null;
        if ($cliente === null) {
            flash_set('error', 'Ese cliente no existe en tu negocio.');
            redirigir('/panel/fiado');
        }

        return $cliente;
    }

    private function sacarFormAnterior(): array
    {
        $form = $_SESSION['fiado_form'] ?? [];
        unset($_SESSION['fiado_form']);

        return is_array($form) ? $form : [];
    }
}
