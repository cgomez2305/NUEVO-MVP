<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\LimiteTasa;
use App\Models\PlanTratamiento;
use App\Models\Sede;
use App\Services\WebPush;

/**
 * Salud: planes de tratamiento por fases con abonos (ver
 * App\Models\PlanTratamiento). Lo crea cualquiera del equipo (el doctor o
 * la recepción); anular abonos y cerrar planes es del dueño.
 */
class SaludController
{
    // ---------- Panel ----------

    public function lista(array $parametros): void
    {
        $negocio = $this->sedeDeSalud();
        ver('panel/planes', [
            'titulo'  => 'Planes de tratamiento · Veci',
            'activo'  => 'planes',
            'negocio' => $negocio,
            'planes'  => PlanTratamiento::listar((int) $negocio['id']),
            'ok'      => flash_obtener('ok'),
        ], 'panel');
    }

    public function nuevo(array $parametros): void
    {
        $negocio = $this->sedeDeSalud();
        // Desde una cita ("Armar plan"): el paciente ya viene elegido.
        $cita = isset($_GET['cita']) ? Cita::buscar((int) $_GET['cita'], (int) $negocio['id']) : null;
        ver('panel/plan_nuevo', [
            'titulo'  => 'Nuevo plan · Veci',
            'activo'  => 'planes',
            'negocio' => $negocio,
            'cita'    => $cita,
            'error'   => flash_obtener('error'),
        ], 'panel');
    }

