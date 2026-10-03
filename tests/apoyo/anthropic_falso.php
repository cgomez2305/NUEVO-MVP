<?php

declare(strict_types=1);

/**
 * Servidor falso de la API de Anthropic para tests/menu_demo.php:
 *   php -S localhost:8003 tests/apoyo/anthropic_falso.php
 * Responde según el modo escrito en <tmp>/veci-ia-falsa-modo (ok, vacio,
 * servicios, error) y guarda la última petición en <tmp>/veci-ia-falsa-ultima.json.
 */

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$tmp = sys_get_temp_dir();
$cuerpo = (string) file_get_contents('php://input');
file_put_contents($tmp . '/veci-ia-falsa-ultima.json', $cuerpo);
$modo = trim((string) @file_get_contents($tmp . '/veci-ia-falsa-modo')) ?: 'ok';
usleep(400_000);

header('Content-Type: application/json');
if ($modo === 'error') {
    http_response_code(529);
    echo json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']]);
    return;
}

$items = [];
if ($modo === 'ok') {
    for ($i = 1; $i <= 20; $i++) {
        $items[] = ['nombre' => "Empanada {$i}", 'precio' => 2500 + $i * 100, 'categoria' => 'Fritos', 'descripcion' => $i === 1 ? '<b>con ají</b>' : ''];
    }
} elseif ($modo === 'servicios') {
    $items = [['nombre' => 'Corte de cabello', 'precio' => 20000, 'duracion_min' => 33], ['nombre' => 'Cejas', 'precio' => 12000, 'duracion_min' => 15]];
}

echo json_encode([
    'id' => 'msg_prueba', 'type' => 'message', 'role' => 'assistant', 'model' => 'prueba',
    'content' => [['type' => 'text', 'text' => json_encode(['items' => $items], JSON_UNESCAPED_UNICODE)]],
    'stop_reason' => 'end_turn',
    'usage' => ['input_tokens' => 1500, 'output_tokens' => 800, 'cache_read_input_tokens' => 0],
], JSON_UNESCAPED_UNICODE);
