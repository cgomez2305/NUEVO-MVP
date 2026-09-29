<?php

declare(strict_types=1);

/**
 * Prueba local del webhook de Bre-B: arma el cuerpo y la firma HMAC igual
 * que las mandaría un proveedor real, y hace el POST contra un servidor
 * local. Útil para probar /webhooks/breb sin depender de un PSP real.
 *
 * Uso:
 *   php bin/probar_webhook_breb.php VECI-P1 aprobado
 *   (con el servidor local corriendo y breb_webhook_secret configurado
 *   igual en config/config.php)
 */

require __DIR__ . '/../src/bootstrap.php';

$referencia = $argv[1] ?? 'VECI-P1';
$estado = $argv[2] ?? 'aprobado';
$url = (string) config('app.url', 'http://localhost:8000') . '/webhooks/breb';
$secreto = (string) config('breb_webhook_secret', '');

if ($secreto === '') {
    fwrite(STDERR, "Falta breb_webhook_secret en config/config.php.\n");
    exit(1);
}

$cuerpo = json_encode(['referencia' => $referencia, 'estado' => $estado, 'monto' => 25000], JSON_UNESCAPED_UNICODE);
$firma = hash_hmac('sha256', $cuerpo, $secreto);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['content-type: application/json', 'X-Breb-Signature: ' . $firma],
    CURLOPT_POSTFIELDS     => $cuerpo,
]);
$respuesta = curl_exec($ch);
$codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP {$codigo}: {$respuesta}\n";
