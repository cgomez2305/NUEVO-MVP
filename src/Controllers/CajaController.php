<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\CierreCaja;

/**
 * Cierre de caja diario de la sede activa. Lo hace quien cierra el local
 * (dueño o colaborador): es operación del día, no configuración.
 */
class CajaController
{
    public function ver(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $sedeId = (int) $negocio['id'];
        $fecha = $this->fechaValida((string) ($_GET['fecha'] ?? ''));
        $cierre = CierreCaja::buscar($sedeId, $fecha);

        ver('panel/caja', [
            'titulo'    => 'Cierre de caja · Veci',
            'activo'    => 'caja',
            'negocio'   => $negocio,
            'fecha'     => $fecha,
            'esHoy'     => $fecha === date('Y-m-d'),
            'cierre'    => $cierre,
            // Cerrado: lo que se contó ese día. Abierto: lo que va del día.
            'resumen'   => $cierre['resumen'] ?? CierreCaja::resumenDelDia($sedeId, $fecha),
            'baseSugerida' => $cierre !== null ? (int) $cierre['base'] : CierreCaja::ultimaBase($sedeId),
            'historial' => CierreCaja::historial($sedeId),
            'ok'        => flash_obtener('ok'),
            'error'     => flash_obtener('error'),
        ], 'panel');
    }

    public function cerrar(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $fecha = $this->fechaValida((string) ($_POST['fecha'] ?? ''));
        if (!csrf_verificar()) {
            redirigir('/panel/caja?fecha=' . $fecha);
        }
        if (trim((string) ($_POST['efectivo_contado'] ?? '')) === '') {
            flash_set('error', 'Escribe cuánto efectivo contaste (si no hay, pon 0).');
            redirigir('/panel/caja?fecha=' . $fecha);
        }

        $cierre = CierreCaja::cerrar(
            (int) $negocio['id'],
            $fecha,
            dinero_desde_texto((string) ($_POST['base'] ?? '')),
            dinero_desde_texto((string) ($_POST['efectivo_contado'] ?? '')),
            trim((string) ($_POST['notas'] ?? '')),
            (int) $negocio['usuario_id'],
            dinero_desde_texto((string) ($_POST['efectivo_servicios'] ?? ''))
        );
        $diferencia = (int) $cierre['diferencia'];
        flash_set('ok', match (true) {
            $diferencia === 0 => 'Caja cerrada: cuadra exacto.',
            $diferencia > 0   => 'Caja cerrada: sobran ' . pesos($diferencia) . '.',
            default           => 'Caja cerrada: faltan ' . pesos(-$diferencia) . '.',
        });
        redirigir('/panel/caja?fecha=' . $fecha);
    }

    /** Un día real, de hoy hacia atrás (no se cierra el futuro). */
    private function fechaValida(string $fecha): string
    {
        $hoy = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || strtotime($fecha) === false || $fecha > $hoy) {
            return $hoy;
        }

        return date('Y-m-d', strtotime($fecha));
    }
}
