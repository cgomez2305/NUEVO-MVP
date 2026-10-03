<?php

declare(strict_types=1);

/**
 * Prueba de la demo pública "Sube tu foto sin cuenta" (POST /api/menu-demo)
 * contra una API de Anthropic falsa (no gasta lecturas reales):
 *   php -S localhost:8003 tests/apoyo/anthropic_falso.php
 *   PHP_CLI_SERVER_WORKERS=4 VECI_IA_PRUEBA_URL=http://localhost:8003/v1/messages php -S localhost:8002 serve.php
 *   php tests/menu_demo.php http://localhost:8002
 * Usa la configuración por defecto de demo_ia (2 por IP, 150 lecturas y
 * 1.500.000 tokens al día, 15 ítems, orígenes tuveci.co).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

use App\Database;

$base = $argv[1] ?? 'http://localhost:8002';
$pdo = Database::conexion();
$fallos = 0;
$ok = static function (bool $condicion, string $texto) use (&$fallos): void {
    echo ($condicion ? '✓ ' : '✗ ') . $texto . "\n";
    if (!$condicion) {
        $fallos++;
    }
};
$modo = static fn (string $m) => file_put_contents(sys_get_temp_dir() . '/veci-ia-falsa-modo', $m);
$limpiar = static function () use ($pdo): void {
    $pdo->exec("DELETE FROM limites_tasa WHERE accion LIKE 'demo_ia%'");
    $pdo->exec('DELETE FROM demo_ia_usos WHERE creado_en >= CURDATE()');
};
$sitio = 'https://tuveci.co';

/** @return array{codigo:int, cabeceras:string, json:?array} */
$pedir = static function (string $metodo, ?string $origen, array $campos = []) use ($base): array {
    $ch = curl_init($base . '/api/menu-demo');
    $cabeceras = $origen !== null ? ['Origin: ' . $origen] : [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => $cabeceras]);
    if ($metodo === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $campos);
    }
    $respuesta = (string) curl_exec($ch);
    $tam = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['codigo' => $codigo, 'cabeceras' => substr($respuesta, 0, $tam), 'json' => json_decode(substr($respuesta, $tam), true)];
};
$foto = static function (int $ancho, int $alto, string $formato = 'jpeg'): string {
    $ruta = tempnam(sys_get_temp_dir(), 'veci-carta');
    $img = imagecreatetruecolor($ancho, $alto);
    imagefill($img, 0, 0, 0xFFFFFF);
    imagestring($img, 5, 10, 10, 'Empanada 2500', 0);
    $formato === 'png' ? imagepng($img, $ruta) : imagejpeg($img, $ruta);

    return $ruta;
};
$carta = $foto(400, 300);

$limpiar();
$modo('ok');

echo "== CORS\n";
$pre = $pedir('OPTIONS', $sitio);
$ok($pre['codigo'] === 204 && str_contains($pre['cabeceras'], 'Access-Control-Allow-Origin: https://tuveci.co'), 'preflight desde tuveci.co: 204 con Allow-Origin');
$ok(str_contains($pre['cabeceras'], 'Vary: Origin'), 'Vary: Origin (las cachés no mezclan orígenes)');
$preMalo = $pedir('OPTIONS', 'https://evil.example');
$ok($preMalo['codigo'] === 403 && !str_contains($preMalo['cabeceras'], 'Access-Control-Allow-Origin'), 'preflight desde otro dominio: 403 sin Allow-Origin');
$sinOrigen = $pedir('POST', null, ['foto' => new CURLFile($carta, 'image/jpeg')]);
$ok($sinOrigen['codigo'] === 403 && ($sinOrigen['json']['estado'] ?? '') === 'origen_no_permitido', 'POST sin Origin: 403');
$otro = $pedir('POST', 'https://tuveci.co.evil.example', ['foto' => new CURLFile($carta, 'image/jpeg')]);
$ok($otro['codigo'] === 403, 'POST desde un dominio parecido: 403');
$ok((int) $pdo->query('SELECT COUNT(*) FROM demo_ia_usos WHERE creado_en >= CURDATE()')->fetchColumn() === 0, 'nada de eso llegó a la IA');

