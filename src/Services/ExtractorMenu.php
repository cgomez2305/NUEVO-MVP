<?php

declare(strict_types=1);

namespace App\Services;

/**
 * "La IA arma tu tienda": lee de una foto el catálogo del negocio.
 * Para negocios de tipo 'pedidos' lee productos, precios, categoría y
 * descripción; para 'reservas' lee servicios, precio y duración.
 *
 * Devuelve siempre un resultado con su estado, para que el onboarding le
 * diga al dueño la verdad sobre lo que pasó:
 *   - 'ok'        la IA leyó la foto y encontró ítems.
 *   - 'vacio'     la IA leyó la foto pero no encontró nada con precio
 *                 (no era un menú, salió borrosa o muy de lejos).
 *   - 'fallo'     no se pudo hablar con la API (red, error, rechazo).
 *   - 'sin_llave' no hay ANTHROPIC_API_KEY configurada (instalación de
 *                 prueba): no se llama a nada.
 * Antes cualquier falla caía en silencio a un catálogo de ejemplo
 * ("Bandeja paisa", "Corte de cabello"…) que el dueño podía creer que la
 * IA había leído de su foto.
 *
 * El límite mensual del plan Gratis lo hace cumplir OnboardingController
 * antes de llamar aquí: este servicio no sabe nada de planes.
 */
class ExtractorMenu
{
    private const MODELO = 'claude-opus-5-5';
    private const MAX_ITEMS = 80;
    /** La API rechaza imágenes de más de 5 MB; se deja margen por el base64. */
    private const MAX_BYTES_IMAGEN = 3_500_000;

    /** Tokens de la última llamada a la API (para el tope diario de la demo pública). */
    private static array $ultimoUso = ['entrada' => 0, 'salida' => 0];

    /** @return array{estado:string, items:array<int, array{nombre:string, precio:int, categoria:string, descripcion:?string}>} */
    public static function extraer(string $rutaAbsolutaImagen, ?int $soloPrimeros = null): array
    {
        $prompt = 'Esta es la foto del menú o carta de un negocio de barrio en Colombia '
            . '(restaurante, panadería, tienda, cafetería…). Extrae cada producto que se venda '
            . "con su precio.\n\n"
            . "- nombre: como aparece en el menú, con mayúscula inicial normal (no todo en mayúsculas).\n"
            . "- precio: en pesos colombianos, número entero. Si el menú abrevia los miles "
            . "(\"12\", \"12k\", \"12.\" o \"$12\" para doce mil), conviértelo a 12000. Si un producto "
            . "tiene varios tamaños o precios, crea un ítem por cada uno (\"Jugo de mora en agua\", "
            . "\"Jugo de mora en leche\"). Si no se ve el precio, usa 0.\n"
            . "- categoria: el nombre de la sección del menú donde está (\"Desayunos\", \"Jugos\", "
            . "\"Corrientazo\"…), corto. Si el menú no tiene secciones, agrupa en categorías obvias.\n"
            . "- descripcion: lo que el menú dice que trae (\"con queso y mantequilla\"), o cadena vacía.\n\n"
            . 'No inventes productos que no estén en la foto. Si la foto no es un menú o no se lee, '
            . 'devuelve la lista vacía.'
            . self::recorte($soloPrimeros, 'productos');

        $esquema = self::esquemaLista([
            'nombre'      => ['type' => 'string'],
            'precio'      => ['type' => 'integer'],
            'categoria'   => ['type' => 'string'],
            'descripcion' => ['type' => 'string'],
        ]);

        $lectura = self::leer($rutaAbsolutaImagen, $prompt, $esquema, $soloPrimeros);
        if ($lectura['estado'] !== 'ok') {
            return $lectura;
        }

        $items = [];
        foreach ($lectura['items'] as $item) {
            $nombre = self::textoLimpio($item['nombre'] ?? '', 120);
            if ($nombre === '') {
                continue;
            }
            $categoria = self::textoLimpio($item['categoria'] ?? '', 60);
            $descripcion = self::textoLimpio($item['descripcion'] ?? '', 160);
            $items[] = [
                'nombre'      => $nombre,
                'precio'      => self::precioLimpio($item['precio'] ?? 0),
                'categoria'   => $categoria !== '' ? $categoria : 'General',
                'descripcion' => $descripcion !== '' ? $descripcion : null,
            ];
        }

        return self::resultado($items);
    }

