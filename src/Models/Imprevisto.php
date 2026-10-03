<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use App\Services\RecordatorioWhatsapp;

/**
 * Lo que pasa cuando la agenda no sale como estaba escrita (ver
 * database/migrations/2026-10-03_13_imprevistos.sql): el negocio va
 * retrasado, se le complicó el día, el trabajo cuesta más de lo pensado, el
 * cliente llega tarde o no llega, o hay que repetir el trabajo.
 *
 * Los avisos al cliente quedan anotados en citas.aviso_imprevisto
 * ('retraso' | 'reprogramar' | 'ajuste') hasta que se mandan: con la API de
 * WhatsApp salen solos; sin ella, la agenda los muestra con el mensaje listo.
 */
class Imprevisto
{
    public const MINUTOS_RETRASO = [10, 15, 20, 30, 45, 60, 90];

    public const MOTIVOS_DIA = [
        'lluvia'     => 'un aguacero fuerte',
        'luz'        => 'se fue la luz',
        'salud'      => 'una enfermedad',
        'calamidad'  => 'una calamidad familiar',
        'transporte' => 'un problema de transporte',
        'otro'       => 'un imprevisto',
    ];

    public const COLCHONES = [0, 5, 10, 15, 20, 30];
    public const TOLERANCIAS = [5, 10, 15, 20, 30];
    public const DIAS_GARANTIA = [7, 15, 30, 60];

    /** Mínimo de citas medidas (Empezar → Terminar) antes de sugerir una duración. */
    public const MUESTRA_MINIMA = 5;

    /**
     * Una cita guarda un solo aviso pendiente. Si llega otro, gana el más
     * importante (un abono o un nuevo valor por aprobar no se pierden porque
     * después el negocio avise un retraso); el de menos peso igual se ve en
     * la página de la cita cuando el cliente abre el enlace.
     */
    private static function sqlAviso(string $nuevo): string
    {
        $orden = ['retraso' => 1, 'reprogramar' => 2, 'ajuste' => 3, 'abono' => 4][$nuevo];

        return "aviso_imprevisto = IF(aviso_imprevisto IS NULL OR FIELD(aviso_imprevisto, 'retraso', 'reprogramar', 'ajuste', 'abono') <= {$orden}, '{$nuevo}', aviso_imprevisto)";
    }

    /**
     * ¿El cliente todavía puede cancelar o mover su cita desde su enlace?
     * Solo si está pendiente o confirmada y no pasó la hora (más la
     * tolerancia y los retrasos avisados): quien no llegó no puede borrar su
     * falta moviéndola después. Una cita que el NEGOCIO pidió mover sí se
     * puede reprogramar siempre.
     */
    public static function clientePuedeGestionar(array $cita, array $sede): bool
    {
        if (!in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
            return false;
        }
        if (!empty($cita['imprevisto_motivo'])) {
            return true;
        }
        $limite = (strtotime((string) $cita['fecha_hora']) ?: 0)
            + ((int) ($sede['tolerancia_min'] ?? 15) + (int) $cita['retraso_cliente_min'] + (int) $cita['retraso_negocio_min']) * 60;

        return time() <= $limite;
    }

    /** ¿Ya se puede marcar "No vino"? Pasada la hora más la tolerancia y los retrasos avisados, y si el negocio no pidió moverla. */
    public static function puedeMarcarNoVino(array $cita, array $sede): bool
    {
        return in_array($cita['estado'], ['pendiente', 'confirmada'], true)
            && empty($cita['imprevisto_motivo'])
            && time() > (strtotime((string) $cita['fecha_hora']) ?: 0)
                + ((int) ($sede['tolerancia_min'] ?? 15) + (int) $cita['retraso_cliente_min'] + (int) $cita['retraso_negocio_min']) * 60;
    }

    // ---------- Reglas de la agenda ----------

    public static function guardarReglas(int $sedeId, int $colchon, int $tolerancia, string $anticipoNoAsiste): void
    {
        Database::conexion()->prepare(
            'UPDATE sedes SET colchon_min = :c, tolerancia_min = :t, anticipo_no_asiste = :a WHERE id = :id'
        )->execute([
            'c'  => in_array($colchon, self::COLCHONES, true) ? $colchon : 0,
            't'  => in_array($tolerancia, self::TOLERANCIAS, true) ? $tolerancia : 15,
            'a'  => $anticipoNoAsiste === 'se_abona' ? 'se_abona' : 'se_pierde',
            'id' => $sedeId,
        ]);
    }

    // ---------- El negocio va retrasado ----------

