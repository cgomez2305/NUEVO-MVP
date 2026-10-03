<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\LimiteTasa;
use App\Models\Sede;
use App\Models\Servicio;
use App\Models\TurnoFila;
use App\Services\WebPush;

/**
 * Fila virtual (ver App\Models\TurnoFila): el cliente que llega sin cita se
 * anota desde la tienda y espera donde quiera; el negocio lo llama por
 * WhatsApp cuando se acerca su turno.
 */
class FilaController
{
    // ---------- Cliente ----------

    public function formulario(array $parametros): void
    {
        $sede = $this->sedeConFila((string) $parametros['slug']);
        ver('tienda/fila', [
            'titulo'     => 'Fila · ' . nombre_publico_sede($sede),
            'negocio'    => $sede,
            'abierta'    => $this->filaDisponible($sede),
            'servicios'  => Servicio::listarPorSede((int) $sede['id'], true),
            'empleados'  => Empleado::listarPorSede((int) $sede['id'], true),
            'enFila'     => count(TurnoFila::deHoy((int) $sede['id'])),
            'error'      => flash_obtener('error'),
        ], 'tienda');
    }

    public function anotarse(array $parametros): void
    {
        $sede = $this->sedeConFila((string) $parametros['slug']);
        $volver = '/t/' . $sede['slug'] . '/fila';
        if (!csrf_verificar()) {
            redirigir($volver);
        }
        if (!$this->filaDisponible($sede)) {
            flash_set('error', 'La fila está cerrada en este momento.');
            redirigir($volver);
        }
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $telefono = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? '')) ?? '';
        if ($nombre === '' || strlen($telefono) < 7 || empty($_POST['autorizo_datos'])) {
            flash_set('error', 'Escribe tu nombre, tu WhatsApp y autoriza que te escribamos para avisarte tu turno.');
            redirigir($volver);
        }
        // Antispam como en reservas: un formulario público no puede llenar la fila de mentiras.
        $ip = ip_cliente();
        if (LimiteTasa::excedido('fila', $ip . '|' . (int) $sede['id'], 5, 3600)) {
            flash_set('error', 'Recibimos muchas solicitudes seguidas desde tu conexión. Escríbele al negocio por WhatsApp.');
            redirigir($volver);
        }
        LimiteTasa::registrar('fila', $ip . '|' . (int) $sede['id']);

        $servicioId = (int) ($_POST['servicio_id'] ?? 0);
        $servicio = $servicioId > 0 ? Servicio::buscar($servicioId, (int) $sede['id']) : null;
        $empleadoId = (int) ($_POST['empleado_id'] ?? 0);
        $empleado = $empleadoId > 0 ? Empleado::buscar($empleadoId, (int) $sede['id']) : null;
        $clienteId = Cliente::buscarOCrear((int) $sede['negocio_id'], $nombre, $telefono, true);
        $token = TurnoFila::anotar(
            (int) $sede['id'],
            $clienteId,
            $servicio !== null ? (int) $servicio['id'] : null,
            $empleado !== null && (int) $empleado['activo'] === 1 ? (int) $empleado['id'] : null
        );
        WebPush::notificarSede((int) $sede['id'], 'Alguien entró a la fila', $nombre . ($servicio !== null ? ' · ' . $servicio['nombre'] : ''), '/panel/fila');
        redirigir('/fila/' . $token);
    }

    public function estado(array $parametros): void
    {
        $turno = TurnoFila::buscarPorToken((string) $parametros['token']);
        if ($turno === null) {
            abortar404();
        }
        $sede = Sede::buscarPorId((int) $turno['sede_id']);
        $vivo = in_array($turno['estado'], ['esperando', 'llamado'], true) && date('Y-m-d', strtotime((string) $turno['creado_en']) ?: 0) === date('Y-m-d');
        ver('tienda/fila_estado', [
            'titulo'   => 'Tu turno · ' . nombre_publico_sede($sede),
            'negocio'  => $sede,
            'turno'    => $turno,
            'vivo'     => $vivo,
            'posicion' => $vivo ? TurnoFila::posicion($turno) : null,
            'refrescarCada' => $vivo ? 30 : null,
            'ok'       => flash_obtener('ok'),
        ], 'tienda');
    }

    public function salir(array $parametros): void
    {
        $turno = TurnoFila::buscarPorToken((string) $parametros['token']);
        if ($turno === null) {
            abortar404();
        }
        if (csrf_verificar()) {
            TurnoFila::seFue((int) $turno['id'], (int) $turno['sede_id']);
            flash_set('ok', 'Saliste de la fila. ¡Vuelve cuando quieras!');
        }
        redirigir('/fila/' . $turno['token']);
    }

    // ---------- Panel ----------

    public function panel(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        ver('panel/fila', [
            'titulo'    => 'Fila de hoy · Veci',
            'activo'    => 'fila',
            'negocio'   => $negocio,
            'turnos'    => TurnoFila::deHoy((int) $negocio['id']),
            'servicios' => Servicio::listarPorSede((int) $negocio['id'], true),
            'empleados' => Empleado::listarPorSede((int) $negocio['id'], true),
            'ok'        => flash_obtener('ok'),
            'error'     => flash_obtener('error'),
        ], 'panel');
    }

    /** Abrir o cerrar la fila: decide el dueño (es cómo atiende el negocio). */
    public function abrirCerrar(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        if (csrf_verificar()) {
            $abrir = ($_POST['abrir'] ?? '') === '1';
            TurnoFila::abrirOCerrar((int) $negocio['id'], $abrir);
            flash_set('ok', $abrir ? 'La fila está abierta: comparte el enlace o el QR en la puerta.' : 'Fila cerrada: nadie más se puede anotar.');
        }
        redirigir('/panel/fila');
    }

    /** Llamar: marca el turno y abre WhatsApp con el aviso listo. */
    public function llamar(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $turno = TurnoFila::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($turno === null || !csrf_verificar()) {
            redirigir('/panel/fila');
        }
        TurnoFila::llamar((int) $turno['id'], (int) $negocio['id']);
        $nombre = explode(' ', trim((string) $turno['cliente_nombre']))[0];
        $texto = "Hola {$nombre}, ya casi es tu turno en " . nombre_publico_sede($negocio) . '. Acércate, te esperamos.';
        header('Location: https://wa.me/57' . preg_replace('/\D+/', '', (string) $turno['cliente_telefono']) . '?text=' . rawurlencode($texto));
        exit;
    }

    public function atender(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $turno = TurnoFila::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($turno === null || !csrf_verificar()) {
            redirigir('/panel/fila');
        }
        $servicio = Servicio::buscar((int) ($_POST['servicio_id'] ?? 0), (int) $negocio['id']);
        $empleadoId = (int) ($_POST['empleado_id'] ?? 0);
        $empleado = $empleadoId > 0 ? Empleado::buscar($empleadoId, (int) $negocio['id']) : null;
        if ($servicio === null) {
            flash_set('error', 'Elige qué servicio le hiciste a ' . $turno['cliente_nombre'] . '.');
            redirigir('/panel/fila');
        }
        $cobradoTexto = trim((string) ($_POST['cobrado'] ?? ''));
        $cobrado = $cobradoTexto !== '' ? dinero_desde_texto($cobradoTexto) : Empleado::condiciones($empleado, $servicio)['precio'];
        $citaId = TurnoFila::atender($turno, $servicio, $empleado, $cobrado);
        flash_set('ok', $citaId !== null
            ? $turno['cliente_nombre'] . ' atendido: ' . pesos($cobrado) . ' quedó en la caja de hoy.'
            : 'Ese turno ya estaba cerrado.');
        redirigir('/panel/fila');
    }

    public function seFue(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        if (csrf_verificar()) {
            TurnoFila::seFue((int) $parametros['id'], (int) $negocio['id']);
        }
        redirigir('/panel/fila');
    }

    // ---------- Ayudas ----------

    private function sedeConFila(string $slug): array
    {
        $sede = Sede::buscarPorSlugPublicada($slug);
        if ($sede === null || $sede['tipo_negocio'] !== 'reservas') {
            abortar404();
        }

        return $sede;
    }

    /** Abierta por el dueño y, si el negocio tiene horario, dentro del horario de hoy. */
    private function filaDisponible(array $sede): bool
    {
        if ((int) $sede['fila_abierta'] !== 1) {
            return false;
        }
        $abierto = negocio_abierto_ahora(Sede::horario($sede));

        return $abierto === null || $abierto['abierto'];
    }
}
