<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use DateTimeImmutable;

/**
 * El copiloto de recompra: reglas simples de frecuencia y último pedido,
 * como pide el documento de producto para el MVP (antes de modelos más
 * complejos): "Doña Marta no pide hace 18 días; mándale esto".
 */
class Copiloto
{
    /** El copiloto es exclusivo de los planes Barrio y Pro (ver planes.incluye_copiloto). $negocio es el contexto de Auth::exigirSesion(). */
    public static function disponiblePara(array $negocio): bool
    {
        return (bool) ($negocio['incluye_copiloto'] ?? false);
    }

    /**
     * Clientes con al menos 2 pedidos cuyo silencio actual supera 1.5x
     * su frecuencia habitual (con un piso de 14 días para no molestar
     * a quien compra casi a diario). Es el segmento "inactivo" de
     * segmentar(), en el formato que ya usaba el dashboard, y solo con
     * clientes que autorizaron promociones (Cliente::contactable).
     *
     * @return array<int, array{cliente: array<string, mixed>, dias_sin_pedir: int, frecuencia_prom: int, motivo: string}>
     */
    public static function clientesAReactivar(int $negocioId, string $tipoNegocio = 'pedidos'): array
    {
        // Solo a quien autorizó promociones: el resto no es "a quién escribirle".
        $inactivos = array_filter(
            self::segmentar($negocioId, $tipoNegocio),
            fn ($fila) => in_array('inactivo', $fila['tags'], true) && $fila['contactable']
        );

        return array_values(array_map(fn ($fila) => [
            'cliente'         => $fila['cliente'],
            'dias_sin_pedir'  => $fila['dias_sin_pedir'],
            'frecuencia_prom' => $fila['frecuencia_prom'],
            'motivo'          => $fila['motivo'],
        ], $inactivos));
    }

    /**
     * $descuentoPct es una elección explícita de quien va a enviar el
     * mensaje (ver selector en copiloto_mensaje.php), nunca un incentivo
     * que el copiloto inventa solo: si viene en 0, el mensaje no promete
     * ningún descuento.
     */
    /**
     * Con descuento, el mensaje lleva el código del cupón personal (ver
     * Cupon::asegurarPersonal): sin código el cliente no tiene cómo cobrarlo
     * en la tienda, y "tienes 10%" quedaba como una promesa de palabra.
     */
    public static function mensajeSugerido(array $cliente, string $segmento = 'inactivo', int $descuentoPct = 0, ?string $codigo = null, ?string $venceEn = null, ?string $enlacePreferencias = null): string
    {
        $nombreParaSaludo = self::nombreParaSaludo((string) $cliente['nombre']);
        $fraseDescuento = null;
        if ($descuentoPct > 0) {
            $fraseDescuento = $codigo !== null
                ? "Tienes {$descuentoPct}% en tu próxima compra con el código {$codigo}" . ($venceEn !== null ? ' (vale hasta el ' . fecha_larga($venceEn) . ').' : '.')
                : "Tienes {$descuentoPct}% en tu próxima compra.";
        }

        $partes = match ($segmento) {
            'vip'   => [
                "Hola {$nombreParaSaludo}, eres uno de nuestros mejores clientes y queremos que lo notes.",
                $fraseDescuento,
                '¿Qué te separamos?',
            ],
            'nuevo' => [
                "Hola {$nombreParaSaludo}, gracias por tu primera compra con nosotros.",
                $fraseDescuento,
                '¿Te ayudamos con algo para la próxima?',
            ],
            default => [
                "Hola {$nombreParaSaludo}, te extrañamos.",
                $fraseDescuento,
                '¿Te separamos lo de siempre?',
            ],
        };

        $texto = implode(' ', array_filter($partes));
        // Cada promoción lleva cómo dejar de recibirlas (Ley 1581): retirar
        // el permiso tiene que ser tan fácil como darlo.
        if ($enlacePreferencias !== null) {
            $texto .= "\n\nSi prefieres no recibir más promociones: " . $enlacePreferencias;
        }

        return $texto;
    }

