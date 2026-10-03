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
 * Precio de un servicio o de una cita según su tipo: "$20.000",
 * "Desde $20.000" o "$20.000 – $35.000". Sirve para filas de servicios y de
 * citas (las dos tienen precio, precio_tipo y precio_max).
 */
function precio_texto(array $fila): string
{
    $precio = (int) ($fila['precio'] ?? 0);

    return match ((string) ($fila['precio_tipo'] ?? 'fijo')) {
        'desde' => 'Desde ' . pesos($precio),
        'rango' => pesos($precio) . ' – ' . pesos((int) ($fila['precio_max'] ?? $precio)),
        default => pesos($precio),
    };
}

/** ¿El precio todavía no es exacto? (se confirma al ver el trabajo) */
function precio_es_estimado(array $fila): bool
{
    return in_array((string) ($fila['precio_tipo'] ?? 'fijo'), ['desde', 'rango'], true);
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

/**
 * La IP real de quien hace la petición, para limitar abuso (ver
 * LimiteTasa::excedido). Lee solo REMOTE_ADDR, nunca cabeceras como
 * X-Forwarded-For: esas las puede mandar cualquiera y, sin un proxy
 * confiable configurado delante (no es el caso de este hosting compartido
 * típico), confiar en ellas dejaría falsificar la IP y saltarse el límite.
 */
function ip_cliente(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
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
        'cancelado', 'cancelada', 'no_asistio' => 'pq-chip-cancelado',
        'en_curso' => 'pq-chip-curso',
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
    // Tope de $99.999.999: un número más largo (un dedazo, un pegado raro)
    // desbordaba la columna INT y tumbaba la página a mitad de una acción.
    $digitos = (string) preg_replace('/\D+/', '', $texto);

    return strlen($digitos) > 8 ? 99_999_999 : (int) $digitos;
}

/** Minutos transcurridos desde una fecha DATETIME hasta ahora. */
function minutos_desde(string $fechaHora): int
{
    return (int) max(0, floor((time() - strtotime($fechaHora)) / 60));
}

/** "5 min esperando" / "2 horas esperando": para pedidos y citas sin resolver. */
function texto_espera(int $minutos): string
{
    if ($minutos < 1) {
        return 'recién llegó';
    }
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
 * Si la sede está abierta en este preciso momento, según su horario
 * (Sede::horario(): día ISO 1=lunes..7=domingo => franjas [inicio, fin]).
 * "pausa" es true si hoy ya abrió y vuelve a abrir más tarde (el almuerzo):
 * la tienda dice "En pausa · vuelve a las 2 p. m." en vez de un "Cerrado"
 * que hace pensar que ya no atienden hoy. Null si el negocio no tiene
 * horario configurado: en ese caso no hay nada honesto que mostrar.
 *
 * @param array<string, array<int, array{0:string,1:string}>> $horario
 * @return array{abierto: bool, desde: ?string, hasta: ?string, pausa: bool, vuelve: ?string}|null
 */
function negocio_abierto_ahora(array $horario): ?array
{
    if ($horario === []) {
        return null;
    }
    $franjas = $horario[(string) date('N')] ?? [];
    $ahora = date('H:i');
    foreach ($franjas as $i => [$inicio, $fin]) {
        if ($ahora >= $inicio && $ahora < $fin) {
            return ['abierto' => true, 'desde' => $inicio, 'hasta' => $fin, 'pausa' => false, 'vuelve' => null];
        }
        if ($i > 0 && $ahora < $inicio && $ahora >= $franjas[$i - 1][1]) {
            return ['abierto' => false, 'desde' => null, 'hasta' => null, 'pausa' => true, 'vuelve' => $inicio];
        }
    }

    return ['abierto' => false, 'desde' => null, 'hasta' => null, 'pausa' => false, 'vuelve' => null];
}

/**
 * "18:00" → "6 p. m."; "9:30" → "9:30 a. m.". El mediodía exacto se
 * escribe "12 m.", como se dice en Colombia (y como sale en los letreros
 * de "Cerrado de 12 m. a 2 p. m.").
 */
function hora_legible(string $hora): string
{
    $ts = strtotime($hora) ?: 0;
    if (date('H:i', $ts) === '12:00') {
        return '12 m.';
    }
    $minutos = date('i', $ts);
    $meridiano = date('a', $ts) === 'am' ? 'a. m.' : 'p. m.';
    return date('g', $ts) . ($minutos !== '00' ? ':' . $minutos : '') . ' ' . $meridiano;
}

/**
 * Cuándo vuelve a abrir, para completar "Cerrado ahora" con algo útil
 * ("Abre mañana a las 9 a. m.", o "hoy a las 2 p. m." después del
 * almuerzo) en vez de dejar al cliente adivinando. Solo tiene sentido
 * llamarla cuando ya se sabe que está cerrado ahora mismo. Null si no hay
 * horario o si no abre ningún día de la semana siguiente.
 *
 * @param array<string, array<int, array{0:string,1:string}>> $horario
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
        foreach ($horario[(string) $diaIso] ?? [] as [$inicio]) {
            if ($offset === 0 && $ahora >= $inicio) {
                continue; // esa franja de hoy ya empezó (o pasó)
            }
            $etiqueta = $offset === 0 ? 'hoy' : ($offset === 1 ? 'mañana' : $diasNombre[$diaIso - 1]);
            return ['dia' => $etiqueta, 'hora' => hora_legible($inicio)];
        }
    }

    return null;
}

/**
 * Agrupa Sede::horario() en líneas legibles, uniendo días consecutivos con
 * el mismo horario (y la misma pausa) en un solo rango ("Lun-Vie"). Los
 * días sin abrir aparecen como "Cerrado" en vez de desaparecer — un
 * negocio que no trabaja domingo necesita poder decirlo. Única excepción:
 * sin NINGÚN horario configurado devuelve [] en vez de un "Lun-Dom:
 * Cerrado" que daría a entender que cerró para siempre.
 *
 * Cada línea trae "franjas" (una por tramo: "8 a. m. – 12 m.", "2 – 6 p. m.")
 * para que la vista las ponga una debajo de otra, y "rango" con todo junto
 * para quien necesite una sola cadena. "hoy" marca la línea de hoy.
 *
 * @param array<string, array<int, array{0:string,1:string}>> $horario
 * @return array<int, array{dia: string, rango: string, franjas: array<int, string>, hoy: bool}>
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
    $actual = $SIN_INICIAR;

    for ($dia = 1; $dia <= 8; $dia++) {
        $franjas = $dia <= 7 ? ($horario[(string) $dia] ?? null) : $FIN;
        $cambia = $franjas !== $actual;

        if ($cambia && $actual !== $SIN_INICIAR) {
            $nombre = $inicioGrupo === $dia - 1 ? $dias[$inicioGrupo - 1] : $dias[$inicioGrupo - 1] . '-' . $dias[$dia - 2];
            $textos = $actual === null ? ['Cerrado'] : array_map('franja_legible', $actual);
            $hoyIso = (int) date('N');
            $lineas[] = [
                'dia'     => $nombre,
                'rango'   => implode(' y ', $textos),
                'franjas' => $textos,
                'hoy'     => $hoyIso >= $inicioGrupo && $hoyIso <= $dia - 1,
            ];
        }
        if ($cambia) {
            $inicioGrupo = $dia;
        }
        $actual = $franjas;
    }

    return $lineas;
}

/**
 * ["14:00","18:00"] → "2 – 6 p. m." (el meridiano una vez si es el mismo);
 * ["08:00","12:00"] → "8 a. m. – 12 m.".
 *
 * @param array{0:string,1:string} $franja
 */
function franja_legible(array $franja): string
{
    $inicio = hora_legible($franja[0]);
    $fin = hora_legible($franja[1]);
    foreach ([' a. m.', ' p. m.'] as $meridiano) {
        if (str_ends_with($inicio, $meridiano) && str_ends_with($fin, $meridiano)) {
            $inicio = substr($inicio, 0, -strlen($meridiano));
        }
    }

    return $inicio . ' – ' . $fin;
}

/**
 * Color de texto legible sobre un fondo de color de marca: tinta oscura o
 * papel claro según la luminancia relativa (WCAG). El negocio elige su
 * color libremente (amarillo, azul, rosado...) y la letra encima tiene que
 * seguir leyéndose — una "S" negra sobre azul oscuro no se lee.
 */
function color_texto_sobre(string $hex): string
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
        return '#1B1A17';
    }
    $canal = static function (string $par): float {
        $c = hexdec($par) / 255;
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };
    $luminancia = 0.2126 * $canal(substr($hex, 0, 2)) + 0.7152 * $canal(substr($hex, 2, 2)) + 0.0722 * $canal(substr($hex, 4, 2));

    // Contraste contra tinta (#1B1A17, L≈0.011) vs. contra papel (#FFFDF8, L≈0.98):
    // gana el que dé más contraste.
    $contraTinta = ($luminancia + 0.05) / (0.011 + 0.05);
    $contraPapel = (0.98 + 0.05) / ($luminancia + 0.05);
    return $contraTinta >= $contraPapel ? '#1B1A17' : '#FFFDF8';
}

