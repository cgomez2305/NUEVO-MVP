<?php

declare(strict_types=1);

/**
 * Revisión estática de seguridad (no necesita base ni servidor):
 *   1. Toda ruta /panel/... llama a Auth::exigirSesion() (directo o por un
 *      ayudante del controlador) y toda ruta /admin/... a AdminAuth.
 *   2. Ninguna vista imprime una variable sin escapar: cada <?= ... ?> pasa
 *      por e(), un número, o un ayudante que ya devuelve HTML seguro.
 *   3. Ningún SQL arma valores con $_GET/$_POST/$_COOKIE dentro de la cadena.
 *
 * Uso: php tests/estatico.php   (sale con código 1 si encuentra algo)
 * Va en el CI: es rápido y atrapa el olvido típico de una pantalla nueva.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
$problemas = [];

// ---------- 1. Rutas protegidas ----------
$indice = (string) file_get_contents($raiz . '/public/index.php');
preg_match_all('/\$(\w+) = new (\w+)\(\)/', $indice, $m, PREG_SET_ORDER);
$clases = [];
foreach ($m as [, $variable, $clase]) {
    $clases[$variable] = $clase;
}
preg_match_all("#\\\$router->(get|post)\\('(/(?:panel|admin)[^']*)', \\[\\\$(\\w+), '(\\w+)'\\]\\)#", $indice, $rutas, PREG_SET_ORDER);

/** Cuerpo de un método (hasta la siguiente declaración de método). */
$cuerpo = static function (string $codigo, string $metodo): ?string {
    if (!preg_match('/function ' . preg_quote($metodo, '/') . '\(.*?(?=\n    (?:public|private|protected) (?:static )?function |\n}\s*$)/s', $codigo, $c)) {
        return null;
    }

    return $c[0];
};
$publicasAdmin = ['formularioLogin', 'iniciarSesion', 'cerrarSesion'];
foreach ($rutas as [, $verbo, $ruta, $variable, $metodo]) {
    $clase = $clases[$variable] ?? null;
    $codigo = $clase !== null ? (string) @file_get_contents("{$raiz}/src/Controllers/{$clase}.php") : '';
    $texto = $cuerpo($codigo, $metodo);
    if ($texto === null) {
        $problemas[] = "Ruta {$verbo} {$ruta}: no encontré {$clase}::{$metodo}";
        continue;
    }
    if (str_starts_with($ruta, '/admin')) {
        if (!in_array($metodo, $publicasAdmin, true) && !str_contains($texto, 'AdminAuth::exigirSesion(')) {
            $problemas[] = "Ruta {$verbo} {$ruta} ({$clase}::{$metodo}) no exige sesión de admin";
        }
        continue;
    }
    // Directo o por ayudantes privados del controlador (hasta 3 niveles).
    $protegida = false;
    $pendientes = [$texto];
    for ($nivel = 0; $nivel < 4 && !$protegida && $pendientes !== []; $nivel++) {
        $siguientes = [];
        foreach ($pendientes as $fragmento) {
            if (str_contains($fragmento, 'Auth::exigirSesion(')) {
                $protegida = true;
                break;
            }
            preg_match_all('/\$this->(\w+)\(/', $fragmento, $llamadas);
            foreach (array_unique($llamadas[1]) as $ayudante) {
                $siguientes[] = (string) $cuerpo($codigo, $ayudante);
            }
        }
        $pendientes = $siguientes;
    }
    if (!$protegida) {
        $problemas[] = "Ruta {$verbo} {$ruta} ({$clase}::{$metodo}) no exige sesión";
    }
}

// ---------- 2. Vistas: texto de la base siempre escapado ----------
// El olvido típico (y el XSS almacenado) es imprimir directo un campo de
// texto que escribió alguien: $cliente['nombre'], $pedido['notas']... Un
// <?= que lea uno de esos campos tiene que pasar por e().
$camposTexto = 'nombre|cliente_nombre|telefono|cliente_telefono|direccion|referencia|notas?|nota|descripcion|detalle|texto|mensaje|comentario|problema|motivo|servicio_nombre|nombre_servicio|empleado_nombre|producto_nombre|negocio_nombre|correo|codigo|slug|whatsapp|premio|titulo|etiqueta|valor|zona|mesa';
$iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/src/Views'));
foreach ($iterador as $archivo) {
    if (!str_ends_with((string) $archivo, '.php')) {
        continue;
    }
    foreach (file((string) $archivo) as $n => $linea) {
        if (!preg_match_all('/<\?=(.*?)\?>/', $linea, $ecos)) {
            continue;
        }
        foreach ($ecos[1] as $eco) {
            // Se quita todo lo que va dentro de e(...) (o de (int)): lo que queda se imprime crudo.
            // También las condiciones (empty/isset y comparaciones): no imprimen nada.
            $crudo = preg_replace([
                '/\b(e|pesos|base_url|url_publica|count|number_format|empty|isset)\s*(\((?:[^()]++|(?2))*\))/',
                '/\(int\)\s*\$\w+(?:\[[^\]]*\])*/',
                '/\$\w+(?:\[[^\]]*\])+\s*(?:===|!==|==|!=)\s*(?:\'[^\']*\'|"[^"]*"|null|\d+)/',
            ], "''", $eco) ?? $eco;
            if (preg_match('/\$\w+\[\s*[\'"](' . $camposTexto . ')[\'"]\s*\]/', $crudo)) {
                $problemas[] = substr((string) $archivo, strlen($raiz) + 1) . ':' . ($n + 1) . ' imprime texto sin e(): ' . trim($eco);
            }
        }
    }
}

// ---------- 3. SQL con datos del usuario dentro de la cadena ----------
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/src')) as $archivo) {
    if (!str_ends_with((string) $archivo, '.php')) {
        continue;
    }
    foreach (file((string) $archivo) as $n => $linea) {
        if (preg_match('/(prepare|query|exec)\(.*\$_(GET|POST|COOKIE|REQUEST|SERVER)/', $linea)) {
            $problemas[] = substr((string) $archivo, strlen($raiz) + 1) . ':' . ($n + 1) . ' arma SQL con datos de la petición';
        }
    }
}

echo 'Rutas de panel/admin revisadas: ' . count($rutas) . "\n";
if ($problemas === []) {
    echo "✓ Revisión estática limpia.\n";
    exit(0);
}
foreach ($problemas as $problema) {
    echo "✗ {$problema}\n";
}
exit(1);