    /**
     * El único mensaje que se le puede mandar a quien NO autorizó
     * promociones: pedirle permiso, una vez, con su enlace para darlo.
     */
    public static function mensajePermiso(array $cliente, string $marca, string $enlacePreferencias): string
    {
        $nombre = self::nombreParaSaludo((string) $cliente['nombre']);

        return "Hola {$nombre}, te escribimos de {$marca}. ¿Te gustaría recibir por WhatsApp nuestras promociones y novedades? "
            . "Si quieres, actívalo aquí: {$enlacePreferencias}\n\nSi no, no te volveremos a escribir por esto.";
    }

    private static function nombreParaSaludo(string $nombreCompleto): string
    {
        $palabras = explode(' ', trim($nombreCompleto));
        $honorificos = ['doña', 'don', 'señora', 'señor'];

        // "Doña Marta" debe saludar como "Marta", no como "Doña".
        $primeraPalabra = mb_strtolower($palabras[0]);
        return (in_array($primeraPalabra, $honorificos, true) && isset($palabras[1]))
            ? $palabras[1]
            : $palabras[0];
    }

    /**
     * Segmenta TODOS los clientes con al menos una compra/cita en: inactivo
     * (se está enfriando, misma regla que antes), vip (gasta o compra
     * mucho más que el resto), nuevo (una sola compra reciente) y
     * recurrente (el resto: compra seguido y no necesita nada especial
     * ahora mismo). Un cliente puede tener varias etiquetas a la vez —
     * un VIP que se está enfriando es justo el caso más valioso de ver.
     *
     * @return array<int, array{
     *   cliente: array<string, mixed>, total_compras: int, gasto_total: int,
     *   dias_sin_pedir: int, frecuencia_prom: ?int, tags: array<int, string>, motivo: string
     * }>
     */
    public static function segmentar(int $negocioId, string $tipoNegocio = 'pedidos'): array
    {
        // pedidos/citas son por sede, pero el copiloto mide al cliente en todo
        // el negocio (si compra en dos sedes de la misma marca, es el mismo
        // cliente), así que se cruza con sedes para filtrar por negocio_id.
        $sql = $tipoNegocio === 'reservas'
            ? 'SELECT c.cliente_id, c.fecha_hora AS fecha, ' . Cita::sqlValor('c') . ' AS monto FROM citas c
               JOIN sedes s ON s.id = c.sede_id
               WHERE s.negocio_id = :negocio_id AND ' . Cita::sqlCuenta('c') . ' ORDER BY c.cliente_id ASC, c.fecha_hora ASC'
            : 'SELECT p.cliente_id, p.creado_en AS fecha, p.total AS monto FROM pedidos p
               JOIN sedes s ON s.id = p.sede_id
               WHERE s.negocio_id = :negocio_id AND p.estado <> \'cancelado\' ORDER BY p.cliente_id ASC, p.creado_en ASC';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['negocio_id' => $negocioId]);

