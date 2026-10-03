<?php

declare(strict_types=1);

namespace App;

/**
 * Router mínimo: patrones tipo /t/{slug}, sin dependencias externas.
 */
class Router
{
    /** @var array<int, array{0:string,1:string,2:callable}> */
    private array $rutas = [];

    /**
     * Rutas POST que no llevan token CSRF porque no las llama un navegador
     * con sesión sino un proveedor (Wompi, el banco): se autentican con su
     * propia firma o secreto dentro del controlador.
     *
     * @param array<int, string> $sinCsrf patrones exactos, p. ej. '/webhooks/wompi'
     */
    public function __construct(private array $sinCsrf = [])
    {
    }

    public function get(string $patron, callable $manejador): void
    {
        $this->agregar('GET', $patron, $manejador);
    }

    public function post(string $patron, callable $manejador): void
    {
        $this->agregar('POST', $patron, $manejador);
    }

    private function agregar(string $metodo, string $patron, callable $manejador): void
    {
        $this->rutas[] = [$metodo, $patron, $manejador];
    }

    public function despachar(string $metodo, string $uriCompleta): void
    {
        $ruta = (string) (parse_url($uriCompleta, PHP_URL_PATH) ?? '/');
        $ruta = rtrim($ruta, '/');
        if ($ruta === '') {
            $ruta = '/';
        }

        foreach ($this->rutas as [$metodoRuta, $patron, $manejador]) {
            if ($metodoRuta !== $metodo) {
                continue;
            }

            $regex = $this->compilar($patron);
            if (preg_match($regex, $ruta, $coincidencias) === 1) {
                if ($metodo === 'POST' && !in_array($patron, $this->sinCsrf, true) && !$this->postConfiable()) {
                    $this->rechazarPost();
                    return;
                }
                $parametros = array_filter(
                    $coincidencias,
                    fn ($llave) => !is_int($llave),
                    ARRAY_FILTER_USE_KEY
                );
                call_user_func($manejador, $parametros);
                return;
            }
        }

        http_response_code(404);
        require __DIR__ . '/Views/errores/404.php';
    }

    /**
     * Segunda línea de defensa contra CSRF para TODO formulario, aunque un
     * controlador nuevo olvide su csrf_verificar(): el token de la sesión
     * debe venir en el cuerpo, y si el navegador dice de dónde viene la
     * petición (Origin / Sec-Fetch-Site), tiene que ser de este mismo sitio.
     * Un envío más grande que post_max_size llega sin cuerpo (ni token): se
     * deja pasar para que el controlador explique "la foto pesa demasiado";
     * sin datos no puede cambiar nada.
     */
    private function postConfiable(): bool
    {
        if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
            return false;
        }
        $origen = $_SERVER['HTTP_ORIGIN'] ?? null;
        if (is_string($origen) && $origen !== '') {
            $host = strtolower((string) parse_url($origen, PHP_URL_HOST));
            $puerto = parse_url($origen, PHP_URL_PORT);
            $deOrigen = $host . ($puerto !== null && $puerto !== false ? ':' . $puerto : '');
            $propios = [strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))];
            // Detrás de un proxy el Host interno puede no ser el público.
            $publico = parse_url((string) config('app.url', ''));
            if (is_array($publico) && isset($publico['host'])) {
                $propios[] = strtolower($publico['host'] . (isset($publico['port']) ? ':' . $publico['port'] : ''));
            }
            // 'null' (sandbox, data:) o cualquier otro dominio: no es este sitio.
            if ($host === '' || !in_array($deOrigen, $propios, true)) {
                return false;
            }
        }

        return csrf_verificar() || post_demasiado_grande();
    }

    /** "Esta página caducó": sin hacer nada, con un enlace para volver a intentarlo. */
    private function rechazarPost(): void
    {
        http_response_code(403);
        $pideJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        if ($pideJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'La página caducó. Recárgala e intenta de nuevo.']);

            return;
        }
        // Volver a la página desde la que se envió, si era de este sitio
        // (solo la ruta: nunca un dominio externo).
        $volver = '/';
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '' && strcasecmp((string) parse_url($referer, PHP_URL_HOST), (string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) === 0) {
            $ruta = (string) parse_url($referer, PHP_URL_PATH);
            $consulta = (string) parse_url($referer, PHP_URL_QUERY);
            if (str_starts_with($ruta, '/') && !str_starts_with($ruta, '//')) {
                $volver = $ruta . ($consulta !== '' ? '?' . $consulta : '');
            }
        }
        require __DIR__ . '/Views/errores/expirado.php';
    }

    private function compilar(string $patron): string
    {
        $patron = rtrim($patron, '/');
        if ($patron === '') {
            $patron = '/';
        }

        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $patron);

        return '#^' . $regex . '$#u';
    }
}
