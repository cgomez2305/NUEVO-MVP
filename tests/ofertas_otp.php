<?php

declare(strict_types=1);

/**
 * Prueba de punta a punta del código por WhatsApp (OTP) que piden las
 * ofertas de plan cuando la API de WhatsApp está configurada.
 *
 * Necesita un servidor con el transporte de prueba, que escribe
 * "telefono codigo" en un archivo en vez de llamar a Meta:
 *   VECI_OTP_ARCHIVO=/tmp/otp.txt php -S localhost:8001 serve.php
 *   php tests/ofertas_otp.php http://localhost:8001 /tmp/otp.txt
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

use App\Database;

$base = $argv[1] ?? 'http://localhost:8001';
$archivoOtp = $argv[2] ?? '';
if ($archivoOtp === '' || !is_file($archivoOtp)) {
    fwrite(STDERR, "Falta el archivo de VECI_OTP_ARCHIVO del servidor (segundo argumento).\n");
    exit(2);
}
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

$jars = [];
$pedir = static function (string $quien, string $metodo, string $ruta, array $datos = []) use ($base, &$jars): array {
    $jars[$quien] ??= tempnam(sys_get_temp_dir(), 'veci-otp');
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
    $tamCabecera = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['cuerpo' => substr($respuesta, $tamCabecera)];
};
$token = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$flash = static fn (string $html): string => preg_match('/class="pq-alerta[^"]*"[^>]*>([^<]+)/', $html, $m) ? trim(html_entity_decode($m[1])) : '';
$numero = static fn (): string => '31' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
$cedula = static fn (): string => (string) random_int(10000000, 1999999999);

$registrar = static function (string $quien, string $whatsapp) use ($pedir, $token, $valor, $limpiarTasas): int {
    $limpiarTasas();
    $form = $pedir($quien, 'GET', '/registro');
    $pedir($quien, 'POST', '/registro', [
        '_csrf' => $token($form['cuerpo']), 'nombre' => 'Prueba OTP ' . substr($whatsapp, -4),
        'whatsapp' => $whatsapp, 'password' => 'una-frase-muy-segura-22', 'tipo_negocio' => 'pedidos',
    ]);

    return (int) $valor('SELECT negocio_id FROM usuarios WHERE whatsapp = :w', ['w' => $whatsapp]);
};
/** POST desde /panel/plan; devuelve [flash, html de la página después]. */
$postPlan = static function (string $quien, string $ruta, array $datos = []) use ($pedir, $token, $flash): array {
    $pagina = $pedir($quien, 'GET', '/panel/plan');
    $pedir($quien, 'POST', $ruta, ['_csrf' => $token($pagina['cuerpo'])] + $datos);
    $despues = $pedir($quien, 'GET', '/panel/plan')['cuerpo'];

    return [$flash($despues), $despues];
};
/** Último código que "llegó" al WhatsApp $telefono. */
$ultimoCodigo = static function (string $telefono) use ($archivoOtp): string {
    $codigo = '';
    foreach (file($archivoOtp, FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
        [$tel, $cod] = array_pad(explode(' ', $linea), 2, '');
        if ($tel === $telefono) {
            $codigo = $cod;
        }
    }

    return $codigo;
};
$otroCodigo = static fn (string $codigo): string => str_pad((string) (((int) $codigo + 1) % 1000000), 6, '0', STR_PAD_LEFT);

$codigo = 'QAOTP' . random_int(100, 999);
$pdo->prepare('INSERT INTO ofertas_plan (codigo, descripcion, porcentaje, cupo_total) VALUES (:c, :d, 30, 300)')
    ->execute(['c' => $codigo, 'd' => 'Prueba OTP']);
$ofertaId = (int) $pdo->lastInsertId();

echo "== 1. Aplicar la oferta pide el código\n";
$w1 = $numero();
$n1 = $registrar('a', $w1);
$u1 = (int) $valor('SELECT id FROM usuarios WHERE whatsapp = :w', ['w' => $w1]);
$doc1 = $cedula();
[$msg, $html] = $postPlan('a', '/panel/plan/oferta', ['codigo' => $codigo, 'documento' => $doc1]);
$ok(str_contains($msg, 'Te enviamos un código'), 'avisa que mandó el código: ' . $msg);
$ok(str_contains($html, 'Confirma tu WhatsApp') && str_contains($html, '••• ' . substr($w1, -4)), 'pide el código y muestra solo los últimos 4 dígitos');
$ok(!str_contains($html, 'pq-oferta-aplicada'), 'la oferta todavía no queda aplicada');
$c1 = $ultimoCodigo($w1);
$ok((bool) preg_match('/^\d{6}$/', $c1), 'el código llegó al WhatsApp de la cuenta');
$fila = $pdo->query("SELECT * FROM verificaciones_whatsapp WHERE usuario_id = {$u1} ORDER BY id DESC LIMIT 1")->fetch();
$ok(is_array($fila) && $fila['codigo_hash'] !== $c1 && !str_contains((string) $fila['codigo_hash'], $c1), 'en la base solo queda la huella del código');
$ok(strtotime((string) $fila['expira_en']) - time() <= 600 && strtotime((string) $fila['expira_en']) - time() > 500, 'vence en 10 minutos');

[$msg] = $postPlan('a', '/panel/plan/oferta/verificar', ['codigo_whatsapp' => $otroCodigo($c1)]);
$ok(str_contains($msg, 'no es el código'), 'código equivocado: rechazado');
$ok((int) $valor('SELECT intentos FROM verificaciones_whatsapp WHERE id = :i', ['i' => $fila['id']]) === 1, 'el intento fallido cuenta');
[$msg, $html] = $postPlan('a', '/panel/plan/oferta/verificar', ['codigo_whatsapp' => substr($c1, 0, 3) . ' ' . substr($c1, 3)]);
$ok(str_contains($msg, 'aplicado'), 'código correcto (con espacio en medio): oferta aplicada');
$ok(str_contains($html, 'pq-oferta-aplicada'), 'la página muestra la oferta aplicada');
[$msg] = $postPlan('a', '/panel/plan/oferta/verificar', ['codigo_whatsapp' => $c1]);
$ok(!str_contains($msg, 'aplicado'), 'el mismo código no se puede volver a usar');

// Quitar y volver a aplicar dentro de los 30 minutos: no pide otro código.
$postPlan('a', '/panel/plan/oferta/quitar');
$limpiarTasas();
$enviosAntes = count(file($archivoOtp) ?: []);
[$msg] = $postPlan('a', '/panel/plan/oferta', ['codigo' => $codigo, 'documento' => $doc1]);
$ok(str_contains($msg, 'aplicado') && count(file($archivoOtp) ?: []) === $enviosAntes, 'ya verificado en esta sesión: aplica sin otro código');
$postPlan('a', '/panel/plan/solicitar', ['plan_id' => (int) $valor("SELECT id FROM planes WHERE nombre = 'barrio'"), 'ciclo' => 'mensual']);
$pago = $pdo->query("SELECT * FROM pagos_plan WHERE negocio_id = {$n1} ORDER BY id DESC LIMIT 1")->fetch();
$ok(is_array($pago) && (int) $pago['monto'] === 20930 && $pago['oferta_codigo'] === $codigo, 'el pedido de plan sale con el descuento ($20.930)');

echo "== 2. Intentos, vencimiento y reenvíos\n";
$w2 = $numero();
$registrar('b', $w2);
$u2 = (int) $valor('SELECT id FROM usuarios WHERE whatsapp = :w', ['w' => $w2]);
$postPlan('b', '/panel/plan/oferta', ['codigo' => $codigo, 'documento' => $cedula()]);
$c2 = $ultimoCodigo($w2);
for ($i = 0; $i < 5; $i++) {
    $postPlan('b', '/panel/plan/oferta/verificar', ['codigo_whatsapp' => $otroCodigo($c2)]);
}
[$msg] = $postPlan('b', '/panel/plan/oferta/verificar', ['codigo_whatsapp' => $c2]);
$ok(str_contains($msg, 'Pide uno nuevo'), 'tras 5 intentos, ni el código correcto sirve: ' . $msg);

[$msg] = $postPlan('b', '/panel/plan/oferta/codigo');
$c2b = $ultimoCodigo($w2);
$ok(str_contains($msg, 'Te enviamos') && $c2b !== '', 'reenviar manda un código nuevo');
$pdo->exec("UPDATE verificaciones_whatsapp SET expira_en = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE usuario_id = {$u2} AND verificado_en IS NULL");
[$msg] = $postPlan('b', '/panel/plan/oferta/verificar', ['codigo_whatsapp' => $c2b]);
$ok(str_contains($msg, 'venció'), 'código vencido: rechazado');

[$msg] = $postPlan('b', '/panel/plan/oferta/codigo');
$c2c = $ultimoCodigo($w2);
[$msg] = $postPlan('b', '/panel/plan/oferta/codigo');
$ok(str_contains($msg, 'varios códigos'), 'cuarto código en la hora: frenado');
$ok($ultimoCodigo($w2) === $c2c, 'el frenado no manda nada');
[$msg] = $postPlan('b', '/panel/plan/oferta/verificar', ['codigo_whatsapp' => $c2b]);
$ok(!str_contains($msg, 'aplicado'), 'un código anterior queda anulado por el nuevo');

// Mientras llegaba el código se pausó la oferta: no se aplica.
$pdo->exec("UPDATE ofertas_plan SET activa = 0 WHERE id = {$ofertaId}");
[$msg, $html] = $postPlan('b', '/panel/plan/oferta/verificar', ['codigo_whatsapp' => $c2c]);
$ok(str_contains($msg, 'ya no está disponible') && !str_contains($html, 'pq-oferta-aplicada'), 'oferta pausada mientras tanto: se vuelve a evaluar y no se aplica');
$pdo->exec("UPDATE ofertas_plan SET activa = 1 WHERE id = {$ofertaId}");

echo "== 3. Cancelar y sesiones ajenas\n";
$w3 = $numero();
$registrar('c', $w3);
$limpiarTasas();
$postPlan('c', '/panel/plan/oferta', ['codigo' => $codigo, 'documento' => $cedula()]);
[, $html] = $postPlan('c', '/panel/plan/oferta/quitar');
$ok(!str_contains($html, 'Confirma tu WhatsApp') && str_contains($html, '¿Tienes un código de oferta?'), 'cancelar vuelve al formulario');
[$msg] = $postPlan('c', '/panel/plan/oferta/verificar', ['codigo_whatsapp' => $ultimoCodigo($w3)]);
$ok(!str_contains($msg, 'aplicado'), 'sin oferta en espera, verificar no aplica nada');
// El código de una cuenta no sirve en otra.
$limpiarTasas();
$postPlan('c', '/panel/plan/oferta', ['codigo' => $codigo, 'documento' => $cedula()]);
[$msg] = $postPlan('c', '/panel/plan/oferta/verificar', ['codigo_whatsapp' => $ultimoCodigo($w2)]);
$ok(!str_contains($msg, 'aplicado'), 'el código de otra cuenta no sirve');

$pdo->exec("UPDATE ofertas_plan SET activa = 0 WHERE id = {$ofertaId}");
foreach ($jars as $jar) {
    @unlink($jar);
}
echo $fallos === 0 ? "\nTodo bien.\n" : "\n{$fallos} fallo(s).\n";
exit($fallos === 0 ? 0 : 1);