/** Un color hex válido (#RRGGBB) o el de respaldo: evita que un dato raro rompa el CSS inline. */
function color_seguro(?string $hex, string $respaldo = '#F2B632'): string
{
    return is_string($hex) && preg_match('/^#[0-9a-f]{6}$/i', trim($hex)) ? trim($hex) : $respaldo;
}

/**
 * "09:30" → "9:30 a. m.", siempre con minutos. A diferencia de hora_legible()
 * (que omite ":00" en textos sueltos como "abre a las 9 a. m."), aquí se usa
 * donde conviven horas en punto y con minutos en la misma grilla — mostrar
 * siempre los minutos evita mezclar "9 a. m." con "9:30 a. m.".
 */
function hora_completa(string $hora): string
{
    $ts = strtotime($hora) ?: 0;
    return date('g:i', $ts) . ' ' . (date('a', $ts) === 'am' ? 'a. m.' : 'p. m.');
}

/**
 * Una fecha como hojita de almanaque (día de la semana, número grande, mes)
 * para los selectores de día de la tienda: reservar y reprogramar usan
 * exactamente el mismo marcado. $href ya debe venir armado (sin escapar).
 */
function hoja_almanaque(string $fecha, string $fechaActiva, string $href): string
{
    $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $ts = strtotime($fecha) ?: 0;
    $esHoy = $fecha === date('Y-m-d');
    $activo = $fecha === $fechaActiva;

    return '<a href="' . e($href) . '" class="pq-dia' . ($activo ? ' pq-dia-activo' : '') . '"'
        . ($activo ? ' aria-current="date"' : '')
        . ' aria-label="' . e(($esHoy ? 'Hoy, ' : '') . fecha_larga($fecha)) . '">'
        . '<span class="pq-dia-semana">' . ($esHoy ? 'hoy' : $dias[(int) date('w', $ts)]) . '</span>'
        . '<span class="pq-dia-numero">' . (int) date('j', $ts) . '</span>'
        . '<span class="pq-dia-mes">' . $meses[(int) date('n', $ts) - 1] . '</span>'
        . '</a>';
}

