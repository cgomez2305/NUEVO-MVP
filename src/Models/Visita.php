<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use App\Services\Subida;

/**
 * Visitas a domicilio (ver database/migrations/2026-10-03_16_visitas.sql):
 * un negocio de reservas con modalidad 'domicilio' va a la casa del cliente.
 *
 * Lo que cambia frente a una cita en el local:
 * - El cliente no elige una hora exacta sino una FRANJA de llegada
 *   (mañana / tarde), que es lo que un técnico puede prometer con tráfico
 *   de por medio. Por dentro se reserva el primer turno libre de esa franja,
 *   así la agenda del técnico sigue sin cruces.
 * - El técnico lo asigna el negocio (el primero libre que hace ese servicio):
 *   nadie elige a su plomero, pero sí ve quién le va a llegar (seguridad).
 * - Dirección, zona (con su recargo de transporte), qué pasa y fotos.
 * - "Voy en camino" con hora estimada de llegada.
 * - Evidencia antes / después, que el cliente ve en su enlace.
 */
class Visita
{
    public const MINUTOS_EN_CAMINO = [15, 30, 45, 60, 90];
    public const MAX_FOTOS_CLIENTE = 3;
    public const MAX_FOTOS_EVIDENCIA = 8;

    public static function esDomicilio(array $sede): bool
    {
        return ($sede['tipo_negocio'] ?? '') === 'reservas' && ($sede['modalidad'] ?? 'local') === 'domicilio';
    }

    public static function esVisita(array $cita): bool
    {
        return !empty($cita['franja_inicio']);
    }

    // ---------- Franjas de llegada ----------

    /**
     * Mañana y tarde de ese día, sacadas del horario de la sede (con su
     * pausa de almuerzo: si atiende 8–12 y 14–18, la tarde es 14–18).
     *
     * @return array<string, array{clave: string, etiqueta: string, inicio: string, fin: string}>
     */
    public static function franjas(array $horario, string $fecha): array
    {
        $rangos = $horario[(string) (int) date('N', strtotime($fecha) ?: time())] ?? [];
        $manana = array_values(array_filter($rangos, fn ($r) => $r[0] < '12:00'));
        $tarde = array_values(array_filter($rangos, fn ($r) => $r[1] > '12:00'));
        $franjas = [];
        if ($manana !== []) {
            $franjas['manana'] = [
                'clave' => 'manana', 'etiqueta' => 'Mañana',
                'inicio' => min(array_column($manana, 0)),
                'fin' => min('12:00', max(array_column($manana, 1))),
            ];
        }
        if ($tarde !== []) {
            $franjas['tarde'] = [
                'clave' => 'tarde', 'etiqueta' => 'Tarde',
                'inicio' => max('12:00', min(array_column($tarde, 0))),
                'fin' => max(array_column($tarde, 1)),
            ];
        }

        return $franjas;
    }

    /**
     * Para cada franja del día, el primer turno libre y quién lo toma. Si
     * hay equipo, se reparte entre quienes hacen el servicio (el primero
     * libre); sin equipo, contra la agenda del negocio.
     *
     * @param array<int, array<string, mixed>> $tecnicos vacío = sin equipo
     * @return array<string, array{clave: string, etiqueta: string, inicio: string, fin: string, hora: ?string, empleado: ?array}>
     */
    public static function disponibilidad(array $sede, string $fecha, int $duracion, array $tecnicos): array
    {
        $franjas = self::franjas(Sede::horario($sede), $fecha);
        if ($franjas === [] || FechaBloqueada::estaBloqueada((int) $sede['id'], $fecha)) {
            return [];
        }
        $candidatos = $tecnicos === [] ? [null] : $tecnicos;
        $slotsPorCandidato = [];
        foreach ($candidatos as $i => $tecnico) {
            $empleadoId = $tecnico !== null ? (int) $tecnico['id'] : null;
            $slotsPorCandidato[$i] = Cita::calcularDisponibilidad(
                Empleado::horario($tecnico, $sede),
                (int) $sede['intervalo_citas_min'],
                $fecha,
                $duracion,
                Cita::ocupadosEnFecha((int) $sede['id'], $fecha, null, $empleadoId),
                (int) $sede['colchon_min']
            );
        }
        foreach ($franjas as $clave => $franja) {
            $mejor = null;
            foreach ($candidatos as $i => $tecnico) {
                foreach ($slotsPorCandidato[$i] as $hora) {
                    if ($hora >= $franja['inicio'] && $hora < $franja['fin']) {
                        if ($mejor === null || $hora < $mejor['hora']) {
                            $mejor = ['hora' => $hora, 'empleado' => $tecnico];
                        }
                        break;
                    }
                }
            }
            $franjas[$clave] += $mejor ?? ['hora' => null, 'empleado' => null];
            // Una franja más corta que el servicio nunca tendrá cupo (el sábado
            // de 12 a 1 para una instalación de 3 horas): no se ofrece.
            if ($mejor === null && (strtotime($franja['fin']) - strtotime($franja['inicio'])) / 60 < $duracion) {
                unset($franjas[$clave]);
            }
        }

        return $franjas;
    }

