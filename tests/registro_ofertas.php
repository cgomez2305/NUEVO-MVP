<?php

declare(strict_types=1);

/**
 * Prueba de punta a punta del registro con parámetros del sitio y de los
 * códigos de oferta de los planes (PENDIENTES-APP.md, puntos 1 y 2).
 *
 * Crea negocios nuevos y pide planes de verdad: correr SOLO contra una base
 * de PRUEBA con el servidor local encendido.
 *   php tests/registro_ofertas.php http://localhost:8000
 * Sale con código 1 si alguna comprobación falla.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

use App\Database;
use App\Models\Admin;
use App\Totp;

$base = $argv[1] ?? 'http://localhost:8000';
$pdo = Database::conexion();
$fallos = 0;
$ok = static function (bool $condicion, string $texto) use (&$fallos): void {
    echo ($condicion ? '✓ ' : '✗ ') . $texto . "\n";
    if (!$condicion) {
        $fallos++;
    }
};
$valor = static fn (string $sql, array $p = []) => (function () use ($pdo, $sql, $p) {
    $st = $pdo->prepare($sql);
    $st->execute($p);

    return $st->fetchColumn();
})();
$limpiarTasas = static fn () => $pdo->exec("DELETE FROM limites_tasa");

// ---------- HTTP con un cookie jar por "navegador" ----------
$jars = [];
$pedir = static function (string $quien, string $metodo, string $ruta, array $datos = []) use ($base, &$jars): array {
    $jars[$quien] ??= tempnam(sys_get_temp_dir(), 'veci-ofertas');
    $ch = curl_init($base . $ruta);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jars[$quien], CURLOPT_COOKIEFILE => $jars[$quien], CURLOPT_TIMEOUT => 30,
    ]);
    if ($metodo === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
    }
    $respuesta = (string) curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tamCabecera = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    preg_match('/^Location:\s*(\S+)/mi', substr($respuesta, 0, $tamCabecera), $loc);

    return ['codigo' => $codigo, 'cuerpo' => substr($respuesta, $tamCabecera), 'location' => $loc[1] ?? ''];
};
$token = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$flash = static fn (string $html): string => preg_match('/class="pq-alerta[^"]*"[^>]*>([^<]+)/', $html, $m) ? trim(html_entity_decode($m[1])) : '';
$numero = static fn (): string => '31' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
$cedula = static fn (): string => (string) random_int(10000000, 1999999999);

/** Registra un negocio en el navegador $quien; devuelve [negocioId, location]. */
$registrar = static function (string $quien, string $query, string $tipo, string $whatsapp, string $password = 'una-frase-muy-segura-22') use ($pedir, $token, $valor, $limpiarTasas): array {
    $limpiarTasas();
    $form = $pedir($quien, 'GET', '/registro' . $query);
    $r = $pedir($quien, 'POST', '/registro', [
        '_csrf' => $token($form['cuerpo']), 'nombre' => 'Prueba Ofertas ' . substr($whatsapp, -4),
        'whatsapp' => $whatsapp, 'password' => $password, 'tipo_negocio' => $tipo,
    ]);
    $id = (int) $valor('SELECT negocio_id FROM usuarios WHERE whatsapp = :w', ['w' => $whatsapp]);

    return [$id, $r['location']];
};
/** Pide un plan desde /panel/plan; devuelve el flash. */
$pedirPlan = static function (string $quien, string $plan, string $ciclo) use ($pedir, $token, $flash, $valor): string {
    $pagina = $pedir($quien, 'GET', '/panel/plan');
    $planId = (int) $valor('SELECT id FROM planes WHERE nombre = :n', ['n' => $plan]);
    $pedir($quien, 'POST', '/panel/plan/solicitar', ['_csrf' => $token($pagina['cuerpo']), 'plan_id' => $planId, 'ciclo' => $ciclo]);

    return $flash($pedir($quien, 'GET', '/panel/plan')['cuerpo']);
};
$aplicar = static function (string $quien, string $codigo, string $documento) use ($pedir, $token, $flash): string {
    $pagina = $pedir($quien, 'GET', '/panel/plan');
    $pedir($quien, 'POST', '/panel/plan/oferta', ['_csrf' => $token($pagina['cuerpo']), 'codigo' => $codigo, 'documento' => $documento]);

    return $flash($pedir($quien, 'GET', '/panel/plan')['cuerpo']);
};
$cancelar = static function (string $quien) use ($pedir, $token): void {
    $pagina = $pedir($quien, 'GET', '/panel/plan');
    $pedir($quien, 'POST', '/panel/plan/cancelar', ['_csrf' => $token($pagina['cuerpo'])]);
};

