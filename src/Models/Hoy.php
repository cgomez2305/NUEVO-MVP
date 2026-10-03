<?php

declare(strict_types=1);

namespace App\Models;

/**
 * "Hoy": lo que el dueño tiene que hacer ahora, antes que cualquier cifra.
 * Acción primero, información después. Cada tarea sale de un dato real
 * (nada inventado para "llenar") y lleva a la pantalla donde se resuelve.
 *
 * tono: urgente (algo se está demorando), atencion (falta un paso),
 * oportunidad (plata por ganar: clientes que deberían volver).
 */
class Hoy
{
    /** Minutos que un pedido puede esperar sin respuesta antes de ser urgente (el mismo objetivo del tablero de pedidos). */
    private const OBJETIVO_PEDIDO_MIN = 20;

    /** Cuántas tareas sueltas se muestran de un mismo tipo antes de agruparlas. */
    private const MAX_POR_TIPO = 3;

    /**
     * @param array<string, mixed> $negocio contexto de Auth::exigirSesion()
     * @return array<int, array{tono: string, texto: string, detalle: ?string, url: string}>
     */
    public static function tareas(array $negocio, int $aReactivar): array
    {
        $sedeId = (int) $negocio['id'];
        $tareas = $negocio['tipo_negocio'] === 'reservas'
            ? self::deReservas($sedeId)
            : self::dePedidos($sedeId);

        // El copiloto es del dueño (los colaboradores no ven clientes para contactar).
        if ($aReactivar > 0 && ($negocio['rol'] ?? '') === 'dueno') {
            $tareas[] = [
                'tono'    => 'oportunidad',
                'texto'   => $aReactivar === 1 ? '1 cliente debería volver y no ha vuelto' : "{$aReactivar} clientes deberían volver y no han vuelto",
                'detalle' => 'Ya pasaron su ritmo de compra habitual y aceptan promociones. Un mensaje a tiempo los trae de vuelta.',
                'url'     => '/panel/copiloto?segmento=inactivo',
            ];
        }

        $orden = ['urgente' => 0, 'atencion' => 1, 'oportunidad' => 2];
        usort($tareas, fn ($a, $b) => $orden[$a['tono']] <=> $orden[$b['tono']]);

        return $tareas;
    }

    /** @return array<int, array{tono: string, texto: string, detalle: ?string, url: string}> */
    private static function dePedidos(int $sedeId): array
    {
        $tareas = [];
        $demorados = [];
        $nuevos = 0;
        $viejos = 0;
        foreach (Pedido::listarActivosPorSede($sedeId) as $pedido) {
            $minutos = minutos_desde((string) $pedido['creado_en']);
            // De días anteriores y sin cerrar: no es "urgente ya", es orden
            // pendiente (casi siempre se entregó y no se marcó).
            if ($minutos >= 24 * 60) {
                $viejos++;
                continue;
            }
            if (!in_array($pedido['estado'], ['pendiente', 'pagado'], true)) {
                continue;
            }
            if (nivel_espera($minutos, self::OBJETIVO_PEDIDO_MIN) === 'prioridad') {
                $demorados[] = ['pedido' => $pedido, 'minutos' => $minutos];
            } elseif ($pedido['estado'] === 'pendiente') {
                $nuevos++;
            }
        }
        // Los que más esperan, primero.
        usort($demorados, fn ($a, $b) => $b['minutos'] <=> $a['minutos']);
        foreach (array_slice($demorados, 0, self::MAX_POR_TIPO) as $d) {
            $tareas[] = [
                'tono'    => 'urgente',
                'texto'   => 'Pedido #' . (int) $d['pedido']['id'] . ' de ' . self::primerNombre((string) $d['pedido']['cliente_nombre']) . ' lleva ' . texto_espera($d['minutos']),
                'detalle' => null,
                'url'     => '/panel/pedidos/' . (int) $d['pedido']['id'],
            ];
        }
        $resto = count($demorados) - self::MAX_POR_TIPO;
        if ($resto > 0) {
            $tareas[] = ['tono' => 'urgente', 'texto' => ($resto === 1 ? '1 pedido más' : "{$resto} pedidos más") . ' esperando hace rato', 'detalle' => null, 'url' => '/panel/pedidos'];
        }
        if ($nuevos > 0) {
            $tareas[] = [
                'tono'    => 'atencion',
                'texto'   => $nuevos === 1 ? '1 pedido nuevo por confirmar' : "{$nuevos} pedidos nuevos por confirmar",
                'detalle' => null,
                'url'     => '/panel/pedidos',
            ];
        }
        if ($viejos > 0) {
            $tareas[] = [
                'tono'    => 'atencion',
                'texto'   => $viejos === 1 ? '1 pedido de días anteriores sigue abierto' : "{$viejos} pedidos de días anteriores siguen abiertos",
                'detalle' => 'Si ya se entregaron, márcalos: así la caja y el copiloto cuentan bien.',
                'url'     => '/panel/pedidos',
            ];
        }

        return $tareas;
    }