    /**
     * Marca el retraso en las citas de hoy que todavía no empiezan (las
     * que ya pasaron de su hora sin empezar son justamente las retrasadas)
     * y deja su aviso pendiente. Devuelve cuántas quedaron avisadas.
     */
    public static function avisarRetraso(int $sedeId, int $minutos, ?int $empleadoId = null): int
    {
        if (!in_array($minutos, self::MINUTOS_RETRASO, true)) {
            return 0;
        }
        // Con equipo, el retraso puede ser de una sola persona: solo sus citas.
        $stmt = Database::conexion()->prepare(
            "UPDATE citas SET retraso_negocio_min = :m, cliente_espera = 0, " . self::sqlAviso('retraso') . "
             WHERE sede_id = :s AND estado IN ('pendiente', 'confirmada') AND imprevisto_motivo IS NULL
               AND DATE(fecha_hora) = CURDATE() AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
            . ($empleadoId !== null ? ' AND empleado_id = :e' : '')
        );
        $stmt->execute(['m' => $minutos, 's' => $sedeId] + ($empleadoId !== null ? ['e' => $empleadoId] : []));

        return $stmt->rowCount();
    }

    /** Ya se puso al día: se quita el retraso de las citas de hoy (y su aviso si no salió). */
    public static function quitarRetraso(int $sedeId): void
    {
        Database::conexion()->prepare(
            "UPDATE citas SET retraso_negocio_min = 0, cliente_espera = 0,
                    aviso_imprevisto = IF(aviso_imprevisto = 'retraso', NULL, aviso_imprevisto)
             WHERE sede_id = :s AND DATE(fecha_hora) = CURDATE() AND retraso_negocio_min > 0"
        )->execute(['s' => $sedeId]);
    }

    /** Minutos de retraso que el negocio tiene avisados hoy (0 si va al día). */
    public static function retrasoDeHoy(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            "SELECT COALESCE(MAX(retraso_negocio_min), 0) FROM citas
             WHERE sede_id = :s AND DATE(fecha_hora) = CURDATE() AND estado IN ('pendiente', 'confirmada')"
        );
        $stmt->execute(['s' => $sedeId]);

