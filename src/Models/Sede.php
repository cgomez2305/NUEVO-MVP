<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Una sede: el punto de atención con tienda pública propia (/t/slug),
 * catálogo, horario y agenda. Todo lo que antes era "del negocio" en el
 * flujo A/B de la maqueta ahora es "de la sede". Un negocio nuevo arranca
 * con una sola sede creada junto con la cuenta.
 */
class Sede
{
    public static function crear(int $negocioId, string $nombre, string $whatsapp, ?string $descripcion = null): int
    {
        $pdo = Database::conexion();

        $stmt = $pdo->prepare(
            'INSERT INTO sedes (negocio_id, slug, nombre, descripcion, whatsapp, inicial)
             VALUES (:negocio_id, :slug, :nombre, :descripcion, :whatsapp, :inicial)'
        );
        $stmt->execute([
            'negocio_id'  => $negocioId,
            'slug'        => self::slugUnico($nombre),
            'nombre'      => $nombre,
            'descripcion' => $descripcion,
            'whatsapp'    => $whatsapp,
            'inicial'     => mb_strtoupper(mb_substr($nombre, 0, 1)),
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function actualizar(int $id, string $nombre, string $whatsapp, bool $aceptaMesa, ?string $direccion = null): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE sedes SET nombre = :nombre, whatsapp = :whatsapp, acepta_mesa = :acepta_mesa, direccion = :direccion WHERE id = :id'
        );
        $stmt->execute([
            'nombre'      => $nombre,
            'whatsapp'    => $whatsapp,
            'acepta_mesa' => $aceptaMesa ? 1 : 0,
            'direccion'   => $direccion,
            'id'          => $id,
        ]);
    }

    /** Cuántas sedes publicadas tiene un negocio: decide si la tienda pública muestra el nombre de la sede o solo el de la marca. */
    public static function contarPublicadasPorNegocio(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) FROM sedes WHERE negocio_id = :negocio_id AND publicada = 1'
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return (int) $stmt->fetchColumn();
    }

    /** Cuántas sedes tiene un negocio en total (publicadas o no): lo que hace cumplir el límite de sedes del plan (ver planes.sedes_incluidas). */
    public static function contarPorNegocio(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) FROM sedes WHERE negocio_id = :negocio_id'
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Columnas de marca y de plan que se funden en toda consulta de sede de
     * abajo: así cualquier $negocio/$contexto de la app (vienen todos de
     * aquí, directo o vía Auth::exigirSesion) trae de una vez tipo_negocio,
     * color_marca Y los límites/flags del plan vigente, sin que cada sitio
     * que necesita gatear una función tenga que ir a buscar el plan aparte.
     * plan_* con el prefijo es de negocios (el estado de la suscripción);
     * el resto sin prefijo es de planes (el catálogo fijo de 3 planes).
     *
     * También trae el plan Gratis aparte (pg.*) para poder caer a sus
     * límites EN LA LECTURA MISMA cuando plan_vence_en ya pasó — ver
     * aplicarVencimiento() — en vez de depender de que bin/revisar_planes.php
     * ya haya corrido hoy. Si ese cron nunca se configura en el hosting (es
     * opcional, fácil de olvidar), sin esto un plan pago no vuelto a pagar
     * se quedaría con sus beneficios para siempre: el enforcement real no
     * puede depender de un cron externo que nadie garantiza que corra.
     */
    private const SELECT_CON_MARCA_Y_PLAN = "
        SELECT s.*, n.tipo_negocio, n.color_marca, n.nombre AS negocio_nombre,
               n.plan_id, n.plan_estado, n.plan_vence_en, n.plan_ciclo,
               p.nombre AS plan_nombre, p.precio_mensual AS plan_precio_mensual, p.precio_anual AS plan_precio_anual,
               p.limite_pedidos_mes, p.limite_ia_mes, p.incluye_copiloto, p.incluye_estadisticas_completas,
               p.incluye_multisede, p.sedes_incluidas, p.precio_sede_extra,
               pg.nombre AS pg_nombre, pg.limite_pedidos_mes AS pg_limite_pedidos_mes, pg.limite_ia_mes AS pg_limite_ia_mes,
               pg.incluye_copiloto AS pg_incluye_copiloto, pg.incluye_estadisticas_completas AS pg_incluye_estadisticas_completas,
               pg.incluye_multisede AS pg_incluye_multisede, pg.sedes_incluidas AS pg_sedes_incluidas,
               pg.precio_sede_extra AS pg_precio_sede_extra
        FROM sedes s
        JOIN negocios n ON n.id = s.negocio_id
        JOIN planes p ON p.id = n.plan_id
        JOIN planes pg ON pg.nombre = 'gratis'
    ";