    public function crear(array $parametros): void
    {
        $negocio = $this->sedeDeSalud();
        if (!csrf_verificar()) {
            redirigir('/panel/planes/nuevo');
        }
        $citaId = (int) ($_POST['cita_id'] ?? 0);
        $volver = '/panel/planes/nuevo' . ($citaId > 0 ? '?cita=' . $citaId : '');
        $cita = $citaId > 0 ? Cita::buscar($citaId, (int) $negocio['id']) : null;
        if ($cita !== null) {
            $clienteId = (int) $cita['cliente_id'];
        } else {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $telefono = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? '')) ?? '';
            if ($nombre === '' || strlen($telefono) < 7 || empty($_POST['autorizo'])) {
                flash_set('error', 'Escribe el nombre y el WhatsApp del paciente, y confirma que autorizó el uso de sus datos.');
                redirigir($volver);
            }
            $clienteId = Cliente::buscarOCrearDesdePanel((int) $negocio['negocio_id'], $nombre, $telefono);
        }
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        ['fases' => $fases, 'total' => $total] = PlanTratamiento::fasesDesdeFormulario((array) ($_POST['fases'] ?? []));
        if ($titulo === '' || $fases === [] || $total <= 0) {
            flash_set('error', 'Ponle un nombre al plan y al menos una fase con su valor.');
            redirigir($volver);
        }
        $id = PlanTratamiento::crear((int) $negocio['id'], $clienteId, $titulo, $fases, (int) ($_POST['validez_dias'] ?? 30), trim((string) ($_POST['nota'] ?? '')));
        $plan = PlanTratamiento::buscar($id, (int) $negocio['id']);
        flash_set('ok', 'Plan por ' . pesos($total) . ' listo. Mándaselo para que lo apruebe.');
        flash_set('wa_plan', $this->enlaceWhatsapp($plan, PlanTratamiento::mensajePlan($plan, $negocio)));
        redirigir('/panel/planes/' . $id);
    }

    public function ver(array $parametros): void
    {
        [$negocio, $plan] = $this->planDelPanel($parametros);
        ver('panel/plan_tratamiento', [
            'titulo'      => $plan['titulo'] . ' · ' . $plan['cliente_nombre'] . ' · Veci',
            'activo'      => 'planes',
            'negocio'     => $negocio,
            'plan'        => $plan,
            'fases'       => PlanTratamiento::fases((int) $plan['id']),
            'abonos'      => PlanTratamiento::abonos((int) $plan['id']),
            'citas'       => PlanTratamiento::citas((int) $plan['id']),
            'vinculables' => $plan['estado'] === 'aprobado' ? PlanTratamiento::citasVinculables($plan) : [],
            'recordar'    => PlanTratamiento::puedeRecordarSaldo($plan),
            'whatsapp'    => flash_obtener('wa_plan'),
            'ok'          => flash_obtener('ok'),
            'error'       => flash_obtener('error'),
        ], 'panel');
    }

    public function abonar(array $parametros): void
    {
        [$negocio, $plan] = $this->planDelPost($parametros);
        $monto = dinero_desde_texto((string) ($_POST['monto'] ?? ''));
        $error = PlanTratamiento::abonar((int) $plan['id'], (int) $negocio['id'], $monto, (string) ($_POST['metodo'] ?? 'efectivo'), trim((string) ($_POST['nota'] ?? '')), isset($negocio['usuario_id']) ? (int) $negocio['usuario_id'] : null);
        flash_set($error === null ? 'ok' : 'error', $error ?? 'Abono de ' . pesos($monto) . ' anotado.');
        redirigir('/panel/planes/' . $plan['id'] . '#abonos');
    }

    public function anularAbono(array $parametros): void
    {
        [$negocio, $plan] = $this->planDelPost($parametros);
        Auth::exigirDueno($negocio);
        flash_set(...(PlanTratamiento::anularAbono((int) $parametros['abono'], (int) $plan['id'], (int) $negocio['id'])
            ? ['ok', 'Abono anulado.']
            : ['error', 'Solo se anula un abono del mismo día (el cierre de caja de días pasados no cambia).']));
        redirigir('/panel/planes/' . $plan['id'] . '#abonos');
    }

    public function vincular(array $parametros): void
    {
        [, $plan] = $this->planDelPost($parametros);
        $ok = PlanTratamiento::vincularCita($plan, (int) ($_POST['fase_id'] ?? 0), (int) ($_POST['cita_id'] ?? 0));
        flash_set($ok ? 'ok' : 'error', $ok ? 'La cita quedó en el plan: va por cuenta de los abonos.' : 'No se pudo: elige una cita del paciente sin atender y una fase con sesiones libres.');
        redirigir('/panel/planes/' . $plan['id'] . '#sesiones');
    }

    public function desvincular(array $parametros): void
    {
        [, $plan] = $this->planDelPost($parametros);
        if (!PlanTratamiento::desvincularCita($plan, (int) $parametros['cita'])) {
            flash_set('error', 'Esa cita ya se atendió: queda en el plan.');
        }
        redirigir('/panel/planes/' . $plan['id'] . '#sesiones');
    }

    public function cerrar(array $parametros): void
    {
        [$negocio, $plan] = $this->planDelPost($parametros);
        Auth::exigirDueno($negocio);
        $estado = (string) ($_POST['estado'] ?? '');
        if (PlanTratamiento::cerrar((int) $plan['id'], (int) $negocio['id'], $estado)) {
            flash_set('ok', $estado === 'terminado' ? 'Plan terminado.' : 'Plan cancelado.');
        } else {
            flash_set('error', 'Solo se termina un plan aprobado.');
        }
        redirigir('/panel/planes/' . $plan['id']);
    }

    /** Recordatorio de saldo (Ley 2300): solo en horario permitido y uno por semana. */
    public function recordarSaldo(array $parametros): void
    {
        [$negocio, $plan] = $this->planDelPost($parametros);
        $puede = PlanTratamiento::puedeRecordarSaldo($plan);
        if (!$puede['permitido']) {
            flash_set('error', (string) $puede['razon']);
            redirigir('/panel/planes/' . $plan['id']);
        }
        if (!PlanTratamiento::marcarSaldoRecordado((int) $plan['id'], (int) $negocio['id'])) {
            flash_set('error', 'Ya le recordaste esta semana: la ley permite un recordatorio de cobro por semana.');
            redirigir('/panel/planes/' . $plan['id']);
        }
        header('Location: ' . $this->enlaceWhatsapp($plan, PlanTratamiento::mensajeSaldo($plan, $negocio)));
        exit;
    }

    // ---------- Paciente ----------

    public function verPaciente(array $parametros): void
    {
        $plan = PlanTratamiento::buscarPorToken((string) $parametros['token']);
        $sede = $plan !== null ? Sede::buscarPorId((int) $plan['sede_id']) : null;
        if ($plan === null || $sede === null) {
            abortar404();
        }
        ver('tienda/plan_tratamiento', [
            'titulo'  => 'Tu plan de tratamiento · ' . nombre_publico_sede($sede),
            'negocio' => $sede,
            'plan'    => $plan,
            'fases'   => PlanTratamiento::fases((int) $plan['id']),
            'abonos'  => array_values(array_filter(PlanTratamiento::abonos((int) $plan['id']), fn ($a) => (int) $a['anulado'] === 0)),
            'vencido' => PlanTratamiento::vencido($plan),
            'ok'      => flash_obtener('ok'),
            'error'   => flash_obtener('error'),
        ], 'tienda');
    }

    public function responder(array $parametros): void
    {
        $plan = PlanTratamiento::buscarPorToken((string) $parametros['token']);
        if ($plan === null) {
            abortar404();
        }
        $volver = '/plan/' . $plan['token'];
        if (!csrf_verificar()) {
            redirigir($volver);
        }
        $clave = 'plan|' . $plan['id'];
        if (LimiteTasa::excedido('plan', $clave, 6, 3600)) {
            flash_set('error', 'Demasiados intentos. Escríbele al consultorio por WhatsApp.');
            redirigir($volver);
        }
        LimiteTasa::registrar('plan', $clave);
        $aprobar = ($_POST['respuesta'] ?? '') === 'aprobar';
        if (PlanTratamiento::responder($plan, $aprobar)) {
            flash_set('ok', $aprobar ? 'Aprobaste tu plan. El consultorio ya puede agendar tus sesiones.' : 'Le avisamos al consultorio que por ahora no lo apruebas.');
            WebPush::notificarSede((int) $plan['sede_id'], $aprobar ? 'Plan aprobado' : 'Plan no aprobado', $plan['cliente_nombre'] . ' respondió su plan de tratamiento', '/panel/planes/' . (int) $plan['id']);
        } else {
            flash_set('error', 'Este plan ya no se puede responder (venció o ya lo respondiste).');
        }
        redirigir($volver);
    }

    // ---------- Ayudas ----------

    private function sedeDeSalud(): array
    {
        $negocio = Auth::exigirSesion();
        if (!PlanTratamiento::esSalud($negocio)) {
            abortar404();
        }

        return $negocio;
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function planDelPanel(array $parametros): array
    {
        $negocio = $this->sedeDeSalud();
        $plan = PlanTratamiento::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($plan === null) {
            abortar404();
        }

        return [$negocio, $plan];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function planDelPost(array $parametros): array
    {
        [$negocio, $plan] = $this->planDelPanel($parametros);
        if (!csrf_verificar()) {
            redirigir('/panel/planes/' . $plan['id']);
        }

        return [$negocio, $plan];
    }

    private function enlaceWhatsapp(array $plan, string $texto): string
    {
        return 'https://wa.me/57' . preg_replace('/\D+/', '', (string) $plan['cliente_telefono']) . '?text=' . rawurlencode($texto);
    }
}