// Oferta limpia para la prueba (no toca VECICHAT30).
$codigo = 'QAOFERTA' . random_int(100, 999);
$pdo->prepare('INSERT INTO ofertas_plan (codigo, descripcion, porcentaje, cupo_total) VALUES (:c, :d, 30, 300)')
    ->execute(['c' => $codigo, 'd' => 'Prueba automática']);
$ofertaId = (int) $pdo->lastInsertId();

echo "== 1. Registro con parámetros del sitio\n";
$w1 = $numero();
$query = '?' . http_build_query([
    'utm_source' => '<b>web</b>', 'utm_medium' => "chat\x01bot", 'utm_campaign' => str_repeat('x', 120),
    'utm_term' => 'término', 'utm_content' => 'precios-hero', 'plan' => 'pro', 'ciclo' => 'anual',
    'modo' => 'reservas', 'oferta' => strtolower($codigo),
]);
$form = $pedir('a', 'GET', '/registro' . $query);
$ok(str_contains($form['cuerpo'], 'value="reservas" checked'), 'modo=reservas deja marcada esa opción');
$ok(str_contains($form['cuerpo'], 'Elegiste el plan <strong>Pro</strong>'), 'el formulario dice que eligió Pro');
// Un error en el formulario no borra lo que llegó del sitio.
$limpiarTasas();
$pedir('a', 'POST', '/registro', ['_csrf' => $token($form['cuerpo']), 'nombre' => 'X', 'whatsapp' => $w1, 'password' => '12345678', 'tipo_negocio' => 'reservas']);
$deNuevo = $pedir('a', 'GET', '/registro');
$ok(str_contains($deNuevo['cuerpo'], 'value="reservas" checked'), 'tras un error, el modo sigue marcado');
[$n1, $loc1] = $registrar('a', '', 'reservas', $w1);
$ok($n1 > 0, 'negocio creado');
$ok(str_contains($loc1, '/panel/plan?plan=pro&ciclo=anual'), 'con plan pago va a /panel/plan con plan y ciclo: ' . $loc1);
$origen = $pdo->query("SELECT * FROM negocio_origen WHERE negocio_id = {$n1}")->fetch();
$ok(is_array($origen), 'origen guardado con el negocio');
$ok(($origen['utm_source'] ?? '') === 'web' && ($origen['utm_medium'] ?? '') === 'chatbot', 'utm como texto plano (sin etiquetas ni caracteres de control)');
$ok(mb_strlen((string) ($origen['utm_campaign'] ?? '')) === 80, 'utm recortado a 80 caracteres');
$ok(($origen['utm_term'] ?? '') === 'término' && ($origen['utm_content'] ?? '') === 'precios-hero', 'utm_term y utm_content guardados');
$ok(($origen['plan_interes'] ?? '') === 'pro' && ($origen['ciclo_interes'] ?? '') === 'anual' && ($origen['modo_interes'] ?? '') === 'reservas', 'plan, ciclo y modo guardados');
$ok(($origen['oferta_codigo'] ?? '') === $codigo, 'código de oferta guardado (normalizado)');
$ok((string) $valor('SELECT plan_id FROM negocios WHERE id = :n', ['n' => $n1]) === '1', 'nace en Gratis: nada se cobra solo');
$ok((int) $valor("SELECT COUNT(*) FROM pagos_plan WHERE negocio_id = :n", ['n' => $n1]) === 0, 'sin pagos creados');
$ok((int) $valor("SELECT COUNT(*) FROM identidades_negocio WHERE negocio_id = :n AND tipo = 'whatsapp'", ['n' => $n1]) === 1, 'el WhatsApp queda como identidad (hash)');
$ok((int) $valor("SELECT COUNT(*) FROM identidades_negocio WHERE hash = :w", ['w' => $w1]) === 0, 'el número no se guarda en claro');
$plan = $pedir('a', 'GET', '/panel/plan?plan=pro&ciclo=anual');
$ok(str_contains($plan['cuerpo'], 'pq-plan-card-elegido" id="plan-pro"'), 'la tarjeta Pro sale marcada');
$ok((bool) preg_match('/id="plan-pro".*?value="anual" checked/s', $plan['cuerpo']), 'el ciclo anual sale marcado');
$ok(str_contains($plan['cuerpo'], 'value="' . $codigo . '"'), 'el código que trajo del sitio viene escrito');