    /**
     * Si plan_vence_en ya pasó, pisa los campos efectivos (plan_nombre,
     * límites, incluye_*) con los del plan Gratis y marca plan_estado como
     * degradado — sin tocar la base de datos: eso lo hace el cron cuando
     * corra, esto es solo para que NINGUNA lectura, corra el cron o no,
     * aplique límites de un plan que ya no está pagado. plan_id y
     * plan_vence_en crudos se dejan intactos (son el historial real).
     *
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private static function aplicarVencimiento(array $fila): array
    {
        $vencido = !empty($fila['plan_vence_en']) && $fila['plan_vence_en'] < date('Y-m-d');

        if ($vencido) {
            $fila['plan_nombre'] = $fila['pg_nombre'];
            $fila['limite_pedidos_mes'] = $fila['pg_limite_pedidos_mes'];
            $fila['limite_ia_mes'] = $fila['pg_limite_ia_mes'];
            $fila['incluye_copiloto'] = $fila['pg_incluye_copiloto'];
            $fila['incluye_estadisticas_completas'] = $fila['pg_incluye_estadisticas_completas'];
            $fila['incluye_multisede'] = $fila['pg_incluye_multisede'];
            $fila['sedes_incluidas'] = $fila['pg_sedes_incluidas'];
            $fila['precio_sede_extra'] = $fila['pg_precio_sede_extra'];
            $fila['plan_estado'] = 'degradado_a_gratis';
        }

        foreach (array_keys($fila) as $clave) {
            if (str_starts_with($clave, 'pg_')) {
                unset($fila[$clave]);
            }
        }

        return $fila;
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorNegocio(int $negocioId): array
    {
        $stmt = Database::conexion()->prepare(
            self::SELECT_CON_MARCA_Y_PLAN . ' WHERE s.negocio_id = :negocio_id ORDER BY s.id ASC'
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return array_map([self::class, 'aplicarVencimiento'], $stmt->fetchAll());
    }

    /** Trae la sede con los datos de marca del negocio (tipo_negocio, color_marca) y de su plan vigente ya incluidos. */
    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare(self::SELECT_CON_MARCA_Y_PLAN . ' WHERE s.id = :id');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch();
        return $fila === false ? null : self::aplicarVencimiento($fila);
    }

    public static function buscarPorIdYNegocio(int $id, int $negocioId): ?array
    {
        $sede = self::buscarPorId($id);
        return ($sede !== null && (int) $sede['negocio_id'] === $negocioId) ? $sede : null;
    }

    public static function buscarPorSlugPublicada(string $slug): ?array
    {
        $stmt = Database::conexion()->prepare(
            self::SELECT_CON_MARCA_Y_PLAN . ' WHERE s.slug = :slug AND s.publicada = 1 AND n.suspendido = 0'
        );
        $stmt->execute(['slug' => $slug]);
        $fila = $stmt->fetch();
        return $fila === false ? null : self::aplicarVencimiento($fila);
    }

    public static function guardarFotoMenu(int $id, string $rutaRelativa): void
    {
        $stmt = Database::conexion()->prepare('UPDATE sedes SET menu_foto = :ruta WHERE id = :id');
        $stmt->execute(['ruta' => $rutaRelativa, 'id' => $id]);
    }

    public static function guardarLlaveBreB(int $id, string $tipo, string $valor): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE sedes SET llave_breb_tipo = :tipo, llave_breb_valor = :valor WHERE id = :id'
        );
        $stmt->execute(['tipo' => $tipo, 'valor' => $valor, 'id' => $id]);
    }

    public static function publicar(int $id): void
    {
        $stmt = Database::conexion()->prepare('UPDATE sedes SET publicada = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * En qué paso del onboarding quedó una sede sin publicar, para poder
     * devolverla ahí mismo al iniciar sesión en vez de soltarla en un
     * panel vacío. Es también el guarda de cada paso en
     * OnboardingController: un paso solo se muestra si los anteriores
     * están completos.
     *
     * La foto no es obligatoria: quien arma su carta a mano pasa directo
     * al catálogo. Un catálogo con ítems sin precio (la IA no alcanzó a
     * leerlo) todavía no está completo.
     *
     * @return 'foto'|'productos'|'horario'|'pago'
     */
    public static function siguientePasoOnboarding(array $sede): string
    {
        $sedeId = (int) $sede['id'];
        $esReservas = $sede['tipo_negocio'] === 'reservas';
        $total = $esReservas ? Servicio::contarPorSede($sedeId) : Producto::contarPorSede($sedeId);

        if ($total === 0) {
            return empty($sede['menu_foto']) ? 'foto' : 'productos';
        }
        if (($esReservas ? Servicio::contarSinPrecio($sedeId) : Producto::contarSinPrecio($sedeId)) > 0) {
            return 'productos';
        }
        if ($esReservas && self::horario($sede) === []) {
            return 'horario';
        }

        return 'pago';
    }

    /**
     * Guarda el horario semanal. Formato: día ISO (1=lunes..7=domingo) =>
     * lista de franjas [inicio, fin], ordenadas. Un día sin pausa tiene una
     * franja; uno con almuerzo, dos ({"1":[["08:00","12:00"],["14:00","18:00"]]}).
     *
     * @param array<string, array<int, array{0:string,1:string}>> $horario
     */
    public static function guardarHorario(int $id, array $horario, int $intervaloMin): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE sedes SET horario_atencion = :horario, intervalo_citas_min = :intervalo WHERE id = :id'
        );
        $stmt->execute([
            'horario'   => json_encode($horario, JSON_UNESCAPED_UNICODE),
            'intervalo' => $intervaloMin,
            'id'        => $id,
        ]);
    }

    /**
     * Horario semanal de la sede, siempre en el formato de franjas (ver
     * guardarHorario). Las sedes guardadas antes de que existiera la pausa
     * tienen un solo rango por día (["09:00","18:00"]): se leen igual.
     *
     * @return array<string, array<int, array{0:string,1:string}>>
     */
    public static function horario(array $sede): array
    {
        if (empty($sede['horario_atencion'])) {
            return [];
        }
        $decodificado = json_decode((string) $sede['horario_atencion'], true);

        return is_array($decodificado) ? self::normalizarHorario($decodificado) : [];
    }

    /**
     * @param array<mixed> $crudo
     * @return array<string, array<int, array{0:string,1:string}>>
     */
    public static function normalizarHorario(array $crudo): array
    {
        $horario = [];
        for ($dia = 1; $dia <= 7; $dia++) {
            $valor = $crudo[(string) $dia] ?? $crudo[$dia] ?? null;
            if (!is_array($valor)) {
                continue;
            }
            // Formato viejo: un solo rango ["09:00","18:00"].
            $lista = isset($valor[0]) && is_string($valor[0]) ? [$valor] : $valor;
            $franjas = [];
            foreach ($lista as $franja) {
                if (is_array($franja) && count($franja) === 2 && self::esHora($franja[0] ?? null) && self::esHora($franja[1] ?? null)
                    && self::aMinutos($franja[0]) < self::aMinutos($franja[1])) {
                    $franjas[] = [self::conCero($franja[0]), self::conCero($franja[1])];
                }
            }
            if ($franjas !== []) {
                usort($franjas, fn ($x, $y) => strcmp($x[0], $y[0]));
                $horario[(string) $dia] = $franjas;
            }
        }

        return $horario;
    }

    /**
     * Arma el horario desde el formulario de la semana (panel/_semana.php):
     * abierto_N, inicio_N, fin_N y, si ese día cierra al mediodía, pausa_N
     * con pausa_inicio_N / pausa_fin_N (1=lunes..7=domingo). El dueño
     * piensa "abro a las 8, cierro a las 6 y almuerzo de 12 a 2"; aquí se
     * convierte en dos franjas (8–12 y 2–6), que es lo que usan los cupos.
     *
     * Una pausa que no cabe dentro del día (o que termina antes de empezar)
     * no se aplica: el día queda corrido y su nombre se agrega a $avisos
     * para decírselo al dueño en vez de guardarla mal en silencio.
     *
     * @param array<string, mixed> $post
     * @param array<int, string> $avisos
     * @return array<string, array<int, array{0:string,1:string}>>
     */
    public static function horarioDesdePost(array $post, array &$avisos = []): array
    {
        $nombres = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
        $horario = [];
        for ($dia = 1; $dia <= 7; $dia++) {
            if (empty($post["abierto_{$dia}"])) {
                continue;
            }
            $inicio = (string) ($post["inicio_{$dia}"] ?? '');
            $fin = (string) ($post["fin_{$dia}"] ?? '');
            if (!self::esHora($inicio) || !self::esHora($fin) || self::aMinutos($inicio) >= self::aMinutos($fin)) {
                continue;
            }
            $inicio = self::conCero($inicio);
            $fin = self::conCero($fin);
            $horario[(string) $dia] = [[$inicio, $fin]];

            if (empty($post["pausa_{$dia}"])) {
                continue;
            }
            $pausaInicio = (string) ($post["pausa_inicio_{$dia}"] ?? '');
            $pausaFin = (string) ($post["pausa_fin_{$dia}"] ?? '');
            $valida = self::esHora($pausaInicio) && self::esHora($pausaFin)
                && self::aMinutos($inicio) < self::aMinutos($pausaInicio)
                && self::aMinutos($pausaInicio) < self::aMinutos($pausaFin)
                && self::aMinutos($pausaFin) < self::aMinutos($fin);
            if ($valida) {
                $horario[(string) $dia] = [[$inicio, self::conCero($pausaInicio)], [self::conCero($pausaFin), $fin]];
            } else {
                $avisos[] = $nombres[$dia];
            }
        }

        return $horario;
    }

    /**
     * Lo que muestra el formulario para un día: apertura, cierre y la pausa
     * (el hueco entre la primera y la segunda franja), si la hay.
     *
     * @param array<int, array{0:string,1:string}> $franjas
     * @return array{inicio:string, fin:string, pausa:?array{0:string,1:string}}
     */
    public static function diaParaFormulario(array $franjas): array
    {
        if ($franjas === []) {
            return ['inicio' => '08:00', 'fin' => '18:00', 'pausa' => null];
        }
        $ultima = $franjas[count($franjas) - 1];

        return [
            'inicio' => $franjas[0][0],
            'fin'    => $ultima[1],
            'pausa'  => count($franjas) > 1 ? [$franjas[0][1], $franjas[1][0]] : null,
        ];
    }

    private static function esHora(mixed $valor): bool
    {
        return is_string($valor) && preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $valor) === 1;
    }

    private static function aMinutos(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', $hora));

        return $h * 60 + $m;
    }

    /** "8:00" → "08:00": así las horas se comparan bien como texto. */
    private static function conCero(string $hora): string
    {
        return str_pad($hora, 5, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $post */
    public static function intervaloDesdePost(array $post): int
    {
        $intervalo = (int) ($post['intervalo'] ?? 30);
        return in_array($intervalo, [15, 20, 30, 45, 60], true) ? $intervalo : 30;
    }

    private static function existeSlug(string $slug): bool
    {
        $stmt = Database::conexion()->prepare('SELECT id FROM sedes WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() !== false;
    }

    private static function slugUnico(string $nombre): string
    {
        $base = self::normalizarSlug($nombre);
        $slug = $base;
        $sufijo = 1;

        while (self::existeSlug($slug)) {
            $sufijo++;
            $slug = $base . $sufijo;
        }

        return $slug;
    }

    private static function normalizarSlug(string $texto): string
    {
        $mapa = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'ñ' => 'n', 'Ñ' => 'n', 'ü' => 'u', 'Ü' => 'u',
        ];

        $slug = strtr($texto, $mapa);
        $slug = mb_strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '', $slug) ?? '';
        $slug = mb_substr($slug, 0, 40);

        return $slug === '' ? 'sede' : $slug;
    }
}
