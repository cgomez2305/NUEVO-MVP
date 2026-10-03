<?php

declare(strict_types=1);

/**
 * Prueba de las fachadas de la tienda (barrio, consultorio, despacho):
 * el registro elige la fachada según la línea, la tienda pública cambia
 * de cabecera y vocabulario, y el dueño la cambia desde "Editar sede".
 *   php tests/fachadas.php http://localhost:8000
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

use App\Database;

$base = $argv[1] ?? 'http://localhost:8000';
$pdo = Database::conexion();
$fallos = 0;
$ok = static function (bool $condicion, string $texto) use (&$fallos): void {
    echo ($condicion ? '✓ ' : '✗ ') . $texto . "\n";
    if (!$condicion) {
        $fallos++;
    }
};
$valor = static function (string $sql, array $p = []) use ($pdo) {
    $st = $pdo->prepare($sql);
    $st->execute($p);

    return $st->fetchColumn();
};
$jars = [];
$pedir = static function (string $quien, string $metodo, string $ruta, array $datos = []) use ($base, &$jars): string {
    $jars[$quien] ??= tempnam(sys_get_temp_dir(), 'veci-fachadas');
    $ch = curl_init($base . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jars[$quien], CURLOPT_COOKIEFILE => $jars[$quien], CURLOPT_TIMEOUT => 30]);
    if ($metodo === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
    }
    $cuerpo = (string) curl_exec($ch);
    curl_close($ch);

    return $cuerpo;
};
$token = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$numero = static fn (): string => '31' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
$registrar = static function (string $quien, string $tipo) use ($pedir, $token, $numero, $valor, $pdo): array {
    $pdo->exec('DELETE FROM limites_tasa');
    $whatsapp = $numero();
    $form = $pedir($quien, 'GET', '/registro');
    $pedir($quien, 'POST', '/registro', ['_csrf' => $token($form), 'nombre' => 'Prueba Fachada ' . $tipo, 'whatsapp' => $whatsapp, 'password' => 'una-frase-muy-segura-22', 'tipo_negocio' => $tipo]);
    $negocioId = (int) $valor('SELECT negocio_id FROM usuarios WHERE whatsapp = :w', ['w' => $whatsapp]);

    return [$negocioId, (int) $valor('SELECT id FROM sedes WHERE negocio_id = :n', ['n' => $negocioId])];
};
/** Guarda "Editar sede" con los datos actuales más $cambios. */
$editarSede = static function (string $quien, int $sedeId, array $cambios) use ($pedir, $token, $valor): void {
    $form = $pedir($quien, 'GET', '/panel/sedes/' . $sedeId . '/editar');
    $pedir($quien, 'POST', '/panel/sedes/' . $sedeId . '/actualizar', $cambios + [
        '_csrf' => $token($form),
        'nombre' => (string) $valor('SELECT nombre FROM sedes WHERE id = :s', ['s' => $sedeId]),
        'whatsapp' => (string) $valor('SELECT whatsapp FROM sedes WHERE id = :s', ['s' => $sedeId]),
    ]);
};

echo "== Registro\n";
$form = $pedir('x', 'GET', '/registro');
$ok(str_contains($form, 'value="profesional"') && str_contains($form, 'Servicios profesionales'), 'el registro ofrece "Servicios profesionales"');
[$nProf, $sProf] = $registrar('prof', 'profesional');
$ok($valor('SELECT CONCAT(tipo_negocio, "/", fachada) FROM negocios WHERE id = :n', ['n' => $nProf]) === 'reservas/despacho', 'profesional: agenda con fachada de despacho');
[$nSalud] = $registrar('salud', 'salud');
$ok($valor('SELECT CONCAT(tipo_negocio, "/", rubro, "/", fachada) FROM negocios WHERE id = :n', ['n' => $nSalud]) === 'reservas/salud/consultorio', 'salud: consultorio');
[$nReservas] = $registrar('res', 'reservas');
$ok($valor('SELECT fachada FROM negocios WHERE id = :n', ['n' => $nReservas]) === 'barrio', 'servicios con cita: barrio, como siempre');
[$nPedidos, $sPedidos] = $registrar('ped', 'pedidos');
$ok($valor('SELECT fachada FROM negocios WHERE id = :n', ['n' => $nPedidos]) === 'barrio', 'productos: barrio');

