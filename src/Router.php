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
