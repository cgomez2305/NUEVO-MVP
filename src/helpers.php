<?php

declare(strict_types=1);

/**
 * Funciones sueltas que usan casi todas las vistas y controladores.
 * Vive fuera de cualquier namespace a propósito: son azúcar global,
 * como config() o e(), que se usan constantemente en las plantillas.
 */

/**
 * Lee config/config.php una sola vez por petición.
 * config('db') devuelve el array completo; config('app.url') navega con puntos.
 */
function config(string $clave, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $ruta = __DIR__ . '/../config/config.php';
        if (!is_file($ruta)) {
            throw new RuntimeException(
                'Falta config/config.php. Copia config/config.example.php y ajústalo.'
            );
        }
        $config = require $ruta;
    }

    $valor = $config;
    foreach (explode('.', $clave) as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            return $default;
        }
        $valor = $valor[$parte];
    }

    return $valor;
}

/**
 * Ruta relativa a la raíz del sitio ("/panel", "/t/donamaria").
 * A propósito NO arma una URL absoluta con config('app.url'): así los
 * enlaces, formularios y redirecciones funcionan sin importar con qué
 * dominio o puerto use el navegador (localhost, 127.0.0.1, el dominio
 * real en producción...). Mezclar un dominio fijo en el HTML con el que
 * el navegador usa de verdad rompe las cookies de sesión.
 */
function base_url(string $ruta = ''): string
{
    return '/' . ltrim($ruta, '/');
}

/**
 * URL absoluta pensada para mostrarse o compartirse fuera del sitio
 * (el enlace de la tienda que el dueño copia a Instagram o WhatsApp).
 * Esta sí depende de config('app.url'), que debe ser el dominio público real.
 */
function url_publica(string $ruta = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    return $base . '/' . ltrim($ruta, '/');
}

function redirigir(string $ruta): never
{
    header('Location: ' . base_url($ruta));
    exit;
}

function abortar404(): never
{
    http_response_code(404);
    require __DIR__ . '/Views/errores/404.php';
    exit;
}

/** Escapa texto para HTML. Nombre corto porque se usa en cada vista. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

function pesos(int $valor): string
{
    return '$' . number_format($valor, 0, ',', '.');
}

/**
 * Renderiza una vista de src/Views/{plantilla}.php con $datos extraídos
 * como variables locales, opcionalmente envuelta en un layout.
 */
function ver(string $plantilla, array $datos = [], ?string $layout = null): void
{
    extract($datos, EXTR_SKIP);

    if ($layout === null) {
        require __DIR__ . "/Views/{$plantilla}.php";
        return;
    }

    ob_start();
    require __DIR__ . "/Views/{$plantilla}.php";
    $contenido = ob_get_clean();

    require __DIR__ . "/Views/layouts/{$layout}.php";
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verificar(): bool
{
    $enviado = $_POST['_csrf'] ?? '';
    return is_string($enviado) && hash_equals($_SESSION['_csrf'] ?? '', $enviado);
}

function flash_set(string $clave, string $mensaje): void
{
    $_SESSION['_flash'][$clave] = $mensaje;
}

function flash_obtener(string $clave): ?string
{
    $mensaje = $_SESSION['_flash'][$clave] ?? null;
    unset($_SESSION['_flash'][$clave]);
    return $mensaje;
}

/** "MAR 27 SEP", sin depender de la extensión intl ni del locale del servidor. */
function strftime_es(): string
{
    $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    $dia = $dias[(int) date('w')];
    $mes = $meses[(int) date('n') - 1];

    return mb_strtoupper("{$dia} " . date('j') . " {$mes}");
}

/**
 * "29 Sep · 12:00 p. m." — fecha+hora corta en español (meses abreviados
 * reales, no el "Dec"/"Abr"→"Apr" que da el locale en inglés de date()), con
 * el año solo si no es el actual. Reemplaza el date('d M, g:i a', ...) que
 * se repetía —en inglés— en pedidos, citas y recordatorios.
 */
function fecha_corta(string $fechaHora, string $separador = ' · '): string
{
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $ts = strtotime($fechaHora);

    $mes = ucfirst($meses[(int) date('n', $ts) - 1]);
    $fecha = date('j', $ts) . ' ' . $mes . ((int) date('Y', $ts) !== (int) date('Y') ? ' ' . date('Y', $ts) : '');
    $meridiano = date('a', $ts) === 'am' ? 'a. m.' : 'p. m.';

    return $fecha . $separador . date('g:i', $ts) . ' ' . $meridiano;
}

/** "miércoles 30 de septiembre" — fecha larga en español, para confirmaciones y listas de espera donde el día de la semana importa más que la hora. */
function fecha_larga(string $fechaIso): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $ts = strtotime($fechaIso) ?: 0;

    return $dias[(int) date('w', $ts)] . ' ' . (int) date('j', $ts) . ' de ' . $meses[(int) date('n', $ts) - 1];
}

