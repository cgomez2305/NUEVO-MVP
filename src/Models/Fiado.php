<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use App\Services\HorarioCobro;

/**
 * El cuaderno del fiado (ver database/migrations/2026-10-03_22_fiado.sql).
 * Es del negocio, como el cliente. Saldo = cargos − abonos, sin contar lo
 * anulado (la venta fiada que se anuló no se cobra).
 */
class Fiado
{
    public const METODOS_ABONO = ['efectivo' => 'Efectivo', 'nequi' => 'Nequi', 'breb' => 'Bre-B'];

    /** Días que deben pasar entre dos recordatorios al mismo cliente (Ley 2300: uno por semana). */
    public const DIAS_ENTRE_RECORDATORIOS = 7;

    /** Saldo de los movimientos con ese alias de tabla ('' = sin alias). */
    private static function saldoSql(string $t = ''): string
    {
        $p = $t !== '' ? $t . '.' : '';

        return "COALESCE(SUM(CASE WHEN {$p}anulado = 1 THEN 0 WHEN {$p}tipo = 'cargo' THEN {$p}monto ELSE -CAST({$p}monto AS SIGNED) END), 0)";
    }

    public static function saldo(int $negocioId, int $clienteId): int
    {
        $stmt = Database::conexion()->prepare('SELECT ' . self::saldoSql() . ' FROM fiado_movimientos WHERE negocio_id = :n AND cliente_id = :c');
        $stmt->execute(['n' => $negocioId, 'c' => $clienteId]);

        return (int) $stmt->fetchColumn();
    }

    /** Lo que le deben al negocio en total (solo saldos a favor del negocio). */
    public static function totalPorCobrar(int $negocioId): int
    {
        return array_sum(array_map(fn ($c) => max(0, (int) $c['saldo']), self::clientesConSaldo($negocioId)));
    }

