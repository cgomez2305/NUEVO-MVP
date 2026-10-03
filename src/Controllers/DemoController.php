<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DemoIA;
use App\Models\LimiteTasa;
use App\Services\ExtractorMenu;

/**
 * "Sube tu foto sin cuenta": el sitio (tuveci.co) manda la foto de una
 * carta o lista de precios y recibe lo que la IA leyó, sin registrarse.
 *
 *   POST /api/menu-demo   multipart: foto (jpeg/png/webp, hasta 4 MB),
 *                         tipo = pedidos | reservas (opcional)
 *
 * Responde JSON {estado, mensaje, items[]} con CORS solo para los orígenes
 * de config demo_ia.origenes. La foto se lee desde el archivo temporal de
 * la subida y se borra al terminar: no se guarda nada de ella.
 *
 * Cada lectura cuesta, así que hay tres frenos: 2 lecturas al día por IP,
 * un tope diario de lecturas y de tokens para toda la demo, y 20 intentos
 * por hora por IP aunque no lleguen a la IA. El encabezado Origin lo puede
 * falsear quien no use un navegador: lo que de verdad limita el gasto son
 * los topes, no el CORS.
 */
class DemoController
{
    private const MAX_BYTES = 4 * 1024 * 1024;
    private const MAX_PIXELES = 30_000_000;

    public function preflight(array $parametros): void
    {
        header('Vary: Origin');
        $origen = $this->origenPermitido();
        if ($origen === null) {
            http_response_code(403);
            return;
        }
        header('Access-Control-Allow-Origin: ' . $origen);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
    }

    public function leerMenu(array $parametros): void
    {
        header('Vary: Origin');
        header('Cache-Control: no-store');
        $origen = $this->origenPermitido();
        if ($origen === null) {
            $this->responder(403, 'origen_no_permitido', 'Esta demo solo se puede usar desde tuveci.co.');
        }
        header('Access-Control-Allow-Origin: ' . $origen);

        $ip = ip_cliente();
        if (LimiteTasa::excedido('demo_ia_intento', $ip, 20, 3600)) {
            $this->responder(429, 'muchos_intentos', 'Hiciste muchos intentos seguidos. Espera un rato y vuelve a probar.');
        }
        LimiteTasa::registrar('demo_ia_intento', $ip);

        if (post_demasiado_grande()) {
            $this->responder(413, 'foto_grande', 'La foto pesa más de 4 MB. Prueba con una más liviana o tomada más de cerca.');
        }
        $foto = $_FILES['foto'] ?? null;
        $error = is_array($foto) ? (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($error === UPLOAD_ERR_OK && (int) $foto['size'] > self::MAX_BYTES)) {
            $this->responder(413, 'foto_grande', 'La foto pesa más de 4 MB. Prueba con una más liviana o tomada más de cerca.');
        }
        $tmp = $error === UPLOAD_ERR_OK ? (string) $foto['tmp_name'] : '';
        if ($tmp === '' || !is_uploaded_file($tmp) || !$this->esFotoValida($tmp)) {
            $this->responder(400, 'foto_invalida', 'Sube una foto en JPG, PNG o WebP de tu carta o lista de precios.');
        }
        $tipo = ($_POST['tipo'] ?? '') === 'reservas' ? 'reservas' : 'pedidos';

        $porIp = max(1, (int) config('demo_ia.por_ip_al_dia', 2));
        if (LimiteTasa::excedido('demo_ia_ip', $ip, $porIp, 86400)) {
            @unlink($tmp);
            $this->responder(429, 'limite_ip', 'Ya probaste ' . $porIp . ' fotos hoy. Crea tu cuenta gratis y la IA lee tu carta completa.');
        }
        if (!ExtractorMenu::disponible()) {
            @unlink($tmp);
            $this->responder(503, 'no_disponible', 'La demo no está disponible en este momento.');
        }
        $id = DemoIA::reservar(hash_identidad('ip', $ip));
        if ($id === null) {
            @unlink($tmp);
            $this->responder(503, 'tope_diario', 'La demo llegó a su límite de hoy. Vuelve mañana o crea tu cuenta gratis.');
        }
        LimiteTasa::registrar('demo_ia_ip', $ip);

        // Leer una carta puede tomar más de medio minuto.
        set_time_limit(150);
        $maxItems = max(1, min(40, (int) config('demo_ia.max_items', 15)));
        $resultado = $tipo === 'reservas' ? ExtractorMenu::extraerServicios($tmp, $maxItems) : ExtractorMenu::extraer($tmp, $maxItems);
        @unlink($tmp);
        $uso = ExtractorMenu::ultimoUso();
        DemoIA::cerrar($id, $resultado['estado'], $uso['entrada'], $uso['salida']);

        // El sitio debe pintarlos como texto, pero por si acaso no viajan etiquetas.
        $items = array_map(
            static fn (array $item): array => array_map(static fn ($v) => is_string($v) ? trim(strip_tags($v)) : $v, $item),
            array_slice($resultado['items'], 0, $maxItems)
        );
        match ($resultado['estado']) {
            'ok'    => $this->responder(200, 'ok', 'Esto leyó la IA de tu foto. En tu cuenta lo revisas y corriges antes de publicar.', $tipo, $items),
            'vacio' => $this->responder(200, 'vacio', 'No encontramos productos con precio en esta foto. Prueba con una más de cerca y con buena luz.', $tipo),
            default => $this->responder(502, 'fallo', 'No pudimos leer la foto en este momento. Intenta de nuevo en un rato.', $tipo),
        };
    }

    /** El Origin de la petición si está en la lista de demo_ia.origenes; si no, null. */
    private function origenPermitido(): ?string
    {
        $origen = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $permitidos = config('demo_ia.origenes', ['https://tuveci.co', 'https://www.tuveci.co']);

        return $origen !== '' && is_array($permitidos) && in_array($origen, $permitidos, true) ? $origen : null;
    }

    /** JPG, PNG o WebP de verdad (por contenido, no por nombre) y de un tamaño razonable en píxeles. */
    private function esFotoValida(string $ruta): bool
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($ruta) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return false;
        }
        $medidas = @getimagesize($ruta);
        if ($medidas === false) {
            return false;
        }
        [$ancho, $alto] = $medidas;

        // Una imagen chiquita no se lee; una de muchos megapíxeles (aunque
        // pese poco) llena la memoria al reducirla.
        return $ancho >= 100 && $alto >= 100 && $ancho * $alto <= self::MAX_PIXELES;
    }

    /** @param list<array<string, mixed>> $items */
    private function responder(int $codigo, string $estado, string $mensaje, ?string $tipo = null, array $items = []): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_filter([
            'estado'  => $estado,
            'mensaje' => $mensaje,
            'tipo'    => $tipo,
            'items'   => $estado === 'ok' || $estado === 'vacio' ? $items : null,
        ], fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE);
        exit;
    }
}