echo "== Validación de la foto\n";
$sinFoto = $pedir('POST', $sitio, ['tipo' => 'pedidos']);
$ok($sinFoto['codigo'] === 400 && ($sinFoto['json']['estado'] ?? '') === 'foto_invalida', 'sin foto: 400');
$texto = tempnam(sys_get_temp_dir(), 'veci-txt');
file_put_contents($texto, '<?php echo "hola";');
$falsa = $pedir('POST', $sitio, ['foto' => new CURLFile($texto, 'image/jpeg', 'carta.jpg')]);
$ok($falsa['codigo'] === 400, 'un archivo que no es imagen (aunque diga .jpg): 400');
$chiquita = $foto(40, 40);
$ok($pedir('POST', $sitio, ['foto' => new CURLFile($chiquita, 'image/jpeg')])['codigo'] === 400, 'imagen de 40×40: 400');
$grande = tempnam(sys_get_temp_dir(), 'veci-grande');
file_put_contents($grande, random_bytes(5 * 1024 * 1024));
$pesada = $pedir('POST', $sitio, ['foto' => new CURLFile($grande, 'image/jpeg')]);
$ok($pesada['codigo'] === 413 && ($pesada['json']['estado'] ?? '') === 'foto_grande', 'más de 4 MB: 413');
$ok((int) $pdo->query('SELECT COUNT(*) FROM demo_ia_usos WHERE creado_en >= CURDATE()')->fetchColumn() === 0, 'las fotos inválidas no gastan lecturas');

echo "== Lectura\n";
$limpiar();
$subidasAntes = count(glob(__DIR__ . '/../storage/uploads/*') ?: []);
$r = $pedir('POST', $sitio, ['foto' => new CURLFile($carta, 'image/jpeg'), 'tipo' => 'pedidos']);
$ok($r['codigo'] === 200 && ($r['json']['estado'] ?? '') === 'ok', 'lectura ok: 200');
$ok(count($r['json']['items'] ?? []) === 15, 'devuelve como máximo 15 ítems (la IA mandó 20)');
$ok(($r['json']['items'][0]['descripcion'] ?? '') === 'con ají', 'sin etiquetas HTML en los textos');
$ok(str_contains($r['cabeceras'], 'Access-Control-Allow-Origin: https://tuveci.co') && str_contains($r['cabeceras'], 'Cache-Control: no-store'), 'con Allow-Origin y sin caché');
$ok(stripos($r['cabeceras'], 'Set-Cookie') === false, 'no abre sesión ni deja cookie');
$enviado = json_decode((string) file_get_contents(sys_get_temp_dir() . '/veci-ia-falsa-ultima.json'), true);
$ok(($enviado['max_tokens'] ?? 0) === 4000 && str_contains((string) ($enviado['messages'][0]['content'][1]['text'] ?? ''), 'como máximo 15'), 'a la IA se le pide solo lo necesario (15 ítems, 4000 tokens)');
$uso = $pdo->query('SELECT * FROM demo_ia_usos WHERE creado_en >= CURDATE() ORDER BY id DESC LIMIT 1')->fetch();
$ok(is_array($uso) && $uso['estado'] === 'ok' && (int) $uso['tokens_entrada'] === 1500 && (int) $uso['tokens_salida'] === 800, 'la lectura queda con los tokens que gastó');
$ok(is_array($uso) && strlen((string) $uso['ip_hash']) === 64 && !str_contains((string) $uso['ip_hash'], '127.0.0.1'), 'la IP solo como huella');
$ok(count(glob(__DIR__ . '/../storage/uploads/*') ?: []) === $subidasAntes, 'la foto no se guarda');