/**
 * Colores de toldo que el dueño puede elegir para su tienda (Negocio::
 * actualizarColor solo acepta estos). Una paleta cerrada, con nombres de
 * barrio, en vez de un selector libre: todos funcionan como rayas del toldo
 * y con color_texto_sobre() siempre dan una letra legible encima.
 *
 * @return array<string, string> hex => nombre
 */
function paleta_marca(): array
{
    return [
        '#E8452C' => 'Ají',
        '#F28C28' => 'Mango',
        '#F2B632' => 'Mostaza',
        '#3F8F4E' => 'Hoja de plátano',
        '#1F9AA6' => 'Turquesa',
        '#3B4CCA' => 'Añil',
        '#7A4FB5' => 'Mora',
        '#E85A8B' => 'Guayaba',
        '#8A5A3C' => 'Café',
    ];
}

/** 0 → "hoy", 1 → "ayer", 5 → "hace 5 días" (nunca "hace 1 días"). */
function hace_dias(int $dias): string
{
    return match (true) {
        $dias <= 0 => 'hoy',
        $dias === 1 => 'ayer',
        default => "hace {$dias} días",
    };
}

/** 'breb' → "Bre-B", 'nequi' → "Nequi"... Nunca mostrar el código crudo ("BREB") al cliente ni al dueño. */
function metodo_pago_legible(string $metodo): string
{
    return match ($metodo) {
        'breb'     => 'Bre-B',
        'nequi'    => 'Nequi',
        'efectivo' => 'Efectivo',
        default    => ucfirst($metodo),
    };
}

