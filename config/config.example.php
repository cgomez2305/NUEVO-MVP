<?php
/**
 * Copia este archivo como config.php y ajusta los valores.
 * config.php nunca se sube al repositorio (ver .gitignore).
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'name'    => 'veci',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        // Sin barra al final. Se usa para armar el enlace público de cada tienda.
        'url'    => 'http://localhost:8000',
        'nombre' => 'Veci',
    ],

    // Opcional. Si defines una llave aquí, la pantalla "La IA arma tu tienda"
    // llama a la API de Claude (Anthropic) para leer productos y precios reales
    // de la foto del menú. Sin llave, usa datos de ejemplo para que el flujo
    // funcione igual de principio a fin. Ver src/Services/ExtractorMenu.php.
    'anthropic_api_key' => null,
];