        $porCliente = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porCliente[(int) $fila['cliente_id']]['fechas'][] = new DateTimeImmutable((string) $fila['fecha']);
            $porCliente[(int) $fila['cliente_id']]['montos'][] = (int) $fila['monto'];
        }

        $hoy = new DateTimeImmutable('today');
        $resultado = [];

        foreach ($porCliente as $clienteId => $datos) {
            $fechas = $datos['fechas'];
            $totalCompras = count($fechas);
            $gastoTotal = array_sum($datos['montos']);

            $frecuenciaProm = null;
            if ($totalCompras >= 2) {
                $brechas = [];
                for ($i = 1; $i < $totalCompras; $i++) {
                    $brechas[] = (int) $fechas[$i - 1]->diff($fechas[$i])->days;
                }
                $frecuenciaProm = (int) round(array_sum($brechas) / count($brechas));
            }

            $ultimaCompra = end($fechas);
            $primeraCompra = $fechas[0];
            $diasSinPedir = (int) $ultimaCompra->diff($hoy)->days;
            $diasDesdeAlta = (int) $primeraCompra->diff($hoy)->days;

            $cliente = Cliente::buscar($clienteId, $negocioId);
            if ($cliente === null) {
                continue;
            }

            $resultado[] = [
                'cliente'              => $cliente,
                'total_compras'        => $totalCompras,
                'gasto_total'          => $gastoTotal,
                'dias_sin_pedir'       => $diasSinPedir,
                'dias_desde_alta'      => $diasDesdeAlta,
                'frecuencia_prom'      => $frecuenciaProm,
                'ultima_compra_monto'  => end($datos['montos']),
                'contactable'          => Cliente::contactable($cliente),
            ];
        }

        // VIP: top 20% por gasto total, con al menos 3 compras (para no
        // etiquetar como VIP a alguien con una sola compra grande).
        $gastosElegiblesVip = array_values(array_filter(
            array_map(fn ($r) => $r['total_compras'] >= 3 ? $r['gasto_total'] : null, $resultado),
            fn ($g) => $g !== null
        ));
        rsort($gastosElegiblesVip);
        $umbralVip = $gastosElegiblesVip === []
            ? null
            : $gastosElegiblesVip[max(0, (int) ceil(count($gastosElegiblesVip) * 0.2) - 1)];

        foreach ($resultado as &$fila) {
            $tags = [];

            $umbralInactivo = $fila['frecuencia_prom'] !== null ? max(14, (int) round($fila['frecuencia_prom'] * 1.5)) : null;
            $esInactivo = $fila['total_compras'] >= 2 && $umbralInactivo !== null && $fila['dias_sin_pedir'] > $umbralInactivo;
            $esVip = $fila['total_compras'] >= 3 && $umbralVip !== null && $fila['gasto_total'] >= $umbralVip;
            $esNuevo = $fila['total_compras'] === 1 && $fila['dias_sin_pedir'] <= 30;

            if ($esInactivo) {
                $tags[] = 'inactivo';
            }
            if ($esVip) {
                $tags[] = 'vip';
            }
            if ($esNuevo) {
                $tags[] = 'nuevo';
            }
            if ($tags === []) {
                $tags[] = 'recurrente';
            }

            $fila['tags'] = $tags;
            $fila['motivo'] = match (true) {
                $esInactivo => "Lleva {$fila['dias_sin_pedir']} días sin pedir · antes pedía cada {$fila['frecuencia_prom']}",
                $esNuevo    => 'Su primera compra fue ' . hace_dias((int) $fila['dias_sin_pedir']),
                $esVip      => "{$fila['total_compras']} compras · " . pesos((int) $fila['gasto_total']) . ' en total',
                default     => "{$fila['total_compras']} compras · la última " . hace_dias((int) $fila['dias_sin_pedir']),
            };
        }
        unset($fila);

        usort($resultado, fn ($a, $b) => $b['dias_sin_pedir'] <=> $a['dias_sin_pedir']);

        return $resultado;
    }

    /** % de clientes que pidieron/reservaron más de una vez en los últimos 30 días. */
    public static function recompraMensualPct(int $negocioId, string $tipoNegocio = 'pedidos'): int
    {
        $sql = $tipoNegocio === 'reservas'
            ? 'SELECT c.cliente_id, COUNT(*) AS total FROM citas c
               JOIN sedes s ON s.id = c.sede_id
               WHERE s.negocio_id = :negocio_id AND c.fecha_hora >= NOW() - INTERVAL 30 DAY AND ' . Cita::sqlCuenta('c') . '
               GROUP BY c.cliente_id'
            : 'SELECT p.cliente_id, COUNT(*) AS total FROM pedidos p
               JOIN sedes s ON s.id = p.sede_id
               WHERE s.negocio_id = :negocio_id AND p.creado_en >= NOW() - INTERVAL 30 DAY AND p.estado <> \'cancelado\'
               GROUP BY p.cliente_id';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['negocio_id' => $negocioId]);
        $filas = $stmt->fetchAll();

        if ($filas === []) {
            return 0;
        }

        $repiten = count(array_filter($filas, fn ($f) => (int) $f['total'] >= 2));

        return (int) round($repiten / count($filas) * 100);
    }

    /** Días después de un mensaje en los que una compra cuenta como recuperada. */
    public const DIAS_ATRIBUCION = 14;

    /**
     * Lo que Veci ayudó a recuperar en un rango: por cada mensaje del
     * copiloto, la PRIMERA compra (o reserva) de ese cliente dentro de los
     * DIAS_ATRIBUCION días siguientes. Regla conservadora y explicable:
     * un mensaje suma a lo sumo una venta, y una venta se cuenta una vez
     * aunque le hayan escrito dos veces. Cuenta la venta que cae dentro del
     * rango (no el mensaje): lo que entró este mes.
     *
     * @return array{total: int, ventas: int, clientes: int, contactados: int, detalle: array<int, array{cliente_id: int, nombre: string, monto: int, fecha: string}>}
     */
    public static function recuperado(int $negocioId, string $tipoNegocio, string $desde, string $hasta): array
    {
        $dias = self::DIAS_ATRIBUCION;
        $sql = $tipoNegocio === 'reservas'
            ? 'SELECT m.cliente_id, (
                   SELECT c.id FROM citas c JOIN sedes s ON s.id = c.sede_id
                   WHERE s.negocio_id = m.negocio_id AND c.cliente_id = m.cliente_id AND ' . Cita::sqlCuenta('c') . "
                     AND c.creado_en > m.enviado_en AND c.creado_en <= m.enviado_en + INTERVAL {$dias} DAY
                   ORDER BY c.creado_en ASC, c.id ASC LIMIT 1
               ) AS venta_id
               FROM mensajes_copiloto m
               WHERE m.negocio_id = :n AND m.enviado_en >= :desde - INTERVAL {$dias} DAY AND m.enviado_en < :hasta"
            : "SELECT m.cliente_id, (
                   SELECT p.id FROM pedidos p JOIN sedes s ON s.id = p.sede_id
                   WHERE s.negocio_id = m.negocio_id AND p.cliente_id = m.cliente_id AND p.estado <> 'cancelado'
                     AND p.creado_en > m.enviado_en AND p.creado_en <= m.enviado_en + INTERVAL {$dias} DAY
                   ORDER BY p.creado_en ASC, p.id ASC LIMIT 1
               ) AS venta_id
               FROM mensajes_copiloto m
               WHERE m.negocio_id = :n AND m.enviado_en >= :desde - INTERVAL {$dias} DAY AND m.enviado_en < :hasta";
        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['n' => $negocioId, 'desde' => $desde, 'hasta' => $hasta]);
        $filas = $stmt->fetchAll();

        $contactados = [];
        $ventaIds = [];
        foreach ($filas as $fila) {
            $contactados[(int) $fila['cliente_id']] = true;
            if ($fila['venta_id'] !== null) {
                $ventaIds[(int) $fila['venta_id']] = true;
            }
        }
        $vacio = ['total' => 0, 'ventas' => 0, 'clientes' => 0, 'contactados' => 0, 'detalle' => []];
        $contactadosEnRango = self::contactadosEnRango($negocioId, $desde, $hasta);
        if ($ventaIds === []) {
            return ['contactados' => $contactadosEnRango] + $vacio;
        }

        $ids = implode(',', array_map('intval', array_keys($ventaIds)));
        $detalleSql = $tipoNegocio === 'reservas'
            ? 'SELECT c.cliente_id, cl.nombre, ' . Cita::sqlValor('c') . " AS monto, c.creado_en AS fecha
               FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
               WHERE c.id IN ({$ids}) AND c.creado_en >= :desde AND c.creado_en < :hasta ORDER BY c.creado_en DESC"
            : "SELECT p.cliente_id, cl.nombre, p.total AS monto, p.creado_en AS fecha
               FROM pedidos p JOIN clientes cl ON cl.id = p.cliente_id
               WHERE p.id IN ({$ids}) AND p.creado_en >= :desde AND p.creado_en < :hasta ORDER BY p.creado_en DESC";
        $stmt = Database::conexion()->prepare($detalleSql);
        $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
        $detalle = array_map(fn ($f) => [
            'cliente_id' => (int) $f['cliente_id'],
            'nombre'     => (string) $f['nombre'],
            'monto'      => (int) $f['monto'],
            'fecha'      => (string) $f['fecha'],
        ], $stmt->fetchAll());

        return [
            'total'       => array_sum(array_column($detalle, 'monto')),
            'ventas'      => count($detalle),
            'clientes'    => count(array_unique(array_column($detalle, 'cliente_id'))),
            'contactados' => $contactadosEnRango,
            'detalle'     => $detalle,
        ];
    }

    /** Lo recuperado en el mes calendario actual. */
    public static function recuperadoEsteMes(int $negocioId, string $tipoNegocio): array
    {
        return self::recuperado($negocioId, $tipoNegocio, date('Y-m-01 00:00:00'), date('Y-m-01 00:00:00', strtotime('first day of next month')));
    }

    private static function contactadosEnRango(int $negocioId, string $desde, string $hasta): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(DISTINCT cliente_id) FROM mensajes_copiloto WHERE negocio_id = :n AND enviado_en >= :desde AND enviado_en < :hasta'
        );
        $stmt->execute(['n' => $negocioId, 'desde' => $desde, 'hasta' => $hasta]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Anota que se le escribió. Si ya hay un contacto de hoy con ese
     * cliente (abrió WhatsApp y además tocó "Ya le escribí"), no se duplica.
     */
    public static function registrarEnvio(int $negocioId, int $clienteId, string $mensaje): void
    {
        $hoy = Database::conexion()->prepare(
            'SELECT 1 FROM mensajes_copiloto WHERE negocio_id = :n AND cliente_id = :c AND enviado_en >= CURDATE() LIMIT 1'
        );
        $hoy->execute(['n' => $negocioId, 'c' => $clienteId]);
        if ($hoy->fetchColumn() !== false) {
            return;
        }
        $stmt = Database::conexion()->prepare(
            'INSERT INTO mensajes_copiloto (negocio_id, cliente_id, mensaje)
             VALUES (:negocio_id, :cliente_id, :mensaje)'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'cliente_id' => $clienteId, 'mensaje' => $mensaje]);
    }

    /**
     * La misma fila que arma segmentar() pero para un solo cliente, para la
     * pantalla de detalle ("por qué te lo recomendamos"). No vale la pena
     * una consulta aparte: segmentar() ya trae todo el negocio en un par de
     * queries y el volumen esperado (MVP, un barrio) lo hace barato.
     *
     * @return array{cliente: array<string, mixed>, total_compras: int, gasto_total: int, dias_sin_pedir: int, frecuencia_prom: ?int, ultima_compra_monto: int, tags: array<int, string>, motivo: string}|null
     */
    public static function contextoCliente(int $negocioId, int $clienteId, string $tipoNegocio = 'pedidos'): ?array
    {
        foreach (self::segmentar($negocioId, $tipoNegocio) as $fila) {
            if ((int) $fila['cliente']['id'] === $clienteId) {
                return $fila;
            }
        }

        return null;
    }

    /** Último mensaje que se le marcó como enviado a este cliente, o null si nunca se le ha contactado desde aquí. */
    public static function ultimoContacto(int $negocioId, int $clienteId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT enviado_en FROM mensajes_copiloto WHERE negocio_id = :negocio_id AND cliente_id = :cliente_id
             ORDER BY enviado_en DESC LIMIT 1'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'cliente_id' => $clienteId]);
        $fila = $stmt->fetch();

        if ($fila === false) {
            return null;
        }

        $fecha = new DateTimeImmutable((string) $fila['enviado_en']);

        return ['fecha' => $fecha, 'dias' => (int) $fecha->diff(new DateTimeImmutable('today'))->days];
    }

    /** Si el cliente volvió a comprar/reservar después de una fecha dada (para saber si un contacto dio resultado). */
    public static function comproDespuesDe(int $negocioId, int $clienteId, DateTimeImmutable $fecha, string $tipoNegocio = 'pedidos'): bool
    {
        $sql = $tipoNegocio === 'reservas'
            ? 'SELECT 1 FROM citas c JOIN sedes s ON s.id = c.sede_id
               WHERE s.negocio_id = :negocio_id AND c.cliente_id = :cliente_id AND ' . Cita::sqlCuenta('c') . '
                 AND c.fecha_hora > :fecha LIMIT 1'
            : 'SELECT 1 FROM pedidos p JOIN sedes s ON s.id = p.sede_id
               WHERE s.negocio_id = :negocio_id AND p.cliente_id = :cliente_id AND p.creado_en > :fecha
                 AND p.estado <> \'cancelado\' LIMIT 1';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute([
            'negocio_id' => $negocioId,
            'cliente_id' => $clienteId,
            'fecha'      => $fecha->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetch() !== false;
    }
}