        return (int) $stmt->fetchColumn();
    }

    // ---------- Se le complicó el día ----------

    /**
     * Pide mover todas las citas que faltan de ese día: quedan marcadas
     * con el motivo y su aviso pendiente, y el cliente elige otra hora
     * desde su enlace (la cita no se cancela: si pagó anticipo, sigue
     * valiendo). Con $bloquear, el día deja de recibir reservas.
     */
    public static function reprogramarDia(int $sedeId, string $fecha, string $motivo, bool $bloquear, ?int $empleadoId = null): int
    {
        if (!isset(self::MOTIVOS_DIA[$motivo]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < date('Y-m-d')) {
            return 0;
        }
        // Cerrar el día es para todo el negocio: si el imprevisto es de una
        // sola persona del equipo, solo se mueven sus citas.
        if ($bloquear && $empleadoId === null) {
            FechaBloqueada::crear($sedeId, $fecha, ucfirst(self::MOTIVOS_DIA[$motivo]));
        }
        $stmt = Database::conexion()->prepare(
            "UPDATE citas SET imprevisto_motivo = :m, " . self::sqlAviso('reprogramar') . ", retraso_negocio_min = 0
             WHERE sede_id = :s AND DATE(fecha_hora) = :f AND fecha_hora >= NOW()
               AND estado IN ('pendiente', 'confirmada')"
            . ($empleadoId !== null ? ' AND empleado_id = :e' : '')
        );
        $stmt->execute(['m' => $motivo, 's' => $sedeId, 'f' => $fecha] + ($empleadoId !== null ? ['e' => $empleadoId] : []));

        return $stmt->rowCount();
    }

    // ---------- Ajuste de precio ----------

    /** El negocio propone un nuevo valor; el cliente lo aprueba o no desde su enlace. */
    public static function proponerAjuste(int $citaId, int $sedeId, int $precio, string $motivo): bool
    {
        $motivo = trim($motivo);
        if ($precio <= 0 || $motivo === '') {
            return false;
        }
        $stmt = Database::conexion()->prepare(
            "UPDATE citas SET ajuste_precio = :p, ajuste_motivo = :m, ajuste_estado = 'pendiente', " . self::sqlAviso('ajuste') . "
             WHERE id = :id AND sede_id = :s AND estado IN ('pendiente', 'confirmada', 'en_curso')"
        );
        $stmt->execute(['p' => $precio, 'm' => mb_substr($motivo, 0, 200), 'id' => $citaId, 's' => $sedeId]);

        return $stmt->rowCount() === 1;
    }

    /** Respuesta del cliente. Aprobado, el nuevo valor queda como lo cobrado (precio_final). */
    public static function responderAjuste(int $citaId, bool $aprueba): bool
    {
        $stmt = Database::conexion()->prepare(
            "UPDATE citas SET ajuste_estado = :e, precio_final = IF(:a = 1, ajuste_precio, precio_final),
                    aviso_imprevisto = IF(aviso_imprevisto = 'ajuste', NULL, aviso_imprevisto)
             WHERE id = :id AND ajuste_estado = 'pendiente' AND estado IN ('pendiente', 'confirmada', 'en_curso')"
        );
        $stmt->execute(['e' => $aprueba ? 'aprobado' : 'rechazado', 'a' => $aprueba ? 1 : 0, 'id' => $citaId]);

        return $stmt->rowCount() === 1;
    }

    // ---------- El cliente ----------

    /** El cliente avisa que llega tarde (desde su enlace). */
    public static function clienteLlegaTarde(int $citaId, int $minutos): bool
    {
        if (!in_array($minutos, [5, 10, 15, 20, 30, 45], true)) {
            return false;
        }
        $stmt = Database::conexion()->prepare(
            "UPDATE citas SET retraso_cliente_min = :m
             WHERE id = :id AND estado IN ('pendiente', 'confirmada') AND DATE(fecha_hora) = CURDATE()"
        );
        $stmt->execute(['m' => $minutos, 'id' => $citaId]);

        return $stmt->rowCount() === 1;
    }

    /** El cliente vio el retraso del negocio y dice que espera. */
    public static function clienteEspera(int $citaId): void
    {
        Database::conexion()->prepare(
            "UPDATE citas SET cliente_espera = 1 WHERE id = :id AND retraso_negocio_min > 0 AND estado IN ('pendiente', 'confirmada')"
        )->execute(['id' => $citaId]);
    }

    /**
     * El cliente no llegó. Lo que pasa con lo que pagó lo decide la regla
     * del negocio (sedes.anticipo_no_asiste), que el cliente vio al reservar:
     * - 'se_pierde': el anticipo queda para el negocio y, si iba con bono,
     *   la sesión cuenta como usada.
     * - 'se_abona': el anticipo pagado vuelve como cupón personal por ese
     *   valor (60 días) y la sesión del bono vuelve.
     * Devuelve el cupón creado, si hubo.
     */
    public static function noAsistio(array $cita, array $sede): ?array
    {
        if (!in_array($cita['estado'], ['pendiente', 'confirmada'], true) || !empty($cita['imprevisto_motivo'])) {
            return null;
        }
        Cita::actualizarEstado((int) $cita['id'], (int) $sede['id'], 'no_asistio');
        if (($sede['anticipo_no_asiste'] ?? 'se_pierde') !== 'se_abona') {
            return null;
        }
        $pdo = Database::conexion();
        // La sesión del bono vuelve (y se anota a qué bono, para poder deshacerlo).
        $uso = $pdo->prepare('SELECT bono_id FROM bono_usos WHERE cita_id = :c');
        $uso->execute(['c' => (int) $cita['id']]);
        $bonoId = $uso->fetchColumn();
        if ($bonoId !== false) {
            Bono::devolverPorCita((int) $cita['id']);
            $pdo->prepare('UPDATE citas SET bono_devuelto_id = :b WHERE id = :id')->execute(['b' => (int) $bonoId, 'id' => (int) $cita['id']]);
        }
        if ($cita['anticipo_estado'] !== 'pagado' || (int) $cita['anticipo_monto'] <= 0) {
            return null;
        }
        $negocioId = (int) $sede['negocio_id'];
        do {
            $codigo = Cupon::codigoAleatorio('ABONO');
        } while (Cupon::existeCodigo($negocioId, $codigo));
        $cuponId = Cupon::crear($negocioId, [
            'codigo'              => $codigo,
            'tipo'                => 'monto',
            'valor'               => (int) $cita['anticipo_monto'],
            'minimo_compra'       => 0,
            'vence_en'            => date('Y-m-d', strtotime('+60 days')),
            'usos_maximos'        => 1,
            'una_vez_por_cliente' => 1,
            'cliente_id'          => (int) $cita['cliente_id'],
        ]);
        // El cliente se entera: queda un aviso con su código (gana a cualquier otro).
        $pdo->prepare('UPDATE citas SET cupon_abono_id = :c, ' . self::sqlAviso('abono') . ' WHERE id = :id')
            ->execute(['c' => $cuponId, 'id' => (int) $cita['id']]);

        return Cupon::buscar($cuponId, $negocioId);
    }

    /**
     * Deshace un "No vino" marcado por error: borra el cupón del abono (si
     * el cliente todavía no lo usó) y vuelve a descontar la sesión del bono.
     * false si el cupón ya se usó: ese abono ya se lo tomó el cliente.
     */
    public static function deshacerNoAsistio(array $cita): bool
    {
        $pdo = Database::conexion();
        if (!empty($cita['cupon_abono_id'])) {
            $usos = $pdo->prepare('SELECT COUNT(*) FROM cupon_usos WHERE cupon_id = :c');
            $usos->execute(['c' => (int) $cita['cupon_abono_id']]);
            if ((int) $usos->fetchColumn() > 0) {
                return false;
            }
            $pdo->prepare('DELETE FROM cupones WHERE id = :c')->execute(['c' => (int) $cita['cupon_abono_id']]);
        }
        if (!empty($cita['bono_devuelto_id'])) {
            Bono::usar((int) $cita['bono_devuelto_id'], (int) $cita['id']);
        }
        $pdo->prepare(
            "UPDATE citas SET cupon_abono_id = NULL, bono_devuelto_id = NULL,
                    aviso_imprevisto = IF(aviso_imprevisto = 'abono', NULL, aviso_imprevisto)
             WHERE id = :id"
        )->execute(['id' => (int) $cita['id']]);

        return true;
    }

    // ---------- Garantía o retoque ----------

    /**
     * Un bono de una sesión a $0 del mismo servicio, ligado a la cita
     * original: al reservar con su WhatsApp, la cita va por cuenta de la
     * garantía. Una sola garantía por cita.
     */
    public static function darGarantia(array $cita, int $dias, ?int $usuarioId): ?array
    {
        if ($cita['estado'] !== 'completada' || empty($cita['servicio_id']) || !in_array($dias, self::DIAS_GARANTIA, true)) {
            return null;
        }
        $pdo = Database::conexion();
        $existe = $pdo->prepare('SELECT token FROM bonos WHERE garantia_de = :c');
        $existe->execute(['c' => (int) $cita['id']]);
        $token = $existe->fetchColumn();
        if (is_string($token)) {
            return Bono::buscarPorToken($token);
        }
        $token = bin2hex(random_bytes(16));
        $pdo->prepare(
            'INSERT INTO bonos (sede_id, paquete_id, garantia_de, cliente_id, servicio_id, nombre_servicio, sesiones_total, precio_pagado, vence_en, token, usuario_id)
             VALUES (:s, NULL, :g, :c, :sv, :n, 1, 0, :v, :t, :u)'
        )->execute([
            's' => (int) $cita['sede_id'], 'g' => (int) $cita['id'], 'c' => (int) $cita['cliente_id'],
            'sv' => (int) $cita['servicio_id'], 'n' => $cita['nombre_servicio'],
            'v' => date('Y-m-d', strtotime("+{$dias} days")), 't' => $token, 'u' => $usuarioId,
        ]);

        return Bono::buscarPorToken($token);
    }

    // ---------- Duración real ----------

    /**
     * Cuánto duran de verdad los servicios, medido de "Empezar" a
     * "Terminar" en los últimos 120 días. Solo los que tienen al menos
     * MUESTRA_MINIMA citas medidas (con menos, un promedio engaña); se
     * descartan mediciones absurdas (menos de 5 min o más de 8 h: alguien
     * olvidó tocar "Terminar").
     *
     * @return array<int, array{citas: int, promedio: int}> por servicio_id
     */
    public static function duracionesReales(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT servicio_id, COUNT(*) AS citas, ROUND(AVG(TIMESTAMPDIFF(MINUTE, iniciada_en, terminada_en))) AS promedio
             FROM citas
             WHERE sede_id = :s AND servicio_id IS NOT NULL AND iniciada_en IS NOT NULL AND terminada_en IS NOT NULL
               AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 120 DAY)
               AND TIMESTAMPDIFF(MINUTE, iniciada_en, terminada_en) BETWEEN 5 AND 480
             GROUP BY servicio_id HAVING COUNT(*) >= :n'
        );
        $stmt->execute(['s' => $sedeId, 'n' => self::MUESTRA_MINIMA]);
        $duraciones = [];
        foreach ($stmt->fetchAll() as $fila) {
            $duraciones[(int) $fila['servicio_id']] = ['citas' => (int) $fila['citas'], 'promedio' => (int) $fila['promedio']];
        }

        return $duraciones;
    }

    /** Redondea a múltiplos de 5 (la grilla de la agenda) una duración medida. */
    public static function redondearDuracion(int $minutos): int
    {
        return max(5, (int) (ceil($minutos / 5) * 5));
    }

    // ---------- Avisos al cliente ----------

    /** @return array<int, array<string, mixed>> citas con un aviso de imprevisto por mandar */
    public static function porAvisar(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono, cu.codigo AS cupon_abono_codigo,
                    cu.valor AS cupon_abono_valor, cu.vence_en AS cupon_abono_vence
             FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN cupones cu ON cu.id = c.cupon_abono_id
             WHERE c.sede_id = :s AND c.aviso_imprevisto IS NOT NULL
               AND (c.estado IN ('pendiente', 'confirmada', 'en_curso') OR (c.estado = 'no_asistio' AND c.aviso_imprevisto = 'abono'))
               AND (c.aviso_imprevisto <> 'retraso' OR DATE(c.fecha_hora) = CURDATE())
             ORDER BY c.fecha_hora"
        );
        $stmt->execute(['s' => $sedeId]);

        return $stmt->fetchAll();
    }

    public static function texto(array $cita, array $sede): ?string
    {
        $nombre = explode(' ', trim((string) $cita['cliente_nombre']))[0];
        $negocio = nombre_publico_sede($sede);
        $ts = strtotime((string) $cita['fecha_hora']) ?: 0;
        $hora = hora_legible(date('H:i', $ts));
        $enlace = url_publica('/cita/' . $cita['token_gestion']);

        return match ((string) $cita['aviso_imprevisto']) {
            'retraso' => "Hola {$nombre}, te escribimos de {$negocio}: vamos con unos {$cita['retraso_negocio_min']} minutos de retraso. "
                . 'Tu cita de las ' . $hora . ' empezaría hacia las ' . hora_legible(date('H:i', $ts + (int) $cita['retraso_negocio_min'] * 60)) . '. '
                . "Si te queda mejor otra hora, cámbiala aquí: {$enlace} ¡Gracias por la paciencia!",
            'reprogramar' => "Hola {$nombre}, te escribimos de {$negocio}: tuvimos " . (self::MOTIVOS_DIA[(string) $cita['imprevisto_motivo']] ?? 'un imprevisto')
                . ' y no vamos a poder atenderte el ' . fecha_larga(date('Y-m-d', $ts)) . " a las {$hora}. Perdón por el cambio. "
                . 'Elige otra hora aquí, sin costo' . ($cita['anticipo_estado'] === 'pagado' ? ' (tu anticipo sigue valiendo)' : '') . ": {$enlace}",
            'abono' => "Hola {$nombre}, te escribimos de {$negocio}: como no pudiste venir a tu cita, tu anticipo de " . pesos((int) $cita['cupon_abono_valor'])
                . " quedó abonado. Usa el código {$cita['cupon_abono_codigo']} en tu próxima reserva"
                . (!empty($cita['cupon_abono_vence']) ? ' (vale hasta el ' . fecha_larga((string) $cita['cupon_abono_vence']) . ')' : '')
                . ': ' . url_publica('/t/' . ($sede['slug'] ?? '')),
            'ajuste' => "Hola {$nombre}, te escribimos de {$negocio} sobre tu {$cita['nombre_servicio']}: "
                . "{$cita['ajuste_motivo']}. El valor quedaría en " . pesos((int) $cita['ajuste_precio']) . '. '
                . "Apruébalo (o no) aquí antes de que sigamos: {$enlace}",
            default => null,
        };
    }

    public static function enlace(array $cita, string $texto): string
    {
        return 'https://wa.me/57' . preg_replace('/\D+/', '', (string) $cita['cliente_telefono']) . '?text=' . rawurlencode($texto);
    }

    public static function marcarAvisado(int $citaId, int $sedeId): void
    {
        Database::conexion()->prepare('UPDATE citas SET aviso_imprevisto = NULL WHERE id = :id AND sede_id = :s')
            ->execute(['id' => $citaId, 's' => $sedeId]);
    }

    /**
     * Manda por la API de WhatsApp los avisos pendientes de la sede (si está
     * configurada). Devuelve cuántos salieron; los que fallen quedan en la
     * lista para mandarlos a mano.
     */
    public static function mandarAutomaticos(array $sede): int
    {
        if (!RecordatorioWhatsapp::disponible()) {
            return 0;
        }
        $enviados = 0;
        foreach (self::porAvisar((int) $sede['id']) as $cita) {
            $texto = self::texto($cita, $sede);
            if ($texto !== null && RecordatorioWhatsapp::enviar($cita, (string) $cita['cliente_telefono'], $texto)) {
                self::marcarAvisado((int) $cita['id'], (int) $sede['id']);
                $enviados++;
            }
        }

        return $enviados;
    }
}
