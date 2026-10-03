<?php

declare(strict_types=1);

/**
 * Prueba de aislamiento entre negocios (multi-tenant).
 *
 * Inicia sesión como el dueño de un negocio ("atacante") y recorre TODAS las
 * rutas del panel que llevan un id en la URL (las lee de public/index.php),
 * usando ids que pertenecen a OTROS negocios. Pasa si:
 *   1. Ninguna respuesta (cuerpo ni cabecera Location) muestra datos de los
 *      otros negocios: nombres de sus clientes, productos, servicios,
 *      empleados ni sus teléfonos.
 *   2. Ninguna fila de los otros negocios cambió (huella de la base antes y
 *      después de mandar todos los POST).
 *
 * Con un COLABORADOR, además, las sedes de su mismo negocio que no tiene
 * asignadas cuentan como ajenas (y los clientes que solo compran allá).
 *
 * Uso (contra una base de PRUEBA, nunca producción: los POST se envían):
 *   php tests/aislamiento.php http://localhost:8000 3001234567 veci123
 * Sale con código 1 si encuentra una fuga.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

use App\Database;

[$_, $base, $whatsapp, $clave] = $argv + [null, 'http://localhost:8000', '3001234567', 'veci123'];
$pdo = Database::conexion();

$atacante = $pdo->prepare('SELECT id, negocio_id, rol FROM usuarios WHERE whatsapp = :w');
$atacante->execute(['w' => $whatsapp]);
$usuarioAtacante = $atacante->fetch(PDO::FETCH_ASSOC) ?: null;
$negocioAtacante = (int) ($usuarioAtacante['negocio_id'] ?? 0);
if ($negocioAtacante === 0) {
    fwrite(STDERR, "No existe un usuario con WhatsApp {$whatsapp}\n");
    exit(2);
}
// Un colaborador solo es "dueño" de sus sedes: las demás sedes de SU
// negocio también cuentan como ajenas (aislamiento entre sedes).
$esColaborador = $usuarioAtacante['rol'] !== 'dueno';
$sedesPropias = $esColaborador
    ? array_map('intval', $pdo->query("SELECT sede_id FROM usuario_sedes WHERE usuario_id = " . (int) $usuarioAtacante['id'])->fetchAll(PDO::FETCH_COLUMN))
    : array_map('intval', $pdo->query("SELECT id FROM sedes WHERE negocio_id = {$negocioAtacante}")->fetchAll(PDO::FETCH_COLUMN));
$inPropias = implode(',', $sedesPropias ?: [0]);

$sedesVictima = array_map('intval', $pdo->query("SELECT id FROM sedes WHERE id NOT IN ({$inPropias})")->fetchAll(PDO::FETCH_COLUMN));
$negociosVictima = array_map('intval', $pdo->query("SELECT id FROM negocios WHERE id <> {$negocioAtacante}")->fetchAll(PDO::FETCH_COLUMN));
if ($sedesVictima === []) {
    fwrite(STDERR, "Hace falta al menos otra sede con datos para probar.\n");
    exit(2);
}
$inSedes = implode(',', $sedesVictima);
$inNegocios = implode(',', $negociosVictima ?: [0]);
// Clientes ajenos: los de otros negocios y, para un colaborador, los de su
// negocio que solo tienen movimiento en sedes que no son suyas.
$sqlClientesAjenos = "SELECT c.id FROM clientes c WHERE c.negocio_id IN ({$inNegocios})"
    . ($esColaborador ? " OR (c.negocio_id = {$negocioAtacante}
        AND EXISTS (SELECT 1 FROM pedidos p WHERE p.cliente_id = c.id UNION SELECT 1 FROM citas ct WHERE ct.cliente_id = c.id UNION SELECT 1 FROM ventas v WHERE v.cliente_id = c.id UNION SELECT 1 FROM fiado_movimientos f WHERE f.cliente_id = c.id)
        AND NOT EXISTS (SELECT 1 FROM pedidos p WHERE p.cliente_id = c.id AND p.sede_id IN ({$inPropias}) UNION SELECT 1 FROM citas ct WHERE ct.cliente_id = c.id AND ct.sede_id IN ({$inPropias}) UNION SELECT 1 FROM ventas v WHERE v.cliente_id = c.id AND v.sede_id IN ({$inPropias}) UNION SELECT 1 FROM fiado_movimientos f WHERE f.cliente_id = c.id AND f.sede_id IN ({$inPropias})))" : '');

// ---------- Ids de los otros negocios, por tabla ----------
$mismoNegocio = $esColaborador ? $negocioAtacante : null;
$idsVictima = static function (string $tabla) use ($pdo, $inSedes, $inNegocios, $sqlClientesAjenos, $mismoNegocio): array {
    $columnas = $pdo->query("SHOW COLUMNS FROM `{$tabla}`")->fetchAll(PDO::FETCH_COLUMN);
    $donde = in_array('sede_id', $columnas, true) ? "sede_id IN ({$inSedes})" : (in_array('negocio_id', $columnas, true) ? "negocio_id IN ({$inNegocios})" : null);
    if ($tabla === 'sedes') {
        $donde = "id IN ({$inSedes})";
    }
    if ($tabla === 'clientes') {
        $donde = "id IN ({$sqlClientesAjenos})";
    }
    if ($donde === null) {
        return [];
    }

    $ids = array_map('intval', $pdo->query("SELECT id FROM `{$tabla}` WHERE {$donde} ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN));
    // Colaborador: también ids de las otras sedes de SU negocio (los más
    // altos suelen ser de otros negocios y esas sedes quedarían sin probar).
    if ($mismoNegocio !== null) {
        $deMiNegocio = $tabla === 'clientes'
            ? "id IN ({$sqlClientesAjenos}) AND negocio_id = {$mismoNegocio}"
            : (in_array('sede_id', $columnas, true) ? "sede_id IN ({$inSedes}) AND sede_id IN (SELECT id FROM sedes WHERE negocio_id = {$mismoNegocio})" : null);
        if ($deMiNegocio !== null) {
            $ids = array_values(array_unique(array_merge(
                array_map('intval', $pdo->query("SELECT id FROM `{$tabla}` WHERE {$deMiNegocio} ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN)),
                $ids
            )));
        }
    }

    return $ids;
};
$porSegmento = [
    'pedidos' => 'pedidos', 'productos' => 'productos', 'servicios' => 'servicios', 'empleados' => 'empleados',
    'citas' => 'citas', 'visitas' => 'citas', 'recordatorios' => 'citas', 'repetir' => 'citas',
    'copiloto' => 'clientes', 'fiado' => 'clientes', 'fidelidad' => 'clientes', 'cupones' => 'cupones',
    'planes' => 'planes_tratamiento', 'fila' => 'turnos_fila', 'domicilios' => 'zonas_domicilio',
    'cobertura' => 'zonas_domicilio', 'sedes' => 'sedes', 'paquetes' => 'paquetes', 'mostrador' => 'ventas',
    'colaboradores' => 'usuarios', 'adicionales' => 'adicionales', 'resenas' => 'resenas',
    'lista-espera' => 'lista_espera', 'horario' => 'fechas_bloqueadas', 'compras' => 'compras',
];
$porParametro = ['cliente' => 'clientes', 'cita' => 'citas', 'abono' => 'plan_abonos', 'movimiento' => 'fiado_movimientos', 'sede' => 'sedes'];
$cache = [];
$ids = static function (string $tabla) use (&$cache, $idsVictima): array {
    return $cache[$tabla] ??= $idsVictima($tabla);
};

// ---------- Lo que nunca debe verse: nombres y teléfonos ajenos ----------
$marcas = [];
foreach ([
    "SELECT nombre, telefono FROM clientes WHERE id IN ({$sqlClientesAjenos})",
    "SELECT nombre, NULL FROM productos WHERE sede_id IN ({$inSedes})",
    "SELECT nombre, NULL FROM servicios WHERE sede_id IN ({$inSedes})",
    "SELECT nombre, NULL FROM empleados WHERE sede_id IN ({$inSedes})",
] as $sql) {
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_NUM) as [$nombre, $telefono]) {
        if (mb_strlen((string) $nombre) >= 6) {
            $marcas[] = (string) $nombre;
        }
        if ($telefono !== null && strlen((string) $telefono) >= 10) {
            $marcas[] = (string) $telefono;
        }
    }
}
// Un nombre que también existe en el negocio atacante no prueba nada.
$propios = $pdo->query(
    "SELECT nombre FROM clientes WHERE negocio_id = {$negocioAtacante} AND id NOT IN ({$sqlClientesAjenos})
     UNION SELECT nombre FROM productos WHERE sede_id IN ({$inPropias})
     UNION SELECT nombre FROM servicios WHERE sede_id IN ({$inPropias})
     UNION SELECT nombre FROM empleados WHERE sede_id IN ({$inPropias})
     UNION SELECT nombre FROM sedes WHERE id IN ({$inPropias})
     UNION SELECT nombre FROM usuarios WHERE negocio_id = {$negocioAtacante}"
)->fetchAll(PDO::FETCH_COLUMN);
// El nombre de una sede propia o del equipo sale en el menú: no es fuga.
$propiosTelefonos = $pdo->query("SELECT telefono FROM clientes WHERE negocio_id = {$negocioAtacante} AND id NOT IN ({$sqlClientesAjenos})")->fetchAll(PDO::FETCH_COLUMN);
$propios = array_merge($propios, $propiosTelefonos);
$marcas = array_values(array_diff(array_unique($marcas), $propios));

// ---------- Huella de todo lo de los otros negocios ----------
$huella = static function () use ($pdo, $inSedes, $inNegocios): array {
    $tablas = $pdo->query(
        "SELECT table_name, GROUP_CONCAT(column_name) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND column_name IN ('sede_id', 'negocio_id') GROUP BY table_name"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $resultado = [];
    foreach ($tablas as $tabla => $columnas) {
        $donde = str_contains((string) $columnas, 'sede_id') ? "sede_id IN ({$inSedes})" : "negocio_id IN ({$inNegocios})";
        if ($tabla === 'sedes') {
            $donde = "id IN ({$inSedes})";
        }
        $filas = $pdo->query("SELECT * FROM `{$tabla}` WHERE {$donde} ORDER BY 1")->fetchAll(PDO::FETCH_ASSOC);
        $resultado[$tabla] = md5(json_encode($filas));
    }

    return $resultado;
};

// ---------- HTTP con sesión ----------
$jar = tempnam(sys_get_temp_dir(), 'veci-aisl');
$pedir = static function (string $metodo, string $ruta, array $datos = []) use ($base, $jar): array {
    $ch = curl_init($base . $ruta);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 20,
    ]);
    if ($metodo === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
    }
    $respuesta = (string) curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$codigo, $respuesta];
};
$csrf = static function (string $html): string {
    return preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
};
$entrar = static function () use ($pedir, $csrf, $whatsapp, $clave): string {
    [, $html] = $pedir('GET', '/login');
    $pedir('POST', '/login', ['_csrf' => $csrf($html), 'whatsapp' => $whatsapp, 'password' => $clave]);
    // "Mi cuenta" siempre tiene formularios (el inicio puede no tener ninguno).
    [, $cuenta] = $pedir('GET', '/panel/cuenta');

    return $csrf($cuenta);
};

$token = $entrar();
if ($token === '') {
    fwrite(STDERR, "No se pudo iniciar sesión como {$whatsapp}\n");
    exit(2);
}

// ---------- Rutas del panel con parámetros ----------
preg_match_all("/\\\$router->(get|post)\('(\/panel[^']*\{[^']*)'/", (string) file_get_contents(__DIR__ . '/../public/index.php'), $m, PREG_SET_ORDER);
$antes = $huella();
$fugas = [];
$probadas = 0;

foreach ($m as [, $metodo, $ruta]) {
    preg_match('#^/panel/([a-z-]+)#', $ruta, $seg);
    preg_match_all('/\{([a-z]+)\}/', $ruta, $params);
    $candidatos = [];
    foreach ($params[1] as $i => $param) {
        $tabla = $porParametro[$param] ?? ($i === 0 ? ($porSegmento[$seg[1]] ?? null) : null);
        // Parámetros secundarios sin tabla conocida (fotos, códigos): un id cualquiera.
        $candidatos[$param] = $tabla !== null ? $ids($tabla) : ['1'];
        if ($param === 'codigo') {
            $candidatos[$param] = array_column($pdo->query("SELECT codigo_barras FROM productos WHERE sede_id IN ({$inSedes}) AND codigo_barras IS NOT NULL LIMIT 2")->fetchAll(PDO::FETCH_NUM), 0) ?: ['7700000000000'];
        }
    }
    $primero = array_key_first($candidatos);
    foreach ($candidatos[$primero] ?? [] as $valor) {
        $url = str_replace('{' . $primero . '}', (string) $valor, $ruta);
        foreach ($candidatos as $param => $valores) {
            $url = str_replace('{' . $param . '}', (string) ($valores[0] ?? 1), $url);
        }
        [$codigo, $respuesta] = $pedir(strtoupper($metodo), $url, ['_csrf' => $token, 'estado' => 'cancelado', 'monto' => '1000', 'nota' => 'x']);
        $probadas++;
        if ($codigo === 302 && str_contains($respuesta, 'Location: ' . $base . '/login')) {
            $token = $entrar(); // alguna acción cerró la sesión: se vuelve a entrar
        }
        foreach ($marcas as $marca) {
            if (str_contains($respuesta, $marca) || str_contains($respuesta, rawurlencode($marca))) {
                $fugas[] = strtoupper($metodo) . " {$url} → muestra «{$marca}» (HTTP {$codigo})";
                break;
            }
        }
    }
}

// ---------- Pantallas sin id (listas, buscadores, mostrador) ----------
// Una lista que trae clientes de otra sede filtra igual que un id ajeno.
preg_match_all('#\$router->get\(\'(/panel[^\'{]*)\'#', (string) file_get_contents(__DIR__ . '/../public/index.php'), $listas);
foreach (array_unique($listas[1]) as $ruta) {
    if (str_contains($ruta, 'exportar') || str_contains($ruta, 'confirmar') || str_contains($ruta, 'onboarding')) {
        continue;
    }
    [$codigo, $respuesta] = $pedir('GET', $ruta);
    $probadas++;
    foreach ($marcas as $marca) {
        if (str_contains($respuesta, $marca) || str_contains($respuesta, rawurlencode($marca))) {
            $fugas[] = "GET {$ruta} → muestra «{$marca}» (HTTP {$codigo})";
            break;
        }
    }
}

$despues = $huella();
foreach ($antes as $tabla => $firma) {
    if (($despues[$tabla] ?? null) !== $firma) {
        $fugas[] = "La tabla {$tabla} de otro negocio CAMBIÓ tras los POST del negocio {$negocioAtacante}";
    }
}
@unlink($jar);

echo ($esColaborador ? 'Colaborador' : 'Dueño') . " del negocio {$negocioAtacante} · rutas con id probadas: " . count($m) . " · peticiones: {$probadas} · marcas vigiladas: " . count($marcas) . "\n";
if ($fugas === []) {
    echo "✓ Sin fugas: ningún dato ni fila de otro negocio fue visible ni modificable.\n";
    exit(0);
}
foreach (array_unique($fugas) as $fuga) {
    echo "✗ {$fuga}\n";
}
exit(1);