    /** "entre 8:00 a. m. y 12:00 m." */
    public static function textoFranja(array $cita): string
    {
        if (!self::esVisita($cita)) {
            return '';
        }

        return 'entre ' . hora_completa(substr((string) $cita['franja_inicio'], 0, 5)) . ' y ' . hora_completa(substr((string) $cita['franja_fin'], 0, 5));
    }

    // ---------- Datos de la visita ----------

    /** @param array{direccion: string, referencia: string, zona: ?array, problema: string, franja: array, recordar: bool} $datos */
    public static function guardarDatos(int $citaId, array $datos): void
    {
        Database::conexion()->prepare(
            'UPDATE citas SET direccion = :d, direccion_referencia = :r, zona_nombre = :zn, recargo_zona = :zr,
                    problema = :p, franja_inicio = :fi, franja_fin = :ff, recordar_repetir = :rr
             WHERE id = :id'
        )->execute([
            'd'  => mb_substr($datos['direccion'], 0, 200),
            'r'  => $datos['referencia'] !== '' ? mb_substr($datos['referencia'], 0, 160) : null,
            'zn' => $datos['zona'] !== null ? (string) $datos['zona']['nombre'] : null,
            'zr' => $datos['zona'] !== null ? (int) $datos['zona']['costo'] : 0,
            'p'  => mb_substr($datos['problema'], 0, 1000),
            'fi' => $datos['franja']['inicio'],
            'ff' => $datos['franja']['fin'],
            'rr' => $datos['recordar'] ? 1 : 0,
            'id' => $citaId,
        ]);
    }

    /** Al mover una visita a otra hora, la franja prometida pasa a ser la de esa hora (y se olvida el "en camino"). */
    public static function moverFranja(int $citaId, array $sede, string $fecha, string $hora): void
    {
        foreach (self::franjas(Sede::horario($sede), $fecha) as $franja) {
            if ($hora >= $franja['inicio'] && $hora < $franja['fin']) {
                Database::conexion()->prepare(
                    'UPDATE citas SET franja_inicio = :i, franja_fin = :f, en_camino_en = NULL, llegada_estimada = NULL WHERE id = :id'
                )->execute(['i' => $franja['inicio'], 'f' => $franja['fin'], 'id' => $citaId]);

                return;
            }
        }
    }

    /** Enlace de Google Maps a la dirección (lo abre la app de mapas del celular). */
    public static function urlMapa(array $cita, array $sede): string
    {
        $partes = array_filter([(string) $cita['direccion'], (string) ($cita['zona_nombre'] ?? '')]);

        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(implode(', ', $partes) . ', Colombia');
    }

    // ---------- En camino ----------

    public static function enCamino(int $citaId, int $sedeId, int $minutos): void
    {
        $minutos = in_array($minutos, self::MINUTOS_EN_CAMINO, true) ? $minutos : 30;
        Database::conexion()->prepare(
            "UPDATE citas SET en_camino_en = NOW(), llegada_estimada = DATE_ADD(NOW(), INTERVAL {$minutos} MINUTE)
             WHERE id = :id AND sede_id = :s AND estado IN ('pendiente', 'confirmada')"
        )->execute(['id' => $citaId, 's' => $sedeId]);
    }

    public static function mensajeEnCamino(array $cita, array $sede): string
    {
        $nombre = explode(' ', trim((string) $cita['cliente_nombre']))[0];
        $quien = !empty($cita['empleado_nombre']) ? $cita['empleado_nombre'] . ', de ' . nombre_publico_sede($sede) . ',' : 'El técnico de ' . nombre_publico_sede($sede);
        $hora = !empty($cita['llegada_estimada']) ? hora_completa(date('H:i', strtotime((string) $cita['llegada_estimada']))) : '';

        return "Hola {$nombre}, {$quien} va en camino a tu casa" . ($hora !== '' ? " y llega hacia las {$hora}" : '') . '. '
            . 'Aquí ves quién te visita: ' . url_publica('/cita/' . $cita['token_gestion']);
    }

    // ---------- Fotos ----------

    /** @return array<int, array<string, mixed>> */
    public static function fotos(int $citaId, ?string $momento = null): array
    {
        $sql = 'SELECT * FROM cita_fotos WHERE cita_id = :c' . ($momento !== null ? ' AND momento = :m' : '') . ' ORDER BY id';
        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute($momento !== null ? ['c' => $citaId, 'm' => $momento] : ['c' => $citaId]);

        return $stmt->fetchAll();
    }

