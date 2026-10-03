<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Empleados/recursos de un negocio de reservas. Si un negocio no tiene
 * ninguno registrado, las citas se agendan contra el negocio completo
 * (comportamiento de siempre, un solo recurso implícito). En cuanto tiene
 * al menos uno activo, el cliente elige con quién agenda y la
 * disponibilidad se calcula por empleado, no por negocio.
 */
class Empleado
{
    public static function crear(int $sedeId, string $nombre): int
    {
        $pdo = Database::conexion();
        $orden = self::contarPorSede($sedeId);

        $stmt = $pdo->prepare(
            'INSERT INTO empleados (sede_id, nombre, orden) VALUES (:sede_id, :nombre, :orden)'
        );
        $stmt->execute(['sede_id' => $sedeId, 'nombre' => $nombre, 'orden' => $orden]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorSede(int $sedeId, bool $soloActivos = false): array
    {
        $sql = 'SELECT * FROM empleados WHERE sede_id = :sede_id';
        if ($soloActivos) {
            $sql .= ' AND activo = 1';
        }
        $sql .= ' ORDER BY orden ASC, id ASC';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['sede_id' => $sedeId]);

        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM empleados WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);

        return $stmt->fetch() ?: null;
    }

    public static function eliminar(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM empleados WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    public static function tieneCitas(int $id): bool
    {
        $stmt = Database::conexion()->prepare('SELECT 1 FROM citas WHERE empleado_id = :e LIMIT 1');
        $stmt->execute(['e' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    public static function contarPorSede(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM empleados WHERE sede_id = :sede_id'
        );
        $stmt->execute(['sede_id' => $sedeId]);

        return (int) $stmt->fetch()['total'];
    }

    // ---------- Perfil (lo que ve el cliente para elegir) ----------

    public const MAX_FOTOS = 9;

    public static function actualizarPerfil(int $id, int $sedeId, string $nombre, string $especialidad, string $bio, ?int $comisionPct): void
    {
        Database::conexion()->prepare(
            'UPDATE empleados SET nombre = :n, especialidad = :e, bio = :b, comision_pct = :c WHERE id = :id AND sede_id = :s'
        )->execute([
            'n'  => mb_substr($nombre, 0, 120),
            'e'  => $especialidad !== '' ? mb_substr($especialidad, 0, 80) : null,
            'b'  => $bio !== '' ? mb_substr($bio, 0, 240) : null,
            'c'  => $comisionPct !== null ? max(0, min(100, $comisionPct)) : null,
            'id' => $id,
            's'  => $sedeId,
        ]);
    }

    public static function guardarFoto(int $id, int $sedeId, ?string $ruta): void
    {
        Database::conexion()->prepare('UPDATE empleados SET foto = :f WHERE id = :id AND sede_id = :s')
            ->execute(['f' => $ruta, 'id' => $id, 's' => $sedeId]);
    }

    public static function alternarActivo(int $id, int $sedeId): void
    {
        Database::conexion()->prepare('UPDATE empleados SET activo = NOT activo WHERE id = :id AND sede_id = :s')
            ->execute(['id' => $id, 's' => $sedeId]);
    }

    // ---------- Horario propio ----------

    /**
     * El horario con el que se reparten los turnos de este profesional: el
     * suyo si lo tiene, si no el de la sede. Su horario vive DENTRO del de
     * la sede: puede entrar más tarde o salir antes, pero nunca se ofrecen
     * turnos con el local cerrado (ni en la pausa de almuerzo de la sede).
     */
    public static function horario(?array $empleado, array $sede): array
    {
        $horarioSede = Sede::horario($sede);
        if ($empleado === null || empty($empleado['horario_atencion'])) {
            return $horarioSede;
        }
        $propio = Sede::horario(['horario_atencion' => $empleado['horario_atencion']]);
        $resultado = [];
        foreach ($propio as $dia => $franjasPropias) {
            $cruce = [];
            foreach ($franjasPropias as [$inicioP, $finP]) {
                foreach ($horarioSede[$dia] ?? [] as [$inicioS, $finS]) {
                    $inicio = max($inicioP, $inicioS);
                    $fin = min($finP, $finS);
                    if ($inicio < $fin) {
                        $cruce[] = [$inicio, $fin];
                    }
                }
            }
            if ($cruce !== []) {
                usort($cruce, fn ($a, $b) => strcmp($a[0], $b[0]));
                $resultado[$dia] = $cruce;
            }
        }

        return $resultado;
    }

    /** null = vuelve a usar el horario de la sede. */
    public static function guardarHorario(int $id, int $sedeId, ?array $horario): void
    {
        Database::conexion()->prepare('UPDATE empleados SET horario_atencion = :h WHERE id = :id AND sede_id = :s')
            ->execute(['h' => $horario !== null ? json_encode($horario) : null, 'id' => $id, 's' => $sedeId]);
    }

    // ---------- Servicios que hace ----------

    /** @return array<int, array{precio: ?int, duracion_min: ?int}> por servicio_id (vacío = hace todos) */
    public static function servicios(int $empleadoId): array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM empleado_servicios WHERE empleado_id = :e');
        $stmt->execute(['e' => $empleadoId]);
        $servicios = [];
        foreach ($stmt->fetchAll() as $fila) {
            $servicios[(int) $fila['servicio_id']] = [
                'precio'       => $fila['precio'] !== null ? (int) $fila['precio'] : null,
                'duracion_min' => $fila['duracion_min'] !== null ? (int) $fila['duracion_min'] : null,
            ];
        }

        return $servicios;
    }

    /**
     * Guarda qué servicios hace y con qué precio/duración propios. Si marca
     * todos sin cambios, se borra todo: "hace todo al precio normal" es el
     * caso por defecto y así un servicio nuevo le queda incluido solo.
     *
     * Devuelve false (sin guardar) si no marcó ninguno: "ninguna fila" quiere
     * decir "hace todos", así que desmarcar todo haría lo contrario de lo
     * que se pidió. Para que no atienda, se pausa.
     *
     * @param array<int, array{hace: bool, precio: ?int, duracion_min: ?int}> $porServicio servicio_id => datos
     */
    public static function guardarServicios(int $empleadoId, int $sedeId, array $porServicio): bool
    {
        if (self::buscar($empleadoId, $sedeId) === null) {
            return false;
        }
        $validos = array_column(Servicio::listarPorSede($sedeId), 'id');
        $validos = array_map('intval', $validos);
        $filas = [];
        $todosNormales = true;
        foreach ($validos as $servicioId) {
            $datos = $porServicio[$servicioId] ?? ['hace' => false, 'precio' => null, 'duracion_min' => null];
            if (!$datos['hace']) {
                $todosNormales = false;
                continue;
            }
            $precio = $datos['precio'] !== null && $datos['precio'] > 0 ? $datos['precio'] : null;
            $duracion = $datos['duracion_min'] !== null && $datos['duracion_min'] >= 5 ? min(600, $datos['duracion_min']) : null;
            if ($precio !== null || $duracion !== null) {
                $todosNormales = false;
            }
            $filas[] = [$servicioId, $precio, $duracion];
        }
        if ($filas === [] && $validos !== []) {
            return false;
        }
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM empleado_servicios WHERE empleado_id = :e')->execute(['e' => $empleadoId]);
        if (!$todosNormales) {
            $insertar = $pdo->prepare('INSERT INTO empleado_servicios (empleado_id, servicio_id, precio, duracion_min) VALUES (:e, :s, :p, :d)');
            foreach ($filas as [$servicioId, $precio, $duracion]) {
                $insertar->execute(['e' => $empleadoId, 's' => $servicioId, 'p' => $precio, 'd' => $duracion]);
            }
        }
        $pdo->commit();

        return true;
    }

    /**
     * Profesionales activos que hacen este servicio: los que no tienen
     * restricción (ninguna fila) y los que lo tienen marcado.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function paraServicio(int $sedeId, int $servicioId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT e.* FROM empleados e
             WHERE e.sede_id = :s AND e.activo = 1
               AND (NOT EXISTS (SELECT 1 FROM empleado_servicios x WHERE x.empleado_id = e.id)
                    OR EXISTS (SELECT 1 FROM empleado_servicios y WHERE y.empleado_id = e.id AND y.servicio_id = :sv))
             ORDER BY e.orden ASC, e.id ASC'
        );
        $stmt->execute(['s' => $sedeId, 'sv' => $servicioId]);

        return $stmt->fetchAll();
    }

    /**
     * Precio y duración del servicio con este profesional (los suyos si los
     * tiene, si no los del servicio). Si el profesional pone precio propio,
     * el precio es exacto aunque el servicio fuera "desde": el tipo y el
     * máximo solo se conservan cuando el precio es el del servicio.
     *
     * @return array{precio: int, duracion_min: int, precio_tipo: string, precio_max: ?int}
     */
    public static function condiciones(?array $empleado, array $servicio): array
    {
        $propio = $empleado !== null ? (self::servicios((int) $empleado['id'])[(int) $servicio['id']] ?? null) : null;
        $precioPropio = $propio['precio'] ?? null;

        return [
            'precio'       => $precioPropio ?? (int) $servicio['precio'],
            'duracion_min' => $propio['duracion_min'] ?? (int) $servicio['duracion_min'],
            'precio_tipo'  => $precioPropio !== null ? 'fijo' : (string) $servicio['precio_tipo'],
            'precio_max'   => $precioPropio !== null ? null : ($servicio['precio_max'] !== null ? (int) $servicio['precio_max'] : null),
        ];
    }

    // ---------- Portafolio ----------

    /** @return array<int, array{id:int, ruta:string}> */
    public static function fotos(int $empleadoId): array
    {
        $stmt = Database::conexion()->prepare('SELECT id, ruta FROM empleado_fotos WHERE empleado_id = :e ORDER BY id DESC');
        $stmt->execute(['e' => $empleadoId]);

        return $stmt->fetchAll();
    }

    public static function agregarFoto(int $empleadoId, string $ruta): bool
    {
        if (count(self::fotos($empleadoId)) >= self::MAX_FOTOS) {
            return false;
        }
        Database::conexion()->prepare('INSERT INTO empleado_fotos (empleado_id, ruta) VALUES (:e, :r)')
            ->execute(['e' => $empleadoId, 'r' => $ruta]);

        return true;
    }

    /** Devuelve la ruta borrada (para quitar el archivo) o null si no era de esta sede. */
    public static function eliminarFoto(int $fotoId, int $sedeId): ?string
    {
        $stmt = Database::conexion()->prepare(
            'SELECT f.id, f.ruta FROM empleado_fotos f JOIN empleados e ON e.id = f.empleado_id WHERE f.id = :f AND e.sede_id = :s'
        );
        $stmt->execute(['f' => $fotoId, 's' => $sedeId]);
        $foto = $stmt->fetch();
        if ($foto === false) {
            return null;
        }
        Database::conexion()->prepare('DELETE FROM empleado_fotos WHERE id = :f')->execute(['f' => $fotoId]);

        return (string) $foto['ruta'];
    }
}
