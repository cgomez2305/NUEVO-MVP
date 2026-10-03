<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Cuándo se le puede escribir a un cliente para recordarle lo que debe.
 *
 * Ley 2300 de 2023 ("dejen de fregar"), leída de forma conservadora para
 * la tienda de barrio que cobra el fiado por WhatsApp:
 *   - lunes a viernes de 7:00 a. m. a 7:00 p. m.;
 *   - sábados de 8:00 a. m. a 3:00 p. m.;
 *   - nunca domingos ni festivos;
 *   - máximo un recordatorio por cliente a la semana (eso lo cuenta
 *     App\Models\Fiado, que tiene el registro de los envíos).
 * Siempre en hora de Colombia, venga de donde venga el servidor.
 */
class HorarioCobro
{
    public const ZONA = 'America/Bogota';

    /** [hora de inicio, hora de fin) por día ISO (1 = lunes … 6 = sábado). */
    private const FRANJAS = [
        1 => [7, 19], 2 => [7, 19], 3 => [7, 19], 4 => [7, 19], 5 => [7, 19],
        6 => [8, 15],
    ];

    /**
     * ¿Se puede enviar en este momento? Si no, la razón en palabras del
     * tendero y desde cuándo sí.
     *
     * @return array{permitido: bool, razon: ?string, proximo: ?\DateTimeImmutable}
     */
    public static function evaluar(?\DateTimeImmutable $momento = null): array
    {
        $zona = new \DateTimeZone(self::ZONA);
        $ahora = ($momento ?? new \DateTimeImmutable('now', $zona))->setTimezone($zona);
        $diaSemana = (int) $ahora->format('N');
        $festivo = self::festivo($ahora->format('Y-m-d'));
        $hora = (int) $ahora->format('G');

        $razon = match (true) {
            $diaSemana === 7 => 'Domingo: los recordatorios de cobro solo se pueden enviar de lunes a sábado.',
            $festivo !== null => "Hoy es festivo ({$festivo}): los recordatorios de cobro no se envían en festivos.",
            $hora < self::FRANJAS[$diaSemana][0] || $hora >= self::FRANJAS[$diaSemana][1] => $diaSemana === 6
                ? 'Los sábados los recordatorios de cobro solo se envían de 8:00 a. m. a 3:00 p. m.'
                : 'Entre semana los recordatorios de cobro solo se envían de 7:00 a. m. a 7:00 p. m.',
            default => null,
        };

        return [
            'permitido' => $razon === null,
            'razon'     => $razon,
            'proximo'   => $razon === null ? null : self::proximoPermitido($ahora),
        ];
    }

    /** El primer momento permitido después de $desde (inicio de la franja). */
    public static function proximoPermitido(\DateTimeImmutable $desde): \DateTimeImmutable
    {
        $dia = $desde->setTime(0, 0);
        for ($i = 0; $i < 15; $i++) {
            $diaSemana = (int) $dia->format('N');
            if ($diaSemana !== 7 && self::festivo($dia->format('Y-m-d')) === null) {
                [$inicio, $fin] = self::FRANJAS[$diaSemana];
                $apertura = $dia->setTime($inicio, 0);
                $cierre = $dia->setTime($fin, 0);
                if ($desde < $apertura) {
                    return $apertura;
                }
                if ($desde < $cierre) {
                    return $desde;
                }
            }
            $dia = $dia->modify('+1 day');
        }

        return $dia; // no pasa: nunca hay 15 días seguidos sin día hábil
    }

    /** Nombre del festivo de Colombia en esa fecha (Y-m-d), o null. */
    public static function festivo(string $fecha): ?string
    {
        $anio = (int) substr($fecha, 0, 4);

        return self::festivosDelAnio($anio)[$fecha] ?? null;
    }

    /**
     * Festivos de Colombia (Ley 51 de 1983, "Ley Emiliani"): los fijos, los
     * que se corren al lunes siguiente si no caen en lunes, y los que
     * dependen de la Pascua (Semana Santa y los trasladables que de ella
     * se cuentan).
     *
     * @return array<string, string> Y-m-d => nombre
     */
    public static function festivosDelAnio(int $anio): array
    {
        static $cache = [];
        if (isset($cache[$anio])) {
            return $cache[$anio];
        }
        $zona = new \DateTimeZone(self::ZONA);
        $fecha = static fn (int $mes, int $dia): \DateTimeImmutable => (new \DateTimeImmutable('now', $zona))->setDate($anio, $mes, $dia)->setTime(0, 0);
        $alLunes = static function (\DateTimeImmutable $dia): \DateTimeImmutable {
            $n = (int) $dia->format('N');

            return $n === 1 ? $dia : $dia->modify('+' . (8 - $n) . ' days');
        };

        $festivos = [];
        $poner = static function (\DateTimeImmutable $dia, string $nombre) use (&$festivos): void {
            $festivos[$dia->format('Y-m-d')] = $nombre;
        };

        // Fijos: no se mueven.
        $poner($fecha(1, 1), 'Año Nuevo');
        $poner($fecha(5, 1), 'Día del Trabajo');
        $poner($fecha(7, 20), 'Independencia');
        $poner($fecha(8, 7), 'Batalla de Boyacá');
        $poner($fecha(12, 8), 'Inmaculada Concepción');
        $poner($fecha(12, 25), 'Navidad');

        // Ley Emiliani: se pasan al lunes siguiente.
        $poner($alLunes($fecha(1, 6)), 'Reyes Magos');
        $poner($alLunes($fecha(3, 19)), 'San José');
        $poner($alLunes($fecha(6, 29)), 'San Pedro y San Pablo');
        $poner($alLunes($fecha(8, 15)), 'Asunción de la Virgen');
        $poner($alLunes($fecha(10, 12)), 'Día de la Raza');
        $poner($alLunes($fecha(11, 1)), 'Todos los Santos');
        $poner($alLunes($fecha(11, 11)), 'Independencia de Cartagena');

        // Según la Pascua.
        $pascua = self::domingoDePascua($anio, $zona);
        $poner($pascua->modify('-3 days'), 'Jueves Santo');
        $poner($pascua->modify('-2 days'), 'Viernes Santo');
        $poner($alLunes($pascua->modify('+39 days')), 'Ascensión del Señor');
        $poner($alLunes($pascua->modify('+60 days')), 'Corpus Christi');
        $poner($alLunes($pascua->modify('+68 days')), 'Sagrado Corazón');

        ksort($festivos);

        return $cache[$anio] = $festivos;
    }

    /** Domingo de Pascua (calendario gregoriano, algoritmo anónimo de Meeus/Jones/Butcher). */
    public static function domingoDePascua(int $anio, ?\DateTimeZone $zona = null): \DateTimeImmutable
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return (new \DateTimeImmutable('now', $zona ?? new \DateTimeZone(self::ZONA)))->setDate($anio, $mes, $dia)->setTime(0, 0);
    }
}