$wMalo = $numero();
[$nMalo] = $registrar('m', '?' . http_build_query(['plan' => 'premium', 'modo' => 'hack', 'ciclo' => 'diario', 'oferta' => '<x>', 'utm_source' => '']), 'pedidos', $wMalo);
$ok(!is_array($pdo->query("SELECT * FROM negocio_origen WHERE negocio_id = {$nMalo}")->fetch()), 'valores fuera de la lista blanca se ignoran (no queda origen)');

echo "== 2. Código de oferta\n";
$doc1 = $cedula();
$ok(str_contains($aplicar('a', $codigo, '12'), 'cédula o el NIT'), 'documento inválido: rechazado');
$ok(str_contains($aplicar('a', 'NOEXISTE1', $doc1), 'no existe'), 'código inexistente: rechazado');
$limpiarTasas();
$ok(str_contains($aplicar('a', strtolower($codigo), $doc1), 'aplicado'), 'código válido aplicado (minúsculas también)');
$plan = $pedir('a', 'GET', '/panel/plan');
$ok(str_contains($plan['cuerpo'], '$20.930'), 'Barrio mensual muestra $20.930 el primer mes');
$ok(str_contains($pedirPlan('a', 'barrio', 'mensual'), '$20.930'), 'la solicitud pide transferir $20.930');
$pago = $pdo->query("SELECT * FROM pagos_plan WHERE negocio_id = {$n1} ORDER BY id DESC LIMIT 1")->fetch();
$ok((int) $pago['monto'] === 20930 && (int) $pago['monto_lista'] === 29900 && (int) $pago['descuento'] === 8970 && $pago['oferta_codigo'] === $codigo, 'pago: monto con descuento, lista y código guardados');
$ok($valor("SELECT estado FROM ofertas_canjes WHERE pago_plan_id = :p", ['p' => $pago['id']]) === 'apartado', 'canje apartado y ligado al pago');
$ok((int) $valor("SELECT COUNT(*) FROM identidades_negocio WHERE negocio_id = :n AND tipo = 'documento'", ['n' => $n1]) === 1, 'el documento queda como identidad (hash)');
$cancelar('a');
$ok($valor("SELECT estado FROM ofertas_canjes WHERE pago_plan_id = :p", ['p' => $pago['id']]) === 'liberado', 'cancelar la solicitud libera el canje (el cupo vuelve)');

// Anual: el código no se acumula.
$limpiarTasas();
$aplicar('a', $codigo, $doc1);
$ok(str_contains($pedirPlan('a', 'barrio', 'anual'), 'no aplica al pago anual'), 'anual: avisa que va sin descuento');
$anual = $pdo->query("SELECT * FROM pagos_plan WHERE negocio_id = {$n1} ORDER BY id DESC LIMIT 1")->fetch();
$ok((int) $anual['descuento'] === 0 && (int) $anual['monto'] === 299000, 'anual: sin descuento ($299.000)');
$cancelar('a');

// Pro mensual con el código, y el admin lo confirma.
$limpiarTasas();
$aplicar('a', $codigo, $doc1);
$pedirPlan('a', 'pro', 'mensual');
$pago = $pdo->query("SELECT * FROM pagos_plan WHERE negocio_id = {$n1} ORDER BY id DESC LIMIT 1")->fetch();
$ok((int) $pago['monto'] === 48930 && (int) $pago['descuento'] === 20970, 'Pro mensual: $69.900 − 30% = $48.930');