    /** @return array{estado:string, items:array<int, array{nombre:string, precio:int, duracion_min:int}>} */
    public static function extraerServicios(string $rutaAbsolutaImagen, ?int $soloPrimeros = null): array
    {
        $prompt = 'Esta es la foto de la lista de servicios y precios de un negocio de barrio en '
            . 'Colombia (peluquería, barbería, spa, uñas, taller, consultorio…). Extrae cada '
            . "servicio con su precio.\n\n"
            . "- nombre: como aparece en la lista, con mayúscula inicial normal.\n"
            . "- precio: en pesos colombianos, número entero. Si la lista abrevia los miles "
            . "(\"20\", \"20k\" o \"$20\" para veinte mil), conviértelo a 20000. Si dice \"desde\", "
            . "usa ese precio. Si no se ve el precio, usa 0.\n"
            . "- duracion_min: la duración en minutos si está escrita; si no, la duración típica "
            . "de ese servicio en un salón de barrio, en múltiplos de 5.\n\n"
            . 'No inventes servicios que no estén en la foto. Si la foto no es una lista de '
            . 'servicios o no se lee, devuelve la lista vacía.'
            . self::recorte($soloPrimeros, 'servicios');

        $esquema = self::esquemaLista([
            'nombre'       => ['type' => 'string'],
            'precio'       => ['type' => 'integer'],
            'duracion_min' => ['type' => 'integer'],
        ]);

        $lectura = self::leer($rutaAbsolutaImagen, $prompt, $esquema, $soloPrimeros);
        if ($lectura['estado'] !== 'ok') {
            return $lectura;
        }

        $items = [];
        foreach ($lectura['items'] as $item) {
            $nombre = self::textoLimpio($item['nombre'] ?? '', 120);
            if ($nombre === '') {
                continue;
            }
            // Entre 5 minutos y 8 horas, redondeado a 5: es la grilla con la
            // que la agenda calcula cupos.
            $duracion = (int) round(((int) ($item['duracion_min'] ?? 30)) / 5) * 5;
            $items[] = [
                'nombre'       => $nombre,
                'precio'       => self::precioLimpio($item['precio'] ?? 0),
                'duracion_min' => max(5, min(480, $duracion)),
            ];
        }

        return self::resultado($items);
    }

