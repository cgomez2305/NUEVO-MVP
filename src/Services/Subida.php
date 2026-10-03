<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Subida de fotos desde el panel (equipo y portafolio). Valida el tipo real
 * del archivo (no la extensión) y el tamaño, y la re-codifica con
 * Imagen::normalizar: queda enderezada, liviana para datos móviles y SIN
 * metadatos EXIF — en fotos de personas eso incluye la ubicación GPS de
 * donde se tomaron, que nunca debe quedar pública.
 */
class Subida
{
    private const TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_BYTES = 8 * 1024 * 1024;

    /**
     * Un archivo de $_FILES (o una entrada de un input múltiple ya
     * separada). Devuelve la ruta relativa a public/ o null si no vino nada
     * válido.
     *
     * @param array{name?:string, tmp_name?:string, error?:int, size?:int}|null $archivo
     */
    public static function imagen(?array $archivo, string $carpeta, string $prefijo, int $ladoMax = 1200): ?string
    {
        if ($archivo === null || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $archivo['tmp_name'])) {
            return null;
        }
        $mime = mime_content_type((string) $archivo['tmp_name']) ?: '';
        if (!isset(self::TIPOS[$mime]) || (int) $archivo['size'] > self::MAX_BYTES || !preg_match('/^[a-z]+$/', $carpeta)) {
            return null;
        }
        $base = __DIR__ . '/../../public/uploads/' . $carpeta . '/';
        $nombre = $prefijo . '-' . bin2hex(random_bytes(8));
        // Siempre re-codificada como JPEG. Si no se puede (sin GD), no se
        // guarda: el original llevaría sus metadatos (EXIF, GPS) a la web.
        if (Imagen::normalizar((string) $archivo['tmp_name'], $base . $nombre . '.jpg', $ladoMax, 82)) {
            return 'uploads/' . $carpeta . '/' . $nombre . '.jpg';
        }

        return null;
    }

    /**
     * Las entradas de un <input type="file" multiple name="x[]">, separadas
     * en archivos sueltos.
     *
     * @return array<int, array{name:string, tmp_name:string, error:int, size:int}>
     */
    public static function multiples(string $campo): array
    {
        $crudo = $_FILES[$campo] ?? null;
        if (!is_array($crudo) || !is_array($crudo['name'] ?? null)) {
            return [];
        }
        $archivos = [];
        foreach ($crudo['name'] as $i => $nombre) {
            $archivos[] = [
                'name' => (string) $nombre, 'tmp_name' => (string) $crudo['tmp_name'][$i],
                'error' => (int) $crudo['error'][$i], 'size' => (int) $crudo['size'][$i],
            ];
        }

        return $archivos;
    }

    /** Borra un archivo subido (solo dentro de public/uploads/). */
    public static function borrar(?string $ruta): void
    {
        if ($ruta === null || !preg_match('#^uploads/[a-z]+/[a-z0-9-]+\.(jpg|png|webp)$#', $ruta)) {
            return;
        }
        $archivo = __DIR__ . '/../../public/' . $ruta;
        if (is_file($archivo)) {
            @unlink($archivo);
        }
    }
}