// Admin con segundo factor.
$correoAdmin = 'qa-ofertas-' . random_int(1000, 9999) . '@veci.test';
$claveAdmin = 'clave-admin-ofertas-' . random_int(1000, 9999);
$adminId = Admin::crear('QA Ofertas', $correoAdmin, $claveAdmin);
$secreto = Totp::nuevoSecreto();
Admin::guardarTotp($adminId, $secreto);
$limpiarTasas();
$login = $pedir('adm', 'GET', '/admin/login');
$pedir('adm', 'POST', '/admin/login', ['_csrf' => $token($login['cuerpo']), 'correo' => $correoAdmin, 'password' => $claveAdmin, 'codigo' => Totp::codigo($secreto, Totp::pasoActual())]);
$panel = $pedir('adm', 'GET', '/admin/negocios/' . $n1);
$ok(str_contains($panel['cuerpo'], '$48.930') && str_contains($panel['cuerpo'], $codigo), 'el admin ve el monto esperado ya con el descuento y el código');
$pedir('adm', 'POST', '/admin/pagos/' . $pago['id'] . '/confirmar', ['_csrf' => $token($panel['cuerpo']), 'monto_recibido' => '48.930']);
$ok($valor('SELECT confirmado_en IS NOT NULL FROM pagos_plan WHERE id = :p', ['p' => $pago['id']]) == 1, 'el admin confirma $48.930');
$ok($valor("SELECT estado FROM ofertas_canjes WHERE pago_plan_id = :p", ['p' => $pago['id']]) === 'confirmado', 'canje confirmado');
$limpiarTasas();
$ok(str_contains($aplicar('a', $codigo, $doc1), 'ya pagó un plan'), 'un negocio que ya pagó no puede volver a usarlo');

// Otro negocio con el mismo documento: no.
$w2 = $numero();
[$n2] = $registrar('b', '', 'pedidos', $w2);
$ok(str_contains($aplicar('b', $codigo, $doc1 . '-7'), 'solo para negocios nuevos'), 'mismo documento (con dígito de verificación) en otro negocio: rechazado');
// Un número que ya tuvo un negocio (borrado, por ejemplo): no.
$w3 = $numero();
[$n3] = $registrar('c', '', 'pedidos', $w3);
$pdo->prepare("INSERT INTO identidades_negocio (tipo, hash, negocio_id) VALUES ('whatsapp', :h, 999999)")->execute(['h' => hash_identidad('whatsapp', $w3)]);
$limpiarTasas();
$ok(str_contains($aplicar('c', $codigo, $cedula()), 'solo para negocios nuevos'), 'WhatsApp que ya tuvo otro negocio: rechazado');

// Vencida, pausada y agotada.
$w4 = $numero();
[$n4] = $registrar('d', '', 'pedidos', $w4);
$doc4 = $cedula();
$pdo->exec("UPDATE ofertas_plan SET vence_en = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id = {$ofertaId}");
$limpiarTasas();
$ok(str_contains($aplicar('d', $codigo, $doc4), 'venció'), 'oferta vencida: rechazada');
$pdo->exec("UPDATE ofertas_plan SET vence_en = NULL, activa = 0 WHERE id = {$ofertaId}");
$ok(str_contains($aplicar('d', $codigo, $doc4), 'ya no está disponible'), 'oferta pausada: rechazada');
$usos = (int) $valor("SELECT COUNT(*) FROM ofertas_canjes WHERE oferta_id = :o AND estado <> 'liberado'", ['o' => $ofertaId]);
$pdo->exec("UPDATE ofertas_plan SET activa = 1, cupo_total = {$usos} WHERE id = {$ofertaId}");
$limpiarTasas();
$ok(str_contains($aplicar('d', $codigo, $doc4), 'agotó'), 'oferta sin cupo: rechazada');

// Límite: 5 intentos por hora.
$pdo->exec("UPDATE ofertas_plan SET cupo_total = 300 WHERE id = {$ofertaId}");
$limpiarTasas();
for ($i = 0; $i < 5; $i++) {
    $aplicar('d', 'MAL' . $i . 'XX', $doc4);
}
$ok(str_contains($aplicar('d', $codigo, $doc4), 'muchos intentos'), 'sexto intento en una hora: frenado');