    /**
     * Catálogo de muestra para instalaciones sin llave de Anthropic (modo de
     * prueba): deja recorrer el alta completa. El onboarding lo presenta
     * como ejemplo, nunca como algo leído de la foto.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function catalogoDeEjemplo(string $tipoNegocio): array
    {
        if ($tipoNegocio === 'reservas') {
            return [
                ['nombre' => 'Corte de cabello', 'precio' => 20000, 'duracion_min' => 30],
                ['nombre' => 'Manicure',         'precio' => 18000, 'duracion_min' => 45],
                ['nombre' => 'Peinado',          'precio' => 35000, 'duracion_min' => 60],
                ['nombre' => 'Tinte y color',    'precio' => 70000, 'duracion_min' => 90],
            ];
        }

        return [
            ['nombre' => 'Bandeja paisa',   'precio' => 28000, 'categoria' => 'Comidas', 'descripcion' => null],
            ['nombre' => 'Arepa con queso', 'precio' => 6000,  'categoria' => 'Comidas', 'descripcion' => null],
            ['nombre' => 'Jugo natural',    'precio' => 5000,  'categoria' => 'Bebidas', 'descripcion' => null],
            ['nombre' => 'Café tinto',      'precio' => 2500,  'categoria' => 'Bebidas', 'descripcion' => null],
        ];
    }

    /** @param array<string, array<string, string>> $campos */
    private static function esquemaLista(array $campos): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [
                'items' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'properties'           => $campos,
                        'required'             => array_keys($campos),
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required'             => ['items'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Llama a la API de Claude con la imagen y devuelve los ítems crudos.
     *
     * - Salida estructurada (output_config.format): la respuesta es JSON
     *   válido con el esquema pedido, sin bloques ```json que limpiar.
     * - fallbacks "default": si el modelo declina la petición, la API la
     *   repite en el modelo de respaldo recomendado en vez de devolver el
     *   rechazo. Un rechazo que igual llegue se trata como 'fallo'.
     * - El modelo piensa antes de responder, así que el primer bloque de la
     *   respuesta puede no ser texto: se busca el bloque de tipo "text".
     *
     * @return array{estado:string, items:array<int, mixed>}
     */
    private static function leer(string $ruta, string $prompt, array $esquema, ?int $soloPrimeros = null): array
    {
        self::$ultimoUso = ['entrada' => 0, 'salida' => 0];
        $apiKey = self::urlDePrueba() !== null ? 'prueba' : config('anthropic_api_key');
        if (!is_string($apiKey) || $apiKey === '') {
            return ['estado' => 'sin_llave', 'items' => []];
        }
        if (!function_exists('curl_init')) {
            return ['estado' => 'fallo', 'items' => []];
        }

        $imagen = self::imagenParaApi($ruta);
        if ($imagen === null) {
            return ['estado' => 'fallo', 'items' => []];
        }

        $cuerpo = json_encode([
            'model'         => self::MODELO,
            // Una lectura recortada (la demo del sitio) no necesita tanto espacio.
            'max_tokens'    => $soloPrimeros !== null ? 4000 : 16000,
            'fallbacks'     => 'default',
            'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $esquema]],
            'messages'      => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $imagen['mime'], 'data' => $imagen['base64']]],
                    ['type' => 'text', 'text' => $prompt],
                ],
            ]],
        ], JSON_UNESCAPED_UNICODE);
        if ($cuerpo === false) {
            return ['estado' => 'fallo', 'items' => []];
        }

        $ch = curl_init(self::urlDePrueba() ?? 'https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
                'anthropic-beta: server-side-fallback-2026-07-01',
            ],
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_CONNECTTIMEOUT => 10,
            // Leer una carta larga pensando puede tomar más de medio minuto.
            CURLOPT_TIMEOUT        => 110,
        ]);
        $respuesta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $huboError = curl_errno($ch) !== 0;
        curl_close($ch);

        if ($huboError || !is_string($respuesta) || $codigo !== 200) {
            error_log('ExtractorMenu: la API respondió ' . $codigo . ($huboError ? ' (error de red)' : ''));
            return ['estado' => 'fallo', 'items' => []];
        }

        $json = json_decode($respuesta, true);
        if (is_array($json) && is_array($json['usage'] ?? null)) {
            $uso = $json['usage'];
            self::$ultimoUso = [
                'entrada' => (int) ($uso['input_tokens'] ?? 0) + (int) ($uso['cache_creation_input_tokens'] ?? 0) + (int) ($uso['cache_read_input_tokens'] ?? 0),
                'salida'  => (int) ($uso['output_tokens'] ?? 0),
            ];
        }
        if (!is_array($json) || ($json['stop_reason'] ?? null) === 'refusal') {
            return ['estado' => 'fallo', 'items' => []];
        }

        $texto = null;
        foreach ($json['content'] ?? [] as $bloque) {
            if (is_array($bloque) && ($bloque['type'] ?? null) === 'text' && is_string($bloque['text'] ?? null)) {
                $texto = $bloque['text'];
                break;
            }
        }
        $datos = is_string($texto) ? json_decode($texto, true) : null;
        if (!is_array($datos) || !is_array($datos['items'] ?? null)) {
            // Respuesta cortada (stop_reason max_tokens) o inesperada.
            return ['estado' => 'fallo', 'items' => []];
        }

        return ['estado' => 'ok', 'items' => array_values(array_filter($datos['items'], 'is_array'))];
    }

    /** ¿Hay con qué leer fotos? (llave de Anthropic configurada). */
    public static function disponible(): bool
    {
        $apiKey = config('anthropic_api_key');

        return self::urlDePrueba() !== null || (is_string($apiKey) && $apiKey !== '');
    }

    /**
     * Solo para pruebas locales (tests/menu_demo.php): con la variable de
     * entorno VECI_IA_PRUEBA_URL apuntando a un servidor falso en localhost,
     * las lecturas van allá en vez de a Anthropic. Solo con el servidor de
     * desarrollo de PHP; nunca la definas en producción.
     */
    private static function urlDePrueba(): ?string
    {
        $url = getenv('VECI_IA_PRUEBA_URL');

        return PHP_SAPI === 'cli-server' && is_string($url) && str_starts_with($url, 'http://localhost:') ? $url : null;
    }

    /** @return array{entrada:int, salida:int} tokens de la última lectura (0 si no llegó a la API). */
    public static function ultimoUso(): array
    {
        return self::$ultimoUso;
    }

    /** Instrucción para leer solo los primeros N ítems (la demo pública no necesita la carta entera). */
    private static function recorte(?int $soloPrimeros, string $que): string
    {
        return $soloPrimeros !== null
            ? "\n\nDevuelve como máximo {$soloPrimeros} {$que}: los primeros que aparezcan en la foto."
            : '';
    }

    /**
     * La foto ya llega normalizada desde el onboarding (Imagen::normalizar),
     * pero una subida antigua o una instalación sin GD puede traer el
     * original del celular: si pesa de más, se reduce en memoria.
     *
     * @return array{mime:string, base64:string}|null
     */
    private static function imagenParaApi(string $ruta): ?array
    {
        if (!is_file($ruta)) {
            return null;
        }
        $mime = mime_content_type($ruta) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        if (filesize($ruta) > self::MAX_BYTES_IMAGEN) {
            $temporal = tempnam(sys_get_temp_dir(), 'veci-menu');
            if ($temporal === false || !Imagen::normalizar($ruta, $temporal, 2000, 82)) {
                return null;
            }
            $datos = file_get_contents($temporal);
            @unlink($temporal);
            $mime = 'image/jpeg';
        } else {
            $datos = file_get_contents($ruta);
        }

        return $datos === false ? null : ['mime' => $mime, 'base64' => base64_encode($datos)];
    }

    /** @param array<int, array<string, mixed>> $items */
    private static function resultado(array $items): array
    {
        $items = array_slice($items, 0, self::MAX_ITEMS);

        return ['estado' => $items === [] ? 'vacio' : 'ok', 'items' => $items];
    }

    private static function textoLimpio(mixed $valor, int $largo): string
    {
        $texto = is_scalar($valor) ? (string) $valor : '';
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');

        return mb_substr($texto, 0, $largo);
    }

    /**
     * Un precio por debajo de 100 pesos no existe en un menú colombiano: si
     * pese a la instrucción llega "12" por "12.000", se lleva a miles.
     */
    private static function precioLimpio(mixed $valor): int
    {
        $precio = is_int($valor) ? $valor : dinero_desde_texto(is_scalar($valor) ? (string) $valor : '');
        if ($precio > 0 && $precio < 100) {
            $precio *= 1000;
        }

        return max(0, min(99_999_999, $precio));
    }
}