/**
 * Valida y normaliza una llave Bre-B según su tipo; null si no sirve.
 * Una llave mal escrita no se nota hasta que un cliente intenta pagar y
 * la transferencia rebota, así que se revisa al guardarla:
 *   - celular: 10 dígitos que empiezan por 3 (acepta "+57", espacios, guiones).
 *   - cedula:  5 a 10 dígitos (acepta puntos).
 *   - correo:  un correo válido, en minúsculas.
 */
function llave_breb_normalizada(string $tipo, string $valor): ?string
{
    $valor = trim($valor);

    if ($tipo === 'correo') {
        $correo = mb_strtolower($valor);
        return mb_strlen($correo) <= 120 && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false ? $correo : null;
    }

    $digitos = preg_replace('/\D+/', '', $valor) ?? '';
    if ($tipo === 'celular') {
        if (strlen($digitos) === 12 && str_starts_with($digitos, '57')) {
            $digitos = substr($digitos, 2);
        }
        return preg_match('/^3\d{9}$/', $digitos) === 1 ? $digitos : null;
    }
    if ($tipo === 'cedula') {
        return preg_match('/^\d{5,10}$/', $digitos) === 1 ? $digitos : null;
    }

    return null;
}

/**
 * "El almuerzo del martes y jueves no cabía…": aviso cuando una pausa del
 * formulario de la semana no se pudo aplicar (ver Sede::horarioDesdePost).
 *
 * @param array<int, string> $dias
 */
function aviso_pausas_invalidas(array $dias): ?string
{
    if ($dias === []) {
        return null;
    }
    $lista = count($dias) === 1 ? $dias[0] : implode(', ', array_slice($dias, 0, -1)) . ' y ' . end($dias);

    return 'La pausa del ' . $lista . ' no quedaba dentro del horario de ese día (tiene que empezar después de abrir y terminar antes de cerrar), así que ese día quedó corrido. Revísala.';
}

/**
 * Días de calendario entre una fecha y hoy (ayer a las 11 p. m. es "ayer"
 * aunque no hayan pasado 24 horas). Para usar con hace_dias().
 */
function dias_desde(string $fecha): int
{
    $dia = strtotime(date('Y-m-d', strtotime($fecha) ?: time()));

    return max(0, (int) round((strtotime(date('Y-m-d')) - $dia) / 86400));
}

/**
 * A dónde volver después de una acción sobre una cita: la agenda, o la
 * hoja de la visita si la acción se hizo desde ahí (solo esa ruta: nada de
 * redirigir a lo que mande el formulario).
 */
function destino_agenda(): string
{
    $volver = (string) ($_POST['volver'] ?? '');

    return preg_match('#^/panel/visitas/\d+$#', $volver) ? $volver : '/panel/citas';
}