    /**
     * Clientes que deben, con desde cuándo: los abonos pagan primero lo más
     * viejo (como en el cuaderno), y "debe desde" es la fecha del cargo más
     * viejo que sigue sin pagar. Ordenados: lo más viejo primero y, a la
     * misma fecha, el que más debe.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function clientesConSaldo(int $negocioId): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT c.id, c.nombre, c.telefono, c.fiado_limite, ' . self::saldoSql('m') . ' AS saldo,
                    (SELECT MAX(r.enviado_en) FROM fiado_recordatorios r WHERE r.cliente_id = c.id AND r.negocio_id = c.negocio_id) AS ultimo_recordatorio
             FROM clientes c JOIN fiado_movimientos m ON m.cliente_id = c.id AND m.negocio_id = c.negocio_id
             WHERE c.negocio_id = :n
             GROUP BY c.id, c.nombre, c.telefono, c.fiado_limite
             HAVING saldo > 0'
        );
        $stmt->execute(['n' => $negocioId]);
        $clientes = $stmt->fetchAll();
        foreach ($clientes as &$cliente) {
            $pendientes = self::pendientes($negocioId, (int) $cliente['id']);
            $cliente['debe_desde'] = $pendientes !== [] ? $pendientes[0]['creado_en'] : null;
        }
        unset($cliente);
        usort($clientes, fn ($a, $b) => [(string) $a['debe_desde'], -(int) $a['saldo']] <=> [(string) $b['debe_desde'], -(int) $b['saldo']]);

        return $clientes;
    }

    /**
     * Movimientos del cliente en orden del cuaderno (del más viejo al más
     * nuevo), cada uno con el saldo que quedó después. Si son muchos, los
     * últimos $limite y el saldo con que arrancan ("viene de antes").
     *
     * @return array{movimientos: array<int, array<string, mixed>>, saldo_anterior: int}
     */
    public static function libreta(int $negocioId, int $clienteId, int $limite = 60): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT m.*, u.nombre AS usuario_nombre, s.nombre AS sede_nombre
             FROM fiado_movimientos m
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             LEFT JOIN sedes s ON s.id = m.sede_id
             WHERE m.negocio_id = :n AND m.cliente_id = :c
             ORDER BY m.creado_en ASC, m.id ASC'
        );
        $stmt->execute(['n' => $negocioId, 'c' => $clienteId]);
        $todos = $stmt->fetchAll();
        $saldo = 0;
        foreach ($todos as &$mov) {
            if ((int) $mov['anulado'] === 0) {
                $saldo += $mov['tipo'] === 'cargo' ? (int) $mov['monto'] : -(int) $mov['monto'];
            }
            $mov['saldo_despues'] = $saldo;
        }
        unset($mov);
        $recorte = max(0, count($todos) - $limite);
        $anterior = $recorte > 0 ? (int) $todos[$recorte - 1]['saldo_despues'] : 0;

        return ['movimientos' => array_slice($todos, $recorte), 'saldo_anterior' => $anterior];
    }

    /**
     * Cargos que siguen sin pagar (los abonos van pagando del más viejo al
     * más nuevo), con lo que falta de cada uno. Sirve para "debe desde" y
     * para el detalle del recordatorio.
     *
     * @return array<int, array{creado_en:string, monto:int, falta:int, venta_id:?int, nota:?string}>
     */
    public static function pendientes(int $negocioId, int $clienteId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT tipo, monto, creado_en, venta_id, nota FROM fiado_movimientos
             WHERE negocio_id = :n AND cliente_id = :c AND anulado = 0 ORDER BY creado_en ASC, id ASC'
        );
        $stmt->execute(['n' => $negocioId, 'c' => $clienteId]);
        $cargos = [];
        $abonado = 0;
        foreach ($stmt->fetchAll() as $mov) {
            if ($mov['tipo'] === 'cargo') {
                $cargos[] = ['creado_en' => (string) $mov['creado_en'], 'monto' => (int) $mov['monto'], 'falta' => (int) $mov['monto'],
                    'venta_id' => $mov['venta_id'] !== null ? (int) $mov['venta_id'] : null, 'nota' => $mov['nota']];
            } else {
                $abonado += (int) $mov['monto'];
            }
        }
        foreach ($cargos as &$cargo) {
            $paga = min($abonado, $cargo['falta']);
            $cargo['falta'] -= $paga;
            $abonado -= $paga;
        }
        unset($cargo);

        return array_values(array_filter($cargos, fn ($c) => $c['falta'] > 0));
    }

    /** Un cargo a mano (lo que estaba en el cuaderno de papel, por ejemplo). No mueve la caja. */
    public static function cargar(int $negocioId, int $sedeId, int $clienteId, int $monto, string $nota, ?int $usuarioId): void
    {
        if ($monto <= 0) {
            throw new \DomainException('Escribe cuánto le vas a cargar.');
        }
        if (trim($nota) === '') {
            throw new \DomainException('Escribe una nota que diga qué es (por ejemplo, "lo del cuaderno").');
        }
        Database::conexion()->prepare(
            "INSERT INTO fiado_movimientos (negocio_id, sede_id, cliente_id, tipo, monto, nota, usuario_id)
             VALUES (:n, :s, :c, 'cargo', :m, :nota, :u)"
        )->execute(['n' => $negocioId, 's' => $sedeId, 'c' => $clienteId, 'm' => $monto, 'nota' => mb_substr(trim($nota), 0, 160), 'u' => $usuarioId]);
    }

    /**
     * Abono del cliente. No puede pasar de lo que debe (no hay "saldo a
     * favor" en el cuaderno: lo que sobre se devuelve en vueltas). El cliente
     * se bloquea mientras tanto: dos abonos a la vez no se pasan juntos.
     */
    public static function abonar(int $negocioId, int $sedeId, int $clienteId, int $monto, string $metodo, string $nota, ?int $usuarioId): int
    {
        if (!isset(self::METODOS_ABONO[$metodo])) {
            throw new \DomainException('Elige cómo te pagó el abono.');
        }
        if ($monto <= 0) {
            throw new \DomainException('Escribe cuánto abonó.');
        }
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id FROM clientes WHERE id = :c AND negocio_id = :n FOR UPDATE');
            $stmt->execute(['c' => $clienteId, 'n' => $negocioId]);
            if ($stmt->fetch() === false) {
                throw new \DomainException('Ese cliente no existe en tu negocio.');
            }
            $saldo = self::saldo($negocioId, $clienteId);
            if ($saldo <= 0) {
                throw new \DomainException('Este cliente no debe nada.');
            }
            if ($monto > $saldo) {
                throw new \DomainException('Debe ' . pesos($saldo) . ': el abono no puede ser mayor. Si te pagó más, dale las vueltas.');
            }
            $pdo->prepare(
                "INSERT INTO fiado_movimientos (negocio_id, sede_id, cliente_id, tipo, monto, metodo, nota, usuario_id)
                 VALUES (:n, :s, :c, 'abono', :m, :metodo, :nota, :u)"
            )->execute([
                'n' => $negocioId, 's' => $sedeId, 'c' => $clienteId, 'm' => $monto, 'metodo' => $metodo,
                'nota' => trim($nota) !== '' ? mb_substr(trim($nota), 0, 160) : null, 'u' => $usuarioId,
            ]);
            $pdo->commit();

            return $saldo - $monto;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function establecerLimite(int $negocioId, int $clienteId, ?int $limite): void
    {
        Database::conexion()->prepare('UPDATE clientes SET fiado_limite = :l WHERE id = :c AND negocio_id = :n')
            ->execute(['l' => $limite !== null ? max(0, $limite) : null, 'c' => $clienteId, 'n' => $negocioId]);
    }

    /**
     * El cliente con ese WhatsApp, o uno nuevo. A un cliente que ya existe no
     * se le cambia nada (ni el nombre ni sus autorizaciones de la tienda).
     * Solo se crea si autorizó guardar sus datos (Ley 1581 de 2012).
     */
    public static function clienteParaFiar(int $negocioId, string $nombre, string $telefono, bool $autorizo): int
    {
        $nombre = mb_substr(trim($nombre), 0, 120);
        $telefono = self::telefonoValido($telefono);
        if ($nombre === '') {
            throw new \DomainException('Escribe el nombre del cliente.');
        }
        if ($telefono === null) {
            throw new \DomainException('Escribe un WhatsApp de 10 dígitos que empiece por 3.');
        }
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('SELECT id FROM clientes WHERE negocio_id = :n AND telefono = :t');
        $stmt->execute(['n' => $negocioId, 't' => $telefono]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        if (!$autorizo) {
            throw new \DomainException('Marca que el cliente autorizó guardar su nombre y número (Ley 1581): sin eso no se puede crear.');
        }
        $pdo->prepare(
            'INSERT INTO clientes (negocio_id, nombre, telefono, autorizo_datos, autorizado_en, acepta_marketing)
             VALUES (:n, :nombre, :t, 1, NOW(), 0)'
        )->execute(['n' => $negocioId, 'nombre' => $nombre, 't' => $telefono]);

        return (int) $pdo->lastInsertId();
    }

    /** Celular colombiano de 10 dígitos que empieza por 3 (acepta +57, espacios y guiones). */
    public static function telefonoValido(string $telefono): ?string
    {
        $digitos = (string) preg_replace('/\D+/', '', $telefono);
        if (strlen($digitos) === 12 && str_starts_with($digitos, '57')) {
            $digitos = substr($digitos, 2);
        }

        return preg_match('/^3\d{9}$/', $digitos) === 1 ? $digitos : null;
    }

    /**
     * Clientes para elegir al fiar: primero los que ya tienen cuenta (con su
     * saldo y límite), luego el resto por nombre.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function clientesParaElegir(int $negocioId, int $limite = 300): array
    {
        $limite = max(1, min(1000, $limite));
        $stmt = Database::conexion()->prepare(
            'SELECT c.id, c.nombre, c.telefono, c.fiado_limite,
                    (SELECT ' . self::saldoSql('m') . '
                       FROM fiado_movimientos m WHERE m.cliente_id = c.id AND m.negocio_id = c.negocio_id) AS saldo,
                    EXISTS(SELECT 1 FROM fiado_movimientos m2 WHERE m2.cliente_id = c.id) AS con_cuenta
             FROM clientes c WHERE c.negocio_id = :n
             ORDER BY con_cuenta DESC, c.nombre ASC LIMIT ' . $limite
        );
        $stmt->execute(['n' => $negocioId]);

        return $stmt->fetchAll();
    }

    public static function ultimoRecordatorio(int $negocioId, int $clienteId): ?string
    {
        $stmt = Database::conexion()->prepare('SELECT MAX(enviado_en) FROM fiado_recordatorios WHERE negocio_id = :n AND cliente_id = :c');
        $stmt->execute(['n' => $negocioId, 'c' => $clienteId]);
        $fecha = $stmt->fetchColumn();

        return $fecha ? (string) $fecha : null;
    }

    /**
     * ¿Se le puede mandar el recordatorio ya? Junta el horario de la ley
     * (HorarioCobro), el "uno por semana" y lo básico: que deba algo y que
     * tenga un WhatsApp al que escribirle.
     *
     * @return array{permitido: bool, razon: ?string}
     */
    public static function puedeRecordar(int $negocioId, array $cliente, int $saldo, ?\DateTimeImmutable $ahora = null): array
    {
        $ahora ??= new \DateTimeImmutable('now', new \DateTimeZone(HorarioCobro::ZONA));
        if ($saldo <= 0) {
            return ['permitido' => false, 'razon' => 'No debe nada: no hay qué recordarle.'];
        }
        if (self::telefonoValido((string) $cliente['telefono']) === null) {
            return ['permitido' => false, 'razon' => 'Este cliente no tiene un WhatsApp válido guardado.'];
        }
        $ultimo = self::ultimoRecordatorio($negocioId, (int) $cliente['id']);
        if ($ultimo !== null) {
            $desde = new \DateTimeImmutable($ultimo, new \DateTimeZone(HorarioCobro::ZONA));
            $habilita = $desde->modify('+' . self::DIAS_ENTRE_RECORDATORIOS . ' days');
            if ($ahora < $habilita) {
                // El próximo, ya en un momento permitido (no un domingo ni un festivo).
                $proximo = HorarioCobro::proximoPermitido($habilita);

                return ['permitido' => false, 'razon' => 'Ya le enviaste un recordatorio el ' . fecha_larga($desde->format('Y-m-d'))
                    . ': la ley permite uno por semana. El próximo se puede desde el ' . fecha_larga($proximo->format('Y-m-d'))
                    . ' a las ' . hora_legible($proximo->format('H:i'))];
            }
        }
        $horario = HorarioCobro::evaluar($ahora);
        if (!$horario['permitido']) {
            $proximo = $horario['proximo'];
            $cuando = $proximo->format('Y-m-d') === $ahora->format('Y-m-d') ? 'hoy' : 'el ' . fecha_larga($proximo->format('Y-m-d'));

            return ['permitido' => false, 'razon' => $horario['razon'] . ' Lo puedes enviar ' . $cuando . ' desde las ' . hora_legible($proximo->format('H:i'))]; // ya termina en "a. m." o "p. m."
        }

        return ['permitido' => true, 'razon' => null];
    }

    public static function registrarRecordatorio(int $negocioId, int $clienteId, ?int $usuarioId, int $saldo): void
    {
        Database::conexion()->prepare(
            'INSERT INTO fiado_recordatorios (negocio_id, cliente_id, usuario_id, saldo) VALUES (:n, :c, :u, :s)'
        )->execute(['n' => $negocioId, 'c' => $clienteId, 'u' => $usuarioId, 's' => max(0, $saldo)]);
    }

    /**
     * El mensaje del recordatorio: amable, sin amenazas, con el saldo y de
     * qué es (los cargos pendientes, hasta 8).
     */
    public static function mensajeRecordatorio(array $negocio, array $cliente, int $saldo, array $pendientes): string
    {
        $nombre = explode(' ', trim((string) $cliente['nombre']))[0];
        $lineas = [];
        foreach (array_slice($pendientes, 0, 8) as $cargo) {
            $detalle = $cargo['venta_id'] !== null ? 'compra' : (string) ($cargo['nota'] ?? 'cargo');
            $lineas[] = '• ' . self::diaCorto((string) $cargo['creado_en']) . ': ' . $detalle . ' ' . pesos($cargo['falta']);
        }
        if (count($pendientes) > 8) {
            $lineas[] = '• y ' . (count($pendientes) - 8) . ' más';
        }

        return "Hola {$nombre}, te saludamos de " . nombre_publico_sede($negocio) . ". "
            . 'Te escribimos para recordarte, con cariño, que tienes una cuenta pendiente de ' . pesos($saldo) . ":\n"
            . implode("\n", $lineas) . "\n"
            . 'Cuando puedas, pasa por la tienda o avísanos si prefieres pagar por Nequi o Bre-B. '
            . 'Si ya pagaste, no tengas en cuenta este mensaje. ¡Gracias por tu confianza!';
    }

    /** "3 oct" (con el año si no es este): la fecha de un renglón del cuaderno. */
    public static function diaCorto(string $fechaHora): string
    {
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $ts = strtotime($fechaHora) ?: 0;

        return date('j', $ts) . ' ' . $meses[(int) date('n', $ts) - 1] . (date('Y', $ts) !== date('Y') ? ' ' . date('Y', $ts) : '');
    }

    /**
     * Abonos que entraron en la sede ese rango, por método (para el cierre
     * de caja: el abono en efectivo es plata que entró al cajón).
     *
     * @return array<string, array{abonos:int, total:int}>
     */
    public static function abonosDelRango(int $sedeId, string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT metodo, COUNT(*) AS abonos, COALESCE(SUM(monto), 0) AS total FROM fiado_movimientos
             WHERE sede_id = :s AND tipo = 'abono' AND anulado = 0 AND creado_en >= :desde AND creado_en < :hasta
             GROUP BY metodo"
        );
        $stmt->execute(['s' => $sedeId, 'desde' => $desde, 'hasta' => $hasta]);
        $porMetodo = array_fill_keys(array_keys(self::METODOS_ABONO), ['abonos' => 0, 'total' => 0]);
        foreach ($stmt->fetchAll() as $fila) {
            $porMetodo[(string) $fila['metodo']] = ['abonos' => (int) $fila['abonos'], 'total' => (int) $fila['total']];
        }

        return $porMetodo;
    }
}
