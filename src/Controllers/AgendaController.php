<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cita;
use App\Models\Empleado;
use App\Models\Imprevisto;
use App\Models\Servicio;

/**
 * Imprevistos de la agenda desde el panel (ver App\Models\Imprevisto): las
 * reglas (colchón, tolerancia, anticipo si no llega), "voy retrasado",
 * "se me complicó el día", terminar con lo cobrado, "no vino", ajuste de
 * precio, garantía y los avisos al cliente que quedan pendientes.
 */
class AgendaController
{
    /** Reglas de la agenda: las decide el dueño (afectan plata y clientes). */
    public function guardarReglas(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        if (csrf_verificar()) {
            Imprevisto::guardarReglas(
                (int) $negocio['id'],
                (int) ($_POST['colchon_min'] ?? 0),
                (int) ($_POST['tolerancia_min'] ?? 15),
                (string) ($_POST['anticipo_no_asiste'] ?? 'se_pierde')
            );
            flash_set('ok', 'Reglas de la agenda guardadas.');
        }
        redirigir('/panel/servicios');
    }

    public function retraso(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        if (!csrf_verificar()) {
            redirigir('/panel/citas');
        }
        $minutos = (int) ($_POST['minutos'] ?? 0);
        if ($minutos === 0) {
            Imprevisto::quitarRetraso((int) $negocio['id']);
            flash_set('ok', 'Listo: vas al día.');
            redirigir('/panel/citas');
        }
        $afectadas = Imprevisto::avisarRetraso((int) $negocio['id'], $minutos, $this->empleadoDelPost($negocio));
        if ($afectadas === 0) {
            flash_set('ok', 'No quedan citas por atender hoy: nadie a quien avisar.');
            redirigir('/panel/citas');
        }
        $automaticos = Imprevisto::mandarAutomaticos($negocio);
        flash_set('ok', $automaticos > 0
            ? "Les avisamos por WhatsApp a {$automaticos} cliente" . ($automaticos === 1 ? '' : 's') . '.'
            : "Avísales a {$afectadas} cliente" . ($afectadas === 1 ? '' : 's') . ' (abajo tienes el mensaje listo).');
        redirigir('/panel/citas#pq-avisos-imprevisto');
    }

