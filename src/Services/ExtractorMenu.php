<?php

declare(strict_types=1);

namespace App\Services;

/**
 * "La IA arma tu tienda": lee productos y precios de la foto del menú.
 *
 * Sin ANTHROPIC_API_KEY configurada (config/config.php → anthropic_api_key),
 * o si la llamada falla por cualquier razón, devuelve un catálogo de ejemplo:
 * así el flujo completo (foto → productos → Bre-B → publicar) funciona de
 * punta a punta sin depender de una API externa ni de una llave pagada.
 */
class ExtractorMenu
{
    /** @return array<int, array{nombre:string, precio:int, categoria:string}> */
    public static function extraer(string $rutaAbsolutaImagen): array
    {
        $apiKey = config('anthropic_api_key');

        if (is_string($apiKey) && $apiKey !== '' && function_exists('curl_init')) {
            $real = self::extraerConClaude($rutaAbsolutaImagen, $apiKey);
            if ($real !== null) {
                return $real;
            }
        }

        return self::catalogoDeEjemplo();
    }

    /** @return array<int, array{nombre:string, precio:int, categoria:string}> */
    private static function catalogoDeEjemplo(): array
    {
        return [
            ['nombre' => 'Bandeja paisa',   'precio' => 28000, 'categoria' => 'Comidas'],
            ['nombre' => 'Arepa con queso', 'precio' => 6000,  'categoria' => 'Comidas'],
            ['nombre' => 'Jugo natural',    'precio' => 5000,  'categoria' => 'Bebidas'],
            ['nombre' => 'Café tinto',      'precio' => 2500,  'categoria' => 'Bebidas'],
        ];
    }

    /** @return array<int, array{nombre:string, precio:int, categoria:string}>|null */
    private static function extraerConClaude(string $ruta, string $apiKey): ?array
    {
        $datosImagen = @file_get_contents($ruta);
        if ($datosImagen === false) {
            return null;
        }

        $mime = mime_content_type($ruta) ?: 'image/jpeg';
        $base64 = base64_encode($datosImagen);

        $cuerpo = json_encode([
            'model'      => 'claude-sonnet-5',
            'max_tokens' => 1024,
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    [
                        'type'   => 'image',
                        'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $base64],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Lee esta foto de un menú de un negocio colombiano. Responde SOLO con '
                            . 'un JSON (sin texto adicional, sin bloque de código) con una lista de '
                            . 'productos: [{"nombre":"...", "precio": 12000, "categoria":"Comidas|Bebidas|General"}]. '
                            . 'El precio va en pesos colombianos, como número entero sin puntos ni símbolo.',
                    ],
                ],
            ]],
        ], JSON_UNESCAPED_UNICODE);

        if ($cuerpo === false) {
            return null;
        }

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => $cuerpo,
            CURLOPT_TIMEOUT    => 30,
        ]);
        $respuesta = curl_exec($ch);
        $huboError = curl_errno($ch) !== 0;
        curl_close($ch);

        if ($huboError || !is_string($respuesta)) {
            return null;
        }

        $json = json_decode($respuesta, true);
        $texto = $json['content'][0]['text'] ?? null;
        if (!is_string($texto)) {
            return null;
        }

        // El modelo a veces envuelve el JSON en ```json ... ``` pese a la instrucción.
        $limpio = preg_replace('/^```(json)?|```$/m', '', $texto);
        $productos = json_decode(trim($limpio ?? $texto), true);
        if (!is_array($productos)) {
            return null;
        }

        $resultado = [];
        foreach ($productos as $item) {
            if (!is_array($item) || !isset($item['nombre'], $item['precio'])) {
                continue;
            }
            $resultado[] = [
                'nombre'    => (string) $item['nombre'],
                'precio'    => (int) $item['precio'],
                'categoria' => (string) ($item['categoria'] ?? 'General'),
            ];
        }

        return $resultado === [] ? null : $resultado;
    }
}