function chip_estado(string $estado): string
{
    return match ($estado) {
        'pagado', 'entregado', 'completada', 'confirmada' => 'pq-chip-caja',
        'cancelado', 'cancelada' => 'pq-chip-cancelado',
        default => 'pq-chip-pendiente',
    };
}

/** Texto legible de un estado de pedidos.estado, para no repetir el mapa en cada plantilla. */
function etiqueta_estado_pedido(string $estado): string
{
    return match ($estado) {
        'pendiente' => 'Pendiente',
        'pagado' => 'Pagado',
        'en_cocina' => 'En cocina',
        'listo' => 'Listo',
        'en_camino' => 'En camino',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

/**
 * Un campo de precio puede llegar como "28000" (sin JS) o "28.000" (el
 * formateo en vivo de interacciones.js) — esto lo deja en entero sin
 * importar cuál de los dos haya mandado el navegador.
 */
function dinero_desde_texto(string $texto): int
{
    return (int) preg_replace('/\D+/', '', $texto);
}

/** Minutos transcurridos desde una fecha DATETIME hasta ahora. */
function minutos_desde(string $fechaHora): int
{
    return (int) max(0, floor((time() - strtotime($fechaHora)) / 60));
}

/** "5 min esperando" / "2 horas esperando": para pedidos y citas sin resolver. */
function texto_espera(int $minutos): string
{
    if ($minutos < 60) {
        return $minutos . ' min esperando';
    }
    $horas = intdiv($minutos, 60);
    return $horas . ($horas === 1 ? ' hora esperando' : ' horas esperando');
}

/**
 * Nivel de urgencia de una espera, relativo a un tiempo objetivo (no a un
 * número de minutos fijo): una cafetería que debería resolver en 5 minutos
 * y un restaurante que se toma 30 usan la misma lógica de semáforo.
 * 0-60% del objetivo → neutral, 60-100% → atención, >100% → prioridad.
 */
function nivel_espera(int $minutos, int $objetivoMin): string
{
    $pct = $minutos / max(1, $objetivoMin);
    if ($pct > 1) {
        return 'prioridad';
    }
    if ($pct >= 0.6) {
        return 'atencion';
    }
    return 'neutral';
}

/**
 * Nombre que el CLIENTE ve en la tienda pública: el de la marca
 * (negocios.nombre), nunca el campo interno sedes.nombre a secas — ese es
 * para que el dueño distinga sus sedes en el panel, no una denominación
 * pensada para el público. Si el negocio tiene una sola sede (el caso
 * normal), mostrar el nombre de la sede no aporta nada y se omite; con
 * varias, se añade como "Marca · Sede" para que el cliente sepa cuál es.
 * Requiere que $sede traiga negocio_nombre y multi_sede (ver
 * TiendaController::negocioOAbortar()).
 */
function nombre_publico_sede(array $sede): string
{
    $marca = (string) ($sede['negocio_nombre'] ?? $sede['nombre']);
    if (empty($sede['multi_sede']) || trim((string) $sede['nombre']) === '') {
        return $marca;
    }
    return $marca . ' · ' . $sede['nombre'];
}

/**
 * Si la sede está abierta en este preciso momento, según su horario crudo
 * (día ISO 1=lunes..7=domingo => [inicio, fin], igual que Sede::horario()
 * — no el ya agrupado de horario_resumen()). Null si el negocio no tiene
 * horario configurado: en ese caso no hay nada honesto que mostrar.
 *
 * @param array<string, array{0:string,1:string}> $horario
 * @return array{abierto: bool, desde: ?string, hasta: ?string}|null
 */
function negocio_abierto_ahora(array $horario): ?array
{
    if ($horario === []) {
        return null;
    }
    $diaHoy = (string) date('N');
    if (!isset($horario[$diaHoy])) {
        return ['abierto' => false, 'desde' => null, 'hasta' => null];
    }
    [$inicio, $fin] = $horario[$diaHoy];
    $ahora = date('H:i');
    return ['abierto' => $ahora >= $inicio && $ahora < $fin, 'desde' => $inicio, 'hasta' => $fin];
}

/** "18:00" → "6:00 p. m." (sin minutos si son :00 → "6 p. m."). */
function hora_legible(string $hora): string
{
    $ts = strtotime($hora) ?: 0;
    $minutos = date('i', $ts);
    $meridiano = date('a', $ts) === 'am' ? 'a. m.' : 'p. m.';
    return date('g', $ts) . ($minutos !== '00' ? ':' . $minutos : '') . ' ' . $meridiano;
}

/**
 * Cuándo vuelve a abrir, para completar "Cerrado ahora" con algo útil
 * ("Abre mañana a las 9:00 a. m.") en vez de dejar al cliente adivinando.
 * Solo tiene sentido llamarla cuando ya se sabe que el negocio está
 * cerrado ahora mismo (ver negocio_abierto_ahora()). Null si no hay
 * horario configurado o si no abre ningún día de la semana siguiente.
 *
 * @param array<string, array{0:string,1:string}> $horario
 * @return array{dia: string, hora: string}|null
 */
function negocio_proxima_apertura(array $horario): ?array
{
    if ($horario === []) {
        return null;
    }
    $diasNombre = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    $ahora = date('H:i');
    $diaHoyIso = (int) date('N');

    for ($offset = 0; $offset <= 7; $offset++) {
        $diaIso = (($diaHoyIso - 1 + $offset) % 7) + 1;
        $rango = $horario[(string) $diaIso] ?? null;
        if ($rango === null) {
            continue;
        }
        if ($offset === 0 && $ahora >= $rango[1]) {
            continue; // hoy ya cerró; sigue buscando el próximo día
        }
        $etiqueta = $offset === 0 ? 'hoy' : ($offset === 1 ? 'mañana' : $diasNombre[$diaIso - 1]);
        return ['dia' => $etiqueta, 'hora' => hora_legible($rango[0])];
    }

    return null;
}

/**
 * Agrupa Sede::horario() (día 1=lunes..7=domingo => [inicio, fin]) en líneas
 * legibles, uniendo días consecutivos con el mismo horario en un solo rango
 * (día "Lun-Vie", rango "8:00 a. m. - 6:00 p. m."). Los días sin abrir
 * aparecen como "Cerrado" (agrupados igual que los abiertos) en vez de
 * desaparecer — un negocio que no trabaja domingo necesita poder decirlo,
 * no solo omitir el día y dejar que el cliente adivine. Única excepción:
 * si el negocio no tiene NINGÚN horario configurado todavía, devuelve []
 * en vez de un "Lun-Dom: Cerrado" que daría a entender que cerró para
 * siempre. Devuelve {dia, rango} en vez de un string ya armado para que la
 * vista no tenga que volver a separar nombre de horas.
 *
 * @param array<string, array{0:string,1:string}> $horario
 * @return array<int, array{dia: string, rango: string}>
 */
function horario_resumen(array $horario): array
{
    if ($horario === []) {
        return [];
    }

    $dias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    $SIN_INICIAR = '__sin_iniciar__';
    $FIN = '__fin__';

    $lineas = [];
    $inicioGrupo = 1;
    $rangoActual = $SIN_INICIAR;

    for ($dia = 1; $dia <= 8; $dia++) {
        $rango = $dia <= 7 ? ($horario[(string) $dia] ?? null) : $FIN;
        $cambia = $rango !== $rangoActual;

        if ($cambia && $rangoActual !== $SIN_INICIAR) {
            $nombre = $inicioGrupo === $dia - 1 ? $dias[$inicioGrupo - 1] : $dias[$inicioGrupo - 1] . '-' . $dias[$dia - 2];
            $rangoTexto = $rangoActual === null ? 'Cerrado' : hora_legible($rangoActual[0]) . ' - ' . hora_legible($rangoActual[1]);
            $lineas[] = ['dia' => $nombre, 'rango' => $rangoTexto];
        }
        if ($cambia) {
            $inicioGrupo = $dia;
        }
        $rangoActual = $rango;
    }

    return $lineas;
}
