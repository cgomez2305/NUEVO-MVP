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