echo "== Panel: Editar sede\n";
$pdo->prepare('UPDATE sedes SET publicada = 1 WHERE id = :s')->execute(['s' => $sProf]);
$slugProf = (string) $valor('SELECT slug FROM sedes WHERE id = :s', ['s' => $sProf]);
$editar = $pedir('prof', 'GET', '/panel/sedes/' . $sProf . '/editar');
$ok(str_contains($editar, 'name="fachada" value="despacho" checked'), 'el editor muestra la fachada actual');
$editarSede('prof', $sProf, [
    'fachada' => 'despacho',
    'credencial' => '  T.P. 99.999   <b>del C.S.</b> ' . str_repeat('x', 200),
    'presentacion' => "Primer párrafo <script>alert(1)</script>\r\n\r\n\r\n\r\nSegundo párrafo.",
]);
$guardado = $pdo->query("SELECT credencial, presentacion FROM negocios WHERE id = {$nProf}")->fetch();
$ok(mb_strlen((string) $guardado['credencial']) === 140 && str_starts_with((string) $guardado['credencial'], 'T.P. 99.999 <b>'), 'credencial: espacios normalizados y recortada a 140');
$ok(!str_contains((string) $guardado['presentacion'], "\r") && !str_contains((string) $guardado['presentacion'], "\n\n\n"), 'presentación: sin \r y sin saltos de más');

echo "== Tienda pública\n";
$tienda = $pedir('cliente', 'GET', '/t/' . $slugProf);
$ok(str_contains($tienda, 'pq-estilo-despacho') && str_contains($tienda, 'pq-membrete') && !str_contains($tienda, 'class="pq-toldo"'), 'despacho: membrete en vez de toldo');
$ok(str_contains($tienda, 'Quiénes somos') && substr_count($tienda, '<p>Primer párrafo') === 1 && str_contains($tienda, '<p>Segundo párrafo.</p>'), 'presentación en párrafos');
$ok(!str_contains($tienda, '<script>alert(1)') && str_contains($tienda, '&lt;script&gt;') && str_contains($tienda, '&lt;b&gt;del C.S.'), 'credencial y presentación escapadas');
$bonita = $pedir('cliente', 'GET', '/t/salonbonita');
$ok(str_contains($bonita, 'pq-estilo-barrio') && str_contains($bonita, 'class="pq-toldo"') && str_contains($bonita, 'Reserva tu turno'), 'barrio sigue igual (toldo, "Reserva tu turno")');
$dental = $pedir('cliente', 'GET', '/t/sonrisadental');
$ok(str_contains($dental, 'pq-estilo-consultorio') && str_contains($dental, 'Agenda tu cita') && str_contains($dental, 'Profesionales'), 'consultorio: "Agenda tu cita" y "Profesionales"');
$servicioDental = (int) $valor("SELECT sv.id FROM servicios sv JOIN sedes s ON s.id = sv.sede_id WHERE s.slug = 'sonrisadental' AND sv.activo = 1 ORDER BY sv.id LIMIT 1");
$reservar = $pedir('cliente', 'GET', '/t/sonrisadental/reservar/' . $servicioDental);
$ok(str_contains($reservar, 'pq-estilo-consultorio') && str_contains($reservar, 'pq-membrete-filete-corto') && !str_contains($reservar, 'pq-toldo-corto'), 'las pantallas internas también llevan la fachada');
$ok(str_contains($reservar, '>P</span>'), 'la inicial de "Dra. Paula Méndez" es P, no D');

echo "== Reglas\n";
$editarSede('ped', $sPedidos, ['fachada' => 'despacho', 'credencial' => 'X']);
$ok($valor('SELECT fachada FROM negocios WHERE id = :n', ['n' => $nPedidos]) === 'barrio', 'un negocio de pedidos no puede pasar a despacho (ni enviando el campo a mano)');
$editarSede('prof', $sProf, ['fachada' => 'inventada']);
$ok($valor('SELECT fachada FROM negocios WHERE id = :n', ['n' => $nProf]) === 'barrio', 'una fachada que no existe cae en barrio');
// Otra cuenta no puede cambiar la sede de este negocio.
$editarSede('salud', $sProf, ['fachada' => 'consultorio', 'credencial' => 'intruso']);
$ok($valor('SELECT credencial FROM negocios WHERE id = :n', ['n' => $nProf]) !== 'intruso', 'otra cuenta no puede editar la presentación ajena');

foreach ($jars as $jar) {
    @unlink($jar);
}
echo $fallos === 0 ? "\nTodo bien.\n" : "\n{$fallos} fallo(s).\n";
exit($fallos === 0 ? 0 : 1);