    /**
     * Guarda las fotos subidas en el campo $campo (input múltiple) hasta el
     * máximo de ese momento. Devuelve cuántas quedaron.
     */
    public static function subirFotos(int $citaId, string $campo, string $momento): int
    {
        if (!in_array($momento, ['cliente', 'antes', 'despues'], true)) {
            return 0;
        }
        $maximo = $momento === 'cliente' ? self::MAX_FOTOS_CLIENTE : self::MAX_FOTOS_EVIDENCIA;
        $cupo = $maximo - count(self::fotos($citaId, $momento));
        $guardadas = 0;
        $insertar = Database::conexion()->prepare('INSERT INTO cita_fotos (cita_id, archivo, momento) VALUES (:c, :a, :m)');
        foreach (Subida::multiples($campo) as $archivo) {
            if ($guardadas >= $cupo) {
                break;
            }
            $nombre = Subida::imagenPrivada($archivo, 'visita');
            if ($nombre !== null) {
                $insertar->execute(['c' => $citaId, 'a' => $nombre, 'm' => $momento]);
                $guardadas++;
            }
        }

        return $guardadas;
    }

    public static function foto(int $fotoId, int $citaId): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM cita_fotos WHERE id = :f AND cita_id = :c');
        $stmt->execute(['f' => $fotoId, 'c' => $citaId]);

        return $stmt->fetch() ?: null;
    }

    public static function eliminarFoto(int $fotoId, int $citaId): void
    {
        $foto = self::foto($fotoId, $citaId);
        if ($foto === null) {
            return;
        }
        Database::conexion()->prepare('DELETE FROM cita_fotos WHERE id = :f')->execute(['f' => $fotoId]);
        $ruta = Subida::rutaPrivada((string) $foto['archivo']);
        if ($ruta !== null && is_file($ruta)) {
            @unlink($ruta);
        }
    }

    /** Envía la foto al navegador (quien llama ya revisó que puede verla). */
    public static function servirFoto(array $foto): never
    {
        $ruta = Subida::rutaPrivada((string) $foto['archivo']);
        if ($ruta === null || !is_file($ruta)) {
            abortar404();
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($ruta);
        exit;
    }

    // ---------- Servicios que se repiten ----------

    /**
     * Clientes a los que ya les toca repetir (o les toca en los próximos 15
     * días): su última cita atendida de un servicio que se repite, sin otra
     * cita de ese servicio después, que pidieron que se lo recordaran y a los
     * que todavía no se les recordó.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function porRepetir(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT c.id, c.cliente_id, c.servicio_id, c.fecha_hora, c.direccion, c.zona_nombre,
                    cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono,
                    s.nombre AS servicio_nombre, s.repetir_cada_meses,
                    DATE_ADD(DATE(c.fecha_hora), INTERVAL s.repetir_cada_meses MONTH) AS toca_el
             FROM citas c
             JOIN servicios s ON s.id = c.servicio_id
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.sede_id = :s AND c.estado = 'completada' AND c.recordar_repetir = 1
               AND c.repetir_avisado_en IS NULL AND s.repetir_cada_meses IS NOT NULL AND s.activo = 1
               AND DATE_ADD(DATE(c.fecha_hora), INTERVAL s.repetir_cada_meses MONTH) <= DATE_ADD(CURDATE(), INTERVAL 15 DAY)
               AND NOT EXISTS (
                   SELECT 1 FROM citas c2
                   WHERE c2.cliente_id = c.cliente_id AND c2.servicio_id = c.servicio_id
                     AND c2.fecha_hora > c.fecha_hora AND c2.estado NOT IN ('cancelada', 'no_asistio')
               )
             ORDER BY toca_el
             LIMIT 100"
        );
        $stmt->execute(['s' => $sedeId]);

        return $stmt->fetchAll();
    }

    /** El cliente pide (o retira) el recordatorio del próximo servicio. */
    public static function pedirRecordatorio(int $citaId, bool $quiere): void
    {
        Database::conexion()->prepare('UPDATE citas SET recordar_repetir = :r WHERE id = :id')
            ->execute(['r' => $quiere ? 1 : 0, 'id' => $citaId]);
    }

    public static function marcarRecordado(int $citaId, int $sedeId): bool
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE citas SET repetir_avisado_en = NOW() WHERE id = :id AND sede_id = :s AND recordar_repetir = 1 AND repetir_avisado_en IS NULL'
        );
        $stmt->execute(['id' => $citaId, 's' => $sedeId]);

        return $stmt->rowCount() === 1;
    }

    public static function mensajeRepetir(array $fila, array $sede): string
    {
        $nombre = explode(' ', trim((string) $fila['cliente_nombre']))[0];
        $meses = (int) $fila['repetir_cada_meses'];

        return "Hola {$nombre}, te escribimos de " . nombre_publico_sede($sede) . ". Ya van {$meses} " . ($meses === 1 ? 'mes' : 'meses')
            . " desde tu {$fila['servicio_nombre']} y nos pediste que te recordáramos el siguiente. "
            . 'Si quieres, lo agendas aquí: ' . url_publica('/t/' . $sede['slug'] . '/reservar/' . (int) $fila['servicio_id']);
    }
}