$modo('servicios');
$s = $pedir('POST', $sitio, ['foto' => new CURLFile($foto(300, 400, 'png'), 'image/png'), 'tipo' => 'reservas']);
$ok(($s['json']['tipo'] ?? '') === 'reservas' && ($s['json']['items'][0]['duracion_min'] ?? 0) === 35, 'servicios (PNG): duración redondeada a 5 minutos');
$tercera = $pedir('POST', $sitio, ['foto' => new CURLFile($carta, 'image/jpeg')]);
$ok($tercera['codigo'] === 429 && ($tercera['json']['estado'] ?? '') === 'limite_ip', 'tercera foto del día desde la misma IP: 429');

echo "== Respuestas de la IA\n";
$limpiar();
$modo('vacio');
$v = $pedir('POST', $sitio, ['foto' => new CURLFile($carta, 'image/jpeg')]);
$ok($v['codigo'] === 200 && ($v['json']['estado'] ?? '') === 'vacio' && ($v['json']['items'] ?? null) === [], 'foto sin precios: vacio con lista vacía');
$modo('error');
$e = $pedir('POST', $sitio, ['foto' => new CURLFile($carta, 'image/jpeg')]);
$ok($e['codigo'] === 502 && ($e['json']['estado'] ?? '') === 'fallo', 'la API falla: 502 fallo');
$ok($pdo->query("SELECT estado FROM demo_ia_usos WHERE creado_en >= CURDATE() ORDER BY id DESC LIMIT 1")->fetchColumn() === 'fallo', 'el fallo queda registrado');

echo "== Topes del día\n";
$limpiar();
$modo('ok');
$pdo->exec("INSERT INTO demo_ia_usos (ip_hash, estado, tokens_entrada, tokens_salida) VALUES (REPEAT('a', 64), 'ok', 1499000, 1000)");
$t = $pedir('POST', $sitio, ['foto' => new CURLFile($carta, 'image/jpeg')]);
$ok($t['codigo'] === 503 && ($t['json']['estado'] ?? '') === 'tope_diario', 'tope de tokens del día alcanzado: 503');
$limpiar();
$llenar = $pdo->prepare("INSERT INTO demo_ia_usos (ip_hash, estado) VALUES (REPEAT('b', 64), 'ok')");
for ($i = 0; $i < 149; $i++) {
    $llenar->execute();
}
// Dos visitas a la vez por la última lectura del día: solo una pasa.
$multi = curl_multi_init();
$handles = [];
foreach ([1, 2] as $_) {
    $ch = curl_init($base . '/api/menu-demo');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Origin: ' . $sitio], CURLOPT_POSTFIELDS => ['foto' => new CURLFile($carta, 'image/jpeg')], CURLOPT_TIMEOUT => 30]);
    curl_multi_add_handle($multi, $ch);
    $handles[] = $ch;
}
do {
    curl_multi_exec($multi, $activos);
    curl_multi_select($multi);
} while ($activos > 0);
$codigos = [];
foreach ($handles as $ch) {
    $codigos[] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_multi_remove_handle($multi, $ch);
}
sort($codigos);
$ok($codigos === [200, 503], 'dos a la vez por la última lectura: una 200 y otra 503 (' . implode(', ', $codigos) . ')');
$ok((int) $pdo->query('SELECT COUNT(*) FROM demo_ia_usos WHERE creado_en >= CURDATE()')->fetchColumn() === 150, 'nunca más de 150 lecturas en el día');

echo "== Intentos\n";
$limpiar();
for ($i = 0; $i < 20; $i++) {
    $pedir('POST', $sitio, ['tipo' => 'pedidos']);
}
$m = $pedir('POST', $sitio, ['foto' => new CURLFile($carta, 'image/jpeg')]);
$ok($m['codigo'] === 429 && ($m['json']['estado'] ?? '') === 'muchos_intentos', 'intento 21 en una hora (aunque fallen): 429');

$limpiar();
foreach ([$carta, $texto, $chiquita, $grande] as $archivo) {
    @unlink($archivo);
}
echo $fallos === 0 ? "\nTodo bien.\n" : "\n{$fallos} fallo(s).\n";
exit($fallos === 0 ? 0 : 1);