// Cupo: dos negocios piden el último cupo a la vez → solo uno lo aparta.
$limpiarTasas();
$aplicar('d', $codigo, $doc4);
$w5 = $numero();
[$n5] = $registrar('e', '', 'pedidos', $w5);
$limpiarTasas();
$aplicar('e', $codigo, $cedula());
$usos = (int) $valor("SELECT COUNT(*) FROM ofertas_canjes WHERE oferta_id = :o AND estado <> 'liberado'", ['o' => $ofertaId]);
$pdo->exec("UPDATE ofertas_plan SET cupo_total = " . ($usos + 1) . " WHERE id = {$ofertaId}");
$multi = curl_multi_init();
$handles = [];
foreach (['d', 'e'] as $quien) {
    $pagina = $pedir($quien, 'GET', '/panel/plan');
    $ch = curl_init($base . '/panel/plan/solicitar');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_COOKIEFILE => $jars[$quien], CURLOPT_COOKIEJAR => $jars[$quien],
        CURLOPT_POSTFIELDS => http_build_query(['_csrf' => $token($pagina['cuerpo']), 'plan_id' => 2, 'ciclo' => 'mensual']),
    ]);
    curl_multi_add_handle($multi, $ch);
    $handles[] = $ch;
}
do {
    curl_multi_exec($multi, $activos);
    curl_multi_select($multi);
} while ($activos > 0);
foreach ($handles as $ch) {
    curl_multi_remove_handle($multi, $ch);
}
$apartados = (int) $valor("SELECT COUNT(*) FROM ofertas_canjes WHERE oferta_id = :o AND negocio_id IN ({$n4}, {$n5}) AND estado = 'apartado'", ['o' => $ofertaId]);
$ok($apartados === 1, 'dos pedidos a la vez por el último cupo: solo uno lo aparta (' . $apartados . ')');

echo "== Panel interno de ofertas\n";
$ofertas = $pedir('adm', 'GET', '/admin/ofertas');
$ok($ofertas['codigo'] === 200 && str_contains($ofertas['cuerpo'], $codigo), '/admin/ofertas lista la oferta');
$ok(str_contains($ofertas['cuerpo'], 'Pagado') && str_contains($ofertas['cuerpo'], 'Todavía no'), 'registro de canjes: estado y si pagó el segundo mes');
$pedir('adm', 'POST', '/admin/ofertas/' . $ofertaId, ['_csrf' => $token($ofertas['cuerpo']), 'descripcion' => 'Editada', 'porcentaje' => 25, 'vence_en' => '2030-12-31', 'cupo_total' => 50, 'activa' => 1]);
$editada = $pdo->query("SELECT porcentaje, vence_en, cupo_total, descripcion FROM ofertas_plan WHERE id = {$ofertaId}")->fetch();
$ok((int) $editada['porcentaje'] === 25 && $editada['vence_en'] === '2030-12-31' && (int) $editada['cupo_total'] === 50, 'el admin cambia porcentaje, fecha de fin y cupo');
$sinSesion = $pedir('anon', 'GET', '/admin/ofertas');
$ok($sinSesion['codigo'] === 302 && str_contains($sinSesion['location'], '/admin/login'), 'sin sesión de admin no se ve');

echo "== Panel interno de campañas\n";
$filaWeb = null;
foreach (\App\Models\OrigenRegistro::reporte(7) as $fila) {
    if ($fila['fuente'] === 'web' && $fila['medio'] === 'chatbot') {
        $filaWeb = $fila;
    }
}
$ok($filaWeb !== null && $filaWeb['registros'] >= 1 && $filaWeb['pagaron'] >= 1 && $filaWeb['con_oferta'] >= 1 && $filaWeb['interes_pro'] >= 1, 'la campaña web/chatbot cuenta el registro, el pago, la oferta y el plan que miraba');
$sinCampana = array_values(array_filter(\App\Models\OrigenRegistro::reporte(7), fn ($f) => $f['fuente'] === null && $f['medio'] === null && $f['campana'] === null));
$ok($sinCampana !== [] && $sinCampana[0]['registros'] >= 4, 'los registros sin utm salen juntos como "sin campaña"');
$campanas = $pedir('adm', 'GET', '/admin/origenes?periodo=7');
$ok(str_contains($campanas['cuerpo'], 'chatbot') && str_contains($campanas['cuerpo'], 'Sin campaña') && str_contains($campanas['cuerpo'], 'pq-embudo'), '/admin/origenes muestra el embudo y las campañas');
$ok(str_contains($pedir('adm', 'GET', '/admin/origenes?periodo=<x>')['cuerpo'], 'href="' . base_url('/admin/origenes') . '?periodo=30" class="pq-segmento pq-segmento-activo"'), 'un período inválido cae en 30 días');
$sinSesion = $pedir('anon', 'GET', '/admin/origenes');
$ok($sinSesion['codigo'] === 302 && str_contains($sinSesion['location'], '/admin/login'), 'campañas sin sesión de admin no se ve');

foreach ($jars as $jar) {
    @unlink($jar);
}
echo $fallos === 0 ? "\nTodo bien.\n" : "\n{$fallos} fallo(s).\n";
exit($fallos === 0 ? 0 : 1);