    public function diaComplicado(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        if (!csrf_verificar()) {
            redirigir('/panel/citas');
        }
        $fecha = (string) ($_POST['fecha'] ?? '');
        $motivo = (string) ($_POST['motivo'] ?? '');
        if (!isset(Imprevisto::MOTIVOS_DIA[$motivo]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < date('Y-m-d')) {
            flash_set('error', 'Elige el día (hoy o después) y el motivo.');
            redirigir('/panel/citas');
        }
        // Cerrar un día para reservas es decisión del dueño (como en "Días no
        // disponibles"); un colaborador sí puede pedir que se muevan las citas.
        $empleadoId = $this->empleadoDelPost($negocio);
        $bloquear = isset($_POST['bloquear']) && $negocio['rol'] === 'dueno' && $empleadoId === null;
        $afectadas = Imprevisto::reprogramarDia((int) $negocio['id'], $fecha, $motivo, $bloquear, $empleadoId);
        $automaticos = $afectadas > 0 ? Imprevisto::mandarAutomaticos($negocio) : 0;
        $bloqueo = $bloquear ? ' El día quedó cerrado para reservas.' : '';
        flash_set('ok', match (true) {
            $afectadas === 0 => 'Ese día no tenía citas por mover.' . $bloqueo,
            $automaticos > 0 => "Les avisamos a {$automaticos} cliente" . ($automaticos === 1 ? '' : 's') . ' para que elijan otra hora.' . $bloqueo,
            default          => "{$afectadas} cita" . ($afectadas === 1 ? '' : 's') . ' por mover: avísales abajo, cada cliente elige otra hora desde su enlace.' . $bloqueo,
        });
        redirigir('/panel/citas#pq-avisos-imprevisto');
    }

    /** Terminar con lo que de verdad se cobró (solo se pregunta si el precio no era exacto o hubo ajuste). */
    public function terminar(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $cita = Cita::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($cita === null || !csrf_verificar() || !in_array($cita['estado'], ['confirmada', 'en_curso'], true)) {
            redirigir('/panel/citas');
        }
        $cobrado = isset($_POST['cobrado']) && trim((string) $_POST['cobrado']) !== ''
            ? dinero_desde_texto((string) $_POST['cobrado'])
            : null;
        Cita::terminar((int) $cita['id'], (int) $negocio['id'], $cobrado);
        flash_set('ok', 'Cita de ' . $cita['cliente_nombre'] . ' terminada.');
        redirigir(destino_agenda());
    }

    public function noVino(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $cita = Cita::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($cita === null || !csrf_verificar()) {
            redirigir('/panel/citas');
        }
        // La misma regla que muestra el botón, también en el servidor: no se
        // puede marcar «No vino» una cita que todavía no pasa la tolerancia.
        if (!Imprevisto::puedeMarcarNoVino($cita, $negocio)) {
            flash_set('error', match (true) {
                !empty($cita['imprevisto_motivo']) => 'Esa cita la pediste mover tú: no se puede marcar «No vino».',
                !in_array($cita['estado'], ['pendiente', 'confirmada'], true) => 'Solo se marca «No vino» en una cita pendiente o confirmada.',
                default => 'Todavía no: espera a que pase la tolerancia (y lo que avisó que llegaba tarde).',
            });
            redirigir('/panel/citas');
        }
        $cupon = Imprevisto::noAsistio($cita, $negocio);
        if ($cupon !== null) {
            Imprevisto::mandarAutomaticos($negocio);
        }
        flash_set('ok', $cupon !== null
            ? "Marcada como «No vino». Su anticipo quedó como cupón {$cupon['codigo']} por " . pesos((int) $cupon['valor']) . ': avísale abajo.'
            : 'Marcada como «No vino».');
        redirigir('/panel/citas');
    }

    public function ajuste(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $cita = Cita::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($cita === null || !csrf_verificar()) {
            redirigir('/panel/citas');
        }
        $precio = dinero_desde_texto((string) ($_POST['precio'] ?? ''));
        $motivo = (string) ($_POST['motivo'] ?? '');
        if (!Imprevisto::proponerAjuste((int) $cita['id'], (int) $negocio['id'], $precio, $motivo)) {
            flash_set('error', 'Escribe el nuevo valor y por qué cambia.');
            redirigir('/panel/citas');
        }
        $automaticos = Imprevisto::mandarAutomaticos($negocio);
        flash_set('ok', $automaticos > 0
            ? 'Le mandamos el nuevo valor a ' . $cita['cliente_nombre'] . ' para que lo apruebe.'
            : 'Mándale el nuevo valor a ' . $cita['cliente_nombre'] . ' (abajo está el mensaje) para que lo apruebe.');
        redirigir('/panel/citas#pq-avisos-imprevisto');
    }

    public function garantia(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        // Regalar un servicio es plata del negocio: lo decide el dueño (como los cupones).
        Auth::exigirDueno($negocio);
        $cita = Cita::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($cita === null || !csrf_verificar()) {
            redirigir('/panel/citas');
        }
        $bono = Imprevisto::darGarantia($cita, (int) ($_POST['dias'] ?? 0), (int) $negocio['usuario_id']);
        if ($bono === null) {
            flash_set('error', 'Solo se da garantía a una cita atendida de un servicio que todavía existe.');
            redirigir('/panel/citas');
        }
        $nombre = explode(' ', trim((string) $cita['cliente_nombre']))[0];
        $texto = "Hola {$nombre}, en " . nombre_publico_sede($negocio) . " te damos el retoque de tu {$cita['nombre_servicio']} sin costo"
            . (!empty($bono['vence_en']) ? ' hasta el ' . fecha_larga((string) $bono['vence_en']) : '')
            . '. Aquí la ves y reservas con tu mismo WhatsApp: ' . url_publica('/bono/' . $bono['token']);
        header('Location: https://wa.me/57' . preg_replace('/\D+/', '', (string) $cita['cliente_telefono']) . '?text=' . rawurlencode($texto));
        exit;
    }

    /** Abre WhatsApp con el aviso de imprevisto listo y lo da por mandado. */
    public function avisar(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $cita = null;
        foreach (Imprevisto::porAvisar((int) $negocio['id']) as $fila) {
            if ((int) $fila['id'] === (int) $parametros['id']) {
                $cita = $fila;
            }
        }
        $texto = $cita !== null ? Imprevisto::texto($cita, $negocio) : null;
        if ($texto === null || !csrf_verificar()) {
            redirigir('/panel/citas');
        }
        Imprevisto::marcarAvisado((int) $cita['id'], (int) $negocio['id']);
        header('Location: ' . Imprevisto::enlace($cita, $texto));
        exit;
    }

    /** El profesional elegido en "Voy retrasado" / "Se me complicó el día" (null = todo el negocio). */
    private function empleadoDelPost(array $negocio): ?int
    {
        $empleadoId = (int) ($_POST['empleado_id'] ?? 0);

        return $empleadoId > 0 && Empleado::buscar($empleadoId, (int) $negocio['id']) !== null ? $empleadoId : null;
    }

    /** Usa la duración medida de verdad como la duración del servicio. */
    public function usarDuracionReal(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        $servicioId = (int) $parametros['id'];
        $medida = Imprevisto::duracionesReales((int) $negocio['id'])[$servicioId] ?? null;
        if ($medida !== null && csrf_verificar() && Servicio::buscar($servicioId, (int) $negocio['id']) !== null) {
            $minutos = Imprevisto::redondearDuracion($medida['promedio']);
            Servicio::actualizarDuracion($servicioId, (int) $negocio['id'], $minutos);
            flash_set('ok', "Duración actualizada a {$minutos} min: tu agenda ya reparte los turnos con ese tiempo.");
        }
        redirigir('/panel/servicios');
    }
}