    /** @return array<int, array{tono: string, texto: string, detalle: ?string, url: string}> */
    private static function deReservas(int $sedeId): array
    {
        $tareas = [];
        $hoy = date('Y-m-d');
        $manana = date('Y-m-d', strtotime('+1 day'));
        $sinConfirmar = [];
        $sinAnticipo = [];
        foreach (Cita::listarProximas($sedeId, 200) as $cita) {
            $dia = substr((string) $cita['fecha_hora'], 0, 10);
            $futura = strtotime((string) $cita['fecha_hora']) > time();
            if (!$futura || !in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
                continue;
            }
            if ($dia === $hoy && $cita['estado'] === 'pendiente') {
                $sinConfirmar[] = $cita;
            }
            if (($dia === $hoy || $dia === $manana) && ($cita['anticipo_estado'] ?? '') === 'pendiente') {
                $sinAnticipo[] = $cita;
            }
        }

        foreach (array_slice($sinAnticipo, 0, self::MAX_POR_TIPO) as $cita) {
            $tareas[] = [
                'tono'    => 'urgente',
                'texto'   => 'Falta el anticipo de ' . self::primerNombre((string) $cita['cliente_nombre']) . ' para ' . self::cuando((string) $cita['fecha_hora']),
                'detalle' => 'Sin anticipo, ese cupo puede quedar vacío.',
                'url'     => '/panel/citas',
            ];
        }
        foreach (array_slice($sinConfirmar, 0, self::MAX_POR_TIPO) as $cita) {
            $tareas[] = [
                'tono'    => 'atencion',
                'texto'   => self::primerNombre((string) $cita['cliente_nombre']) . ' no ha confirmado su cita de ' . hora_completa(date('H:i', strtotime((string) $cita['fecha_hora']))),
                'detalle' => (string) $cita['nombre_servicio'],
                'url'     => '/panel/citas',
            ];
        }
        $resto = count($sinConfirmar) - self::MAX_POR_TIPO;
        if ($resto > 0) {
            $tareas[] = ['tono' => 'atencion', 'texto' => ($resto === 1 ? '1 cita más' : "{$resto} citas más") . ' de hoy sin confirmar', 'detalle' => null, 'url' => '/panel/citas'];
        }

        $recordatorios = count(Cita::pendientesDeRecordatorio($sedeId));
        if ($recordatorios > 0) {
            $tareas[] = [
                'tono'    => 'atencion',
                'texto'   => $recordatorios === 1 ? '1 recordatorio de mañana por enviar' : "{$recordatorios} recordatorios de mañana por enviar",
                'detalle' => 'Un recordatorio el día antes baja mucho las citas perdidas.',
                'url'     => '/panel/recordatorios',
            ];
        }
        $espera = ListaEspera::contarPendientesPorSede($sedeId);
        if ($espera > 0) {
            $tareas[] = [
                'tono'    => 'oportunidad',
                'texto'   => $espera === 1 ? '1 persona espera un cupo' : "{$espera} personas esperan un cupo",
                'detalle' => 'Si se libera un horario, avísales primero a ellas.',
                'url'     => '/panel/citas',
            ];
        }

        return $tareas;
    }

    private static function primerNombre(string $nombre): string
    {
        return explode(' ', trim($nombre))[0];
    }

    /** "hoy a las 2:30 p. m." / "mañana a las 9 a. m." */
    private static function cuando(string $fechaHora): string
    {
        $dia = substr($fechaHora, 0, 10) === date('Y-m-d') ? 'hoy' : 'mañana';

        return $dia . ' a las ' . hora_completa(date('H:i', strtotime($fechaHora)));
    }
}
